<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The numbers behind /inventory/reports.
 *
 * Four questions, which is what the screen it feeds is organised around:
 * what do we hold and what is it worth, which items are in trouble, what has
 * procurement spent, and what has moved.
 *
 * Nothing here writes. Every figure is derived from the same tables the
 * operational screens are built from — `item_stock_levels` for balances,
 * `stock_movements` for activity, `purchase_orders` for spend — so a report
 * cannot drift from the records it summarises.
 *
 * Aggregation is left to the database with plain SUM/COUNT/GROUP BY, which
 * behaves the same on TiDB in production as on SQLite under test. Date
 * functions are deliberately avoided for that reason. `toBase()` skips model
 * hydration: these rows are totals, not entities, and an alias like `value`
 * would otherwise collide with a real attribute.
 */
class InventoryReportService
{
    /** Window the movement and spend sections cover unless asked otherwise. */
    public const DEFAULT_PERIOD_DAYS = 30;

    /** The periods the screen offers. Anything else is clamped into range. */
    public const PERIOD_OPTIONS = [
        '1' => 'Today',
        '7' => 'Last 7 days',
        '30' => 'Last 30 days',
        '90' => 'Last 90 days',
        '365' => 'Last 12 months',
        'all' => 'All time',
        'custom' => 'Custom date range',
    ];

    /** The report types supported by the report generator. */
    public const REPORT_TYPES = [
        'all' => 'All Reports (Comprehensive)',
        'stock_status' => 'Stock Status',
        'valuation' => 'Inventory Valuation',
        'stock_by_location' => 'Stock by Location',
        'expiry_exposure' => 'Expiry Exposure',
        'movement_history' => 'Movement History',
        'procurement_expense' => 'Procurement Expense',
        'spend_by_supplier' => 'Spend by Supplier',
        'most_consumed' => 'Most Consumed Items',
        'movements_by_type' => 'Activity by Movement Type',
    ];

    /** The supported export formats. */
    public const EXPORT_FORMATS = [
        'pdf' => 'PDF / Print Document',
        'excel' => 'Microsoft Excel (.xls)',
        'csv' => 'Comma-Separated Values (.csv)',
        'json' => 'JavaScript Object Notation (.json)',
    ];

    /**
     * Every figure the screen needs, in one call.
     *
     * @return array<string, mixed>
     */
    public function build(int $days = self::DEFAULT_PERIOD_DAYS, ?Carbon $from = null, ?Carbon $to = null, array $filters = []): array
    {
        if ($from && $to) {
            $since = $from;
            $until = $to;
            $days = max(1, (int) round($from->diffInDays($to)));
            $period = [
                'days' => $days,
                'from' => $since,
                'to' => $until,
                'is_custom' => true,
                'description' => $since->format('M d, Y').' — '.$until->format('M d, Y').' (Custom Range)',
            ];
        } else {
            $days = max(1, min(365, $days));
            $since = $days === 1 ? now()->startOfDay() : now()->subDays($days)->startOfDay();
            $until = now();
            $period = [
                'days' => $days,
                'from' => $since,
                'to' => $until,
                'is_custom' => false,
                'description' => 'Last '.$days.' days ('.$since->format('M d, Y').' — '.$until->format('M d, Y').')',
            ];
        }

        $categoryId = ! empty($filters['category_id']) ? (int) $filters['category_id'] : null;
        $locationId = ! empty($filters['storage_location_id']) ? (int) $filters['storage_location_id'] : null;
        $supplierId = ! empty($filters['supplier_id']) ? (int) $filters['supplier_id'] : null;
        $movementType = ! empty($filters['movement_type']) ? (string) $filters['movement_type'] : null;
        $stockStatusFilter = ! empty($filters['stock_status']) ? (string) $filters['stock_status'] : null;

        $stockStatus = $this->stockStatus($categoryId, $locationId, $stockStatusFilter);
        $movementsByType = $this->movementsByType($since, $until, $movementType, $locationId, $categoryId);

        return [
            'period' => $period,
            'summary' => $this->summary($stockStatus),
            'stockStatus' => $stockStatus,
            'expiry' => $this->expiryExposure($categoryId, $locationId),
            'valuationByCategory' => $this->valuationByCategory($categoryId, $locationId, $stockStatusFilter),
            'stockByLocation' => $this->stockByLocation($categoryId, $locationId, $stockStatusFilter),
            'spend' => $this->procurementSpend($since, $until, $supplierId),
            'spendBySupplier' => $this->spendBySupplier($since, $until, $supplierId),
            'movementsByType' => $movementsByType,
            'movementTotals' => $this->movementTotals($movementsByType),
            'topConsumedItems' => $this->topConsumedItems($since, $until, $categoryId, $locationId),
            'recentMovements' => $this->recentMovements($since, 15, $until, $movementType, $locationId, $categoryId),
            'activeFilters' => $filters,
        ];
    }

    /**
     * The headline tiles: catalogue size, units held, what it is worth, and
     * how many items are asking for attention.
     *
     * @param  array<string, array{items: int, units: int, reserved: int, value: float}>  $stockStatus
     * @return array<string, mixed>
     */
    public function summary(?array $stockStatus = null): array
    {
        $stockStatus ??= $this->stockStatus();

        return [
            'items' => (int) collect($stockStatus)->sum('items'),
            'units_on_hand' => (int) collect($stockStatus)->sum('units'),
            'reserved_units' => (int) collect($stockStatus)->sum('reserved'),
            'stock_value' => (float) collect($stockStatus)->sum('value'),
            'needs_attention' => $stockStatus['low_stock']['items'] + $stockStatus['out_of_stock']['items'],
        ];
    }

    /**
     * Current inventory balances at catalogue or location scope.
     *
     * The cached item rollups are correct for the whole hospital. A location
     * filter must instead aggregate item_stock_levels so quantities, reserved
     * units, status buckets, and valuation all describe that exact location.
     *
     * @return Collection<int, object>
     */
    private function inventorySnapshot(?int $categoryId = null, ?int $locationId = null): Collection
    {
        $query = InventoryItem::query()
            ->leftJoin('item_categories', 'item_categories.id', '=', 'inventory_items.category_id')
            ->when($categoryId, fn ($builder) => $builder->where('inventory_items.category_id', $categoryId));

        if ($locationId) {
            return $query
                ->join('item_stock_levels', 'item_stock_levels.item_id', '=', 'inventory_items.id')
                ->where('item_stock_levels.storage_location_id', $locationId)
                ->select([
                    'inventory_items.id',
                    'inventory_items.sku',
                    'inventory_items.name',
                    'inventory_items.unit',
                    'inventory_items.reorder_level',
                    'inventory_items.unit_cost',
                ])
                ->selectRaw("coalesce(item_categories.name, 'Uncategorised') as category")
                ->selectRaw('coalesce(sum(item_stock_levels.quantity), 0) as quantity_on_hand')
                ->selectRaw('coalesce(sum(item_stock_levels.reserved_quantity), 0) as reserved_quantity')
                ->groupBy(
                    'inventory_items.id',
                    'inventory_items.sku',
                    'inventory_items.name',
                    'inventory_items.unit',
                    'inventory_items.reorder_level',
                    'inventory_items.unit_cost',
                    'item_categories.name'
                )
                ->toBase()
                ->get();
        }

        return $query
            ->select([
                'inventory_items.id',
                'inventory_items.sku',
                'inventory_items.name',
                'inventory_items.unit',
                'inventory_items.reorder_level',
                'inventory_items.unit_cost',
                'inventory_items.quantity_on_hand',
                'inventory_items.reserved_quantity',
            ])
            ->selectRaw("coalesce(item_categories.name, 'Uncategorised') as category")
            ->toBase()
            ->get();
    }

    private function stockStatusKey(int $quantity, int $reorderLevel): string
    {
        return match (true) {
            $quantity <= 0 => 'out_of_stock',
            $reorderLevel > 0 && $quantity <= $reorderLevel => 'low_stock',
            default => 'in_stock',
        };
    }

    /**
     * Items bucketed into in stock / low stock / out of stock.
     *
     * Worked out from the quantities through the item's own predicates rather
     * than read off `inventory_items.status`. That column is a cache written
     * when stock moves through InventoryAutomationService, so an item created
     * by hand and never moved still reads whatever it was created with — the
     * report would then disagree with the item screen sitting next to it.
     *
     * @return array<string, array{items: int, units: int, reserved: int, value: float}>
     */
    public function stockStatus(?int $categoryId = null, ?int $locationId = null, ?string $statusFilter = null): array
    {
        $buckets = [
            'in_stock' => ['items' => 0, 'units' => 0, 'reserved' => 0, 'value' => 0.0],
            'low_stock' => ['items' => 0, 'units' => 0, 'reserved' => 0, 'value' => 0.0],
            'out_of_stock' => ['items' => 0, 'units' => 0, 'reserved' => 0, 'value' => 0.0],
        ];

        $this->inventorySnapshot($categoryId, $locationId)
            ->each(function (object $item) use (&$buckets, $statusFilter) {
                $key = $this->stockStatusKey((int) $item->quantity_on_hand, (int) $item->reorder_level);

                if ($statusFilter && $statusFilter !== $key) {
                    return;
                }

                $buckets[$key]['items']++;
                $buckets[$key]['units'] += (int) $item->quantity_on_hand;
                $buckets[$key]['reserved'] += (int) $item->reserved_quantity;
                $buckets[$key]['value'] += (int) $item->quantity_on_hand * (float) $item->unit_cost;
            });

        return $buckets;
    }

    /**
     * Stock that is expired or heading that way, and what it is worth.
     *
     * Batches holding nothing are dropped: an expired batch with no units left
     * is a closed record, not money at risk, and listing it would bury the
     * ones that still matter.
     *
     * @return array<string, mixed>
     */
    public function expiryExposure(?int $categoryId = null, ?int $locationId = null): array
    {
        $stockLevelScope = fn ($query) => $query->when(
            $locationId,
            fn ($locationQuery) => $locationQuery->where('storage_location_id', $locationId)
        );

        $batches = ItemBatch::query()
            ->active()
            ->whereNotNull('expiry_date')
            ->with('item')
            ->withSum(['stockLevels as units_on_hand' => $stockLevelScope], 'quantity')
            ->when($categoryId, fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->when($locationId, fn ($query) => $query->whereHas('stockLevels', $stockLevelScope))
            ->fefo()
            ->get()
            ->filter(fn (ItemBatch $batch) => (int) $batch->units_on_hand > 0);

        $expired = $batches->filter(fn (ItemBatch $batch) => $batch->isExpired());
        $expiringSoon = $batches->filter(fn (ItemBatch $batch) => $batch->isExpiringSoon());

        // Batch cost where the receipt recorded one, item cost otherwise.
        $value = fn (Collection $set) => (float) $set->sum(
            fn (ItemBatch $batch) => (int) $batch->units_on_hand
                * (float) ($batch->unit_cost ?? $batch->item?->unit_cost ?? 0)
        );

        return [
            'expired' => [
                'batches' => $expired->count(),
                'units' => (int) $expired->sum(fn (ItemBatch $batch) => (int) $batch->units_on_hand),
                'value' => $value($expired),
            ],
            'expiring_soon' => [
                'batches' => $expiringSoon->count(),
                'units' => (int) $expiringSoon->sum(fn (ItemBatch $batch) => (int) $batch->units_on_hand),
                'value' => $value($expiringSoon),
            ],
            'rows' => $expired->merge($expiringSoon)->take(10)->values(),
        ];
    }

    /**
     * What the catalogue is worth, broken down by category.
     *
     * @return Collection<int, object>
     */
    public function valuationByCategory(?int $categoryId = null, ?int $locationId = null, ?string $statusFilter = null): Collection
    {
        return $this->inventorySnapshot($categoryId, $locationId)
            ->filter(fn (object $item) => ! $statusFilter || $this->stockStatusKey((int) $item->quantity_on_hand, (int) $item->reorder_level) === $statusFilter)
            ->groupBy('category')
            ->map(fn (Collection $items, string $category) => (object) [
                'category' => $category,
                'items' => $items->count(),
                'units' => (int) $items->sum('quantity_on_hand'),
                'value' => (float) $items->sum(fn (object $item) => (int) $item->quantity_on_hand * (float) $item->unit_cost),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Where the stock physically is, and how full each place is.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function stockByLocation(?int $categoryId = null, ?int $locationId = null, ?string $statusFilter = null): Collection
    {
        $allowedItemIds = $statusFilter
            ? $this->inventorySnapshot($categoryId, $locationId)
                ->filter(fn (object $item) => $this->stockStatusKey((int) $item->quantity_on_hand, (int) $item->reorder_level) === $statusFilter)
                ->pluck('id')
            : null;

        return ItemStockLevel::query()
            ->join('storage_locations', 'storage_locations.id', '=', 'item_stock_levels.storage_location_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'item_stock_levels.item_id')
            ->when($categoryId, fn ($query) => $query->where('inventory_items.category_id', $categoryId))
            ->when($locationId, fn ($query) => $query->where('storage_locations.id', $locationId))
            ->when($allowedItemIds !== null, fn ($query) => $query->whereIn('inventory_items.id', $allowedItemIds))
            ->selectRaw('storage_locations.name as location')
            ->selectRaw('storage_locations.code as code')
            ->selectRaw('storage_locations.capacity as capacity')
            ->selectRaw('count(distinct item_stock_levels.item_id) as items')
            ->selectRaw('coalesce(sum(item_stock_levels.quantity), 0) as units')
            ->selectRaw('coalesce(sum(item_stock_levels.quantity * coalesce(inventory_items.unit_cost, 0)), 0) as value')
            ->groupBy('storage_locations.id', 'storage_locations.name', 'storage_locations.code', 'storage_locations.capacity')
            ->orderByDesc('units')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'location' => $row->location,
                'code' => $row->code,
                'items' => (int) $row->items,
                'units' => (int) $row->units,
                'value' => (float) $row->value,
                'capacity' => $row->capacity === null ? null : (int) $row->capacity,
                // Null where no capacity is configured — "unknown" is not 0%.
                'utilisation' => $row->capacity
                    ? round(((int) $row->units / (int) $row->capacity) * 100, 1)
                    : null,
            ]);
    }

    /**
     * Procurement spend: what was committed, what has landed, what is still out.
     *
     * Orders are dated by `requested_at` (when the commitment was made) and
     * receipts by `received_at` (when the goods arrived), so an order raised
     * last quarter and received this one counts once in each place — which is
     * what a spend report is supposed to show.
     *
     * Outstanding is deliberately not windowed: an order left open eight
     * months ago is exactly the one a report should surface.
     *
     * @return array<string, mixed>
     */
    public function procurementSpend(Carbon $since, ?Carbon $until = null, ?int $supplierId = null): array
    {
        $totals = fn ($query) => $query
            ->selectRaw('count(*) as orders')
            ->selectRaw('coalesce(sum(total_amount), 0) as value')
            ->toBase()
            ->first();

        $ordered = $totals(PurchaseOrder::query()
            ->where('requested_at', '>=', $since)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($until, fn ($q) => $q->where('requested_at', '<=', $until)));
        $received = $totals(PurchaseOrder::query()
            ->where('status', 'received')
            ->where('received_at', '>=', $since)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($until, fn ($q) => $q->where('received_at', '<=', $until)));
        $outstanding = $totals(PurchaseOrder::query()
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->whereNotIn('status', ['received', 'cancelled']));

        $orderCount = (int) ($ordered->orders ?? 0);

        return [
            'ordered' => ['orders' => $orderCount, 'value' => (float) ($ordered->value ?? 0)],
            'received' => [
                'orders' => (int) ($received->orders ?? 0),
                'value' => (float) ($received->value ?? 0),
            ],
            'outstanding' => [
                'orders' => (int) ($outstanding->orders ?? 0),
                'value' => (float) ($outstanding->value ?? 0),
            ],
            'average_order_value' => $orderCount > 0
                ? round((float) ($ordered->value ?? 0) / $orderCount, 2)
                : 0.0,
        ];
    }

    /**
     * Spend split by vendor, biggest first.
     *
     * @return Collection<int, object>
     */
    public function spendBySupplier(Carbon $since, ?Carbon $until = null, ?int $supplierId = null): Collection
    {
        return PurchaseOrder::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.requested_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('purchase_orders.requested_at', '<=', $until))
            ->when($supplierId, fn ($query) => $query->where('purchase_orders.supplier_id', $supplierId))
            ->selectRaw('purchase_orders.supplier_id as supplier_id')
            ->selectRaw("coalesce(suppliers.name, 'Unassigned') as supplier")
            ->selectRaw('count(*) as orders')
            ->selectRaw("coalesce(sum(case when purchase_orders.status = 'received' then 1 else 0 end), 0) as received_orders")
            ->selectRaw('coalesce(sum(purchase_orders.total_amount), 0) as value')
            ->groupBy('purchase_orders.supplier_id', 'suppliers.id', 'suppliers.name')
            ->orderByDesc('value')
            ->limit(10)
            ->toBase()
            ->get();
    }

    /**
     * Activity in the window, one row per movement type.
     *
     * Every type is listed even when it did not occur. A type that simply
     * disappears reads as "not tracked"; a zero reads as "nothing happened",
     * which is the true statement.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function movementsByType(Carbon $since, ?Carbon $until = null, ?string $movementType = null, ?int $locationId = null, ?int $categoryId = null): Collection
    {
        $rows = StockMovement::query()
            ->where('moved_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('moved_at', '<=', $until))
            ->when($movementType, fn ($query) => $query->where('movement_type', $movementType))
            ->when($categoryId, fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->when($locationId, fn ($query) => $query->where(fn ($locationQuery) => $locationQuery
                ->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId)))
            ->selectRaw('movement_type')
            ->selectRaw('count(*) as movements')
            ->selectRaw('coalesce(sum(quantity), 0) as units')
            ->selectRaw('coalesce(sum(quantity * coalesce(unit_cost, 0)), 0) as value')
            ->groupBy('movement_type')
            ->toBase()
            ->get()
            ->keyBy('movement_type');

        $types = $movementType
            ? collect(MovementType::cases())->where('value', $movementType)
            : collect(MovementType::cases());

        return $types->map(fn (MovementType $type) => [
            'type' => $type,
            'movements' => (int) ($rows[$type->value]->movements ?? 0),
            'units' => (int) ($rows[$type->value]->units ?? 0),
            'value' => (float) ($rows[$type->value]->value ?? 0),
        ]);
    }

    /**
     * The one-line reading of the movement table.
     *
     * Inbound counts stock_in only and outbound counts consumption only.
     * A transfer touches two locations without changing what the hospital
     * holds, so adding it to either side would report stock appearing or
     * leaving when none did.
     *
     * @param  Collection<int, array<string, mixed>>  $movementsByType
     * @return array<string, mixed>
     */
    public function movementTotals(Collection $movementsByType): array
    {
        $by = fn (array $types, string $key) => $movementsByType
            ->whereIn('type', $types)
            ->sum($key);

        $consumption = MovementType::consumptionCases();

        return [
            'movements' => (int) $movementsByType->sum('movements'),
            'units_in' => (int) $by([MovementType::StockIn], 'units'),
            'units_out' => (int) $by($consumption, 'units'),
            'consumption_value' => (float) $by($consumption, 'value'),
            'transfers' => (int) $by([MovementType::Transfer], 'movements'),
            'disposals' => (int) $by([MovementType::Disposal], 'units'),
        ];
    }

    /**
     * The items the hospital actually gets through, by units consumed.
     *
     * @return Collection<int, object>
     */
    public function topConsumedItems(Carbon $since, ?Carbon $until = null, ?int $categoryId = null, ?int $locationId = null): Collection
    {
        $rows = StockMovement::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_movements.item_id')
            ->whereIn('stock_movements.movement_type', MovementType::consumptionValues())
            ->where('stock_movements.moved_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('stock_movements.moved_at', '<=', $until))
            ->when($categoryId, fn ($query) => $query->where('inventory_items.category_id', $categoryId))
            ->when($locationId, fn ($query) => $query->where(fn ($locationQuery) => $locationQuery
                ->where('stock_movements.from_location_id', $locationId)
                ->orWhere('stock_movements.to_location_id', $locationId)))
            ->selectRaw('inventory_items.id as item_id')
            ->selectRaw('inventory_items.name as item')
            ->selectRaw('inventory_items.sku as sku')
            ->selectRaw('inventory_items.unit as unit')
            ->selectRaw('inventory_items.quantity_on_hand as on_hand')
            ->selectRaw('count(*) as movements')
            ->selectRaw('coalesce(sum(stock_movements.quantity), 0) as units')
            // The cost recorded on the movement, falling back to the item's
            // current cost for rows written before that column was populated.
            ->selectRaw('coalesce(sum(stock_movements.quantity * coalesce(stock_movements.unit_cost, inventory_items.unit_cost, 0)), 0) as value')
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.sku', 'inventory_items.unit', 'inventory_items.quantity_on_hand')
            ->orderByDesc('units')
            ->limit(10)
            ->toBase()
            ->get();

        if ($locationId && $rows->isNotEmpty()) {
            $onHandByItem = ItemStockLevel::query()
                ->where('storage_location_id', $locationId)
                ->whereIn('item_id', $rows->pluck('item_id'))
                ->selectRaw('item_id, coalesce(sum(quantity), 0) as on_hand')
                ->groupBy('item_id')
                ->pluck('on_hand', 'item_id');

            $rows->each(fn (object $row) => $row->on_hand = (int) ($onHandByItem[$row->item_id] ?? 0));
        }

        return $rows;
    }

    /**
     * The movement ledger for the window, newest first.
     *
     * Hydrated as models rather than a raw aggregate: the view needs the
     * movement_type cast, the location names and the polymorphic reference
     * that carries the ward or vendor on an issuance or a return.
     *
     * @return Collection<int, StockMovement>
     */
    public function recentMovements(Carbon $since, int $limit = 15, ?Carbon $until = null, ?string $movementType = null, ?int $locationId = null, ?int $categoryId = null): Collection
    {
        return StockMovement::query()
            ->with(['item', 'fromLocation', 'toLocation', 'user', 'reference'])
            ->where('moved_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('moved_at', '<=', $until))
            ->when($movementType, fn ($query) => $query->where('movement_type', $movementType))
            ->when($categoryId, fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->when($locationId, fn ($query) => $query->where(fn ($locationQuery) => $locationQuery
                ->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId)))
            ->latest('moved_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Generate a targeted or unified inventory report based on dynamic filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function generateReport(array $filters, ?User $user = null): array
    {
        $reportType = (string) ($filters['report_type'] ?? 'all');
        $format = (string) ($filters['format'] ?? 'pdf');

        // Resolve Date Window
        if (($filters['period'] ?? null) === 'custom' && ! empty($filters['from']) && ! empty($filters['to'])) {
            $from = Carbon::parse($filters['from'])->startOfDay();
            $to = Carbon::parse($filters['to'])->endOfDay();
            $periodDescription = $from->format('M d, Y').' — '.$to->format('M d, Y').' (Custom Range)';
        } elseif (($filters['period'] ?? null) === 'all') {
            $from = Carbon::createFromTimestamp(0);
            $to = now();
            $periodDescription = 'All Time (up to '.now()->format('M d, Y').')';
        } elseif (($filters['period'] ?? null) === '1') {
            $from = now()->startOfDay();
            $to = now();
            $periodDescription = 'Today ('.now()->format('M d, Y').')';
        } else {
            $days = max(1, min(3650, (int) ($filters['period'] ?? $filters['days'] ?? self::DEFAULT_PERIOD_DAYS)));
            $from = now()->subDays($days)->startOfDay();
            $to = now();
            $periodDescription = 'Last '.$days.' days ('.$from->format('M d, Y').' — '.$to->format('M d, Y').')';
        }

        $canViewFinancial = $user ? $user->can(Permission::ViewProcurementSensitiveData->value) : true;

        // Parse Entity Filters
        $categoryId = ! empty($filters['category_id']) ? (int) $filters['category_id'] : null;
        $locationId = ! empty($filters['storage_location_id']) ? (int) $filters['storage_location_id'] : null;
        $supplierId = ! empty($filters['supplier_id']) ? (int) $filters['supplier_id'] : null;
        $movementType = ! empty($filters['movement_type']) ? (string) $filters['movement_type'] : null;
        $status = ! empty($filters['status']) && $filters['status'] !== 'all' ? (string) $filters['status'] : null;
        $sortBy = ! empty($filters['sort_by']) ? (string) $filters['sort_by'] : null;
        $sortDir = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        // Filter Labels for Metadata
        $filterLabels = [
            'Reporting Period' => $periodDescription,
        ];
        if ($categoryId) {
            $cat = ItemCategory::find($categoryId);
            $filterLabels['Item Category'] = $cat ? $cat->name : 'ID: '.$categoryId;
        } else {
            $filterLabels['Item Category'] = 'All Categories';
        }

        if ($locationId) {
            $loc = StorageLocation::find($locationId);
            $filterLabels['Storage Location'] = $loc ? $loc->name.' ('.$loc->code.')' : 'ID: '.$locationId;
        } else {
            $filterLabels['Storage Location'] = 'All Locations';
        }

        if ($supplierId) {
            $sup = Supplier::find($supplierId);
            $filterLabels['Supplier'] = $sup ? $sup->name : 'ID: '.$supplierId;
        } else {
            $filterLabels['Supplier'] = 'All Suppliers';
        }

        if ($movementType) {
            $filterLabels['Movement Type'] = ucwords(str_replace('_', ' ', $movementType));
        } else {
            $filterLabels['Movement Type'] = 'All Movement Types';
        }

        if ($status) {
            $filterLabels['Stock Status'] = ucwords(str_replace('_', ' ', $status));
        } else {
            $filterLabels['Stock Status'] = 'All Stock Statuses';
        }

        if ($sortBy) {
            $filterLabels['Sorted By'] = ucwords(str_replace('_', ' ', $sortBy)).' ('.strtoupper($sortDir).')';
        }

        $meta = [
            'report_type' => $reportType,
            'report_title' => self::REPORT_TYPES[$reportType] ?? 'Inventory Report',
            'generated_at' => now(),
            'generated_by' => $user ? $user->name.' ('.($user->role?->label() ?? 'Staff').')' : 'HIMS System',
            'hospital_name' => 'Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium',
            'sub_title' => 'Tala, Caloocan City • Materials Management & Supply Division',
            'period' => [
                'from' => $from,
                'to' => $to,
                'description' => $periodDescription,
            ],
            'filters' => $filters,
            'filter_labels' => $filterLabels,
            'is_financial_permitted' => $canViewFinancial,
            'format' => $format,
        ];

        return match ($reportType) {
            'stock_status' => $this->generateStockStatusReport($meta, $categoryId, $locationId, $status, $sortBy, $sortDir, $canViewFinancial),
            'valuation' => $this->generateValuationReport($meta, $categoryId, $locationId, $status, $sortBy, $sortDir, $canViewFinancial),
            'stock_by_location' => $this->generateStockByLocationReport($meta, $categoryId, $locationId, $status, $sortBy, $sortDir, $canViewFinancial),
            'expiry_exposure' => $this->generateExpiryExposureReport($meta, $locationId, $categoryId, $sortBy, $sortDir, $canViewFinancial),
            'movement_history' => $this->generateMovementHistoryReport($meta, $from, $to, $movementType, $locationId, $categoryId, $sortBy, $sortDir, $canViewFinancial),
            'procurement_expense' => $this->generateProcurementExpenseReport($meta, $from, $to, $supplierId, $status, $sortBy, $sortDir, $canViewFinancial),
            'spend_by_supplier' => $this->generateSpendBySupplierReport($meta, $from, $to, $supplierId, $sortBy, $sortDir, $canViewFinancial),
            'most_consumed' => $this->generateMostConsumedReport($meta, $from, $to, $categoryId, $locationId, $sortBy, $sortDir, $canViewFinancial),
            'movements_by_type' => $this->generateMovementsByTypeReport($meta, $from, $to, $movementType, $locationId, $categoryId, $sortBy, $sortDir, $canViewFinancial),
            default => $this->generateAllReports($meta, $from, $to, $categoryId, $locationId, $supplierId, $movementType, $status, $sortBy, $sortDir, $canViewFinancial),
        };
    }

    /**
     * Stock Status Report generation.
     */
    protected function generateStockStatusReport(array $meta, ?int $categoryId, ?int $locationId, ?string $statusFilter, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $items = $this->inventorySnapshot($categoryId, $locationId);

        $classified = $items->map(function (object $item) {
            $statusKey = $this->stockStatusKey((int) $item->quantity_on_hand, (int) $item->reorder_level);

            $units = (int) $item->quantity_on_hand;
            $unitCost = (float) $item->unit_cost;
            $val = $units * $unitCost;

            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category,
                'quantity_on_hand' => $units,
                'reserved_quantity' => (int) $item->reserved_quantity,
                'reorder_level' => (int) $item->reorder_level,
                'unit' => $item->unit ?? 'unit',
                'unit_cost' => $unitCost,
                'total_value' => $val,
                'status_key' => $statusKey,
                'status' => match ($statusKey) {
                    'out_of_stock' => 'Out of Stock',
                    'low_stock' => 'Low Stock',
                    default => 'In Stock',
                },
            ];
        });

        if ($statusFilter) {
            $classified = $classified->filter(fn ($r) => $r['status_key'] === $statusFilter)->values();
        }

        // Sorting
        $classified = (match ($sortBy) {
            'name' => $sortDir === 'asc' ? $classified->sortBy('name') : $classified->sortByDesc('name'),
            'units' => $sortDir === 'asc' ? $classified->sortBy('quantity_on_hand') : $classified->sortByDesc('quantity_on_hand'),
            'value' => $sortDir === 'asc' ? $classified->sortBy('total_value') : $classified->sortByDesc('total_value'),
            'status' => $sortDir === 'asc' ? $classified->sortBy('status') : $classified->sortByDesc('status'),
            default => $sortDir === 'asc' ? $classified->sortBy('name') : $classified->sortByDesc('quantity_on_hand'),
        })->values();

        $columns = [
            'sku' => 'SKU',
            'name' => 'Item Description',
            'category' => 'Category',
            'quantity_on_hand' => 'Units On Hand',
            'reserved_quantity' => 'Reserved Units',
            'reorder_level' => 'Reorder Level',
        ];
        if ($canViewFinancial) {
            $columns['unit_cost'] = 'Unit Cost (₱)';
            $columns['total_value'] = 'Total Value (₱)';
        }
        $columns['status'] = 'Stock Status';

        $totalUnits = (int) $classified->sum('quantity_on_hand');
        $totalVal = (float) $classified->sum('total_value');

        $summary = [
            'Total Items' => $classified->count(),
            'In Stock Items' => $classified->where('status_key', 'in_stock')->count(),
            'Low Stock Items' => $classified->where('status_key', 'low_stock')->count(),
            'Out of Stock Items' => $classified->where('status_key', 'out_of_stock')->count(),
            'Total Units' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Valuation'] = '₱'.number_format($totalVal, 2);
        }

        $totals = [
            'sku' => 'TOTALS',
            'name' => $classified->count().' items',
            'category' => '-',
            'quantity_on_hand' => $totalUnits,
            'reserved_quantity' => (int) $classified->sum('reserved_quantity'),
            'reorder_level' => '-',
            'unit_cost' => '-',
            'total_value' => $canViewFinancial ? '₱'.number_format($totalVal, 2) : '-',
            'status' => '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $classified->all(),
            'totals' => $totals,
            'is_empty' => $classified->isEmpty(),
            'empty_message' => 'No items found matching the selected stock status criteria.',
        ];
    }

    /**
     * Valuation Report generation.
     */
    protected function generateValuationReport(array $meta, ?int $categoryId, ?int $locationId, ?string $statusFilter, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $rows = $this->valuationByCategory($categoryId, $locationId, $statusFilter);

        $totalVal = (float) $rows->sum('value');
        $totalUnits = (int) $rows->sum('units');
        $totalItems = (int) $rows->sum('items');

        $data = $rows->map(function ($r) use ($totalVal) {
            $val = (float) $r->value;
            $share = $totalVal > 0 ? round(($val / $totalVal) * 100, 1) : 0.0;

            return [
                'category' => $r->category,
                'items' => (int) $r->items,
                'units' => (int) $r->units,
                'value' => $val,
                'share' => $share,
            ];
        });

        // Sorting
        $data = (match ($sortBy) {
            'name' => $sortDir === 'asc' ? $data->sortBy('category') : $data->sortByDesc('category'),
            'items' => $sortDir === 'asc' ? $data->sortBy('items') : $data->sortByDesc('items'),
            'units' => $sortDir === 'asc' ? $data->sortBy('units') : $data->sortByDesc('units'),
            default => $sortDir === 'asc' ? $data->sortBy('value') : $data->sortByDesc('value'),
        })->values();

        $columns = [
            'category' => 'Item Category',
            'items' => 'Items in Catalogue',
            'units' => 'Units On Hand',
        ];
        if ($canViewFinancial) {
            $columns['value'] = 'Valuation (₱)';
            $columns['share'] = 'Share of Valuation (%)';
        }

        $summary = [
            'Categories' => $data->count(),
            'Total Items' => $totalItems,
            'Total Units On Hand' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Inventory Value'] = '₱'.number_format($totalVal, 2);
        }

        $totals = [
            'category' => 'TOTALS',
            'items' => $totalItems,
            'units' => $totalUnits,
            'value' => $canViewFinancial ? '₱'.number_format($totalVal, 2) : '-',
            'share' => '100.0%',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $data->all(),
            'totals' => $totals,
            'is_empty' => $data->isEmpty(),
            'empty_message' => 'No inventory valuation data found for the selected category.',
        ];
    }

    /**
     * Stock By Location Report generation.
     */
    protected function generateStockByLocationReport(array $meta, ?int $categoryId, ?int $locationId, ?string $statusFilter, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $rows = $this->stockByLocation($categoryId, $locationId, $statusFilter)
            ->map(fn (array $row) => [
                ...$row,
                'capacity' => $row['capacity'] ?? 'Uncapped',
                'utilisation' => $row['utilisation'] !== null ? $row['utilisation'].'%' : 'N/A',
                'utilisation_num' => $row['utilisation'] ?? 0,
            ]);

        // Sorting
        $rows = (match ($sortBy) {
            'name' => $sortDir === 'asc' ? $rows->sortBy('location') : $rows->sortByDesc('location'),
            'items' => $sortDir === 'asc' ? $rows->sortBy('items') : $rows->sortByDesc('items'),
            'utilisation' => $sortDir === 'asc' ? $rows->sortBy('utilisation_num') : $rows->sortByDesc('utilisation_num'),
            'value' => $sortDir === 'asc' ? $rows->sortBy('value') : $rows->sortByDesc('value'),
            default => $sortDir === 'asc' ? $rows->sortBy('units') : $rows->sortByDesc('units'),
        })->values();

        $columns = [
            'location' => 'Storage Location',
            'code' => 'Location Code',
            'capacity' => 'Capacity (Units)',
            'items' => 'Unique Items',
            'units' => 'Stored Units',
        ];
        if ($canViewFinancial) {
            $columns['value'] = 'Stored Value (₱)';
        }
        $columns['utilisation'] = 'Space Utilisation';

        $totalUnits = (int) $rows->sum('units');
        $totalVal = (float) $rows->sum('value');

        $summary = [
            'Storage Locations' => $rows->count(),
            'Total Units Stored' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Stored Value'] = '₱'.number_format($totalVal, 2);
        }

        $totals = [
            'location' => 'TOTALS',
            'code' => '-',
            'capacity' => '-',
            'items' => (int) $rows->sum('items'),
            'units' => $totalUnits,
            'value' => $canViewFinancial ? '₱'.number_format($totalVal, 2) : '-',
            'utilisation' => '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $rows->all(),
            'totals' => $totals,
            'is_empty' => $rows->isEmpty(),
            'empty_message' => 'No stock records found for the selected storage location.',
        ];
    }

    /**
     * Expiry Exposure Report generation.
     */
    protected function generateExpiryExposureReport(array $meta, ?int $locationId, ?int $categoryId, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $stockLevelScope = fn ($query) => $query->when(
            $locationId,
            fn ($locationQuery) => $locationQuery->where('storage_location_id', $locationId)
        );

        $batchesQuery = ItemBatch::query()
            ->active()
            ->whereNotNull('expiry_date')
            ->with(['item.category', 'stockLevels' => $stockLevelScope, 'stockLevels.storageLocation'])
            ->withSum(['stockLevels as units_on_hand' => $stockLevelScope], 'quantity')
            ->fefo()
            ->when($categoryId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('category_id', $categoryId)))
            ->when($locationId, fn ($q) => $q->whereHas('stockLevels', $stockLevelScope));

        $batches = $batchesQuery->get()->filter(fn (ItemBatch $b) => (int) $b->units_on_hand > 0);

        $expired = $batches->filter(fn (ItemBatch $b) => $b->isExpired());
        $expiringSoon = $batches->filter(fn (ItemBatch $b) => $b->isExpiringSoon());
        $atRisk = $expired->merge($expiringSoon)->values();

        $rows = $atRisk->map(function (ItemBatch $batch) {
            $cost = (float) ($batch->unit_cost ?? $batch->item?->unit_cost ?? 0);
            $units = (int) $batch->units_on_hand;
            $riskVal = $units * $cost;
            $isExp = $batch->isExpired();

            $expiryDate = $batch->expiry_date ? Carbon::parse($batch->expiry_date) : null;
            $daysLeft = $expiryDate ? (int) now()->startOfDay()->diffInDays($expiryDate, false) : 0;

            $activeLevels = $batch->stockLevels->filter(fn ($sl) => (int) $sl->quantity > 0);
            if ($activeLevels->isEmpty()) {
                $activeLevels = $batch->stockLevels;
            }

            $locNames = $activeLevels
                ->map(fn ($sl) => ($sl->storageLocation ?? $sl->location)?->name)
                ->filter()
                ->unique()
                ->implode(', ') ?: 'Main Store';

            return [
                'batch_number' => $batch->batch_number,
                'item' => $batch->item?->name ?? 'Unknown Item',
                'sku' => $batch->item?->sku ?? '-',
                'category' => $batch->item?->category?->name ?? 'Uncategorised',
                'location' => $locNames,
                'expiry_date' => $expiryDate ? $expiryDate->format('M d, Y') : '-',
                'days_remaining' => $isExp ? 'EXPIRED ('.abs($daysLeft).'d ago)' : $daysLeft.' days left',
                'days_num' => $daysLeft,
                'units' => $units,
                'unit_cost' => $cost,
                'risk_value' => $riskVal,
                'status' => $isExp ? 'EXPIRED' : 'EXPIRING SOON',
            ];
        });

        // Sorting
        $rows = (match ($sortBy) {
            'name' => $sortDir === 'asc' ? $rows->sortBy('item') : $rows->sortByDesc('item'),
            'units' => $sortDir === 'asc' ? $rows->sortBy('units') : $rows->sortByDesc('units'),
            'value' => $sortDir === 'asc' ? $rows->sortBy('risk_value') : $rows->sortByDesc('risk_value'),
            default => $sortDir === 'asc' ? $rows->sortBy('days_num') : $rows->sortByDesc('days_num'),
        })->values();

        $columns = [
            'batch_number' => 'Batch Number',
            'item' => 'Item Description',
            'category' => 'Category',
            'location' => 'Storage Location',
            'expiry_date' => 'Expiry Date',
            'days_remaining' => 'Timeline',
            'units' => 'Units at Risk',
        ];
        if ($canViewFinancial) {
            $columns['unit_cost'] = 'Unit Cost (₱)';
            $columns['risk_value'] = 'Risk Value (₱)';
        }
        $columns['status'] = 'Status';

        $totalUnits = (int) $rows->sum('units');
        $totalRiskVal = (float) $rows->sum('risk_value');

        $summary = [
            'Expired Batches' => $expired->count(),
            'Expired Units' => (int) $expired->sum('units_on_hand'),
            'Expiring Soon Batches' => $expiringSoon->count(),
            'Expiring Soon Units' => (int) $expiringSoon->sum('units_on_hand'),
            'Total Units at Risk' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Risk Valuation'] = '₱'.number_format($totalRiskVal, 2);
        }

        $totals = [
            'batch_number' => 'TOTALS',
            'item' => $rows->count().' batches at risk',
            'category' => '-',
            'location' => '-',
            'expiry_date' => '-',
            'days_remaining' => '-',
            'units' => $totalUnits,
            'unit_cost' => '-',
            'risk_value' => $canViewFinancial ? '₱'.number_format($totalRiskVal, 2) : '-',
            'status' => '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $rows->all(),
            'totals' => $totals,
            'is_empty' => $rows->isEmpty(),
            'empty_message' => 'No active batches are expired or expiring soon for the selected criteria.',
        ];
    }

    /**
     * Movement History Report generation.
     */
    protected function generateMovementHistoryReport(array $meta, Carbon $from, Carbon $to, ?string $movementType, ?int $locationId, ?int $categoryId, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $query = StockMovement::query()
            ->with(['item.category', 'fromLocation', 'toLocation', 'user', 'reference'])
            ->where('moved_at', '>=', $from)
            ->where('moved_at', '<=', $to)
            ->when($movementType, fn ($q) => $q->where('movement_type', $movementType))
            ->when($categoryId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('category_id', $categoryId)))
            ->when($locationId, fn ($q) => $q->where(fn ($lq) => $lq->where('from_location_id', $locationId)->orWhere('to_location_id', $locationId)));

        $movements = $query->get();

        $rows = $movements->map(function (StockMovement $m) {
            $qty = (int) $m->quantity;
            $cost = (float) ($m->unit_cost ?? $m->item?->unit_cost ?? 0);
            $val = $qty * $cost;

            $referenceText = match (true) {
                $m->reference instanceof PurchaseOrder => 'PO #'.$m->reference->po_number,
                $m->reference_type !== null => class_basename($m->reference_type).' #'.$m->reference_id,
                default => '-',
            };

            $destination = $m->toLocation?->name ?? ($m->notes ?: $referenceText);

            return [
                'id' => '#'.$m->id,
                'moved_at' => $m->moved_at ? $m->moved_at->format('M d, Y H:i') : '-',
                'timestamp' => $m->moved_at?->timestamp ?? 0,
                'type' => $m->movement_type instanceof MovementType ? $m->movement_type->label() : ucwords(str_replace('_', ' ', (string) $m->movement_type)),
                'item' => $m->item?->name ?? 'Unknown',
                'sku' => $m->item?->sku ?? '-',
                'category' => $m->item?->category?->name ?? 'Uncategorised',
                'quantity' => $qty,
                'unit_cost' => $cost,
                'value' => $val,
                'from_location' => $m->fromLocation?->name ?? 'External / Supplier',
                'to_location' => $destination,
                'user' => $m->user?->name ?? 'System Automated',
            ];
        });

        // Sorting
        $rows = (match ($sortBy) {
            'item' => $sortDir === 'asc' ? $rows->sortBy('item') : $rows->sortByDesc('item'),
            'quantity' => $sortDir === 'asc' ? $rows->sortBy('quantity') : $rows->sortByDesc('quantity'),
            'value' => $sortDir === 'asc' ? $rows->sortBy('value') : $rows->sortByDesc('value'),
            default => $sortDir === 'asc' ? $rows->sortBy('timestamp') : $rows->sortByDesc('timestamp'),
        })->values();

        $columns = [
            'id' => 'Ref #',
            'moved_at' => 'Date & Time',
            'type' => 'Movement Type',
            'item' => 'Item Description',
            'sku' => 'SKU',
            'quantity' => 'Quantity',
        ];
        if ($canViewFinancial) {
            $columns['unit_cost'] = 'Unit Cost (₱)';
            $columns['value'] = 'Total Value (₱)';
        }
        $columns['from_location'] = 'Origin';
        $columns['to_location'] = 'Destination / Ref';
        $columns['user'] = 'Logged By';

        $totalUnits = (int) $rows->sum('quantity');
        $totalVal = (float) $rows->sum('value');

        $summary = [
            'Total Movements' => $rows->count(),
            'Total Units Moved' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Movements Value'] = '₱'.number_format($totalVal, 2);
        }

        $totals = [
            'id' => 'TOTALS',
            'moved_at' => '-',
            'type' => '-',
            'item' => $rows->count().' movement records',
            'sku' => '-',
            'quantity' => $totalUnits,
            'unit_cost' => '-',
            'value' => $canViewFinancial ? '₱'.number_format($totalVal, 2) : '-',
            'from_location' => '-',
            'to_location' => '-',
            'user' => '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $rows->all(),
            'totals' => $totals,
            'is_empty' => $rows->isEmpty(),
            'empty_message' => 'No stock movement records found for the applied criteria within this date window.',
        ];
    }

    /**
     * Procurement Expense Report generation.
     */
    protected function generateProcurementExpenseReport(array $meta, Carbon $from, Carbon $to, ?int $supplierId, ?string $statusFilter, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        if (! $canViewFinancial) {
            abort(403, 'You do not have permission to view procurement financial reports.');
        }

        $query = PurchaseOrder::query()
            ->with(['supplier', 'item'])
            ->where('requested_at', '>=', $from)
            ->where('requested_at', '<=', $to)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($statusFilter && $statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter));

        $orders = $query->get();

        $rows = $orders->map(function (PurchaseOrder $po) {
            $qty = (int) $po->quantity;
            $cost = (float) $po->unit_cost;
            $amount = (float) $po->total_amount;

            return [
                'po_number' => $po->po_number,
                'requested_at' => $po->requested_at ? $po->requested_at->format('M d, Y') : '-',
                'timestamp' => $po->requested_at?->timestamp ?? 0,
                'supplier' => $po->supplier?->name ?? 'Unassigned',
                'item' => $po->item?->name ?? 'Multiple Items',
                'quantity' => $qty,
                'unit_cost' => $cost,
                'total_amount' => $amount,
                'status' => ucwords(str_replace('_', ' ', (string) $po->status)),
                'received_at' => $po->received_at ? $po->received_at->format('M d, Y') : 'Pending',
            ];
        });

        // Sorting
        $rows = (match ($sortBy) {
            'supplier' => $sortDir === 'asc' ? $rows->sortBy('supplier') : $rows->sortByDesc('supplier'),
            'amount' => $sortDir === 'asc' ? $rows->sortBy('total_amount') : $rows->sortByDesc('total_amount'),
            'status' => $sortDir === 'asc' ? $rows->sortBy('status') : $rows->sortByDesc('status'),
            default => $sortDir === 'asc' ? $rows->sortBy('timestamp') : $rows->sortByDesc('timestamp'),
        })->values();

        $received = $orders->where('status', 'received');
        $outstanding = PurchaseOrder::query()
            ->whereNotIn('status', ['received', 'cancelled'])
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->get();

        $totalOrdersCount = $orders->count();
        $totalOrderedVal = (float) $orders->sum('total_amount');
        $receivedVal = (float) $received->sum('total_amount');
        $outstandingVal = (float) $outstanding->sum('total_amount');
        $avgOrderVal = $totalOrdersCount > 0 ? round($totalOrderedVal / $totalOrdersCount, 2) : 0.0;

        $columns = [
            'po_number' => 'PO Number',
            'requested_at' => 'Order Date',
            'supplier' => 'Supplier',
            'item' => 'Item Description',
            'quantity' => 'Quantity',
            'unit_cost' => 'Unit Cost (₱)',
            'total_amount' => 'Total Amount (₱)',
            'status' => 'Status',
            'received_at' => 'Date Received',
        ];

        $summary = [
            'Purchase Orders Placed' => $totalOrdersCount,
            'Total Ordered Amount' => '₱'.number_format($totalOrderedVal, 2),
            'Total Landed / Received' => '₱'.number_format($receivedVal, 2),
            'Outstanding Commitments' => '₱'.number_format($outstandingVal, 2).' ('.$outstanding->count().' POs)',
            'Average Order Value' => '₱'.number_format($avgOrderVal, 2),
        ];

        $totals = [
            'po_number' => 'TOTALS',
            'requested_at' => '-',
            'supplier' => '-',
            'item' => $totalOrdersCount.' orders',
            'quantity' => (int) $rows->sum('quantity'),
            'unit_cost' => '-',
            'total_amount' => '₱'.number_format($totalOrderedVal, 2),
            'status' => '-',
            'received_at' => '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $rows->all(),
            'totals' => $totals,
            'is_empty' => $rows->isEmpty(),
            'empty_message' => 'No purchase orders found matching the filter criteria within this window.',
        ];
    }

    /**
     * Spend By Supplier Report generation.
     */
    protected function generateSpendBySupplierReport(array $meta, Carbon $from, Carbon $to, ?int $supplierId, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        if (! $canViewFinancial) {
            abort(403, 'You do not have permission to view vendor spend reports.');
        }

        $query = PurchaseOrder::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.requested_at', '>=', $from)
            ->where('purchase_orders.requested_at', '<=', $to)
            ->when($supplierId, fn ($q) => $q->where('purchase_orders.supplier_id', $supplierId))
            ->selectRaw("coalesce(suppliers.name, 'Unassigned') as supplier")
            ->selectRaw('count(*) as orders')
            ->selectRaw("coalesce(sum(case when purchase_orders.status = 'received' then 1 else 0 end), 0) as received_orders")
            ->selectRaw('coalesce(sum(purchase_orders.total_amount), 0) as value')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->toBase();

        $rows = $query->get()->map(function ($r) {
            $orders = (int) $r->orders;
            $received = (int) $r->received_orders;
            $rate = $orders > 0 ? round(($received / $orders) * 100, 1) : 0.0;
            $val = (float) $r->value;

            return [
                'supplier' => $r->supplier,
                'orders' => $orders,
                'received_orders' => $received,
                'fulfilment_rate' => $rate.'%',
                'fulfilment_num' => $rate,
                'value' => $val,
            ];
        });

        // Sorting
        $rows = (match ($sortBy) {
            'supplier' => $sortDir === 'asc' ? $rows->sortBy('supplier') : $rows->sortByDesc('supplier'),
            'orders' => $sortDir === 'asc' ? $rows->sortBy('orders') : $rows->sortByDesc('orders'),
            'fulfilment' => $sortDir === 'asc' ? $rows->sortBy('fulfilment_num') : $rows->sortByDesc('fulfilment_num'),
            default => $sortDir === 'asc' ? $rows->sortBy('value') : $rows->sortByDesc('value'),
        })->values();

        $totalSpend = (float) $rows->sum('value');
        $totalOrders = (int) $rows->sum('orders');
        $totalReceived = (int) $rows->sum('received_orders');
        $overallRate = $totalOrders > 0 ? round(($totalReceived / $totalOrders) * 100, 1) : 0.0;

        $columns = [
            'supplier' => 'Supplier / Vendor',
            'orders' => 'Orders Placed',
            'received_orders' => 'Fulfilled Orders',
            'fulfilment_rate' => 'Fulfilment Rate (%)',
            'value' => 'Total Procurement Spend (₱)',
        ];

        $summary = [
            'Active Vendors' => $rows->count(),
            'Total POs Placed' => $totalOrders,
            'Overall Fulfilment Rate' => $overallRate.'%',
            'Total Spend' => '₱'.number_format($totalSpend, 2),
        ];

        $totals = [
            'supplier' => 'TOTALS',
            'orders' => $totalOrders,
            'received_orders' => $totalReceived,
            'fulfilment_rate' => $overallRate.'%',
            'value' => '₱'.number_format($totalSpend, 2),
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $rows->all(),
            'totals' => $totals,
            'is_empty' => $rows->isEmpty(),
            'empty_message' => 'No vendor spending records found matching the applied criteria.',
        ];
    }

    /**
     * Most Consumed Items Report generation.
     */
    protected function generateMostConsumedReport(array $meta, Carbon $from, Carbon $to, ?int $categoryId, ?int $locationId, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $query = StockMovement::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_movements.item_id')
            ->whereIn('stock_movements.movement_type', MovementType::consumptionValues())
            ->where('stock_movements.moved_at', '>=', $from)
            ->where('stock_movements.moved_at', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('inventory_items.category_id', $categoryId))
            ->when($locationId, fn ($query) => $query->where(fn ($locationQuery) => $locationQuery
                ->where('stock_movements.from_location_id', $locationId)
                ->orWhere('stock_movements.to_location_id', $locationId)))
            ->selectRaw('inventory_items.id as item_id')
            ->selectRaw('inventory_items.name as item')
            ->selectRaw('inventory_items.sku as sku')
            ->selectRaw('inventory_items.unit as unit')
            ->selectRaw('inventory_items.quantity_on_hand as on_hand')
            ->selectRaw('count(*) as movements')
            ->selectRaw('coalesce(sum(stock_movements.quantity), 0) as units')
            ->selectRaw('coalesce(sum(stock_movements.quantity * coalesce(stock_movements.unit_cost, inventory_items.unit_cost, 0)), 0) as value')
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.sku', 'inventory_items.unit', 'inventory_items.quantity_on_hand')
            ->toBase();

        $rawRows = $query->get();

        if ($locationId && $rawRows->isNotEmpty()) {
            $onHandByItem = ItemStockLevel::query()
                ->where('storage_location_id', $locationId)
                ->whereIn('item_id', $rawRows->pluck('item_id'))
                ->selectRaw('item_id, coalesce(sum(quantity), 0) as on_hand')
                ->groupBy('item_id')
                ->pluck('on_hand', 'item_id');

            $rawRows->each(fn (object $row) => $row->on_hand = (int) ($onHandByItem[$row->item_id] ?? 0));
        }

        $rows = $rawRows->map(fn ($r) => [
            'item' => $r->item,
            'sku' => $r->sku,
            'unit' => $r->unit ?? 'unit',
            'on_hand' => (int) $r->on_hand,
            'movements' => (int) $r->movements,
            'units' => (int) $r->units,
            'value' => (float) $r->value,
        ]);

        // Sorting
        $rows = (match ($sortBy) {
            'item' => $sortDir === 'asc' ? $rows->sortBy('item') : $rows->sortByDesc('item'),
            'movements' => $sortDir === 'asc' ? $rows->sortBy('movements') : $rows->sortByDesc('movements'),
            'value' => $sortDir === 'asc' ? $rows->sortBy('value') : $rows->sortByDesc('value'),
            default => $sortDir === 'asc' ? $rows->sortBy('units') : $rows->sortByDesc('units'),
        })->values();

        $totalUnits = (int) $rows->sum('units');
        $totalVal = (float) $rows->sum('value');

        $columns = [
            'item' => 'Item Description',
            'sku' => 'SKU',
            'unit' => 'Unit',
            'on_hand' => 'Remaining On Hand',
            'movements' => 'Consumption Events',
            'units' => 'Units Consumed',
        ];
        if ($canViewFinancial) {
            $columns['value'] = 'Consumption Value (₱)';
        }

        $summary = [
            'Consumed Item Types' => $rows->count(),
            'Total Units Consumed' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Consumption Value'] = '₱'.number_format($totalVal, 2);
        }

        $totals = [
            'item' => 'TOTALS',
            'sku' => '-',
            'unit' => '-',
            'on_hand' => (int) $rows->sum('on_hand'),
            'movements' => (int) $rows->sum('movements'),
            'units' => $totalUnits,
            'value' => $canViewFinancial ? '₱'.number_format($totalVal, 2) : '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $rows->all(),
            'totals' => $totals,
            'is_empty' => $rows->isEmpty(),
            'empty_message' => 'No item consumption recorded within this date window.',
        ];
    }

    /**
     * Movements By Type Report generation.
     */
    protected function generateMovementsByTypeReport(array $meta, Carbon $from, Carbon $to, ?string $movementType, ?int $locationId, ?int $categoryId, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $rows = StockMovement::query()
            ->where('moved_at', '>=', $from)
            ->where('moved_at', '<=', $to)
            ->when($movementType, fn ($q) => $q->where('movement_type', $movementType))
            ->when($categoryId, fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->when($locationId, fn ($query) => $query->where(fn ($locationQuery) => $locationQuery
                ->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId)))
            ->selectRaw('movement_type')
            ->selectRaw('count(*) as movements')
            ->selectRaw('coalesce(sum(quantity), 0) as units')
            ->selectRaw('coalesce(sum(quantity * coalesce(unit_cost, 0)), 0) as value')
            ->groupBy('movement_type')
            ->toBase()
            ->get()
            ->keyBy('movement_type');

        $cases = $movementType ? [MovementType::tryFrom($movementType)] : MovementType::cases();
        $cases = array_filter($cases);

        $data = collect($cases)->map(function (MovementType $type) use ($rows) {
            $key = $type->value;
            $mov = (int) ($rows[$key]->movements ?? 0);
            $units = (int) ($rows[$key]->units ?? 0);
            $val = (float) ($rows[$key]->value ?? 0);

            return [
                'type' => $type->label(),
                'type_key' => $type->value,
                'movements' => $mov,
                'units' => $units,
                'value' => $val,
            ];
        });

        // Sorting
        $data = (match ($sortBy) {
            'type' => $sortDir === 'asc' ? $data->sortBy('type') : $data->sortByDesc('type'),
            'units' => $sortDir === 'asc' ? $data->sortBy('units') : $data->sortByDesc('units'),
            'value' => $sortDir === 'asc' ? $data->sortBy('value') : $data->sortByDesc('value'),
            default => $sortDir === 'asc' ? $data->sortBy('movements') : $data->sortByDesc('movements'),
        })->values();

        $totalMov = (int) $data->sum('movements');
        $totalUnits = (int) $data->sum('units');
        $totalVal = (float) $data->sum('value');

        $columns = [
            'type' => 'Movement Type',
            'movements' => 'Recorded Movements',
            'units' => 'Total Units',
        ];
        if ($canViewFinancial) {
            $columns['value'] = 'Movement Value (₱)';
        }

        $summary = [
            'Activity Types' => $data->count(),
            'Total Movements' => $totalMov,
            'Total Units' => $totalUnits,
        ];
        if ($canViewFinancial) {
            $summary['Total Activity Value'] = '₱'.number_format($totalVal, 2);
        }

        $totals = [
            'type' => 'TOTALS',
            'movements' => $totalMov,
            'units' => $totalUnits,
            'value' => $canViewFinancial ? '₱'.number_format($totalVal, 2) : '-',
        ];

        return [
            'meta' => $meta,
            'summary' => $summary,
            'columns' => $columns,
            'data' => $data->all(),
            'totals' => $totals,
            'is_empty' => $totalMov === 0,
            'empty_message' => 'No stock movement activity recorded within this date window.',
        ];
    }

    /**
     * All Reports compilation.
     */
    protected function generateAllReports(array $meta, Carbon $from, Carbon $to, ?int $categoryId, ?int $locationId, ?int $supplierId, ?string $movementType, ?string $status, ?string $sortBy, string $sortDir, bool $canViewFinancial): array
    {
        $stockStatus = $this->generateStockStatusReport($meta, $categoryId, $locationId, $status, $sortBy, $sortDir, $canViewFinancial);
        $valuation = $this->generateValuationReport($meta, $categoryId, $locationId, $status, $sortBy, $sortDir, $canViewFinancial);
        $stockByLocation = $this->generateStockByLocationReport($meta, $categoryId, $locationId, $status, $sortBy, $sortDir, $canViewFinancial);
        $expiry = $this->generateExpiryExposureReport($meta, $locationId, $categoryId, $sortBy, $sortDir, $canViewFinancial);
        $movements = $this->generateMovementHistoryReport($meta, $from, $to, $movementType, $locationId, $categoryId, $sortBy, $sortDir, $canViewFinancial);
        $consumed = $this->generateMostConsumedReport($meta, $from, $to, $categoryId, $locationId, $sortBy, $sortDir, $canViewFinancial);
        $movementsByType = $this->generateMovementsByTypeReport($meta, $from, $to, $movementType, $locationId, $categoryId, $sortBy, $sortDir, $canViewFinancial);

        $sections = [
            'stock_status' => [
                'title' => 'Stock Status & Level Breakdown',
                'summary' => $stockStatus['summary'],
                'columns' => $stockStatus['columns'],
                'rows' => $stockStatus['data'],
                'totals' => $stockStatus['totals'],
            ],
            'valuation' => [
                'title' => 'Inventory Valuation by Category',
                'summary' => $valuation['summary'],
                'columns' => $valuation['columns'],
                'rows' => $valuation['data'],
                'totals' => $valuation['totals'],
            ],
            'stock_by_location' => [
                'title' => 'Stock by Storage Location',
                'summary' => $stockByLocation['summary'],
                'columns' => $stockByLocation['columns'],
                'rows' => $stockByLocation['data'],
                'totals' => $stockByLocation['totals'],
            ],
            'expiry' => [
                'title' => 'Expiry Exposure & Risk Batches',
                'summary' => $expiry['summary'],
                'columns' => $expiry['columns'],
                'rows' => $expiry['data'],
                'totals' => $expiry['totals'],
            ],
            'movements' => [
                'title' => 'Recent Stock Movement Ledger',
                'summary' => $movements['summary'],
                'columns' => $movements['columns'],
                'rows' => array_slice($movements['data'], 0, 50),
                'totals' => $movements['totals'],
            ],
            'consumed' => [
                'title' => 'Top Consumed Items',
                'summary' => $consumed['summary'],
                'columns' => $consumed['columns'],
                'rows' => $consumed['data'],
                'totals' => $consumed['totals'],
            ],
            'movements_by_type' => [
                'title' => 'Activity by Movement Type',
                'summary' => $movementsByType['summary'],
                'columns' => $movementsByType['columns'],
                'rows' => $movementsByType['data'],
                'totals' => $movementsByType['totals'],
            ],
        ];

        if ($canViewFinancial) {
            $procurement = $this->generateProcurementExpenseReport($meta, $from, $to, $supplierId, $status, $sortBy, $sortDir, $canViewFinancial);
            $spend = $this->generateSpendBySupplierReport($meta, $from, $to, $supplierId, $sortBy, $sortDir, $canViewFinancial);

            $sections['procurement'] = [
                'title' => 'Procurement Expense & Purchase Commitments',
                'summary' => $procurement['summary'],
                'columns' => $procurement['columns'],
                'rows' => $procurement['data'],
                'totals' => $procurement['totals'],
            ];

            $sections['spend'] = [
                'title' => 'Spend by Vendor / Supplier',
                'summary' => $spend['summary'],
                'columns' => $spend['columns'],
                'rows' => $spend['data'],
                'totals' => $spend['totals'],
            ];
        }

        $overallSummary = [
            'Catalogue Items' => $stockStatus['summary']['Total Items'] ?? 0,
            'Units on Hand' => $stockStatus['summary']['Total Units'] ?? 0,
            'Expired Batches' => $expiry['summary']['Expired Batches'] ?? 0,
            'Total Movements' => $movements['summary']['Total Movements'] ?? 0,
        ];
        if ($canViewFinancial) {
            $overallSummary['Inventory Valuation'] = $valuation['summary']['Total Inventory Value'] ?? '₱0.00';
            $overallSummary['Procurement Spend'] = $spend['summary']['Total Spend'] ?? '₱0.00';
        }

        return [
            'meta' => $meta,
            'summary' => $overallSummary,
            'sections' => $sections,
            'data' => [],
            'columns' => [],
            'totals' => [],
            'is_empty' => false,
            'empty_message' => '',
        ];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function exportCsv(array $report): StreamedResponse
    {
        $filename = 'hims-'.($report['meta']['report_type'] ?? 'report').'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for MS Excel compatibility

            fputcsv($out, [$report['meta']['hospital_name'] ?? 'Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium']);
            fputcsv($out, [$report['meta']['sub_title'] ?? 'Materials Management & Inventory Division']);
            fputcsv($out, ['Report:', $report['meta']['report_title'] ?? 'Inventory Report']);
            fputcsv($out, ['Generated:', optional($report['meta']['generated_at'])->format('Y-m-d H:i:s'), 'By:', $report['meta']['generated_by'] ?? '']);
            fputcsv($out, ['Period:', $report['meta']['period']['description'] ?? '']);
            foreach ($report['meta']['filter_labels'] ?? [] as $label => $val) {
                fputcsv($out, ['Filter: '.$label, $val]);
            }
            fputcsv($out, []); // blank line

            if (($report['meta']['report_type'] ?? '') === 'all') {
                foreach ($report['sections'] ?? [] as $section) {
                    fputcsv($out, ['=== '.strtoupper($section['title']).' ===']);
                    if (! empty($section['summary'])) {
                        foreach ($section['summary'] as $k => $v) {
                            fputcsv($out, [$k, $v]);
                        }
                        fputcsv($out, []);
                    }
                    if (! empty($section['columns'])) {
                        fputcsv($out, array_values($section['columns']));
                        foreach ($section['rows'] ?? [] as $row) {
                            fputcsv($out, array_map(fn ($k) => $row[$k] ?? '', array_keys($section['columns'])));
                        }
                        if (! empty($section['totals'])) {
                            fputcsv($out, array_map(fn ($k) => $section['totals'][$k] ?? '', array_keys($section['columns'])));
                        }
                    }
                    fputcsv($out, []);
                }
            } else {
                if (! empty($report['summary'])) {
                    fputcsv($out, ['--- SUMMARY ---']);
                    foreach ($report['summary'] as $k => $v) {
                        fputcsv($out, [$k, $v]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($report['columns'])) {
                    fputcsv($out, array_values($report['columns']));
                    if (empty($report['data'])) {
                        fputcsv($out, ['No records found matching the applied filter criteria.']);
                    } else {
                        foreach ($report['data'] as $row) {
                            fputcsv($out, array_map(fn ($k) => $row[$k] ?? '', array_keys($report['columns'])));
                        }
                    }
                    if (! empty($report['totals'])) {
                        fputcsv($out, array_map(fn ($k) => $report['totals'][$k] ?? '', array_keys($report['columns'])));
                    }
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export JSON format.
     */
    public function exportJson(array $report): JsonResponse
    {
        $filename = 'hims-'.($report['meta']['report_type'] ?? 'report').'-'.now()->format('Ymd_His').'.json';

        return response()->json($report, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
