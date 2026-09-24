<?php

namespace App\Services\Ai;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\ChainOfCustodyLog;
use App\Models\GoodsReceiptNote;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AiDemandForecastService;
use App\Services\DemandForecastService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Secure, role-aware database query and calculation tool registry for HIMS AI.
 *
 * Provides typed methods used for both:
 * 1. Gemini Function Calling declarations and execution
 * 2. Deterministic grounded offline/fallback generation
 */
class HimsAiToolRegistry
{
    public function __construct(
        private readonly DemandForecastService $forecastService,
        private readonly AiDemandForecastService $aiForecasts,
    ) {}

    /**
     * Search inventory items by keyword, category, or stock condition.
     *
     * @return array<string, mixed>
     */
    public function searchInventory(User $actor, string $query = '', ?string $category = null, ?string $stockStatus = null, int $limit = 8): array
    {
        $q = trim($query);
        $builder = InventoryItem::query()
            ->with(['category', 'supplier:id,name,contact_person'])
            ->where('status', 'active');

        if ($q !== '') {
            $builder->where(function (Builder $b) use ($q) {
                $b->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('sku', 'LIKE', "%{$q}%")
                    ->orWhere('barcode_value', 'LIKE', "%{$q}%")
                    ->orWhere('generic_name', 'LIKE', "%{$q}%")
                    ->orWhere('brand_name', 'LIKE', "%{$q}%");
            });
        }

        if ($category !== null && trim($category) !== '') {
            $catName = trim($category);
            $builder->whereHas('category', function (Builder $b) use ($catName) {
                $b->where('name', 'LIKE', "%{$catName}%");
            });
        }

        if ($stockStatus === 'low_stock') {
            $builder->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                ->where('quantity_on_hand', '>', 0);
        } elseif ($stockStatus === 'out_of_stock') {
            $builder->where('quantity_on_hand', '<=', 0);
        } elseif ($stockStatus === 'in_stock') {
            $builder->whereColumn('quantity_on_hand', '>', 'reorder_level');
        }

        $items = $builder->orderBy('quantity_on_hand', 'asc')->take($limit)->get();

        $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);

        return [
            'count' => $items->count(),
            'stock_filter' => $stockStatus,
            'items' => $items->map(function (InventoryItem $item) use ($canViewFinances) {
                $onHand = (int) $item->quantity_on_hand;
                $reserved = (int) ($item->reserved_quantity ?? 0);
                $available = max(0, $onHand - $reserved);
                $reorder = (int) $item->reorder_level;
                $safety = (int) ($item->safety_stock ?? 0);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category?->name ?? 'General',
                    'unit' => $item->unit ?? 'units',
                    'quantity_on_hand' => $onHand,
                    'reserved_quantity' => $reserved,
                    'available_stock' => $available,
                    'reorder_level' => $reorder,
                    'safety_stock' => $safety,
                    'lead_time_days' => (int) ($item->lead_time_days ?: DemandForecastService::DEFAULT_LEAD_TIME_DAYS),
                    'status_label' => $onHand <= 0 ? 'Out of Stock' : ($onHand <= $reorder ? 'Low Stock' : 'In Stock'),
                    'primary_supplier' => $item->supplier?->name ?? 'None Assigned',
                    'unit_cost' => $canViewFinances && $item->unit_cost !== null ? (float) $item->unit_cost : null,
                ];
            })->all(),
        ];
    }

    /**
     * Determine which items need replenishing, and how much to order.
     *
     * This is the answer to "may item ba na need na talagang orderin?", so it is
     * assembled from the rules the rest of HIMS already uses rather than a
     * formula of its own. The item set is InventoryItem::needsAttention() — the
     * same predicate behind the low-stock badges on the inventory and forecast
     * screens — so the assistant cannot contradict what the user can see. The
     * quantities come from the existing reorder calculation: the AI demand
     * forecast when its pass has covered the item (it already nets off stock
     * that is on order, so we do not recommend ordering the same units twice),
     * and the statistical DemandForecastService for the rest.
     *
     * @return array<string, mixed>
     */
    public function getReplenishmentRecommendations(User $actor, int $limit = 8): array
    {
        $limit = max(1, min(15, $limit));

        $items = InventoryItem::query()
            ->active()
            ->needsAttention()
            ->with(['category:id,name', 'supplier:id,name'])
            ->orderBy('quantity_on_hand')
            ->orderByDesc('reorder_level')
            ->take($limit)
            ->get();

        /** @var Collection<int|string, array<string, mixed>> $aiForecast */
        $aiForecast = collect($this->aiForecasts->cached(90, 30)['items'] ?? [])->keyBy('item_id');
        $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);

        $recommendations = $items->map(function (InventoryItem $item) use ($aiForecast, $canViewFinances): array {
            $onHand = (int) $item->quantity_on_hand;
            $reserved = (int) ($item->reserved_quantity ?? 0);
            $available = max(0, $onHand - $reserved);
            $reorder = (int) $item->reorder_level;
            $leadTime = (int) ($item->lead_time_days ?: DemandForecastService::DEFAULT_LEAD_TIME_DAYS);

            $status = InventoryItem::stockStatusFor($onHand, $reorder);
            $ai = $aiForecast->get($item->id);
            $statistical = $ai === null ? $this->forecastService->forecast($item, 90, 30, $leadTime) : null;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category?->name ?? 'General',
                'unit' => $item->unit ?? 'units',
                'quantity_on_hand' => $onHand,
                'reserved_quantity' => $reserved,
                'available_stock' => $available,
                'reorder_level' => $reorder,
                'safety_stock' => (int) ($item->safety_stock ?? 0),
                'stock_status' => $status,
                'status_label' => match ($status) {
                    'out_of_stock' => 'Out of Stock',
                    'low_stock' => 'Low Stock',
                    default => 'In Stock',
                },
                'units_below_reorder_level' => max(0, $reorder - $onHand),
                'recommended_order_quantity' => (int) ($ai['recommended_reorder_quantity']
                    ?? $statistical['suggested_order_quantity']
                    ?? 0),
                'incoming_stock_quantity' => $ai === null ? null : (int) ($ai['pending_procurement_quantity'] ?? 0),
                'projected_demand_30_days' => (int) ($ai['predicted_demand'] ?? $statistical['upcoming_need'] ?? 0),
                'reorder_priority' => $ai['reorder_priority'] ?? ($status === 'out_of_stock' ? 'critical' : 'high'),
                'demand_risk' => $ai['risk_level'] ?? 'unknown',
                'quantity_basis' => $ai === null ? 'statistical_forecast' : 'ai_demand_forecast',
                'primary_supplier' => $item->supplier?->name ?? 'None Assigned',
                'lead_time_days' => $leadTime,
                'unit_cost' => $canViewFinances && $item->unit_cost !== null ? (float) $item->unit_cost : null,
            ];
        })->all();

        return [
            'count' => count($recommendations),
            'out_of_stock_count' => collect($recommendations)->where('stock_status', 'out_of_stock')->count(),
            'low_stock_count' => collect($recommendations)->where('stock_status', 'low_stock')->count(),
            'requires_replenishment' => $recommendations !== [],
            'items' => $recommendations,
        ];
    }

    /**
     * Retrieve a detailed dossier for a specific inventory item.
     *
     * @return array<string, mixed>|null
     */
    public function getStockDetails(User $actor, int|string $identifier): ?array
    {
        $item = $this->findItem($identifier);
        if (! $item) {
            return null;
        }

        $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);
        $onHand = (int) $item->quantity_on_hand;
        $reserved = (int) ($item->reserved_quantity ?? 0);
        $available = max(0, $onHand - $reserved);
        $reorder = (int) $item->reorder_level;
        $safety = (int) ($item->safety_stock ?? 0);
        $leadTime = (int) ($item->lead_time_days ?: DemandForecastService::DEFAULT_LEAD_TIME_DAYS);

        // Batches with FEFO sorting
        $batches = $item->batches()
            ->withSum('stockLevels as remaining_stock', 'quantity')
            ->orderBy('expiry_date', 'asc')
            ->take(6)
            ->get()
            ->map(function (ItemBatch $b) {
                $days = $b->daysUntilExpiry();
                $qty = (int) ($b->remaining_stock ?? ($b->stockLevels->sum('quantity') ?: ($b->initial_quantity ?? 0)));

                return [
                    'batch_number' => $b->batch_number,
                    'lot_number' => $b->lot_number ?? 'N/A',
                    'quantity' => $qty,
                    'expiry_date' => $b->expiry_date?->toDateString(),
                    'days_remaining' => $days,
                    'expiry_status' => $b->expiryClassification(),
                    'expiry_status_label' => $b->expiryStatusLabel(),
                    'is_expired' => $b->isExpired(),
                    'is_nearing_expiry' => $b->isExpiringSoon(),
                ];
            })->all();

        // Location distribution
        $locations = $item->stockLevels()
            ->with('storageLocation')
            ->get()
            ->map(function ($lvl) {
                return [
                    'location_name' => $lvl->storageLocation?->name ?? 'Unknown Location',
                    'location_code' => $lvl->storageLocation?->code ?? 'N/A',
                    'quantity' => (int) $lvl->quantity,
                    'reserved' => (int) ($lvl->reserved_quantity ?? 0),
                ];
            })->all();

        // Recent movements
        $recentMovements = $item->movements()
            ->with('user:id,name')
            ->latest('moved_at')
            ->take(5)
            ->get()
            ->map(function (StockMovement $m) {
                $type = $m->movement_type instanceof MovementType ? $m->movement_type->label() : (string) $m->movement_type;

                return [
                    'type' => $type,
                    'quantity' => (int) $m->quantity,
                    'actor' => $m->user?->name ?? 'System',
                    'date' => $m->moved_at?->toDateString() ?? 'Recently',
                    'notes' => $m->remarks,
                ];
            })->all();

        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode_value,
            'generic_name' => $item->generic_name,
            'category' => $item->category?->name ?? 'General',
            'unit' => $item->unit ?? 'units',
            'quantity_on_hand' => $onHand,
            'reserved_quantity' => $reserved,
            'available_stock' => $available,
            'reorder_level' => $reorder,
            'safety_stock' => $safety,
            'lead_time_days' => $leadTime,
            'supplier_name' => $item->supplier?->name ?? 'None Assigned',
            'supplier_id' => $item->supplier_id,
            'supplier_contact' => $item->supplier?->contact_person,
            'supplier_phone' => $item->supplier?->phone,
            'unit_cost' => $canViewFinances && $item->unit_cost !== null ? (float) $item->unit_cost : null,
            'total_value' => $canViewFinances && $item->total_value !== null ? (float) $item->total_value : null,
            'status' => $item->status,
            'status_label' => $onHand <= 0 ? 'Out of Stock' : ($onHand <= $reorder ? 'Low Stock' : 'Adequate'),
            'batches' => $batches,
            'locations' => $locations,
            'recent_movements' => $recentMovements,
        ];
    }

    /**
     * Search and retrieve supplier records.
     *
     * @return array<string, mixed>
     */
    public function searchSuppliers(User $actor, string $query = '', ?string $status = null, int $limit = 6): array
    {
        $q = trim($query);
        $builder = Supplier::query();

        if ($q !== '') {
            $builder->where(function (Builder $b) use ($q) {
                $b->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('contact_person', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('phone', 'LIKE', "%{$q}%");
            });
        }

        if ($status !== null && trim($status) !== '') {
            $builder->where('status', $status);
        }

        $suppliers = $builder->orderBy('name')->take($limit)->get();

        return [
            'count' => $suppliers->count(),
            'suppliers' => $suppliers->map(function (Supplier $s) {
                $itemCount = InventoryItem::where('supplier_id', $s->id)->count();

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'contact_person' => $s->contact_person ?? 'Not Specified',
                    'email' => $s->email ?? 'N/A',
                    'phone' => $s->phone ?? 'N/A',
                    'status' => is_object($s->status) ? $s->status->value : (string) $s->status,
                    'lead_time_days' => (int) ($s->standard_lead_time_days ?: 7),
                    'supplied_items_count' => $itemCount,
                ];
            })->all(),
        ];
    }

    /**
     * Retrieve all inventory items provided by a specific supplier.
     *
     * @return array<string, mixed>|null
     */
    public function getSupplierItems(User $actor, int|string $supplierIdentifier): ?array
    {
        $supplier = is_numeric($supplierIdentifier)
            ? Supplier::find((int) $supplierIdentifier)
            : Supplier::where('name', 'LIKE', "%{$supplierIdentifier}%")->first();

        if (! $supplier) {
            return null;
        }

        $items = InventoryItem::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('name')
            ->take(15)
            ->get();

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'items_count' => $items->count(),
            'items' => $items->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'sku' => $i->sku,
                'current_stock' => (int) $i->quantity_on_hand,
                'unit' => $i->unit ?? 'units',
                'reorder_level' => (int) $i->reorder_level,
                'is_low_stock' => (int) $i->quantity_on_hand <= (int) $i->reorder_level,
            ])->all(),
        ];
    }

    /**
     * Retrieve recent stock movements with actor attribution.
     *
     * @return array<string, mixed>
     */
    public function getStockMovements(User $actor, ?int $itemId = null, ?string $type = null, int $days = 30, int $limit = 10): array
    {
        $builder = StockMovement::query()
            ->with(['item:id,name,sku,unit', 'user:id,name'])
            ->where('moved_at', '>=', now()->subDays(max(1, $days)));

        if ($itemId !== null) {
            $builder->where('item_id', $itemId);
        }

        if ($type !== null && trim($type) !== '') {
            $builder->where('movement_type', $type);
        }

        $movements = $builder->latest('moved_at')->take($limit)->get();

        return [
            'count' => $movements->count(),
            'period_days' => $days,
            'movements' => $movements->map(function (StockMovement $m) {
                $typeLabel = $m->movement_type instanceof MovementType
                    ? $m->movement_type->label()
                    : (string) $m->movement_type;

                return [
                    'id' => $m->id,
                    'item_name' => $m->item?->name ?? 'Unknown Item',
                    'sku' => $m->item?->sku ?? 'N/A',
                    'type' => $typeLabel,
                    'quantity' => (int) $m->quantity,
                    'unit' => $m->item?->unit ?? 'units',
                    'actor' => $m->user?->name ?? 'System',
                    'date' => $m->moved_at?->toFormattedDateString() ?? 'Recently',
                    'time_ago' => $m->moved_at?->diffForHumans() ?? 'recently',
                    'notes' => $m->remarks,
                ];
            })->all(),
        ];
    }

    /**
     * Retrieve active batches with one through ninety days remaining.
     *
     * @return array<string, mixed>
     */
    public function getExpiringBatches(User $actor, int $daysAhead = 90, ?int $itemId = null, int $limit = 10): array
    {
        $daysAhead = max(1, min(ItemBatch::EXPIRING_SOON_DAYS, $daysAhead));

        $builder = ItemBatch::query()
            ->with(['item:id,name,sku,unit', 'stockLevels'])
            ->withSum('stockLevels as remaining_stock', 'quantity')
            ->expiringSoon($daysAhead)
            ->where(function ($q) {
                $q->whereHas('stockLevels', fn ($sl) => $sl->where('quantity', '>', 0))
                    ->orWhere('initial_quantity', '>', 0);
            });

        if ($itemId !== null) {
            $builder->where('item_id', $itemId);
        }

        $batches = $builder->orderBy('expiry_date', 'asc')->take($limit)->get();

        return [
            'count' => $batches->count(),
            'threshold_days' => $daysAhead,
            'batches' => $batches->map(function (ItemBatch $b) {
                $days = $b->daysUntilExpiry();
                $remainingQty = (int) ($b->remaining_stock ?? ($b->stockLevels->sum('quantity') ?: ($b->initial_quantity ?? 0)));

                return [
                    'id' => $b->id,
                    'item_name' => $b->item?->name ?? 'Unknown Item',
                    'sku' => $b->item?->sku ?? 'N/A',
                    'batch_number' => $b->batch_number,
                    'lot_number' => $b->lot_number ?? 'N/A',
                    'remaining_quantity' => $remainingQty,
                    'unit' => $b->item?->unit ?? 'units',
                    'expiry_date' => $b->expiry_date?->toDateString(),
                    'days_remaining' => $days,
                    'status' => strtoupper($b->expiryClassification() ?? 'normal'),
                    'status_label' => $b->expiryStatusLabel(),
                    'recommendation' => 'Prioritize FEFO Dispensing',
                ];
            })->all(),
        ];
    }

    /**
     * Retrieve items or batches that have no expiry date recorded.
     *
     * HIMS represents expiry on the batch (`item_batches.expiry_date`, nullable)
     * and, separately, on the item (`inventory_items.is_expiry_tracked`). Those
     * two can disagree, so neither is trusted on its own: an item is reported as
     * having no expiry only when no expiry date is recorded anywhere against it
     * — none of its batches carries one, which also covers an item that has no
     * batch rows yet. `is_expiry_tracked` then says which of the two readings
     * applies:
     *
     *  - `false` — the item is configured as non-perishable, so having no expiry
     *    is correct and expected (equipment, linen, reagents with no shelf life).
     *  - `true`  — the item is meant to be expiry-tracked but no expiry date has
     *    been captured. Nothing is expiring here; the record is incomplete.
     *
     * Batches that carry a NULL `expiry_date` while their item is otherwise
     * expiry-tracked are reported separately as missing expiry information, so
     * an incomplete record is never mistaken for a non-expiring one.
     *
     * @return array<string, mixed>
     */
    public function getItemsWithoutExpiry(User $actor, int $limit = 10): array
    {
        $limit = max(1, min(40, $limit));

        // 1. Batches holding stock but carrying no expiry date of their own.
        $batchesWithoutExpiry = ItemBatch::query()
            ->with(['item:id,name,sku,unit,category_id,is_expiry_tracked', 'item.category:id,name', 'stockLevels'])
            ->withSum('stockLevels as remaining_stock', 'quantity')
            ->whereNull('expiry_date')
            ->where(function ($q) {
                $q->whereHas('stockLevels', fn ($sl) => $sl->where('quantity', '>', 0))
                    ->orWhere('initial_quantity', '>', 0);
            })
            ->orderBy('item_id')
            ->get();

        // 2. Active items with stock on hand and no expiry date recorded on any
        //    of their batches (an item with no batches at all included).
        $itemsWithNoExpiryRecorded = InventoryItem::query()
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->whereDoesntHave('batches', fn ($q) => $q->whereNotNull('expiry_date'))
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        $results = [];
        $coveredItemIds = [];
        $missingExpiryBatchCount = 0;

        foreach ($batchesWithoutExpiry->groupBy('item_id') as $itemId => $batches) {
            $item = $batches->first()->item;
            if (! $item) {
                continue;
            }

            $batchList = $batches->map(fn (ItemBatch $b) => [
                'batch_number' => $b->batch_number,
                'lot_number' => $b->lot_number ?? 'N/A',
                'remaining_quantity' => (int) ($b->remaining_stock ?? ($b->stockLevels->sum('quantity') ?: ($b->initial_quantity ?? 0))),
            ])->all();

            $missingExpiryBatchCount += count($batchList);

            $results[] = [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category?->name ?? 'General',
                'unit' => $item->unit ?? 'units',
                'total_stock_without_expiry' => array_sum(array_column($batchList, 'remaining_quantity')),
                'batch_count' => count($batchList),
                'batches' => $batchList,
                'basis' => 'batch_missing_expiry',
                'basis_label' => 'Batch has no expiry date recorded',
                'reason' => 'Batch(es) with no expiry date recorded',
            ];
            $coveredItemIds[] = $item->id;
        }

        foreach ($itemsWithNoExpiryRecorded as $item) {
            // Items already reported above carry their batch detail with them.
            if (in_array($item->id, $coveredItemIds, true)) {
                continue;
            }

            $isExpiryTracked = (bool) $item->is_expiry_tracked;

            $results[] = [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category?->name ?? 'General',
                'unit' => $item->unit ?? 'units',
                'total_stock_without_expiry' => (int) $item->quantity_on_hand,
                'batch_count' => 0,
                'batches' => [],
                'basis' => $isExpiryTracked ? 'expiry_tracked_missing_date' : 'not_expiry_tracked',
                'basis_label' => $isExpiryTracked
                    ? 'Expiry-tracked item with no expiry date captured'
                    : 'Configured as non-expiring (no expiry date applies)',
                'reason' => $isExpiryTracked
                    ? 'Expiry-tracked item with no expiry date recorded yet'
                    : 'Non-perishable item (expiry not tracked)',
            ];
        }

        $totalCount = count($results);

        return [
            'count' => min($totalCount, $limit),
            'total_matching' => $totalCount,
            'items' => array_slice($results, 0, $limit),
            'batches_missing_expiry_count' => $missingExpiryBatchCount,
            'has_no_expiry' => true,
        ];
    }

    /**
     * Retrieve batches that have already expired (expiry_date < today) and still
     * have remaining stock on hand, indicating stock that should be quarantined
     * or disposed of.
     *
     * @return array<string, mixed>
     */
    public function getExpiredBatches(User $actor, ?int $itemId = null, int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));

        $builder = ItemBatch::query()
            ->with(['item:id,name,sku,unit', 'stockLevels'])
            ->withSum('stockLevels as remaining_stock', 'quantity')
            ->expired()
            ->where(function ($q) {
                $q->whereHas('stockLevels', fn ($sl) => $sl->where('quantity', '>', 0))
                    ->orWhere('initial_quantity', '>', 0);
            });

        if ($itemId !== null) {
            $builder->where('item_id', $itemId);
        }

        $batches = $builder->orderBy('expiry_date', 'asc')->take($limit)->get();

        return [
            'count' => $batches->count(),
            'is_expired' => true,
            'batches' => $batches->map(function (ItemBatch $b) {
                $daysSinceExpiry = abs($b->daysUntilExpiry() ?? 0);
                $remainingQty = (int) ($b->remaining_stock ?? ($b->stockLevels->sum('quantity') ?: ($b->initial_quantity ?? 0)));

                return [
                    'id' => $b->id,
                    'item_name' => $b->item?->name ?? 'Unknown Item',
                    'sku' => $b->item?->sku ?? 'N/A',
                    'batch_number' => $b->batch_number,
                    'lot_number' => $b->lot_number ?? 'N/A',
                    'remaining_quantity' => $remainingQty,
                    'unit' => $b->item?->unit ?? 'units',
                    'expiry_date' => $b->expiry_date->toDateString(),
                    'days_since_expiry' => $daysSinceExpiry,
                    'recommendation' => $daysSinceExpiry > 90
                        ? 'Immediate disposal required — expired over 90 days'
                        : 'Quarantine and schedule disposal',
                ];
            })->all(),
        ];
    }

    /**
     * Retrieve procurement purchase orders (financial totals masked if unauthorized).
     *
     * @return array<string, mixed>
     */
    public function getProcurementRecords(User $actor, ?string $status = null, ?int $supplierId = null, int $limit = 8): array
    {
        $builder = PurchaseOrder::query()
            ->with(['supplier:id,name', 'lines:id,purchase_order_id,item_id,ordered_quantity']);

        if ($status !== null && trim($status) !== '') {
            $builder->where('status', $status);
        }

        if ($supplierId !== null) {
            $builder->where('supplier_id', $supplierId);
        }

        $orders = $builder->latest('created_at')->take($limit)->get();
        $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);

        return [
            'count' => $orders->count(),
            'orders' => $orders->map(function (PurchaseOrder $po) use ($canViewFinances) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_name' => $po->supplier?->name ?? 'Unknown Supplier',
                    'status' => is_object($po->status) ? $po->status->value : (string) $po->status,
                    'order_date' => $po->order_date ?? $po->created_at?->toDateString(),
                    'expected_delivery_date' => $po->delivery_date?->toDateString() ?? 'Not Scheduled',
                    'items_count' => $po->lines->count(),
                    'total_amount' => $canViewFinances && $po->total_amount !== null ? (float) $po->total_amount : '[Restricted]',
                ];
            })->all(),
        ];
    }

    /**
     * Retrieve material / department supply requisitions.
     *
     * @return array<string, mixed>
     */
    public function getDepartmentRequisitions(User $actor, ?string $status = null, ?string $department = null, int $limit = 8): array
    {
        $builder = MaterialRequisition::query()
            ->with(['requestingUser:id,name,department']);

        if ($status !== null && trim($status) !== '') {
            $builder->where('status', $status);
        }

        if ($department !== null && trim($department) !== '') {
            $dept = trim($department);
            $builder->where('department', 'LIKE', "%{$dept}%");
        }

        $requisitions = $builder->latest('created_at')->take($limit)->get();

        return [
            'count' => $requisitions->count(),
            'requisitions' => $requisitions->map(function (MaterialRequisition $mr) {
                return [
                    'id' => $mr->id,
                    'requisition_number' => $mr->requisition_number,
                    'department' => $mr->department ?? $mr->requestingUser?->department ?? 'General Department',
                    'status' => (string) $mr->status,
                    'urgency' => $mr->urgency ?? 'Normal',
                    'requested_by' => $mr->requestingUser?->name ?? 'Staff',
                    'required_date' => $mr->required_date?->toDateString() ?? 'Immediate',
                    'justification' => $mr->justification,
                ];
            })->all(),
        ];
    }

    /**
     * Retrieve shipment deliveries and identify delayed shipments.
     *
     * @return array<string, mixed>
     */
    public function getShipmentsAndDeliveries(User $actor, ?string $status = null, bool $delayedOnly = false, int $limit = 8): array
    {
        $builder = Shipment::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number']);

        if ($status !== null && trim($status) !== '') {
            $builder->where('status', $status);
        }

        if ($delayedOnly) {
            $builder->where('estimated_delivery_date', '<', now()->startOfDay())
                ->whereNull('actual_delivery_date');
        }

        $shipments = $builder->latest('dispatch_date')->take($limit)->get();

        return [
            'count' => $shipments->count(),
            'delayed_only' => $delayedOnly,
            'shipments' => $shipments->map(function (Shipment $s) {
                $isDelayed = $s->estimated_delivery_date && $s->estimated_delivery_date < now()->startOfDay() && empty($s->actual_delivery_date);

                return [
                    'id' => $s->id,
                    'shipment_number' => $s->shipment_number,
                    'po_number' => $s->purchaseOrder?->po_number ?? 'N/A',
                    'supplier_name' => $s->supplier?->name ?? 'Unknown Supplier',
                    'carrier' => $s->carrier_name ?? 'In-House Logistics',
                    'tracking_number' => $s->tracking_number ?? 'N/A',
                    'status' => (string) $s->status,
                    'dispatch_date' => $s->dispatch_date?->toDateString(),
                    'estimated_delivery' => $s->estimated_delivery_date?->toDateString(),
                    'actual_delivery' => $s->actual_delivery_date?->toDateString(),
                    'is_delayed' => $isDelayed,
                    'is_cold_chain' => (bool) $s->is_cold_chain,
                ];
            })->all(),
        ];
    }

    /**
     * Compute total inventory valuation and breakdown by category.
     * Strictly gated by ViewProcurementSensitiveData permission.
     *
     * @return array<string, mixed>
     */
    public function getInventoryValuation(User $actor, ?int $categoryId = null): array
    {
        if (! $actor->can(Permission::ViewProcurementSensitiveData->value)) {
            return [
                'authorized' => false,
                'message' => 'Restricted: You do not have permission to view procurement financial valuations. Physical item counts and availability remain accessible.',
            ];
        }

        $builder = InventoryItem::query()->where('status', 'active');
        if ($categoryId !== null) {
            $builder->where('category_id', $categoryId);
        }

        $totalItems = $builder->count();
        $totalUnits = (int) $builder->sum('quantity_on_hand');
        $totalValuation = (float) $builder->sum('total_value');

        // Category breakdown
        $categories = InventoryItem::query()
            ->where('inventory_items.status', 'active')
            ->join('item_categories', 'inventory_items.category_id', '=', 'item_categories.id')
            ->groupBy('item_categories.id', 'item_categories.name')
            ->selectRaw('item_categories.name as category_name, count(*) as item_count, sum(inventory_items.quantity_on_hand) as units, sum(inventory_items.total_value) as valuation')
            ->orderByDesc('valuation')
            ->take(8)
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category_name,
                'items' => (int) $r->item_count,
                'units' => (int) $r->units,
                'valuation' => (float) $r->valuation,
            ])->all();

        return [
            'authorized' => true,
            'total_items' => $totalItems,
            'total_units_on_hand' => $totalUnits,
            'total_valuation_php' => round($totalValuation, 2),
            'category_breakdown' => $categories,
        ];
    }

    /**
     * Compute statistical demand forecasting and days of cover for an item.
     *
     * @return array<string, mixed>|null
     */
    public function getDemandForecast(User $actor, int|string $identifier, int $analysisDays = 90, int $forecastDays = 30): ?array
    {
        $item = $this->findItem($identifier);
        if (! $item) {
            return null;
        }

        $leadTime = (int) ($item->lead_time_days ?: DemandForecastService::DEFAULT_LEAD_TIME_DAYS);
        $forecast = $this->forecastService->forecast($item, $analysisDays, $forecastDays, $leadTime);

        $onHand = (int) $item->quantity_on_hand;
        $reserved = (int) ($item->reserved_quantity ?? 0);
        $available = max(0, $onHand - $reserved);

        $dailyUsage = (float) ($forecast['average_daily_usage'] ?? 0);
        $daysOfCover = $dailyUsage > 0 ? round($available / $dailyUsage, 1) : null;
        $isDataLimited = (int) ($forecast['history_total'] ?? 0) === 0;

        return [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'sku' => $item->sku,
            'unit' => $item->unit ?? 'units',
            'current_stock' => $onHand,
            'reserved_stock' => $reserved,
            'available_stock' => $available,
            'reorder_level' => (int) $item->reorder_level,
            'safety_stock' => (int) ($forecast['safety_stock'] ?? $item->safety_stock),
            'reorder_point' => (int) ($forecast['reorder_point'] ?? $item->reorder_point),
            'supplier_lead_time_days' => $leadTime,
            'analysis_period_days' => $analysisDays,
            'forecast_horizon_days' => $forecastDays,
            'historical_consumption_units' => (int) ($forecast['history_total'] ?? 0),
            'average_daily_usage' => round($dailyUsage, 2),
            'days_of_cover' => $daysOfCover,
            'predicted_demand_units' => (int) ($forecast['projected_usage'] ?? 0),
            'suggested_reorder_quantity' => (int) ($forecast['suggested_order_quantity'] ?? 0),
            'demand_trend' => (string) ($forecast['trend']?->value ?? $forecast['trend'] ?? 'stable'),
            'limited_data' => $isDataLimited,
            'explanation' => $isDataLimited
                ? 'There is not enough historical movement data to calculate a reliable consumption rate.'
                : ($forecast['trigger_reason'] ?? 'Calculated using moving-average consumption over recorded stock issues.'),
        ];
    }

    /**
     * Retrieve recent audit activity (stripped of confidential secrets).
     *
     * @return array<string, mixed>
     */
    public function getRecentAuditActivity(User $actor, int $limit = 8, ?string $module = null): array
    {
        if (! $actor->can(Permission::ViewReports->value)) {
            return [
                'authorized' => false,
                'message' => 'Restricted: You do not have permission to view system audit logs.',
            ];
        }

        $builder = AuditLog::query();
        if ($module !== null && trim($module) !== '') {
            $builder->where('module', $module);
        }

        $logs = $builder->latest('created_at')->take($limit)->get();

        return [
            'authorized' => true,
            'count' => $logs->count(),
            'logs' => $logs->map(fn (AuditLog $l) => [
                'event_id' => $l->event_id,
                'actor' => $l->actor_name ?? 'System',
                'action' => is_object($l->action) ? $l->action->value : (string) $l->action,
                'module' => $l->module,
                'description' => $l->description,
                'date' => $l->created_at?->toFormattedDateString(),
                'time_ago' => $l->created_at?->diffForHumans(),
            ])->all(),
        ];
    }

    /**
     * Retrieve open system recovery records.
     *
     * @return array<string, mixed>
     */
    public function getSystemRecoveryStatus(User $actor): array
    {
        if (! $actor->can(Permission::ManageSystemRecovery->value)) {
            return [
                'authorized' => false,
                'message' => 'Restricted: You do not have permission to view system recovery diagnostics.',
            ];
        }

        if (! Schema::hasTable('system_recovery_records')) {
            return ['authorized' => true, 'open_issues_count' => 0, 'issues' => []];
        }

        $issues = SystemRecoveryRecord::open()->latest('created_at')->take(6)->get();

        return [
            'authorized' => true,
            'open_issues_count' => $issues->count(),
            'issues' => $issues->map(fn (SystemRecoveryRecord $r) => [
                'error_id' => $r->error_id,
                'module' => $r->module,
                'operation' => $r->operation,
                'error_summary' => $r->error_summary,
                'retry_count' => $r->retry_count,
                'is_retryable' => (bool) $r->is_retryable,
            ])->all(),
        ];
    }

    /**
     * Compute today's operational summary.
     *
     * @return array<string, mixed>
     */
    public function getDailySummary(User $actor): array
    {
        $today = now()->startOfDay();

        $totalItems = InventoryItem::where('status', 'active')->count();
        $outOfStock = InventoryItem::where('status', 'active')->where('quantity_on_hand', '<=', 0)->count();
        $lowStock = InventoryItem::where('status', 'active')->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('quantity_on_hand', '>', 0)->count();

        $movementsToday = StockMovement::where('moved_at', '>=', $today)->count();
        $unitsIssuedToday = (int) StockMovement::where('moved_at', '>=', $today)->where('movement_type', MovementType::StockOut)->sum('quantity');
        $unitsReceivedToday = (int) StockMovement::where('moved_at', '>=', $today)->where('movement_type', MovementType::StockIn)->sum('quantity');

        $pendingRequisitions = MaterialRequisition::whereIn('status', ['pending', 'draft', 'pending_approval'])->count();
        $pendingPOs = PurchaseOrder::whereIn('status', ['pending', 'draft', 'ordered'])->count();
        $expiringBatches = ItemBatch::query()
            ->expiringSoon()
            ->where(function ($q) {
                $q->whereHas('stockLevels', fn ($sl) => $sl->where('quantity', '>', 0))
                    ->orWhere('initial_quantity', '>', 0);
            })
            ->count();

        return [
            'date' => now()->toFormattedDateString(),
            'active_items_count' => $totalItems,
            'out_of_stock_count' => $outOfStock,
            'low_stock_count' => $lowStock,
            'movements_today_count' => $movementsToday,
            'units_issued_today' => $unitsIssuedToday,
            'units_received_today' => $unitsReceivedToday,
            'pending_requisitions_count' => $pendingRequisitions,
            'pending_purchase_orders_count' => $pendingPOs,
            'expiring_batches_90_days' => $expiringBatches,
        ];
    }

    /**
     * Helper to locate an inventory item by numeric ID, exact SKU, or partial name.
     */
    public function findItem(int|string $identifier): ?InventoryItem
    {
        if (is_numeric($identifier)) {
            $item = InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels'])->find((int) $identifier);
            if ($item) {
                return $item;
            }
        }

        $str = trim((string) $identifier);
        if ($str === '') {
            return null;
        }

        // Exact SKU match
        $item = InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])->where('sku', $str)->first();
        if ($item) {
            return $item;
        }

        // Exact Name match
        $item = InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])->where('name', $str)->first();
        if ($item) {
            return $item;
        }

        // Exact barcode match
        $item = InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])->where('barcode_value', $str)->first();
        if ($item) {
            return $item;
        }

        // Case-insensitive Like Name, SKU, generic_name, brand_name match
        return InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])
            ->where('name', 'LIKE', "%{$str}%")
            ->orWhere('sku', 'LIKE', "%{$str}%")
            ->orWhere('generic_name', 'LIKE', "%{$str}%")
            ->orWhere('brand_name', 'LIKE', "%{$str}%")
            ->orWhere('barcode_value', 'LIKE', "%{$str}%")
            ->first();
    }

    /**
     * Search multiple matching inventory items for ambiguous inquiries.
     *
     * @return \Illuminate\Support\Collection<int, InventoryItem>
     */
    public function searchMatchingItems(string $query, int $limit = 6): Collection
    {
        $term = trim($query);
        if ($term === '') {
            return collect();
        }

        return InventoryItem::query()
            ->with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])
            ->where(function ($b) use ($term) {
                $b->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('sku', 'LIKE', "%{$term}%")
                    ->orWhere('barcode_value', 'LIKE', "%{$term}%")
                    ->orWhere('generic_name', 'LIKE', "%{$term}%")
                    ->orWhere('brand_name', 'LIKE', "%{$term}%");
            })
            ->take($limit)
            ->get();
    }

    /**
     * Retrieve storage locations, zones, classifications, and current capacity states.
     *
     * @return array<string, mixed>
     */
    public function getStorageLocations(User $actor, ?string $type = null, int $limit = 12): array
    {
        if (! $actor->can(Permission::ViewInventory->value)) {
            return [
                'authorized' => false,
                'count' => 0,
                'locations' => [],
                'message' => 'You do not have permission to view storage locations.',
            ];
        }

        $query = StorageLocation::query();
        if ($type !== null && trim($type) !== '') {
            $t = trim($type);
            $query->where(function (Builder $b) use ($t) {
                $b->where('type', 'LIKE', "%{$t}%")
                    ->orWhere('zone', 'LIKE', "%{$t}%")
                    ->orWhere('name', 'LIKE', "%{$t}%");
            });
        }

        $locations = $query->orderBy('name')->take($limit)->get();

        return [
            'authorized' => true,
            'count' => $locations->count(),
            'locations' => $locations->map(fn (StorageLocation $loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => $loc->code,
                'type' => $loc->type,
                'zone' => $loc->zone,
                'temperature_classification' => $loc->temperature_classification,
                'status' => $loc->status,
                'capacity' => $loc->capacity,
                'aisle' => $loc->aisle,
                'rack' => $loc->rack,
                'shelf' => $loc->shelf,
                'bin' => $loc->bin,
            ])->all(),
        ];
    }

    /**
     * Retrieve active stock alerts, threshold warnings, and critical anomalies.
     *
     * @return array<string, mixed>
     */
    public function getStockAlerts(User $actor, ?string $severity = null, int $limit = 8): array
    {
        if (! $actor->can(Permission::ViewInventory->value)) {
            return [
                'authorized' => false,
                'count' => 0,
                'alerts' => [],
                'message' => 'You do not have permission to view stock alerts.',
            ];
        }

        $query = StockAlert::query()
            ->with(['item:id,name,sku', 'storageLocation:id,name,code'])
            ->whereNull('resolved_at');

        if ($severity !== null && trim($severity) !== '') {
            $query->where('severity', trim($severity));
        }

        $alerts = $query->latest()->take($limit)->get();

        return [
            'authorized' => true,
            'count' => $alerts->count(),
            'alerts' => $alerts->map(fn (StockAlert $alert) => [
                'id' => $alert->id,
                'item_name' => $alert->item?->name ?? 'Unknown Item',
                'sku' => $alert->item?->sku ?? 'N/A',
                'type' => is_object($alert->type) ? $alert->type->value : $alert->type,
                'severity' => is_object($alert->severity) ? $alert->severity->value : $alert->severity,
                'status' => is_object($alert->status) ? $alert->status->value : $alert->status,
                'message' => $alert->message,
                'threshold_value' => $alert->threshold_value,
                'current_value' => $alert->current_value,
                'location' => $alert->storageLocation?->name,
                'created_at' => $alert->created_at?->toFormattedDateString(),
            ])->all(),
        ];
    }

    /**
     * Retrieve Goods Receipt Notes (GRN) and Inspection Acceptance Reports (IAR).
     *
     * @return array<string, mixed>
     */
    public function getReceivingRecords(User $actor, int $limit = 8): array
    {
        if (! $actor->can(Permission::ViewInventory->value) && ! $actor->can(Permission::ViewProcurement->value)) {
            return [
                'authorized' => false,
                'grn_count' => 0,
                'iar_count' => 0,
                'grns' => [],
                'iars' => [],
                'message' => 'You do not have permission to view receiving and inspection records.',
            ];
        }

        $grns = GoodsReceiptNote::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number'])
            ->latest('received_at')
            ->take($limit)
            ->get();

        $iars = InspectionAcceptanceReport::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number'])
            ->latest('iar_date')
            ->take($limit)
            ->get();

        return [
            'authorized' => true,
            'grn_count' => $grns->count(),
            'iar_count' => $iars->count(),
            'grns' => $grns->map(fn (GoodsReceiptNote $g) => [
                'id' => $g->id,
                'grn_number' => $g->grn_number,
                'dr_number' => $g->dr_number,
                'supplier' => $g->supplier?->name ?? 'N/A',
                'po_number' => $g->purchaseOrder?->po_number ?? 'N/A',
                'receipt_status' => $g->receipt_status,
                'delivery_status' => $g->delivery_status,
                'received_at' => $g->received_at?->toFormattedDateString(),
                'is_cold_chain' => (bool) $g->is_cold_chain,
            ])->all(),
            'iars' => $iars->map(fn (InspectionAcceptanceReport $i) => [
                'id' => $i->id,
                'iar_number' => $i->iar_number,
                'supplier' => $i->supplier?->name ?? 'N/A',
                'po_number' => $i->purchaseOrder?->po_number ?? 'N/A',
                'inspection_status' => $i->inspection_status,
                'status' => $i->status,
                'inspection_findings' => $i->inspection_findings,
                'iar_date' => $i->iar_date?->toFormattedDateString(),
            ])->all(),
        ];
    }

    /**
     * Retrieve chain of custody logs for regulated medications, narcotics, or high-value items.
     *
     * @return array<string, mixed>
     */
    public function getChainOfCustodyRecords(User $actor, ?int $itemId = null, int $limit = 8): array
    {
        if (! $actor->can(Permission::ViewInventory->value)) {
            return [
                'authorized' => false,
                'count' => 0,
                'records' => [],
                'message' => 'You do not have permission to view chain of custody logs.',
            ];
        }

        $query = ChainOfCustodyLog::query()
            ->with(['releasingUser:id,name', 'receivingUser:id,name']);

        if ($itemId !== null) {
            $query->where('trackable_type', InventoryItem::class)
                ->where('trackable_id', $itemId);
        }

        $logs = $query->latest('transferred_at')->take($limit)->get();

        return [
            'authorized' => true,
            'count' => $logs->count(),
            'records' => $logs->map(fn (ChainOfCustodyLog $log) => [
                'id' => $log->id,
                'custody_number' => $log->custody_number,
                'event_type' => $log->event_type,
                'releasing_party' => $log->releasingUser?->name ?? $log->releasing_party_name ?? 'N/A',
                'receiving_party' => $log->receivingUser?->name ?? $log->receiving_party_name ?? 'N/A',
                'transferred_at' => $log->transferred_at?->toFormattedDateString(),
                'origin_location' => $log->origin_location,
                'destination_location' => $log->destination_location,
                'package_condition' => $log->package_condition,
                'verification_method' => $log->verification_method,
            ])->all(),
        ];
    }

    /**
     * Retrieve user accounts and assigned roles. Strictly requires ManageUsers permission.
     * Never exposes password hashes, tokens, MFA secrets, or recovery keys.
     *
     * @return array<string, mixed>
     */
    public function getUserManagementInfo(User $actor, ?string $search = null): array
    {
        if (! $actor->can(Permission::ManageUsers->value)) {
            return [
                'authorized' => false,
                'count' => 0,
                'users' => [],
                'message' => 'You do not have permission to view user accounts. Only authorized administrators may manage users.',
            ];
        }

        $query = User::query()
            ->select(['id', 'name', 'email', 'role', 'status', 'employee_id', 'department']);

        if ($search !== null && trim($search) !== '') {
            $s = trim($search);
            $query->where(function (Builder $b) use ($s) {
                $b->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('email', 'LIKE', "%{$s}%")
                    ->orWhere('department', 'LIKE', "%{$s}%")
                    ->orWhere('employee_id', 'LIKE', "%{$s}%");
            });
        }

        $users = $query->orderBy('name')->take(12)->get();

        return [
            'authorized' => true,
            'count' => $users->count(),
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => is_object($u->role) ? $u->role->label() : (string) $u->role,
                'status' => is_object($u->status) ? $u->status->label() : (string) $u->status,
                'department' => $u->department ?? 'General',
                'employee_id' => $u->employee_id ?? 'N/A',
            ])->all(),
        ];
    }

    /**
     * Retrieve available reports catalog in HIMS.
     *
     * @return array<string, mixed>
     */
    public function getReportsCatalog(User $actor): array
    {
        $canViewReports = $actor->can(Permission::ViewReports->value);
        $canViewFinances = $actor->can(Permission::ViewProcurementSensitiveData->value);

        return [
            'authorized' => $canViewReports,
            'reports' => [
                [
                    'name' => 'Inventory Valuation Report',
                    'category' => 'Financial',
                    'description' => 'Total monetary valuation of all items on hand grouped by category.',
                    'accessible' => $canViewFinances,
                ],
                [
                    'name' => 'Stock Movement & Audit Report',
                    'category' => 'Inventory',
                    'description' => 'Complete ledger of stock-in, stock-out, and transfers with actor attribution.',
                    'accessible' => $canViewReports,
                ],
                [
                    'name' => 'Batch Expiry & FEFO Risk Report',
                    'category' => 'Clinical / Pharmacy',
                    'description' => 'Active expiring batches with 1-90 days remaining, including critical 1-30 day stock, plus expired batches at 0 days or past due.',
                    'accessible' => $canViewReports,
                ],
                [
                    'name' => 'Fast / Slow Moving Items Analysis',
                    'category' => 'Analytics',
                    'description' => 'Consumption velocity and turnover rates across departments.',
                    'accessible' => $canViewReports,
                ],
                [
                    'name' => 'Supplier Delivery & SLA Performance',
                    'category' => 'Procurement',
                    'description' => 'Vendor on-time delivery rates, lead time accuracy, and defect counts.',
                    'accessible' => $canViewReports,
                ],
                [
                    'name' => 'Chain of Custody Audit Ledger',
                    'category' => 'Compliance',
                    'description' => 'Verifiable transfer log for controlled substances and high-value equipment.',
                    'accessible' => $canViewReports,
                ],
            ],
        ];
    }

    /**
     * Return OpenAPI/Gemini function declaration definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getGeminiFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'search_inventory',
                'description' => 'Search hospital inventory items by keyword, category, or stock condition (low_stock, out_of_stock, in_stock).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING', 'description' => 'Search term matching item name or SKU.'],
                        'category' => ['type' => 'STRING', 'description' => 'Optional category filter name.'],
                        'stock_status' => ['type' => 'STRING', 'description' => 'Filter by condition: low_stock, out_of_stock, in_stock.'],
                    ],
                ],
            ],
            [
                'name' => 'get_replenishment_recommendations',
                'description' => 'List the items that need replenishing (out of stock or at/below reorder level) with the recommended order quantity, incoming stock already on order, and reorder priority. Use this for any question about what to order, restock, buy, or replenish, including Filipino/Taglish phrasing.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'limit' => ['type' => 'INTEGER', 'description' => 'Maximum items to return (default 8).'],
                    ],
                ],
            ],
            [
                'name' => 'get_stock_details',
                'description' => 'Retrieve complete stock details, locations, batches, and supplier for a specific item.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'item_identifier' => ['type' => 'STRING', 'description' => 'Item ID, SKU, or name.'],
                    ],
                    'required' => ['item_identifier'],
                ],
            ],
            [
                'name' => 'search_suppliers',
                'description' => 'Search hospital medical and pharmaceutical suppliers.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING', 'description' => 'Supplier name or contact.'],
                    ],
                ],
            ],
            [
                'name' => 'get_supplier_items',
                'description' => 'Retrieve all items provided by a specific supplier.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'supplier_identifier' => ['type' => 'STRING', 'description' => 'Supplier ID or name.'],
                    ],
                    'required' => ['supplier_identifier'],
                ],
            ],
            [
                'name' => 'get_stock_movements',
                'description' => 'Get recent inventory stock in, stock out, or transfer movements with actor attribution.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'days' => ['type' => 'INTEGER', 'description' => 'Number of days back to search (default 30).'],
                        'type' => ['type' => 'STRING', 'description' => 'Movement type (stock_in, stock_out, transfer).'],
                    ],
                ],
            ],
            [
                'name' => 'get_expiring_batches',
                'description' => 'Retrieve active medication batches with 1-90 days remaining (nearing expiration, FEFO priority). Results with 1-30 days remaining are Critical / Near Expiry. Do NOT use for items without expiry or items at 0 days/past due.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'days_ahead' => ['type' => 'INTEGER', 'description' => 'Days ahead to check for expiration (default 90).'],
                    ],
                ],
            ],
            [
                'name' => 'get_items_without_expiry',
                'description' => 'Retrieve items or batches that do not have an expiry date (expiry_date is NULL). Use when the user asks about items "walang expiry", "no expiry", "without expiry", "hindi nag-e-expire", or "don\'t expire". Do NOT use this for items nearing expiry or already expired.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'limit' => ['type' => 'INTEGER', 'description' => 'Maximum items to return (default 10).'],
                    ],
                ],
            ],
            [
                'name' => 'get_expired_batches',
                'description' => 'Retrieve batches that are expired (0 days remaining or expiry_date is in the past) and still have remaining stock. Use when the user asks about "expired na", "already expired", "may expired stock", or "past expiry". Do NOT use this for active items with 1-90 days remaining or items without expiry.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [],
                ],
            ],
            [
                'name' => 'get_procurement_records',
                'description' => 'Get hospital purchase orders and procurement requests with statuses.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'status' => ['type' => 'STRING', 'description' => 'PO status: draft, pending, ordered, completed.'],
                    ],
                ],
            ],
            [
                'name' => 'get_department_requisitions',
                'description' => 'Get supply requisitions submitted by hospital departments.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'department' => ['type' => 'STRING', 'description' => 'Department name filter.'],
                    ],
                ],
            ],
            [
                'name' => 'get_shipments_and_deliveries',
                'description' => 'Check supplier shipments, delivery tracking, and delayed shipments.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'delayed_only' => ['type' => 'BOOLEAN', 'description' => 'If true, only return overdue shipments.'],
                    ],
                ],
            ],
            [
                'name' => 'get_inventory_valuation',
                'description' => 'Get total hospital inventory monetary valuation and category breakdown (financial role required).',
                'parameters' => ['type' => 'OBJECT', 'properties' => []],
            ],
            [
                'name' => 'get_demand_forecast',
                'description' => 'Get consumption moving average, days of cover, and suggested reorder quantity for an item.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'item_identifier' => ['type' => 'STRING', 'description' => 'Item ID, SKU, or name.'],
                    ],
                    'required' => ['item_identifier'],
                ],
            ],
            [
                'name' => 'get_daily_summary',
                'description' => 'Retrieve today comprehensive inventory summary (movements, alerts, receipts, stockouts). Use only when the user asks for an overview, summary, status, or dashboard, not for a specific question.',
                'parameters' => ['type' => 'OBJECT', 'properties' => []],
            ],
            [
                'name' => 'get_recent_audit_activity',
                'description' => 'Retrieve recent HIMS audit trail entries with actor attribution (reports permission required).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'module' => ['type' => 'STRING', 'description' => 'Optional module filter.'],
                    ],
                ],
            ],
            [
                'name' => 'get_system_recovery_status',
                'description' => 'Retrieve open system recovery records and failure diagnostics (system recovery permission required).',
                'parameters' => ['type' => 'OBJECT', 'properties' => []],
            ],
            [
                'name' => 'get_storage_locations',
                'description' => 'Retrieve hospital storage locations, zones, classifications, and capacity.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'type' => ['type' => 'STRING', 'description' => 'Optional location type or zone.'],
                    ],
                ],
            ],
            [
                'name' => 'get_stock_alerts',
                'description' => 'Retrieve active stock alerts, threshold warnings, and critical anomalies.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'severity' => ['type' => 'STRING', 'description' => 'Optional severity filter (critical, high, medium, low).'],
                    ],
                ],
            ],
            [
                'name' => 'get_receiving_records',
                'description' => 'Retrieve Goods Receipt Notes (GRN) and Inspection Acceptance Reports (IAR).',
                'parameters' => ['type' => 'OBJECT', 'properties' => []],
            ],
            [
                'name' => 'get_chain_of_custody_records',
                'description' => 'Retrieve chain of custody logs for regulated items, narcotics, or high-value medical supplies.',
                'parameters' => ['type' => 'OBJECT', 'properties' => []],
            ],
            [
                'name' => 'get_user_management_info',
                'description' => 'Retrieve HIMS user accounts and roles (strictly requires ManageUsers permission).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Search term by user name, email, or department.'],
                    ],
                ],
            ],
            [
                'name' => 'get_reports_catalog',
                'description' => 'List available reports and analytical exports in HIMS.',
                'parameters' => ['type' => 'OBJECT', 'properties' => []],
            ],
        ];
    }
}
