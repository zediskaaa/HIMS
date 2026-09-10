<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sourcing_rfq_id',
        'pr_line_id',
        'item_id',
        'line_number',
        'target_quantity',
        'uom',
        'item_description',
        'technical_specifications',
        'max_budget_unit_price',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'target_quantity' => 'integer',
        'max_budget_unit_price' => 'decimal:2',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SourcingRfq::class, 'sourcing_rfq_id');
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'pr_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class, 'rfq_line_item_id');
    }
}
