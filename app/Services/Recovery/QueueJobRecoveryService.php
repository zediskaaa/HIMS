<?php

namespace App\Services\Recovery;

use App\Enums\AuditAction;
use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use App\Models\SystemRecoveryRecord;
use App\Services\AuditLogger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps recovery incidents in step with what the queue worker actually did.
 *
 * A queue retry can only be dispatched, not awaited, so the incident stays
 * `Recovery Pending` until the worker reports back here. The worker's verdict —
 * not the dispatch — is what makes an incident Recovered.
 */
class QueueJobRecoveryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SafeExecutionService $safeExecution,
    ) {}

    /**
     * A job failed. Either it is the first failure of a new job, or a retry that
     * was dispatched from the Recovery Center has failed again.
     */
    public function handleFailure(JobFailed $event): void
    {
        try {
            $uuid = $this->uuid($event->job);

            if ($uuid === null) {
                return;
            }

            $incident = $this->findOpenIncident($uuid);

            if ($incident === null) {
                $this->recordNewIncident($event, $uuid);

                return;
            }

            if ($incident->status->isInFlight()) {
                $this->markRetryFailed($incident, $uuid);
            }
        } catch (Throwable $e) {
            // Never let bookkeeping break the worker's failure handling.
            Log::error('Failed to reconcile a queue job failure with the Recovery Center.', [
                'exception' => $e,
            ]);
        }
    }

    /**
     * A job finished processing. If its incident was waiting on this job, the
     * incident is now genuinely recovered.
     */
    public function handleSuccess(JobProcessed $event): void
    {
        try {
            $uuid = $this->uuid($event->job);

            if ($uuid === null) {
                return;
            }

            $incident = SystemRecoveryRecord::where('reference_id', $uuid)
                ->whereIn('status', [RecoveryStatus::RecoveryPending, RecoveryStatus::Retrying])
                ->orderByDesc('id')
                ->first();

            if ($incident === null) {
                return;
            }

            $incident->forceFill([
                'status' => RecoveryStatus::Recovered,
                'last_attempt_outcome' => RecoveryAttemptOutcome::Succeeded,
                'last_attempt_error' => null,
                'resolved_at' => now(),
                'resolved_by_user_id' => null,
                'resolution_notes' => 'The queued job completed successfully after being re-queued by the Recovery Center.',
            ])->save();

            $this->auditLogger->log(
                action: AuditAction::SystemOperationRecovered,
                actor: null,
                description: sprintf(
                    'Incident [%s] recovered: queue job [%s] completed successfully after being re-queued.',
                    $incident->error_id,
                    $uuid
                ),
                target: $incident,
                targetName: $incident->error_id,
                newValues: [
                    'status' => RecoveryStatus::Recovered->value,
                    'queue_job_uuid' => $uuid,
                ],
                source: 'system',
            );
        } catch (Throwable $e) {
            Log::error('Failed to confirm a recovered queue job in the Recovery Center.', [
                'exception' => $e,
            ]);
        }
    }

    /**
     * A failed queue job is a genuine recoverable event, so it gets one incident
     * with a real retry path rather than only appearing as a counter.
     */
    private function recordNewIncident(JobFailed $event, string $uuid): void
    {
        $this->safeExecution->recordFailure(
            exception: $event->exception,
            module: 'Queue',
            operation: 'queue_job',
            context: [
                'connection' => (string) $event->connectionName,
                'queue' => (string) $event->job->getQueue(),
            ],
            isRetryable: true,
            retryHandler: RecoveryRetryHandler::QueueJob,
            retryPayload: ['failed_job_uuid' => $uuid],
            strategy: 'queue_worker_failure',
            failureType: RecoveryFailureType::QueueJob,
            affectedResource: $this->jobName($event),
            referenceId: $uuid,
        );
    }

    private function markRetryFailed(SystemRecoveryRecord $incident, string $uuid): void
    {
        // The outstanding attempt was dispatched and the worker has now reported
        // its verdict, so the attempt's outcome is the worker's, not the dispatch's.
        $incident->forceFill([
            'status' => RecoveryStatus::RecoveryFailed,
            'last_attempt_outcome' => RecoveryAttemptOutcome::Failed,
            'last_attempt_error' => 'The job failed again when the worker ran it. Review the job error on the health panel before retrying.',
        ])->save();

        $this->auditLogger->log(
            action: AuditAction::TriggeredRecoveryAction,
            actor: null,
            description: sprintf(
                'Recovery attempt for incident [%s] failed: queue job [%s] failed again when the worker ran it.',
                $incident->error_id,
                $uuid
            ),
            target: $incident,
            targetName: $incident->error_id,
            newValues: [
                'status' => RecoveryStatus::RecoveryFailed->value,
                'queue_job_uuid' => $uuid,
            ],
            outcome: 'failure',
            source: 'system',
        );
    }

    /**
     * The incident awaiting this job, if any. The lookup is served by the index
     * on `reference_id`, so it stays a single index seek on the hot path.
     */
    private function findOpenIncident(string $uuid): ?SystemRecoveryRecord
    {
        return SystemRecoveryRecord::where('reference_id', $uuid)
            ->whereIn('status', [
                RecoveryStatus::Failed,
                RecoveryStatus::RecoveryPending,
                RecoveryStatus::Retrying,
                RecoveryStatus::RecoveryFailed,
            ])
            ->orderByDesc('id')
            ->first();
    }

    private function uuid(object $job): ?string
    {
        $uuid = $job->uuid();

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function jobName(JobFailed $event): ?string
    {
        try {
            $name = $event->job->resolveName();

            // Named the same way the health panel names failed jobs, so one job
            // reads identically everywhere it appears in the Recovery Center.
            return is_string($name) && $name !== '' ? class_basename($name) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
