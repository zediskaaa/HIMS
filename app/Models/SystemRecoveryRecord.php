<?php

namespace App\Models;

use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemRecoveryRecord extends Model
{
    use HasFactory;

    /**
     * How many automated attempts an incident may consume before it is left to
     * a human. Keeps a permanently broken operation from being retried forever.
     */
    public const MAX_RECOVERY_ATTEMPTS = 3;

    protected $table = 'system_recovery_records';

    protected $fillable = [
        'error_id',
        'user_id',
        'user_snapshot',
        'module',
        'failure_type',
        'operation',
        'error_summary',
        'affected_resource',
        'reference_id',
        'exception_class',
        'technical_details',
        'status',
        'strategy_applied',
        'is_retryable',
        'retry_handler',
        'retry_payload',
        'retry_count',
        'last_retried_at',
        'last_attempt_outcome',
        'last_attempt_error',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_notes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'failure_type' => RecoveryFailureType::class,
            'status' => RecoveryStatus::class,
            'retry_handler' => RecoveryRetryHandler::class,
            'last_attempt_outcome' => RecoveryAttemptOutcome::class,
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

    /**
     * Append-only history of every recovery attempt made against this incident.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(SystemRecoveryAttempt::class)->orderBy('attempt_number');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RecoveryStatus::Failed,
            RecoveryStatus::RecoveryPending,
            RecoveryStatus::Retrying,
            RecoveryStatus::RecoveryFailed,
        ]);
    }

    public function scopeRecovered(Builder $query): Builder
    {
        return $query->where('status', RecoveryStatus::Recovered);
    }

    public function scopeAwaitingRecovery(Builder $query): Builder
    {
        return $query->whereIn('status', [RecoveryStatus::Failed, RecoveryStatus::RecoveryPending, RecoveryStatus::Retrying]);
    }

    /**
     * Incidents whose operation failed and which still have an untouched retry budget.
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('is_retryable', true)
            ->whereIn('status', [RecoveryStatus::Failed, RecoveryStatus::RecoveryFailed])
            ->where('retry_count', '<', self::MAX_RECOVERY_ATTEMPTS);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isRecovered(): bool
    {
        return $this->status->isRecovered();
    }

    public function attemptsRemaining(): int
    {
        return max(0, self::MAX_RECOVERY_ATTEMPTS - $this->retry_count);
    }

    /**
     * Whether a retry may run right now.
     *
     * Requires a handler the Recovery Center can actually execute, a status that
     * is not already in flight or closed, and an unspent attempt budget. Without
     * all three the UI must not offer a retry at all.
     */
    public function canRetry(): bool
    {
        return $this->is_retryable
            && $this->retry_handler !== null
            && in_array($this->status, [RecoveryStatus::Failed, RecoveryStatus::RecoveryFailed], true)
            && $this->attemptsRemaining() > 0;
    }

    /**
     * Whether a human may still close this incident manually.
     */
    public function canBeClosed(): bool
    {
        return ! $this->status->isTerminal();
    }

    /**
     * Why no retry button is offered, in operator-facing language.
     */
    public function retryBlockedReason(): ?string
    {
        if ($this->canRetry()) {
            return null;
        }

        return match (true) {
            ! $this->is_retryable => 'This failure type has no safe automated retry.',
            $this->retry_handler === null => 'No recovery handler is registered for this operation.',
            $this->status->isInFlight() => 'A recovery attempt is already in progress.',
            $this->attemptsRemaining() === 0 => 'The retry budget of ' . self::MAX_RECOVERY_ATTEMPTS . ' attempts is exhausted.',
            $this->status->isTerminal() => 'This incident is already closed.',
            default => 'This incident cannot be retried in its current state.',
        };
    }
}
