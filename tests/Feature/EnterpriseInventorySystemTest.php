<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Models\CostCenter;
use App\Models\CycleCountDoc;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\MaterialRequisition;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\AdjustmentApprovalService;
use App\Services\Inventory\CycleCountService;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\IssuanceEngine;
use App\Services\Inventory\QualityControlService;
use App\Services\Inventory\ReplenishmentDaemon;
use App\Services\Inventory\TransferService;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseInventorySystemTest extends TestCase
{
    use RefreshDatabase;

    private function createWarehouseStaff(): User
    {
        return User::factory()->warehouseStaff()->create();
    }

    private function createInventoryManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function createAdmin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function createItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name' => 'Propofol 10mg/mL 20mL Vial',
            'sku' => 'MED-PROP-20ML',
            'description' => 'Intravenous anesthetic emulsion',
            'unit_of_measure' => 'vial',
            'unit_cost' => 250.00,
            'quantity_on_hand' => 0,
            'reorder_point' => 50,
            'safety_stock' => 20,
            'economic_order_quantity' => 100,
            'lead_time_days' => 5,
            'annual_demand' => 1200,
            'abc_class' => 'A',
            'costing_method' => 'FIFO',
            'status' => 'active',
        ], $overrides));
    }

    private function createLocation(string $code, string $name, string $zone = 'General'): StorageLocation
    {
        return StorageLocation::create([
            'code' => $code,
            'name' => $name,
            'zone' => $zone,
            'status' => 'active',
        ]);
    }

    /**
     * Inbound Receiving against PO with +5% tolerance limit and Quarantine placement.
     */
    public function test_inbound_dock_receiving_against_po_enforces_tolerance_and_routes_to_quarantine(): void
    {
        $staff = $this->createWarehouseStaff();
        $supplier = Supplier::create([
            'name' => 'Zuellig Pharma Corp',
            'contact_email' => 'deliveries@zuellig.com.ph',
            'status' => 'active',
        ]);

        $item = $this->createItem();
        $location = $this->createLocation('LOC-MAIN-01', 'Central Pharmacy Shelves');

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Dispatched->value,
            'payment_terms' => 'Net 30',
            'total_amount' => 25000.00,
            'created_by_id' => $staff->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 100,
            'received_quantity' => 0,
            'unit_price' => 250.00,
            'line_total' => 25000.00,
        ]);

        $receiptService = app(GoodsReceiptService::class);

        // 1. Rejection of delivery exceeding +5% tolerance cap (ordered 100, receiving 106 -> +6%)
        try {
            $receiptService->receiveOrder($po, [
                'carrier_name' => 'Air21 Logistics',
                'waybill_number' => 'WB-99881',
                'lines' => [
                    [
                        'po_line_id' => $poLine->id,
                        'received_quantity' => 106,
                    ],
                ],
            ], $staff);
            $this->fail('Expected over-delivery > +5% to throw exception.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('+5% over-delivery tolerance', $e->getMessage());
        }

        // 2. Acceptance of delivery within +5% tolerance cap (ordered 100, receiving 105)
        $grn = $receiptService->receiveOrder($po, [
            'carrier_name' => 'Air21 Logistics',
            'waybill_number' => 'WB-99881',
            'packing_slip_number' => 'PS-44321',
            'notes' => 'Cold-chain container thermometer showed 4.2C upon arrival.',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 105,
                    'batch_number' => 'BATCH-PROP-2026A',
                    'lot_number' => 'LOT-ZUE-998',
                    'expiry_date' => now()->addMonths(18)->format('Y-m-d'),
                ],
            ],
        ], $staff);

        $this->assertNotNull($grn);
        $this->assertEquals('quarantined', $grn->status);
        $this->assertDatabaseHas('goods_receipt_notes', ['id' => $grn->id]);
        $this->assertDatabaseHas('grn_line_items', [
            'goods_receipt_note_id' => $grn->id,
            'received_quantity' => 105,
        ]);

        // Verify Quality Inspection was automatically scheduled
        $inspection = QualityInspection::where('item_id', $item->id)->first();
        $this->assertNotNull($inspection);
        $this->assertEquals('pending_sample', $inspection->inspection_status);
        $this->assertEquals(105, $inspection->sample_size);

        // Verify stock is placed in Quarantined status and NOT in unrestricted ATP or on-hand
        $item->refresh();
        $this->assertEquals(0, $item->quantity_on_hand); // Unrestricted cached QOH remains 0
        $this->assertEquals(105, $item->quarantinedQuantity());
        $this->assertEquals(0, $item->availableToPromise());
    }

    /**
     * Quality Control Assay & Partitioning: Release to Unrestricted & Rejection to Blocked.
     */
    public function test_quality_control_assay_releases_stock_to_unrestricted_and_isolates_rejections(): void
    {
        $staff = $this->createWarehouseStaff();
        $manager = $this->createInventoryManager();
        $item = $this->createItem();
        $targetLocation = $this->createLocation('LOC-CENTRAL-A', 'Main Pharmacy Dispensary');
        $supplier = Supplier::create([
            'name' => 'Zuellig Pharma Corp',
            'contact_email' => 'deliveries@zuellig.com.ph',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-QC-TEST',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Dispatched->value,
            'payment_terms' => 'Net 30',
            'total_amount' => 25000.00,
            'created_by_id' => $staff->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 100,
            'received_quantity' => 0,
            'unit_price' => 250.00,
            'line_total' => 25000.00,
        ]);

        $receiptService = app(GoodsReceiptService::class);
        $grn = $receiptService->receiveOrder($po, [
            'carrier_name' => 'Air21 Logistics',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 100,
                    'batch_number' => 'LOT-QC-001',
                    'expiry_date' => now()->addYear()->format('Y-m-d'),
                ],
            ],
        ], $staff);

        $inspection = QualityInspection::where('item_id', $item->id)->firstOrFail();
        $qcService = app(QualityControlService::class);

        // 1. Release 80 units to unrestricted central dispensary
        $qcService->releaseLot(
            $inspection,
            80,
            $targetLocation->id,
            $manager,
            'Chemical assay purity 99.8%, packaging intact.'
        );

        $item->refresh();
        $this->assertEquals(80, $item->quantity_on_hand);
        $this->assertEquals(80, $item->availableToPromise());
        $this->assertEquals(20, $item->quarantinedQuantity());

        // Verify StockMovement recorded for QualityRelease
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::QualityRelease->value,
            'quantity' => 80,
        ]);

        // 2. Reject remaining 20 units to blocked inventory
        $qcService->rejectLot(
            $inspection,
            20,
            'Packaging micro-fissure detected on secondary carton.',
            $manager
        );

        $item->refresh();
        $this->assertEquals(80, $item->quantity_on_hand); // Unrestricted QOH unchanged
        $this->assertEquals(0, $item->quarantinedQuantity()); // Quarantine fully cleared
        $this->assertEquals(20, $item->blockedQuantity()); // Moved to blocked status

        // Verify StockMovement recorded for QualityReject
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::QualityReject->value,
            'quantity' => 20,
        ]);
    }

    /**
     * Department Store Requisition: SoD self-approval prevention and ATP reservation.
     */
    public function test_material_requisition_enforces_sod_and_reserves_atp_upon_approval(): void
    {
        $requester = $this->createWarehouseStaff();
        $supervisor = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 50]);
        $location = $this->createLocation('LOC-DISP-01', 'Pharmacy Dispensary');

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $issuanceEngine = app(IssuanceEngine::class);

        // 1. Create Requisition
        $requisition = $issuanceEngine->createRequisition([
            'department' => 'Emergency Room',
            'urgency' => 'urgent',
            'justification' => 'ER Trauma Unit evening replenishment',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => 30,
                    'allocation_strategy' => 'FEFO',
                ],
            ],
        ], $requester);

        $this->assertEquals('pending_approval', $requisition->status);

        // 2. Test Segregation of Duties: Requester CANNOT approve own requisition
        try {
            $issuanceEngine->approveRequisition($requisition, $requester);
            $this->fail('Expected self-approval to throw DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsStringIgnoringCase('cannot approve your own store requisition', $e->getMessage());
        }

        // 3. Supervisor approves requisition -> ATP Reservation should occur
        $issuanceEngine->approveRequisition($requisition, $supervisor);

        $requisition->refresh();
        $item->refresh();

        $this->assertEquals('approved', $requisition->status);
        $this->assertEquals($supervisor->id, $requisition->approved_by_id);
        $this->assertEquals(30, $item->reservedQuantity());
        $this->assertEquals(20, $item->availableToPromise()); // 50 SOH - 30 Reserved = 20 ATP
    }

    /**
     * Algorithmic FEFO Pick List, Atomic Issuance, and Handover Acknowledgment.
     */
    public function test_algorithmic_fefo_picking_atomic_issuance_and_handover_acknowledgment(): void
    {
        $requester = $this->createWarehouseStaff();
        $supervisor = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 60]);
        $location = $this->createLocation('LOC-DISP-02', 'Dispensary Shelf B');

        // Create 2 batches: Batch A (expires in 30 days) and Batch B (expires in 180 days)
        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-EXP-NEAR',
            'quantity' => 20,
            'expiry_date' => now()->addDays(30),
            'storage_location_id' => $location->id,
        ]);

        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-EXP-FAR',
            'quantity' => 40,
            'expiry_date' => now()->addDays(180),
            'storage_location_id' => $location->id,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 20,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 40,
            'reserved_quantity' => 0,
        ]);

        $issuanceEngine = app(IssuanceEngine::class);

        $req = $issuanceEngine->createRequisition([
            'department' => 'ICU',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => 30,
                    'allocation_strategy' => 'FEFO',
                ],
            ],
        ], $requester);

        $issuanceEngine->approveRequisition($req, $supervisor);

        // Verify FEFO algorithm prioritizes earliest expiring batch first
        $pickList = $issuanceEngine->generatePickList($req);
        $linePicks = $pickList[$req->lines->first()->id];
        $this->assertCount(2, $linePicks);
        $this->assertEquals('LOT-EXP-NEAR', $linePicks[0]['batch_number']);
        $this->assertEquals(20, $linePicks[0]['pick_quantity']); // Take all 20 of near-expiry
        $this->assertEquals('LOT-EXP-FAR', $linePicks[1]['batch_number']);
        $this->assertEquals(10, $linePicks[1]['pick_quantity']); // Remainder from far-expiry

        // Atomic Issuance
        $line = $req->lines->first();
        $issuanceEngine->issueRequisition($req, [
            'lines' => [
                [
                    'line_id' => $line->id,
                    'quantity' => 30,
                    'location_id' => $location->id,
                    'batch_id' => null,
                ],
            ],
        ], $supervisor);

        $req->refresh();
        $item->refresh();

        $this->assertEquals('issued', $req->status);
        $this->assertEquals(30, $item->quantity_on_hand); // 60 - 30 = 30
        $this->assertEquals(0, $item->reservedQuantity()); // Reservation released

        // Handover Acknowledgment
        $issuanceEngine->acknowledgeHandover($req, $requester, 'Received in good order at ICU drug counter.');
        $req->refresh();
        $this->assertEquals('acknowledged', $req->status);
        $this->assertNotNull($req->acknowledged_at);
    }

    /**
     * Stock Transfers: Virtual In-Transit buffer tracking and carrier transit loss/damage write-off.
     */
    public function test_stock_transfers_with_virtual_in_transit_buffer_and_transit_damage_resolution(): void
    {
        $staff = $this->createWarehouseStaff();
        $item = $this->createItem(['quantity_on_hand' => 100]);

        $origin = $this->createLocation('LOC-ORIGIN', 'Central Warehouse Bay 1');
        $destination = $this->createLocation('LOC-DEST', 'Satellite Clinic Pharmacy');

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $origin->id,
            'quantity' => 100,
        ]);

        $transferService = app(TransferService::class);

        // 1. Dispatch 50 units
        $transfer = $transferService->dispatchTransfer([
            'source_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'notes' => 'Emergency transfer to satellite facility',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 50,
                ],
            ],
        ], $staff);

        $this->assertEquals('in_transit', $transfer->status);

        // Origin decremented by 50
        $originStock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $origin->id)
            ->value('quantity');
        $this->assertEquals(50, $originStock);

        // In-Transit buffer incremented by 50
        $inTransitLoc = StorageLocation::where('code', 'LOC-IN-TRANSIT')->firstOrFail();
        $inTransitStock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $inTransitLoc->id)
            ->value('in_transit_quantity');
        $this->assertEquals(50, $inTransitStock);

        // 2. Receive at destination with carrier damage/loss discrepancy
        // Dispatched 50 -> Received 45, Damaged 3, Lost 2
        $transferLine = $transfer->lines->first();
        $receivedTransfer = $transferService->receiveTransfer($transfer, [
            'discrepancy_reason' => 'Carton crushed by courier, 3 ampoules shattered, 2 missing.',
            'lines' => [
                [
                    'line_id' => $transferLine->id,
                    'received_quantity' => 45,
                    'damaged_quantity' => 3,
                    'lost_quantity' => 2,
                ],
            ],
        ], $staff);

        $this->assertEquals('discrepancy', $receivedTransfer->status);

        // Destination received exactly 45 unrestricted units
        $destStock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $destination->id)
            ->value('quantity');
        $this->assertEquals(45, $destStock);

        // In-Transit buffer fully cleared
        $inTransitStockAfter = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $inTransitLoc->id)
            ->value('in_transit_quantity');
        $this->assertEquals(0, $inTransitStockAfter);

        // Item total QOH is now 95 (Origin 50 + Destination 45; damaged 3 & lost 2 written off)
        $item->refresh();
        $this->assertEquals(95, $item->quantity_on_hand);
    }

    /**
     * Blind Cycle Count: Snapshot freeze, recount triggers on > 2% variance, SoD counter approval block.
     */
    public function test_blind_cycle_counting_snapshot_freeze_recount_triggers_and_sod(): void
    {
        $counter = $this->createWarehouseStaff();
        $manager = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 100, 'unit_cost' => 500.00]);
        $location = $this->createLocation('LOC-WH-A', 'Warehouse Shelf A');

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 100,
        ]);

        $cycleCountService = app(CycleCountService::class);

        // 1. Generate Count Document (Captures immutable snapshot)
        $doc = $cycleCountService->generateCountDocument('location', $location->id, $manager, $counter->id);
        $this->assertEquals('generated', $doc->status);
        $this->assertNotNull($doc->snapshot_timestamp);

        $line = $doc->lines->first();
        $this->assertEquals(100, $line->book_quantity_snapshot);

        // 2. Counter submits blind count with variance (Counted 85 instead of 100 -> delta = -15, value = -₱7,500)
        // Since variance is 15% (> 2%) and value is ₱7,500 (> ₱5,000), recount MUST trigger!
        $cycleCountService->submitBlindCounts($doc, [
            $line->id => 85,
        ], $counter);

        $doc->refresh();
        $line->refresh();

        $this->assertEquals('recount_pending', $doc->status);
        $this->assertEquals(85, $line->counted_quantity_blind);
        $this->assertEquals(-15, $line->variance_quantity);
        $this->assertEquals(-7500.00, $line->variance_value);
        $this->assertTrue($line->recount_required);

        // 3. Segregation of Duties: Assigned counter CANNOT approve the count document
        try {
            $cycleCountService->approveAndPostAdjustments($doc, $counter);
            $this->fail('Expected counter approval to throw DomainException under SoD.');
        } catch (DomainException $e) {
            $this->assertStringContainsStringIgnoringCase('Counter cannot approve their own cycle count', $e->getMessage());
        }

        // 4. Authorized Inventory Manager approves and posts adjustments
        $cycleCountService->approveAndPostAdjustments($doc, $manager);

        $doc->refresh();
        $item->refresh();

        $this->assertEquals('posted', $doc->status);
        $this->assertEquals(85, $item->quantity_on_hand); // Reconciled to physical count
    }

    /**
     * Dual-Tier Adjustment Authorization: Single tier for <= ₱25k, dual tier for > ₱25k.
     */
    public function test_dual_tier_adjustment_authorization_thresholds(): void
    {
        $requester = $this->createWarehouseStaff();
        $manager = $this->createInventoryManager();
        $admin = $this->createAdmin();
        $item = $this->createItem(['quantity_on_hand' => 100, 'unit_cost' => 1000.00]);
        $location = $this->createLocation('LOC-STORE', 'Main Drug Store');

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 100,
        ]);

        $adjustmentService = app(AdjustmentApprovalService::class);

        // 1. Adjustment <= ₱25,000 (Adjust 10 units * ₱1,000 = ₱10,000)
        $adjSmall = $adjustmentService->requestAdjustment([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'adjustment_type' => 'decrease',
            'quantity' => 10,
            'reason_code' => 'damage',
            'explanation' => 'Routine breakage write-off',
        ], $requester);

        $this->assertEquals('pending_approval', $adjSmall->status);
        $this->assertFalse($adjSmall->requiresDualApproval());

        // Requester cannot approve own adjustment
        try {
            $adjustmentService->approveAndPost($adjSmall, $requester);
            $this->fail('Expected self-approval to throw DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Segregation of Duties Violation: You cannot approve your own adjustment request.', $e->getMessage());
        }

        // Single approval by independent manager posts immediately
        $postedSmall = $adjustmentService->approveAndPost($adjSmall, $manager);
        $this->assertEquals('posted', $postedSmall->status);

        // 2. Adjustment > ₱25,000 (Adjust 30 units * ₱1,000 = ₱30,000)
        $adjLarge = $adjustmentService->requestAdjustment([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'adjustment_type' => 'decrease',
            'quantity' => 30,
            'reason_code' => 'spoilage',
            'explanation' => 'Cold chain freezer compressor failure overnight',
        ], $requester);

        $this->assertEquals('pending_approval', $adjLarge->status);
        $this->assertTrue($adjLarge->requiresDualApproval());

        // Tier 1 approval moves status to 'second_approval_required'
        $tier1 = $adjustmentService->approveAndPost($adjLarge, $manager);
        $this->assertEquals('pending_second_approval', $tier1->status);

        // Manager cannot approve tier 2 (must be independent executive)
        try {
            $adjustmentService->approveAndPost($adjLarge, $manager);
            $this->fail('Expected same user tier-2 approval to fail.');
        } catch (DomainException $e) {
            $this->assertStringContainsStringIgnoringCase('The second approver must be distinct from the first approver', $e->getMessage());
        }

        // Tier 2 approval by Administrator posts the adjustment
        $tier2 = $adjustmentService->approveAndPost($adjLarge, $admin);
        $this->assertEquals('posted', $tier2->status);
        $this->assertEquals($admin->id, $tier2->second_approved_by_id);
    }

    /**
     * Deterministic Replenishment Engine: SS, ROP, EOQ formulas and automated draft PR instantiation.
     */
    public function test_replenishment_daemon_calculates_eoq_and_auto_triggers_purchase_request(): void
    {
        CostCenter::create([
            'code' => 'CC-MED-01',
            'name' => 'Central Pharmacy Operations',
            'department' => 'Pharmacy',
            'is_active' => true,
        ]);

        ProcurementCategory::create([
            'name' => 'Pharmaceutical Supplies',
            'code' => 'PHARM',
            'is_active' => true,
        ]);

        $item = $this->createItem([
            'quantity_on_hand' => 15, // Below ROP
            'reorder_point' => 50,
            'safety_stock' => 20,
            'economic_order_quantity' => 120,
            'annual_demand' => 1000,
            'lead_time_days' => 7,
            'unit_cost' => 150.00,
        ]);

        $daemon = app(ReplenishmentDaemon::class);

        // 1. Validate mathematical formulas
        $ss = $daemon->calculateSafetyStock($item);
        $this->assertGreaterThan(0, $ss);

        $rop = $daemon->calculateReorderPoint($item);
        $this->assertGreaterThan($ss, $rop);

        $eoq = $daemon->calculateEconomicOrderQuantity($item);
        $this->assertGreaterThan(0, $eoq);

        // 2. Trigger automated replenishment
        $pr = $daemon->evaluateAndTriggerReplenishment($item);

        $this->assertNotNull($pr);
        $this->assertEquals(RequisitionStatus::Draft, $pr->status);
        $this->assertDatabaseHas('purchase_requests', ['id' => $pr->id]);

        $prLine = $pr->lines->first();
        $this->assertNotNull($prLine);
        $this->assertEquals($item->id, $prLine->item_id);
        $this->assertEquals(120, $prLine->requested_quantity); // EOQ quantity
    }

    /**
     * Authoritative Ledger Reconciliation: Physical SOH matches cumulative StockMovement entries.
     */
    public function test_authoritative_ledger_reconciliation(): void
    {
        $manager = $this->createInventoryManager();
        $item = $this->createItem(['quantity_on_hand' => 0]);
        $loc = $this->createLocation('LOC-AUDIT-TEST', 'Audit Test Shelf');

        $automation = app(InventoryAutomationService::class);

        // Sequence of movements:
        // +100 Inbound StockIn
        $automation->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn->value,
            'quantity' => 100,
            'to_location_id' => $loc->id,
            'remarks' => 'Initial intake',
        ], $manager->id);

        // -30 Store Issuance
        $automation->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::Issuance->value,
            'quantity' => 30,
            'from_location_id' => $loc->id,
            'remarks' => 'Department issue',
        ], $manager->id);

        $item->refresh();

        // 100 - 30 = 70
        $this->assertEquals(70, $item->quantity_on_hand);

        // Calculate ledger sum
        $inbound = StockMovement::where('item_id', $item->id)
            ->where('movement_type', MovementType::StockIn->value)
            ->sum('quantity');

        $outbound = StockMovement::where('item_id', $item->id)
            ->where('movement_type', MovementType::Issuance->value)
            ->sum('quantity');

        $this->assertEquals($item->quantity_on_hand, $inbound - $outbound);
    }
}
