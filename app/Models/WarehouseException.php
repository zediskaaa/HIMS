<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseException extends Model
{
    protected $fillable = [
        'exception_number', 'warehouse_task_id', 'exception_type', 'priority', 'status',
        'storage_location_id', 'item_id', 'quantity', 'details', 'raised_by_id',
        'assigned_to_id', 'resolution', 'resolved_by_id', 'resolved_at',
    ];

    protected $casts = ['quantity' => 'integer', 'resolved_at' => 'datetime'];

    public function task(): BelongsTo { return $this->belongsTo(WarehouseTask::class, 'warehouse_task_id'); }
    public function location(): BelongsTo { return $this->belongsTo(StorageLocation::class, 'storage_location_id'); }
    public function storageLocation(): BelongsTo { return $this->belongsTo(StorageLocation::class, 'storage_location_id'); }
    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function raisedBy(): BelongsTo { return $this->belongsTo(User::class, 'raised_by_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_id'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by_id'); }
}
