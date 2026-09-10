<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'item_batch_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'from_location_id',
        'to_location_id',
        'reference_type',
        'reference_id',
        'remarks',
        'hash',
        'previous_hash',
        'moved_at',
        'user_id',
    ];

    protected $casts = [
        'movement_type' => MovementType::class,
        'moved_at' => 'datetime',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whatever caused this movement — a goods receipt, an adjustment, etc.
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOfType($query, MovementType $type)
    {
        return $query->where('movement_type', $type->value);
    }
}
