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
        'category_id',
        'unit',
        'is_batch_tracked',
        'quantity_on_hand',
        'reserved_quantity',
        'reorder_level',
        'expiry_alert_days',
        'unit_cost',
        'total_value',
        'supplier_id',
        'default_location_id',
        'status',
    ];

    protected $casts = [
        'is_batch_tracked' => 'boolean',
        'quantity_on_hand' => 'integer',
        'reserved_quantity' => 'integer',
        'reorder_level' => 'integer',
        'expiry_alert_days' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

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

    /**
     * Live total across every location. `quantity_on_hand` caches this value;
     * use this when you need the authoritative number.
     */
    public function actualQuantityOnHand(): int
    {
        return (int) $this->stockLevels()->sum('quantity');
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
