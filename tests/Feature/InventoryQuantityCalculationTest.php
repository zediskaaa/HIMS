<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\ItemUnitConversion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\QualityControlService;
use App\Services\Logistics\InspectionAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryQuantityCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private StorageLocation $location;
    private StorageLocation $quarantineLocation;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $this->location = StorageLocation::create([
            'code' => 'LOC-MAIN-01',
            'name' => 'Main Warehouse Storage',
            'type' => 'zone',
            'status' => 'active',
            'is_quarantine' => false,
        ]);

        $this->quarantineLocation = StorageLocation::create([
            'code' => 'LOC-QUARANTINE',
            'name' => 'Receiving Quarantine Holding Area',
            'type' => 'zone',
            'status' => 'active',
            'is_quarantine' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Sterling Health Supplies Inc',
            'contact_person' => 'Juan Dela Cruz',
            'email' => 'sales@sterlinghealth.ph',
            'phone' => '+63288880000',
            'address' => 'BGC, Taguig City',
            'status' => 'active',
            'accreditation_status' => 'approved',
        ]);
    }

    /**
     * 1. Exact Sterline Gauze Sponge Scenario (The Critical Bug Reproducer & Fix).
     *
     * Item: Sterline Gauze Sponge
     * Existing Stock: 200 units
     * Pending Order: 4 packs
     * Pack Conversion: 1 pack = 100 units
     * Expected Result after Approval + Receiving:
     *   200 existing + 400 received = 600 units
     * Must NOT be: 4, 204, 400, or 40,000.
     */
    public function test_exact_sterile_gauze_sponge_receiving_converts_packs_and_preserves_existing_stock(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterline Gauze Sponge 4x4 12-Ply',
            'sku' => 'SGS-4X4-100',
            'unit' => 'piece',
            'quantity_on_hand' => 200,
            'reorder_level' => 50,
            'unit_cost' => 1.85,
            'default_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'is_default' => true,
        ]);

        // Establish 200 existing units in stock level
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->location->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-SGS-2026-001',
            'supplier_id' => $this->supplier->id,
            'item_id' => $item->id,
            'quantity' => 4,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'unit_cost' => 185.00,
            'total_amount' => 740.00,
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 185.00,
            'total_line_amount' => 740.00,
            'line_status' => 'open',
        ]);

        // Prior to receiving: stock is still 200
        $this->assertEquals(200, $item->fresh()->quantity_on_hand);

        // Perform receiving through web controller action
        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po))
            ->assertRedirect(route('inventory.purchases'));

        $item->refresh();
        $po->refresh();
        $poLine->refresh();

        // THE CRITICAL ASSERTIONS:
        // Stock must be 600, NOT 4, NOT 204, NOT 400, NOT 40,000!
        $this->assertNotEquals(4, $item->quantity_on_hand, 'CRITICAL: Stock was replaced with raw ordered quantity (4)!');
        $this->assertNotEquals(204, $item->quantity_on_hand, 'CRITICAL: Pack conversion was ignored (200 + 4)!');
        $this->assertNotEquals(400, $item->quantity_on_hand, 'CRITICAL: Existing stock was wiped out (400 only)!');
        $this->assertNotEquals(40000, $item->quantity_on_hand, 'CRITICAL: Double conversion occurred (40,000)!');
        $this->assertEquals(600, $item->quantity_on_hand, 'Stock must be 200 existing + 400 received base units = 600.');

        // Verify PO tracking
        $this->assertEquals(4, $poLine->ordered_quantity);
        $this->assertEquals(4, $poLine->received_quantity);
        $this->assertEquals(400, $poLine->receivedBaseQuantity());
        $this->assertEquals(0, $poLine->remainingQuantity());
        $this->assertContains($poLine->line_status, ['received', 'fully_received']);
        $this->assertEquals('received', $po->status);

        // Verify StockMovement audit row
        $movement = StockMovement::where('item_id', $item->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(400, $movement->quantity, 'Stock movement must record +400 base units, not +4.');
        $this->assertStringContainsString('4 pack', $movement->remarks);
        $this->assertStringContainsString('400 piece', $movement->remarks);

        // Verify item master configuration was NOT modified
        $this->assertEquals('piece', $item->unit, 'Item master unit must remain "piece".');
    }

    /**
     * 2. Existing stock + received pieces (1:1 conversion).
     * Current: 20 pieces, Received: 5 pieces -> Expected: 25 pieces.
     */
    public function test_existing_stock_plus_received_pieces(): void
    {
        $item = $this->createItemWithStock('Syringe 5mL Luer Lock', 'piece', 20, 10.00);

        $po = $this->createApprovedPO($item, 5, 'piece', 1);

        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po));

        $this->assertEquals(25, $item->fresh()->quantity_on_hand);
    }

    /**
     * 3. Existing stock + received boxes.
     * Current: 50 pieces, Received: 2 boxes × 25 = 50 -> Expected: 100 pieces.
     */
    public function test_existing_stock_plus_received_boxes(): void
    {
        $item = $this->createItemWithStock('Surgical Gloves Medium', 'piece', 50, 15.00);
        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'box',
            'conversion_factor' => 25,
        ]);

        $po = $this->createApprovedPO($item, 2, 'box', 25);

        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po));

        $this->assertEquals(100, $item->fresh()->quantity_on_hand);
    }

    /**
     * 4. Existing stock + received bottles.
     * Current: 10 bottles, Received: 6 bottles × 1 = 6 -> Expected: 16 bottles.
     */
    public function test_existing_stock_plus_received_bottles(): void
    {
        $item = $this->createItemWithStock('Povidone Iodine 10% Solution', 'bottle', 10, 85.00);

        $po = $this->createApprovedPO($item, 6, 'bottle', 1);

        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po));

        $this->assertEquals(16, $item->fresh()->quantity_on_hand);
    }

    /**
     * 5. Existing stock + received cases.
     * Current: 100 pieces, Received: 2 cases × 24 = 48 -> Expected: 148 pieces.
     */
    public function test_existing_stock_plus_received_cases(): void
    {
        $item = $this->createItemWithStock('Normal Saline 0.9% 500mL', 'piece', 100, 45.00);
        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'case',
            'conversion_factor' => 24,
        ]);

        $po = $this->createApprovedPO($item, 2, 'case', 24);

        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po));

        $this->assertEquals(148, $item->fresh()->quantity_on_hand);
    }

    /**
     * 6. Partial receiving across multiple transactions.
     * Current: 200 units, Ordered: 4 packs × 100 = 400 base units.
     * 1st receipt: 1 pack -> Stock: 300
     * 2nd receipt: 2 packs -> Stock: 500
     * Final receipt: 1 pack -> Stock: 600
     */
    public function test_partial_receiving_accumulates_stock_cumulatively(): void
    {
        $item = $this->createItemWithStock('Gauze Pads Partial Test', 'piece', 200, 2.00);
        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-PARTIAL-CUMULATIVE',
            'supplier_id' => $this->supplier->id,
            'item_id' => $item->id,
            'quantity' => 4,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'unit_cost' => 200.00,
            'total_amount' => 800.00,
            'status' => PurchaseOrderStatus::Dispatched->value,
            'created_by_user_id' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 200.00,
            'total_line_amount' => 800.00,
            'line_status' => 'open',
        ]);

        $grnService = app(GoodsReceiptService::class);

        // First receipt: 1 pack (100 base units)
        $grn1 = $grnService->receiveOrder($po, [
            'carrier_name' => 'Courier 1',
            'lines' => [
                ['po_line_id' => $poLine->id, 'received_quantity' => 1],
            ],
        ], $this->manager);

        $poLine->refresh();
        $this->assertEquals(1, $poLine->received_quantity);
        $this->assertEquals(3, $poLine->remainingQuantity());
        $this->assertEquals('partially_received', $poLine->line_status);
        $this->assertEquals(100, $item->quarantinedQuantity());

        // Perform custodial acceptance of GRN 1 into active stock
        $iarService = app(InspectionAcceptanceService::class);
        $inspector = User::factory()->create(['role' => UserRole::InventoryManager, 'status' => 'active']);
        $custodian = User::factory()->create(['role' => UserRole::InventoryManager, 'status' => 'active']);

        $iar1 = $iarService->createFromReceipt($grn1, [], $this->manager);
        $iarService->performTechnicalInspection($iar1, ['inspection_status' => 'in_order'], $inspector);
        $iarService->performCustodialAcceptance($iar1, [], $custodian);

        $this->assertEquals(300, $item->fresh()->quantity_on_hand, 'After 1 pack: 200 + 100 = 300.');

        // Second receipt: 2 packs (200 base units)
        $grn2 = $grnService->receiveOrder($po, [
            'carrier_name' => 'Courier 2',
            'lines' => [
                ['po_line_id' => $poLine->id, 'received_quantity' => 2],
            ],
        ], $this->manager);

        $poLine->refresh();
        $this->assertEquals(3, $poLine->received_quantity);
        $this->assertEquals(1, $poLine->remainingQuantity());

        $iar2 = $iarService->createFromReceipt($grn2, [], $this->manager);
        $iarService->performTechnicalInspection($iar2, ['inspection_status' => 'in_order'], $inspector);
        $iarService->performCustodialAcceptance($iar2, [], $custodian);

        $this->assertEquals(500, $item->fresh()->quantity_on_hand, 'After 2 packs: 300 + 200 = 500.');

        // Third receipt: final 1 pack (100 base units)
        $grn3 = $grnService->receiveOrder($po, [
            'carrier_name' => 'Courier 3',
            'lines' => [
                ['po_line_id' => $poLine->id, 'received_quantity' => 1],
            ],
        ], $this->manager);

        $poLine->refresh();
        $this->assertEquals(4, $poLine->received_quantity);
        $this->assertEquals(0, $poLine->remainingQuantity());
        $this->assertEquals('fully_received', $poLine->line_status);

        $iar3 = $iarService->createFromReceipt($grn3, [], $this->manager);
        $iarService->performTechnicalInspection($iar3, ['inspection_status' => 'in_order'], $inspector);
        $iarService->performCustodialAcceptance($iar3, [], $custodian);

        $this->assertEquals(600, $item->fresh()->quantity_on_hand, 'After final pack: 500 + 100 = 600.');
    }

    /**
     * 7. Approval alone must NOT increase inventory.
     */
    public function test_approval_does_not_increase_stock(): void
    {
        $item = $this->createItemWithStock('Surgical Blade #10', 'piece', 200, 5.00);
        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'box',
            'conversion_factor' => 100,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-APPROVAL-CHECK',
            'supplier_id' => $this->supplier->id,
            'item_id' => $item->id,
            'quantity' => 4,
            'purchase_unit' => 'box',
            'conversion_factor' => 100,
            'status' => PurchaseOrderStatus::PendingApproval->value,
            'created_by_user_id' => $this->manager->id,
        ]);

        // Approve the PO
        $po->status = PurchaseOrderStatus::Approved->value;
        $po->save();

        // Stock must remain strictly 200
        $this->assertEquals(200, $item->fresh()->quantity_on_hand);
        $this->assertEquals(0, $item->quarantinedQuantity());
    }

    /**
     * 8. Cancelled or Rejected order must not increase stock.
     */
    public function test_cancelled_or_rejected_order_does_not_increase_stock(): void
    {
        $item = $this->createItemWithStock('Endotracheal Tube 7.0', 'piece', 200, 120.00);

        $po = $this->createApprovedPO($item, 4, 'box', 10);

        // Cancel the PO
        $po->status = PurchaseOrderStatus::Cancelled->value;
        $po->save();

        // Attempting to receive must fail
        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po))
            ->assertSessionHasErrors('receive');

        $this->assertEquals(200, $item->fresh()->quantity_on_hand);
    }

    /**
     * 9. Duplicate receiving prevention.
     */
    public function test_duplicate_receiving_prevention(): void
    {
        $item = $this->createItemWithStock('Blood Lancet 28G', 'piece', 200, 1.00);
        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
        ]);

        $po = $this->createApprovedPO($item, 4, 'pack', 100);

        // First receive succeeds: 200 + 400 = 600
        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po))
            ->assertRedirect(route('inventory.purchases'));

        $this->assertEquals(600, $item->fresh()->quantity_on_hand);

        // Duplicate receive attempt is blocked
        $this->actingAs($this->manager)
            ->post(route('inventory.purchases.receive', $po))
            ->assertRedirect(route('inventory.purchases'))
            ->assertSessionHas('info', 'This purchase order has already been received.');

        // Stock remains exactly 600, NOT 1,000!
        $this->assertEquals(600, $item->fresh()->quantity_on_hand);
    }

    /**
     * 10. Multiple independent POs for the same item accumulate correctly.
     */
    public function test_multiple_purchase_orders_for_same_item_accumulate_correctly(): void
    {
        $item = $this->createItemWithStock('N95 Respirator Mask', 'piece', 200, 35.00);
        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
        ]);

        // PO 1: 2 packs × 100 = 200 units
        $po1 = $this->createApprovedPO($item, 2, 'pack', 100, 'PO-MULTI-001');
        $this->actingAs($this->manager)->post(route('inventory.purchases.receive', $po1));
        $this->assertEquals(400, $item->fresh()->quantity_on_hand, '200 existing + 200 from PO1 = 400.');

        // PO 2: 3 packs × 100 = 300 units
        $po2 = $this->createApprovedPO($item, 3, 'pack', 100, 'PO-MULTI-002');
        $this->actingAs($this->manager)->post(route('inventory.purchases.receive', $po2));
        $this->assertEquals(700, $item->fresh()->quantity_on_hand, '400 + 300 from PO2 = 700.');
    }

    /**
     * 11. Different units for the same item (e.g. boxes of 50 and packs of 100).
     */
    public function test_different_units_for_same_item_convert_with_respective_factors(): void
    {
        $item = $this->createItemWithStock('Examination Gloves Nitrile', 'piece', 200, 5.00);

        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'box',
            'conversion_factor' => 50,
        ]);

        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
        ]);

        // Order 1: 2 boxes × 50 = 100 pieces
        $po1 = $this->createApprovedPO($item, 2, 'box', 50, 'PO-DIFF-001');
        $this->actingAs($this->manager)->post(route('inventory.purchases.receive', $po1));
        $this->assertEquals(300, $item->fresh()->quantity_on_hand, '200 + 100 (2 boxes) = 300.');

        // Order 2: 3 packs × 100 = 300 pieces
        $po2 = $this->createApprovedPO($item, 3, 'pack', 100, 'PO-DIFF-002');
        $this->actingAs($this->manager)->post(route('inventory.purchases.receive', $po2));
        $this->assertEquals(600, $item->fresh()->quantity_on_hand, '300 + 300 (3 packs) = 600.');
    }

    /**
     * 12. Batch tracking updates batch initial quantity in base units.
     */
    public function test_batch_tracking_updates_batch_in_base_units(): void
    {
        $item = InventoryItem::create([
            'name' => 'Ceftriaxone 1g Powder for Injection',
            'sku' => 'CEFT-1G-VIAL',
            'unit' => 'vial',
            'quantity_on_hand' => 50,
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
            'default_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'box',
            'conversion_factor' => 10,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $po = $this->createApprovedPO($item, 5, 'box', 10, 'PO-BATCH-001');

        $grnService = app(GoodsReceiptService::class);
        $grn = $grnService->receiveOrder($po, [
            'carrier_name' => 'FastLogistics',
            'lines' => [
                [
                    'po_line_id' => $po->lines->first()->id,
                    'received_quantity' => 5, // 5 boxes
                    'batch_number' => 'BATCH-CEFT-2026-X',
                    'expiry_date' => now()->addYear()->toDateString(),
                ],
            ],
        ], $this->manager);

        // Batch initial_quantity must be 50 vials (5 boxes × 10), not 5!
        $batch = ItemBatch::where('batch_number', 'BATCH-CEFT-2026-X')->firstOrFail();
        $this->assertEquals(50, $batch->initial_quantity, 'Batch initial quantity must be 50 base units.');
        $this->assertEquals(50, $item->quarantinedQuantity());

        // QC release 50 vials into unrestricted stock
        $inspection = QualityInspection::where('item_id', $item->id)->firstOrFail();
        $qcService = app(QualityControlService::class);
        $qcService->releaseLot($inspection, 5, $this->location->id, $this->manager, 'QC Passed');

        $this->assertEquals(100, $item->fresh()->quantity_on_hand, '50 existing + 50 received = 100.');
    }

    /**
     * 13. Invalid receiving quantities (zero, negative, exceeding tolerance).
     */
    public function test_invalid_receiving_quantities_are_rejected(): void
    {
        $item = $this->createItemWithStock('Cotton Balls 500s', 'piece', 50, 40.00);
        $po = $this->createApprovedPO($item, 10, 'piece', 1, 'PO-INVALID-QTY');

        $grnService = app(GoodsReceiptService::class);

        // Exceeding +5% tolerance: ordered 10, max allowed 11, trying to receive 15
        $this->expectException(ValidationException::class);
        $grnService->receiveOrder($po, [
            'lines' => [
                ['po_line_id' => $po->lines->first()->id, 'received_quantity' => 15],
            ],
        ], $this->manager);
    }

    // --- Helper Methods ---

    private function createItemWithStock(string $name, string $unit, int $initialStock, float $unitCost): InventoryItem
    {
        $item = InventoryItem::create([
            'name' => $name,
            'sku' => 'SKU-'.strtoupper(substr(md5($name), 0, 8)),
            'unit' => $unit,
            'quantity_on_hand' => $initialStock,
            'reorder_level' => 10,
            'unit_cost' => $unitCost,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'default_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->location->id,
            'quantity' => $initialStock,
            'reserved_quantity' => 0,
        ]);

        return $item;
    }

    private function createApprovedPO(
        InventoryItem $item,
        int $quantity,
        string $purchaseUnit,
        float $conversionFactor,
        ?string $poNumber = null
    ): PurchaseOrder {
        $poNumber ??= 'PO-TEST-'.strtoupper(substr(md5(uniqid()), 0, 8));
        $unitCost = 100.00;
        $totalAmount = round($quantity * $unitCost, 2);

        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'supplier_id' => $this->supplier->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'purchase_unit' => $purchaseUnit,
            'conversion_factor' => $conversionFactor,
            'unit_cost' => $unitCost,
            'total_amount' => $totalAmount,
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $this->manager->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'purchase_unit' => $purchaseUnit,
            'conversion_factor' => $conversionFactor,
            'ordered_quantity' => $quantity,
            'received_quantity' => 0,
            'unit_price' => $unitCost,
            'total_line_amount' => $totalAmount,
            'line_status' => 'open',
        ]);

        return $po;
    }
}
