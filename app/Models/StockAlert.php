<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'item_batch_id',
        'storage_location_id',
        'type',
        'severity',
        'status',
        'message',
        'threshold_value',
        'current_value',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'type' => AlertType::class,
        'severity' => AlertSeverity::class,
        'status' => AlertStatus::class,
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'threshold_value' => 'integer',
        'current_value' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
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

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function acknowledge(?int $userId = null): bool
    {
        $this->status = AlertStatus::Acknowledged;
        $this->acknowledged_by = $userId ?? auth()->id();
        $this->acknowledged_at = now();

        return $this->save();
    }

    public function resolve(): bool
    {
        $this->status = AlertStatus::Resolved;
        $this->resolved_at = now();

        return $this->save();
    }

    /**
     * Open or acknowledged — i.e. the condition is still live.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value]);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', AlertStatus::Open->value);
    }

    public function scopeOfType($query, AlertType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Critical first, then newest.
     */
    public function scopeMostUrgent($query)
    {
        return $query->orderByRaw("case severity when 'critical' then 3 when 'warning' then 2 else 1 end desc")
            ->latest();
    }
}
