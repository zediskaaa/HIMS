<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IoTTelemetryLog extends Model
{
    use HasFactory;

    protected $table = 'iot_telemetry_logs';

    public $timestamps = false;

    protected $fillable = [
        'sensor_id',
        'storage_location_id',
        'temperature_celsius',
        'relative_humidity_pct',
        'excursion_status',
        'resulting_event',
        'recorded_at',
    ];

    protected $casts = [
        'temperature_celsius' => 'decimal:2',
        'relative_humidity_pct' => 'decimal:2',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function isExcursion(): bool
    {
        return $this->excursion_status === 'excursion';
    }
}
