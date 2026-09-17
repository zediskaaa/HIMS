<?php

namespace Tests\Feature;

use App\Enums\RecoveryStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\ProcessRecommendation;
use App\Models\ProcurementSavingsLog;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierScorecard;
use App\Models\SystemRecoveryAttempt;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\Import\ImportStagingService;
use Database\Seeders\ErrorRecoveryDemoSeeder;
use Database\Seeders\OperationalMetricsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecoveryAndMetricsSeedingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $inventoryManager;
    private User $warehouseStaff;
    private User $pharmacyStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdministrator,
            'status' => 'active',
            'is_protected' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::Administrator,
            'status' => 'active',
        ]);

        $this->inventoryManager = User::factory()->create([
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $this->warehouseStaff = User::factory()->create([
            'role' => UserRole::WarehouseStaff,
            'status' => 'active',
        ]);

        $this->pharmacyStaff = User::factory()->create([
            'role' => UserRole::PharmacyStaff,
            'status' => 'active',
        ]);
    }

    public function test_error_recovery_demo_seeder_populates_expected_records(): void
    {
        $this->seed(ErrorRecoveryDemoSeeder::class);

        $this->assertSame(8, SystemRecoveryRecord::count());
        $this->assertSame(4, SystemRecoveryRecord::open()->count());
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::recovered()->count());
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::retryable()->count());

        // Check specific incident attributes
        $importIncident = SystemRecoveryRecord::where('error_id', 'REC-2026-IMP-001')->firstOrFail();
        $this->assertSame('Imports', $importIncident->module);
        $this->assertSame(RecoveryStatus::Failed, $importIncident->status);
        $this->assertTrue($importIncident->is_retryable);
        $this->assertIsArray($importIncident->technical_details);
        $this->assertSame(
            ['target' => 'items', 'staged_rows' => 120],
            $importIncident->technical_details['context']
        );

        // The seeded token has genuinely lapsed, so the incident is a truthful
        // stand-in for an expired import session rather than a failed retry.
        $this->assertNull(app(ImportStagingService::class)->retrieve(
            $importIncident->retry_payload['import_token'],
            $this->inventoryManager->id
        ));

        // Check failed jobs: exactly one, and it is the incident's reference target.
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'queue' => 'notifications',
        ]);
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            SystemRecoveryRecord::where('error_id', 'REC-2026-QUE-002')->value('reference_id')
        );

        // The Audit Trail is append-only and records what people did. The seeder
        // seeds incidents, so it deliberately writes nothing there.
        $this->assertSame(0, AuditLog::where('module', 'System Recovery')->count());
    }

    private function createItem(string $name = 'Paracetamol', string $sku = 'MED-PARA-500'): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'unit' => 'box',
            'quantity_on_hand' => 100,
            'unit_cost' => 10.00,
            'total_value' => 1000.00,
        ]);
    }

    private function createLocation(string $name = 'Main Warehouse', string $code = 'WH-01'): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'type' => 'warehouse',
            'status' => 'active',
        ]);
    }

    public function test_operational_metrics_demo_seeder_populates_reviews_and_adjustments(): void
    {
        Supplier::create([
            'name' => 'MedSupply Demonstration Corp',
            'status' => 'active',
        ]);
        $item = $this->createItem();
        $location = $this->createLocation();

        $this->seed(OperationalMetricsDemoSeeder::class);

        $this->assertSame(2, KpiProcessReview::count());
        $this->assertDatabaseHas('kpi_process_reviews', ['review_number' => 'REV-2026-Q3', 'status' => 'approved']);
        $this->assertDatabaseHas('kpi_process_reviews', ['review_number' => 'REV-2026-Q4', 'status' => 'submitted']);

        $this->assertGreaterThanOrEqual(1, SupplierScorecard::count());
        $this->assertGreaterThanOrEqual(1, ProcessRecommendation::count());
        $this->assertGreaterThanOrEqual(1, InventoryAdjustment::count());

        $criticalRec = ProcessRecommendation::where('priority', 'critical')->firstOrFail();
        $this->assertSame('pending', $criticalRec->status);
        $this->assertNotEmpty($criticalRec->problem_detected);

        $adjustment = InventoryAdjustment::where('adjustment_number', 'ADJ-2026-0001')->firstOrFail();
        $this->assertSame('damage', $adjustment->adjustment_type);
        $this->assertSame('posted', $adjustment->status);
    }

    public function test_reseeding_is_idempotent_and_does_not_create_duplicate_records(): void
    {
        Supplier::create([
            'name' => 'MedSupply Demonstration Corp',
            'status' => 'active',
        ]);
        $this->createItem();
        $this->createLocation();

        // First run
        $this->seed(ErrorRecoveryDemoSeeder::class);
        $this->seed(OperationalMetricsDemoSeeder::class);

        $recoveryCount = SystemRecoveryRecord::count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        $reviewCount = KpiProcessReview::count();
        $adjustmentCount = InventoryAdjustment::count();

        // Second run
        $this->seed(ErrorRecoveryDemoSeeder::class);
        $this->seed(OperationalMetricsDemoSeeder::class);

        $this->assertSame($recoveryCount, SystemRecoveryRecord::count());
        $this->assertSame($failedJobsCount, DB::table('failed_jobs')->count());
        $this->assertSame($reviewCount, KpiProcessReview::count());
        $this->assertSame($adjustmentCount, InventoryAdjustment::count());
    }

    public function test_recovery_center_dashboard_displays_seeded_metrics(): void
    {
        $this->seed(ErrorRecoveryDemoSeeder::class);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.recovery.index'));

        $response->assertOk();

        // Incidents across the whole status vocabulary are listed.
        $response->assertSee('REC-2026-IMP-001');
        $response->assertSee('REC-2026-TXN-006');
        $response->assertSee('REC-2026-EXP-008');

        // Their real attributes, not just their IDs.
        $response->assertSee('Data import');
        $response->assertSee('data_import');
        $response->assertSee('PDEA-2026-08');
        $response->assertSee('Connection could not be established with host smtp.hospital.local:587');
        $response->assertSee('Deadlock found when trying to get lock');

        // The detail page carries the traceability fields the list omits.
        $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.recovery.show', SystemRecoveryRecord::where('error_id', 'REC-2026-QUE-002')->firstOrFail()))
            ->assertOk()
            ->assertSee('SendQueuedNotifications')
            ->assertSee('550e8400-e29b-41d4-a716-446655440000')
            ->assertSee('System / Automated');

        // The summary tiles are computed from those records. Attempts never inflate
        // the incident counts: four seeded attempts belong to three incidents.
        $metrics = $response->viewData('metrics');

        $this->assertSame(8, $metrics['total']);
        $this->assertSame(4, $metrics['open']);
        $this->assertSame(1, $metrics['recovered']);
        $this->assertSame(1, $metrics['resolved']);
        $this->assertSame(1, $metrics['recovery_failed']);
        $this->assertSame(1, $metrics['awaiting_worker']);
        $this->assertSame(2, $metrics['failed_attempts']);
        $this->assertSame(4, SystemRecoveryAttempt::count());

        // One recovered and one recovery-failed incident have a verdict: 1 of 2.
        $this->assertSame(50, $metrics['success_rate']);
    }
}
