<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\MaterialRequisition;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialRequisitionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createPharmacyUser(): User
    {
        return User::factory()->pharmacyStaff()->create();
    }

    private function createInventoryManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function createWarehouseStaff(): User
    {
        return User::factory()->warehouseStaff()->create();
    }

    private function createItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'MED-PCM-500',
            'description' => 'Antipyretic/analgesic tablet',
            'unit_of_measure' => 'box',
            'unit_cost' => 120.00,
            'quantity_on_hand' => 100,
            'reorder_point' => 20,
            'safety_stock' => 10,
            'economic_order_quantity' => 50,
            'lead_time_days' => 3,
            'annual_demand' => 600,
            'abc_class' => 'B',
            'costing_method' => 'FIFO',
            'status' => 'active',
        ], $overrides));
    }

    private function createLocation(string $code = 'LOC-MAIN-01', string $name = 'Main Dispensary'): StorageLocation
    {
        return StorageLocation::create([
            'code' => $code,
            'name' => $name,
            'zone' => 'General',
            'status' => 'active',
        ]);
    }

    public function test_inventory_requisitions_index_loads_successfully(): void
    {
        $user = $this->createPharmacyUser();
        $this->createItem();

        CostCenter::create([
            'code' => 'CC-ER-01',
            'name' => 'Emergency Room',
            'department' => 'Emergency',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index'));

        $response->assertOk();
        $response->assertViewIs('inventory.requisitions.index');
        $response->assertSeeText('Department Material Requisitions');
        $response->assertSeeText('New Store Requisition');
    }

    public function test_creating_requisition_validates_numeric_positive_quantities(): void
    {
        $requester = $this->createPharmacyUser();
        $item = $this->createItem();

        // Test with zero quantity
        $response = $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Pediatrics',
            'urgency' => 'routine',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.requested_quantity']);

        // Test with negative quantity
        $response = $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Pediatrics',
            'urgency' => 'routine',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => -5,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.requested_quantity']);
    }

    public function test_creating_requisition_auto_generates_reference_number_and_records_audit(): void
    {
        $requester = $this->createPharmacyUser();
        $item = $this->createItem();

        $response = $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Intensive Care Unit (ICU)',
            'urgency' => 'urgent',
            'justification' => 'Urgent post-op replenishment',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => 15,
                ],
            ],
        ]);

        $response->assertRedirect(route('inventory.requisitions.index'));

        $requisition = MaterialRequisition::where('department', 'Intensive Care Unit (ICU)')->first();
        $this->assertNotNull($requisition);
        $this->assertMatchesRegularExpression('/^MR-\d{8}-\d{4}$/', $requisition->requisition_number);
        $this->assertEquals('pending_approval', $requisition->status);
        $this->assertEquals($requester->id, $requisition->requesting_user_id);
        $this->assertCount(1, $requisition->lines);
        $this->assertEquals(15, $requisition->lines->first()->requested_quantity);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $requester->id,
            'action' => AuditAction::CreatedMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);
    }

    public function test_unauthorized_user_cannot_approve_requisition(): void
    {
        $requester = $this->createPharmacyUser();
        $item = $this->createItem();

        $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Emergency',
            'urgency' => 'routine',
            'lines' => [
                ['item_id' => $item->id, 'requested_quantity' => 10],
            ],
        ]);

        $requisition = MaterialRequisition::first();

        // Requester lacks ApproveRequisition permission -> 403 Forbidden
        $response = $this->actingAs($requester)->post(route('inventory.requisitions.approve', $requisition));
        $response->assertForbidden();

        $requisition->refresh();
        $this->assertEquals('pending_approval', $requisition->status);
    }

    public function test_manager_cannot_self_approve_own_requisition(): void
    {
        $manager = $this->createInventoryManager();
        $item = $this->createItem();

        $this->actingAs($manager)->post(route('inventory.requisitions.store'), [
            'department' => 'Emergency',
            'urgency' => 'routine',
            'lines' => [
                ['item_id' => $item->id, 'requested_quantity' => 10],
            ],
        ]);

        $requisition = MaterialRequisition::first();

        // Manager has permission, but SoD blocks self-approval
        $response = $this->actingAs($manager)->post(route('inventory.requisitions.approve', $requisition));
        $response->assertSessionHasErrors(['approve']);

        $requisition->refresh();
        $this->assertEquals('pending_approval', $requisition->status);
    }

    public function test_supervisor_approves_requisition_reserving_atp_and_logging_audit(): void
    {
        $requester = $this->createPharmacyUser();
        $manager = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 100]);
        $location = $this->createLocation();

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Emergency',
            'urgency' => 'routine',
            'lines' => [
                ['item_id' => $item->id, 'requested_quantity' => 25],
            ],
        ]);

        $requisition = MaterialRequisition::first();

        $response = $this->actingAs($manager)->post(route('inventory.requisitions.approve', $requisition));
        $response->assertRedirect(route('inventory.requisitions.index'));
        $response->assertSessionHas('success');

        $requisition->refresh();
        $item->refresh();

        $this->assertEquals('approved', $requisition->status);
        $this->assertEquals($manager->id, $requisition->approved_by_id);
        $this->assertEquals(25, $item->reservedQuantity());
        $this->assertEquals(75, $item->availableToPromise());

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::ApprovedMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);
    }

    public function test_requisition_rejection_requires_reason_and_updates_status(): void
    {
        $requester = $this->createPharmacyUser();
        $manager = $this->createInventoryManager();
        $item = $this->createItem();

        $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Surgery',
            'urgency' => 'routine',
            'lines' => [
                ['item_id' => $item->id, 'requested_quantity' => 10],
            ],
        ]);

        $requisition = MaterialRequisition::first();

        // Rejection without reason fails validation
        $response = $this->actingAs($manager)->post(route('inventory.requisitions.reject', $requisition), []);
        $response->assertSessionHasErrors(['rejection_reason']);

        // Rejection with valid reason
        $response = $this->actingAs($manager)->post(route('inventory.requisitions.reject', $requisition), [
            'rejection_reason' => 'Duplicate requisition submitted by Operating Room staff earlier.',
        ]);

        $response->assertRedirect(route('inventory.requisitions.index'));
        $response->assertSessionHas('success');

        $requisition->refresh();
        $this->assertEquals('rejected', $requisition->status);
        $this->assertEquals('Duplicate requisition submitted by Operating Room staff earlier.', $requisition->rejection_reason);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::RejectedMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);
    }

    public function test_cancellation_releases_atp_reservations(): void
    {
        $requester = $this->createPharmacyUser();
        $manager = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 100]);
        $location = $this->createLocation();

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Emergency',
            'urgency' => 'routine',
            'lines' => [
                ['item_id' => $item->id, 'requested_quantity' => 20],
            ],
        ]);

        $requisition = MaterialRequisition::first();

        // Approve it first to trigger ATP reservation
        $this->actingAs($manager)->post(route('inventory.requisitions.approve', $requisition));
        $item->refresh();
        $this->assertEquals(20, $item->reservedQuantity());

        // Cancel the approved requisition
        $response = $this->actingAs($requester)->post(route('inventory.requisitions.cancel', $requisition), [
            'cancellation_reason' => 'Patient transferred to another hospital, medication no longer required.',
        ]);

        $response->assertRedirect(route('inventory.requisitions.index'));
        $response->assertSessionHas('success');

        $requisition->refresh();
        $item->refresh();

        $this->assertEquals('cancelled', $requisition->status);
        $this->assertEquals(0, $item->reservedQuantity()); // ATP hold released!
        $this->assertEquals(100, $item->availableToPromise());

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $requester->id,
            'action' => AuditAction::CancelledMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);
    }

    public function test_requisition_show_displays_details_and_picklist(): void
    {
        $requester = $this->createPharmacyUser();
        $manager = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 50]);
        $location = $this->createLocation();

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($requester)->post(route('inventory.requisitions.store'), [
            'department' => 'Outpatient Department',
            'urgency' => 'urgent',
            'justification' => 'Weekly clinic supply',
            'lines' => [
                ['item_id' => $item->id, 'requested_quantity' => 10],
            ],
        ]);

        $requisition = MaterialRequisition::first();

        $response = $this->actingAs($manager)->get(route('inventory.requisitions.show', $requisition));

        $response->assertOk();
        $response->assertViewIs('inventory.requisitions.show');
        $response->assertSeeText($requisition->requisition_number);
        $response->assertSeeText('Outpatient Department');
        $response->assertSeeText('Weekly clinic supply');
        $response->assertSeeText($item->name);
    }
}
