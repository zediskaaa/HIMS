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
        'purchase_unit',
        'conversion_factor',
        'ordered_quantity',
        'received_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'invoiced_quantity',
        'unit_price',
        'total_line_amount',
        'line_status',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'conversion_factor' => 'decimal:4',
        'ordered_quantity' => 'integer',
        'received_quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
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
        return max(0, $this->ordered_quantity - $this->received_quantity + $this->rejected_quantity);
    }

    public function outstandingQuantity(): int
    {
        return max(0, $this->ordered_quantity - $this->accepted_quantity);
    }

    public function conversionFactor(): float
    {
        $factor = (float) ($this->conversion_factor ?? 1);

        return $factor > 0 ? $factor : 1.0;
    }

    public function orderedBaseQuantity(): int
    {
        return (int) round($this->ordered_quantity * $this->conversionFactor());
    }

    public function receivedBaseQuantity(): int
    {
        return (int) round($this->received_quantity * $this->conversionFactor());
    }

    public function remainingBaseQuantity(): int
    {
        return (int) round($this->remainingQuantity() * $this->conversionFactor());
    }

    public function lineTotal(): float
    {
        return round((float) ($this->total_line_amount ?: ($this->ordered_quantity * $this->unit_price)), 2);
    }

    public function conversionDisplay(): string
    {
        $factor = $this->conversionFactor();
        if ($factor <= 1.0) {
            return '';
        }

        $pUnit = $this->purchase_unit ?: $this->item?->unit ?: 'unit';
        $bUnit = $this->item?->unit ?: 'unit';

        return "1 {$pUnit} = {$factor} {$bUnit}";
    }
}
