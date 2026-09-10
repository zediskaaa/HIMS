<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleCountDoc extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'count_type',
        'scheduled_date',
        'assigned_counter_id',
        'storage_location_id',
        'status',
        'snapshot_timestamp',
        'completed_at',
        'approved_by_id',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'snapshot_timestamp' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function assignedCounter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_counter_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CycleCountLine::class);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'approved', 'posted'], true);
    }
}
