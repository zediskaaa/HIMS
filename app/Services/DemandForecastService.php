<?php

namespace App\Services;

use App\Enums\DemandTrend;
use App\Enums\MovementType;
use App\Models\DemandPlan;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demand Forecasting: works out how much of an item to reorder, and when.
 *
 * Everything here is derived from `stock_movements` — the same rows the stock
 * screens are built from. The screen this replaces asked the user to type in
 * the historical usage, which meant the forecast only ever restated whatever
 * number was entered.
 *
 * The method is a moving average over a fixed window, which is deliberate:
 *
 *   average daily usage = quantity consumed in the window / days in the window
 *   safety stock        = average daily usage x buffer days
 *   reorder point       = (average daily usage x supplier lead time) + safety stock
 *   suggested order     = (average daily usage x forecast horizon) + safety stock
 *                         - stock currently on hand
 *
 * These are the textbook inventory-control formulas, and each intermediate
 * number is stored on the plan and shown on screen, so a panel can check the
 * arithmetic by hand. A seasonal or regression model would fit a hospital's
 * yearly patterns better but needs years of history to beat a moving average —
 * this system has none yet, and an unexplainable forecast is worse than a
 * plain one.
 */
class DemandForecastService
{
    /** Days of history the average is taken over. */
    public const DEFAULT_ANALYSIS_DAYS = 90;

    /** Days ahead the forecast covers. */
    public const DEFAULT_FORECAST_DAYS = 30;

    /** Assumed supplier lead time when the item has no better figure. */
    public const DEFAULT_LEAD_TIME_DAYS = 7;

    /** Extra days of cover held against demand spikes and late deliveries. */
    public const DEFAULT_BUFFER_DAYS = 7;

    /**
     * Forecast one item without saving anything.
     *
     * @return array<string, mixed>
     */
    public function forecast(
        InventoryItem $item,
        int $analysisDays = self::DEFAULT_ANALYSIS_DAYS,
        int $forecastDays = self::DEFAULT_FORECAST_DAYS,
        ?int $leadTimeDays = null,
        int $bufferDays = self::DEFAULT_BUFFER_DAYS,
    ): array {
        $analysisDays = max(1, $analysisDays);
        $forecastDays = max(1, $forecastDays);
        $leadTimeDays = max(0, $leadTimeDays ?? self::DEFAULT_LEAD_TIME_DAYS);

        $since = now()->subDays($analysisDays);
        $consumption = $this->consumptionSince($item, $since);

        return $this->calculateForecast(
            $item,
            $consumption,
            $since,
            $analysisDays,
            $forecastDays,
            $leadTimeDays,
            $bufferDays,
        );
    }

    /**
     * Forecast every active item, worst days-of-cover first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forecastAll(
        int $analysisDays = self::DEFAULT_ANALYSIS_DAYS,
        int $forecastDays = self::DEFAULT_FORECAST_DAYS,
    ): Collection {
        $analysisDays = max(1, $analysisDays);
        $since = now()->subDays($analysisDays);

        return InventoryItem::query()
            ->where('status', '!=', 'inactive')
            ->with([
                'supplier',
                'category',
                'movements' => fn ($query) => $query
                    ->whereIn('movement_type', MovementType::consumptionValues())
                    ->where('moved_at', '>=', $since)
                    ->orderBy('moved_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                ...$this->calculateForecast($item, $item->movements, $since, $analysisDays, $forecastDays),
                'item' => $item,
            ])
            ->sortBy(fn (array $row) => $row['days_of_cover'] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Save a forecast as a DemandPlan so it can be quoted later.
     *
     * A plan is a snapshot on purpose: the movement history behind it keeps
     * moving, so re-running the numbers next month would not reproduce what
     * the order was actually based on.
     *
     * @param  array<string, mixed>  $forecast
     */
    public function storePlan(array $forecast, ?int $userId = null, ?string $notes = null): DemandPlan
    {
        return DemandPlan::create([
            'plan_number' => $this->nextPlanNumber(),
            'item_id' => $forecast['item_id'],
            'analysis_days' => $forecast['analysis_days'],
            'forecast_days' => $forecast['forecast_days'],
            'lead_time_days' => $forecast['lead_time_days'],
            'current_stock' => $forecast['current_stock'],
            'historical_usage' => $forecast['historical_usage'],
            'average_daily_usage' => $forecast['average_daily_usage'],
            'upcoming_need' => $forecast['upcoming_need'],
            'reorder_point' => $forecast['reorder_point'],
            'safety_stock' => $forecast['safety_stock'],
            'suggested_order_quantity' => $forecast['suggested_order_quantity'],
            'days_of_cover' => $forecast['days_of_cover'],
            'trend' => $forecast['trend'] instanceof DemandTrend
                ? $forecast['trend']->value
                : $forecast['trend'],
            'trigger_reason' => $forecast['trigger_reason'],
            'generated_by' => $userId ?? auth()->id(),
            'generated_at' => now(),
            'notes' => $notes,
            'status' => 'draft',
        ]);
    }

    /**
     * Consumption movements for an item inside the window.
     *
     * Aggregated in PHP rather than with a database date function so the same
     * code runs on TiDB in production and SQLite under test. The row counts
     * involved are small — one item over 90 days.
     *
     * @return Collection<int, StockMovement>
     */
    private function consumptionSince(InventoryItem $item, Carbon $since): Collection
    {
        return StockMovement::query()
            ->where('item_id', $item->id)
            ->whereIn('movement_type', MovementType::consumptionValues())
            ->where('moved_at', '>=', $since)
            ->orderBy('moved_at')
            ->get();
    }

    /**
     * @param  Collection<int, StockMovement>  $consumption
     * @return array<string, mixed>
     */
    private function calculateForecast(
        InventoryItem $item,
        Collection $consumption,
        Carbon $since,
        int $analysisDays,
        int $forecastDays,
        ?int $leadTimeDays = null,
        int $bufferDays = self::DEFAULT_BUFFER_DAYS,
    ): array {
        $leadTimeDays = max(0, $leadTimeDays ?? self::DEFAULT_LEAD_TIME_DAYS);
        $totalConsumed = (int) $consumption->sum('quantity');
        $averageDailyUsage = round($totalConsumed / $analysisDays, 3);
        $onHand = max(0, (int) $item->availableQuantity());
        $forecastQuantity = (int) ceil($averageDailyUsage * $forecastDays);
        $safetyStock = (int) ceil($averageDailyUsage * $bufferDays);
        $reorderPoint = (int) ceil($averageDailyUsage * $leadTimeDays) + $safetyStock;
        $suggestedOrderQuantity = max(0, $forecastQuantity + $safetyStock - $onHand);

        return [
            'item_id' => $item->id,
            'analysis_days' => $analysisDays,
            'forecast_days' => $forecastDays,
            'lead_time_days' => $leadTimeDays,
            'buffer_days' => $bufferDays,
            'current_stock' => $onHand,
            'historical_usage' => $totalConsumed,
            'average_daily_usage' => $averageDailyUsage,
            'upcoming_need' => $forecastQuantity,
            'reorder_point' => $reorderPoint,
            'safety_stock' => $safetyStock,
            'suggested_order_quantity' => $suggestedOrderQuantity,
            'days_of_cover' => $this->daysOfCover($onHand, $averageDailyUsage),
            'trend' => $this->trend($consumption, $since, $analysisDays),
            'movement_count' => $consumption->count(),
            'needs_reorder' => $onHand <= $reorderPoint && $averageDailyUsage > 0.0,
            'trigger_reason' => $this->triggerReason($item, $onHand, $reorderPoint, $averageDailyUsage),
        ];
    }

    /**
     * How long current stock lasts at the current rate, in whole days.
     *
     * Null means usage is zero — an item nobody has consumed is not counting
     * down to anything, which is a different statement from "0 days left".
     */
    private function daysOfCover(int $onHand, float $averageDailyUsage): ?int
    {
        if ($averageDailyUsage <= 0.0) {
            return null;
        }

        return (int) floor($onHand / $averageDailyUsage);
    }

    /**
     * Compare the later half of the window against the earlier half.
     *
     * @param  Collection<int, StockMovement>  $consumption
     */
    private function trend(Collection $consumption, Carbon $since, int $analysisDays): DemandTrend
    {
        if ($consumption->count() < 2) {
            return DemandTrend::Insufficient;
        }

        $midpoint = $since->copy()->addDays((int) floor($analysisDays / 2));

        $earlier = (int) $consumption
            ->filter(fn (StockMovement $m) => $m->moved_at !== null && $m->moved_at->lt($midpoint))
            ->sum('quantity');

        $later = (int) $consumption
            ->filter(fn (StockMovement $m) => $m->moved_at !== null && $m->moved_at->gte($midpoint))
            ->sum('quantity');

        return DemandTrend::fromChange((float) $earlier, (float) $later);
    }

    private function triggerReason(
        InventoryItem $item,
        int $onHand,
        int $reorderPoint,
        float $averageDailyUsage
    ): string {
        if ($averageDailyUsage <= 0.0) {
            return 'No recorded consumption in the analysis window.';
        }

        if ($onHand <= 0) {
            return 'Out of stock.';
        }

        if ($onHand <= $reorderPoint) {
            return sprintf('On hand (%d) is at or below the forecast reorder point (%d).', $onHand, $reorderPoint);
        }

        return sprintf('Stock is above the reorder point of %d.', $reorderPoint);
    }

    /**
     * Sequential per day, so two plans generated in the same second do not
     * collide on the unique `plan_number`.
     */
    private function nextPlanNumber(): string
    {
        $prefix = 'PLAN-'.now()->format('Ymd');

        $sequence = DemandPlan::query()
            ->where('plan_number', 'like', $prefix.'%')
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
