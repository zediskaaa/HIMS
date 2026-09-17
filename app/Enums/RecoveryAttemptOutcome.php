<?php

namespace App\Enums;

/**
 * The observed result of a single recovery attempt.
 *
 * Attempts are append-only ledger entries; they never overwrite the original
 * failure that produced the incident.
 */
enum RecoveryAttemptOutcome: string
{
    /** The operation was re-executed and verified as successful. */
    case Succeeded = 'succeeded';

    /** The operation was re-executed and failed again. */
    case Failed = 'failed';

    /** The attempt was not executed because re-running it would be unsafe or redundant. */
    case Skipped = 'skipped';

    /** The recovery action was accepted and handed to an asynchronous worker. */
    case Dispatched = 'dispatched';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Skipped => 'Not executed',
            self::Dispatched => 'Dispatched',
        };
    }
}
