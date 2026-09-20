<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnitConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'purchase_unit',
        'conversion_factor',
        'is_default',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
