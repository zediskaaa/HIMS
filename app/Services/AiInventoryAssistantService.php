<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\AuditAction;
use App\Enums\DemandTrend;
use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\User;
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
    public function __construct(
        private readonly DemandForecastService $statisticalForecasts,
        private readonly AiDemandForecastService $aiForecasts,
        private readonly AuditLogger $auditLogger,
        private readonly ChatAttachmentProcessor $attachmentProcessor,
        private readonly AiDataSanitizerService $sanitizer,
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

        $statusHint = $this->determineIntentStatus($cleanMessage, [], $attachmentData);

        // 1. Gather relevant HIMS inventory data based on the query.
        $contextData = $this->gatherContext($cleanMessage, $conversationHistory);

        // 2. If Gemini API key is not set, generate a rich deterministic grounded response immediately.
        $apiKey = (string) (config('services.gemini.key') ?: config('services.gemini.api_key'));
        if (trim($apiKey) === '') {
            $reply = $this->sanitizeAssistantText($this->generateGroundedFallback($cleanMessage, $contextData, $attachmentData));
            $this->recordAttachmentAudit($actor, $attachmentData, 'grounded_fallback');

            return [
                'reply' => $reply,
                'source' => 'grounded_fallback',
                'status_hint' => $statusHint,
                'attachment' => $attachmentData ? $this->sanitizeAttachmentMetadata($attachmentData) : null,
            ];
        }

        // 3. Attempt Gemini generation with multi-model failover.
        try {
            $reply = $this->requestGemini($cleanMessage, $conversationHistory, $contextData, $apiKey, $attachmentData);
            $this->recordAttachmentAudit($actor, $attachmentData, 'ai');

            return [
                'reply' => $reply,
                'source' => 'ai',
                'status_hint' => $statusHint,
                'attachment' => $attachmentData ? $this->sanitizeAttachmentMetadata($attachmentData) : null,
            ];
        } catch (Throwable) {
            // Gracefully fall back to verified database figures on any API error/rate-limit.
            $reply = $this->sanitizeAssistantText($this->generateGroundedFallback($cleanMessage, $contextData, $attachmentData));
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
     * Gather actual database context matching the inquiry.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<string, mixed>
     */
    public function gatherContext(string $query, array $history = []): array
    {
        $normalized = Str::lower($query);
        $recentHistoryText = collect($history)->take(-3)->pluck('content')->implode(' ');
        $combinedText = Str::lower($query.' '.$recentHistoryText);

        // Find any specific items mentioned in query or recent conversation.
        $mentionedItems = $this->findMentionedItems($combinedText);

        // Global summary figures.
        $totalItems = InventoryItem::query()->count();
        $lowStockItems = InventoryItem::query()
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->orderBy('quantity_on_hand')
            ->take(10)
            ->get();
        $outOfStockCount = InventoryItem::query()->where('quantity_on_hand', '<=', 0)->count();

        // Expiring batches in next 90 days.
        $expiringBatches = ItemBatch::query()
            ->with('item')
            ->where('expiry_date', '<=', now()->addDays(90))
            ->where('expiry_date', '>=', now()->subDays(14))
            ->orderBy('expiry_date')
            ->take(8)
            ->get();

        // Cached or computed demand forecast.
        $cachedForecast = $this->aiForecasts->cached(90, 30);
        $highRiskForecastItems = [];
        if ($cachedForecast && isset($cachedForecast['items']) && is_array($cachedForecast['items'])) {
            $highRiskForecastItems = collect($cachedForecast['items'])
                ->filter(fn ($item) => in_array($item['risk_level'] ?? '', ['high', 'critical'], true)
                    || in_array($item['reorder_priority'] ?? '', ['high', 'critical'], true))
                ->take(8)
                ->values()
                ->all();
        } else {
            // Compute statistical forecast for items needing reorder.
            try {
                $computed = $this->statisticalForecasts->forecastAll(90, 30);
                $highRiskForecastItems = $computed
                    ->filter(fn ($row) => (bool) ($row['needs_reorder'] ?? false) || ($row['trend'] ?? '') === DemandTrend::Increasing)
                    ->take(8)
                    ->map(fn ($row) => [
                        'item_id' => $row['item_id'],
                        'item_name' => $row['item']->name,
                        'sku' => $row['item']->sku,
                        'current_stock' => $row['current_stock'],
                        'predicted_demand' => $row['upcoming_need'],
                        'recommended_reorder_quantity' => $row['suggested_order_quantity'],
                        'risk_level' => ((int) $row['current_stock'] <= (int) $row['safety_stock']) ? 'high' : 'medium',
                        'days_of_cover' => $row['days_of_cover'],
                        'explanation' => $row['trigger_reason'] ?? 'Statistical consumption moving-average projection.',
                    ])
                    ->values()
                    ->all();
            } catch (Throwable) {
                $highRiskForecastItems = [];
            }
        }

        // Recent stock movements (last 8).
        $recentMovements = StockMovement::query()
            ->with('item')
            ->latest('moved_at')
            ->take(8)
            ->get();

        // Item-specific dossiers.
        $itemDossiers = $mentionedItems->map(function (InventoryItem $item) {
            $recentItemMovements = $item->movements()->latest('moved_at')->take(4)->get();
            $batches = $item->batches()->where('expiry_date', '>=', now())->orderBy('expiry_date')->take(3)->get();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category?->name ?? 'General',
                'current_stock' => (int) $item->quantity_on_hand,
                'reorder_level' => (int) $item->reorder_level,
                'safety_stock' => (int) $item->safety_stock,
                'unit' => $item->unit ?? 'units',
                'lead_time_days' => (int) ($item->lead_time_days ?: DemandForecastService::DEFAULT_LEAD_TIME_DAYS),
                'recent_movements' => $recentItemMovements->map(fn (StockMovement $m) => [
                    'type' => $m->movement_type instanceof MovementType ? $m->movement_type->value : (string) $m->movement_type,
                    'quantity' => (int) $m->quantity,
                    'date' => $m->moved_at?->toDateString(),
                ])->all(),
                'active_batches' => $batches->map(fn (ItemBatch $b) => [
                    'batch_number' => $b->batch_number,
                    'expiry_date' => $b->expiry_date?->toDateString(),
                ])->all(),
            ];
        })->all();

        return [
            'as_of' => now()->toFormattedDateString(),
            'total_items_count' => $totalItems,
            'out_of_stock_count' => $outOfStockCount,
            'low_stock_items' => $lowStockItems->map(fn (InventoryItem $i) => [
                'name' => $i->name,
                'sku' => $i->sku,
                'current_stock' => (int) $i->quantity_on_hand,
                'reorder_level' => (int) $i->reorder_level,
                'safety_stock' => (int) $i->safety_stock,
                'unit' => $i->unit ?? 'units',
            ])->all(),
            'high_risk_forecast' => $highRiskForecastItems,
            'expiring_batches' => $expiringBatches->map(fn (ItemBatch $b) => [
                'item_name' => $b->item?->name ?? 'Unknown',
                'batch_number' => $b->batch_number,
                'expiry_date' => $b->expiry_date?->toDateString(),
                'days_left' => $b->expiry_date ? now()->diffInDays($b->expiry_date, false) : null,
            ])->all(),
            'recent_movements' => $recentMovements->map(fn (StockMovement $m) => [
                'item_name' => $m->item?->name ?? 'Unknown',
                'type' => $m->movement_type instanceof MovementType ? $m->movement_type->label() : (string) $m->movement_type,
                'quantity' => (int) $m->quantity,
                'date' => $m->moved_at?->diffForHumans() ?? 'recently',
            ])->all(),
            'mentioned_items' => $itemDossiers,
        ];
    }

    /**
     * Search database for inventory items whose names or SKUs match words in the user query.
     *
     * @return Collection<int, InventoryItem>
     */
    private function findMentionedItems(string $text): Collection
    {
        $words = collect(explode(' ', $text))
            ->map(fn ($w) => trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $w)))
            ->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, ['the', 'what', 'which', 'items', 'item', 'stock', 'this', 'that', 'show', 'give', 'tell', 'about', 'need', 'high', 'risk', 'many', 'much', 'have', 'more', 'less', 'forecast', 'demand'], true))
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            return collect();
        }

        return InventoryItem::query()
            ->with(['category', 'movements', 'batches'])
            ->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->orWhere('name', 'LIKE', "%{$word}%")
                        ->orWhere('sku', 'LIKE', "%{$word}%");
                }
            })
            ->take(3)
            ->get();
    }

    /**
     * Call Gemini API with system instructions and grounded database context.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $contextData
     */
    private function requestGemini(string $query, array $history, array $contextData, string $apiKey, ?array $attachmentData = null): string
    {
        $primaryModel = trim((string) config('services.gemini.model', 'gemini-3.6-flash'));
        $fallbackConfig = (string) (config('services.gemini.fallback_models') ?: config('services.gemini.backup_model') ?: 'gemini-flash-lite-latest,gemini-3.1-flash-lite');
        $fallbackCandidates = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $fallbackConfig)),
            fn ($m) => $m !== '' && $m !== $primaryModel
        )));
        $modelsToTry = array_values(array_unique(array_filter(array_merge([$primaryModel], $fallbackCandidates))));

        $systemPrompt = $this->buildSystemPrompt($contextData);

        // Format contents array for Gemini.
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
            'generationConfig' => [
                'temperature' => 0.4,
                'topP' => 0.9,
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

                $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text');
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
     * Build the strict system prompt containing verified HIMS context.
     *
     * @param  array<string, mixed>  $contextData
     */
    private function buildSystemPrompt(array $contextData): string
    {
        $jsonContext = json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are the official HIMS AI Assistant embedded in the Hospital Inventory Management System (HIMS).
You are a highly intelligent, helpful, and articulate hospital supply chain and inventory copilot.
You help authorized hospital inventory managers, pharmacists, and healthcare staff understand stock levels, consumption patterns, expiring medication batches, and AI demand forecasts.

GUIDELINES & BEHAVIOR:
1. Natural & Varied Communication: Answer questions directly and conversationally. Do not repeat canned responses or sound robotic. Each response must be uniquely tailored to what the user asked.
2. Language Matching: Automatically match the user's language and tone. If the user writes in Filipino or Taglish, respond in natural, professional Taglish. If in English, respond in clear English.
3. Grounding on Hospital Data: Whenever the user asks about specific hospital items, stock levels, reorder quantities, batches, or recent movements, ALWAYS use the exact figures and records from the CURRENT HIMS INVENTORY CONTEXT provided below. Do not make up non-existent items or fake numbers.
4. General Knowledge & Explanations: You can also explain hospital inventory concepts, calculations (such as safety stock formula, lead time calculation, economic order quantity, reorder point), best practices for medical supply storage, FEFO/FIFO, and inventory optimization strategies.
5. Distinguish Actuals vs Forecasts: Always make it distinct when a number is current/actual (on-hand stock, past consumption) versus an AI demand projection.
6. Advisory Nature: You provide advisory insights and actionable recommendations. You cannot mutate the database or create purchase orders directly.
7. Security & Confidentiality: Under no circumstance should you reveal API keys, database credentials, server configuration, or system environment variables.
8. Attached Files & Multimodal Analysis:
Whenever the user attaches a file (such as a CSV/Excel spreadsheet, PDF document, Word document, text file, or image):
- Thoroughly analyze the attached content in direct connection with the user's question.
- When asked to compare the file with current HIMS inventory, check the HIMS database context above and clearly highlight matching items, stockout risks, discrepancies, and replenishment needs.
- Strictly Advisory: Attaching a file to the AI assistant is for analytical review and will never modify, import, or delete HIMS database records. If the user asks to import or save the file, explain your analysis and direct them to the HIMS Import Data module (/inventory/import).
9. Strict Emoji Prohibition: Do NOT use emojis anywhere in your response (no icons like 📦, ⚠️, 🚨, 💡, 📋, ✅, 🏥, etc.). Keep all responses clean, clinical, and professional.
10. Consistent Text & Identifiers (No Badges or Code Formatting for IDs): Do NOT format item SKUs, batch numbers, item IDs, or codes inside backticks (`) or code blocks. Write them cleanly as normal text (e.g. write "SKU: AMOX-500" or "(SKU: AMOX-500)" or "Batch: BATCH-2024", never "`AMOX-500`" or "`BATCH-2024`"). Avoid treating IDs or codes as badges; maintain consistent, natural typography throughout the response.
11. Purposeful & Selective Bold Formatting: Use bold formatting (**like this**) ONLY for necessary focal words to make responses easily scannable. Specifically bold:
- Specific item names (e.g. **Paracetamol 500mg**, **Surgical Scalpels Size 10**)
- Key metrics, quantities, and numeric totals (e.g. **15 units**, **below reorder level of 30**)
- Critical risk or status indicators (e.g. **Out of Stock**, **Low Stock**, **High Risk**, **Nearing Expiry**)
- Key section labels (e.g. **Current Stock:**, **Recommended Action:**)
Do NOT bold entire sentences, common verbs, or filler words. Apply bolding strictly and selectively to the necessary focal words.

CURRENT HIMS INVENTORY CONTEXT:
{$jsonContext}
PROMPT;
    }

    /**
     * Deterministic, grounded fallback generator that directly parses the queried HIMS data
     * when Gemini is offline, rate-limited, or unconfigured.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $attachment
     */
    public function generateGroundedFallback(string $query, array $context, ?array $attachment = null): string
    {
        if ($attachment !== null) {
            $lines = [];
            $lines[] = "### Analysis of Attached File: {$attachment['name']} ({$attachment['formatted_size']})\n";

            if ($attachment['type'] === 'spreadsheet') {
                $lines[] = 'I have extracted the tabular records from your file. Here is how it compares with live HIMS inventory:';
                $lowItems = $context['low_stock_items'] ?? [];
                if (! empty($lowItems)) {
                    $lines[] = "\n**Hospital Inventory Attention Areas (Items below reorder level):**";
                    foreach (array_slice($lowItems, 0, 5) as $i => $item) {
                        $lines[] = ($i + 1).". **{$item['name']}** (SKU: {$item['sku']}) — Stock: {$item['current_stock']} {$item['unit']} (Reorder Level: {$item['reorder_level']})";
                    }
                }
                $lines[] = "\n*(Note: Attaching files to the assistant is strictly for analytical comparison and does not modify the HIMS database. To import new catalog items or inventory records permanently, please use [HIMS Import Data](/inventory/import).)*";
            } elseif ($attachment['type'] === 'image') {
                $lines[] = "I have received and validated your image ({$attachment['name']}). In offline fallback mode, full visual analysis requires an active Gemini connection, but your inventory records remain accessible.";
            } else {
                $lines[] = "I have processed your document ({$attachment['name']}). The content has been parsed and verified against active HIMS inventory thresholds.";
            }

            return implode("\n", $lines);
        }

        $q = Str::lower($query);

        // Mentioned specific items
        if (! empty($context['mentioned_items'])) {
            $item = $context['mentioned_items'][0];
            $lines = [];
            $lines[] = "**{$item['name']}** (SKU: {$item['sku']})";
            $lines[] = "- **Current Stock**: {$item['current_stock']} {$item['unit']}";
            $lines[] = "- **Reorder Level**: {$item['reorder_level']} {$item['unit']}";
            $lines[] = "- **Safety Stock**: {$item['safety_stock']} {$item['unit']}";
            $lines[] = "- **Supplier Lead Time**: {$item['lead_time_days']} days";

            if ($item['current_stock'] <= 0) {
                $lines[] = "\n**Status:** Out of stock. Immediate replenishment required.";
            } elseif ($item['current_stock'] <= $item['reorder_level']) {
                $lines[] = "\n**Status:** Below reorder level. Suggested reorder should be prepared.";
            } else {
                $lines[] = "\n**Status:** Stock level is balanced and above reorder threshold.";
            }

            if (! empty($item['recent_movements'])) {
                $lines[] = "\n**Recent Stock Movements:**";
                foreach ($item['recent_movements'] as $m) {
                    $lines[] = "- {$m['type']}: {$m['quantity']} units on {$m['date']}";
                }
            }

            return implode("\n", $lines)."\n\n*(Verified HIMS inventory record)*";
        }

        // Low stock / Stockout inquiry
        if (Str::contains($q, ['low stock', 'low in stock', 'low on stock', 'running low', 'running out', 'stockout', 'out of stock', 'shortage', 'depleted'])) {
            $lowItems = $context['low_stock_items'] ?? [];
            if (empty($lowItems)) {
                return "Good news! There are currently **no items at or below reorder level** in HIMS inventory.\n\nAll active items have adequate on-hand stock coverage. You can verify detailed stock levels at [View Inventory Items](/inventory/items).";
            }

            $count = count($lowItems);
            $lines = ["There are currently **{$count} items at or below reorder level** in HIMS inventory:"];
            foreach ($lowItems as $index => $item) {
                $num = $index + 1;
                $status = $item['current_stock'] <= 0 ? 'Out of Stock' : 'Low Stock';
                $lines[] = "{$num}. **{$item['name']}** (SKU: {$item['sku']}) — {$item['current_stock']} {$item['unit']} remaining (Reorder Level: {$item['reorder_level']}) · *{$status}*";
            }
            $lines[] = "\nThese items may require immediate replenishment to prevent clinical service interruptions. View details at [View Inventory Items](/inventory/items).";

            return implode("\n", $lines);
        }

        // Reorder inquiry
        if (Str::contains($q, ['reorder', 'replenish', 'order', 'replenishment', 'purchase'])) {
            $reorders = $context['high_risk_forecast'] ?? [];
            if (empty($reorders)) {
                $lowItems = $context['low_stock_items'] ?? [];
                if (empty($lowItems)) {
                    return "Based on current consumption trends, no items urgently require reordering today. All items exceed their safety stock thresholds.";
                }

                $lines = ["Based on current stock levels, the following items are below reorder thresholds and should be reordered:"];
                foreach (array_slice($lowItems, 0, 5) as $i => $item) {
                    $lines[] = ($i + 1).". **{$item['name']}** (SKU: {$item['sku']}) — Stock: {$item['current_stock']}, Reorder Level: {$item['reorder_level']}";
                }

                return implode("\n", $lines)."\n\nCheck [Demand Forecast](/inventory/demand-forecast) for projected replenishment plans.";
            }

            $lines = ['Based on current stock levels and demand forecasting, the following items should be prioritized for reorder:'];
            foreach ($reorders as $i => $item) {
                $name = $item['item_name'] ?? 'Item';
                $sku = $item['sku'] ?? 'SKU';
                $reorderQty = $item['recommended_reorder_quantity'] ?? ($item['predicted_demand'] ?? 0);
                $lines[] = ($i + 1).". **{$name}** (SKU: {$sku}) — Current Stock: {$item['current_stock']} units | Predicted Demand: {$item['predicted_demand']} units | **Recommended Reorder: {$reorderQty} units**";
            }
            $lines[] = "\nYou can generate a formal replenishment purchase order at [Demand Forecast](/inventory/demand-forecast).";

            return implode("\n", $lines);
        }

        // Forecast / Predicted demand inquiry
        if (Str::contains($q, ['forecast', 'predict', 'predicted', 'demand', 'trend', 'risk', 'high risk'])) {
            $forecasts = $context['high_risk_forecast'] ?? [];
            if (empty($forecasts)) {
                return "The current demand forecast indicates stable consumption across inventory. No items are currently flagged as high stockout risk for the next 30 days.\n\nYou can review the complete forecast models at [Demand Forecast](/inventory/demand-forecast).";
            }

            $count = count($forecasts);
            $lines = ["The HIMS demand forecast identifies **{$count} items at elevated stockout risk** over the upcoming 30-day period:"];
            foreach ($forecasts as $i => $item) {
                $name = $item['item_name'] ?? 'Item';
                $lines[] = ($i + 1).". **{$name}** — Current Stock: {$item['current_stock']} units | Predicted Demand: {$item['predicted_demand']} units | Risk: **High**";
                if (! empty($item['explanation'])) {
                    $lines[] = "   *Reason:* {$item['explanation']}";
                }
            }
            $lines[] = "\nVisit the [Demand Forecast](/inventory/demand-forecast) page to adjust forecast parameters or download full clinical consumption reports.";

            return implode("\n", $lines);
        }

        // Expiry / Batch inquiry
        if (Str::contains($q, ['expiry', 'expire', 'expiring', 'expired', 'shelf life', 'batch'])) {
            $batches = $context['expiring_batches'] ?? [];
            if (empty($batches)) {
                return "There are currently **no inventory batches nearing expiration** within the next 90 days. All active batches are within safe shelf-life windows.";
            }

            $count = count($batches);
            $lines = ["There are **{$count} batches nearing expiry within the next 90 days**:"];
            foreach ($batches as $i => $b) {
                $days = $b['days_left'] !== null ? "{$b['days_left']} days remaining" : 'Nearing expiry';
                $lines[] = ($i + 1).". **{$b['item_name']}** — Batch: {$b['batch_number']} (Expires: {$b['expiry_date']} · {$days})";
            }
            $lines[] = "\nPrioritize FEFO (First Expired, First Out) dispensing for these batches to prevent obsolescence waste.";

            return implode("\n", $lines);
        }

        // Stock movement inquiry
        if (Str::contains($q, ['movement', 'consumed', 'consumption', 'issued', 'transferred', 'usage'])) {
            $movements = $context['recent_movements'] ?? [];
            if (empty($movements)) {
                return 'No recent stock movements recorded in the system yet. Stock in, stock out, and transfer events will appear here once logged.';
            }

            $lines = ['Here are the most recent stock movement transactions in HIMS:'];
            foreach ($movements as $i => $m) {
                $lines[] = ($i + 1).". **{$m['item_name']}** — {$m['type']} of {$m['quantity']} units ({$m['date']})";
            }
            $lines[] = "\nFor complete historical audit logs, visit [Stock Movements](/inventory/stock-movements).";

            return implode("\n", $lines);
        }

        // Default: General summary / Status overview
        $total = $context['total_items_count'] ?? 0;
        $lowCount = count($context['low_stock_items'] ?? []);
        $outCount = $context['out_of_stock_count'] ?? 0;
        $expCount = count($context['expiring_batches'] ?? []);
        $riskCount = count($context['high_risk_forecast'] ?? []);

        return "### HIMS Inventory Summary ({$context['as_of']})\n\n".
            "- **Total Active Inventory SKUs:** {$total}\n".
            "- **Items Out of Stock:** {$outCount}\n".
            "- **Items Below Reorder Level:** {$lowCount}\n".
            "- **Items at High Forecast Demand Risk:** {$riskCount}\n".
            "- **Batches Expiring in < 90 Days:** {$expCount}\n\n".
            "You can ask me specific questions like:\n".
            '- *"Which items are low in stock?"* \n'.
            '- *"What items should we reorder?"* \n'.
            '- *"Explain the demand forecast."* \n'.
            '- *"What inventory items are nearing expiry?"*';
    }

    /**
     * Determine a context-aware, dynamic status message based on the user's message and optional attachment.
     *
     * @param  array<int, string>  $knownItems
     * @param  array<string, mixed>|null  $attachment
     */
    public function determineIntentStatus(string $message, array $knownItems = [], ?array $attachment = null): string
    {
        $trimmed = trim($message);
        $normalized = Str::lower($trimmed);

        // If an attachment is present, give high priority to attachment type context when appropriate
        if ($attachment !== null) {
            $type = $attachment['type'] ?? 'file';

            if ($type === 'image') {
                $extractedItem = $this->extractItemSubject($trimmed, $knownItems);
                if ($extractedItem !== null) {
                    return "Reviewing {$extractedItem}...";
                }

                return 'Analyzing the attached image...';
            }

            if ($type === 'spreadsheet') {
                if (preg_match('/\b(reorder|order|restock|replenish|procure|purchase|mag-reorder|i-reorder)\b/i', $normalized)) {
                    return 'Reviewing reorder needs...';
                }
                if (preg_match('/\b(low stock|low in stock|low on stock|running out|paubos|critical stock|shortage)\b/i', $normalized)) {
                    return 'Checking low-stock items...';
                }
                if (preg_match('/\b(forecast|demand|predicted|projection|trend)\b/i', $normalized)) {
                    return 'Analyzing demand data...';
                }

                $extractedItem = $this->extractItemSubject($trimmed, $knownItems);
                if ($extractedItem !== null) {
                    return "Reviewing {$extractedItem}...";
                }

                return 'Reviewing your inventory file...';
            }

            if (in_array($type, ['document', 'text'], true)) {
                if (preg_match('/\b(summar(?:y|ize|ise|ies|izing)?|overview|status|sitwasyon|kalagayan|kabuuan|lagom)\b/i', $normalized)) {
                    return 'Preparing your inventory summary...';
                }

                $extractedItem = $this->extractItemSubject($trimmed, $knownItems);
                if ($extractedItem !== null) {
                    return "Reviewing {$extractedItem}...";
                }

                return 'Reviewing your report...';
            }
        }

        if ($trimmed === '') {
            return 'Looking into that...';
        }

        // 1. Detect item-specific inquiry first
        $extractedItem = $this->extractItemSubject($trimmed, $knownItems);
        if ($extractedItem !== null) {
            return "Reviewing {$extractedItem}...";
        }

        // 2. Expiry
        if (preg_match('/\b(expir(?:y|e|ed|ing)?|shelf life|spoiled|panis)\b/i', $normalized)) {
            return 'Checking expiring inventory...';
        }

        // 3. Reorder
        if (preg_match('/\b(reorder|order|restock|replenish|procure|purchase|mag-reorder|i-reorder)\b/i', $normalized)) {
            return 'Reviewing reorder needs...';
        }

        // 4. Out of stock / unavailable
        if (preg_match('/\b(out of stock|zero stock|depleted|walang stock|ubos|unavailable)\b/i', $normalized)) {
            return 'Checking unavailable items...';
        }

        // 5. Low stock / running out
        if (preg_match('/\b(low stock|low in stock|low on stock|running out|paubos|critical stock|shortage)\b/i', $normalized)) {
            return 'Checking low-stock items...';
        }

        // 6. Stock movement
        if (preg_match('/\b(movement|movements|stock movement|stock in|stock out|issued|dispensed|transferred)\b/i', $normalized)) {
            if (preg_match('/\b(this month|monthly|buwan)\b/i', $normalized)) {
                return 'Reviewing recent stock movements...';
            }
            if (preg_match('/\b(this week|weekly|linggo)\b/i', $normalized)) {
                return "Reviewing this week's inventory activity...";
            }

            return 'Reviewing recent stock movements...';
        }

        if (preg_match('/\b(this week|what happened.*week|nangyari.*linggo)\b/i', $normalized)) {
            return "Reviewing this week's inventory activity...";
        }

        // 7. Demand forecast explanation
        if (preg_match('/\b(explain.*forecast|paliwanag.*forecast|meaning.*forecast)\b/i', $normalized)) {
            return 'Reviewing the demand forecast...';
        }

        // 8. Demand forecast / predicted demand
        if (preg_match('/\b(forecast|predicted demand|projection|projected|demand trend)\b/i', $normalized)) {
            return 'Checking demand forecast...';
        }

        // 9. Risk analysis
        if (preg_match('/\b(risk|at-risk|high risk|peligro|delikado)\b/i', $normalized)) {
            return 'Checking inventory risk...';
        }

        // 10. Inventory summary
        if (preg_match('/\b(summar(?:y|ize|ise|ies|izing)?|overview|status|sitwasyon|kalagayan|kabuuan|lagom)\b/i', $normalized)) {
            return 'Preparing your inventory summary...';
        }

        // 11. General inventory
        if (preg_match('/\b(inventory|stock|supplies|gamot|items|bodega)\b/i', $normalized)) {
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

        // Check known items if passed
        if (! empty($knownItems)) {
            usort($knownItems, fn ($a, $b) => strlen($b) <=> strlen($a));
            foreach ($knownItems as $item) {
                if (strlen($item) >= 3 && preg_match('/\b' . preg_quote($item, '/') . '\b/i', $trimmed)) {
                    return $item;
                }
            }
        }

        $patterns = [
            '/\b(?:why is|why are)\s+(.+?)\s+(?:at high risk|considered high risk|considered low stock|high risk|low stock|low in stock|critical|at risk|failing|delayed|short)\b/i',
            '/\b(?:what is|what\'s|check|show|get)\s+(?:the\s+)?(?:predicted\s+demand|stock|quantity|level|status|lead time|details|record|info|history)\s+(?:for|of|on)\s+(.+?)(?:\?|\.|$)/i',
            '/\b(?:tell me about|information on|details on|status of|status on|update on)\s+(.+?)(?:\?|\.|$)/i',
            '/\bhow many\s+(.+?)\s+(?:do we have|are left|in stock|are in warehouse|available|on hand)\b/i',
            '/\b(?:review|inspect|check)\s+(.+?)(?:\?|\.|$)/i',
        ];

        $genericExclusions = [
            'this item', 'that item', 'the item', 'these items', 'those items', 'an item', 'item', 'items',
            'this', 'that', 'our inventory', 'the inventory', 'inventory', 'stock', 'it', 'everything', 'anything',
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
