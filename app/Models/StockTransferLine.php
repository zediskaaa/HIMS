<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'item_id',
        'item_batch_id',
        'dispatched_quantity',
        'received_quantity',
        'damaged_quantity',
        'lost_quantity',
        'line_status',
        'notes',
    ];

    protected $casts = [
        'dispatched_quantity' => 'integer',
        'received_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'lost_quantity' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }
}
