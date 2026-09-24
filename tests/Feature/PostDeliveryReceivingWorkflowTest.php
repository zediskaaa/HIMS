<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\NotificationDestination;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\InventorySerial;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseTask;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\QualityControlService;
use App\Services\InventoryAutomationService;
use App\Services\InventoryReportService;
use App\Services\Logistics\InspectionAcceptanceService;
use App\Services\Warehouse\WarehouseTaskService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PostDeliveryReceivingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $receivingClerk;
    private User $qcInspector;
    private User $warehouseStaff;
    private User $procurementOfficer;
    private Supplier $supplier;
    private StorageLocation $quarantineLocation;
    private StorageLocation $stagingLocation;
    private StorageLocation $mainWarehouseLocation;
    private GoodsReceiptService $receiptService;
    private QualityControlService $qcService;
    private WarehouseTaskService $taskService;
    private InventoryAutomationService $automationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quarantineLocation = StorageLocation::firstOrCreate(
            ['code' => 'LOC-QUARANTINE'],
            [
                'name' => 'Receiving Quarantine Holding',
                'type' => 'holding',
                'status' => 'active',
                'is_quarantine' => true,
                'is_damaged_stock' => false,
                'barcode_value' => 'LOC-QUARANTINE-01',
            ]
        );

        $this->stagingLocation = StorageLocation::firstOrCreate(
            ['code' => 'LOC-STAGING'],
            [
                'name' => 'Receiving Staging Bay',
                'type' => 'staging',
                'status' => 'active',
                'is_quarantine' => false,
                'is_receiving_staging' => true,
                'is_damaged_stock' => false,
                'barcode_value' => 'LOC-STAGING-01',
            ]
        );

        $this->mainWarehouseLocation = StorageLocation::firstOrCreate(
            ['code' => 'LOC-MAIN-01'],
            [
                'name' => 'Main Pharmacy Central Bay',
                'type' => 'bin',
                'status' => 'active',
                'is_quarantine' => false,
                'is_damaged_stock' => false,
                'barcode_value' => 'LOC-MAIN-01',
            ]
        );

        $this->supplier = Supplier::create([
            'name' => 'Metro Drug Distribution PH',
            'contact_person' => 'Eduardo Santos',
            'email' => 'deliveries@metrodrug.com.ph',
            'phone' => '+63289001122',
            'address' => 'Makati City, Philippines',
            'status' => 'active',
            'accreditation_status' => 'approved',
        ]);

        $this->receivingClerk = User::factory()->create([
            'name' => 'Dock Receiving Clerk',
            'role' => UserRole::WarehouseStaff,
            'status' => 'active',
        ]);

        $this->qcInspector = User::factory()->create([
            'name' => 'QA / QC Pharmacist',
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $this->warehouseStaff = User::factory()->create([
            'name' => 'Floor Warehouse Operator',
            'role' => UserRole::WarehouseStaff,
            'status' => 'active',
        ]);

        $this->procurementOfficer = User::factory()->create([
            'name' => 'Procurement Buyer',
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $this->receiptService = app(GoodsReceiptService::class);
        $this->qcService = app(QualityControlService::class);
        $this->taskService = app(WarehouseTaskService::class);
        $this->automationService = app(InventoryAutomationService::class);
    }

    /**
     * 1. PURCHASE ORDER READY FOR DELIVERY
     * An unapproved PO cannot have goods received against it.
     */
    public function test_unapproved_purchase_order_cannot_be_received(): void
    {
        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'MED-PARA-500',
            'unit' => 'tablet',
            'quantity_on_hand' => 0,
            'unit_cost' => 2.50,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $draftPo = PurchaseOrder::create([
            'po_number' => 'PO-DRAFT-001',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Draft->value,
            'total_amount' => 500.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $draftPo->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 200,
            'received_quantity' => 0,
            'unit_price' => 2.50,
            'total_line_amount' => 500.00,
            'purchase_unit' => 'box',
            'conversion_factor' => 10,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('must be approved before receiving');

        $this->receiptService->receiveOrder($draftPo, [
            'carrier_name' => '2GO Logistics',
            'waybill_number' => 'WB-12345',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 20,
                    'batch_number' => 'LOT-001',
                    'expiry_date' => now()->addYear()->toDateString(),
                ]
            ]
        ], $this->receivingClerk);
    }

    /**
     * 2 & 3 & 8. PACK-TO-UNIT CONVERSION, QUARANTINE ROUTING, AND PO FULFILLMENT
     * Receiving 4 packs of 100 units increases quarantined stock by 400 base units, not 4.
     * PO status becomes fulfilled and available stock remains 0 until QC.
     */
    public function test_full_delivery_with_pack_to_unit_conversion_and_quarantine_routing(): void
    {
        $item = InventoryItem::create([
            'name' => 'Latex Examination Gloves (Medium)',
            'sku' => 'GLV-MED-100',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'unit_cost' => 4.00,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-GLV-01',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 1600.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 4, // 4 packs
            'received_quantity' => 0,
            'unit_price' => 400.00,
            'total_line_amount' => 1600.00,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100, // 1 pack = 100 pieces
        ]);

        $grn = $this->receiptService->receiveOrder($po, [
            'carrier_name' => 'LBC Express',
            'waybill_number' => 'LBC-998877',
            'packing_slip_number' => 'DR-GLV-001',
            'destination_location_id' => $this->mainWarehouseLocation->id,
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 4,
                    'destination_location_id' => $this->mainWarehouseLocation->id,
                    'batch_number' => 'LOT-GLV-2026-A',
                    'expiry_date' => now()->addYears(2)->toDateString(),
                    'item_condition' => 'good',
                ]
            ]
        ], $this->receivingClerk);

        $this->assertNotNull($grn);
        $this->assertSame('quarantined', $grn->status);

        $line = $grn->lines()->first();
        $this->assertEquals(4, $line->received_quantity);
        $this->assertEquals(400, $line->received_base_quantity);
        $this->assertEquals(4, $line->quarantined_quantity);

        // Check inventory balance: Quarantine has 400 pieces in quarantined_quantity, unrestricted available stock is 0
        $quarantineStock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $this->quarantineLocation->id)
            ->sum('quarantined_quantity');
        $availableStock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', '!=', $this->quarantineLocation->id)
            ->sum('quantity');

        $this->assertEquals(400, $quarantineStock);
        $this->assertEquals(0, $availableStock);

        // PO line and PO status updated
        $poLine->refresh();
        $this->assertEquals(4, $poLine->received_quantity);
        $this->assertSame(PurchaseOrderStatus::UnderInspection, $po->refresh()->statusEnum());

        // Stock movement recorded in ledger as Quarantine
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::Quarantine->value,
            'quantity' => 400,
            'to_location_id' => $this->quarantineLocation->id,
        ]);

        $this->actingAs($this->receivingClerk)
            ->get(route('inventory.receiving.show', $grn))
            ->assertOk()
            ->assertSee('Delivery Receipt Details')
            ->assertSee('DR-GLV-001')
            ->assertSee('PO-2026-GLV-01')
            ->assertSee('Metro Drug Distribution PH')
            ->assertSee('Dock Receiving Clerk')
            ->assertSee('Main Pharmacy Central Bay')
            ->assertSee('Latex Examination Gloves (Medium)')
            ->assertSee('GLV-MED-100')
            ->assertSee('LOT-GLV-2026-A')
            ->assertSee($line->expiry_date->format('M d, Y'))
            ->assertSee('4 packs')
            ->assertSee('₱400.00/pack')
            ->assertSee('₱1,600.00')
            ->assertDontSee('Sensor Logger')
            ->assertDontSee('Transit Temperature')
            ->assertDontSee('Bluetooth')
            ->assertDontSee('USB');
    }

    /**
     * 4 & 5. PARTIAL DELIVERY AND SHORT DELIVERY
     * Receiving only 3 packs out of 10 leaves 7 packs open.
     * PO status is partially_fulfilled.
     */
    public function test_partial_delivery_and_short_delivery_leaves_po_balance_open(): void
    {
        $item = InventoryItem::create([
            'name' => 'Ceftriaxone 1g Vial',
            'sku' => 'ANT-CEF-1G',
            'unit' => 'vial',
            'quantity_on_hand' => 0,
            'unit_cost' => 120.00,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-CEF-002',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 12000.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 10, // 10 boxes
            'received_quantity' => 0,
            'unit_price' => 1200.00,
            'total_line_amount' => 12000.00,
            'purchase_unit' => 'box',
            'conversion_factor' => 10, // 1 box = 10 vials
        ]);

        // First receipt: Deliver 3 boxes (30 vials)
        $this->receiptService->receiveOrder($po, [
            'carrier_name' => 'Internal Transport',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 3,
                    'batch_number' => 'LOT-CEF-P1',
                    'expiry_date' => now()->addMonths(18)->toDateString(),
                    'item_condition' => 'good',
                    'discrepancy_type' => 'shortage',
                    'discrepancy_action' => 'quarantine',
                    'discrepancy_notes' => 'Partial shipment delivered; 7 boxes backordered by supplier.',
                ]
            ]
        ], $this->receivingClerk);

        $poLine->refresh();
        $this->assertEquals(3, $poLine->received_quantity);
        $this->assertSame(PurchaseOrderStatus::UnderInspection, $po->refresh()->statusEnum());

        // Second receipt: Deliver remaining 7 boxes (70 vials)
        $this->receiptService->receiveOrder($po, [
            'carrier_name' => 'Internal Transport',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 7,
                    'batch_number' => 'LOT-CEF-P2',
                    'expiry_date' => now()->addMonths(18)->toDateString(),
                    'item_condition' => 'good',
                ]
            ]
        ], $this->receivingClerk);

        $poLine->refresh();
        $this->assertEquals(10, $poLine->received_quantity);
        $this->assertSame(PurchaseOrderStatus::UnderInspection, $po->refresh()->statusEnum());

        // Total quarantined stock = 30 + 70 = 100 vials
        $quarantineStock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $this->quarantineLocation->id)
            ->sum('quarantined_quantity');
        $this->assertEquals(100, $quarantineStock);
    }

    /**
     * 4. OVER-DELIVERY TOLERANCE RULES
     * Within +5% tolerance is allowed; exceeding +5% is blocked.
     */
    public function test_over_delivery_tolerance_enforced(): void
    {
        $item = InventoryItem::create([
            'name' => 'Disposable Syringes 5ml',
            'sku' => 'SYR-5ML',
            'unit' => 'piece',
            'unit_cost' => 8.00,
            'is_batch_tracked' => false,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-SYR-003',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 800.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 100,
            'received_quantity' => 0,
            'unit_price' => 8.00,
            'total_line_amount' => 800.00,
            'purchase_unit' => 'piece',
            'conversion_factor' => 1,
        ]);

        // Attempting to deliver 106 units (> +5% tolerance of 105) throws ValidationException
        $this->expectException(ValidationException::class);

        $this->receiptService->receiveOrder($po, [
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 106,
                    'item_condition' => 'good',
                ]
            ]
        ], $this->receivingClerk);
    }

    /**
     * 4. DAMAGED ITEM CONDITION FLAGGED
     * Packaging damage is recorded on line item and quarantined for inspection.
     */
    public function test_damaged_item_condition_recorded_and_quarantined(): void
    {
        $item = InventoryItem::create([
            'name' => 'Intravenous Infusion Set',
            'sku' => 'INF-SET-01',
            'unit' => 'set',
            'unit_cost' => 45.00,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-INF-004',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 2250.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 50,
            'received_quantity' => 0,
            'unit_price' => 45.00,
            'total_line_amount' => 2250.00,
            'purchase_unit' => 'set',
            'conversion_factor' => 1,
        ]);

        $grn = $this->receiptService->receiveOrder($po, [
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 50,
                    'batch_number' => 'LOT-INF-CRUSH-01',
                    'expiry_date' => now()->addYears(3)->toDateString(),
                    'item_condition' => 'damaged',
                    'discrepancy_type' => 'damage',
                    'discrepancy_action' => 'quarantine',
                    'discrepancy_notes' => 'Outer carton crushed in transit; inner seals require sterility testing.',
                ]
            ]
        ], $this->receivingClerk);

        $line = $grn->lines()->first();
        $this->assertSame('damaged', $line->item_condition);
        $this->assertSame('damage', $line->discrepancy_type);
        $this->assertSame('quarantine', $line->discrepancy_action);
        $this->assertStringContainsString('Outer carton crushed', $line->discrepancy_notes);
        $discrepancyNotice = $this->procurementOfficer->notifications()->get()
            ->first(fn ($notification) => str_contains($notification->data['title'], 'Discrepancy'));
        $this->assertNotNull($discrepancyNotice);
        $this->assertSame(route('inventory.receiving.show', $grn),
            NotificationDestination::GoodsReceipt->url($this->procurementOfficer, $discrepancyNotice->data['route_parameters']));
    }

    /**
     * 4. DUPLICATE SERIAL NUMBER BLOCKED
     * The system rejects receiving an item with an existing registered serial number.
     */
    public function test_duplicate_serial_number_blocked(): void
    {
        $item = InventoryItem::create([
            'name' => 'Defibrillator Unit Pad Module',
            'sku' => 'DEFIB-MOD-01',
            'unit' => 'unit',
            'unit_cost' => 15000.00,
            'is_batch_tracked' => false,
            'is_serial_tracked' => true,
            'status' => 'active',
        ]);

        // Pre-existing serial
        InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'SN-DEFIB-8888',
            'status' => 'available',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DEFIB-005',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 15000.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 1,
            'received_quantity' => 0,
            'unit_price' => 15000.00,
            'total_line_amount' => 15000.00,
            'purchase_unit' => 'unit',
            'conversion_factor' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $this->receiptService->receiveOrder($po, [
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 1,
                    'serial_number' => 'SN-DEFIB-8888',
                ]
            ]
        ], $this->receivingClerk);
    }

    /**
     * 6 & 7. QC RELEASE AND PUT-AWAY WORKFLOW
     * Releasing stock from QC moves it from LOC-QUARANTINE to LOC-STAGING,
     * creates a PutAway task to the target storage location,
     * and completing the PutAway task places stock into LOC-MAIN-01 as available inventory.
     */
    public function test_qc_release_to_staging_and_put_away_to_available_storage(): void
    {
        $item = InventoryItem::create([
            'name' => 'Amoxicillin 500mg Capsules',
            'sku' => 'MED-AMOX-500',
            'unit' => 'capsule',
            'quantity_on_hand' => 0,
            'unit_cost' => 3.50,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-AMOX-006',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 1750.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 5, // 5 boxes of 100 capsules = 500 capsules
            'received_quantity' => 0,
            'unit_price' => 350.00,
            'total_line_amount' => 1750.00,
            'purchase_unit' => 'box',
            'conversion_factor' => 100,
        ]);

        $grn = $this->receiptService->receiveOrder($po, [
            'destination_location_id' => $this->mainWarehouseLocation->id,
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 5,
                    'destination_location_id' => $this->mainWarehouseLocation->id,
                    'batch_number' => 'LOT-AMOX-2026-B1',
                    'expiry_date' => now()->addMonths(24)->toDateString(),
                    'item_condition' => 'good',
                ]
            ]
        ], $this->receivingClerk);

        $line = $grn->lines()->first();
        $inspection = $line->inspections()->first();
        $this->assertNotNull($inspection);

        // 1. Before QC Release: 500 capsules in LOC-QUARANTINE quarantined_quantity, 0 in LOC-STAGING, 0 in LOC-MAIN-01
        $this->assertEquals(500, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->quarantineLocation->id)->sum('quarantined_quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->stagingLocation->id)->sum('quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->mainWarehouseLocation->id)->sum('quantity'));

        // 2. Perform QC Release (5 boxes = 500 capsules)
        $this->qcService->releaseLot(
            $inspection,
            5,
            $this->mainWarehouseLocation->id,
            $this->qcInspector,
            'Assay passed. CoA verified.'
        );

        // After QC Release: Stock moved from LOC-QUARANTINE to LOC-STAGING!
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->quarantineLocation->id)->sum('quarantined_quantity'));
        $this->assertEquals(500, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->stagingLocation->id)->sum('in_transit_quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->stagingLocation->id)->sum('quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->mainWarehouseLocation->id)->sum('quantity'));

        // Put-Away task generated
        $task = WarehouseTask::where('task_type', WarehouseTaskType::PutAway)
            ->where('source_location_id', $this->stagingLocation->id)
            ->where('destination_location_id', $this->mainWarehouseLocation->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($task);
        $this->assertEquals(500, $task->requested_quantity);

        // 3. Put-Away execution: Complete task from staging to main rack with barcode scan verification
        $this->taskService->start($task, $this->warehouseStaff);
        $this->taskService->scan($task, $this->stagingLocation->barcode_value, $this->warehouseStaff);
        $this->taskService->scan($task, $item->sku, $this->warehouseStaff);
        $this->taskService->scan($task, $this->mainWarehouseLocation->barcode_value, $this->warehouseStaff);
        $this->taskService->complete($task, 500, $this->warehouseStaff);

        // After Put-Away: Staging has 0, LOC-MAIN-01 has 500 available stock!
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->stagingLocation->id)->sum('in_transit_quantity'));
        $this->assertEquals(500, ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $this->mainWarehouseLocation->id)->sum('quantity'));
        $this->assertSame(WarehouseTaskStatus::Completed, $task->refresh()->status);

        // Invariant: ORDERED ≠ DELIVERED ≠ ACCEPTED ≠ STORED ≠ AVAILABLE
        // At this point, stock has successfully traversed all stages into Available!
    }

    /**
     * 4 & 5. QC REJECTION MOVES STOCK TO BLOCKED STATUS
     * Non-conforming stock is moved to blocked balance and batch marked as rejected.
     */
    public function test_qc_rejection_moves_stock_to_blocked_inventory(): void
    {
        $item = InventoryItem::create([
            'name' => 'Insulin Glargine 100 IU/ml',
            'sku' => 'COLD-INS-01',
            'unit' => 'vial',
            'unit_cost' => 450.00,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-INS-007',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 4500.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 10,
            'received_quantity' => 0,
            'unit_price' => 450.00,
            'total_line_amount' => 4500.00,
            'purchase_unit' => 'vial',
            'conversion_factor' => 1,
        ]);

        $grn = $this->receiptService->receiveOrder($po, [
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 10,
                    'batch_number' => 'LOT-INS-TEMP-FAIL',
                    'expiry_date' => now()->addMonths(12)->toDateString(),
                    'item_condition' => 'compromised',
                ]
            ]
        ], $this->receivingClerk);

        $line = $grn->lines()->first();
        $inspection = $line->inspections()->first();

        // Reject lot due to cold-chain temperature excursion
        $this->qcService->rejectLot(
            $inspection,
            10,
            'Temp logger showed 14°C excursion during transit (limit 2-8°C). Protein degraded.',
            $this->qcInspector
        );

        $inspection->refresh();
        $this->assertSame('rejected', $inspection->inspection_status);
        $this->assertEquals(10, $inspection->rejected_quantity);

        // Batch marked as rejected
        $batch = ItemBatch::where('batch_number', 'LOT-INS-TEMP-FAIL')->first();
        $this->assertSame('rejected', $batch->status);

        // Ledger reflects QualityReject movement
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::QualityReject->value,
            'quantity' => 10,
            'from_location_id' => $this->quarantineLocation->id,
        ]);
        $rejectionNotice = $this->procurementOfficer->notifications()->get()
            ->first(fn ($notification) => str_contains($notification->data['title'], 'Rejected'));
        $this->assertNotNull($rejectionNotice);
        $this->assertSame(route('inventory.receiving.show', $grn),
            NotificationDestination::GoodsReceipt->url($this->procurementOfficer, $rejectionNotice->data['route_parameters']));
    }

    /**
     * 11 & 12. HTTP CONTROLLER AND PERMISSIONS
     * Verifies HTTP endpoints enforce permissions and render the lifecycle views.
     */
    public function test_receiving_http_routes_and_views(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Saline Solution 500ml',
            'sku' => 'SAL-500ML',
            'unit' => 'bottle',
            'unit_cost' => 35.00,
            'is_batch_tracked' => true,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-SAL-008',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'total_amount' => 700.00,
            'user_id' => $this->procurementOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'ordered_quantity' => 20,
            'received_quantity' => 0,
            'unit_price' => 35.00,
            'total_line_amount' => 700.00,
            'purchase_unit' => 'bottle',
            'conversion_factor' => 1,
        ]);

        // 1. Unauthenticated cannot access receiving
        $this->get(route('inventory.receiving.index'))
            ->assertRedirect(route('login'));

        // 2. Receiving clerk can access index and submit receipt
        $response = $this->actingAs($this->receivingClerk)->post(route('inventory.receiving.store'), [
            'purchase_order_id' => $po->id,
            'actual_supplier_id' => $this->supplier->id,
            'carrier_name' => 'FastCargo',
            'waybill_number' => 'FC-887711',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'actual_sku' => $item->sku,
                    'actual_purchase_unit' => 'bottle',
                    'received_quantity' => 20,
                    'item_condition' => 'good',
                    'batch_number' => 'LOT-SAL-01',
                    'expiry_date' => now()->addYear()->toDateString(),
                ]
            ]
        ]);

        $response->assertRedirect(route('inventory.receiving.index'));
        $this->assertDatabaseHas('goods_receipt_notes', [
            'purchase_order_id' => $po->id,
            'receipt_status' => 'quarantined',
        ]);

        $grn = GoodsReceiptNote::where('purchase_order_id', $po->id)->first();

        // 3. User with ViewInventory can view details
        $this->actingAs($this->receivingClerk)
            ->get(route('inventory.receiving.show', $grn))
            ->assertOk()
            ->assertSee('Receiving Workflow')
            ->assertSee('Quality inspection')
            ->assertSee('PO-SAL-008');
    }

    public function test_partial_qc_stays_actionable_and_only_put_away_makes_accepted_stock_available(): void
    {
        [$po, $poLine, $item] = $this->makeOrder(4, 100, true);
        $grn = $this->receive($po, $poLine, 4, ['batch_number' => 'PARTIAL-LOT']);
        $line = $grn->lines()->firstOrFail();
        $inspection = $line->inspections()->firstOrFail();

        $this->qcService->releaseLot($inspection, 2, $this->mainWarehouseLocation->id, $this->qcInspector, 'Passed', 'partial-accept-1');
        $this->assertSame('partially_disposed', $inspection->refresh()->inspection_status);
        $this->assertSame(2, $line->refresh()->quarantined_quantity);
        $this->assertEquals(200, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
        $this->assertEquals(200, ItemStockLevel::where('item_id', $item->id)->sum('in_transit_quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
        $this->actingAs($this->qcInspector)->get(route('inventory.qc.index'))->assertSee($item->name);

        $this->qcService->rejectLot($inspection, 1, 'Failed assay', $this->qcInspector, 'partial-reject-1');
        $this->assertSame('partially_disposed', $inspection->refresh()->inspection_status);
        $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector, 'Passed', 'partial-accept-2');
        $this->assertSame('partially_accepted', $inspection->refresh()->inspection_status);
        $this->assertSame(0, $line->refresh()->quarantined_quantity);
        $this->assertSame(3, $line->accepted_quantity);
        $this->assertSame(1, $line->rejected_quantity);
        $this->assertSame(PurchaseOrderStatus::PartiallyFulfilled, $po->refresh()->statusEnum());
        $this->assertEquals(300, ItemStockLevel::where('item_id', $item->id)->sum('in_transit_quantity'));
        $this->assertEquals(100, ItemStockLevel::where('item_id', $item->id)->sum('blocked_quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));

        foreach (WarehouseTask::where('task_type', WarehouseTaskType::PutAway)->get() as $task) {
            $this->taskService->start($task, $this->warehouseStaff);
            $this->taskService->scan($task, $this->stagingLocation->barcode_value, $this->warehouseStaff);
            $this->taskService->scan($task, $item->sku, $this->warehouseStaff);
            $this->taskService->scan($task, $this->mainWarehouseLocation->barcode_value, $this->warehouseStaff);
            $this->taskService->complete($task, $task->requested_quantity, $this->warehouseStaff);
        }
        $this->assertEquals(300, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('in_transit_quantity'));
        $this->assertEquals(100, ItemStockLevel::where('item_id', $item->id)->sum('blocked_quantity'));
        $this->assertEquals(3, $poLine->refresh()->accepted_quantity);
        $this->assertEquals(1, $poLine->remainingQuantity());
        $putAwayNotice = $this->procurementOfficer->notifications()->get()
            ->first(fn ($notification) => str_contains($notification->data['title'], 'Put-Away Completed'));
        $this->assertNotNull($putAwayNotice);
        $this->assertSame(route('inventory.receiving.show', $grn),
            NotificationDestination::GoodsReceipt->url($this->procurementOfficer, $putAwayNotice->data['route_parameters']));

        $row = app(InventoryReportService::class)->receivingReconciliation(Carbon::yesterday())->first();
        $this->assertSame(4, $row['receipt_quantity']);
        $this->assertSame(3, $row['accepted']);
        $this->assertSame(1, $row['rejected']);
        $this->assertSame(300, $row['available_base']);
        $export = $this->actingAs($this->procurementOfficer)
            ->getJson('/inventory/reports/generate?report_type=procurement_expense&format=json&period=30');
        $export->assertOk();
        $this->assertEquals(600, $export->json('data.0.accepted_value'));
        $this->assertEquals(200, $export->json('data.0.outstanding_value'));
    }

    public function test_iar_reconciles_qc_without_posting_inventory_and_duplicate_acceptance_fails(): void
    {
        [$po, $poLine, $item] = $this->makeOrder(4, 100, true);
        $grn = $this->receive($po, $poLine, 4, ['batch_number' => 'IAR-LOT']);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $service = app(InspectionAcceptanceService::class);
        $iar = $service->createFromReceipt($grn, [], $this->receivingClerk);
        $service->performTechnicalInspection($iar, ['inspection_status' => 'in_order'], $this->qcInspector);
        try {
            $service->performCustodialAcceptance($iar, [], $this->warehouseStaff);
            $this->fail('IAR acceptance must wait for QC.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('QC must resolve', $e->getMessage());
        }

        $this->qcService->releaseLot($inspection, 3, $this->mainWarehouseLocation->id, $this->qcInspector);
        $this->qcService->rejectLot($inspection, 1, 'Failed assay', $this->qcInspector);
        $before = StockMovement::count();
        $service->performCustodialAcceptance($iar, [], $this->warehouseStaff);
        $this->assertSame('accepted', $iar->refresh()->status);
        $this->assertSame('partial', $iar->delivery_status);
        $this->assertSame($before, StockMovement::count());
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
        try {
            $service->performCustodialAcceptance($iar, [], $this->warehouseStaff);
            $this->fail('Repeated IAR acceptance must fail.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already accepted', $e->getMessage());
        }
        $this->assertSame($before, StockMovement::count());
    }

    public function test_fully_rejected_delivery_cannot_be_accepted_by_iar_and_can_be_returned_once(): void
    {
        [$po, $poLine, $item] = $this->makeOrder(2);
        $grn = $this->receive($po, $poLine, 2);
        $line = $grn->lines()->firstOrFail();
        $inspection = $line->inspections()->firstOrFail();
        $this->qcService->rejectLot($inspection, 2, 'Damaged seals', $this->qcInspector);
        $this->assertSame(PurchaseOrderStatus::RejectedDelivery, $po->refresh()->statusEnum());
        $this->assertEquals(2, $poLine->refresh()->remainingQuantity());
        $iar = InspectionAcceptanceReport::create([
            'iar_number' => 'IAR-REJECT-'.Str::ulid(), 'goods_receipt_note_id' => $grn->id,
            'purchase_order_id' => $po->id, 'supplier_id' => $this->supplier->id,
            'status' => 'inspected_passed', 'inspected_by_id' => $this->qcInspector->id,
            'iar_date' => now(),
        ]);
        try {
            app(InspectionAcceptanceService::class)->performCustodialAcceptance($iar, [], $this->warehouseStaff);
            $this->fail('Fully rejected delivery must fail IAR acceptance.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('fully rejected', $e->getMessage());
        }
        $this->qcService->returnRejected($line, 2, $this->warehouseStaff, 'return-1');
        $this->qcService->returnRejected($line, 2, $this->warehouseStaff, 'return-1');
        $this->assertSame(2, $line->refresh()->returned_quantity);
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('blocked_quantity'));
        $this->assertSame(1, StockMovement::where('movement_type', MovementType::ReturnToSupplier)->count());
    }

    public function test_duplicate_receipt_and_qc_keys_do_not_post_twice(): void
    {
        [$po, $poLine, $item] = $this->makeOrder(1);
        $data = $this->receiptData($poLine, 1) + ['receipt_key' => 'receipt-once', 'waybill_number' => 'WB-ONCE'];
        $grn = $this->receiptService->receiveOrder($po, $data, $this->receivingClerk);
        $this->assertSame($grn->id, $this->receiptService->receiveOrder($po, $data, $this->receivingClerk)->id);
        $this->assertSame(1, GoodsReceiptNote::where('purchase_order_id', $po->id)->count());
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector, null, 'qc-once');
        $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector, null, 'qc-once');
        $this->assertSame(1, StockMovement::where('movement_type', MovementType::QualityRelease)->count());
        $this->assertSame(1, WarehouseTask::where('task_type', WarehouseTaskType::PutAway)->count());
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('in_transit_quantity'));
    }

    public function test_failed_multi_line_receipt_rolls_back_and_completed_line_cannot_be_reposted(): void
    {
        [$po, $first, $item] = $this->makeOrder(2);
        $secondItem = InventoryItem::create(['name' => 'Second ordered item', 'sku' => 'SECOND-'.Str::ulid(), 'unit' => 'piece', 'status' => 'active', 'is_batch_tracked' => false]);
        $second = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'item_id' => $secondItem->id, 'line_number' => 2,
            'ordered_quantity' => 3, 'received_quantity' => 0, 'unit_price' => 5,
            'total_line_amount' => 15, 'purchase_unit' => 'piece', 'conversion_factor' => 1,
        ]);
        $this->receive($po, $first, 2);
        $this->assertSame(0, $first->refresh()->remainingQuantity());
        $before = GoodsReceiptNote::count();
        try {
            $this->receiptService->receiveOrder($po, ['lines' => [
                $this->receiptData($second, 1)['lines'][0],
                $this->receiptData($first, 1)['lines'][0],
            ]], $this->receivingClerk);
            $this->fail('Completed line must not be received twice.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertSame($before, GoodsReceiptNote::count());
        $this->assertSame(0, $second->refresh()->received_quantity);
        $this->assertEquals(0, ItemStockLevel::where('item_id', $secondItem->id)->sum('quarantined_quantity'));
        $this->receive($po, $second, 3);
        $this->assertSame(3, $second->refresh()->received_quantity);
    }

    public function test_wrong_supplier_sku_and_uom_are_rejected_without_stock(): void
    {
        [$po, $line, $item] = $this->makeOrder(1);
        foreach ([
            ['actual_supplier_id' => $this->supplier->id + 999],
            ['lines' => [['po_line_id' => $line->id, 'received_quantity' => 1, 'actual_sku' => 'WRONG']]],
            ['lines' => [['po_line_id' => $line->id, 'received_quantity' => 1, 'actual_purchase_unit' => 'box']]],
        ] as $override) {
            try {
                $this->receiptService->receiveOrder($po, array_replace_recursive($this->receiptData($line, 1), $override), $this->receivingClerk);
                $this->fail('Mismatched delivery identity must fail.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, GoodsReceiptNote::count());
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
    }

    public function test_expired_stock_is_quarantined_but_cannot_be_accepted(): void
    {
        [$po, $line, $item] = $this->makeOrder(1, 1, true);
        $grn = $this->receive($po, $line, 1, ['batch_number' => 'EXPIRED-LOT', 'expiry_date' => now()->subDay()->toDateString()]);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $this->assertSame('expired', $grn->lines()->firstOrFail()->discrepancy_type);
        try {
            $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector);
            $this->fail('Expired lot cannot be released.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
    }

    public function test_inactive_put_away_destination_rolls_back_qc_decision(): void
    {
        [$po, $line, $item] = $this->makeOrder(1);
        $grn = $this->receive($po, $line, 1);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $this->mainWarehouseLocation->update(['status' => 'inactive']);
        try {
            $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector);
            $this->fail('Inactive destination must be rejected.');
        } catch (DomainException|ValidationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertSame('pending_sample', $inspection->refresh()->inspection_status);
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('in_transit_quantity'));
        $this->assertSame(0, WarehouseTask::count());
    }

    public function test_existing_lot_with_conflicting_expiry_is_rejected_and_its_balance_is_preserved(): void
    {
        [$po, $line, $item] = $this->makeOrder(2, 1, true);
        $firstExpiry = now()->addYear()->toDateString();
        $this->receive($po, $line, 1, ['batch_number' => 'SHARED-LOT', 'expiry_date' => $firstExpiry]);
        $before = GoodsReceiptNote::count();
        try {
            $this->receive($po, $line, 1, ['batch_number' => 'SHARED-LOT', 'expiry_date' => now()->addYears(2)->toDateString()]);
            $this->fail('The same lot cannot be booked with conflicting expiry.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertSame($before, GoodsReceiptNote::count());
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
        $this->assertEquals(1, ItemBatch::where('item_id', $item->id)->where('batch_number', 'SHARED-LOT')->value('initial_quantity'));
    }

    public function test_near_expiry_stock_remains_in_quarantine_until_rejected(): void
    {
        [$po, $line, $item] = $this->makeOrder(1, 1, true);
        $grn = $this->receive($po, $line, 1, ['batch_number' => 'NEAR-LOT', 'expiry_date' => now()->addDays(10)->toDateString()]);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $this->assertSame('near_expiry', $grn->lines()->firstOrFail()->discrepancy_type);
        try {
            $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector);
            $this->fail('Near-expiry lot must not become available.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->qcService->rejectLot($inspection, 1, 'Near expiry rejected', $this->qcInspector);
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('blocked_quantity'));
    }

    public function test_serial_remains_unavailable_until_verified_put_away(): void
    {
        [$po, $line, $item] = $this->makeOrder(1);
        $item->update(['is_serial_tracked' => true, 'gtin' => '04801234567897']);
        $grn = $this->receive($po, $line, 1, ['serial_number' => 'SN-RECEIVING-1']);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $serial = InventorySerial::where('item_id', $item->id)->firstOrFail();
        $this->assertSame('quarantined', $serial->status);
        $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector);
        $this->assertSame('awaiting_put_away', $serial->refresh()->status);
        $task = WarehouseTask::where('task_type', WarehouseTaskType::PutAway)->firstOrFail();
        $this->taskService->start($task, $this->warehouseStaff);
        $this->taskService->scan($task, $this->stagingLocation->barcode_value, $this->warehouseStaff);
        $this->taskService->scan($task, '(01)04801234567897(21)SN-RECEIVING-1', $this->warehouseStaff);
        $this->taskService->scan($task, $this->mainWarehouseLocation->barcode_value, $this->warehouseStaff);
        $this->taskService->complete($task, 1, $this->warehouseStaff);
        $this->assertSame('available', $serial->refresh()->status);
        $this->assertSame(PurchaseOrderStatus::Fulfilled, $po->refresh()->statusEnum());
        $fullNotice = $this->procurementOfficer->notifications()->get()
            ->first(fn ($notification) => str_contains($notification->data['title'], 'Fully Accepted'));
        $this->assertNotNull($fullNotice);
        $this->assertSame(route('inventory.receiving.show', $grn),
            NotificationDestination::GoodsReceipt->url($this->procurementOfficer, $fullNotice->data['route_parameters']));
        $this->assertSame($this->mainWarehouseLocation->id, $serial->storage_location_id);
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
    }

    public function test_legacy_po_action_redirects_without_stock_and_receiving_urls_require_permissions(): void
    {
        [$po, $line, $item] = $this->makeOrder(1);
        $this->actingAs($this->receivingClerk)
            ->post(route('inventory.purchases.receive', $po))
            ->assertRedirect(route('inventory.receiving.index', ['purchase_order_id' => $po->id]));
        $this->assertSame(0, GoodsReceiptNote::count());
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('quantity'));

        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->post(route('inventory.receiving.store'), $this->receiptData($line, 1) + ['purchase_order_id' => $po->id])->assertForbidden();
        $grn = $this->receive($po, $line, 1);
        $deliveryNotice = $this->procurementOfficer->notifications()->get()
            ->first(fn ($notification) => str_contains($notification->data['title'], 'Delivery Recorded'));
        $this->assertNotNull($deliveryNotice);
        $this->assertSame(route('inventory.receiving.show', $grn),
            NotificationDestination::GoodsReceipt->url($this->procurementOfficer, $deliveryNotice->data['route_parameters']));
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $this->actingAs($viewer)->post(route('inventory.qc.release', $inspection), ['accepted_quantity' => 1, 'target_location_id' => $this->mainWarehouseLocation->id])->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.qc.reject', $inspection), ['rejected_quantity' => 1, 'rejection_reason' => 'Bad'])->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.receiving.return', [$grn, $grn->lines()->firstOrFail()]), ['quantity' => 1])->assertForbidden();
        $this->assertSame('pending_sample', $inspection->refresh()->inspection_status);
    }

    public function test_receiving_notifications_resolve_to_the_grn_and_put_away_task(): void
    {
        [$po, $line] = $this->makeOrder(1);
        $grn = $this->receive($po, $line, 1);
        $qcNotice = $this->qcInspector->notifications()->get()->first(fn ($notification) => $notification->data['destination'] === NotificationDestination::QualityControl->value);
        $this->assertNotNull($qcNotice);
        $this->assertSame(route('inventory.qc.index'), NotificationDestination::QualityControl->url($this->qcInspector, $qcNotice->data['route_parameters']));

        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        $this->qcService->releaseLot($inspection, 1, $this->mainWarehouseLocation->id, $this->qcInspector);
        $acceptedNotice = $this->procurementOfficer->notifications()->get()->first(fn ($notification) => $notification->data['destination'] === NotificationDestination::GoodsReceipt->value);
        $this->assertNotNull($acceptedNotice);
        $this->assertSame(route('inventory.receiving.show', $grn), NotificationDestination::GoodsReceipt->url($this->procurementOfficer, $acceptedNotice->data['route_parameters']));
        $task = WarehouseTask::where('task_type', WarehouseTaskType::PutAway)->firstOrFail();
        $taskNotice = $this->warehouseStaff->notifications()->get()->first(fn ($notification) => $notification->data['destination'] === NotificationDestination::WarehouseTask->value);
        $this->assertNotNull($taskNotice);
        $this->assertSame(route('inventory.warehouse-tasks.show', $task), NotificationDestination::WarehouseTask->url($this->warehouseStaff, $taskNotice->data['route_parameters']));
    }

    public function test_balance_helpers_reject_underflow_without_changing_stock(): void
    {
        [$po, $line, $item] = $this->makeOrder(1);
        $grn = $this->receive($po, $line, 1);
        try {
            $this->automationService->adjustQuarantinedStock($item->id, $grn->quarantine_location_id, null, -2);
            $this->fail('Quarantine underflow must be rejected.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertEquals(1, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
        try {
            $this->automationService->releaseReservation($item->id, $grn->quarantine_location_id, null, 1);
            $this->fail('Reservation underflow must be rejected.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertEquals(0, ItemStockLevel::where('item_id', $item->id)->sum('reserved_quantity'));
    }

    /** @return array{PurchaseOrder, PurchaseOrderLine, InventoryItem} */
    private function makeOrder(int $ordered, int $factor = 1, bool $batchTracked = false): array
    {
        $item = InventoryItem::create([
            'name' => 'Receiving regression item', 'sku' => 'RCV-'.Str::ulid(),
            'unit' => 'piece', 'status' => 'active', 'unit_cost' => 2,
            'is_batch_tracked' => $batchTracked,
        ]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-RCV-'.Str::ulid(), 'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'user_id' => $this->procurementOfficer->id,
            'created_by_user_id' => $this->procurementOfficer->id,
            'total_amount' => $ordered * $factor * 2,
            'requested_at' => now(),
        ]);
        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'item_id' => $item->id, 'line_number' => 1,
            'ordered_quantity' => $ordered, 'received_quantity' => 0,
            'unit_price' => $factor * 2, 'total_line_amount' => $ordered * $factor * 2,
            'purchase_unit' => $factor > 1 ? 'pack' : 'piece', 'conversion_factor' => $factor,
        ]);

        return [$po, $line, $item];
    }

    private function receiptData(PurchaseOrderLine $line, int $quantity, array $override = []): array
    {
        return [
            'actual_supplier_id' => $this->supplier->id,
            'lines' => [array_merge([
                'po_line_id' => $line->id, 'received_quantity' => $quantity,
                'actual_sku' => $line->item->sku,
                'actual_purchase_unit' => $line->purchase_unit,
                'destination_location_id' => $this->mainWarehouseLocation->id,
            ], $override)],
        ];
    }

    private function receive(PurchaseOrder $po, PurchaseOrderLine $line, int $quantity, array $override = []): GoodsReceiptNote
    {
        return $this->receiptService->receiveOrder($po, $this->receiptData($line, $quantity, $override), $this->receivingClerk);
    }
}
