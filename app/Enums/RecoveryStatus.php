<?php

namespace App\Enums;

/**
 * Lifecycle of a recorded system failure and its recovery.
 *
 * Every state is derived from an observed operation outcome: a record only
 * becomes Recovered after a retry handler verifies the operation succeeded.
 */
enum RecoveryStatus: string
{
    /** The operation failed and no recovery attempt has succeeded yet. */
    case Failed = 'failed';

    /** Recovery work was handed to an asynchronous worker; the outcome is not yet observable. */
    case RecoveryPending = 'recovery_pending';

    /** A recovery attempt is executing right now. */
    case Retrying = 'retrying';

    /** A recovery attempt ran and the operation was verified as successful. */
    case Recovered = 'recovered';

    /** A recovery attempt ran and did not succeed. */
    case RecoveryFailed = 'recovery_failed';

    /** No safe automated retry exists; the failure needs human handling. */
    case NotRecoverable = 'not_recoverable';

    /** A Super Administrator verified the underlying problem was fixed and closed the incident. */
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Failed => 'Failed',
            self::RecoveryPending => 'Recovery Pending',
            self::Retrying => 'Retrying',
            self::Recovered => 'Recovered',
            self::RecoveryFailed => 'Recovery Failed',
            self::NotRecoverable => 'Not Recoverable',
            self::Resolved => 'Resolved',
        };
    }

    /** A state no further automatic recovery will leave. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Recovered, self::Resolved], true);
    }

    /** An incident that still needs attention from a Super Administrator. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Failed, self::RecoveryPending, self::Retrying, self::RecoveryFailed], true);
    }

    /** A recovery attempt is in flight or awaiting an asynchronous worker. */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Retrying, self::RecoveryPending], true);
    }

    /** The operation is confirmed working again. */
    public function isRecovered(): bool
    {
        return $this === self::Recovered;
    }
}
