<?php

namespace App\Services\Recovery;

use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryStatus;

/**
 * What a recovery handler observed when it ran.
 *
 * A handler must report the outcome it actually verified; it never reports
 * success for an operation it did not re-execute or could not confirm.
 */
final readonly class RecoveryOutcome
{
    private function __construct(
        public RecoveryAttemptOutcome $outcome,
        public RecoveryStatus $resultingStatus,
        public string $message,
    ) {}

    /** The operation was re-executed and verified as successful. */
    public static function succeeded(string $message): self
    {
        return new self(RecoveryAttemptOutcome::Succeeded, RecoveryStatus::Recovered, $message);
    }

    /** The recovery action was accepted but its result is not yet observable. */
    public static function dispatched(string $message): self
    {
        return new self(RecoveryAttemptOutcome::Dispatched, RecoveryStatus::RecoveryPending, $message);
    }

    /** The operation was re-executed and failed again. */
    public static function failed(string $message): self
    {
        return new self(RecoveryAttemptOutcome::Failed, RecoveryStatus::RecoveryFailed, $message);
    }

    /** Running the operation again would be unsafe, redundant, or impossible. */
    public static function skipped(string $message): self
    {
        return new self(RecoveryAttemptOutcome::Skipped, RecoveryStatus::NotRecoverable, $message);
    }
}
