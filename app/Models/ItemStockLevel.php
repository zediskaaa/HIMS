<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The balance of one item, in one storage location, optionally for one batch.
 *
 * This is the source of truth for stock quantities;
 * `inventory_items.quantity_on_hand` is a cached rollup of these rows.
 */
class ItemStockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'storage_location_id',
        'item_batch_id',
        'quantity',
        'reserved_quantity',
        'quarantined_quantity',
        'blocked_quantity',
        'in_transit_quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'quarantined_quantity' => 'integer',
        'blocked_quantity' => 'integer',
        'in_transit_quantity' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    /**
     * Stock that can actually be issued — on hand less anything reserved.
     */
    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeAtLocation($query, int $locationId)
    {
        return $query->where('storage_location_id', $locationId);
    }
}
