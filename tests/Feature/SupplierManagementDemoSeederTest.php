<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierManagementDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_only_the_missing_eligible_supplier_through_the_real_workflow(): void
    {
        $creator = User::factory()->inventoryManager()->create();
        $approver = User::factory()->administrator()->create();
        $existing = $this->draftSupplier();
        $existingState = $existing->fresh()->getAttributes();

        $this->seed(SupplierManagementDemoSeeder::class);

        $supplier = Supplier::where('identity_key', SupplierManagementDemoSeeder::IDENTITY_KEY)->firstOrFail();
        $this->assertSame(SupplierStatus::Active, $supplier->status);
        $this->assertSame(SupplierAccreditationStatus::Approved, $supplier->accreditation_status);
        $this->assertTrue($supplier->isProcurementEligible());
        $this->assertFalse($supplier->provides_regulated_health_products);
        $this->assertSame($creator->id, $supplier->created_by);
        $this->assertSame($creator->id, $supplier->reviewed_by);
        $this->assertSame($approver->id, $supplier->approved_by);
        $this->assertSame($existingState, $existing->fresh()->getAttributes());

        $accreditation = $supplier->accreditations()->sole();
        $this->assertSame(1, $accreditation->cycle_number);
        $this->assertSame(SupplierAccreditationStatus::Approved, $accreditation->status);
        $this->assertSame($creator->id, $accreditation->submitted_by);
        $this->assertSame($approver->id, $accreditation->decided_by);

        $this->assertSame(3, AuditLog::where('target_type', Supplier::class)
            ->where('target_id', (string) $supplier->id)
            ->whereIn('action', [
                AuditAction::CreatedSupplier->value,
                AuditAction::SubmittedSupplier->value,
                AuditAction::ApprovedSupplier->value,
            ])->count());
    }

    public function test_repeated_seeding_does_not_create_duplicates(): void
    {
        User::factory()->inventoryManager()->create();
        User::factory()->administrator()->create();

        $this->seed(SupplierManagementDemoSeeder::class);
        $counts = [
            Supplier::count(),
            Supplier::firstOrFail()->accreditations()->count(),
            AuditLog::count(),
        ];

        $this->seed(SupplierManagementDemoSeeder::class);

        $this->assertSame($counts, [
            Supplier::count(),
            Supplier::firstOrFail()->accreditations()->count(),
            AuditLog::count(),
        ]);
    }

    public function test_it_adds_nothing_when_an_eligible_supplier_already_exists(): void
    {
        User::factory()->inventoryManager()->create();
        User::factory()->administrator()->create();
        $eligible = $this->draftSupplier([
            'name' => 'Existing Eligible Supplier',
            'identity_key' => 'tax:741852963000',
            'tax_number' => '741-852-963-000',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addYear(),
        ]);

        $this->seed(SupplierManagementDemoSeeder::class);

        $this->assertSame(1, Supplier::count());
        $this->assertTrue($eligible->fresh()->isProcurementEligible());
        $this->assertDatabaseMissing('suppliers', ['identity_key' => SupplierManagementDemoSeeder::IDENTITY_KEY]);
    }

    public function test_it_preserves_an_existing_demonstration_supplier_that_is_no_longer_eligible(): void
    {
        User::factory()->inventoryManager()->create();
        User::factory()->administrator()->create();
        $supplier = $this->draftSupplier([
            'identity_key' => SupplierManagementDemoSeeder::IDENTITY_KEY,
            'tax_number' => '245-731-680-000',
            'status' => SupplierStatus::Suspended,
            'notes' => 'Changed by a user after initial seeding.',
        ]);
        $state = $supplier->fresh()->getAttributes();

        $this->seed(SupplierManagementDemoSeeder::class);

        $this->assertSame(1, Supplier::count());
        $this->assertSame($state, $supplier->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::count());
    }

    private function draftSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_replace([
            'name' => 'Existing Draft Supplier',
            'business_structure' => 'corporation',
            'address' => 'Manila, Metro Manila',
            'email' => 'procurement@existing-supplier.test',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ], $overrides));
    }
}
