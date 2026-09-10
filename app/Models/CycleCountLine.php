<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_count_doc_id',
        'item_id',
        'storage_location_id',
        'item_batch_id',
        'book_quantity_snapshot',
        'counted_quantity_blind',
        'variance_quantity',
        'variance_value',
        'recount_required',
        'status',
        'inventory_adjustment_id',
        'notes',
    ];

    protected $casts = [
        'book_quantity_snapshot' => 'integer',
        'counted_quantity_blind' => 'integer',
        'variance_quantity' => 'integer',
        'variance_value' => 'decimal:2',
        'recount_required' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(CycleCountDoc::class, 'cycle_count_doc_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }
}
