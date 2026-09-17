<?php

namespace App\Enums;

/**
 * The recovery operations the Recovery Center can genuinely re-execute.
 *
 * A record is only retryable when its handler appears here; anything else is
 * presented as not recoverable rather than offering a button that cannot work.
 */
enum RecoveryRetryHandler: string
{
    /** Re-runs a staged import payload through the import executor. */
    case Import = 'import';

    /** Pushes a failed queue job back onto its queue. */
    case QueueJob = 'queue_job';

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Staged import replay',
            self::QueueJob => 'Queue job re-dispatch',
        };
    }

    /**
     * Resolve a stored handler value, tolerating unknown legacy values.
     */
    public static function fromStored(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
