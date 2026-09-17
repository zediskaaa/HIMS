<?php

namespace App\Services\Recovery;

use App\Enums\AuditAction;
use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use App\Exceptions\RecoveryNotRetryableException;
use App\Models\SystemRecoveryAttempt;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Import\DataImportExecutor;
use App\Services\Import\ImportStagingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Re-executes a failed operation and records what actually happened.
 *
 * The service never reports a recovery it did not observe: every handler either
 * re-runs the operation and verifies the result, hands the work to a worker and
 * says so, or declines with a reason. Attempts are appended to a ledger so the
 * original failure stays intact.
 */
class SmartRetryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ImportStagingService $stagingService,
        private readonly DataImportExecutor $importExecutor,
    ) {}

    /**
     * @throws RecoveryNotRetryableException when the incident cannot be retried
     */
    public function retry(SystemRecoveryRecord $record, User $actor): RecoveryOutcome
    {
        $claimed = $this->claim($record, $actor);
        $startedAt = microtime(true);

        try {
            $outcome = $this->runHandler($claimed, $actor);
        } catch (Throwable $e) {
            // A handler blowing up is a failed attempt, not a lost incident.
            // Full detail goes to the log; the operator sees a safe message.
            Log::error('Recovery attempt raised an unexpected exception.', [
                'recovery_record_id' => $claimed->getKey(),
                'error_id' => $claimed->error_id,
                'retry_handler' => $claimed->retry_handler?->value,
                'exception' => $e,
            ]);

            $outcome = RecoveryOutcome::failed($this->safeMessage($e));
        }

        return $this->settle($claimed, $actor, $outcome, (int) round((microtime(true) - $startedAt) * 1000));
    }

    /**
     * Atomically take ownership of the incident so two operators cannot run the
     * same retry, and so a retry cannot start from a state that forbids it.
     *
     * @throws RecoveryNotRetryableException
     */
    private function claim(SystemRecoveryRecord $record, User $actor): SystemRecoveryRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $locked = SystemRecoveryRecord::whereKey($record->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new RecoveryNotRetryableException('This incident no longer exists.');
            }

            if (! $locked->canRetry()) {
                throw new RecoveryNotRetryableException(
                    $locked->retryBlockedReason() ?? 'This incident cannot be retried in its current state.'
                );
            }

            $locked->forceFill([
                'status' => RecoveryStatus::Retrying,
                'retry_count' => $locked->retry_count + 1,
                'last_retried_at' => now(),
            ])->save();

            $this->audit(
                action: AuditAction::TriggeredRecoveryAction,
                actor: $actor,
                record: $locked,
                description: sprintf(
                    'Recovery attempt #%d started for incident [%s] (%s in %s) using the %s handler.',
                    $locked->retry_count,
                    $locked->error_id,
                    $locked->operation,
                    $locked->module,
                    $locked->retry_handler?->label() ?? 'unknown'
                ),
                newValues: [
                    'status' => RecoveryStatus::Retrying->value,
                    'retry_count' => $locked->retry_count,
                    'handler' => $locked->retry_handler?->value,
                ],
                outcome: 'success'
            );

            return $locked;
        });
    }

    private function runHandler(SystemRecoveryRecord $record, User $actor): RecoveryOutcome
    {
        return match ($record->retry_handler) {
            RecoveryRetryHandler::Import => $this->retryImport($record, $actor),
            RecoveryRetryHandler::QueueJob => $this->retryQueueJob($record),
            null => RecoveryOutcome::skipped('No recovery handler is registered for this operation.'),
        };
    }

    /**
     * Replays a staged import. The staging payload is only discarded once the
     * executor reports that every staged row was written, so a failed replay can
     * still be attempted again and a successful one cannot be applied twice.
     */
    private function retryImport(SystemRecoveryRecord $record, User $actor): RecoveryOutcome
    {
        $payload = $record->retry_payload ?? [];
        $token = $payload['import_token'] ?? null;
        $target = $payload['target'] ?? null;

        if (! is_string($token) || $token === '' || ! is_string($target) || $target === '') {
            return RecoveryOutcome::skipped(
                'The recorded retry payload does not identify a staged import, so nothing can be replayed.'
            );
        }

        $staged = $this->stagingService->retrieve($token, (int) ($record->user_id ?? $actor->getKey()))
            ?? $this->stagingService->retrieve($token, (int) $actor->getKey());

        if ($staged === null || empty($staged['records'])) {
            return RecoveryOutcome::skipped(
                'The staged import data has expired or was already consumed. Re-upload the source file and run the import again.'
            );
        }

        if (($staged['target'] ?? null) !== $target) {
            return RecoveryOutcome::skipped(
                'The staged import no longer matches the target recorded on this incident.'
            );
        }

        $expected = count($staged['records']);

        // DataImportExecutor commits in a single transaction, so a partial apply
        // is impossible; a short total still means the replay did not take.
        $result = $this->importExecutor->execute($target, $staged['records'], $actor);
        $written = (int) ($result['total'] ?? 0);

        if ($written !== $expected) {
            return RecoveryOutcome::failed(sprintf(
                'The replay wrote %d of %d staged rows, so the import did not fully apply.',
                $written,
                $expected
            ));
        }

        $this->stagingService->forget($token);

        return RecoveryOutcome::succeeded(sprintf(
            'Replayed the staged import: %d row(s) written to %s.',
            $written,
            $target
        ));
    }

    /**
     * Pushes a failed queue job back onto its queue.
     *
     * The job's result is not observable from here, so a successful push is
     * reported as dispatched rather than recovered; the queue listeners settle
     * the incident when the worker actually processes or fails the job.
     */
    private function retryQueueJob(SystemRecoveryRecord $record): RecoveryOutcome
    {
        $payload = $record->retry_payload ?? [];
        $identifier = $payload['failed_job_uuid']
            ?? $payload['job_uuid']
            ?? $payload['job_id']
            ?? $record->reference_id;

        if (! is_scalar($identifier) || (string) $identifier === '') {
            return RecoveryOutcome::skipped('This incident does not record which failed job to retry.');
        }

        $identifier = (string) $identifier;
        $failedJob = DB::table('failed_jobs')->where('uuid', $identifier)->first();

        if ($failedJob === null && ctype_digit($identifier)) {
            $failedJob = DB::table('failed_jobs')->where('id', (int) $identifier)->first();
        }

        if ($failedJob === null) {
            return RecoveryOutcome::skipped(
                'This job is no longer in the failed jobs table, so it was already retried or removed. Verify the operation directly before closing this incident.'
            );
        }

        $uuid = (string) $failedJob->uuid;

        // queue:retry returns no value, so the dispatch is verified by observing
        // that the failed record was consumed. If the job fails again the row
        // reappears under the same UUID, which correctly reads as dispatched for
        // an asynchronous worker and as an immediate re-failure for a sync queue.
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        if (DB::table('failed_jobs')->where('uuid', $uuid)->exists()) {
            return RecoveryOutcome::failed(
                'The queue did not release this job from the failed jobs table, so it was not re-queued.'
            );
        }

        return RecoveryOutcome::dispatched(sprintf(
            'Queue job [%s] was pushed back onto the %s queue. Its outcome is confirmed once the worker processes it.',
            $uuid,
            (string) ($failedJob->queue ?? 'default')
        ));
    }

    /**
     * Persist the attempt and the incident state it produced, then audit it.
     *
     * The incident is re-read under a lock because a queue listener can settle
     * it while the handler is still running (a synchronous queue executes the
     * job inline), and a confirmed worker verdict outranks a dispatch.
     */
    private function settle(
        SystemRecoveryRecord $record,
        User $actor,
        RecoveryOutcome $outcome,
        int $durationMs
    ): RecoveryOutcome {
        $attemptNumber = $record->retry_count;

        $effective = DB::transaction(function () use ($record, $actor, $outcome, $attemptNumber, $durationMs): RecoveryOutcome {
            $current = SystemRecoveryRecord::whereKey($record->getKey())->lockForUpdate()->first();

            if ($current === null) {
                return $outcome;
            }

            $outcome = $this->honourWorkerVerdict($outcome, $current);

            $message = Str::limit($outcome->message, 500, '...');
            $recovered = $outcome->resultingStatus === RecoveryStatus::Recovered;

            SystemRecoveryAttempt::create([
                'system_recovery_record_id' => $current->getKey(),
                'attempt_number' => $attemptNumber,
                'outcome' => $outcome->outcome,
                'handler' => $current->retry_handler,
                'message' => $message,
                'actor_user_id' => $actor->getKey(),
                'actor_snapshot' => $this->actorSnapshot($actor),
                'duration_ms' => $durationMs,
            ]);

            // The original failure fields (error_summary, technical_details,
            // exception_class) are deliberately left untouched so the incident
            // still describes what first went wrong.
            $current->forceFill([
                'status' => $outcome->resultingStatus,
                'last_attempt_outcome' => $outcome->outcome,
                'last_attempt_error' => in_array($outcome->outcome, [RecoveryAttemptOutcome::Failed, RecoveryAttemptOutcome::Skipped], true)
                    ? $message
                    : null,
                'resolved_at' => $recovered ? ($current->resolved_at ?? now()) : $current->resolved_at,
                'resolved_by_user_id' => $recovered ? ($current->resolved_by_user_id ?? $actor->getKey()) : $current->resolved_by_user_id,
                'resolution_notes' => $recovered
                    ? ($current->resolution_notes ?? sprintf('Recovered by %s on attempt #%d.', $actor->name, $attemptNumber))
                    : $current->resolution_notes,
            ])->save();

            return $outcome;
        });

        $record->refresh();

        $this->recordAttemptAudit($record, $actor, $effective, $attemptNumber);

        return $effective;
    }

    /**
     * A dispatch says only that the job was re-queued. If the worker has since
     * reported what actually happened, that verdict wins.
     */
    private function honourWorkerVerdict(RecoveryOutcome $outcome, SystemRecoveryRecord $current): RecoveryOutcome
    {
        if ($outcome->resultingStatus !== RecoveryStatus::RecoveryPending) {
            return $outcome;
        }

        return match ($current->status) {
            RecoveryStatus::Recovered => RecoveryOutcome::succeeded(
                'The queue worker ran the job and it completed successfully.'
            ),
            RecoveryStatus::RecoveryFailed => RecoveryOutcome::failed(
                'The queue worker ran the job and it failed again.'
            ),
            default => $outcome,
        };
    }

    private function recordAttemptAudit(
        SystemRecoveryRecord $record,
        User $actor,
        RecoveryOutcome $outcome,
        int $attemptNumber
    ): void {
        $verifiedRecovery = $outcome->outcome === RecoveryAttemptOutcome::Succeeded;

        $this->audit(
            action: $verifiedRecovery ? AuditAction::SystemOperationRecovered : AuditAction::TriggeredRecoveryAction,
            actor: $actor,
            record: $record,
            description: $verifiedRecovery
                ? sprintf(
                    'Incident [%s] recovered: attempt #%d verified the %s operation succeeded. %s',
                    $record->error_id,
                    $attemptNumber,
                    $record->operation,
                    $outcome->message
                )
                : sprintf(
                    'Recovery attempt #%d for incident [%s] ended as %s. %s',
                    $attemptNumber,
                    $record->error_id,
                    $outcome->outcome->label(),
                    $outcome->message
                ),
            newValues: [
                'status' => $outcome->resultingStatus->value,
                'attempt' => $attemptNumber,
                'attempt_outcome' => $outcome->outcome->value,
                'handler' => $record->retry_handler?->value,
            ],
            outcome: in_array($outcome->outcome, [RecoveryAttemptOutcome::Succeeded, RecoveryAttemptOutcome::Dispatched], true)
                ? 'success'
                : 'failure'
        );
    }

    private function audit(
        AuditAction $action,
        User $actor,
        SystemRecoveryRecord $record,
        string $description,
        array $newValues,
        string $outcome
    ): void {
        try {
            $this->auditLogger->log(
                action: $action,
                actor: $actor,
                description: $description,
                target: $record,
                targetName: $record->error_id,
                oldValues: [],
                newValues: $newValues,
                outcome: $outcome
            );
        } catch (Throwable $e) {
            // The attempt already committed; losing the audit row must not
            // rewrite the recovery outcome, but it must be visible to IT.
            Log::error('Failed to write a recovery audit entry.', [
                'recovery_record_id' => $record->getKey(),
                'error_id' => $record->error_id,
                'exception_class' => $e::class,
            ]);
        }
    }

    private function actorSnapshot(User $actor): string
    {
        return sprintf(
            '%s (%s, %s)',
            $actor->name,
            $actor->employee_id ?? 'No ID',
            $actor->role?->label() ?? 'Unknown'
        );
    }

    /**
     * Turn an unexpected exception into an operator-safe sentence. Internal
     * messages can carry SQL, paths, or connection detail, so they stay in the log.
     */
    private function safeMessage(Throwable $e): string
    {
        return sprintf(
            'The retry could not be completed (%s). Technical details were written to the application log.',
            class_basename($e)
        );
    }
}
