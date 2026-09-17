<?php

namespace Database\Seeders;

use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use App\Enums\UserRole;
use App\Models\SystemRecoveryAttempt;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sample recovery incidents for demonstration and local development.
 *
 * Every record here uses the same status vocabulary, retry handlers, and
 * technical-detail shape that the running system writes, so the Recovery Center
 * renders these exactly as it renders a real incident. Nothing is written to the
 * Audit Trail: that log is append-only and records what people actually did, so
 * seeding invented entries there would misattribute actions to real accounts.
 * Real recovery actions produce their own audit entries at runtime.
 *
 * Guarded against production and idempotent, so running it twice changes nothing.
 */
class ErrorRecoveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Recovery Center demonstration incidents are disabled in production.');
        }

        $superAdmin = User::active()->role(UserRole::SuperAdministrator)->oldest('id')->first();
        $inventoryManager = User::active()->role(UserRole::InventoryManager)->oldest('id')->first();
        $warehouseStaff = User::active()->role(UserRole::WarehouseStaff)->oldest('id')->first();
        $pharmacyStaff = User::active()->role(UserRole::PharmacyStaff)->oldest('id')->first();

        // The one live failed job the queue retry path can genuinely re-dispatch.
        // Its UUID is the incident's reference ID, which is what the retry handler
        // looks the row up by.
        $failedJobUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->seedFailedJob($failedJobUuid);

        $incidents = $this->incidents(
            $superAdmin,
            $inventoryManager,
            $warehouseStaff,
            $pharmacyStaff,
            $failedJobUuid,
        );

        foreach ($incidents as $incident) {
            SystemRecoveryRecord::updateOrCreate(
                ['error_id' => $incident['error_id']],
                $incident
            );
        }

        $this->seedAttempts($superAdmin);
    }

    /**
     * A failed job row with no incident of its own is dead weight, so exactly one
     * is seeded and it is wired to the queue incident below.
     */
    private function seedFailedJob(string $uuid): void
    {
        DB::table('failed_jobs')->updateOrInsert(
            ['uuid' => $uuid],
            [
                'connection' => 'database',
                'queue' => 'notifications',
                'payload' => json_encode([
                    'uuid' => $uuid,
                    'displayName' => 'Illuminate\\Notifications\\SendQueuedNotifications',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@go',
                    'maxTries' => 3,
                    'timeout' => 60,
                    'data' => ['commandName' => 'Illuminate\\Notifications\\SendQueuedNotifications'],
                ], JSON_THROW_ON_ERROR),
                'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection could not be established with host smtp.hospital.local:587\nStack trace:\n#0 /var/www/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php(128): stream_socket_client()\n#1 /var/www/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(120): Illuminate\\Notifications\\SendQueuedNotifications->handle()",
                'failed_at' => now()->subHours(6),
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function incidents(
        ?User $superAdmin,
        ?User $inventoryManager,
        ?User $warehouseStaff,
        ?User $pharmacyStaff,
        string $failedJobUuid,
    ): array {
        // A staged import token that no longer resolves. The retry handler reports
        // that truthfully rather than replaying anything, which is the same path a
        // real expired import takes.
        $lapsedImportToken = (string) Str::uuid();

        return [
            [
                'error_id' => 'REC-2026-IMP-001',
                'user_id' => $inventoryManager?->id,
                'user_snapshot' => $this->snapshot($inventoryManager, 'Pharmacy Inventory Manager'),
                'module' => 'Imports',
                'failure_type' => RecoveryFailureType::Import,
                'operation' => 'data_import',
                'error_summary' => "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'lot_number' cannot be null (connection: mysql, sql: insert into `item_batches`)",
                'affected_resource' => 'inventory items',
                'reference_id' => $lapsedImportToken,
                'exception_class' => 'Illuminate\Database\QueryException',
                'technical_details' => [
                    'message' => "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'lot_number' cannot be null",
                    'file' => 'app/Services/Import/DataImportExecutor.php',
                    'line' => 74,
                    'code' => '23000',
                    'context' => ['target' => 'items', 'staged_rows' => 120],
                    'url' => 'http://hims.test/api/v1/inventory/import/commit',
                    'method' => 'POST',
                ],
                'status' => RecoveryStatus::Failed,
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => true,
                'retry_handler' => RecoveryRetryHandler::Import,
                'retry_payload' => ['import_token' => $lapsedImportToken, 'target' => 'items'],
                'retry_count' => 0,
                'last_retried_at' => null,
                'last_attempt_outcome' => null,
                'last_attempt_error' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.45',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'error_id' => 'REC-2026-QUE-002',
                'user_id' => null,
                'user_snapshot' => 'System / Automated',
                'module' => 'Queue',
                'failure_type' => RecoveryFailureType::QueueJob,
                'operation' => 'queue_job',
                'error_summary' => 'Connection could not be established with host smtp.hospital.local:587',
                'affected_resource' => 'SendQueuedNotifications',
                'reference_id' => $failedJobUuid,
                'exception_class' => 'Symfony\Component\Mailer\Exception\TransportException',
                'technical_details' => [
                    'message' => 'Connection could not be established with host smtp.hospital.local:587',
                    'context' => ['connection' => 'database', 'queue' => 'notifications'],
                    'method' => 'CLI',
                ],
                'status' => RecoveryStatus::Failed,
                'strategy_applied' => 'queue_worker_failure',
                'is_retryable' => true,
                'retry_handler' => RecoveryRetryHandler::QueueJob,
                'retry_payload' => ['failed_job_uuid' => $failedJobUuid],
                'retry_count' => 0,
                'last_retried_at' => null,
                'last_attempt_outcome' => null,
                'last_attempt_error' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
            [
                'error_id' => 'REC-2026-IMP-003',
                'user_id' => $warehouseStaff?->id,
                'user_snapshot' => $this->snapshot($warehouseStaff, 'Warehouse Dock Officer'),
                'module' => 'Imports',
                'failure_type' => RecoveryFailureType::Import,
                'operation' => 'data_import',
                'error_summary' => "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'storage_location' in 'field list'",
                'affected_resource' => 'storage locations',
                'reference_id' => 'b7c1e4a2-5f38-4d19-9c60-2a7e8f1b3d40',
                'exception_class' => 'Illuminate\Database\QueryException',
                'technical_details' => [
                    'message' => "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'storage_location' in 'field list'",
                    'file' => 'app/Services/Import/DataImportExecutor.php',
                    'line' => 118,
                    'code' => '42S22',
                    'context' => ['target' => 'locations', 'staged_rows' => 24],
                    'url' => 'http://hims.test/api/v1/inventory/import/commit',
                    'method' => 'POST',
                ],
                'status' => RecoveryStatus::RecoveryFailed,
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => true,
                'retry_handler' => RecoveryRetryHandler::Import,
                'retry_payload' => ['import_token' => 'b7c1e4a2-5f38-4d19-9c60-2a7e8f1b3d40', 'target' => 'locations'],
                'retry_count' => 2,
                'last_retried_at' => now()->subMinutes(90),
                'last_attempt_outcome' => RecoveryAttemptOutcome::Failed,
                'last_attempt_error' => 'The staged import data has expired or was already consumed. Re-upload the source file and run the import again.',
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.62',
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subMinutes(90),
            ],
            [
                'error_id' => 'REC-2026-QUE-004',
                'user_id' => null,
                'user_snapshot' => 'System / Automated',
                'module' => 'Queue',
                'failure_type' => RecoveryFailureType::QueueJob,
                'operation' => 'queue_job',
                'error_summary' => 'Maximum execution time of 60 seconds exceeded while rendering the near-expiry alert digest',
                'affected_resource' => 'SendQueuedNotifications',
                'reference_id' => 'c8f2a1d3-6e49-4b28-8a71-3c9d2e5f7a16',
                'exception_class' => 'Symfony\Component\Process\Exception\ProcessTimedOutException',
                'technical_details' => [
                    'message' => 'Maximum execution time of 60 seconds exceeded',
                    'context' => ['connection' => 'database', 'queue' => 'notifications'],
                    'method' => 'CLI',
                ],
                // A queued job was re-dispatched and the worker confirmed it ran.
                'status' => RecoveryStatus::Recovered,
                'strategy_applied' => 'queue_worker_failure',
                'is_retryable' => true,
                'retry_handler' => RecoveryRetryHandler::QueueJob,
                'retry_payload' => ['failed_job_uuid' => 'c8f2a1d3-6e49-4b28-8a71-3c9d2e5f7a16'],
                'retry_count' => 1,
                'last_retried_at' => now()->subHours(22),
                'last_attempt_outcome' => RecoveryAttemptOutcome::Dispatched,
                'last_attempt_error' => null,
                'resolved_by_user_id' => $superAdmin?->id,
                'resolved_at' => now()->subHours(21),
                'resolution_notes' => sprintf('Recovered by %s on attempt #1.', $superAdmin?->name ?? 'Super Administrator'),
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHours(21),
            ],
            [
                'error_id' => 'REC-2026-QUE-005',
                'user_id' => null,
                'user_snapshot' => 'System / Automated',
                'module' => 'Queue',
                'failure_type' => RecoveryFailureType::QueueJob,
                'operation' => 'queue_job',
                'error_summary' => 'Deadlock found when trying to get lock while writing the stock alert digest',
                'affected_resource' => 'SendQueuedNotifications',
                'reference_id' => 'd4a7b9c0-1f52-4e83-9b26-5a8c7d3e0f91',
                'exception_class' => 'Illuminate\Database\QueryException',
                'technical_details' => [
                    'message' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock',
                    'context' => ['connection' => 'database', 'queue' => 'notifications'],
                    'method' => 'CLI',
                ],
                // Re-dispatched and handed to a worker; the outcome is not yet
                // observable, so the incident stays open until the worker reports.
                'status' => RecoveryStatus::RecoveryPending,
                'strategy_applied' => 'queue_worker_failure',
                'is_retryable' => true,
                'retry_handler' => RecoveryRetryHandler::QueueJob,
                'retry_payload' => ['failed_job_uuid' => 'd4a7b9c0-1f52-4e83-9b26-5a8c7d3e0f91'],
                'retry_count' => 1,
                'last_retried_at' => now()->subMinutes(12),
                'last_attempt_outcome' => RecoveryAttemptOutcome::Dispatched,
                'last_attempt_error' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subMinutes(12),
            ],
            [
                'error_id' => 'REC-2026-TXN-006',
                'user_id' => $warehouseStaff?->id,
                'user_snapshot' => $this->snapshot($warehouseStaff, 'Warehouse Dock Officer'),
                'module' => 'Inventory',
                'failure_type' => RecoveryFailureType::Database,
                'operation' => 'stock_movement',
                'error_summary' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
                'affected_resource' => 'item_stock_levels #2 at location #1',
                'reference_id' => 'REQ-2026-0042',
                'exception_class' => 'Illuminate\Database\QueryException',
                'technical_details' => [
                    'message' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
                    'file' => 'app/Services/Inventory/StockMovementService.php',
                    'line' => 213,
                    'code' => '40001',
                    'context' => ['item_id' => 2, 'storage_location_id' => 1, 'quantity_delta' => -50],
                    'url' => 'http://hims.test/inventory/requisitions/REQ-2026-0042/issue',
                    'method' => 'POST',
                ],
                // The transaction rolled back and there is no safe way to replay a
                // movement whose originating request has already ended.
                'status' => RecoveryStatus::NotRecoverable,
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => false,
                'retry_handler' => null,
                'retry_payload' => null,
                'retry_count' => 0,
                'last_retried_at' => null,
                'last_attempt_outcome' => null,
                'last_attempt_error' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.62',
                'created_at' => now()->subHours(8),
                'updated_at' => now()->subHours(8),
            ],
            [
                'error_id' => 'REC-2026-EXT-007',
                'user_id' => null,
                'user_snapshot' => 'System / Automated',
                'module' => 'Procurement',
                'failure_type' => RecoveryFailureType::Integration,
                'operation' => 'dpri_price_sync',
                'error_summary' => 'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received',
                'affected_resource' => 'DOH DPRI price reference endpoint',
                'reference_id' => 'DPRI-2026',
                'exception_class' => 'Illuminate\Http\Client\ConnectionException',
                'technical_details' => [
                    'message' => 'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received',
                    'file' => 'app/Services/Procurement/DpriPriceSyncService.php',
                    'line' => 96,
                    'code' => 28,
                    'context' => ['http_code' => 504, 'edition_year' => 2026],
                    'method' => 'CLI',
                ],
                'status' => RecoveryStatus::Resolved,
                'strategy_applied' => 'safe_default',
                'is_retryable' => false,
                'retry_handler' => null,
                'retry_payload' => null,
                'retry_count' => 0,
                'last_retried_at' => null,
                'last_attempt_outcome' => null,
                'last_attempt_error' => null,
                'resolved_by_user_id' => $superAdmin?->id,
                'resolved_at' => now()->subHours(20),
                'resolution_notes' => 'Confirmed the DOH portal was reachable again and the cached ceiling prices matched the published edition. Closed without a retry because the scheduled sync had already refreshed the prices.',
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHours(20),
            ],
            [
                'error_id' => 'REC-2026-EXP-008',
                'user_id' => $pharmacyStaff?->id,
                'user_snapshot' => $this->snapshot($pharmacyStaff, 'Hospital Pharmacist'),
                'module' => 'Reports',
                'failure_type' => RecoveryFailureType::Export,
                'operation' => 'report_export',
                'error_summary' => 'Allowed memory size of 134217728 bytes exhausted while rendering the dangerous drugs register',
                'affected_resource' => 'PDEA Form 8 dangerous drugs register',
                'reference_id' => 'PDEA-2026-08',
                'exception_class' => 'Symfony\Component\ErrorHandler\Error\FatalError',
                'technical_details' => [
                    'message' => 'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
                    'file' => 'app/Services/Reports/DangerousDrugsRegisterBuilder.php',
                    'line' => 158,
                    'code' => 1,
                    'context' => ['report_code' => 'PDEA_FORM_8', 'period' => '2026-08', 'pages_rendered' => 84],
                    'url' => 'http://hims.test/reports/dangerous-drugs/export',
                    'method' => 'POST',
                ],
                // Export generation has no safe replay path: the export stream is
                // tied to the request that asked for it.
                'status' => RecoveryStatus::NotRecoverable,
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => false,
                'retry_handler' => null,
                'retry_payload' => null,
                'retry_count' => 0,
                'last_retried_at' => null,
                'last_attempt_outcome' => null,
                'last_attempt_error' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.88',
                'created_at' => now()->subHours(11),
                'updated_at' => now()->subHours(11),
            ],
        ];
    }

    /**
     * The attempt ledger for the incidents that were retried. Written with
     * firstOrCreate so a repeated seed cannot duplicate history.
     */
    private function seedAttempts(?User $superAdmin): void
    {
        $ledger = [
            'REC-2026-IMP-003' => [
                [
                    'attempt_number' => 1,
                    'outcome' => RecoveryAttemptOutcome::Failed,
                    'message' => 'The staged import data has expired or was already consumed. Re-upload the source file and run the import again.',
                    'duration_ms' => 8,
                ],
                [
                    'attempt_number' => 2,
                    'outcome' => RecoveryAttemptOutcome::Failed,
                    'message' => 'The staged import data has expired or was already consumed. Re-upload the source file and run the import again.',
                    'duration_ms' => 6,
                ],
            ],
            'REC-2026-QUE-004' => [
                [
                    'attempt_number' => 1,
                    'outcome' => RecoveryAttemptOutcome::Dispatched,
                    'message' => 'Queue job [c8f2a1d3-6e49-4b28-8a71-3c9d2e5f7a16] was pushed back onto the notifications queue. Its outcome is confirmed once the worker processes it.',
                    'duration_ms' => 412,
                ],
            ],
            'REC-2026-QUE-005' => [
                [
                    'attempt_number' => 1,
                    'outcome' => RecoveryAttemptOutcome::Dispatched,
                    'message' => 'Queue job [d4a7b9c0-1f52-4e83-9b26-5a8c7d3e0f91] was pushed back onto the notifications queue. Its outcome is confirmed once the worker processes it.',
                    'duration_ms' => 388,
                ],
            ],
        ];

        foreach ($ledger as $errorId => $attempts) {
            $record = SystemRecoveryRecord::where('error_id', $errorId)->first();

            if ($record === null) {
                continue;
            }

            // A recovery attempt can only be run by a Super Administrator, so that
            // is the actor the ledger attributes these to.
            $occurredAt = $record->last_retried_at ?? $record->created_at;

            foreach ($attempts as $attempt) {
                $entry = SystemRecoveryAttempt::firstOrCreate(
                    [
                        'system_recovery_record_id' => $record->getKey(),
                        'attempt_number' => $attempt['attempt_number'],
                    ],
                    [
                        'outcome' => $attempt['outcome'],
                        'handler' => $record->retry_handler,
                        'message' => $attempt['message'],
                        'actor_user_id' => $superAdmin?->getKey(),
                        'actor_snapshot' => $this->snapshot($superAdmin, 'System / Automated'),
                        'duration_ms' => $attempt['duration_ms'],
                    ]
                );

                // Timestamps are not mass assignable, so the attempt is placed at
                // the time the incident records for its retry rather than at seed
                // time.
                if ($entry->wasRecentlyCreated) {
                    $entry->forceFill(['created_at' => $occurredAt, 'updated_at' => $occurredAt])->save();
                }
            }
        }
    }

    private function snapshot(?User $user, string $fallback): string
    {
        if ($user === null) {
            return $fallback;
        }

        return sprintf(
            '%s (%s, %s)',
            $user->name,
            $user->employee_id ?? 'No ID',
            $user->role?->label() ?? 'Unknown'
        );
    }
}
