<?php

use App\Enums\RecoveryRetryHandler;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Maps existing recovery records onto the evidence-based status vocabulary and
 * backfills the failure type and reference ID.
 *
 * Legacy `pending` meant "recorded, not yet handled", which conflated retryable
 * failures with failures that have no retry path; the mapping splits them using
 * the record's own `is_retryable` flag rather than guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Normalise retryability first. Legacy rows claimed to be retryable while
        // carrying handlers ("generic", "export") that never re-executed anything,
        // which is what produced recovery records that reported success for work
        // that never happened. Only handlers the Recovery Center can genuinely run
        // survive, and anything else becomes explicitly non-retryable.
        $supportedHandlers = array_map(
            static fn (RecoveryRetryHandler $handler): string => $handler->value,
            RecoveryRetryHandler::cases()
        );

        DB::table('system_recovery_records')
            ->where(function ($query) use ($supportedHandlers): void {
                $query->whereNull('retry_handler')
                    ->orWhereNotIn('retry_handler', $supportedHandlers);
            })
            ->update(['retry_handler' => null, 'is_retryable' => false]);

        DB::table('system_recovery_records')
            ->select(['id', 'status', 'is_retryable', 'module', 'retry_handler', 'retry_payload'])
            ->orderBy('id')
            ->chunkById(200, function ($records): void {
                foreach ($records as $record) {
                    DB::table('system_recovery_records')
                        ->where('id', $record->id)
                        ->update([
                            'status' => $this->mapStatus($record->status, (bool) $record->is_retryable),
                            'failure_type' => $this->mapFailureType($record),
                            'reference_id' => $this->mapReferenceId($record),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Reverse mapping is best effort: the legacy vocabulary could not
        // distinguish "recorded but untouched" from "retry crashed mid-flight",
        // so both collapse back to `pending`.
        $reverse = [
            'failed' => 'pending',
            'recovery_pending' => 'in_progress',
            'retrying' => 'in_progress',
            'recovered' => 'retried',
            'recovery_failed' => 'failed_permanently',
            'not_recoverable' => 'pending',
            'resolved' => 'resolved',
        ];

        foreach ($reverse as $new => $old) {
            DB::table('system_recovery_records')->where('status', $new)->update(['status' => $old]);
        }
    }

    private function mapStatus(string $legacy, bool $isRetryable): string
    {
        return match ($legacy) {
            'pending' => $isRetryable ? 'failed' : 'not_recoverable',
            // A record left in flight has no live worker behind it, so it is a
            // failure that was never confirmed recovered.
            'in_progress' => $isRetryable ? 'failed' : 'not_recoverable',
            'retried' => 'recovered',
            'failed_permanently' => 'recovery_failed',
            'resolved' => 'resolved',
            // The old vocabulary had two human-close states that carried the same
            // operational meaning; they collapse into the single Resolved state,
            // and each record's own notes preserve which decision was taken.
            'ignored' => 'resolved',
            default => $isRetryable ? 'failed' : 'not_recoverable',
        };
    }

    private function mapFailureType(object $record): string
    {
        return match ($record->retry_handler) {
            'import' => 'import',
            'export' => 'export',
            'queue_job' => 'queue_job',
            default => match (true) {
                Str::contains((string) $record->module, ['import']) => 'import',
                Str::contains((string) $record->module, ['export', 'report']) => 'export',
                Str::contains((string) $record->module, ['queue']) => 'queue_job',
                Str::contains((string) $record->module, ['telemetry', 'integration', 'sync']) => 'integration',
                default => 'application',
            },
        };
    }

    private function mapReferenceId(object $record): ?string
    {
        $payload = json_decode((string) $record->retry_payload, true);

        if (! is_array($payload)) {
            return null;
        }

        $reference = $payload['failed_job_uuid']
            ?? $payload['job_uuid']
            ?? $payload['import_token']
            ?? null;

        return is_scalar($reference) ? Str::limit((string) $reference, 64, '') : null;
    }
};
