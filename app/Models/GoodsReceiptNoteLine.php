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
        'purchase_unit',
        'conversion_factor',
        'ordered_quantity',
        'shipped_quantity',
        'received_quantity',
        'received_base_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'quarantined_quantity',
        'unit_cost',
        'destination_location_id',
        'staging_location_id',
        'pending_put_away_quantity',
        'returned_quantity',
        'batch_number',
        'lot_number',
        'expiry_date',
        'manufactured_date',
        'serial_number',
        'status',
        'item_condition',
        'discrepancy_type',
        'discrepancy_action',
        'discrepancy_notes',
        'notes',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'ordered_quantity' => 'integer',
        'shipped_quantity' => 'integer',
        'received_quantity' => 'integer',
        'received_base_quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'quarantined_quantity' => 'integer',
        'pending_put_away_quantity' => 'integer',
        'staging_location_id' => 'integer',
        'returned_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'expiry_date' => 'date',
        'manufactured_date' => 'date',
    ];

    public function conversionFactor(): float
    {
        $factor = (float) ($this->conversion_factor ?? 1);

        return $factor > 0 ? $factor : 1.0;
    }

    public function calculatedReceivedBaseQuantity(): int
    {
        if ($this->received_base_quantity > 0) {
            return (int) $this->received_base_quantity;
        }

        return (int) round($this->received_quantity * $this->conversionFactor());
    }

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

    public function stagingLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'staging_location_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'grn_line_item_id');
    }
}
