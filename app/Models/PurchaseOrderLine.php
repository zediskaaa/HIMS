<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $table = 'po_line_items';

    protected $fillable = [
        'purchase_order_id',
        'pr_line_id',
        'quote_line_id',
        'item_id',
        'line_number',
        'ordered_quantity',
        'received_quantity',
        'invoiced_quantity',
        'unit_price',
        'total_line_amount',
        'line_status',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'ordered_quantity' => 'integer',
        'received_quantity' => 'integer',
        'invoiced_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_line_amount' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'pr_line_id');
    }

    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLineItem::class, 'quote_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->ordered_quantity - $this->received_quantity);
    }
}
