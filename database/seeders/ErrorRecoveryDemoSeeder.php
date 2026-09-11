<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ErrorRecoveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::active()->role(UserRole::SuperAdministrator)->oldest('id')->first();
        $admin = User::active()->role(UserRole::Administrator)->oldest('id')->first();
        $inventoryManager = User::active()->role(UserRole::InventoryManager)->oldest('id')->first();
        $warehouseStaff = User::active()->role(UserRole::WarehouseStaff)->oldest('id')->first();
        $pharmacyStaff = User::active()->role(UserRole::PharmacyStaff)->oldest('id')->first();

        // 1. Seed System Recovery Records
        $recoveryIncidents = [
            [
                'error_id' => 'REC-2026-IMP-001',
                'user_id' => $inventoryManager?->id,
                'user_snapshot' => $inventoryManager ? "{$inventoryManager->name} ({$inventoryManager->email})" : 'Pharmacy Inventory Manager',
                'module' => 'imports',
                'operation' => 'inventory_csv_import',
                'error_summary' => "Failed to import batch inventory: Missing required header 'lot_number' at row 1.",
                'exception_class' => 'League\Csv\InvalidArgumentException',
                'technical_details' => [
                    'message' => "Required header 'lot_number' is missing from uploaded inventory CSV.",
                    'code' => 0,
                    'file' => 'app/Services/DataImportService.php',
                    'line' => 142,
                    'detected_headers' => ['item_code', 'quantity', 'expiry_date', 'storage_location'],
                    'required_headers' => ['item_code', 'quantity', 'lot_number', 'expiry_date'],
                    'context' => 'Pre-import header schema verification',
                ],
                'status' => 'pending',
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => true,
                'retry_handler' => 'import',
                'retry_payload' => [
                    'file_path' => 'imports/batch_rx_quarantine_202609.csv',
                    'original_filename' => 'batch_rx_quarantine_202609.csv',
                    'rows_staged' => 120,
                ],
                'retry_count' => 0,
                'last_retried_at' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.45',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'error_id' => 'REC-2026-TXN-002',
                'user_id' => $warehouseStaff?->id,
                'user_snapshot' => $warehouseStaff ? "{$warehouseStaff->name} ({$warehouseStaff->email})" : 'Warehouse Dock Officer',
                'module' => 'inventory',
                'operation' => 'stock_movement_batch_commit',
                'error_summary' => 'Deadlock encountered during simultaneous emergency requisition fulfillment and bulk restock.',
                'exception_class' => 'Illuminate\Database\QueryException',
                'technical_details' => [
                    'message' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
                    'code' => '40001',
                    'sql' => 'update `item_stock_levels` set `quantity` = `quantity` - 50 where `item_id` = 2 and `storage_location_id` = 1',
                    'conflicting_transactions' => ['txn_emergency_er_requisition_784', 'txn_grn_dock_restock_209'],
                    'deadlock_cycles' => 1,
                ],
                'status' => 'retried',
                'strategy_applied' => 'deadlock_retry',
                'is_retryable' => true,
                'retry_handler' => 'generic',
                'retry_payload' => [
                    'affected_items' => [2, 3],
                    'source_location_id' => 1,
                    'quantity_delta' => 50,
                ],
                'retry_count' => 2,
                'last_retried_at' => now()->subHour(),
                'resolved_by_user_id' => $superAdmin?->id,
                'resolved_at' => now()->subHour(),
                'resolution_notes' => 'Deadlock retry strategy successfully re-attempted transaction with exponential backoff. Stock ledger balances fully reconciled without discrepancies.',
                'ip_address' => '192.168.10.62',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHour(),
            ],
            [
                'error_id' => 'REC-2026-EXT-003',
                'user_id' => null,
                'user_snapshot' => 'System Automated Worker (Cron Poller)',
                'module' => 'procurement',
                'operation' => 'dpri_price_sync',
                'error_summary' => 'DOH DPRI external reference price endpoint timed out (HTTP 504 Gateway Timeout).',
                'exception_class' => 'Illuminate\Http\Client\ConnectionException',
                'technical_details' => [
                    'message' => 'cURL error 28: Operation timed out after 30001 milliseconds with 0 out of 0 bytes received',
                    'endpoint' => 'https://dpri.doh.gov.ph/api/v2/prices/2026',
                    'http_code' => 504,
                    'attempt' => 3,
                    'retry_after_seconds' => 300,
                ],
                'status' => 'resolved',
                'strategy_applied' => 'safe_default',
                'is_retryable' => true,
                'retry_handler' => 'generic',
                'retry_payload' => [
                    'edition_year' => 2026,
                    'fallback_cache_used' => true,
                ],
                'retry_count' => 1,
                'last_retried_at' => now()->subHours(20),
                'resolved_by_user_id' => $superAdmin?->id,
                'resolved_at' => now()->subHours(20),
                'resolution_notes' => 'Applied local cached DPRI ceiling prices fallback table to avoid blocking active purchase orders. DOH portal connection re-established and validated.',
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHours(20),
            ],
            [
                'error_id' => 'REC-2026-EXP-004',
                'user_id' => $pharmacyStaff?->id,
                'user_snapshot' => $pharmacyStaff ? "{$pharmacyStaff->name} ({$pharmacyStaff->email})" : 'Hospital Pharmacist',
                'module' => 'reports',
                'operation' => 'export_narcotics_report',
                'error_summary' => 'Export failed during PDF rendering: Temporary storage allocation exceeded for high-resolution surgical logs.',
                'exception_class' => 'RuntimeException',
                'technical_details' => [
                    'message' => 'Temporary buffer exceeded memory ceiling (128MB) while building PDEA Annex F multi-page audit report.',
                    'allocated_memory' => '134217728 bytes',
                    'page_count_rendered' => 84,
                    'report_code' => 'PDEA_FORM_8_DANGEROUS_DRUGS',
                ],
                'status' => 'pending',
                'strategy_applied' => 'queued_for_review',
                'is_retryable' => true,
                'retry_handler' => 'export',
                'retry_payload' => [
                    'report_type' => 'pdea_narcotics_monthly',
                    'period_from' => '2026-08-01',
                    'period_to' => '2026-08-31',
                    'format' => 'pdf',
                    'split_by_vault' => true,
                ],
                'retry_count' => 0,
                'last_retried_at' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.88',
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],
            [
                'error_id' => 'REC-2026-QUE-005',
                'user_id' => null,
                'user_snapshot' => 'Laravel Queue Worker (worker-notifications-1)',
                'module' => 'queue',
                'operation' => 'process_expiry_alerts_job',
                'error_summary' => 'Failed processing queued job App\Jobs\DispatchNearExpiryStockAlerts: SMTP mailer connection refused.',
                'exception_class' => 'Symfony\Component\Mailer\Exception\TransportException',
                'technical_details' => [
                    'message' => 'Connection could not be established with host mail.hospital.local:25 :stream_socket_client(): unable to connect to mail.hospital.local:25',
                    'queue' => 'notifications',
                    'job_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                    'attempts' => 3,
                ],
                'status' => 'pending',
                'strategy_applied' => 'queued_for_review',
                'is_retryable' => true,
                'retry_handler' => 'queue_job',
                'retry_payload' => [
                    'job_id' => 1,
                    'job_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                    'queue' => 'notifications',
                ],
                'retry_count' => 0,
                'last_retried_at' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
            [
                'error_id' => 'REC-2026-IOT-006',
                'user_id' => null,
                'user_snapshot' => 'IoT Telemetry Ingestion Service',
                'module' => 'warehousing',
                'operation' => 'cold_chain_iot_telemetry',
                'error_summary' => 'Transient sensor jitter detected on Cold Storage Zone C sensor #4: Value -999.0°C outside physical range.',
                'exception_class' => 'UnexpectedValueException',
                'technical_details' => [
                    'sensor_id' => 'TEMP-WH-01-COLD-04',
                    'location' => 'Cold Chain Vaccine Refrigerator A',
                    'reported_value' => -999.0,
                    'valid_range' => [2.0, 8.0],
                    'cause' => 'Hardware telemetry bus voltage dip during battery change',
                ],
                'status' => 'ignored',
                'strategy_applied' => 'safe_default',
                'is_retryable' => false,
                'retry_handler' => null,
                'retry_payload' => null,
                'retry_count' => 0,
                'last_retried_at' => null,
                'resolved_by_user_id' => $superAdmin?->id,
                'resolved_at' => now()->subDays(2),
                'resolution_notes' => 'Confirmed telemetry spike caused by battery replacement on sensor probe. Sensor recalibrated and now transmitting nominal 4.1°C.',
                'ip_address' => '192.168.20.15',
                'created_at' => now()->subDays(2)->subHours(3),
                'updated_at' => now()->subDays(2),
            ],
            [
                'error_id' => 'REC-2026-MIG-007',
                'user_id' => $admin?->id,
                'user_snapshot' => $admin ? "{$admin->name} ({$admin->email})" : 'Hospital Administrator',
                'module' => 'imports',
                'operation' => 'legacy_excel_migration',
                'error_summary' => 'Corrupted binary payload encountered in legacy Excel worksheet: File signature mismatch.',
                'exception_class' => 'PhpOffice\PhpSpreadsheet\Reader\Exception',
                'technical_details' => [
                    'message' => 'File format signature 0x00000000 not recognized as valid BIFF8 or OpenXML archive.',
                    'filename' => 'legacy_pharmacy_archive_2022.xls',
                    'detected_size' => 4096,
                    'status' => 'corrupted_archive',
                ],
                'status' => 'failed_permanently',
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => false,
                'retry_handler' => null,
                'retry_payload' => null,
                'retry_count' => 0,
                'last_retried_at' => null,
                'resolved_by_user_id' => $superAdmin?->id,
                'resolved_at' => now()->subDays(3),
                'resolution_notes' => 'Source media was corrupted during archive extraction. Hospital records department contacted to provide clean tape backup.',
                'ip_address' => '192.168.10.12',
                'created_at' => now()->subDays(3)->subHours(5),
                'updated_at' => now()->subDays(3),
            ],
            [
                'error_id' => 'REC-2026-DIS-008',
                'user_id' => $warehouseStaff?->id,
                'user_snapshot' => $warehouseStaff ? "{$warehouseStaff->name} ({$warehouseStaff->email})" : 'Warehouse Staff',
                'module' => 'inventory',
                'operation' => 'material_requisition_issuance',
                'error_summary' => 'Concurrency conflict: Requisition was concurrently modified by another pharmacy terminal.',
                'exception_class' => 'Illuminate\Database\Eloquent\ModelNotFoundException',
                'technical_details' => [
                    'message' => 'Optimistic lock version mismatch during material requisition issuance dispatch.',
                    'requisition_code' => 'REQ-2026-0042',
                    'action' => 'issuance_dispatch',
                ],
                'status' => 'in_progress',
                'strategy_applied' => 'automatic_rollback',
                'is_retryable' => true,
                'retry_handler' => 'generic',
                'retry_payload' => [
                    'requisition_code' => 'REQ-2026-0042',
                ],
                'retry_count' => 0,
                'last_retried_at' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
                'ip_address' => '192.168.10.66',
                'created_at' => now()->subMinutes(45),
                'updated_at' => now()->subMinutes(45),
            ],
        ];

        foreach ($recoveryIncidents as $incident) {
            SystemRecoveryRecord::updateOrCreate(
                ['error_id' => $incident['error_id']],
                $incident
            );
        }

        // 2. Seed Realistic Failed Jobs in failed_jobs table
        $failedJobs = [
            [
                'uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'connection' => 'database',
                'queue' => 'notifications',
                'payload' => json_encode([
                    'uuid' => '550e8400-e29b-41d4-a716-446655440000',
                    'displayName' => 'App\\Jobs\\DispatchNearExpiryStockAlerts',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@go',
                    'maxTries' => 3,
                    'timeout' => 60,
                    'data' => ['commandName' => 'App\\Jobs\\DispatchNearExpiryStockAlerts'],
                ], JSON_THROW_ON_ERROR),
                'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection could not be established with host mail.hospital.local:25 in /var/www/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:128\nStack trace:\n#0 /var/www/app/Jobs/DispatchNearExpiryStockAlerts.php(52): Symfony\\Component\\Mailer\\Transport\\AbstractTransport->send()\n#1 /var/www/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(120): App\\Jobs\\DispatchNearExpiryStockAlerts->handle()",
                'failed_at' => now()->subHours(6),
            ],
            [
                'uuid' => '550e8400-e29b-41d4-a716-446655440001',
                'connection' => 'database',
                'queue' => 'reports',
                'payload' => json_encode([
                    'uuid' => '550e8400-e29b-41d4-a716-446655440001',
                    'displayName' => 'App\\Jobs\\GenerateMonthlyNarcoticsRegisterPdf',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@go',
                    'maxTries' => 1,
                    'timeout' => 300,
                    'data' => [
                        'commandName' => 'App\\Jobs\\GenerateMonthlyNarcoticsRegisterPdf',
                        'reportId' => 'PDEA-2026-08',
                    ],
                ], JSON_THROW_ON_ERROR),
                'exception' => "RuntimeException: Out of memory buffer (134217728 bytes) during PDF stream rendering in /var/www/app/Services/ReportGeneratorService.php:88\nStack trace:\n#0 /var/www/app/Jobs/GenerateMonthlyNarcoticsRegisterPdf.php(64): App\\Services\\ReportGeneratorService->generatePdf()\n#1 /var/www/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(120): App\\Jobs\\GenerateMonthlyNarcoticsRegisterPdf->handle()",
                'failed_at' => now()->subHours(5),
            ],
        ];

        foreach ($failedJobs as $fj) {
            DB::table('failed_jobs')->updateOrInsert(
                ['uuid' => $fj['uuid']],
                $fj
            );
        }

        // 3. Seed Audit Trail entries for system failure & recovery events
        $auditLogger = app(AuditLogger::class);

        // Check if recovery audit entries already exist to avoid duplicate logs on repeated seeding
        $existingRecoveryAuditCount = DB::table('audit_logs')
            ->where('module', 'System Recovery')
            ->count();

        if ($existingRecoveryAuditCount === 0) {
            // Failure audit log
            $auditLogger->log(
                action: AuditAction::SystemOperationFailed,
                actor: $inventoryManager,
                description: "Critical import failure rolled back automatically [Ref: REC-2026-IMP-001]. Missing required header 'lot_number'.",
                oldValues: [],
                newValues: ['error_id' => 'REC-2026-IMP-001', 'module' => 'imports', 'strategy' => 'automatic_rollback'],
                module: 'System Recovery',
                outcome: 'failure',
                targetReference: 'REC-2026-IMP-001'
            );

            // Recovery audit log
            $auditLogger->log(
                action: AuditAction::SystemOperationRecovered,
                actor: null,
                description: 'Database deadlock automatically recovered after transient backoff retry [Ref: REC-2026-TXN-002]. Stock movement committed.',
                oldValues: ['status' => 'pending', 'retry_count' => 1],
                newValues: ['status' => 'retried', 'retry_count' => 2, 'strategy' => 'deadlock_retry'],
                module: 'System Recovery',
                outcome: 'success',
                targetReference: 'REC-2026-TXN-002'
            );

            // Super Admin manual resolution audit log
            $auditLogger->log(
                action: AuditAction::TriggeredRecoveryAction,
                actor: $superAdmin,
                description: 'Super Administrator marked external API timeout as resolved [Ref: REC-2026-EXT-003]. Fallback cache deployed.',
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'resolved', 'strategy' => 'safe_default', 'notes' => 'Applied local cached DPRI ceiling prices.'],
                module: 'System Recovery',
                outcome: 'success',
                targetReference: 'REC-2026-EXT-003'
            );

            // System maintenance audit log
            $auditLogger->log(
                action: AuditAction::SystemHealthMaintenance,
                actor: $superAdmin,
                description: 'Super Administrator triggered system diagnostics cache rebuild and optimized route state.',
                oldValues: [],
                newValues: ['action' => 'cache_rebuild', 'status' => 'healthy', 'execution_time_ms' => 142.5],
                module: 'System Recovery',
                outcome: 'success'
            );
        }
    }
}
