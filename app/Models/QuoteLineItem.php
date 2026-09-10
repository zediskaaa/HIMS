<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_quote_id',
        'rfq_line_item_id',
        'offered_unit_price',
        'offered_quantity',
        'lead_time_days',
        'shipping_cost',
        'tariffs_cost',
        'handling_cost',
        'discount_amount',
        'landed_cost',
        'technical_compliance',
        'technical_score',
        'is_awarded',
        'notes',
    ];

    protected $casts = [
        'offered_unit_price' => 'decimal:2',
        'offered_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'shipping_cost' => 'decimal:2',
        'tariffs_cost' => 'decimal:2',
        'handling_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'landed_cost' => 'decimal:2',
        'technical_compliance' => 'boolean',
        'technical_score' => 'decimal:2',
        'is_awarded' => 'boolean',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class, 'supplier_quote_id');
    }

    public function rfqLineItem(): BelongsTo
    {
        return $this->belongsTo(RfqLineItem::class);
    }

    public function computeLandedCost(): float
    {
        $baseTotal = $this->offered_unit_price * $this->offered_quantity;
        $landed = $baseTotal + $this->shipping_cost + $this->tariffs_cost + $this->handling_cost - $this->discount_amount;
        $this->landed_cost = max(0, $landed);

        return (float) $this->landed_cost;
    }
}
