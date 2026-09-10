<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = ['supplier_id', 'item_id', 'supplier_sku', 'supplier_product_name', 'manufacturer', 'brand', 'pack_size', 'unit', 'minimum_order_quantity', 'lead_time_days', 'is_preferred', 'is_active'];

    protected function casts(): array
    {
        return ['minimum_order_quantity' => 'integer', 'lead_time_days' => 'integer', 'is_preferred' => 'boolean', 'is_active' => 'boolean'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SupplierPrice::class);
    }
}
