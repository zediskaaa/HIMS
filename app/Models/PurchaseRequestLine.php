<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    use HasFactory;

    protected $table = 'pr_line_items';

    protected $fillable = [
        'purchase_request_id',
        'item_id',
        'line_number',
        'gl_account_code',
        'item_description',
        'quantity',
        'uom',
        'estimated_unit_price',
        'estimated_total_price',
        'need_by_date',
        'is_contracted_catalog',
        'contract_id',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'quantity' => 'integer',
        'estimated_unit_price' => 'decimal:2',
        'estimated_total_price' => 'decimal:2',
        'need_by_date' => 'date',
        'is_contracted_catalog' => 'boolean',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SupplierContract::class, 'contract_id');
    }

    public function getRequestedQuantityAttribute(): int
    {
        return (int) ($this->quantity ?? 0);
    }

    public function setRequestedQuantityAttribute($value): void
    {
        $this->attributes['quantity'] = $value;
    }
}
