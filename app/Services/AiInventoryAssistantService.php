<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\DemandTrend;
use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AiInventoryAssistantService
{
    public function __construct(
        private readonly DemandForecastService $statisticalForecasts,
        private readonly AiDemandForecastService $aiForecasts,
    ) {}

    /**
     * Process an inventory inquiry using grounded HIMS database context and the Gemini API.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     * @return array{reply: string, source: string}
     */
    public function respond(User $actor, string $userMessage, array $conversationHistory = []): array
    {
        $cleanMessage = trim($userMessage);
        if ($cleanMessage === '') {
            return [
                'reply' => 'Please ask a question regarding HIMS inventory, stock levels, or demand forecasts.',
                'source' => 'system',
            ];
        }

        // 1. Gather relevant HIMS inventory data based on the query.
        $contextData = $this->gatherContext($cleanMessage, $conversationHistory);

        // 2. If Gemini API key is not set, generate a rich deterministic grounded response immediately.
        $apiKey = (string) (config('services.gemini.key') ?: config('services.gemini.api_key'));
        if (trim($apiKey) === '') {
            return [
                'reply' => $this->generateGroundedFallback($cleanMessage, $contextData),
                'source' => 'grounded_fallback',
            ];
        }

        // 3. Attempt Gemini generation with multi-model failover.
        try {
            $reply = $this->requestGemini($cleanMessage, $conversationHistory, $contextData, $apiKey);

            return [
                'reply' => $reply,
                'source' => 'ai',
            ];
        } catch (Throwable) {
            // Gracefully fall back to verified database figures on any API error/rate-limit.
            return [
                'reply' => $this->generateGroundedFallback($cleanMessage, $contextData),
                'source' => 'grounded_fallback',
            ];
        }
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
    private function requestGemini(string $query, array $history, array $contextData, string $apiKey): string
    {
        $primaryModel = trim((string) config('services.gemini.model', 'gemini-flash-lite-latest'));
        $fallbackConfig = (string) (config('services.gemini.fallback_models') ?: config('services.gemini.backup_model') ?: 'gemini-3.1-flash-lite');
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

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $query]],
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
                    ->timeout(max(5, (int) config('services.gemini.timeout', 25)))
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
                    return trim($text);
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

CURRENT HIMS INVENTORY CONTEXT:
{$jsonContext}
PROMPT;
    }

    /**
     * Deterministic, grounded fallback generator that directly parses the queried HIMS data
     * when Gemini is offline, rate-limited, or unconfigured.
     *
     * @param  array<string, mixed>  $context
     */
    public function generateGroundedFallback(string $query, array $context): string
    {
        $q = Str::lower($query);

        // Mentioned specific items
        if (! empty($context['mentioned_items'])) {
            $item = $context['mentioned_items'][0];
            $lines = [];
            $lines[] = "**{$item['name']}** (SKU: `{$item['sku']}`)";
            $lines[] = "- **Current Stock**: {$item['current_stock']} {$item['unit']}";
            $lines[] = "- **Reorder Level**: {$item['reorder_level']} {$item['unit']}";
            $lines[] = "- **Safety Stock**: {$item['safety_stock']} {$item['unit']}";
            $lines[] = "- **Supplier Lead Time**: {$item['lead_time_days']} days";

            if ($item['current_stock'] <= 0) {
                $lines[] = "\n⚠️ **Status:** Out of stock. Immediate replenishment required.";
            } elseif ($item['current_stock'] <= $item['reorder_level']) {
                $lines[] = "\n⚠️ **Status:** Below reorder level. Suggested reorder should be prepared.";
            } else {
                $lines[] = "\n✅ **Status:** Stock level is balanced and above reorder threshold.";
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
                $lines[] = "{$num}. **{$item['name']}** (`{$item['sku']}`) — {$item['current_stock']} {$item['unit']} remaining (Reorder Level: {$item['reorder_level']}) · *{$status}*";
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
                    $lines[] = ($i + 1).". **{$item['name']}** (`{$item['sku']}`) — Stock: {$item['current_stock']}, Reorder Level: {$item['reorder_level']}";
                }

                return implode("\n", $lines)."\n\nCheck [Demand Forecast](/inventory/demand-forecast) for projected replenishment plans.";
            }

            $lines = ['Based on current stock levels and demand forecasting, the following items should be prioritized for reorder:'];
            foreach ($reorders as $i => $item) {
                $name = $item['item_name'] ?? 'Item';
                $sku = $item['sku'] ?? 'SKU';
                $reorderQty = $item['recommended_reorder_quantity'] ?? ($item['predicted_demand'] ?? 0);
                $lines[] = ($i + 1).". **{$name}** (`{$sku}`) — Current Stock: {$item['current_stock']} units | Predicted Demand: {$item['predicted_demand']} units | **Recommended Reorder: {$reorderQty} units**";
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
                $lines[] = ($i + 1).". **{$b['item_name']}** — Batch `{$b['batch_number']}` (Expires: {$b['expiry_date']} · {$days})";
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
}
