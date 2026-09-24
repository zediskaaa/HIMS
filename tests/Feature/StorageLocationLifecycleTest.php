<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseTask;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\QualityControlService;
use App\Services\Warehouse\WarehouseTaskService;
use App\Services\InventoryAutomationService;
use App\Services\Inventory\TransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorageLocationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $manager;
    private User $staff;
    private InventoryAutomationService $automationService;
    private TransferService $transferService;

    private function receiveInto(PurchaseOrder $po, StorageLocation $destination, int $quantity): void
    {
        $line = $po->lines()->firstOrFail();
        $grn = app(GoodsReceiptService::class)->receiveOrder($po, [
            'destination_location_id' => $destination->id,
            'lines' => [['po_line_id' => $line->id, 'received_quantity' => $quantity,
                'batch_number' => 'LOT-'.$po->id, 'expiry_date' => now()->addYear()->toDateString()]],
        ], $this->staff);
        $this->assertSame(0, $line->item->fresh()->quantity_on_hand);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        app(QualityControlService::class)->releaseLot($inspection, $quantity, $destination->id, $this->manager);
        $task = WarehouseTask::where('reference_type', $inspection->getMorphClass())->where('reference_id', $inspection->id)->firstOrFail();
        $tasks = app(WarehouseTaskService::class);
        $tasks->start($task, $this->staff);
        $tasks->scan($task, 'LOC-STAGING', $this->staff);
        $tasks->scan($task, $line->item->sku, $this->staff);
        $tasks->scan($task, $destination->code, $this->staff);
        $tasks->complete($task, $quantity, $this->staff);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdministrator()->create([
            'status' => 'active',
        ]);

        $this->admin = User::factory()->administrator()->create([
            'status' => 'active',
        ]);

        $this->manager = User::factory()->inventoryManager()->create([
            'status' => 'active',
        ]);

        $this->staff = User::factory()->warehouseStaff()->create([
            'status' => 'active',
        ]);

        $this->automationService = app(InventoryAutomationService::class);
        $this->transferService = app(TransferService::class);
    }

    private function createLocation(string $name, string $code, string $status = 'active', string $type = 'shelf'): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'status' => $status,
        ]);
    }

    private function createItem(string $name, string $sku, ?StorageLocation $location = null, int $initialQty = 0): InventoryItem
    {
        $category = ItemCategory::firstOrCreate(
            ['code' => 'GEN-MED'],
            ['name' => 'General Medicine']
        );

        $item = InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $category->id,
            'unit' => 'box',
            'unit_cost' => 50.00,
            'default_location_id' => $location?->id,
            'status' => 'active',
            'quantity_on_hand' => $initialQty,
        ]);

        if ($location && $initialQty > 0) {
            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $location->id,
                'quantity' => $initialQty,
                'reserved_quantity' => 0,
            ]);
        }

        return $item;
    }

    private function createSupplier(string $name = 'MediCorp Pharma'): Supplier
    {
        return Supplier::firstOrCreate(
            ['name' => $name],
            ['code' => 'SUP-001', 'status' => 'active']
        );
    }

    /**
     * Requirement 1: Super Admin can deactivate location.
     */
    public function test_01_super_admin_can_deactivate_storage_location(): void
    {
        $location = $this->createLocation('Pharmacy Bay 1', 'BAY-01', 'active');

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Annual facility maintenance and inventory audit',
            ]);

        $response->assertSessionHas('success');
        $this->assertSame('inactive', $location->fresh()->status);
        $this->assertTrue($location->fresh()->isInactive());
    }

    /**
     * Requirement 2: Admin cannot deactivate location (403).
     */
    public function test_02_admin_cannot_deactivate_storage_location(): void
    {
        $location = $this->createLocation('Pharmacy Bay 2', 'BAY-02', 'active');

        $response = $this->actingAs($this->admin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Admin attempt',
            ]);

        $response->assertStatus(403);
        $this->assertSame('active', $location->fresh()->status);
    }

    /**
     * Requirement 3: Staff cannot deactivate location (403).
     */
    public function test_03_staff_cannot_deactivate_storage_location(): void
    {
        $location = $this->createLocation('Pharmacy Bay 3', 'BAY-03', 'active');

        $response = $this->actingAs($this->staff)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Staff attempt',
            ]);

        $response->assertStatus(403);
        $this->assertSame('active', $location->fresh()->status);
    }

    /**
     * Requirement 4: Super Admin can reactivate location.
     */
    public function test_04_super_admin_can_reactivate_inactive_location(): void
    {
        $location = $this->createLocation('Quarantine Bay 4', 'BAY-04', 'inactive');

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'active',
                'reason' => 'Maintenance completed, reopening location',
            ]);

        $response->assertSessionHas('success');
        $this->assertSame('active', $location->fresh()->status);
        $this->assertTrue($location->fresh()->isActive());
    }

    /**
     * Requirement 5: Inactive location remains in database.
     */
    public function test_05_inactive_location_remains_in_database(): void
    {
        $location = $this->createLocation('Warehouse A', 'WH-A', 'active');
        $originalId = $location->id;

        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Decommissioning bay',
            ]);

        $this->assertDatabaseHas('storage_locations', [
            'id' => $originalId,
            'code' => 'WH-A',
            'status' => 'inactive',
        ]);
        $this->assertNotNull(StorageLocation::find($originalId));
    }

    /**
     * Requirement 6: Inactive location remains in reports.
     */
    public function test_06_inactive_location_remains_in_reports(): void
    {
        $activeLoc = $this->createLocation('Active Storeroom', 'ACT-01', 'active');
        $inactiveLoc = $this->createLocation('Inactive Storeroom', 'INACT-01', 'inactive');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('inventory.reports'));

        $response->assertOk();
        $locations = $response->viewData('locations');
        $this->assertTrue($locations->contains('id', $inactiveLoc->id));
        $this->assertTrue($locations->contains('id', $activeLoc->id));
        $response->assertSee('Inactive Storeroom (INACT-01) [Inactive]');
    }

    /**
     * Requirement 7: Existing inventory is not deleted or zeroed upon deactivation.
     */
    public function test_07_existing_inventory_is_not_deleted_or_zeroed_upon_deactivation(): void
    {
        $location = $this->createLocation('Storage Room 101', 'SR-101', 'active');
        $itemA = $this->createItem('Item Alpha', 'SKU-A', $location, 50);
        $itemB = $this->createItem('Item Beta', 'SKU-B', $location, 75);

        $this->assertEquals(125, $location->totalQuantity());

        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Scheduled maintenance',
            ]);

        $this->assertEquals(50, ItemStockLevel::where('storage_location_id', $location->id)->where('item_id', $itemA->id)->value('quantity'));
        $this->assertEquals(75, ItemStockLevel::where('storage_location_id', $location->id)->where('item_id', $itemB->id)->value('quantity'));
        $this->assertEquals(125, $location->fresh()->totalQuantity());
    }

    /**
     * Requirement 8: Purchase Order receiving into inactive location is blocked.
     */
    public function test_08_purchase_order_receiving_into_inactive_location_is_blocked(): void
    {
        $inactiveLoc = $this->createLocation('Inactive Receiving Bay', 'INACT-BAY', 'inactive');
        $supplier = $this->createSupplier();
        $item = $this->createItem('Antibiotics', 'MED-ANT-01', $inactiveLoc, 0);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_cost' => 1000.00,
            'order_date' => now(),
            'created_by_id' => $this->admin->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 20,
            'received_quantity' => 0,
            'unit_cost' => 50.00,
            'subtotal' => 1000.00,
            'line_status' => 'pending',
        ]);

        // A GRN cannot nominate an inactive final destination.
        try {
            app(GoodsReceiptService::class)->receiveOrder($po, [
                'destination_location_id' => $inactiveLoc->id,
                'lines' => [['po_line_id' => $po->lines()->firstOrFail()->id, 'received_quantity' => 20,
                    'batch_number' => 'LOT-INACTIVE-20', 'expiry_date' => now()->addYear()->toDateString()]],
            ], $this->staff);
            $this->fail('Inactive destination was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }
        $this->assertNotSame('received', $po->fresh()->status);
        $this->assertEquals(0, ItemStockLevel::where('storage_location_id', $inactiveLoc->id)->where('item_id', $item->id)->value('quantity') ?? 0);
    }

    /**
     * Requirement 9: Manual stock-in to inactive location is blocked.
     */
    public function test_09_manual_stock_in_to_inactive_location_is_blocked(): void
    {
        $inactiveLoc = $this->createLocation('Defunct Aisle', 'DEF-01', 'inactive');
        $item = $this->createItem('Syringes 5ml', 'SYR-005', $inactiveLoc, 10);

        // Controller level positive adjustment with authorized manager
        $response = $this->actingAs($this->manager)
            ->post(route('inventory.adjustments.store'), [
                'item_id' => $item->id,
                'location_id' => $inactiveLoc->id,
                'adjustment_type' => 'increase',
                'quantity' => 15,
                'reason' => 'Physical count surplus',
            ]);

        $response->assertSessionHasErrors('location_id');

        // Service level direct positive adjustment
        $this->expectException(ValidationException::class);
        $this->automationService->adjustStockLevel(
            $item->id,
            $inactiveLoc->id,
            null,
            10
        );
    }

    /**
     * Requirement 10: Transfer INTO inactive location is blocked.
     */
    public function test_10_transfer_into_inactive_location_is_blocked(): void
    {
        $sourceLoc = $this->createLocation('Active Source', 'ACT-SRC', 'active');
        $inactiveLoc = $this->createLocation('Inactive Dest', 'INACT-DST', 'inactive');
        $item = $this->createItem('Latex Gloves', 'GLV-001', $sourceLoc, 100);

        $response = $this->actingAs($this->staff)
            ->post(route('inventory.transfers.store'), [
                'source_location_id' => $sourceLoc->id,
                'destination_location_id' => $inactiveLoc->id,
                'notes' => 'Attempting transfer to inactive destination',
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 10],
                ],
            ]);

        $response->assertSessionHasErrors('destination_location_id');
        $this->assertEquals(0, StockTransfer::where('destination_location_id', $inactiveLoc->id)->count());
    }

    /**
     * Requirement 11: Transfer OUT of inactive location is allowed.
     */
    public function test_11_transfer_out_of_inactive_location_is_allowed(): void
    {
        $inactiveSource = $this->createLocation('Decommissioned Vault', 'VAULT-OLD', 'inactive');
        $activeDest = $this->createLocation('Active Central Storeroom', 'STOREROOM-NEW', 'active');
        $item = $this->createItem('Surgical Blades #10', 'BLD-010', $inactiveSource, 50);

        // Initiate transfer out of inactive source to active destination
        $response = $this->actingAs($this->staff)
            ->post(route('inventory.transfers.store'), [
                'source_location_id' => $inactiveSource->id,
                'destination_location_id' => $activeDest->id,
                'notes' => 'Evacuating inventory from decommissioned vault',
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 20],
                ],
            ]);

        $response->assertRedirect(route('inventory.transfers.index'));
        $response->assertSessionHas('success');

        $transfer = StockTransfer::where('source_location_id', $inactiveSource->id)
            ->where('destination_location_id', $activeDest->id)
            ->first();

        $this->assertNotNull($transfer);
        $this->assertSame('in_transit', $transfer->status);

        // Source quantity decreased by 20 (now 30)
        $this->assertEquals(30, ItemStockLevel::where('storage_location_id', $inactiveSource->id)->where('item_id', $item->id)->value('quantity'));

        // Receive transfer into active destination
        $receiveResponse = $this->actingAs($this->staff)
            ->post(route('inventory.transfers.receive', $transfer), [
                'lines' => [
                    ['line_id' => $transfer->lines->first()->id, 'received_quantity' => 20],
                ],
            ]);

        $receiveResponse->assertSessionHas('success');
        $this->assertEquals(20, ItemStockLevel::where('storage_location_id', $activeDest->id)->where('item_id', $item->id)->value('quantity'));
    }

    /**
     * Requirement 12: Stock release/issuance from inactive location is allowed.
     */
    public function test_12_stock_release_issuance_from_inactive_location_is_allowed(): void
    {
        $inactiveLoc = $this->createLocation('Old Dispensary', 'OLD-DISP', 'inactive');
        $item = $this->createItem('Ethanol 70%', 'ETH-70', $inactiveLoc, 40);

        // Deplete / release stock outbound (delta < 0)
        $this->automationService->adjustStockLevel(
            $item->id,
            $inactiveLoc->id,
            null,
            -15
        );

        $this->assertEquals(25, ItemStockLevel::where('storage_location_id', $inactiveLoc->id)->where('item_id', $item->id)->value('quantity'));
    }

    /**
     * Requirement 13: Historical transactions remain accessible.
     */
    public function test_13_historical_transactions_remain_accessible(): void
    {
        $location = $this->createLocation('Historical Room', 'HIST-01', 'active');
        $item = $this->createItem('Saline Solution', 'SAL-001', $location, 100);

        // Create a historical movement
        $movement = StockMovement::create([
            'item_id' => $item->id,
            'from_location_id' => $location->id,
            'to_location_id' => null,
            'quantity' => 10,
            'movement_type' => MovementType::StockOut->value,
            'user_id' => $this->staff->id,
            'notes' => 'Pre-deactivation historical movement',
        ]);

        // Deactivate location
        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'End of year archive',
            ]);

        // Movement still references the location and can be queried
        $historical = StockMovement::where('from_location_id', $location->id)->first();
        $this->assertNotNull($historical);
        $this->assertSame($movement->id, $historical->id);
        $this->assertSame('inactive', $historical->fromLocation->status);
    }

    /**
     * Requirement 14: Active location continues receiving normally.
     */
    public function test_14_active_location_continues_receiving_normally(): void
    {
        $activeLoc = $this->createLocation('Central Warehouse Bay 1', 'CWH-01', 'active');
        $supplier = $this->createSupplier();
        $item = $this->createItem('Bandages', 'BND-001', $activeLoc, 0);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-ACTIVE-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_cost' => 500.00,
            'order_date' => now(),
            'created_by_id' => $this->admin->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 10,
            'received_quantity' => 0,
            'unit_cost' => 50.00,
            'unit_price' => 50.00,
            'subtotal' => 500.00,
            'line_status' => 'pending',
        ]);

        $this->receiveInto($po, $activeLoc, 10);
        $this->assertSame(PurchaseOrderStatus::Fulfilled->value, $po->fresh()->status);
        $this->assertEquals(10, ItemStockLevel::where('storage_location_id', $activeLoc->id)->where('item_id', $item->id)->value('quantity'));
    }

    /**
     * Requirement 15: Reactivated location can receive again.
     */
    public function test_15_reactivated_location_can_receive_again(): void
    {
        $location = $this->createLocation('Reactivatable Bay', 'RE-BAY-01', 'inactive');
        $supplier = $this->createSupplier();
        $item = $this->createItem('Infusion Sets', 'INF-001', $location, 0);

        // Super Admin reactivates location
        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'active',
                'reason' => 'Renovation completed, ready to receive inventory',
            ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-REACTIVATED-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_cost' => 800.00,
            'order_date' => now(),
            'created_by_id' => $this->admin->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 16,
            'received_quantity' => 0,
            'unit_cost' => 50.00,
            'unit_price' => 50.00,
            'subtotal' => 800.00,
            'line_status' => 'pending',
        ]);

        // Receiving now succeeds into reactivated location
        $this->receiveInto($po, $location, 16);
        $this->assertEquals(16, ItemStockLevel::where('storage_location_id', $location->id)->where('item_id', $item->id)->value('quantity'));
    }

    /**
     * Requirement 16: Direct API status bypass by non-super-admin returns 403.
     */
    public function test_16_direct_api_status_bypass_by_non_super_admin_returns_403(): void
    {
        $location = $this->createLocation('API Test Location', 'API-LOC', 'active', 'warehouse');

        // Non-super-admin (admin) attempts to update status via API
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->putJson("/api/v1/storage-locations/{$location->id}", [
            'name' => 'API Test Location Updated',
            'code' => 'API-LOC',
            'type' => 'warehouse',
            'status' => 'inactive',
        ]);

        $response->assertStatus(403);
        $this->assertSame('active', $location->fresh()->status);

        // Super Admin succeeds via API
        Sanctum::actingAs($this->superAdmin, ['*']);
        $superResponse = $this->putJson("/api/v1/storage-locations/{$location->id}", [
            'name' => 'API Test Location Updated',
            'code' => 'API-LOC',
            'type' => 'warehouse',
            'status' => 'inactive',
        ]);

        $superResponse->assertOk();
        $this->assertSame('inactive', $location->fresh()->status);
    }

    /**
     * Requirement 17: Audit trail records activation and deactivation.
     */
    public function test_17_audit_trail_records_activation_and_deactivation(): void
    {
        $location = $this->createLocation('Auditable Location', 'AUD-01', 'active');

        // Deactivate
        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Deactivating for restructuring',
            ]);

        $deactivationLog = AuditLog::where('action', AuditAction::UpdatedStorageLocationStatus->value)
            ->where('target_id', $location->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($deactivationLog);
        $this->assertSame($this->superAdmin->id, $deactivationLog->user_id);
        $this->assertStringContainsString('inactive', $deactivationLog->description);

        // Reactivate
        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'active',
                'reason' => 'Reactivating location',
            ]);

        $reactivationLog = AuditLog::where('action', AuditAction::UpdatedStorageLocationStatus->value)
            ->where('target_id', $location->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($reactivationLog);
        $this->assertSame($this->superAdmin->id, $reactivationLog->user_id);
        $this->assertStringContainsString('active', $reactivationLog->description);
    }

    /**
     * Requirement 18: Pending POs are handled without deleting history.
     */
    public function test_18_pending_purchase_orders_handled_without_deleting_history(): void
    {
        $location = $this->createLocation('Pending PO Warehouse', 'PPO-01', 'active');
        $supplier = $this->createSupplier();
        $item = $this->createItem('Catheters', 'CAT-001', $location, 0);

        // PO created while location was active
        $po = PurchaseOrder::create([
            'po_number' => 'PO-PENDING-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_cost' => 1200.00,
            'order_date' => now(),
            'created_by_id' => $this->admin->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 24,
            'received_quantity' => 0,
            'unit_cost' => 50.00,
            'unit_price' => 50.00,
            'subtotal' => 1200.00,
            'line_status' => 'pending',
        ]);

        // Location gets deactivated
        $this->actingAs($this->superAdmin)
            ->patch(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Emergency closure',
            ]);

        // PO still exists with all historical data
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'po_number' => 'PO-PENDING-001']);
        $this->assertDatabaseHas('po_line_items', ['purchase_order_id' => $po->id, 'ordered_quantity' => 24]);

        // Receiving into the inactive location is blocked without changing the PO.
        try {
            app(GoodsReceiptService::class)->receiveOrder($po, [
                'destination_location_id' => $location->id,
                'lines' => [['po_line_id' => $po->lines()->firstOrFail()->id, 'received_quantity' => 24,
                    'batch_number' => 'LOT-INACTIVE-24', 'expiry_date' => now()->addYear()->toDateString()]],
            ], $this->staff);
            $this->fail('Inactive destination was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }
        $this->assertSame(PurchaseOrderStatus::Approved->value, $po->fresh()->status);
    }

    /**
     * Requirement 19: Location search/filters can find inactive locations.
     */
    public function test_19_location_search_and_filters_can_find_inactive_locations(): void
    {
        $active1 = $this->createLocation('Active Alpha', 'ALPHA-01', 'active');
        $inactive1 = $this->createLocation('Inactive Omega', 'OMEGA-01', 'inactive');

        // Filter inactive
        $responseInactive = $this->actingAs($this->superAdmin)
            ->get(route('inventory.warehousing.locations', ['status' => 'inactive']));

        $responseInactive->assertOk();
        $this->assertTrue($responseInactive->viewData('locations')->contains('id', $inactive1->id));
        $this->assertFalse($responseInactive->viewData('locations')->contains('id', $active1->id));

        // Filter active
        $responseActive = $this->actingAs($this->superAdmin)
            ->get(route('inventory.warehousing.locations', ['status' => 'active']));

        $responseActive->assertOk();
        $this->assertTrue($responseActive->viewData('locations')->contains('id', $active1->id));
        $this->assertFalse($responseActive->viewData('locations')->contains('id', $inactive1->id));
    }

    /**
     * Requirement 20: FIFO/FEFO outbound allocation from inactive location remains correct.
     */
    public function test_20_fifo_fefo_outbound_allocation_from_inactive_location_remains_correct(): void
    {
        $inactiveLoc = $this->createLocation('FEFO Inactive Bay', 'FEFO-INACT', 'inactive');
        $item = $this->createItem('Insulin 100IU', 'INS-100', $inactiveLoc, 0);

        // Batch 1: Expiring in 10 days
        $batch1 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-EXP-SOON',
            'quantity' => 20,
            'remaining_quantity' => 20,
            'expiry_date' => Carbon::today()->addDays(10),
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'item_batch_id' => $batch1->id,
            'storage_location_id' => $inactiveLoc->id,
            'quantity' => 20,
            'reserved_quantity' => 0,
        ]);

        // Batch 2: Expiring in 60 days
        $batch2 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-EXP-LATER',
            'quantity' => 30,
            'remaining_quantity' => 30,
            'expiry_date' => Carbon::today()->addDays(60),
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'item_batch_id' => $batch2->id,
            'storage_location_id' => $inactiveLoc->id,
            'quantity' => 30,
            'reserved_quantity' => 0,
        ]);

        // Allocate 15 units via FEFO from the inactive location
        $allocations = $this->automationService->allocateFefo($item->id, $inactiveLoc->id, 15);

        // The earliest expiring batch (batch1) should be allocated first
        $this->assertCount(1, $allocations);
        $this->assertSame($batch1->id, $allocations[0]['batch_id']);
        $this->assertSame(15, $allocations[0]['quantity']);

        // Allocate 25 units (spans batch 1 remainder of 20 and 5 from batch 2)
        $allocationsMulti = $this->automationService->allocateFefo($item->id, $inactiveLoc->id, 25);

        $this->assertCount(2, $allocationsMulti);
        $this->assertSame($batch1->id, $allocationsMulti[0]['batch_id']);
        $this->assertSame(20, $allocationsMulti[0]['quantity']);
        $this->assertSame($batch2->id, $allocationsMulti[1]['batch_id']);
        $this->assertSame(5, $allocationsMulti[1]['quantity']);
    }

    /**
     * Requirement 21: AJAX deactivation by Super Admin returns JSON with success and location data.
     */
    public function test_21_ajax_deactivation_by_super_admin_returns_json_and_updates_status(): void
    {
        $location = $this->createLocation('Warehouse Bay 21', 'BAY-21', 'active');

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Facility reorganization and audit',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'location' => [
                    'id' => $location->id,
                    'code' => 'BAY-21',
                    'name' => 'Warehouse Bay 21',
                    'status' => 'inactive',
                ],
            ]);

        $this->assertSame('inactive', $location->fresh()->status);
        $this->assertTrue($location->fresh()->isInactive());

        $this->assertDatabaseHas('storage_locations', [
            'id' => $location->id,
            'status' => 'inactive',
        ]);
    }

    /**
     * Requirement 22: AJAX deactivation by Admin or Staff is rejected with 403 JSON.
     */
    public function test_22_ajax_deactivation_by_non_super_admin_is_rejected_with_403_json(): void
    {
        $location = $this->createLocation('Warehouse Bay 22', 'BAY-22', 'active');

        $responseAdmin = $this->actingAs($this->admin)
            ->patchJson(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Unauthorized admin attempt',
            ]);

        $responseAdmin->assertStatus(403);
        $this->assertSame('active', $location->fresh()->status);

        $responseStaff = $this->actingAs($this->staff)
            ->patchJson(route('inventory.storage-locations.status', $location), [
                'status' => 'inactive',
                'reason' => 'Unauthorized staff attempt',
            ]);

        $responseStaff->assertStatus(403);
        $this->assertSame('active', $location->fresh()->status);
    }

    /**
     * Requirement 23: AJAX status update with invalid status returns 422 JSON and leaves status unchanged.
     */
    public function test_23_ajax_status_update_with_invalid_data_returns_422_json_and_leaves_status_unchanged(): void
    {
        $location = $this->createLocation('Warehouse Bay 23', 'BAY-23', 'active');

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('inventory.storage-locations.status', $location), [
                'status' => 'invalid_status_value',
                'reason' => 'Testing invalid status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('active', $location->fresh()->status);
    }

    /**
     * Requirement 24: AJAX reactivation by Super Admin returns JSON and restores active status.
     */
    public function test_24_ajax_reactivation_by_super_admin_returns_json_and_updates_status(): void
    {
        $location = $this->createLocation('Warehouse Bay 24', 'BAY-24', 'inactive');

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('inventory.storage-locations.status', $location), [
                'status' => 'active',
                'reason' => 'Renovation completed, restoring location',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'location' => [
                    'id' => $location->id,
                    'code' => 'BAY-24',
                    'name' => 'Warehouse Bay 24',
                    'status' => 'active',
                ],
            ]);

        $this->assertSame('active', $location->fresh()->status);
        $this->assertTrue($location->fresh()->isActive());
    }

    /**
     * Requirement 25: Super Admin viewing warehousing locations page sees deactivate modal and button.
     */
    public function test_25_super_admin_viewing_warehousing_locations_page_sees_deactivate_modal_and_button(): void
    {
        $location = $this->createLocation('Warehouse Bay 25', 'BAY-25', 'active');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('inventory.warehousing.locations'));

        $response->assertOk();
        $response->assertSee('btn-deactivate-location-' . $location->id, false);
        $response->assertSee('Are you sure you want to deactivate this storage location?', false);
        $response->assertSee('Are you sure you want to deactivate ' . $location->code, false);
        $response->assertSee('Yes, Deactivate Location', false);
    }

    /**
     * Requirement 26: Super Admin viewing storage locations registry sees deactivate button with confirmation.
     */
    public function test_26_super_admin_viewing_storage_locations_registry_sees_deactivate_button_with_confirmation(): void
    {
        $location = $this->createLocation('Warehouse Bay 26', 'BAY-26', 'active');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('inventory.storage-locations'));

        $response->assertOk();
        $response->assertSee('Deactivate', false);
        $response->assertSee('Are you sure you want to deactivate this storage location?', false);
        $response->assertSee('Are you sure you want to deactivate ' . $location->code, false);
        $response->assertSee('Yes, Deactivate Location', false);
    }
}
