<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurgicalConsignmentBillOnly extends Model
{
    use HasFactory;

    protected $table = 'surgical_consignment_bill_onlys';

    protected $fillable = [
        'request_number',
        'inventory_item_id',
        'inventory_serial_id',
        'item_batch_id',
        'storage_location_id',
        'patient_encounter_id',
        'operating_suite',
        'surgeon_name',
        'implanted_quantity',
        'status',
        'purchase_request_id',
        'recorded_by_id',
        'implanted_at',
        'notes',
    ];

    protected $casts = [
        'implanted_quantity' => 'integer',
        'implanted_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'inventory_serial_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
