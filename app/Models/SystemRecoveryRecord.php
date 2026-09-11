<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemRecoveryRecord extends Model
{
    use HasFactory;

    protected $table = 'system_recovery_records';

    protected $fillable = [
        'error_id',
        'user_id',
        'user_snapshot',
        'module',
        'operation',
        'error_summary',
        'exception_class',
        'technical_details',
        'status',
        'strategy_applied',
        'is_retryable',
        'retry_handler',
        'retry_payload',
        'retry_count',
        'last_retried_at',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_notes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'technical_details' => 'array',
            'retry_payload' => 'array',
            'is_retryable' => 'boolean',
            'retry_count' => 'integer',
            'last_retried_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['resolved', 'retried']);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('is_retryable', true)->whereIn('status', ['pending', 'failed_permanently']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'retried'], true);
    }

    public function canRetry(): bool
    {
        return $this->is_retryable && in_array($this->status, ['pending', 'failed_permanently'], true);
    }
}
