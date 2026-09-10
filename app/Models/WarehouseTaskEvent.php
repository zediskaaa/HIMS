<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTaskEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['warehouse_task_id', 'event_type', 'from_status', 'to_status', 'actor_id', 'metadata', 'created_at'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(WarehouseTask::class, 'warehouse_task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Warehouse task events are append-only.'));
        static::deleting(fn () => throw new LogicException('Warehouse task events are append-only.'));
    }
}
