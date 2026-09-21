<?php

namespace App\Models;

use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'barcode_value',
        'gtin',
        'category_id',
        'unit',
        'generic_name',
        'pndf_code',
        'brand_name',
        'dosage_form_strength',
        'regulatory_category',
        'lasa_group_code',
        'storage_temp_min',
        'storage_temp_max',
        'fda_cpr_number',
        'is_consignment',
        'is_batch_tracked',
        'is_serial_tracked',
        'is_expiry_tracked',
        'storage_classification',
        'temperature_classification',
        'pick_face_minimum',
        'pick_face_maximum',
        'abc_class',
        'costing_method',
        'quantity_on_hand',
        'reserved_quantity',
        'reorder_level',
        'expiry_alert_days',
        'safety_stock',
        'reorder_point',
        'economic_order_quantity',
        'lead_time_days',
        'annual_demand',
        'unit_cost',
        'total_value',
        'supplier_id',
        'default_location_id',
        'status',
        'archived_at',
        'archived_by',
        'archive_reason',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'is_batch_tracked' => 'boolean',
        'is_serial_tracked' => 'boolean',
        'is_expiry_tracked' => 'boolean',
        'is_consignment' => 'boolean',
        'storage_temp_min' => 'decimal:2',
        'storage_temp_max' => 'decimal:2',
        'pick_face_minimum' => 'integer',
        'pick_face_maximum' => 'integer',
        'quantity_on_hand' => 'integer',
        'reserved_quantity' => 'integer',
        'reorder_level' => 'integer',
        'expiry_alert_days' => 'integer',
        'safety_stock' => 'integer',
        'reorder_point' => 'integer',
        'economic_order_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'annual_demand' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function isDangerousDrug(): bool
    {
        return $this->regulatory_category === 'DANGEROUS_DRUG';
    }

    public function isHighAlert(): bool
    {
        return $this->regulatory_category === 'HIGH_ALERT';
    }

    public function isConsignment(): bool
    {
        return (bool) $this->is_consignment;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'default_location_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class, 'item_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(ItemStockLevel::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'item_id');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class, 'item_id');
    }

    public function grnLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteLine::class, 'item_id');
    }

    public function requisitionLines(): HasMany
    {
        return $this->hasMany(MaterialRequisitionLine::class, 'item_id');
    }

    public function transferLines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class, 'item_id');
    }

    public function cycleCountLines(): HasMany
    {
        return $this->hasMany(CycleCountLine::class, 'item_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'item_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(InventorySerial::class, 'item_id');
    }

    public function warehouseTasks(): HasMany
    {
        return $this->hasMany(WarehouseTask::class, 'item_id');
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class, 'item_id');
    }

    /**
     * Resolve the conversion multiplier (base units per purchase unit).
     *
     * Example: If 1 pack = 100 pieces, conversionFactorFor('pack') returns 100.0.
     */
    public function conversionFactorFor(?string $purchaseUnit): float
    {
        if (blank($purchaseUnit)) {
            return 1.0;
        }

        $cleanUnit = strtolower(trim($purchaseUnit));
        $baseUnit = strtolower(trim($this->unit ?? 'unit'));

        // Direct identity check (or standard piece/unit identity)
        if ($cleanUnit === $baseUnit) {
            return 1.0;
        }

        $pieceSynonyms = ['unit', 'units', 'piece', 'pieces', 'pc', 'pcs', 'each'];
        if (in_array($cleanUnit, $pieceSynonyms, true) && in_array($baseUnit, $pieceSynonyms, true)) {
            return 1.0;
        }

        // 1. Explicit item unit conversion records
        if ($this->relationLoaded('unitConversions')) {
            $conv = $this->unitConversions->first(fn ($c) => strtolower(trim($c->purchase_unit)) === $cleanUnit);
            if ($conv && (float) $conv->conversion_factor > 0) {
                return (float) $conv->conversion_factor;
            }
        } else {
            $conv = $this->unitConversions()
                ->whereRaw('LOWER(purchase_unit) = ?', [$cleanUnit])
                ->value('conversion_factor');
            if ($conv && (float) $conv > 0) {
                return (float) $conv;
            }
        }

        // 2. Check associated supplier product pack size/unit
        $supplierProducts = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts
            : $this->supplierProducts()->get();

        foreach ($supplierProducts as $sp) {
            if (strtolower(trim((string) $sp->unit)) === $cleanUnit && filled($sp->pack_size)) {
                $mult = $this->extractPackagingMultiplier($sp->pack_size);
                if ($mult && $mult > 0) {
                    return $mult;
                }
            }
        }

        // 3. Fallback: parse item name or packaging pattern (e.g. "Pack of 100", "Box of 50")
        $nameMultiplier = $this->extractPackagingMultiplier($this->name);
        if ($nameMultiplier && $nameMultiplier > 0) {
            if (in_array($cleanUnit, ['pack', 'packs', 'box', 'boxes', 'bottle', 'bottles', 'case', 'cases', 'carton', 'cartons', 'set', 'sets', 'vial', 'vials'], true)) {
                return $nameMultiplier;
            }
        }

        return 1.0;
    }

    /**
     * Extract numeric multiplier from packaging strings like "Pack of 100", "Box of 50", "(Pack of 100)", "100/box".
     */
    public function extractPackagingMultiplier(?string $text): ?float
    {
        if (blank($text)) {
            return null;
        }

        if (preg_match('/(?:pack|box|case|carton|tray|bottle|set|vial|bag)\s+(?:of\s+)?(\d+(?:\.\d+)?)/i', $text, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:pcs|units?|pieces?|tablets?|capsules?)?\s*\/\s*(?:pack|box|case|carton|pk|bx)/i', $text, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*$/', $text, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Safeguard existing stock balance into an ItemStockLevel row if not already present.
     */
    public function ensureStockLevelExists(?int $locationId = null): void
    {
        $levelsSum = (int) $this->stockLevels()->sum('quantity');
        if ($levelsSum === 0 && (int) $this->quantity_on_hand > 0) {
            $locId = $locationId ?? $this->default_location_id ?? StorageLocation::query()->orderBy('id')->value('id');
            if ($locId) {
                $level = ItemStockLevel::firstOrCreate(
                    [
                        'item_id' => $this->id,
                        'storage_location_id' => $locId,
                        'item_batch_id' => null,
                    ],
                    ['quantity' => 0, 'reserved_quantity' => 0]
                );
                $level->quantity = (int) $this->quantity_on_hand;
                $level->save();
            }
        }
    }

    /**
     * Live total across every location. `quantity_on_hand` caches this value;
     * use this when you need the authoritative number.
     */
    public function actualQuantityOnHand(): int
    {
        return (int) $this->stockLevels()->sum('quantity');
    }

    /**
     * Physical on-hand inventory encompasses Unrestricted, Quarantined, and Blocked stock.
     */
    public function physicalQuantityOnHand(): int
    {
        return (int) $this->stockLevels()->selectRaw('coalesce(sum(quantity + quarantined_quantity + blocked_quantity), 0) as total')->value('total');
    }

    public function quarantinedQuantity(): int
    {
        return (int) $this->stockLevels()->sum('quarantined_quantity');
    }

    public function blockedQuantity(): int
    {
        return (int) $this->stockLevels()->sum('blocked_quantity');
    }

    public function inTransitQuantity(): int
    {
        return (int) $this->stockLevels()->sum('in_transit_quantity');
    }

    public function reservedQuantity(): int
    {
        return (int) ($this->reserved_quantity ?? $this->stockLevels()->sum('reserved_quantity'));
    }

    /**
     * Available-to-Promise excludes stock that is quarantined, blocked, or in transit.
     */
    public function availableToPromise(): int
    {
        return max(0, (int) $this->quantity_on_hand - (int) $this->reserved_quantity);
    }

    public function availableQuantity(): int
    {
        return max(0, (int) $this->quantity_on_hand - (int) $this->reserved_quantity);
    }

    public function isLowStock(): bool
    {
        return (int) $this->reorder_level > 0
            && (int) $this->quantity_on_hand <= (int) $this->reorder_level
            && (int) $this->quantity_on_hand > 0;
    }

    public function isOutOfStock(): bool
    {
        return (int) $this->quantity_on_hand <= 0;
    }

    /**
     * The one rule for the stock condition of an item at a given quantity.
     *
     * Stock condition is derived, never read back from
     * `inventory_items.status`. That column carries the item's lifecycle
     * (`active` / `inactive`) and is set by hand; the stock condition changes
     * with every movement, so having a movement write it meant a receipt could
     * overwrite the lifecycle, and the catalogue and the stock report could
     * describe the same row differently.
     */
    public static function stockStatusFor(int $quantity, int $reorderLevel): string
    {
        return match (true) {
            $quantity <= 0 => 'out_of_stock',
            $reorderLevel > 0 && $quantity <= $reorderLevel => 'low_stock',
            default => 'in_stock',
        };
    }

    public function stockStatus(): string
    {
        return static::stockStatusFor((int) $this->quantity_on_hand, (int) $this->reorder_level);
    }

    /**
     * The item is in use — the opposite of the `inactive` the API accepts for
     * lifecycle. Master-data pickers read through here so a deactivated item is
     * not offered, and so the column keeps one vocabulary.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0);
    }

    /**
     * Narrow the query to one derived stock state.
     *
     * Expressed in SQL from the same quantities `stockStatusFor()` reads, so a
     * filtered page cannot show a row whose badge contradicts the filter. An
     * unrecognised value matches nothing rather than quietly widening back to
     * the unfiltered list.
     */
    public function scopeStockStatus($query, string $status)
    {
        return match ($status) {
            'out_of_stock' => $query->where('quantity_on_hand', '<=', 0),
            'low_stock' => $query->where('quantity_on_hand', '>', 0)
                ->where('reorder_level', '>', 0)
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level'),
            'in_stock' => $query->where('quantity_on_hand', '>', 0)
                ->where(fn ($group) => $group
                    ->where('reorder_level', '<=', 0)
                    ->orWhereColumn('quantity_on_hand', '>', 'reorder_level')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeNeedsAttention($query)
    {
        // Derived from the quantities, and grouped, so the OR cannot escape
        // into a caller's other constraints.
        return $query->where(fn ($group) => $group
            ->where('quantity_on_hand', '<=', 0)
            ->orWhere(fn ($inner) => $inner
                ->where('reorder_level', '>', 0)
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level')));
    }

    public function dpriReferencePrices(): HasMany
    {
        return $this->hasMany(DpriReferencePrice::class, 'pndf_code', 'pndf_code');
    }

    public function activeDpriPrice(): BelongsTo
    {
        return $this->belongsTo(DpriReferencePrice::class, 'pndf_code', 'pndf_code')
            ->where('is_active', true);
    }

    public function unitOfMeasure(): ?UnitOfMeasure
    {
        return UnitOfMeasure::tryFromNormalized($this->unit);
    }

    public function unitLabel(): string
    {
        return UnitOfMeasure::labelFor($this->unit);
    }

    public function unitAbbreviation(): string
    {
        return $this->unitOfMeasure()?->abbreviation() ?? ($this->unit ?: 'unit');
    }
}
