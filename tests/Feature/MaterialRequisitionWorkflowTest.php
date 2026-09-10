<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\MaterialRequisition;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\Inventory\IssuanceEngine;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    private function createRequisition(User $requester, string $status = 'pending_approval'): MaterialRequisition
    {
        return MaterialRequisition::create([
            'requisition_number' => 'MR-20260910-9001',
            'requesting_user_id' => $requester->id,
            'department' => $requester->department ?? 'Pharmacy',
            'status' => $status,
            'urgency' => 'routine',
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

    public function test_registry_explains_exact_approver_roles_and_shows_the_action_only_to_an_independent_approver(): void
    {
        $requester = $this->createPharmacyUser();
        $manager = $this->createInventoryManager();
        $requisition = $this->createRequisition($requester);

        $managerView = $this->actingAs($manager)->get(route('inventory.requisitions.index'));
        $managerView->assertOk()
            ->assertSeeText('Who can approve a Store Requisition?')
            ->assertSeeText('Inventory Manager')
            ->assertSeeText('Administrator')
            ->assertSeeText('Super Administrator')
            ->assertSee('data-confirm-title="Approve Store Requisition"', false)
            ->assertSee(route('inventory.requisitions.approve', $requisition), false);

        $requesterView = $this->actingAs($requester)->get(route('inventory.requisitions.index'));
        $requesterView->assertOk()
            ->assertSeeText('Self-approval blocked')
            ->assertDontSee('data-confirm-title="Approve Store Requisition"', false);
    }

    public function test_only_the_documented_roles_receive_requisition_approval_permission(): void
    {
        foreach ([UserRole::InventoryManager, UserRole::Administrator, UserRole::SuperAdministrator] as $role) {
            $this->assertTrue($role->grants(Permission::ApproveRequisition), $role->label().' should approve requisitions.');
        }

        foreach ([UserRole::WarehouseStaff, UserRole::PharmacyStaff, UserRole::Viewer] as $role) {
            $this->assertFalse($role->grants(Permission::ApproveRequisition), $role->label().' should not approve requisitions.');
        }
    }

    public function test_administrator_and_super_administrator_can_approve_through_their_own_guards(): void
    {
        foreach ([UserRole::Administrator, UserRole::SuperAdministrator] as $index => $role) {
            $requester = $this->createPharmacyUser();
            $approver = User::factory()->role($role)->create();
            $item = $this->createItem(['sku' => 'ROLE-APPROVAL-'.$index, 'quantity_on_hand' => 10]);
            $location = $this->createLocation('ROLE-LOC-'.$index, 'Role Test Location '.$index);
            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $location->id,
                'quantity' => 10,
                'reserved_quantity' => 0,
            ]);
            $requisition = app(IssuanceEngine::class)->createRequisition([
                'department' => 'Pharmacy',
                'lines' => [['item_id' => $item->id, 'requested_quantity' => 1]],
            ], $requester);
            if ($role === UserRole::SuperAdministrator) {
                $requisition->update(['status' => 'submitted']);
            }

            $this->actingAs($approver)
                ->post(route('inventory.requisitions.approve', $requisition))
                ->assertRedirect(route('inventory.requisitions.index'));

            $this->assertSame($approver->id, $requisition->fresh()->approved_by_id);
        }
    }

    public function test_api_rejection_requires_requisition_approval_permission(): void
    {
        $requester = $this->createPharmacyUser();
        $requisition = $this->createRequisition($requester);
        $unauthorizedUser = $this->createWarehouseStaff();
        Sanctum::actingAs($unauthorizedUser, ['*']);

        $this->postJson('/api/v1/inventory/requisitions/'.$requisition->id.'/reject', [
            'rejection_reason' => 'Attempted without approval authority.',
        ])->assertForbidden();

        $this->assertSame('pending_approval', $requisition->fresh()->status);
        $this->assertNull($requisition->approved_by_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::RejectedMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);
    }

    public function test_another_requester_cannot_cancel_someone_elses_requisition(): void
    {
        $owner = $this->createPharmacyUser();
        $otherRequester = $this->createPharmacyUser();
        $requisition = $this->createRequisition($owner);

        $this->actingAs($otherRequester)
            ->post(route('inventory.requisitions.cancel', $requisition), ['cancellation_reason' => 'Not my request.'])
            ->assertSessionHasErrors(['cancel']);

        $this->assertSame('pending_approval', $requisition->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::CancelledMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);
    }

    public function test_only_the_original_requester_can_acknowledge_an_issued_requisition(): void
    {
        $requester = $this->createPharmacyUser();
        $otherUser = $this->createPharmacyUser();
        $requisition = $this->createRequisition($requester, 'issued');

        $this->actingAs($otherUser)
            ->post(route('inventory.requisitions.acknowledge', $requisition))
            ->assertSessionHasErrors(['acknowledge']);

        $this->assertSame('issued', $requisition->fresh()->status);
        $this->assertNull($requisition->acknowledged_by_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::AcknowledgedMaterialIssuance->value,
            'target_id' => $requisition->id,
        ]);

        $this->actingAs($requester)
            ->post(route('inventory.requisitions.acknowledge', $requisition), ['notes' => 'Received in good order.'])
            ->assertRedirect(route('inventory.requisitions.show', $requisition));

        $requisition->refresh();
        $this->assertSame('acknowledged', $requisition->status);
        $this->assertSame($requester->id, $requisition->acknowledged_by_id);
        $this->assertTrue($requisition->acknowledgedBy->is($requester));
        $this->assertNotNull($requisition->acknowledged_at);
    }

    public function test_requester_cannot_acknowledge_before_stock_is_issued(): void
    {
        $requester = $this->createPharmacyUser();
        $requisition = $this->createRequisition($requester);

        $this->actingAs($requester)
            ->post(route('inventory.requisitions.acknowledge', $requisition))
            ->assertSessionHasErrors(['acknowledge']);

        $this->assertSame('pending_approval', $requisition->fresh()->status);
        $this->assertNull($requisition->acknowledged_at);
    }

    public function test_issuance_cannot_exceed_the_remaining_request_and_records_the_issuer(): void
    {
        $requester = $this->createPharmacyUser();
        $manager = $this->createInventoryManager();
        $warehouseStaff = $this->createWarehouseStaff();
        $item = $this->createItem(['quantity_on_hand' => 20]);
        $location = $this->createLocation();
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 20,
            'reserved_quantity' => 0,
        ]);

        $requisition = app(IssuanceEngine::class)->createRequisition([
            'department' => 'Pharmacy',
            'lines' => [['item_id' => $item->id, 'requested_quantity' => 10]],
        ], $requester);
        app(IssuanceEngine::class)->approveRequisition($requisition, $manager);
        $line = $requisition->fresh()->lines()->sole();

        try {
            app(IssuanceEngine::class)->issueRequisition($requisition, [
                'lines' => [['line_id' => $line->id, 'quantity' => 11, 'location_id' => $location->id]],
            ], $warehouseStaff);
            $this->fail('Over-issuance should have been rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('remaining requested quantity', $exception->getMessage());
        }

        $this->assertSame('approved', $requisition->fresh()->status);
        $this->assertSame(20, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::IssuedMaterialRequisition->value,
            'target_id' => $requisition->id,
        ]);

        app(IssuanceEngine::class)->issueRequisition($requisition, [
            'lines' => [['line_id' => $line->id, 'quantity' => 10, 'location_id' => $location->id]],
        ], $warehouseStaff);

        $requisition->refresh();
        $this->assertSame('issued', $requisition->status);
        $this->assertSame($warehouseStaff->id, $requisition->issued_by_id);
        $this->assertTrue($requisition->issuedBy->is($warehouseStaff));
        $this->assertNotNull($requisition->issued_at);
    }
}
