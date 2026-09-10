<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptNoteLine extends Model
{
    use HasFactory;

    protected $table = 'grn_line_items';

    protected $fillable = [
        'goods_receipt_note_id',
        'po_line_id',
        'item_id',
        'item_batch_id',
        'ordered_quantity',
        'shipped_quantity',
        'received_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'quarantined_quantity',
        'unit_cost',
        'destination_location_id',
        'batch_number',
        'lot_number',
        'expiry_date',
        'manufactured_date',
        'serial_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'ordered_quantity' => 'integer',
        'shipped_quantity' => 'integer',
        'received_quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'quarantined_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'expiry_date' => 'date',
        'manufactured_date' => 'date',
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'destination_location_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'grn_line_item_id');
    }
}
