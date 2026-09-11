<?php

namespace Tests\Feature;

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
use App\Models\SystemRecoveryRecord;
use App\Models\User;
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
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::pending()->count());
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::resolved()->count());
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::retryable()->count());

        // Check specific incident attributes
        $importIncident = SystemRecoveryRecord::where('error_id', 'REC-2026-IMP-001')->firstOrFail();
        $this->assertSame('imports', $importIncident->module);
        $this->assertSame('pending', $importIncident->status);
        $this->assertTrue($importIncident->is_retryable);
        $this->assertIsArray($importIncident->technical_details);
        $this->assertArrayHasKey('detected_headers', $importIncident->technical_details);

        // Check failed jobs
        $this->assertSame(2, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'queue' => 'notifications',
        ]);

        // Check audit logs
        $recoveryLogs = AuditLog::where('module', 'System Recovery')->get();
        $this->assertGreaterThanOrEqual(3, $recoveryLogs->count());
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
            ->get(route('admin.recovery.index'));

        $response->assertOk();
        $response->assertSee('REC-2026-IMP-001');
        $response->assertSee('REC-2026-TXN-002');
        $response->assertSee('REC-2026-EXP-004');
        $response->assertSee('DispatchNearExpiryStockAlerts');
    }
}
