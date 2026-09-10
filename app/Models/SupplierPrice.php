<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPrice extends Model
{
    protected $fillable = ['supplier_product_id', 'supplier_contract_id', 'unit_price', 'currency', 'minimum_order_quantity', 'effective_from', 'effective_until', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'minimum_order_quantity' => 'integer', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SupplierContract::class, 'supplier_contract_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrent(): bool
    {
        return ! $this->effective_from->gt(today())
            && ($this->effective_until === null || ! $this->effective_until->lt(today()))
            && ($this->contract === null || $this->contract->effectiveStatus() === 'active');
    }
}
