<?php

namespace App\Models;

use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WarehouseTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_number', 'task_type', 'status', 'priority', 'source_location_id',
        'destination_location_id', 'item_id', 'item_batch_id', 'requested_quantity',
        'completed_quantity', 'assigned_to_id', 'created_by_id', 'reference_type',
        'reference_id', 'idempotency_key', 'due_at', 'started_at', 'completed_at',
        'recommendation_reason', 'override_reason', 'notes',
    ];

    protected $casts = [
        'task_type' => WarehouseTaskType::class,
        'status' => WarehouseTaskStatus::class,
        'requested_quantity' => 'integer',
        'completed_quantity' => 'integer',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'destination_location_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WarehouseTaskEvent::class)->latest('created_at');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(WarehouseScanEvent::class)->orderBy('sequence_number');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(WarehouseException::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->requested_quantity - $this->completed_quantity);
    }
}
