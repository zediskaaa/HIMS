<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseScanEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'scan_identifier', 'warehouse_task_id', 'sequence_number', 'raw_value',
        'normalized_value', 'symbology', 'resolved_type', 'resolved_id', 'outcome',
        'message', 'metadata', 'scanned_by_id', 'idempotency_key', 'created_at',
    ];

    protected $casts = ['sequence_number' => 'integer', 'metadata' => 'array', 'created_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(WarehouseTask::class, 'warehouse_task_id');
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Warehouse scan events are append-only.'));
        static::deleting(fn () => throw new LogicException('Warehouse scan events are append-only.'));
    }
}
