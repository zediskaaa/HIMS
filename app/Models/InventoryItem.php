<?php

namespace App\Models;

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
    ];

    protected $casts = [
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

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0);
    }

    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('status', ['low_stock', 'out_of_stock']);
    }
}
