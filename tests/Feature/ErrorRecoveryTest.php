<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Exceptions\SafeOperationException;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\Recovery\SafeExecutionService;
use App\Services\Recovery\SmartRetryService;
use App\Services\Recovery\SystemHealthService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdministrator()->create([
            'name' => 'Super Admin Officer',
            'employee_id' => 'EMP-0001',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->administrator()->create([
            'name' => 'IT Administrator',
            'employee_id' => 'EMP-0002',
        ]);
    }

    private function inventoryManager(): User
    {
        return User::factory()->inventoryManager()->create([
            'name' => 'Pharmacy Custodian',
            'employee_id' => 'EMP-0003',
        ]);
    }

    public function test_guest_is_redirected_to_login_when_accessing_recovery_center(): void
    {
        $response = $this->get('/admin/recovery');
        $response->assertRedirect(route('super-admin.login'));

        $superResponse = $this->get('/super-admin/recovery');
        $superResponse->assertRedirect(route('super-admin.login'));
    }

    public function test_regular_administrator_cannot_access_recovery_center(): void
    {
        $admin = $this->admin();
        $record = SystemRecoveryRecord::create([
            'error_id' => 'REC-AUTH-001',
            'module' => 'inventory',
            'operation' => 'stock_movement',
            'error_summary' => 'Simulated failure',
            'status' => 'pending',
            'is_retryable' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/recovery');
        $response->assertForbidden();

        $retryResponse = $this->actingAs($admin)->post("/admin/recovery/{$record->id}/retry");
        $retryResponse->assertForbidden();
    }

    public function test_inventory_manager_cannot_access_recovery_center(): void
    {
        $manager = $this->inventoryManager();

        $response = $this->actingAs($manager)->get('/admin/recovery');
        $response->assertForbidden();
    }

    public function test_super_administrator_can_view_recovery_center(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get('/admin/recovery');

        $response->assertOk();
        $response->assertSee('System Recovery Center');
        $response->assertSee('Total Incidents');
        $response->assertSee('System Health Telemetry');
    }

    public function test_super_administrator_can_view_system_health_diagnostics(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get('/admin/recovery/health');

        $response->assertOk();
        $response->assertSee('System Health Diagnostics');
        $response->assertSee('Database Engine');
        $response->assertSee('Queue &amp; Background Pipeline', false);
        $response->assertSee('Storage / Disks');
        $response->assertSee('Application Cache');
    }

    public function test_safe_execution_service_rolls_back_database_writes_on_exception(): void
    {
        $service = app(SafeExecutionService::class);
        $category = ItemCategory::create([
            'name' => 'Medicines',
            'code' => 'MED',
            'is_active' => true,
        ]);

        $this->assertSame(0, InventoryItem::count());

        try {
            $service->executeTransaction(
                module: 'inventory',
                operation: 'stock_movement',
                callback: function () use ($category) {
                    InventoryItem::create([
                        'sku' => 'TEST-SKU-999',
                        'name' => 'Failing Item',
                        'item_category_id' => $category->id,
                        'unit_of_measure' => 'piece',
                        'unit_cost' => 150.00,
                        'reorder_level' => 10,
                    ]);

                    throw new RuntimeException('Intentional simulated database transaction failure.');
                },
                context: ['test_context' => 'active']
            );

            $this->fail('SafeExecutionService should have rethrown a SafeOperationException.');
        } catch (SafeOperationException $e) {
            $this->assertStringContainsString('inventory movement could not be recorded', $e->getMessage());
            $this->assertStringStartsWith('REC-', $e->errorId);
        }

        // Assert 100% rollback occurred: 0 items persisted
        $this->assertSame(0, InventoryItem::count());

        // Assert system recovery record was stored
        $this->assertSame(1, SystemRecoveryRecord::count());
        $record = SystemRecoveryRecord::first();
        $this->assertSame('inventory', $record->module);
        $this->assertSame('stock_movement', $record->operation);
        $this->assertSame('pending', $record->status);
        $this->assertSame('automatic_rollback', $record->strategy_applied);
        $this->assertStringContainsString('Intentional simulated', $record->error_summary);

        // Assert audit trail captured SystemOperationFailed
        $auditLog = AuditLog::where('action', AuditAction::SystemOperationFailed)->first();
        $this->assertNotNull($auditLog);
        $this->assertSame('failure', $auditLog->outcome);
        $this->assertSame('System Recovery', $auditLog->module);
    }

    public function test_safe_operation_exception_renders_safe_500_view_with_reference_id(): void
    {
        Route::get('/_test/error-recovery/safe-crash', function () {
            throw new SafeOperationException(
                message: 'An unexpected transaction error occurred.',
                errorId: 'REC-TEST-SAFE-500',
                module: 'inventory',
                operation: 'data_import'
            );
        });

        $response = $this->get('/_test/error-recovery/safe-crash');

        $response->assertStatus(500);
        $response->assertSee('System Error Intercepted');
        $response->assertSee('REC-TEST-SAFE-500');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('PDOException');
    }

    public function test_safe_operation_json_request_returns_safe_json_payload(): void
    {
        Route::get('/_test/error-recovery/safe-json-crash', function () {
            throw new SafeOperationException(
                message: 'Database write error safely intercepted.',
                errorId: 'REC-JSON-001',
                module: 'inventory',
                operation: 'stock_adjustment'
            );
        });

        $response = $this->getJson('/_test/error-recovery/safe-json-crash');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error_id' => 'REC-JSON-001',
            'module' => 'inventory',
            'message' => 'Database write error safely intercepted.',
        ]);
    }

    public function test_smart_retry_service_executes_retry_and_updates_status_to_retried(): void
    {
        $superAdmin = $this->superAdmin();

        $record = SystemRecoveryRecord::create([
            'error_id' => 'REC-RETRY-001',
            'user_id' => $superAdmin->id,
            'user_snapshot' => $superAdmin->name,
            'module' => 'inventory',
            'operation' => 'generic_sync',
            'error_summary' => 'Connection timed out during sync.',
            'status' => 'pending',
            'is_retryable' => true,
            'retry_handler' => 'generic',
            'retry_payload' => ['target' => 'sync'],
            'retry_count' => 0,
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post("/admin/recovery/{$record->id}/retry");

        $response->assertSessionHas('success');

        $record->refresh();
        $this->assertSame('retried', $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame($superAdmin->id, $record->resolved_by_user_id);
        $this->assertNotNull($record->resolved_at);

        // Verify audit log has TriggeredRecoveryAction and SystemOperationRecovered
        $this->assertTrue(AuditLog::where('action', AuditAction::TriggeredRecoveryAction)->exists());
        $this->assertTrue(AuditLog::where('action', AuditAction::SystemOperationRecovered)->exists());
    }

    public function test_smart_retry_service_prevents_duplicate_processing_when_already_resolved(): void
    {
        $superAdmin = $this->superAdmin();

        $record = SystemRecoveryRecord::create([
            'error_id' => 'REC-RETRY-002',
            'user_id' => $superAdmin->id,
            'user_snapshot' => $superAdmin->name,
            'module' => 'inventory',
            'operation' => 'generic_sync',
            'error_summary' => 'Sync error',
            'status' => 'resolved',
            'is_retryable' => true,
            'retry_handler' => 'generic',
            'retry_count' => 1,
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post("/admin/recovery/{$record->id}/retry");

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Cannot retry incident', session('error'));

        $record->refresh();
        $this->assertSame('resolved', $record->status);
        $this->assertSame(1, $record->retry_count);
    }

    public function test_execute_with_fallback_runs_fallback_and_records_safe_default(): void
    {
        $service = app(SafeExecutionService::class);

        $result = $service->executeWithFallback(
            module: 'telemetry',
            operation: 'iot_reading',
            primary: function () {
                throw new RuntimeException('IoT sensor connection lost.');
            },
            fallback: function () {
                return ['temperature' => 4.0, 'is_fallback' => true];
            },
            context: ['sensor_id' => 'VAULT-COLD-01']
        );

        $this->assertSame(['temperature' => 4.0, 'is_fallback' => true], $result);

        $record = SystemRecoveryRecord::where('module', 'telemetry')->first();
        $this->assertNotNull($record);
        $this->assertSame('safe_default', $record->strategy_applied);
        $this->assertStringContainsString('IoT sensor connection lost', $record->error_summary);
    }

    public function test_super_admin_can_resolve_incident_manually(): void
    {
        $superAdmin = $this->superAdmin();

        $record = SystemRecoveryRecord::create([
            'error_id' => 'REC-RESOLVE-001',
            'module' => 'procurement',
            'operation' => 'purchase_order',
            'error_summary' => 'Missing approval step in sequence',
            'status' => 'pending',
            'is_retryable' => false,
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post("/admin/recovery/{$record->id}/resolve", [
                'notes' => 'Manually verified and confirmed with the procurement officer.',
            ]);

        $response->assertSessionHas('success');

        $record->refresh();
        $this->assertSame('resolved', $record->status);
        $this->assertSame('Manually verified and confirmed with the procurement officer.', $record->resolution_notes);
        $this->assertSame($superAdmin->id, $record->resolved_by_user_id);
    }

    public function test_super_admin_can_ignore_incident(): void
    {
        $superAdmin = $this->superAdmin();

        $record = SystemRecoveryRecord::create([
            'error_id' => 'REC-IGNORE-001',
            'module' => 'warehousing',
            'operation' => 'label_print',
            'error_summary' => 'Printer temporarily offline',
            'status' => 'pending',
            'is_retryable' => false,
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post("/admin/recovery/{$record->id}/ignore");

        $response->assertSessionHas('info');

        $record->refresh();
        $this->assertSame('ignored', $record->status);
    }

    public function test_super_admin_can_rebuild_cache_and_audit_action_is_recorded(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post('/admin/recovery/rebuild-cache');

        $response->assertSessionHas('success');

        $auditLog = AuditLog::where('action', AuditAction::SystemHealthMaintenance)->first();
        $this->assertNotNull($auditLog);
        $this->assertSame($superAdmin->id, $auditLog->user_id);
    }

    public function test_system_health_service_reports_healthy_components(): void
    {
        $service = app(SystemHealthService::class);
        $diagnostics = $service->runFullDiagnostics();

        $this->assertArrayHasKey('overall_status', $diagnostics);
        $this->assertArrayHasKey('database', $diagnostics);
        $this->assertArrayHasKey('queue', $diagnostics);
        $this->assertArrayHasKey('storage', $diagnostics);
        $this->assertArrayHasKey('cache', $diagnostics);

        $this->assertSame('healthy', $diagnostics['database']['status']);
        $this->assertSame('healthy', $diagnostics['storage']['status']);
        $this->assertSame('healthy', $diagnostics['cache']['status']);
    }

    public function test_super_admin_dashboard_shows_alert_when_pending_incidents_exist(): void
    {
        $superAdmin = $this->superAdmin();

        SystemRecoveryRecord::create([
            'error_id' => 'REC-DASH-001',
            'module' => 'import',
            'operation' => 'data_import',
            'error_summary' => 'Import batch dropped connection',
            'status' => 'pending',
            'is_retryable' => true,
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get('/super-admin/dashboard');

        $response->assertOk();
        $response->assertSee('Attention: 1 Unresolved System Incident');
        $response->assertSee('Open Recovery Center');
    }

    public function test_super_admin_can_retry_queue_job(): void
    {
        $superAdmin = $this->superAdmin();
        $uuid = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessDataImportJob']),
            'exception' => "RuntimeException: Queue processing timed out\nat /app/Jobs/ProcessDataImportJob.php:45",
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post("/admin/recovery/retry-job/{$uuid}");

        $response->assertRedirect();
        $this->assertTrue(AuditLog::where('action', AuditAction::TriggeredRecoveryAction)->where('target_name', $uuid)->exists());
    }

    public function test_smart_retry_service_handles_import_retry_flow(): void
    {
        $superAdmin = $this->superAdmin();

        $token = app(\App\Services\Import\ImportStagingService::class)->stage(
            target: 'items',
            mode: 'create_only',
            records: [
                [
                    'sku' => 'REC-SKU-001',
                    'name' => 'Recovered Surgical Scalpel',
                    'item_category_id' => null,
                    'unit_of_measure' => 'box',
                    'unit_cost' => 450.00,
                    'reorder_level' => 5,
                ]
            ],
            userId: $superAdmin->id
        );

        $record = SystemRecoveryRecord::create([
            'error_id' => 'REC-IMP-001',
            'user_id' => $superAdmin->id,
            'user_snapshot' => $superAdmin->name,
            'module' => 'import',
            'operation' => 'data_import',
            'error_summary' => 'Import interrupted by network failure',
            'status' => 'pending',
            'is_retryable' => true,
            'retry_handler' => 'import',
            'retry_payload' => [
                'import_token' => $token,
                'target' => 'items',
            ],
            'retry_count' => 0,
        ]);

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post("/admin/recovery/{$record->id}/retry");

        $response->assertSessionHas('success');

        $record->refresh();
        $this->assertSame('retried', $record->status);
        $this->assertSame(1, $record->retry_count);

        // Verify item was created
        $this->assertTrue(InventoryItem::where('sku', 'REC-SKU-001')->exists());
    }
}
