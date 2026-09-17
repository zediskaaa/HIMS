<?php

namespace App\Models;

use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryRetryHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemRecoveryAttempt extends Model
{
    protected $table = 'system_recovery_attempts';

    protected $fillable = [
        'system_recovery_record_id',
        'attempt_number',
        'outcome',
        'handler',
        'message',
        'actor_user_id',
        'actor_snapshot',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => RecoveryAttemptOutcome::class,
            'handler' => RecoveryRetryHandler::class,
            'attempt_number' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SystemRecoveryRecord::class, 'system_recovery_record_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
