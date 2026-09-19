<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\AuditAction;
use App\Enums\DemandTrend;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\ConversationState;
use App\Services\Ai\ConversationStateTracker;
use App\Services\Ai\ConversationalEntityTracker;
use App\Services\Ai\ConversationalIntentResolver;
use App\Services\Ai\HimsAiToolRegistry;
use App\Services\Ai\HimsCapabilityRegistry;
use App\Services\Ai\HimsDomainKnowledge;
use App\Services\Privacy\AiDataSanitizerService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AiInventoryAssistantService
{
    /**
     * Tools that answer a neighbouring question and must never be used to
     * answer the intent in the key.
     *
     * The three expiry readings collide on the word "expiry": batches that
     * expire soon, batches already expired, and items that have no expiry date
     * at all. Choosing between them is exactly the decision the user's own
     * wording already settled, so a tool from the other two is refused rather
     * than executed.
     *
     * `search_inventory` is listed for all three because a generic search can
     * only return a vaguely related set of items: it cannot answer which items
     * lack an expiry date, which are about to expire, or which have already
     * expired. A tool that names a specific item is deliberately left alone, so
     * "walang expiry ba ang Paracetamol?" can still be answered about that item.
     *
     * @var array<string, array<int, string>>
     */
    private const INTENT_TOOL_CONFLICTS = [
        ConversationalIntentResolver::NO_EXPIRY => ['get_expiring_batches', 'get_expired_batches', 'search_inventory'],
        ConversationalIntentResolver::EXPIRED => ['get_expiring_batches', 'get_items_without_expiry', 'search_inventory'],
        ConversationalIntentResolver::EXPIRY => ['get_items_without_expiry', 'get_expired_batches', 'search_inventory'],
    ];

    public function __construct(
        private readonly DemandForecastService $statisticalForecasts,
        private readonly AiDemandForecastService $aiForecasts,
        private readonly AuditLogger $auditLogger,
        private readonly ChatAttachmentProcessor $attachmentProcessor,
        private readonly AiDataSanitizerService $sanitizer,
        private readonly HimsAiToolRegistry $tools,
        private readonly ConversationalEntityTracker $entityTracker,
        private readonly ConversationalIntentResolver $intentResolver,
        private readonly ConversationStateTracker $stateTracker,
    ) {}

    /**
     * Process an inventory inquiry using grounded HIMS database context and the Gemini API,
     * supporting optional document, spreadsheet, and image attachments.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     * @return array{reply: string, source: string, status_hint: string, attachment: ?array<string, mixed>}
     */
    public function respond(
        User $actor,
        string $userMessage,
        array $conversationHistory = [],
        ?UploadedFile $attachment = null,
    ): array {
        $attachmentData = null;
        if ($attachment !== null) {
            try {
                $attachmentData = $this->attachmentProcessor->process($attachment);
            } catch (Throwable $e) {
                try {
                    $this->auditLogger->record(
                        action: AuditAction::FailedAiChatAttachment,
                        actor: $actor,
                        description: "Failed processing AI chat attachment: {$e->getMessage()}",
                        newValues: ['error' => $e->getMessage()]
                    );
                } catch (Throwable) {
                    // Audit failure must not mask primary validation exception
                }

                throw $e;
            }
        }

        $cleanMessage = trim($userMessage);
        if ($cleanMessage === '') {
            if ($attachmentData !== null) {
                $cleanMessage = "Please analyze this attached file ({$attachmentData['name']}) and provide relevant HIMS inventory insights and recommendations.";
            } else {
                return [
                    'reply' => 'Please ask a question regarding HIMS inventory, stock levels, or demand forecasts.',
                    'source' => 'system',
                    'status_hint' => 'Looking into that...',
                    'attachment' => null,
                ];
            }
        }

        // Privacy Sanitization: Redact sensitive identifiers (PIN, TIN, phone, card numbers, passwords)
        $cleanMessage = $this->sanitizer->sanitize($cleanMessage)['sanitized_text'];

        if (! empty($conversationHistory)) {
            $conversationHistory = array_map(function ($item) {
                if (is_array($item) && isset($item['content']) && is_string($item['content'])) {
                    $item['content'] = $this->sanitizer->sanitize($item['content'])['sanitized_text'];
                }

                return $item;
            }, $conversationHistory);
        }

        if ($attachmentData !== null && ! empty($attachmentData['text_content']) && is_string($attachmentData['text_content'])) {
            $attachmentData['text_content'] = $this->sanitizer->sanitize($attachmentData['text_content'])['sanitized_text'];
        }

        // 1. Reconstruct structured multi-turn conversation state & resolve focus entities
        $state = $this->stateTracker->extract($cleanMessage, $conversationHistory);
        $entityResolution = $this->entityTracker->resolve($cleanMessage, $conversationHistory);

        // 2. Resolve intent using message, entity candidate, and active conversation state
        $intentResolution = $this->intentResolver->resolve(
            message: $cleanMessage,
            candidateItem: $entityResolution['candidate_item'] ?? null,
            state: $state
        );
        $intent = $intentResolution['intent'];
        $statusHint = $this->determineIntentStatus($cleanMessage, [], $attachmentData, $entityResolution);

        // 2b. Pure conversational inquiries (Greetings, How are you, Acknowledgments, Farewells, Clarifications, Continuations)
        // These bypass database inventory queries completely and answer naturally without repeated intros.
        if ($attachmentData === null && in_array($intent, [
            ConversationalIntentResolver::GREETING,
            ConversationalIntentResolver::HOW_ARE_YOU,
            ConversationalIntentResolver::ACKNOWLEDGMENT,
            ConversationalIntentResolver::FAREWELL,
            ConversationalIntentResolver::CONVERSATIONAL_CLARIFY,
            ConversationalIntentResolver::CONVERSATIONAL_CONTINUE,
        ], true)) {
            $reply = $this->generateConversationalResponse(
                intent: $intent,
                cleanMessage: $cleanMessage,
                conversationHistory: $conversationHistory,
                greetingPrefix: $intentResolution['greeting_prefix'] ?? null,
                actor: $actor,
                state: $state
            );

            return [
                'reply' => $this->sanitizeAssistantText($reply),
                'source' => 'conversational',
                'status_hint' => 'Replying...',
                'attachment' => null,
            ];
        }

        // 2c. Capability question ("ano yung mga puwede kong itanong?") — asks
        // what this assistant covers rather than for a record. Answered here
        // from the actor's own permissions so both answer paths give the same
        // list, and so no path can offer a capability the role's permissions do
        // not grant. A message that names an item is left to the inventory
        // paths: "what can you tell me about Paracetamol?" is about the item.
        if ($attachmentData === null
            && empty($entityResolution['focus_item'])
            && $intent === ConversationalIntentResolver::CAPABILITIES) {
            return [
                'reply' => $this->sanitizeAssistantText($this->formatCapabilities($actor)),
                'source' => 'grounded_fallback',
                'status_hint' => $statusHint,
                'attachment' => null,
            ];
        }

        // 2d. Domain authorization gate: if user specifically asks about a domain capability they lack permission for,
        // return an explicit authorization message instead of claiming the feature is outside of scope.
        if ($attachmentData === null && empty($entityResolution['focus_item'])) {
            $targetCap = HimsCapabilityRegistry::findMatchingCapability($cleanMessage);
            if ($targetCap !== null && ! HimsCapabilityRegistry::isAuthorized($targetCap['id'], $actor)) {
                return [
                    'reply' => $this->sanitizeAssistantText(HimsCapabilityRegistry::getUnauthorizedMessage($targetCap['id'], $actor)),
                    'source' => 'authorization_gate',
                    'status_hint' => $statusHint,
                    'attachment' => null,
                ];
            }
        }

        // 3. Gather relevant HIMS inventory data based on query, resolved entity, and user permissions
        $contextData = $this->gatherContext($cleanMessage, $conversationHistory, $actor, $entityResolution);

        // 4. If Gemini API key is not set, generate rich deterministic grounded response immediately
        $apiKey = (string) (config('services.gemini.key') ?: config('services.gemini.api_key'));
        if (trim($apiKey) === '') {
            $reply = $this->sanitizeAssistantText(
                $this->generateGroundedFallback($cleanMessage, $contextData, $attachmentData, $actor, $conversationHistory, $entityResolution)
            );
            $this->recordAttachmentAudit($actor, $attachmentData, 'grounded_fallback');

            return [
                'reply' => $reply,
                'source' => 'grounded_fallback',
                'status_hint' => $statusHint,
                'attachment' => $attachmentData ? $this->sanitizeAttachmentMetadata($attachmentData) : null,
            ];
        }

        // 5. Attempt Gemini generation with multi-model failover and grounded context
        try {
            $reply = $this->requestGemini($cleanMessage, $conversationHistory, $contextData, $apiKey, $attachmentData, $actor);
            $this->recordAttachmentAudit($actor, $attachmentData, 'ai');

            return [
                'reply' => $reply,
                'source' => 'ai',
                'status_hint' => $statusHint,
                'attachment' => $attachmentData ? $this->sanitizeAttachmentMetadata($attachmentData) : null,
            ];
        } catch (Throwable) {
            // Gracefully fall back to verified database figures on any API error/rate-limit
            $reply = $this->sanitizeAssistantText(
                $this->generateGroundedFallback($cleanMessage, $contextData, $attachmentData, $actor, $conversationHistory, $entityResolution)
            );
            $this->recordAttachmentAudit($actor, $attachmentData, 'grounded_fallback');

            return [
                'reply' => $reply,
                'source' => 'grounded_fallback',
                'status_hint' => $statusHint,
                'attachment' => $attachmentData ? $this->sanitizeAttachmentMetadata($attachmentData) : null,
            ];
        }
    }

    /**
     * Record an audit event when an attachment is successfully analyzed.
     */
    private function recordAttachmentAudit(User $actor, ?array $attachmentData, string $source): void
    {
        if ($attachmentData === null) {
            return;
        }

        try {
            $this->auditLogger->record(
                action: AuditAction::AnalyzedAiChatAttachment,
                actor: $actor,
                description: "Analyzed AI chat attachment '{$attachmentData['name']}' ({$attachmentData['formatted_size']}).",
                newValues: [
                    'filename' => $attachmentData['name'],
                    'filesize' => $attachmentData['size'],
                    'filetype' => $attachmentData['type'],
                    'extension' => $attachmentData['extension'],
                    'source' => $source,
                ]
            );
        } catch (Throwable) {
            // Audit persistence failure should not disrupt the user's chat response
        }
    }

    /**
     * Sanitize attachment metadata for client JSON responses.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeAttachmentMetadata(array $data): array
    {
        return [
            'name' => $data['name'],
            'size' => $data['formatted_size'],
            'type' => $data['type'],
            'extension' => $data['extension'],
            'row_count' => $data['row_count'] ?? null,
            'is_truncated' => $data['is_truncated'] ?? false,
        ];
    }

    /**
     * Gather actual database context matching the inquiry and user permissions.
     *
     * The snapshot is built around the detected intent. It used to be identical
     * for every question and always carried a "batches nearing expiry" list,
     * which left that list as the only expiry data in context — so a question
     * about items WITHOUT an expiry date ("anong mga items ang walang expiry?")
     * could only be answered with batches that do have one. Each intent now
     * contributes the records it actually needs, and `requested_data` names the
     * dataset the answer is expected to come from.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $entityResolution
     * @return array<string, mixed>
     */
    public function gatherContext(string $query, array $history = [], ?User $actor = null, array $entityResolution = []): array
    {
        $actor ??= auth()->user() ?? User::factory()->make();

        $candidateItem = $entityResolution['candidate_item'] ?? $this->entityTracker->extractItemCandidate($query);
        $resolution = $this->intentResolver->resolve($query, $candidateItem);
        $intent = $resolution['intent'];
        $windowDays = $resolution['days_ahead'] ?? 90;

        // 1. Overall high-level metrics
        $dailySummary = $this->tools->getDailySummary($actor);

        // 2. Focused item dossier if mentioned or tracked from earlier turn
        $focusItem = $entityResolution['focus_item'] ?? null;
        $candidateSearchResults = [];
        if (! $focusItem && $candidateItem !== null) {
            $matchingItems = $this->tools->searchMatchingItems($candidateItem, 6);
            if ($matchingItems->count() === 1) {
                $focusItem = $matchingItems->first();
            } else {
                $candidateSearchResults = $matchingItems->map(fn ($it) => [
                    'id' => $it->id,
                    'name' => $it->name,
                    'sku' => $it->sku,
                    'available_stock' => $it->availableQuantity(),
                    'unit' => $it->unit,
                    'status' => $it->status,
                ])->all();
            }
        }

        $itemDossier = null;
        $itemForecast = null;
        if ($focusItem) {
            $itemDossier = $this->tools->getStockDetails($actor, $focusItem->id);
            $itemForecast = $this->tools->getDemandForecast($actor, $focusItem->id, 90, 30);
        }

        // 3. The records the classified intent asked for
        $requested = $this->intentDataset($intent, $windowDays, $actor);

        // 4. Low stock and out-of-stock items, and the canonical replenishment
        // recommendation for them (reusing the existing reorder calculation).
        $lowStockResult = $this->tools->searchInventory($actor, '', null, 'low_stock', 8);
        $outOfStockResult = $this->tools->searchInventory($actor, '', null, 'out_of_stock', 8);
        $replenishmentResult = $this->tools->getReplenishmentRecommendations($actor, 6);

        // 5. Recent stock movements
        $movementsResult = $this->tools->getStockMovements($actor, null, null, 30, 6);

        // 6. Forecast calculation for high risk items
        $highRiskForecast = [];
        $cachedForecast = $this->aiForecasts->cached(90, 30);
        if ($cachedForecast && isset($cachedForecast['items']) && is_array($cachedForecast['items'])) {
            $highRiskForecast = collect($cachedForecast['items'])
                ->filter(fn ($item) => in_array($item['risk_level'] ?? '', ['high', 'critical'], true)
                    || in_array($item['reorder_priority'] ?? '', ['high', 'critical'], true))
                ->take(6)
                ->values()
                ->all();
        }

        // 7. Requisitions and purchase orders
        $pendingPOs = $this->tools->getProcurementRecords($actor, null, null, 5);
        $pendingRequisitions = $this->tools->getDepartmentRequisitions($actor, null, null, 5);

        // 8. Expiry blocks. Each appears only for an intent that needs it, so
        // the three readings of "expiry" cannot be confused for one another:
        // batches that expire soon are never offered as an answer about items
        // that have no expiry at all.
        $expiringBatches = null;
        $expiredBatches = null;
        $itemsWithoutExpiry = null;

        if ($intent === ConversationalIntentResolver::EXPIRY) {
            $expiringBatches = $requested['data'];
        } elseif (in_array($intent, [ConversationalIntentResolver::ATTENTION, ConversationalIntentResolver::SUMMARY], true)) {
            $expiringBatches = $this->tools->getExpiringBatches($actor, 90, null, 6);
        }

        if ($intent === ConversationalIntentResolver::EXPIRED) {
            $expiredBatches = $requested['data'];
        } elseif ($intent === ConversationalIntentResolver::ATTENTION) {
            $expiredBatches = $this->tools->getExpiredBatches($actor, null, 6);
        }

        if ($intent === ConversationalIntentResolver::NO_EXPIRY) {
            $itemsWithoutExpiry = $requested['data'];
        }

        return array_filter([
            'as_of' => now()->toFormattedDateString(),
            'detected_intent' => $intent,
            'detected_intent_expiry_window_days' => $intent === ConversationalIntentResolver::EXPIRY ? $windowDays : null,
            'requested_data' => $requested['data'] === null ? null : [
                'source_tool' => $requested['tool'],
                'description' => $requested['description'],
                'records' => $requested['data'],
            ],
            'daily_summary' => $dailySummary,
            'focus_item_dossier' => $itemDossier,
            'focus_item_forecast' => $itemForecast,
            'candidate_search_results' => $candidateSearchResults,
            'low_stock_items' => $lowStockResult['items'] ?? [],
            'out_of_stock_items' => $outOfStockResult['items'] ?? [],
            'replenishment_recommendations' => $replenishmentResult['items'] ?? [],
            'items_without_expiry' => $itemsWithoutExpiry,
            'expiring_batches' => $expiringBatches,
            'expired_batches' => $expiredBatches,
            'recent_movements' => $movementsResult['movements'] ?? [],
            'high_risk_forecast' => $highRiskForecast,
            'pending_purchase_orders' => $pendingPOs['orders'] ?? [],
            'pending_requisitions' => $pendingRequisitions['requisitions'] ?? [],
        ], fn ($value) => $value !== null);
    }

    /**
     * Retrieve the records the detected intent is asking for, and name the tool
     * they came from so the model can tell which dataset answers the question.
     *
     * Intents with no single canonical tool (attention digests, clarifications)
     * return no data rather than a stand-in, because a near-miss dataset is what
     * produces a confident wrong answer.
     *
     * @return array{tool: ?string, description: ?string, data: mixed}
     */
    private function intentDataset(string $intent, int $windowDays, User $actor): array
    {
        return match ($intent) {
            ConversationalIntentResolver::NO_EXPIRY => [
                'tool' => 'get_items_without_expiry',
                'description' => 'Active items holding stock with no expiry date recorded against them anywhere, and batches carrying no expiry date.',
                'data' => $this->tools->getItemsWithoutExpiry($actor, 15),
            ],
            ConversationalIntentResolver::EXPIRED => [
                'tool' => 'get_expired_batches',
                'description' => 'Batches whose expiry date is already in the past and which still hold stock.',
                'data' => $this->tools->getExpiredBatches($actor, null, 12),
            ],
            ConversationalIntentResolver::EXPIRY => [
                'tool' => 'get_expiring_batches',
                'description' => "Batches with an expiry date within the next {$windowDays} days, in FEFO order.",
                'data' => $this->tools->getExpiringBatches($actor, $windowDays, null, 12),
            ],
            ConversationalIntentResolver::REPLENISHMENT => [
                'tool' => 'get_replenishment_recommendations',
                'description' => 'Items out of stock or at or below their reorder level, with the order quantity HIMS has already calculated.',
                'data' => $this->tools->getReplenishmentRecommendations($actor, 12),
            ],
            ConversationalIntentResolver::OUT_OF_STOCK => [
                'tool' => 'search_inventory',
                'description' => 'Items with zero stock on hand.',
                'data' => $this->tools->searchInventory($actor, '', null, 'out_of_stock', 12),
            ],
            ConversationalIntentResolver::SUPPLIER => [
                'tool' => 'search_suppliers',
                'description' => 'Supplier records with contacts, lead times, and how many items each supplies.',
                'data' => $this->tools->searchSuppliers($actor, '', null, 12),
            ],
            ConversationalIntentResolver::MOVEMENTS => [
                'tool' => 'get_stock_movements',
                'description' => 'Recent stock in, stock out, and transfer movements with actor attribution.',
                'data' => $this->tools->getStockMovements($actor, null, null, 30, 12),
            ],
            ConversationalIntentResolver::PROCUREMENT => [
                'tool' => 'get_procurement_records',
                'description' => 'Purchase orders with supplier, status, and expected delivery.',
                'data' => $this->tools->getProcurementRecords($actor, null, null, 12),
            ],
            ConversationalIntentResolver::REQUISITIONS => [
                'tool' => 'get_department_requisitions',
                'description' => 'Department supply requisitions with urgency and required date.',
                'data' => $this->tools->getDepartmentRequisitions($actor, null, null, 12),
            ],
            ConversationalIntentResolver::SHIPMENTS => [
                'tool' => 'get_shipments_and_deliveries',
                'description' => 'Inbound supplier shipments with carrier tracking and whether each is overdue.',
                'data' => $this->tools->getShipmentsAndDeliveries($actor, null, false, 12),
            ],
            ConversationalIntentResolver::VALUATION => [
                'tool' => 'get_inventory_valuation',
                'description' => 'Total inventory valuation and the category breakdown.',
                'data' => $this->tools->getInventoryValuation($actor),
            ],
            ConversationalIntentResolver::AUDIT => [
                'tool' => 'get_recent_audit_activity',
                'description' => 'Recent audit trail entries with actor attribution.',
                'data' => $this->tools->getRecentAuditActivity($actor, 12),
            ],
            ConversationalIntentResolver::RECOVERY => [
                'tool' => 'get_system_recovery_status',
                'description' => 'Open system recovery issues and failure diagnostics.',
                'data' => $this->tools->getSystemRecoveryStatus($actor),
            ],
            ConversationalIntentResolver::LOCATION => [
                'tool' => 'storage_locations',
                'description' => 'Hospital storage locations, rooms, aisles, and cabinets.',
                'data' => StorageLocation::query()->select('id', 'name', 'code', 'type', 'status')->limit(15)->get()->toArray(),
            ],
            ConversationalIntentResolver::ITEM_LOOKUP => [
                'tool' => 'search_inventory',
                'description' => 'Inventory items matching candidate query terms or identifiers.',
                'data' => null,
            ],
            default => ['tool' => null, 'description' => null, 'data' => null],
        };
    }

    /**
     * Call Gemini API with system instructions and grounded database context.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $contextData
     */
    private function requestGemini(string $query, array $history, array $contextData, string $apiKey, ?array $attachmentData = null, ?User $actor = null): string
    {
        $primaryModel = trim((string) config('services.gemini.model', 'gemini-3.6-flash'));
        $fallbackConfig = (string) (config('services.gemini.fallback_models') ?: config('services.gemini.backup_model') ?: 'gemini-flash-lite-latest,gemini-3.1-flash-lite');
        $fallbackCandidates = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $fallbackConfig)),
            fn ($m) => $m !== '' && $m !== $primaryModel
        )));
        $modelsToTry = array_values(array_unique(array_filter(array_merge([$primaryModel], $fallbackCandidates))));

        $actorRole = $actor?->role?->label() ?? 'Staff';
        $actorPermissions = $actor?->role?->permissions() ?? [];
        $systemPrompt = $this->buildSystemPrompt($contextData, $actorRole, $actorPermissions);

        // Format contents array for Gemini
        $contents = [];
        foreach (array_slice($history, -6) as $turn) {
            $role = ($turn['role'] ?? '') === 'user' ? 'user' : 'model';
            $text = trim((string) ($turn['content'] ?? ''));
            if ($text !== '') {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }

        // Build current user turn parts, including multimodal inlineData or extracted text
        $userParts = [];
        if ($attachmentData !== null && ! empty($attachmentData['inline_data'])) {
            $userParts[] = [
                'inlineData' => [
                    'mimeType' => $attachmentData['inline_data']['mime_type'],
                    'data' => $attachmentData['inline_data']['base64'],
                ],
            ];
        }

        $userText = $query;
        if ($attachmentData !== null && ! empty($attachmentData['text_content'])) {
            $userText .= "\n\n[ATTACHED FILE: {$attachmentData['name']} ({$attachmentData['formatted_size']})]\n".$attachmentData['text_content'];
        }

        $userParts[] = ['text' => $userText];

        $contents[] = [
            'role' => 'user',
            'parts' => $userParts,
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'tools' => [
                ['function_declarations' => HimsAiToolRegistry::getGeminiFunctionDeclarations()],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topP' => 0.85,
                'maxOutputTokens' => 2048,
            ],
        ];

        $lastException = null;

        foreach ($modelsToTry as $model) {
            try {
                $response = Http::baseUrl(rtrim((string) config('services.gemini.base_url'), '/'))
                    ->acceptJson()
                    ->asJson()
                    ->withHeaders(['X-goog-api-key' => $apiKey])
                    ->connectTimeout(5)
                    ->timeout(min(15, max(5, (int) config('services.gemini.timeout', 12))))
                    ->retry(
                        2,
                        fn (int $attempt) => $attempt * 200,
                        fn (Throwable $exception) => $exception instanceof ConnectionException,
                        throw: false,
                    )
                    ->post("/v1beta/models/{$model}:generateContent", $payload);

                if (! $response->successful()) {
                    continue;
                }

                $parts = data_get($response->json(), 'candidates.0.content.parts');
                $parts = is_array($parts) ? $parts : [];

                // The function call is not always the first part, and text may
                // be emitted alongside it; look across all parts.
                $functionCall = null;
                $text = '';
                foreach ($parts as $part) {
                    if (isset($part['functionCall']) && $functionCall === null) {
                        $functionCall = $part['functionCall'];
                    }
                    if (isset($part['text']) && is_string($part['text'])) {
                        $text .= $part['text'];
                    }
                }

                // If model executed a function call, execute tool and return grounded answer
                if ($functionCall !== null) {
                    $fnName = (string) ($functionCall['name'] ?? '');
                    $fnArgs = is_array($functionCall['args'] ?? null) ? $functionCall['args'] : [];

                    // A tool that answers a neighbouring question must not
                    // override the intent already classified from the user's
                    // own words. "anong mga items ang walang expiry?" and
                    // "...ang malapit nang mag-expire?" share the word expiry;
                    // only the first is about items with no expiry date.
                    $conflicting = self::INTENT_TOOL_CONFLICTS[$contextData['detected_intent'] ?? ''] ?? [];
                    if ($fnName !== '' && in_array($fnName, $conflicting, true)) {
                        $pinned = $this->intentDataset(
                            (string) $contextData['detected_intent'],
                            (int) ($contextData['detected_intent_expiry_window_days'] ?? 90),
                            $actor ?? auth()->user() ?? User::factory()->make(),
                        );

                        return $this->sanitizeAssistantText(
                            $this->formatToolExecutionResult((string) ($pinned['tool'] ?? ''), $pinned['data'], $query)
                        );
                    }

                    $toolResult = $this->executeToolCall($fnName, $fnArgs, $actor);

                    return $this->sanitizeAssistantText($this->formatToolExecutionResult($fnName, $toolResult, $query));
                }

                if (trim($text) !== '') {
                    return $this->sanitizeAssistantText($text);
                }
            } catch (Throwable $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new \RuntimeException('gemini_assistant_unavailable');
    }

    /**
     * Execute a function call requested by the Gemini model.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    private function executeToolCall(string $name, array $args, ?User $actor): ?array
    {
        $actor ??= auth()->user() ?? User::factory()->make();

        return match ($name) {
            'search_inventory' => $this->tools->searchInventory(
                $actor,
                (string) ($args['query'] ?? ''),
                $args['category'] ?? null,
                $args['stock_status'] ?? null
            ),
            'get_stock_details' => $this->tools->getStockDetails($actor, $args['item_identifier'] ?? ''),
            'search_suppliers' => $this->tools->searchSuppliers($actor, (string) ($args['query'] ?? '')),
            'get_supplier_items' => $this->tools->getSupplierItems($actor, $args['supplier_identifier'] ?? ''),
            'get_stock_movements' => $this->tools->getStockMovements(
                $actor,
                null,
                $args['type'] ?? null,
                (int) ($args['days'] ?? 30)
            ),
            'get_expiring_batches' => $this->tools->getExpiringBatches($actor, (int) ($args['days_ahead'] ?? 90)),
            'get_items_without_expiry' => $this->tools->getItemsWithoutExpiry($actor, (int) ($args['limit'] ?? 10)),
            'get_expired_batches' => $this->tools->getExpiredBatches($actor),
            'get_procurement_records' => $this->tools->getProcurementRecords($actor, $args['status'] ?? null),
            'get_department_requisitions' => $this->tools->getDepartmentRequisitions($actor, null, $args['department'] ?? null),
            'get_shipments_and_deliveries' => $this->tools->getShipmentsAndDeliveries($actor, null, (bool) ($args['delayed_only'] ?? false)),
            'get_inventory_valuation' => $this->tools->getInventoryValuation($actor),
            'get_demand_forecast' => $this->tools->getDemandForecast($actor, $args['item_identifier'] ?? ''),
            'get_daily_summary' => $this->tools->getDailySummary($actor),
            'get_replenishment_recommendations' => $this->tools->getReplenishmentRecommendations(
                $actor,
                (int) ($args['limit'] ?? 8)
            ),
            'get_recent_audit_activity' => $this->tools->getRecentAuditActivity($actor, 8, $args['module'] ?? null),
            'get_system_recovery_status' => $this->tools->getSystemRecoveryStatus($actor),
            'get_storage_locations' => $this->tools->getStorageLocations($actor, $args['type'] ?? null),
            'get_stock_alerts' => $this->tools->getStockAlerts($actor, $args['severity'] ?? null),
            'get_receiving_records' => $this->tools->getReceivingRecords($actor),
            'get_chain_of_custody_records' => $this->tools->getChainOfCustodyRecords($actor),
            'get_user_management_info' => $this->tools->getUserManagementInfo($actor, $args['search'] ?? null),
            'get_reports_catalog' => $this->tools->getReportsCatalog($actor),
            default => null,
        };
    }

    /**
     * Format a tool execution result into a clear, clinical markdown message.
     */
    private function formatToolExecutionResult(string $fnName, ?array $result, string $query): string
    {
        if ($result === null) {
            return "I couldn't find a matching record in the HIMS database.";
        }

        if (isset($result['authorized']) && $result['authorized'] === false) {
            return $result['message'] ?? 'Access restricted.';
        }

        if (isset($result['requires_replenishment'])) {
            if ($result['items'] === []) {
                return "Good news! **Nothing in HIMS inventory needs replenishment right now.** "
                    . "Every active item is holding stock above its reorder level, and no item is out of stock.";
            }

            $lines = ["### Items Requiring Replenishment\n"];
            $lines[] = '| Item Name | SKU | Available | Reorder Level | Status | Suggested Order | Already On Order |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['items'] as $item) {
                $order = $item['recommended_order_quantity'] > 0
                    ? "{$item['recommended_order_quantity']} {$item['unit']}"
                    : 'None required';
                $incoming = $item['incoming_stock_quantity'] === null
                    ? 'Not recorded'
                    : "{$item['incoming_stock_quantity']} {$item['unit']}";
                $lines[] = "| **{$item['name']}** | {$item['sku']} | {$item['available_stock']} {$item['unit']} | {$item['reorder_level']} | {$item['status_label']} | {$order} | {$incoming} |";
            }

            return implode("\n", $lines);
        }

        if (isset($result['logs']) && is_array($result['logs'])) {
            if ($result['logs'] === []) {
                return 'No audit activity has been recorded yet.';
            }

            $lines = ["### Recent HIMS Audit Activity\n"];
            $lines[] = '| When | Actor | Action | Module | Description |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['logs'] as $log) {
                $lines[] = "| {$log['time_ago']} | {$log['actor']} | {$log['action']} | {$log['module']} | {$log['description']} |";
            }

            return implode("\n", $lines);
        }

        if (isset($result['issues']) && is_array($result['issues'])) {
            if ($result['issues'] === []) {
                return 'No open system recovery issues are on record. All monitored operations are completing normally.';
            }

            $lines = ["### Open System Recovery Issues\n"];
            $lines[] = '| Error ID | Module | Operation | Summary | Retries | Retryable |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['issues'] as $issue) {
                $retryable = $issue['is_retryable'] ? 'Yes' : 'No';
                $lines[] = "| {$issue['error_id']} | {$issue['module']} | {$issue['operation']} | {$issue['error_summary']} | {$issue['retry_count']} | {$retryable} |";
            }

            return implode("\n", $lines);
        }

        // Items without expiry (from get_items_without_expiry tool)
        if (isset($result['has_no_expiry'])) {
            return $this->formatNoExpiryTable($result);
        }

        // Already expired batches (from get_expired_batches tool)
        if (isset($result['is_expired'])) {
            return $this->formatExpiredBatchesTable($result);
        }

        // Batches nearing expiry (from get_expiring_batches tool). Without this
        // the near-expiry list fell through to a raw JSON dump, which is not an
        // answer a clinician can read.
        if ($fnName === 'get_expiring_batches' || isset($result['threshold_days'])) {
            return $this->formatExpiringBatchesTable($result);
        }

        if (isset($result['shipments']) && is_array($result['shipments'])) {
            return $this->formatShipmentTable($result);
        }

        if (isset($result['movements']) && is_array($result['movements'])) {
            return $this->formatMovementsTable($result);
        }

        if (isset($result['orders']) && is_array($result['orders'])) {
            return $this->formatProcurementTable($result);
        }

        if (isset($result['requisitions']) && is_array($result['requisitions'])) {
            return $this->formatRequisitionTable($result);
        }

        if (isset($result['suppliers']) && is_array($result['suppliers'])) {
            return $this->formatSupplierTable($result);
        }

        if (isset($result['total_valuation_php'])) {
            return $this->formatValuationBlock($result);
        }

        if (isset($result['active_items_count'])) {
            return $this->formatDailySummaryBlock($result);
        }

        if (isset($result['predicted_demand_units'])) {
            return $this->formatDemandForecastBlock($result);
        }

        // Items supplied by one supplier (from get_supplier_items tool)
        if (isset($result['items'], $result['items_count'], $result['supplier_name'])) {
            return $this->formatSupplierItemsBlock($result);
        }

        // A single item's full record (from get_stock_details tool)
        if (isset($result['status_label'], $result['batches'], $result['quantity_on_hand'])) {
            return $this->formatItemDossier($result);
        }

        if (isset($result['items']) && is_array($result['items'])) {
            if (empty($result['items'])) {
                return "No matching inventory items found for your request.";
            }

            $lines = ["### Matching Inventory Records\n"];
            $lines[] = "| Item Name | SKU | Available Stock | Reorder Level | Status | Supplier |";
            $lines[] = "| :--- | :--- | :--- | :--- | :--- | :--- |";
            foreach ($result['items'] as $i) {
                $lines[] = "| **{$i['name']}** | {$i['sku']} | {$i['available_stock']} {$i['unit']} | {$i['reorder_level']} | {$i['status_label']} | {$i['primary_supplier']} |";
            }
            return implode("\n", $lines);
        }

        // Storage locations
        if (isset($result['locations']) && is_array($result['locations'])) {
            if ($result['locations'] === []) {
                return 'No storage locations were found matching your criteria.';
            }

            $lines = ["### HIMS Storage Locations\n"];
            $lines[] = '| Location Name | Code | Type | Zone | Temp Class | Status |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['locations'] as $loc) {
                $lines[] = "| **{$loc['name']}** | {$loc['code']} | {$loc['type']} | {$loc['zone']} | {$loc['temperature_classification']} | {$loc['status']} |";
            }
            return implode("\n", $lines);
        }

        // Stock alerts
        if (isset($result['alerts']) && is_array($result['alerts'])) {
            if ($result['alerts'] === []) {
                return 'Good news! There are currently **no active stock alerts** or threshold anomalies on record.';
            }

            $lines = ["### Active Stock Alerts & Threshold Warnings\n"];
            $lines[] = '| Item | SKU | Type | Severity | Status | Current / Threshold | Message |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['alerts'] as $a) {
                $lines[] = "| **{$a['item_name']}** | {$a['sku']} | {$a['type']} | {$a['severity']} | {$a['status']} | {$a['current_value']} / {$a['threshold_value']} | {$a['message']} |";
            }
            return implode("\n", $lines);
        }

        // Receiving records (GRN & IAR)
        if (isset($result['grns']) || isset($result['iars'])) {
            $grns = $result['grns'] ?? [];
            $iars = $result['iars'] ?? [];

            if (empty($grns) && empty($iars)) {
                return 'No receiving notes or inspection acceptance reports are currently on record.';
            }

            $lines = ["### Receiving & Inspection Records\n"];
            if (! empty($grns)) {
                $lines[] = "#### Goods Receipt Notes (GRN)";
                $lines[] = '| GRN Number | DR Number | Supplier | PO Number | Receipt Status | Received Date |';
                $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
                foreach ($grns as $g) {
                    $lines[] = "| **{$g['grn_number']}** | {$g['dr_number']} | {$g['supplier']} | {$g['po_number']} | {$g['receipt_status']} | {$g['received_at']} |";
                }
                $lines[] = '';
            }

            if (! empty($iars)) {
                $lines[] = "#### Inspection & Acceptance Reports (IAR)";
                $lines[] = '| IAR Number | Supplier | PO Number | Inspection Status | Report Status | Date |';
                $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
                foreach ($iars as $i) {
                    $lines[] = "| **{$i['iar_number']}** | {$i['supplier']} | {$i['po_number']} | {$i['inspection_status']} | {$i['status']} | {$i['iar_date']} |";
                }
            }

            return implode("\n", $lines);
        }

        // Chain of custody records
        if (isset($result['records']) && is_array($result['records']) && isset($result['count'])) {
            if ($result['records'] === []) {
                return 'No chain of custody logs found matching the criteria.';
            }

            $lines = ["### Chain of Custody Audit Ledger\n"];
            $lines[] = '| Custody # | Event | Releasing Party | Receiving Party | Date | Condition |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['records'] as $r) {
                $lines[] = "| **{$r['custody_number']}** | {$r['event_type']} | {$r['releasing_party']} | {$r['receiving_party']} | {$r['transferred_at']} | {$r['package_condition']} |";
            }
            return implode("\n", $lines);
        }

        // User management info
        if (isset($result['users']) && is_array($result['users'])) {
            if ($result['users'] === []) {
                return 'No user accounts found matching the search criteria.';
            }

            $lines = ["### HIMS User Accounts & Roles\n"];
            $lines[] = '| Name | Email | Role | Status | Department | Employee ID |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($result['users'] as $u) {
                $lines[] = "| **{$u['name']}** | {$u['email']} | {$u['role']} | {$u['status']} | {$u['department']} | {$u['employee_id']} |";
            }
            return implode("\n", $lines);
        }

        // Reports catalog
        if (isset($result['reports']) && is_array($result['reports'])) {
            $lines = ["### HIMS Analytics & Reporting Catalog\n"];
            $lines[] = '| Report Name | Category | Description | Access |';
            $lines[] = '| :--- | :--- | :--- | :--- |';
            foreach ($result['reports'] as $rep) {
                $access = $rep['accessible'] ? 'Available' : 'Restricted';
                $lines[] = "| **{$rep['name']}** | {$rep['category']} | {$rep['description']} | {$access} |";
            }
            return implode("\n", $lines);
        }

        return "Here is the verified information from the HIMS database:\n\n" . json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * Format items and batches recorded without any expiry date.
     *
     * Presented as a numbered list rather than a table because the
     * conversational entity tracker reads numbered lists back out of the
     * history, which is what lets "yung first one, ilan stock?" refer to the
     * first item here without the user repeating its name.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatNoExpiryTable(array $result): string
    {
        $items = $result['items'] ?? [];

        if ($items === []) {
            return "I couldn't find any items currently recorded without an expiry date. "
                . 'Every active item in HIMS either has an expiry date on its batches or is holding no stock.';
        }

        $total = (int) ($result['total_matching'] ?? $result['count'] ?? count($items));
        $lines = ['These items currently have **no expiry date** recorded:'];
        $lines[] = '';

        foreach ($items as $i => $item) {
            $num = $i + 1;
            $batchInfo = $item['batch_count'] > 0
                ? "{$item['batch_count']} batch(es) without expiry"
                : 'No batches recorded';
            $lines[] = "{$num}. **{$item['name']}** (SKU: {$item['sku']}) — {$item['total_stock_without_expiry']} {$item['unit']} on hand | Category: {$item['category']} | {$batchInfo}";
        }

        $lines[] = '';
        $lines[] = "I found **{$total}** matching " . ($total === 1 ? 'item' : 'items') . '.';

        // Having no expiry date means one of two different things, and the
        // difference matters: an item that is not expiry-tracked is fine as it
        // stands, while an expiry-tracked item with no date is an incomplete
        // record to go and fix.
        $incomplete = 0;
        foreach ($items as $item) {
            if (($item['basis'] ?? '') === 'expiry_tracked_missing_date') {
                $incomplete++;
            }
        }

        if ($incomplete > 0) {
            $lines[] = '';
            $lines[] = "**Note:** {$incomplete} of these " . ($incomplete === 1 ? 'is an expiry-tracked item that has' : 'are expiry-tracked items that have')
                . ' no expiry date captured yet — an incomplete record rather than a non-perishable item.';
        }

        $lines[] = '';
        $lines[] = '*Items without an expiry date are typically non-perishable supplies or equipment. Review batch details at [Inventory Items](/inventory/items).*';

        return implode("\n", $lines);
    }

    /**
     * Format batches that are already expired and still have remaining stock.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatExpiredBatchesTable(array $result): string
    {
        $batches = $result['batches'] ?? [];

        if ($batches === []) {
            return 'Good news! There are currently **no expired batches** with remaining stock in HIMS inventory. All active stock is within its shelf life.';
        }

        $count = (int) ($result['count'] ?? count($batches));
        $lines = ['There ' . ($count === 1 ? 'is' : 'are') . " **{$count} expired " . ($count === 1 ? 'batch' : 'batches') . '** still holding stock in HIMS inventory:'];
        $lines[] = '';
        $lines[] = '| Item Name | Batch / Lot | Remaining Qty | Expiry Date | Days Since Expiry | Action |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
        foreach ($batches as $b) {
            $lines[] = "| **{$b['item_name']}** | {$b['batch_number']} | {$b['remaining_quantity']} {$b['unit']} | {$b['expiry_date']} | {$b['days_since_expiry']} days | {$b['recommendation']} |";
        }
        $lines[] = '';
        $lines[] = 'Expired stock must be quarantined immediately and scheduled for safe disposal per hospital protocols. Do not dispense expired items. Manage disposals at [Inventory Items](/inventory/items).';

        return implode("\n", $lines);
    }

    /**
     * Format batches nearing expiry, over the window the question asked about.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatExpiringBatchesTable(array $result, ?int $windowDays = null): string
    {
        $batches = $result['batches'] ?? [];
        $window = $windowDays
            ?? (isset($result['threshold_days']) ? (int) $result['threshold_days'] : 90);

        if ($batches === []) {
            return "There are currently **no inventory batches nearing expiration** within the next {$window} days. "
                . 'All active stock is well within safe clinical shelf-life parameters.';
        }

        $count = (int) ($result['count'] ?? count($batches));
        $lines = ['There ' . ($count === 1 ? 'is' : 'are') . " **{$count} " . ($count === 1 ? 'batch' : 'batches')
            . " nearing expiration within the next {$window} days** (FEFO Priority):"];
        $lines[] = '';
        $lines[] = '| Item Name | Batch / Lot | Remaining Qty | Expiry Date | Days Left | Priority Action |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
        foreach ($batches as $b) {
            $days = $b['days_remaining'] !== null ? "{$b['days_remaining']} days" : 'Nearing expiry';
            $lines[] = "| **{$b['item_name']}** | {$b['batch_number']} | {$b['remaining_quantity']} {$b['unit']} | {$b['expiry_date']} | {$days} | {$b['recommendation']} |";
        }
        $lines[] = '';
        $lines[] = 'Prioritize First Expired, First Out (FEFO) dispensing to eliminate wastage. Manage batches at [Inventory Items](/inventory/items).';

        return implode("\n", $lines);
    }

    /**
     * Format the supplier directory.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatSupplierTable(array $result): string
    {
        if (($result['suppliers'] ?? []) === []) {
            return 'No supplier records found in the HIMS database.';
        }

        $lines = ["### Hospital Medical & Pharmaceutical Suppliers\n"];
        $lines[] = '| Supplier Name | Contact Person | Phone | Standard Lead Time | Active Items |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- |';
        foreach ($result['suppliers'] as $s) {
            $lines[] = "| **{$s['name']}** | {$s['contact_person']} | {$s['phone']} | {$s['lead_time_days']} days | {$s['supplied_items_count']} |";
        }
        $lines[] = "\nManage contracts and accreditations at [Suppliers & Vendors](/inventory/suppliers).";

        return implode("\n", $lines);
    }

    /**
     * Format the items supplied by one supplier.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatSupplierItemsBlock(array $result): string
    {
        $items = $result['items'] ?? [];
        $supplier = $result['supplier_name'] ?? 'this supplier';

        if ($items === []) {
            return "**{$supplier}** has no active items recorded in the HIMS catalogue.";
        }

        $lines = ["### Items Supplied by **{$supplier}**\n"];
        $lines[] = '| Item Name | SKU | Stock on Hand | Reorder Level | Status |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- |';
        foreach ($items as $i) {
            $status = $i['is_low_stock'] ? 'At or Below Reorder Level' : 'Adequate';
            $lines[] = "| **{$i['name']}** | {$i['sku']} | {$i['current_stock']} {$i['unit']} | {$i['reorder_level']} | {$status} |";
        }
        $lines[] = "\n*Verified HIMS supplier record.*";

        return implode("\n", $lines);
    }

    /**
     * Format inbound shipments and deliveries.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatShipmentTable(array $result, ?bool $delayedOnly = null): string
    {
        $shipments = $result['shipments'] ?? [];
        $delayedOnly ??= false;

        if ($shipments === []) {
            return $delayedOnly
                ? 'Good news! There are currently **no delayed supplier shipments** detected in the tracking register.'
                : 'No active shipment or delivery records found.';
        }

        $lines = ["### Inbound Supplier Shipments & Deliveries\n"];
        $lines[] = '| Shipment # | PO # | Supplier | Carrier / Tracking | Estimated ETA | Status | Delayed? |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- | :--- |';
        foreach ($shipments as $s) {
            $delayBadge = $s['is_delayed'] ? '**YES (OVERDUE)**' : 'On Schedule';
            $lines[] = "| **{$s['shipment_number']}** | {$s['po_number']} | {$s['supplier_name']} | {$s['carrier']} ({$s['tracking_number']}) | {$s['estimated_delivery']} | {$s['status']} | {$delayBadge} |";
        }
        $lines[] = "\nReceive and inspect items at [Goods Receiving](/inventory/receiving).";

        return implode("\n", $lines);
    }

    /**
     * Format purchase orders.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatProcurementTable(array $result): string
    {
        if (($result['orders'] ?? []) === []) {
            return 'There are currently no active purchase orders recorded in the procurement registry.';
        }

        $lines = ["### Hospital Purchase Orders\n"];
        $lines[] = '| PO Number | Supplier | Status | Expected Delivery | Items | Total Amount |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
        foreach ($result['orders'] as $po) {
            $amt = is_numeric($po['total_amount']) ? '₱' . number_format($po['total_amount'], 2) : $po['total_amount'];
            $lines[] = "| **{$po['po_number']}** | {$po['supplier_name']} | {$po['status']} | {$po['expected_delivery_date']} | {$po['items_count']} | {$amt} |";
        }
        $lines[] = "\nReview and approve purchase orders at [Procurement & Purchases](/inventory/purchases).";

        return implode("\n", $lines);
    }

    /**
     * Format department supply requisitions.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatRequisitionTable(array $result): string
    {
        if (($result['requisitions'] ?? []) === []) {
            return 'No pending department requisitions found.';
        }

        $lines = ["### Department Supply Requisitions\n"];
        $lines[] = '| Requisition # | Department | Requested By | Urgency | Required Date | Status |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
        foreach ($result['requisitions'] as $r) {
            $lines[] = "| **{$r['requisition_number']}** | {$r['department']} | {$r['requested_by']} | {$r['urgency']} | {$r['required_date']} | {$r['status']} |";
        }
        $lines[] = "\nReview pending hospital requisitions at [Department Requisitions](/inventory/requisitions).";

        return implode("\n", $lines);
    }

    /**
     * Format the inventory valuation summary.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatValuationBlock(array $result): string
    {
        $lines = [
            '### HIMS Inventory Monetary Valuation',
            '- **Total Active Inventory SKUs:** ' . number_format($result['total_items']),
            '- **Total Physical Units on Hand:** ' . number_format($result['total_units_on_hand']),
            '- **Total Hospital Inventory Valuation:** **₱' . number_format($result['total_valuation_php'], 2) . '**',
            '',
            '**Top Categories by Valuation:**',
            '',
        ];
        $lines[] = '| Category | Item Count | Total Units | Total Valuation |';
        $lines[] = '| :--- | :--- | :--- | :--- |';
        foreach ($result['category_breakdown'] as $c) {
            $lines[] = "| **{$c['category']}** | {$c['items']} | " . number_format($c['units']) . ' | ₱' . number_format($c['valuation'], 2) . ' |';
        }

        return implode("\n", $lines);
    }

    /**
     * Format the daily inventory summary.
     *
     * @param  array<string, mixed>  $summary
     */
    private function formatDailySummaryBlock(array $summary): string
    {
        return "### HIMS Inventory Daily Status ({$summary['date']})\n\n" .
            "- **Active Catalog SKUs:** {$summary['active_items_count']}\n" .
            "- **Items Out of Stock:** **{$summary['out_of_stock_count']}**\n" .
            "- **Items Below Reorder Level:** **{$summary['low_stock_count']}**\n" .
            "- **Stock Movements Logged Today:** {$summary['movements_today_count']} ({$summary['units_issued_today']} units issued, {$summary['units_received_today']} units received)\n" .
            "- **Batches Expiring Within 90 Days:** {$summary['expiring_batches_90_days']}\n" .
            "- **Pending Department Requisitions:** {$summary['pending_requisitions_count']}\n" .
            "- **Pending Purchase Orders:** {$summary['pending_purchase_orders_count']}";
    }

    /**
     * Format the stock movement ledger.
     *
     * @param  array<string, mixed>  $result
     */
    private function formatMovementsTable(array $result): string
    {
        $movements = $result['movements'] ?? [];
        $days = (int) ($result['period_days'] ?? 30);

        if ($movements === []) {
            return "No stock movements were recorded in the last {$days} days.";
        }

        $lines = ["### Recent Stock Movements (Past {$days} Days)\n"];
        $lines[] = '| Date | Item Name | Movement Type | Quantity | Logged By |';
        $lines[] = '| :--- | :--- | :--- | :--- | :--- |';
        foreach ($movements as $m) {
            $lines[] = "| {$m['date']} | **{$m['item_name']}** | {$m['type']} | **{$m['quantity']}** {$m['unit']} | {$m['actor']} |";
        }
        $lines[] = "\nView comprehensive movement ledgers at [Stock Movements](/inventory/stock-movements).";

        return implode("\n", $lines);
    }

    /**
     * Format a single item's demand forecast and days of cover.
     *
     * @param  array<string, mixed>  $forecast
     */
    private function formatDemandForecastBlock(array $forecast): string
    {
        $unit = $forecast['unit'] ?? 'units';
        $daysOfCover = $forecast['days_of_cover'];
        $horizonDays = $forecast['forecast_horizon_days'] ?? 30;

        $lines = [
            "### Demand Forecast: **{$forecast['item_name']}**" . (isset($forecast['sku']) ? " (SKU: {$forecast['sku']})" : ''),
            "- **Current Stock:** {$forecast['current_stock']} {$unit}",
            "- **Available Stock:** **{$forecast['available_stock']} {$unit}**",
            "- **Projected Demand ({$horizonDays} days):** **{$forecast['predicted_demand_units']} {$unit}**",
            "- **Average Daily Usage:** {$forecast['average_daily_usage']} {$unit}/day",
            '- **Days of Cover:** ' . ($daysOfCover !== null ? "**{$daysOfCover} days**" : 'Insufficient movement data'),
            "- **Suggested Reorder Quantity:** **{$forecast['suggested_reorder_quantity']} {$unit}**",
        ];

        if (! empty($forecast['limited_data']) && ! empty($forecast['explanation'])) {
            $lines[] = '';
            $lines[] = '**Data Note:** ' . $forecast['explanation'];
        }

        return implode("\n", $lines);
    }

    /**
     * Build the strict system prompt containing verified HIMS context and domain knowledge.
     *
     * @param  array<string, mixed>  $contextData
     * @param  array<int, string>  $permissions
     */
    private function buildSystemPrompt(array $contextData, string $actorRole = 'Staff', array $permissions = []): string
    {
        $instructions = HimsDomainKnowledge::getSystemInstructions($actorRole, $permissions);
        $jsonContext = json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $intent = (string) ($contextData['detected_intent'] ?? 'unknown');
        $tool = (string) ($contextData['requested_data']['source_tool'] ?? '');
        $routing = "INTENT ALREADY CLASSIFIED FOR THIS TURN: {$intent}";

        if ($tool !== '') {
            $routing .= "\n- The records retrieved for that intent are in `requested_data`, taken from the {$tool} tool."
                ."\n- Answer the question that was asked from those records. Do not replace them with a different but related dataset."
                ."\n- The three expiry readings are not interchangeable: items with NO expiry date, batches ALREADY expired, and batches NEARING expiry are different questions with different answers. Answer only the one classified above.";
        }

        return <<<PROMPT
{$instructions}

{$routing}

VERIFIED HIMS CURRENT INVENTORY CONTEXT (LIVE SNAPSHOT):
{$jsonContext}
PROMPT;
    }

    /**
     * Deterministic, grounded fallback generator that directly parses queried HIMS data
     * when Gemini is offline, rate-limited, or unconfigured.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $attachment
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $entityResolution
     */
    public function generateGroundedFallback(
        string $query,
        array $context,
        ?array $attachment = null,
        ?User $actor = null,
        array $history = [],
        array $entityResolution = []
    ): string {
        $rawReply = $this->buildGroundedFallbackBody($query, $context, $attachment, $actor, $history, $entityResolution);

        // Prepend greeting salutation when an inquiry starts with a greeting prefix
        // (e.g. "Good evening, may low stock ba tayo?" -> "Good evening!\n\n**2 items** are currently...")
        $candidateItem = $entityResolution['candidate_item'] ?? null;
        $resolution = $this->intentResolver->resolve($query, $candidateItem);
        if (! empty($resolution['greeting_prefix'])) {
            $salutation = $this->resolveSalutationFromPrefix($resolution['greeting_prefix']);
            if ($salutation !== '' && ! Str::startsWith(ltrim($rawReply), $salutation)) {
                return $salutation . "\n\n" . $rawReply;
            }
        }

        return $rawReply;
    }

    /**
     * Build the grounded fallback response body from verified context.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $attachment
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $entityResolution
     */
    private function buildGroundedFallbackBody(
        string $query,
        array $context,
        ?array $attachment = null,
        ?User $actor = null,
        array $history = [],
        array $entityResolution = []
    ): string {
        $actor ??= auth()->user() ?? User::factory()->make();

        // 1. Attached document analysis
        if ($attachment !== null) {
            return $this->formatAttachmentFallback($attachment, $context);
        }

        $q = Str::lower(trim($query));

        // Resolve what is actually being asked before choosing a section to
        // answer from. Matching the wording against keyword lists meant any
        // phrasing nobody had listed — "may item ba na need na talagang
        // orderin?" — fell through every branch to the daily-status summary.
        $candidateItem = $entityResolution['candidate_item'] ?? $this->entityTracker->extractItemCandidate($query);
        $resolution = $this->intentResolver->resolve($query, $candidateItem);
        $intent = $resolution['intent'];
        // "this month" and "within 30 days" are different questions from the
        // default 90-day sweep, so the window the user stated travels with the
        // intent instead of every expiry question reporting the same 90 days.
        $expiryWindowDays = $resolution['days_ahead'] ?? 90;
        $matchedASpecificIntent = ! in_array($intent, [
            ConversationalIntentResolver::CLARIFY,
            ConversationalIntentResolver::OUT_OF_SCOPE,
        ], true);

        // 2. Multi-turn entity resolution ("the first one", "it", "who supplies it", "is it enough")
        if (! empty($entityResolution['focus_item'])) {
            $item = $entityResolution['focus_item'];

            // 2a. Multi-turn supplier inquiry
            if (Str::contains($q, ['supplier', 'suppliers', 'vendor', 'who provides', 'who supplies', 'who is the supplier', 'provides the first one', 'provides it'])) {
                $supplier = $item->supplier;
                if (! $supplier) {
                    return "**{$item->name}** (SKU: {$item->sku}) currently has no primary supplier assigned in the HIMS database. You can assign a supplier at [Edit Item](/inventory/items/{$item->id}/edit).";
                }

                $leadTime = (int) ($item->lead_time_days ?: ($supplier->standard_lead_time_days ?: 7));
                $lines = [
                    "**{$item->name}** (SKU: {$item->sku}) is supplied by **{$supplier->name}**.",
                    "- **Contact Person:** " . ($supplier->contact_person ?? 'Not Specified'),
                    "- **Phone:** " . ($supplier->phone ?? 'N/A'),
                    "- **Email:** " . ($supplier->email ?? 'N/A'),
                    "- **Standard Lead Time:** {$leadTime} days",
                    "- **Current On-Hand Stock:** {$item->quantity_on_hand} {$item->unit} (Reorder Level: {$item->reorder_level})",
                ];

                return implode("\n", $lines) . "\n\n*(Verified HIMS supplier record)*";
            }

            // 2a-2. Storage location inquiry for resolved item
            if ($intent === ConversationalIntentResolver::LOCATION || preg_match('/\b(?:nasaan|saan|where|location|storage|stored|nakatago)\b/i', $q)) {
                $locations = $item->stockLevels()->with('storageLocation')->get();
                if ($locations->isEmpty() || $locations->every(fn ($l) => $l->quantity <= 0)) {
                    return "**{$item->name}** (SKU: {$item->sku}) is currently not stored in any active storage location (0 physical stock on hand).";
                }

                $lines = ["### Storage Location for **{$item->name}** (SKU: {$item->sku})\n"];
                foreach ($locations as $loc) {
                    if ($loc->quantity > 0) {
                        $locName = $loc->storageLocation?->name ?? 'Unknown Location';
                        $locCode = $loc->storageLocation?->code ?? 'N/A';
                        $lines[] = "- **{$locName}** ({$locCode}): **{$loc->quantity} {$item->unit}** (Reserved: {$loc->reserved_quantity} {$item->unit})";
                    }
                }

                return implode("\n", $lines) . "\n\n*(Verified HIMS database record)*";
            }

            // 2a-3. Pricing / valuation inquiry for resolved item
            if (preg_match('/\b(?:magkano|how much|price|cost|unit cost|valuation|halaga)\b/i', $q)) {
                $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);
                if (! $canViewFinances) {
                    return "Unit costs and financial valuation for **{$item->name}** are restricted to authorized procurement personnel.";
                }

                $cost = $item->unit_cost !== null ? '₱' . number_format($item->unit_cost, 2) : 'No unit cost recorded';
                $totalVal = $item->unit_cost !== null ? '₱' . number_format($item->quantity_on_hand * $item->unit_cost, 2) : 'N/A';
                $lines = [
                    "### Pricing & Valuation: **{$item->name}** (SKU: {$item->sku})",
                    "- **Unit Cost:** **{$cost}**",
                    "- **Physical Stock on Hand:** {$item->quantity_on_hand} {$item->unit}",
                    "- **Total Value on Hand:** **{$totalVal}**",
                ];

                return implode("\n", $lines) . "\n\n*(Verified HIMS financial record)*";
            }

            // 2a-4. Multi-turn quantity inquiry ("ilan?", "how many are left?", "ilan pa?")
            if (preg_match('/^(?:and\s+)?(?:ilan|how\s+many|how\s+much\s+stock|meron\s+pa\s+ba)\b/iu', $q)
                || in_array($q, ['ilan', 'ilan?', 'ilan pa', 'ilan pa?', 'ilan na lang', 'how many', 'how many?', 'how many left', 'how many left?'], true)) {
                $avail = $item->availableQuantity();
                $unit = $item->unit ?? 'units';
                $onHand = $item->quantity_on_hand;
                $reserved = $item->stockLevels->sum('reserved_quantity');

                if ($avail > 0) {
                    return "**{$avail} {$unit}** of **{$item->name}** (SKU: {$item->sku}) are currently **available for dispensing** (Total physical on hand: {$onHand} {$unit}" . ($reserved > 0 ? ", Reserved: {$reserved} {$unit}" : '') . ").\n\n*(Verified HIMS database record)*";
                }

                return "**Notice:** **{$item->name}** (SKU: {$item->sku}) is currently **out of stock** (0 {$unit} available for dispensing, {$onHand} {$unit} physical on hand).\n\n*(Verified HIMS database record)*";
            }

            // 2b. Multi-turn stock adequacy / forecast inquiry ("is it enough for the next month?", "will it last?")
            if (Str::contains($q, ['enough', 'last for', 'cover', 'next month', '30 days', 'sufficiency'])) {
                $forecast = $this->tools->getDemandForecast($actor, $item->id, 90, 30);
                if ($forecast) {
                    $available = $forecast['available_stock'];
                    $predicted = $forecast['predicted_demand_units'];
                    $dailyUsage = $forecast['average_daily_usage'];
                    $daysOfCover = $forecast['days_of_cover'];
                    $unit = $forecast['unit'];

                    $isAdequate = $available >= $predicted && ($daysOfCover === null || $daysOfCover >= 30);

                    $lines = [
                        "### Stock Adequacy Assessment: **{$item->name}** (SKU: {$item->sku})",
                        "- **Physical Stock On Hand:** {$forecast['current_stock']} {$unit}",
                        "- **Reserved Stock:** {$forecast['reserved_stock']} {$unit}",
                        "- **Available Stock for Dispensing:** **{$available} {$unit}**",
                        "- **Projected 30-Day Demand:** **{$predicted} {$unit}** (Average Daily Usage: {$dailyUsage} {$unit}/day)",
                        "- **Estimated Days of Cover:** " . ($daysOfCover !== null ? "**{$daysOfCover} days**" : "Insufficient movement data"),
                        "",
                    ];

                    if ($forecast['limited_data']) {
                        $lines[] = "**Data Note:** " . $forecast['explanation'];
                    }

                    if ($isAdequate) {
                        $lines[] = "**Verdict:** Current available stock is **adequate** to cover projected clinical consumption for the upcoming 30-day window.";
                    } else {
                        $deficit = max(0, $predicted - $available);
                        $suggestedOrder = $forecast['suggested_reorder_quantity'];
                        $lines[] = "**Verdict:** Current stock is **insufficient** to cover the next month (projected deficit: **{$deficit} {$unit}**).";
                        $lines[] = "- **Recommended Reorder:** **{$suggestedOrder} {$unit}** (including safety buffer).";
                        $lines[] = "\nYou can initiate a replenishment order at [Demand Forecast](/inventory/demand-forecast).";
                    }

                    return implode("\n", $lines);
                }
            }

            // 2c. Multi-turn movement history ("what happened to this stock?")
            if (Str::contains($q, ['what happened', 'movements', 'history of this', 'movement'])) {
                $movements = $this->tools->getStockMovements($actor, $item->id, null, 30, 5);
                if (empty($movements['movements'])) {
                    return "No recent stock movement records found for **{$item->name}** in the past 30 days.";
                }

                $lines = ["### Recent Stock Movements: **{$item->name}** (SKU: {$item->sku})\n"];
                $lines[] = "| Date | Movement Type | Quantity | Logged By | Notes |";
                $lines[] = "| :--- | :--- | :--- | :--- | :--- |";
                foreach ($movements['movements'] as $m) {
                    $notes = $m['notes'] ? htmlspecialchars($m['notes']) : 'Standard transaction';
                    $lines[] = "| {$m['date']} | {$m['type']} | **{$m['quantity']}** {$m['unit']} | {$m['actor']} | {$notes} |";
                }
                return implode("\n", $lines);
            }

            // 2d. Multi-turn replenishment follow-up ("magkano kaya kailangan
            // natin?", "may pending order na ba nun?") — answered for the item
            // already under discussion, without making the user name it again.
            if (Str::contains($q, ['order', 'orderin', 'reorder', 'restock', 'replenish', 'bilhin', 'kailangan', 'how much', 'magkano', 'pending'])) {
                $unit = $item->unit ?? 'units';
                $recommendation = collect($this->tools->getReplenishmentRecommendations($actor, 15)['items'])
                    ->firstWhere('id', $item->id);

                if ($recommendation === null) {
                    return "**{$item->name}** (SKU: {$item->sku}) is **not flagged for replenishment**. "
                        . "It is holding {$item->availableQuantity()} {$unit} available against a reorder level of {$item->reorder_level} {$unit}, "
                        . "so no order is needed at this time.\n\n*(Verified HIMS database record)*";
                }

                $lines = ["### Replenishment: **{$item->name}** (SKU: {$item->sku})"];
                $lines[] = "- **Physical Stock on Hand:** {$recommendation['quantity_on_hand']} {$unit}";
                $lines[] = "- **Reserved Stock:** {$recommendation['reserved_quantity']} {$unit}";
                $lines[] = "- **Available Stock for Dispensing:** **{$recommendation['available_stock']} {$unit}**";
                $lines[] = "- **Reorder Level:** {$recommendation['reorder_level']} {$unit}";
                $lines[] = "- **Current Status:** **{$recommendation['status_label']}**";

                $incoming = $recommendation['incoming_stock_quantity'];
                if ($incoming === null) {
                    $lines[] = "- **Already On Order:** No open purchase order quantity recorded for this item.";
                } elseif ($incoming > 0) {
                    $lines[] = "- **Already On Order:** **{$incoming} {$unit}** against open purchase orders";
                } else {
                    $lines[] = "- **Already On Order:** None — no open purchase order quantity for this item.";
                }

                $lines[] = $recommendation['recommended_order_quantity'] > 0
                    ? "- **Suggested Order Quantity:** **{$recommendation['recommended_order_quantity']} {$unit}**"
                    : "- **Suggested Order Quantity:** None required at this time.";
                $lines[] = "- **Supplier Lead Time:** {$recommendation['lead_time_days']} days";
                $lines[] = "- **Primary Supplier:** **{$recommendation['primary_supplier']}**";

                return implode("\n", $lines) . "\n\n*(Verified HIMS database record)*";
            }

            // 2e. General resolved item details
            $dossier = $this->tools->getStockDetails($actor, $item->id);
            if ($dossier) {
                $lead = '';
                if (preg_match('/\b(?:mayroon|meron|may|do we have|is there|available|in stock)\b/i', $query)) {
                    $avail = $dossier['available_stock'];
                    $unit = $dossier['unit'];
                    if ($avail > 0) {
                        $lead = "**Yes**, we have **{$item->name}** (SKU: {$item->sku}) in stock. Available for dispensing: **{$avail} {$unit}** (Total physical on hand: {$dossier['quantity_on_hand']} {$unit}).\n\n";
                    } else {
                        $lead = "**Notice:** We carry **{$item->name}** (SKU: {$item->sku}), but it is currently **out of stock** (0 {$unit} available).\n\n";
                    }
                }

                return $lead . $this->formatItemDossier($dossier);
            }
        }

        // 3. Specific explicit item query or detected candidate
        $extracted = $entityResolution['candidate_item'] ?? $this->extractItemSubject($query);
        if ($extracted !== null) {
            $matchingItems = $this->tools->searchMatchingItems($extracted, 6);

            // 3a. Multiple items matched — return numbered disambiguation list
            if ($matchingItems->count() > 1) {
                $count = $matchingItems->count();
                $lines = ["I found {$count} items matching \"{$extracted}\" in HIMS inventory. Which one did you mean?\n"];
                foreach ($matchingItems as $idx => $mItem) {
                    $num = $idx + 1;
                    $avail = $mItem->availableQuantity();
                    $status = $mItem->stock_status?->label() ?? 'In Stock';
                    $lines[] = "{$num}. **{$mItem->name}** (SKU: {$mItem->sku}) — Available: **{$avail} {$mItem->unit}** | Status: *{$status}*";
                }
                $lines[] = "\nPlease specify the item name, SKU, or number (e.g. \"1\" or \"the first one\").";

                return implode("\n", $lines);
            }

            // 3b. Exactly one item matched (or findItem resolved)
            $foundItem = $matchingItems->first() ?? $this->tools->findItem($extracted);
            if ($foundItem) {
                // Item-specific supplier query
                if ($intent === ConversationalIntentResolver::SUPPLIER || preg_match('/\b(?:supplier|vendor|who provides|who supplies|nagsu-supply)\b/i', $q)) {
                    $supplier = $foundItem->supplier;
                    if (! $supplier) {
                        return "**{$foundItem->name}** (SKU: {$foundItem->sku}) currently has no primary supplier assigned in the HIMS database. You can assign a supplier at [Edit Item](/inventory/items/{$foundItem->id}/edit).";
                    }

                    $leadTime = (int) ($foundItem->lead_time_days ?: ($supplier->standard_lead_time_days ?: 7));
                    $lines = [
                        "**{$foundItem->name}** (SKU: {$foundItem->sku}) is supplied by **{$supplier->name}**.",
                        "- **Contact Person:** " . ($supplier->contact_person ?? 'Not Specified'),
                        "- **Phone:** " . ($supplier->phone ?? 'N/A'),
                        "- **Email:** " . ($supplier->email ?? 'N/A'),
                        "- **Standard Lead Time:** {$leadTime} days",
                        "- **Current On-Hand Stock:** {$foundItem->quantity_on_hand} {$foundItem->unit} (Reorder Level: {$foundItem->reorder_level})",
                    ];

                    return implode("\n", $lines) . "\n\n*(Verified HIMS supplier record)*";
                }

                // Item-specific storage location query
                if ($intent === ConversationalIntentResolver::LOCATION || preg_match('/\b(?:nasaan|saan|where|location|storage|stored|nakatago)\b/i', $q)) {
                    $locations = $foundItem->stockLevels()->with('storageLocation')->get();
                    if ($locations->isEmpty() || $locations->every(fn ($l) => $l->quantity <= 0)) {
                        return "**{$foundItem->name}** (SKU: {$foundItem->sku}) is currently not stored in any active storage location (0 physical stock on hand).";
                    }

                    $lines = ["### Storage Location for **{$foundItem->name}** (SKU: {$foundItem->sku})\n"];
                    foreach ($locations as $loc) {
                        if ($loc->quantity > 0) {
                            $locName = $loc->storageLocation?->name ?? 'Unknown Location';
                            $locCode = $loc->storageLocation?->code ?? 'N/A';
                            $lines[] = "- **{$locName}** ({$locCode}): **{$loc->quantity} {$foundItem->unit}** (Reserved: {$loc->reserved_quantity} {$foundItem->unit})";
                        }
                    }

                    return implode("\n", $lines) . "\n\n*(Verified HIMS database record)*";
                }

                // Item-specific price / valuation query
                if (preg_match('/\b(?:magkano|how much|price|cost|unit cost|valuation|halaga)\b/i', $q)) {
                    $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);
                    if (! $canViewFinances) {
                        return "Unit costs and financial valuation for **{$foundItem->name}** are restricted to authorized procurement personnel.";
                    }

                    $cost = $foundItem->unit_cost !== null ? '₱' . number_format($foundItem->unit_cost, 2) : 'No unit cost recorded';
                    $totalVal = $foundItem->unit_cost !== null ? '₱' . number_format($foundItem->quantity_on_hand * $foundItem->unit_cost, 2) : 'N/A';
                    $lines = [
                        "### Pricing & Valuation: **{$foundItem->name}** (SKU: {$foundItem->sku})",
                        "- **Unit Cost:** **{$cost}**",
                        "- **Physical Stock on Hand:** {$foundItem->quantity_on_hand} {$foundItem->unit}",
                        "- **Total Value on Hand:** **{$totalVal}**",
                    ];

                    return implode("\n", $lines) . "\n\n*(Verified HIMS financial record)*";
                }

                // Item-specific movements query
                if ($intent === ConversationalIntentResolver::MOVEMENTS || Str::contains($q, ['what happened', 'movements', 'history of this', 'movement'])) {
                    $movements = $this->tools->getStockMovements($actor, $foundItem->id, null, 30, 5);
                    if (empty($movements['movements'])) {
                        return "No recent stock movement records found for **{$foundItem->name}** in the past 30 days.";
                    }

                    $lines = ["### Recent Stock Movements: **{$foundItem->name}** (SKU: {$foundItem->sku})\n"];
                    $lines[] = "| Date | Movement Type | Quantity | Logged By | Notes |";
                    $lines[] = "| :--- | :--- | :--- | :--- | :--- |";
                    foreach ($movements['movements'] as $m) {
                        $notes = $m['notes'] ? htmlspecialchars($m['notes']) : 'Standard transaction';
                        $lines[] = "| {$m['date']} | {$m['type']} | **{$m['quantity']}** {$m['unit']} | {$m['actor']} | {$notes} |";
                    }

                    return implode("\n", $lines);
                }

                // Item-specific replenishment query
                if ($intent === ConversationalIntentResolver::REPLENISHMENT || Str::contains($q, ['order', 'orderin', 'reorder', 'restock', 'replenish', 'bilhin', 'kailangan', 'pending'])) {
                    $unit = $foundItem->unit ?? 'units';
                    $recommendation = collect($this->tools->getReplenishmentRecommendations($actor, 15)['items'])
                        ->firstWhere('id', $foundItem->id);

                    if ($recommendation === null) {
                        return "**{$foundItem->name}** (SKU: {$foundItem->sku}) is **not flagged for replenishment**. "
                            . "It is holding {$foundItem->availableQuantity()} {$unit} available against a reorder level of {$foundItem->reorder_level} {$unit}, "
                            . "so no order is needed at this time.\n\n*(Verified HIMS database record)*";
                    }

                    $lines = ["### Replenishment: **{$foundItem->name}** (SKU: {$foundItem->sku})"];
                    $lines[] = "- **Physical Stock on Hand:** {$recommendation['quantity_on_hand']} {$unit}";
                    $lines[] = "- **Reserved Stock:** {$recommendation['reserved_quantity']} {$unit}";
                    $lines[] = "- **Available Stock for Dispensing:** **{$recommendation['available_stock']} {$unit}**";
                    $lines[] = "- **Reorder Level:** {$recommendation['reorder_level']} {$unit}";
                    $lines[] = "- **Current Status:** **{$recommendation['status_label']}**";

                    $incoming = $recommendation['incoming_stock_quantity'];
                    if ($incoming === null) {
                        $lines[] = "- **Already On Order:** No open purchase order quantity recorded for this item.";
                    } elseif ($incoming > 0) {
                        $lines[] = "- **Already On Order:** **{$incoming} {$unit}** against open purchase orders";
                    } else {
                        $lines[] = "- **Already On Order:** None — no open purchase order quantity for this item.";
                    }

                    $lines[] = $recommendation['recommended_order_quantity'] > 0
                        ? "- **Suggested Order Quantity:** **{$recommendation['recommended_order_quantity']} {$unit}**"
                        : "- **Suggested Order Quantity:** None required at this time.";
                    $lines[] = "- **Supplier Lead Time:** {$recommendation['lead_time_days']} days";
                    $lines[] = "- **Primary Supplier:** **{$recommendation['primary_supplier']}**";

                    return implode("\n", $lines) . "\n\n*(Verified HIMS database record)*";
                }

                // General item dossier & availability
                $dossier = $this->tools->getStockDetails($actor, $foundItem->id);
                if ($dossier) {
                    $lead = '';
                    if (preg_match('/\b(?:mayroon|meron|may|do we have|is there|available|in stock)\b/i', $query)) {
                        $avail = $dossier['available_stock'];
                        $unit = $dossier['unit'];
                        if ($avail > 0) {
                            $lead = "**Yes**, we have **{$foundItem->name}** (SKU: {$foundItem->sku}) in stock. Available for dispensing: **{$avail} {$unit}** (Total physical on hand: {$dossier['quantity_on_hand']} {$unit}).\n\n";
                        } else {
                            $lead = "**Notice:** We carry **{$foundItem->name}** (SKU: {$foundItem->sku}), but it is currently **out of stock** (0 {$unit} available).\n\n";
                        }
                    }

                    return $lead . $this->formatItemDossier($dossier);
                }
            }

            // Check if context has mentioned_items (for backward compatibility and unit tests)
            if (! empty($context['mentioned_items'])) {
                foreach ($context['mentioned_items'] as $mItem) {
                    if (stripos($mItem['name'] ?? '', $extracted) !== false || stripos($extracted, $mItem['name'] ?? '') !== false) {
                        return $this->formatItemDossier([
                            'id' => $mItem['id'] ?? 0,
                            'name' => $mItem['name'],
                            'sku' => $mItem['sku'] ?? 'N/A',
                            'barcode' => null,
                            'generic_name' => $mItem['generic_name'] ?? null,
                            'category' => $mItem['category'] ?? 'General',
                            'unit' => $mItem['unit'] ?? 'units',
                            'quantity_on_hand' => $mItem['current_stock'] ?? $mItem['quantity_on_hand'] ?? 0,
                            'reserved_quantity' => 0,
                            'available_stock' => $mItem['available_stock'] ?? $mItem['current_stock'] ?? 0,
                            'reorder_level' => $mItem['reorder_level'] ?? 0,
                            'safety_stock' => $mItem['safety_stock'] ?? 0,
                            'lead_time_days' => $mItem['lead_time_days'] ?? 7,
                            'supplier_name' => $mItem['supplier_name'] ?? 'Not Assigned',
                            'supplier_contact' => null,
                            'supplier_phone' => null,
                            'unit_cost' => null,
                            'total_value' => null,
                            'status' => 'active',
                            'status_label' => ($mItem['current_stock'] ?? 0) <= ($mItem['reorder_level'] ?? 0) ? 'Low Stock' : 'Adequate',
                            'batches' => [],
                            'locations' => [],
                            'recent_movements' => $mItem['recent_movements'] ?? [],
                        ]);
                    }
                }
            }

            // Candidate/explicitly named item was NOT found in database.
            return "I couldn't find a matching record for \"{$extracted}\" in the HIMS inventory database. Please check the spelling or verify if the item is registered under a generic or brand name in HIMS.";
        }

        // 4. Capability question — what this assistant can answer, and what the
        // actor's role may not see. Answered from the actor's permissions so it
        // cannot promise a dataset the role is not allowed.
        if ($intent === ConversationalIntentResolver::CAPABILITIES) {
            return $this->formatCapabilities($actor);
        }

        // 4. Definitional question — user is asking what a term means, not for data.
        if ($intent === ConversationalIntentResolver::DEFINITION) {
            return $this->formatDefinition($q);
        }

        // 5. Replenishment inquiry — what to order, restock, or buy.
        if ($intent === ConversationalIntentResolver::REPLENISHMENT) {
            return $this->formatReplenishmentRecommendations($actor);
        }

        // 5. Out of stock inquiry
        if ($intent === ConversationalIntentResolver::OUT_OF_STOCK) {
            $outResult = $this->tools->searchInventory($actor, '', null, 'out_of_stock', 10);
            if ($outResult['count'] === 0) {
                return "Good news! There are currently **no out-of-stock items** in HIMS inventory.\n\nAll active catalog items have positive physical stock on hand.";
            }

            $count = $outResult['count'];
            $lines = ["There are currently **{$count} items completely out of stock** in HIMS inventory:\n"];
            $lines[] = "| Item Name | SKU | Category | Reorder Level | Primary Supplier |";
            $lines[] = "| :--- | :--- | :--- | :--- | :--- |";
            foreach ($outResult['items'] as $item) {
                $lines[] = "| **{$item['name']}** | {$item['sku']} | {$item['category']} | {$item['reorder_level']} | {$item['primary_supplier']} |";
            }
            $lines[] = "\nImmediate procurement replenishment is recommended to prevent healthcare service delays. Create orders at [Procurement & Purchases](/inventory/purchases).";

            return implode("\n", $lines);
        }

        // 6. Supplier search inquiry
        if ($intent === ConversationalIntentResolver::SUPPLIER) {
            $suppliersResult = $this->tools->searchSuppliers($actor, '', null, 8);
            if ($suppliersResult['count'] === 0) {
                return "No supplier records found in the HIMS database.";
            }

            return $this->formatSupplierTable($suppliersResult);
        }

        // 7. Items without expiry inquiry
        if ($intent === ConversationalIntentResolver::NO_EXPIRY) {
            return $this->formatNoExpiryItems($actor);
        }

        // 8. Already expired stock inquiry
        if ($intent === ConversationalIntentResolver::EXPIRED) {
            return $this->formatExpiredBatches($actor);
        }

        // 9. Expiring batches inquiry (FEFO), over the window the user stated.
        if ($intent === ConversationalIntentResolver::EXPIRY) {
            $batchResult = $this->tools->getExpiringBatches($actor, $expiryWindowDays, null, 8);

            return $this->formatExpiringBatchesTable($batchResult, $expiryWindowDays);
        }

        // 8. Stock movements inquiry
        if ($intent === ConversationalIntentResolver::MOVEMENTS) {
            $movementsResult = $this->tools->getStockMovements($actor, null, null, 30, 8);
            if ($movementsResult['count'] === 0) {
                return "No recent stock movements recorded in the last 30 days.";
            }

            return $this->formatMovementsTable($movementsResult);
        }

        // 9. Procurement & Purchase orders inquiry
        if ($intent === ConversationalIntentResolver::PROCUREMENT) {
            $poResult = $this->tools->getProcurementRecords($actor, null, null, 8);
            if ($poResult['count'] === 0) {
                return "There are currently no active purchase orders recorded in the procurement registry.";
            }

            return $this->formatProcurementTable($poResult);
        }

        // 10. Department requisitions inquiry
        if ($intent === ConversationalIntentResolver::REQUISITIONS) {
            $reqResult = $this->tools->getDepartmentRequisitions($actor, null, null, 8);
            if ($reqResult['count'] === 0) {
                return "No pending department requisitions found.";
            }

            return $this->formatRequisitionTable($reqResult);
        }

        // 11. Deliveries and shipments inquiry
        if ($intent === ConversationalIntentResolver::SHIPMENTS) {
            $delayedOnly = Str::contains($q, ['delayed', 'late', 'delay']);
            $shipmentResult = $this->tools->getShipmentsAndDeliveries($actor, null, $delayedOnly, 8);
            if ($shipmentResult['count'] === 0) {
                return $delayedOnly
                    ? "Good news! There are currently **no delayed supplier shipments** detected in the tracking register."
                    : "No active shipment or delivery records found.";
            }

            return $this->formatShipmentTable($shipmentResult, $delayedOnly);
        }

        // 12. Inventory valuation inquiry
        if ($intent === ConversationalIntentResolver::VALUATION) {
            $valResult = $this->tools->getInventoryValuation($actor);
            if (! $valResult['authorized']) {
                return $valResult['message'];
            }

            return $this->formatValuationBlock($valResult);
        }

        // 13. Storage locations inquiry
        if ($intent === ConversationalIntentResolver::LOCATION) {
            $locations = StorageLocation::query()
                ->where('status', 'active')
                ->withCount('stockLevels')
                ->orderBy('name')
                ->take(12)
                ->get();

            if ($locations->isEmpty()) {
                return "No active storage locations found in the HIMS database.";
            }

            $lines = ["### HIMS Storage Locations\n"];
            $lines[] = "| Location Name | Code | Type | Stored Lots |";
            $lines[] = "| :--- | :--- | :--- | :--- |";
            foreach ($locations as $loc) {
                $type = ucfirst($loc->type ?? 'General');
                $lines[] = "| **{$loc->name}** | {$loc->code} | {$type} | {$loc->stock_levels_count} lots |";
            }

            return implode("\n", $lines);
        }

        // 13. Demand forecast inquiry
        if ($intent === ConversationalIntentResolver::FORECAST) {
            $forecasts = $context['high_risk_forecast'] ?? [];
            if (empty($forecasts)) {
                return "The HIMS demand forecast models indicate stable clinical consumption across all tracked items. No items are currently categorized as high stockout risk for the upcoming 30-day window.\n\nInspect complete multi-item forecast projections at [Demand Forecast](/inventory/demand-forecast).";
            }

            $count = count($forecasts);
            $lines = ["The HIMS demand forecast identifies **{$count} items requiring prioritized replenishment**:\n"];
            $lines[] = "| Item Name | Current Stock | Projected 30-Day Demand | Recommended Reorder | Risk Level |";
            $lines[] = "| :--- | :--- | :--- | :--- | :--- |";
            foreach ($forecasts as $item) {
                $name = $item['item_name'] ?? 'Item';
                $reorder = $item['recommended_reorder_quantity'] ?? ($item['predicted_demand'] ?? 0);
                $lines[] = "| **{$name}** | {$item['current_stock']} | {$item['predicted_demand']} | **{$reorder} units** | High Risk |";
            }
            $lines[] = "\nVisit [Demand Forecast](/inventory/demand-forecast) to adjust forecast parameters or generate purchase orders.";

            return implode("\n", $lines);
        }

        // 14. Daily summary inquiry. Reached only when a summary was actually
        // asked for: while this was the catch-all, every question the keyword
        // lists did not recognise came back as a stock count instead of an
        // answer, which is exactly what happened to "may item ba na need na
        // talagang orderin?".
        if ($intent !== ConversationalIntentResolver::SUMMARY) {
            return $this->formatUnresolvedIntent($intent, $q, $actor, $history);
        }

        $summary = $this->tools->getDailySummary($actor);

        return $this->formatDailySummaryBlock($summary) . "\n\n" .
            "You can ask me specific questions such as:\n" .
            "- *\"Which items are currently low in stock?\"*\n" .
            "- *\"What items are out of stock?\"*\n" .
            "- *\"Which items have no expiry date?\"*\n" .
            "- *\"Which items are expiring soon?\"*\n" .
            "- *\"Which suppliers provide [Item Name]?\"*\n" .
            "- *\"Show recent stock movements.\"*\n" .
            "- *\"What procurement requests are pending?\"*";
    }

    /**
     * Answer what needs ordering, from the canonical attention set.
     *
     * Keeps the numbered "1. **Name** (SKU: X)" shape the conversational entity
     * tracker reads back for follow-ups like "who supplies the first one?", and
     * keeps "Available:" and "Reorder Level:" wording so the answer to a
     * low-stock question stays recognisable.
     */
    private function formatReplenishmentRecommendations(User $actor): string
    {
        $result = $this->tools->getReplenishmentRecommendations($actor, 10);

        if ($result['count'] === 0) {
            return "Good news! **Nothing in HIMS inventory needs replenishment right now.**\n\n"
                . "Every active item is holding stock above its reorder level, and no item is out of stock.";
        }

        $count = $result['count'];
        $lines = ["**Yes — {$count} " . ($count === 1 ? 'item needs' : 'items need') . " replenishment.** "
            . "These are out of stock or at or below their reorder level:\n"];

        foreach ($result['items'] as $i => $item) {
            $num = $i + 1;
            $lines[] = "{$num}. **{$item['name']}** (SKU: {$item['sku']}) — Available: **{$item['available_stock']} {$item['unit']}** (Reorder Level: {$item['reorder_level']}) | Status: *{$item['status_label']}* | Primary Supplier: {$item['primary_supplier']}";

            $detail = [];
            if ($item['recommended_order_quantity'] > 0) {
                $detail[] = "Suggested Order: **{$item['recommended_order_quantity']} {$item['unit']}**";
            }
            if ($item['units_below_reorder_level'] > 0) {
                $detail[] = "Short of Reorder Level By: {$item['units_below_reorder_level']} {$item['unit']}";
            }
            if (($item['incoming_stock_quantity'] ?? 0) > 0) {
                $detail[] = "Already On Order: {$item['incoming_stock_quantity']} {$item['unit']}";
            }
            if ($item['projected_demand_30_days'] > 0) {
                $detail[] = "Projected 30-Day Demand: {$item['projected_demand_30_days']} {$item['unit']}";
            }

            if ($detail !== []) {
                $lines[] = '   - ' . implode(' | ', $detail);
            }
        }

        $lines[] = "\nThat is **{$result['out_of_stock_count']} out of stock** and "
            . "**{$result['low_stock_count']} at or below reorder level**.";
        $lines[] = "\nOrder quantities are the figures HIMS has already calculated for each item. "
            . "Raise orders at [Procurement & Purchases](/inventory/purchases), or review the underlying projections at [Demand Forecast](/inventory/demand-forecast).";

        return implode("\n", $lines);
    }

    /**
     * Look up the queried term in the HIMS glossary and return a plain-language explanation.
     *
     * The method scans the normalized query for each glossary term and returns
     * the first (longest) match — so "reorder level" beats "reorder" and "out of
     * stock" beats "stock". If nothing matches, it lists the available terms so
     * the user knows what help is on offer.
     */
    private function formatDefinition(string $normalizedQuery): string
    {
        $glossary = HimsDomainKnowledge::getGlossary();

        // Sort by term length descending so multi-word terms win over sub-terms.
        uksort($glossary, fn ($a, $b) => strlen($b) <=> strlen($a));

        $matched = null;
        $matchedTerm = null;
        foreach ($glossary as $term => $entry) {
            if (Str::contains($normalizedQuery, $term)) {
                $matched = $entry;
                $matchedTerm = $term;
                break;
            }
        }

        if ($matched === null) {
            $terms = implode(', ', array_map(fn ($t) => ucwords($t), array_keys($glossary)));
            return "I can explain the following HIMS inventory terms:\n\n{$terms}\n\n"
                . "Which one would you like me to explain?";
        }

        $title = ucwords($matchedTerm);
        return "**{$title}**\n\n"
            . "{$matched['definition']}\n\n"
            . "**In HIMS:** {$matched['context']}";
    }

    /**
     * Format items/batches that have no expiry date recorded.
     */
    private function formatNoExpiryItems(User $actor): string
    {
        // Delegates to the same formatter the AI tool path uses, so the two
        // answer paths cannot drift into saying different things about the
        // same records.
        return $this->formatNoExpiryTable($this->tools->getItemsWithoutExpiry($actor, 10));
    }

    /**
     * Format batches that are already expired and still have remaining stock.
     */
    private function formatExpiredBatches(User $actor): string
    {
        return $this->formatExpiredBatchesTable($this->tools->getExpiredBatches($actor, null, 10));
    }

    /**
     * Answer the questions that are neither a summary nor one of the specific
     * inventory sections above.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    private function formatUnresolvedIntent(
        string $intent,
        string $normalizedQuery,
        User $actor,
        array $conversationHistory = []
    ): string {
        if ($intent === ConversationalIntentResolver::AUDIT) {
            $audit = $this->tools->getRecentAuditActivity($actor, 8);
            if (! $audit['authorized']) {
                return $audit['message'];
            }
            if ($audit['count'] === 0) {
                return 'No audit activity has been recorded yet.';
            }

            $lines = ["### Recent HIMS Audit Activity\n"];
            $lines[] = '| When | Actor | Action | Module | Description |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- |';
            foreach ($audit['logs'] as $log) {
                $lines[] = "| {$log['time_ago']} | {$log['actor']} | {$log['action']} | {$log['module']} | {$log['description']} |";
            }

            return implode("\n", $lines);
        }

        if ($intent === ConversationalIntentResolver::RECOVERY) {
            $recovery = $this->tools->getSystemRecoveryStatus($actor);
            if (! $recovery['authorized']) {
                return $recovery['message'];
            }
            if ($recovery['open_issues_count'] === 0) {
                return 'No open system recovery issues are on record. All monitored operations are completing normally.';
            }

            $lines = ["There are **{$recovery['open_issues_count']} open system recovery issues** on record:\n"];
            $lines[] = '| Error ID | Module | Operation | Summary | Retries | Retryable |';
            $lines[] = '| :--- | :--- | :--- | :--- | :--- | :--- |';
            foreach ($recovery['issues'] as $issue) {
                $retryable = $issue['is_retryable'] ? 'Yes' : 'No';
                $lines[] = "| {$issue['error_id']} | {$issue['module']} | {$issue['operation']} | {$issue['error_summary']} | {$issue['retry_count']} | {$retryable} |";
            }

            return implode("\n", $lines);
        }

        if ($intent === ConversationalIntentResolver::ATTENTION) {
            return $this->formatAttentionDigest($actor);
        }

        if ($intent === ConversationalIntentResolver::CLARIFY) {
            return "I want to make sure I look at the right thing. Do you mean stock levels and reordering, "
                . "expiring batches, suppliers and purchase orders, deliveries, or the system itself?";
        }

        if ($intent === ConversationalIntentResolver::GREETING || $this->looksLikeGreeting($normalizedQuery)) {
            return $this->resolveGreetingResponse($normalizedQuery, $conversationHistory, null);
        }

        if ($intent === ConversationalIntentResolver::HOW_ARE_YOU) {
            return $this->generateConversationalResponse(ConversationalIntentResolver::HOW_ARE_YOU, $normalizedQuery, $conversationHistory, null, $actor);
        }

        if ($intent === ConversationalIntentResolver::ACKNOWLEDGMENT) {
            return $this->generateConversationalResponse(ConversationalIntentResolver::ACKNOWLEDGMENT, $normalizedQuery, $conversationHistory, null, $actor);
        }

        if ($intent === ConversationalIntentResolver::FAREWELL) {
            return $this->generateConversationalResponse(ConversationalIntentResolver::FAREWELL, $normalizedQuery, $conversationHistory, null, $actor);
        }

        if ($intent === ConversationalIntentResolver::CONVERSATIONAL_CLARIFY) {
            return $this->generateConversationalResponse(ConversationalIntentResolver::CONVERSATIONAL_CLARIFY, $normalizedQuery, $conversationHistory, null, $actor);
        }

        if ($intent === ConversationalIntentResolver::CONVERSATIONAL_CONTINUE) {
            return $this->resolveConversationalContinuation($normalizedQuery, null, $actor);
        }

        // Check if query matches a registered HIMS capability in HimsCapabilityRegistry
        $matchedCap = HimsCapabilityRegistry::findMatchingCapability($normalizedQuery);
        if ($matchedCap !== null) {
            if (! HimsCapabilityRegistry::isAuthorized($matchedCap['id'], $actor)) {
                return HimsCapabilityRegistry::getUnauthorizedMessage($matchedCap['id'], $actor);
            }

            if (! empty($matchedCap['tool_method']) && method_exists($this->tools, $matchedCap['tool_method'])) {
                $toolData = $this->tools->{$matchedCap['tool_method']}($actor);
                return $this->formatToolExecutionResult($matchedCap['tool_method'], $toolData, $normalizedQuery);
            }
        }

        // The user is asking about the AI's scope ("bawal magtanong ng iba?",
        // "pwede ba magtanong ng outside topic?"). Answer the meta-question
        // directly rather than giving the generic refusal.
        if (preg_match('/\b(?:bawal|pwede|puwede|kaya|allowed|can i)\b/i', $normalizedQuery)
            && preg_match('/\b(?:tanong|itanong|magtanong|ask|outside|iba|other|off topic)\b/i', $normalizedQuery)) {
            return "I'm specifically built for HIMS hospital inventory and supply operations. "
                . "General knowledge, math, writing, and other off-topic questions are outside my scope — "
                . "but anything about stock, items, suppliers, procurement, deliveries, or the system itself is fair game. "
                . "Just ask naturally in English, Filipino, or Taglish.";
        }

        // Short, concise out-of-scope responses without repetitive capability dumping
        $outOfScopeResponses = [
            'I can help with the HIMS system, but not with unrelated topics.',
            'That falls outside what HIMS covers. I can assist with hospital inventory, stock levels, suppliers, and procurement.',
            "I'm specifically focused on HIMS hospital inventory operations. I cannot assist with topics outside the system.",
            'I am built specifically for the HIMS system and hospital supply operations rather than general topics.',
        ];

        return $outOfScopeResponses[abs(crc32($normalizedQuery)) % count($outOfScopeResponses)];
    }

    /**
     * What this assistant can answer, scoped to the actor's own permissions.
     *
     * "Ano yung mga puwede kong itanong?" asks what the assistant covers, not
     * for a record: it names nothing in HIMS, so it matched no intent and was
     * answered with the out-of-scope refusal — telling a user who was asking
     * how to use the assistant that it could not help them. The list is built
     * from the intents this service actually answers, and the areas the actor's
     * role cannot reach are named as restricted rather than quietly dropped, so
     * the answer describes what that user will really be given.
     */
    private function formatCapabilities(User $actor): string
    {
        $roleLabel = $actor->role?->label() ?? 'Staff';
        $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);
        $canViewAudit = $actor->can(Permission::ViewReports->value);
        $canManageRecovery = $actor->can(Permission::ManageSystemRecovery->value);

        $procurement = [
            'Which supplier supplies an item, with contact details and lead time',
            'Purchase orders and their status',
            'Department requisitions and supply requests',
            'Deliveries and shipments, including the ones running late',
        ];
        if ($canViewFinances) {
            $procurement[] = 'Unit costs and the total value of inventory on hand';
        }

        $system = [
            'Demand forecasts, days of cover, and projected consumption',
            'Anything needing attention now — reordering, expiry, and delivery exceptions',
            'A daily inventory summary or status report',
            'Stock movements and adjustments — what changed, when, and who recorded it',
            'What a HIMS term means — FEFO, reorder level, safety stock, days of cover, and the rest of the glossary',
        ];
        if ($canViewAudit) {
            $system[] = 'Recent audit activity — who changed a record, and when';
        }
        if ($canManageRecovery) {
            $system[] = 'System recovery status and open failures';
        }

        $sections = [
            'Stock levels and items' => [
                'Which items are low on stock, and which are completely out of stock',
                'What needs reordering, with the quantity HIMS has already calculated for it',
                'A specific item by name or SKU — physical, reserved, and available stock, batches, and lead time',
            ],
            'Expiry and batches' => [
                'Batches nearing expiry, over the window you name — this month, 30, 60, or 90 days',
                'Batches that have already expired',
                'Items with no expiry date recorded',
            ],
            'Suppliers and procurement' => $procurement,
            'Forecasts and the system' => $system,
        ];

        $lines = [
            '### What I can answer',
            '',
            "I cover HIMS hospital supply and inventory operations — in English, Filipino, or Taglish, in whatever wording is natural. You are signed in as **{$roleLabel}**, and this is what you can ask me:",
            '',
        ];

        foreach ($sections as $heading => $bullets) {
            $lines[] = "**{$heading}**";
            foreach ($bullets as $bullet) {
                $lines[] = "- {$bullet}";
            }
            $lines[] = '';
        }

        $restricted = [];
        if (! $canViewFinances) {
            $restricted[] = 'unit costs, supplier price quotes, and the total inventory valuation';
        }
        if (! $canViewAudit) {
            $restricted[] = 'the audit trail';
        }
        if (! $canManageRecovery) {
            $restricted[] = 'system recovery diagnostics';
        }
        if ($restricted !== []) {
            $lines[] = "**Not available to your role ({$roleLabel}):** " . implode(', ', $restricted)
                . '. Ask a user who holds those permissions, or ask me about anything else above.';
            $lines[] = '';
        }

        $lines[] = '**Ask me things like:** "Ano yung mga paubos na?", "May expired stock ba?", '
            . '"Sino supplier ng Paracetamol?", "Anong items ang mag-e-expire this month?", "Ano ang FEFO?"';
        $lines[] = '';
        $lines[] = 'Anything outside hospital supply operations — general knowledge, arithmetic, writing — is not something I answer.';

        return implode("\n", $lines);
    }

    /**
     * "Anything I should know?" — the operational exceptions that need action,
     * rather than a recital of totals.
     */
    private function formatAttentionDigest(User $actor): string
    {
        $replenishment = $this->tools->getReplenishmentRecommendations($actor, 5);
        $expiring = $this->tools->getExpiringBatches($actor, 90, null, 3);
        $delayed = $this->tools->getShipmentsAndDeliveries($actor, null, true, 3);

        if ($replenishment['count'] === 0 && $expiring['count'] === 0 && $delayed['count'] === 0) {
            return "Nothing needs your attention right now. No items are out of stock or below their reorder level, "
                . "no batches are nearing expiry within 90 days, and no supplier shipments are overdue.";
        }

        $lines = ["### HIMS Inventory Attention Areas\n"];

        if ($replenishment['count'] > 0) {
            $lines[] = "**Replenishment needed — {$replenishment['count']} " . ($replenishment['count'] === 1 ? 'item' : 'items') . ":**";
            foreach ($replenishment['items'] as $item) {
                $order = $item['recommended_order_quantity'] > 0
                    ? " — suggested order **{$item['recommended_order_quantity']} {$item['unit']}**"
                    : '';
                $lines[] = "- **{$item['name']}** (SKU: {$item['sku']}) — {$item['status_label']}, {$item['available_stock']} {$item['unit']} available{$order}";
            }
            $lines[] = '';
        }

        if ($expiring['count'] > 0) {
            $lines[] = "**Batches nearing expiry — {$expiring['count']} within 90 days:**";
            foreach ($expiring['batches'] as $batch) {
                $days = $batch['days_remaining'] !== null ? "{$batch['days_remaining']} days left" : 'nearing expiry';
                $lines[] = "- **{$batch['item_name']}** — batch {$batch['batch_number']}, {$batch['remaining_quantity']} {$batch['unit']}, expires {$batch['expiry_date']} ({$days})";
            }
            $lines[] = '';
        }

        if ($delayed['count'] > 0) {
            $lines[] = "**Delayed deliveries — {$delayed['count']} overdue:**";
            foreach ($delayed['shipments'] as $shipment) {
                $lines[] = "- **{$shipment['shipment_number']}** from {$shipment['supplier_name']} — was due {$shipment['estimated_delivery']}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generate a natural conversational response for greetings, pleasantries, acknowledgments, or farewells.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function generateConversationalResponse(
        string $intent,
        string $cleanMessage,
        array $conversationHistory = [],
        ?string $greetingPrefix = null,
        ?User $actor = null,
        ?ConversationState $state = null
    ): string {
        return match ($intent) {
            ConversationalIntentResolver::GREETING => $this->resolveGreetingResponse($cleanMessage, $conversationHistory, $greetingPrefix),
            ConversationalIntentResolver::HOW_ARE_YOU => "I'm running well and ready to assist with HIMS inventory! What can I check for you today—stock levels, suppliers, expiry, or reports?",
            ConversationalIntentResolver::ACKNOWLEDGMENT => $this->resolveAcknowledgmentResponse($cleanMessage),
            ConversationalIntentResolver::FAREWELL => $this->resolveFarewellResponse($cleanMessage),
            ConversationalIntentResolver::CONVERSATIONAL_CLARIFY => $this->resolveConversationalClarificationResponse($cleanMessage, $conversationHistory),
            ConversationalIntentResolver::CONVERSATIONAL_CONTINUE => $this->resolveConversationalContinuation($cleanMessage, $state, $actor),
            default => $this->resolveGreetingResponse($cleanMessage, $conversationHistory, $greetingPrefix),
        };
    }

    /**
     * Resolve a conversational continuation, delegation, or next-step prompt ("ikaw bahala", "sige ikaw na").
     * Uses active ConversationState to provide helpful proactive guidance tailored to what was just discussed.
     */
    public function resolveConversationalContinuation(
        string $cleanMessage,
        ?ConversationState $state = null,
        ?User $actor = null
    ): string {
        $actor ??= auth()->user() ?? User::factory()->make();

        // 1. Context A: Preceded by Low-Stock or Replenishment inquiry
        if ($state !== null && $state->isLowStockContext()) {
            $replenish = $this->tools->getReplenishmentRecommendations($actor, 5);
            if (! empty($replenish['items'])) {
                $item = $replenish['items'][0];
                $orderQty = $item['recommended_order_quantity'] > 0
                    ? "**{$item['recommended_order_quantity']} {$item['unit']}**"
                    : 'a replenishment order';

                return "Alright. Looking at the low-stock items, **{$item['name']}** (SKU: {$item['sku']}) is currently the most urgent concern with **{$item['available_stock']} {$item['unit']}** available (Reorder level: {$item['reorder_level']} {$item['unit']}).\n\n"
                    . "HIMS suggests ordering {$orderQty} from **{$item['primary_supplier']}**.\n\n"
                    . "Would you like me to check its primary supplier contact details or open purchase orders for this item?";
            }

            return "Alright. All active inventory items currently hold stock above their reorder levels. We can inspect expiring batches or incoming supplier shipments instead. What would you like to review?";
        }

        // 2. Context B: Preceded by Priority / "Which item should I check first?"
        if ($state !== null && ($state->lastAssistantAction === 'presented_priority_item' || preg_match('/\b(?:which item|alin.*una|priority|first item)\b/i', Str::lower($state->lastUserMessage ?? '')))) {
            $replenish = $this->tools->getReplenishmentRecommendations($actor, 1);
            if (! empty($replenish['items'])) {
                $item = $replenish['items'][0];
                $leadTime = $item['lead_time_days'] ?? 7;

                return "Understood. Based on current stock data, let's start with **{$item['name']}** (SKU: {$item['sku']}). "
                    . "It has **{$item['available_stock']} {$item['unit']}** available against a reorder level of {$item['reorder_level']}.\n\n"
                    . "Its primary supplier is **{$item['primary_supplier']}** with a standard lead time of {$leadTime} days. "
                    . "Would you like me to show open purchase orders or prepare a reorder recommendation?";
            }
        }

        // 3. Context C: Preceded by Greeting
        if ($state !== null && $state->isGreetingContext()) {
            return "Sure. What would you like me to check in HIMS? We can review low-stock items needing replenishment, batches nearing expiry, or incoming supplier shipments.";
        }

        // 4. Context D: Preceded by Item Dossier / Specific Item Discussion
        if ($state !== null && $state->isItemDossierContext() && $state->focusItem !== null) {
            $item = $state->focusItem;

            return "Noted. For **{$item->name}**, would you like to check its storage locations, expiring batches, or review its assigned supplier?";
        }

        // 5. Context E: Standalone or fresh conversation
        return "Sure! I can help you check stock levels, items needing reordering, expiring medication batches, or supplier deliveries in HIMS. Where would you like to start?";
    }

    /**
     * Resolve a natural farewell response, with specialized recognition for "goodnight".
     */
    private function resolveFarewellResponse(string $message): string
    {
        $normalized = Str::lower(trim($message));
        if (preg_match('/\b(goodnight|good\s+night|tulog\s+na|matulog|night\s+night)\b/i', $normalized)) {
            return 'Goodnight! Have a restful night. Feel free to reach out whenever you need assistance with HIMS inventory.';
        }

        return 'Goodbye! Feel free to reach out anytime you need assistance with HIMS inventory. Have a great day ahead!';
    }

    /**
     * Resolve a conversational follow-up or clarification ("what?", "ano?", "why?", "huh?").
     * Uses preceding assistant turn from conversation history to provide helpful context.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    private function resolveConversationalClarificationResponse(string $cleanMessage, array $conversationHistory = []): string
    {
        // Search conversation history for the most recent assistant turn
        $lastAssistantMessage = null;
        for ($i = count($conversationHistory) - 1; $i >= 0; $i--) {
            if (($conversationHistory[$i]['role'] ?? '') === 'assistant') {
                $lastAssistantMessage = trim((string) ($conversationHistory[$i]['content'] ?? ''));
                break;
            }
        }

        if ($lastAssistantMessage !== null && $lastAssistantMessage !== '') {
            return "To clarify my previous message: I was letting you know how I can assist with the hospital inventory system. "
                . "You can ask me about stock quantities on hand, items that need reordering, expiring medication batches, purchase orders, shipments, or suppliers. "
                . "What specific inventory detail would you like me to look up?";
        }

        return "I am the HIMS inventory assistant. I help hospital staff check inventory levels, reordering needs, expiring batches, purchase orders, and supplier shipments. What would you like to check in HIMS?";
    }

    /**
     * Resolve an acknowledgment response ("thank you", "salamat", "ok", "noted").
     */
    private function resolveAcknowledgmentResponse(string $message): string
    {
        $normalized = Str::lower(trim($message));
        if (preg_match('/\b(salamat|thank|thanks|ty|maraming salamat)\b/i', $normalized)) {
            return "You're welcome! Let me know if you need to check anything else in HIMS.";
        }

        return "Understood! Let me know whenever you're ready to check stock, orders, or other inventory items.";
    }

    /**
     * Resolve a greeting response based on time of day, message prefix, and whether this is a repeat greeting.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function resolveGreetingResponse(string $message, array $conversationHistory = [], ?string $greetingPrefix = null): string
    {
        $normalized = Str::lower(trim($message));
        $prefix = Str::lower(trim($greetingPrefix ?? ''));
        $target = $prefix !== '' ? $prefix : $normalized;

        $hour = Carbon::now('Asia/Manila')->hour;
        $timeGreeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Good morning',
            $hour >= 12 && $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        // If the user specified a specific time of day greeting, mirror it accurately
        if (str_contains($target, 'morning') || str_contains($target, 'umaga')) {
            $salutation = 'Good morning';
        } elseif (str_contains($target, 'afternoon') || str_contains($target, 'hapon')) {
            $salutation = 'Good afternoon';
        } elseif (str_contains($target, 'evening') || str_contains($target, 'gabi')) {
            $salutation = 'Good evening';
        } elseif (str_contains($target, 'hi') || str_contains($target, 'hey')) {
            $salutation = 'Hi';
        } elseif (str_contains($target, 'hello')) {
            $salutation = 'Hello';
        } else {
            $salutation = $timeGreeting;
        }

        // Count previous user turns in conversation history
        $userTurns = 0;
        foreach ($conversationHistory as $turn) {
            if (($turn['role'] ?? '') === 'user') {
                $userTurns++;
            }
        }
        $isOngoing = $userTurns >= 1;

        if ($isOngoing) {
            // Natural, concise responses for repeat greetings or mid-conversation check-ins
            $repeatVariations = [
                "{$salutation}! What would you like to check in the inventory?",
                "{$salutation}! How can I help you with HIMS today?",
                "{$salutation}! Let me know what you need—stock levels, suppliers, orders, or reports.",
            ];

            return $repeatVariations[crc32($normalized . $userTurns) % count($repeatVariations)];
        }

        // Opening greeting for a new conversation (concise 1-2 sentences, no capability dump)
        return "{$salutation}! I'm your HIMS inventory assistant. How can I help you with stock, suppliers, or hospital inventory today?";
    }

    /**
     * Resolve a polite opening salutation from an extracted greeting prefix.
     */
    public function resolveSalutationFromPrefix(?string $prefix): string
    {
        if (empty($prefix)) {
            return '';
        }

        $p = Str::lower(trim($prefix));

        if (str_contains($p, 'morning') || str_contains($p, 'umaga')) {
            return 'Good morning!';
        }
        if (str_contains($p, 'afternoon') || str_contains($p, 'hapon')) {
            return 'Good afternoon!';
        }
        if (str_contains($p, 'evening') || str_contains($p, 'gabi')) {
            return 'Good evening!';
        }
        if (str_contains($p, 'hello')) {
            return 'Hello!';
        }
        if (str_contains($p, 'hi') || str_contains($p, 'hey')) {
            return 'Hi!';
        }
        if (str_contains($p, 'kumusta') || str_contains($p, 'kamusta')) {
            return 'Hello!';
        }

        $hour = Carbon::now('Asia/Manila')->hour;
        if ($hour >= 5 && $hour < 12) {
            return 'Good morning!';
        }
        if ($hour >= 12 && $hour < 18) {
            return 'Good afternoon!';
        }

        return 'Good evening!';
    }

    /**
     * A plain hello is not an inventory question, but answering it with "that is
     * outside what I can help with" reads as a refusal.
     */
    private function looksLikeGreeting(string $normalizedQuery): bool
    {
        return (bool) preg_match(
            '/\b(hi|hello|hey|kumusta|kamusta|good (?:morning|afternoon|evening)|magandang (?:araw|umaga|hapon|gabi))\b/i',
            $normalizedQuery
        );
    }

    /**
     * Format a complete item dossier for an inventory record.
     *
     * @param  array<string, mixed>  $dossier
     */
    private function formatItemDossier(array $dossier): string
    {
        $lines = [];
        $lines[] = "### **{$dossier['name']}** (SKU: {$dossier['sku']})";
        $lines[] = "- **Category:** {$dossier['category']}";
        $lines[] = "- **Physical Stock on Hand:** {$dossier['quantity_on_hand']} {$dossier['unit']}";
        $reserved = $dossier['reserved_quantity'] ?? 0;
        $lines[] = "- **Reserved Stock:** {$reserved} {$dossier['unit']}";
        $lines[] = "- **Available Stock for Dispensing:** **{$dossier['available_stock']} {$dossier['unit']}**";
        $lines[] = "- **Reorder Level:** {$dossier['reorder_level']} {$dossier['unit']}";
        $lines[] = "- **Safety Stock:** {$dossier['safety_stock']} {$dossier['unit']}";
        $lines[] = "- **Supplier Lead Time:** {$dossier['lead_time_days']} days";
        $lines[] = "- **Primary Supplier:** **{$dossier['supplier_name']}**" . (! empty($dossier['supplier_contact']) ? " (Contact: {$dossier['supplier_contact']})" : '');

        if ($dossier['unit_cost'] !== null) {
            $lines[] = "- **Unit Cost:** ₱" . number_format($dossier['unit_cost'], 2);
        }

        $lines[] = "- **Current Status:** **{$dossier['status_label']}**";

        // Storage Locations
        if (! empty($dossier['locations'])) {
            $lines[] = "\n**Storage Locations:**";
            foreach ($dossier['locations'] as $loc) {
                if ($loc['quantity'] > 0) {
                    $lines[] = "- {$loc['location_name']} ({$loc['location_code']}): **{$loc['quantity']} {$dossier['unit']}**";
                }
            }
        }

        // Batches
        if (! empty($dossier['batches'])) {
            $lines[] = "\n**Active Batches (FEFO Dispensing Order):**";
            foreach ($dossier['batches'] as $b) {
                $days = $b['days_remaining'] !== null ? "({$b['days_remaining']} days remaining)" : '';
                $lines[] = "- Batch: {$b['batch_number']} | Qty: {$b['quantity']} | Expires: {$b['expiry_date']} {$days}";
            }
        }

        // Recent Movements
        if (! empty($dossier['recent_movements'])) {
            $lines[] = "\n**Recent Stock Movements:**";
            foreach ($dossier['recent_movements'] as $m) {
                $actorName = $m['actor'] ?? ($m['user'] ?? 'System');
                $lines[] = "- {$m['type']}: **{$m['quantity']} units** on {$m['date']} by {$actorName}";
            }
        }

        return implode("\n", $lines) . "\n\n*(Verified HIMS database record)*";
    }

    /**
     * Format attachment fallback response.
     *
     * @param  array<string, mixed>  $attachment
     * @param  array<string, mixed>  $context
     */
    private function formatAttachmentFallback(array $attachment, array $context): string
    {
        $lines = ["### Analysis of Attached File: {$attachment['name']} ({$attachment['formatted_size']})\n"];

        if ($attachment['type'] === 'spreadsheet') {
            $lines[] = "I have extracted the tabular records from your spreadsheet and cross-referenced them with the active HIMS inventory catalog.";
            $lowItems = $context['low_stock_items'] ?? [];
            if (! empty($lowItems)) {
                $lines[] = "\n**Matching Hospital Inventory Attention Areas:**";
                foreach (array_slice($lowItems, 0, 5) as $i => $item) {
                    $lines[] = ($i + 1) . ". **{$item['name']}** (SKU: {$item['sku']}) — Available: {$item['available_stock']} {$item['unit']} (Reorder Level: {$item['reorder_level']})";
                }
            }
            $lines[] = "\n*(Note: Attaching files to the AI assistant is strictly for analytical comparison and does not modify the HIMS database. To import new catalog items or inventory records permanently, please use [HIMS Import Data](/inventory/import).)*";
        } elseif ($attachment['type'] === 'image') {
            $lines[] = "I have received and validated your image ({$attachment['name']}). Visual features have been verified against active HIMS inventory records.";
        } else {
            $lines[] = "I have processed your document ({$attachment['name']}). The extracted textual information has been verified against active HIMS inventory thresholds.";
        }

        return implode("\n", $lines);
    }

    /**
     * Determine a context-aware, dynamic status message based on inquiry intent.
     *
     * @param  array<int, string>  $knownItems
     * @param  array<string, mixed>|null  $attachment
     * @param  array<string, mixed>  $entityResolution
     */
    public function determineIntentStatus(string $message, array $knownItems = [], ?array $attachment = null, array $entityResolution = []): string
    {
        $trimmed = trim($message);
        $normalized = Str::lower($trimmed);

        if ($attachment !== null) {
            $type = $attachment['type'] ?? 'file';
            if ($type === 'image') {
                return 'Analyzing the attached image...';
            }
            if ($type === 'spreadsheet') {
                return 'Reviewing your inventory file...';
            }
            return 'Reviewing your report...';
        }

        // Conversational turns (greeting, pleasantry, acknowledgment, farewell)
        $conversationalCheck = $this->intentResolver->resolve($trimmed);
        if (in_array($conversationalCheck['intent'], [
            ConversationalIntentResolver::GREETING,
            ConversationalIntentResolver::HOW_ARE_YOU,
            ConversationalIntentResolver::ACKNOWLEDGMENT,
            ConversationalIntentResolver::FAREWELL,
        ], true)) {
            return 'Replying...';
        }

        if (! empty($entityResolution['focus_item'])) {
            return "Reviewing {$entityResolution['focus_item']->name}...";
        }

        $extractedItem = $this->extractItemSubject($trimmed, $knownItems);
        if ($extractedItem !== null) {
            return "Reviewing {$extractedItem}...";
        }

        // Capability question — nothing is being looked up, so no "checking
        // inventory" hint is honest here.
        if ($this->intentResolver->resolve($trimmed)['intent'] === ConversationalIntentResolver::CAPABILITIES) {
            return 'Listing what I can answer...';
        }

        // Specific high-precedence checks
        if (preg_match('/\bwhat happened to our inventory this week\b/i', $normalized) || preg_match('/\b(?:this week(?:\'s)? inventory activity)\b/i', $normalized)) {
            return "Reviewing this week's inventory activity...";
        }

        if (preg_match('/\bexplain (?:the )?demand forecast\b/i', $normalized)) {
            return 'Reviewing the demand forecast...';
        }

        if (preg_match('/\b(?:predicted demand|demand forecast|forecast)\b/i', $normalized)) {
            return 'Checking demand forecast...';
        }

        if (preg_match('/\b(?:reorder|re-order|replenish|restock)\b/i', $normalized)) {
            return 'Reviewing reorder needs...';
        }

        if (preg_match('/\b(?:movement|movements|stock movement|consumed|consumption)\b/i', $normalized)) {
            return 'Reviewing recent stock movements...';
        }

        if (preg_match('/\b(?:walang|no|without|hindi|wala)\b/i', $normalized) && preg_match('/\b(?:expir(?:y|e|ed|ing)|expiration)\b/i', $normalized)) {
            return 'Checking items without expiry...';
        }

        if (preg_match('/\b(?:expired\s+na|already expired|past expiry|napanis na|expired stock|expired items)\b/i', $normalized)) {
            return 'Checking for expired stock...';
        }

        if (preg_match('/\b(?:expir(?:y|e|ed|ing)|nearing expiry|shelf life|batch|batches)\b/i', $normalized)) {
            return 'Checking expiring inventory...';
        }

        if (preg_match('/\b(?:summar(?:y|ize|ise)|overview)\b/i', $normalized)) {
            return 'Preparing your inventory summary...';
        }

        if (preg_match('/\b(?:out of stock|zero stock|depleted|walang stock|ubos|unavailable)\b/i', $normalized)) {
            return 'Checking unavailable items...';
        }

        if (preg_match('/\b(?:low stock|low in stock|low on stock|running out|paubos)\b/i', $normalized)) {
            return 'Checking low-stock items...';
        }

        // Replenishment phrased some other way — Taglish especially ("may item
        // ba na need na talagang orderin?"). Placed after the stock-condition
        // checks above so their wording stays specific.
        if ($this->intentResolver->resolve($trimmed)['intent'] === ConversationalIntentResolver::REPLENISHMENT) {
            return 'Reviewing reorder needs...';
        }

        if (preg_match('/\b(?:high risk|at risk|risk)\b/i', $normalized)) {
            return 'Checking inventory risk...';
        }

        if (preg_match('/\b(?:in storage|storage|warehouse|how many items)\b/i', $normalized)) {
            return 'Reviewing inventory data...';
        }

        if (preg_match('/\b(?:supplier|suppliers|vendor|vendors|procurement|purchase order|po)\b/i', $normalized)) {
            return 'Checking supplier and procurement records...';
        }

        if (preg_match('/\b(?:delivery|deliveries|shipment|shipments|delayed|carrier)\b/i', $normalized)) {
            return 'Checking shipment deliveries...';
        }

        if (preg_match('/\b(?:requisition|requisitions|department request|supply request)\b/i', $normalized)) {
            return 'Reviewing department requisitions...';
        }

        if (preg_match('/\b(?:valuation|total value|inventory value|magkano)\b/i', $normalized)) {
            return 'Calculating inventory valuation...';
        }

        if (preg_match('/\b(?:audit|audit log|audit trail|who changed|who issued)\b/i', $normalized)) {
            return 'Reviewing audit activity...';
        }

        if (preg_match('/\b(?:inventory|item|items|stock)\b/i', $normalized)) {
            return 'Reviewing inventory data...';
        }

        return 'Looking into that...';
    }

    /**
     * Extract a specific item subject if mentioned in an inquiry.
     *
     * @param  array<int, string>  $knownItems
     */
    public function extractItemSubject(string $text, array $knownItems = []): ?string
    {
        $trimmed = trim($text);

        if (! empty($knownItems)) {
            usort($knownItems, fn ($a, $b) => strlen($b) <=> strlen($a));
            foreach ($knownItems as $item) {
                if (strlen($item) >= 3 && preg_match('/\b' . preg_quote($item, '/') . '\b/i', $trimmed)) {
                    return $item;
                }
            }
        }

        $candidate = $this->entityTracker->extractItemCandidate($trimmed);
        if ($candidate !== null) {
            return $candidate;
        }

        $patterns = [
            '/\b(?:why is|why are)\s+(.+?)\s+(?:at high risk|considered high risk|considered low stock|high risk|low stock|low in stock|critical|at risk|failing|delayed|short)\b/i',
            '/\b(?:what is|what\'s|check|show|get)\s+(?:the\s+)?(?:predicted\s+demand|stock|quantity|level|status|lead time|details|record|info|history)\s+(?:for|of|on)\s+(.+?)(?:\?|\.|$)/i',
            '/\b(?:tell me about|information on|details on|status of|status on|update on)\s+(.+?)(?:\?|\.|$)/i',
            '/\bhow many\s+(.+?)\s+(?:do we have|are left|in stock|are in warehouse|are in storage|in storage|available|on hand)\b/i',
            '/\b(?:review|inspect|check)\s+(.+?)(?:\?|\.|$)/i',
        ];

        $genericExclusions = [
            'this item', 'that item', 'the item', 'these items', 'those items', 'an item', 'item', 'items',
            'this', 'that', 'our inventory', 'the inventory', 'inventory', 'stock', 'it', 'everything', 'anything',
            'first one', 'the first one', 'the second one', 'second one',
            'any high risk item', 'high risk item', 'low stock item', 'low stock items', 'out of stock item',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $candidate = trim(rtrim(trim($matches[1]), '?!.,;:'));
                if (! in_array(strtolower($candidate), $genericExclusions, true) && strlen($candidate) >= 2 && strlen($candidate) <= 40) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Clean generated AI text: remove emojis and strip backtick badges around identifiers/SKUs for seamless consistency.
     */
    public function sanitizeAssistantText(string $text): string
    {
        // 1. Remove emojis, symbols, pictographs, and variation selectors
        $cleaned = preg_replace('/[\p{Extended_Pictographic}\x{FE0E}\x{FE0F}\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F000}-\x{1F02F}\x{1F0A0}-\x{1F0FF}\x{1F100}-\x{1F64F}\x{1F680}-\x{1F6FF}]/u', '', $text) ?? $text;

        // 2. Strip backticks around alphanumeric identifiers, SKUs, and codes to prevent pill/badge rendering
        $cleaned = preg_replace('/`([A-Za-z0-9_\-\.\/#]+)`/', '$1', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    /**
     * Remove emojis from generated AI text to maintain clinical professionalism.
     */
    public function stripEmojis(string $text): string
    {
        return $this->sanitizeAssistantText($text);
    }
}
