<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequisitionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_requisition_id',
        'item_id',
        'requested_quantity',
        'reserved_quantity',
        'issued_quantity',
        'allocation_strategy',
        'item_batch_id',
        'storage_location_id',
        'line_status',
        'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'issued_quantity' => 'integer',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function unfulfilledQuantity(): int
    {
        return max(0, $this->requested_quantity - $this->issued_quantity);
    }
}
