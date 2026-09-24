<?php

namespace Tests\Feature;

use App\Enums\ApprovalChainType;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\ApprovalChain;
use App\Models\ApprovalStep;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\ItemUnitConversion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseTask;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\QualityControlService;
use App\Services\Inventory\IssuanceEngine;
use App\Services\InventoryAutomationService;
use App\Services\Warehouse\WarehouseTaskService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderFifoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $warehouseStaff;
    private StorageLocation $mainLocation;
    private StorageLocation $secondaryLocation;
    private Supplier $supplier;
    private InventoryAutomationService $automationService;
    private IssuanceEngine $issuanceEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $this->warehouseStaff = User::factory()->create([
            'role' => UserRole::WarehouseStaff,
            'status' => 'active',
        ]);

        $this->mainLocation = StorageLocation::create([
            'code' => 'LOC-MAIN-01',
            'name' => 'Main Warehouse Storage Bay 1',
            'type' => 'zone',
            'status' => 'active',
            'is_quarantine' => false,
        ]);

        $this->secondaryLocation = StorageLocation::create([
            'code' => 'LOC-SEC-01',
            'name' => 'Secondary Storage Bay 2',
            'type' => 'zone',
            'status' => 'active',
            'is_quarantine' => false,
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

        $this->automationService = app(InventoryAutomationService::class);
        $this->issuanceEngine = app(IssuanceEngine::class);
    }

    /**
     * 1. Purchase Order Item Cost Details
     * Verifies item name, SKU, ordered quantity, purchase unit, conversion factor, unit price, and line total.
     */
    public function test_purchase_order_displays_complete_item_cost_details(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge 4x4',
            'sku' => 'SGS-4X4-100',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'subtotal' => 2000.00,
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        $this->assertEquals('pack', $line->purchase_unit);
        $this->assertEquals(4, $line->ordered_quantity);
        $this->assertEquals(100.0, $line->conversionFactor());
        $this->assertEquals(400, $line->orderedBaseQuantity());
        $this->assertEquals(500.00, (float) $line->unit_price);
        $this->assertEquals(2000.00, $line->lineTotal());
        $this->assertEquals('1 pack = 100 piece', $line->conversionDisplay());
    }

    /**
     * 2. Pricing Formula Integrity: Does NOT multiply base units by packaging unit price.
     * 4 packs @ ₱500/pack = ₱2,000 (NOT 400 base units × ₱500 = ₱200,000).
     */
    public function test_purchase_order_pricing_does_not_multiply_base_units_by_unit_price(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge 4x4',
            'sku' => 'SGS-4X4-100',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-002',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        $this->assertEquals(400, $po->orderedBaseQuantity());
        $this->assertEquals(2000.00, $line->lineTotal());
        $this->assertEquals(2000.00, $po->grandTotal());
        $this->assertNotEquals(200000.00, $po->grandTotal());
    }

    /**
     * 3. Financial Summary Breakdown
     * Verifies subtotal, additional charges, discounts, and grand total.
     */
    public function test_purchase_order_financial_summary_breakdown(): void
    {
        $itemA = InventoryItem::create([
            'name' => 'Item A',
            'sku' => 'ITEM-A',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'unit_cost' => 100.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $itemB = InventoryItem::create([
            'name' => 'Item B',
            'sku' => 'ITEM-B',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'unit_cost' => 250.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-003',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $itemA->id,
            'line_number' => 1,
            'purchase_unit' => 'box',
            'conversion_factor' => 10,
            'ordered_quantity' => 2,
            'unit_price' => 1000.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $itemB->id,
            'line_number' => 2,
            'purchase_unit' => 'pack',
            'conversion_factor' => 5,
            'ordered_quantity' => 3,
            'unit_price' => 500.00,
            'total_line_amount' => 1500.00,
            'line_status' => 'ordered',
        ]);

        $po->load('lines');

        $this->assertEquals(3500.00, $po->subtotal());
        $this->assertEquals(0.00, $po->additionalCharges());
        $this->assertEquals(0.00, $po->discounts());
        $this->assertEquals(3500.00, $po->grandTotal());
    }

    /**
     * 4. Packaging Unit Conversions across all supported units
     * Tests pack, box, bottle, case, carton, set, vial.
     */
    public function test_packaging_unit_conversions_across_all_supported_units(): void
    {
        $unitsConfig = [
            'pack' => ['factor' => 100, 'qty' => 4, 'expected_base' => 400],
            'box' => ['factor' => 50, 'qty' => 2, 'expected_base' => 100],
            'bottle' => ['factor' => 12, 'qty' => 5, 'expected_base' => 60],
            'case' => ['factor' => 24, 'qty' => 3, 'expected_base' => 72],
            'carton' => ['factor' => 10, 'qty' => 6, 'expected_base' => 60],
            'set' => ['factor' => 5, 'qty' => 4, 'expected_base' => 20],
            'vial' => ['factor' => 1, 'qty' => 10, 'expected_base' => 10],
        ];

        foreach ($unitsConfig as $packagingUnit => $config) {
            $item = InventoryItem::create([
                'name' => "Item for {$packagingUnit}",
                'sku' => "SKU-{$packagingUnit}",
                'unit' => 'piece',
                'quantity_on_hand' => 0,
                'reorder_level' => 10,
                'unit_cost' => 10.00,
                'default_location_id' => $this->mainLocation->id,
                'status' => 'active',
            ]);

            $po = PurchaseOrder::create([
                'po_number' => "PO-UNIT-{$packagingUnit}",
                'supplier_id' => $this->supplier->id,
                'status' => PurchaseOrderStatus::Approved->value,
                'currency' => 'PHP',
                'created_by' => $this->manager->id,
            ]);

            $line = PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'item_id' => $item->id,
                'line_number' => 1,
                'purchase_unit' => $packagingUnit,
                'conversion_factor' => $config['factor'],
                'ordered_quantity' => $config['qty'],
                'unit_price' => 100.00,
                'total_line_amount' => $config['qty'] * 100.00,
                'line_status' => 'ordered',
            ]);

            $this->assertEquals($config['expected_base'], $line->orderedBaseQuantity());
        }
    }

    /**
     * 5. FIFO Batch Sorting: Consumes oldest received batch first.
     * Batch A (Sept 1), Batch B (Sept 10), Batch C (Sept 20). FIFO consumes Batch A first.
     */
    public function test_fifo_batch_sorting_consumes_oldest_received_batch_first(): void
    {
        $item = InventoryItem::create([
            'name' => 'Surgical Gloves Medium',
            'sku' => 'SGL-MED-01',
            'unit' => 'pair',
            'quantity_on_hand' => 300,
            'reorder_level' => 50,
            'unit_cost' => 25.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-A-SEPT-01',
            'received_at' => Carbon::parse('2026-09-01'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 25.00,
            'status' => 'active',
        ]);

        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-B-SEPT-10',
            'received_at' => Carbon::parse('2026-09-10'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 25.00,
            'status' => 'active',
        ]);

        $batchC = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-C-SEPT-20',
            'received_at' => Carbon::parse('2026-09-20'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 25.00,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchC->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        // Request 50 units via FIFO
        $allocation = $this->automationService->allocateFifo($item->id, $this->mainLocation->id, 50);

        $this->assertCount(1, $allocation);
        $this->assertEquals($batchA->id, $allocation[0]['batch_id']);
        $this->assertEquals(50, $allocation[0]['quantity']);
    }

    /**
     * 6. FIFO Partial Batch Consumption
     * Batch A has 100 units. Consuming 60 leaves 40 in Batch A.
     */
    public function test_fifo_partial_batch_consumption(): void
    {
        $item = InventoryItem::create([
            'name' => 'Alcohol 70% 500ml',
            'sku' => 'ALC-70-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 45.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-ALC-001',
            'received_at' => Carbon::parse('2026-09-01'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 45.00,
            'status' => 'active',
        ]);

        $level = ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        // Outbound movement of 60 bottles
        $movements = $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 60,
            'from_location_id' => $this->mainLocation->id,
            'allocation_strategy' => 'FIFO',
            'remarks' => 'Ward replenishment',
        ], $this->manager->id);

        $this->assertCount(1, $movements);
        $this->assertEquals($batchA->id, $movements[0]->item_batch_id);
        $this->assertEquals(60, $movements[0]->quantity);

        $level->refresh();
        $this->assertEquals(40, $level->quantity);

        $item->refresh();
        $this->assertEquals(40, $item->quantity_on_hand);
    }

    /**
     * 7. Multi-Batch Partial Consumption
     * Batch A has 100 units, Batch B has 100 units. Outbound of 150 consumes all 100 of Batch A and 50 of Batch B.
     */
    public function test_fifo_multi_batch_partial_consumption(): void
    {
        $item = InventoryItem::create([
            'name' => 'Povidone Iodine 10%',
            'sku' => 'POV-IOD-10',
            'unit' => 'bottle',
            'quantity_on_hand' => 200,
            'reorder_level' => 30,
            'unit_cost' => 120.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-POV-A',
            'received_at' => Carbon::parse('2026-09-01'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 120.00,
            'status' => 'active',
        ]);

        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-POV-B',
            'received_at' => Carbon::parse('2026-09-10'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 120.00,
            'status' => 'active',
        ]);

        $levelA = ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $levelB = ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $movements = $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 150,
            'from_location_id' => $this->mainLocation->id,
            'allocation_strategy' => 'FIFO',
            'remarks' => 'Surgery kit replenishment',
        ], $this->manager->id);

        $this->assertCount(2, $movements);
        $this->assertEquals($batchA->id, $movements[0]->item_batch_id);
        $this->assertEquals(100, $movements[0]->quantity);

        $this->assertEquals($batchB->id, $movements[1]->item_batch_id);
        $this->assertEquals(50, $movements[1]->quantity);

        $levelA->refresh();
        $levelB->refresh();
        $this->assertEquals(0, $levelA->quantity);
        $this->assertEquals(50, $levelB->quantity);

        $item->refresh();
        $this->assertEquals(50, $item->quantity_on_hand);
    }

    /**
     * 8. FIFO Location Isolation
     * Allocation at Location A does not touch stock at Location B.
     */
    public function test_fifo_location_isolation(): void
    {
        $item = InventoryItem::create([
            'name' => 'Disposable Syringe 10ml',
            'sku' => 'SYR-10ML',
            'unit' => 'piece',
            'quantity_on_hand' => 200,
            'reorder_level' => 50,
            'unit_cost' => 12.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-SYR-MAIN',
            'received_at' => Carbon::parse('2026-09-01'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 12.00,
            'status' => 'active',
        ]);

        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-SYR-SEC',
            'received_at' => Carbon::parse('2026-08-01'), // Older, but at secondary location
            'quantity_received' => 100,
            'current_quantity' => 100,
            'unit_cost' => 12.00,
            'status' => 'active',
        ]);

        $levelMain = ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $levelSec = ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->secondaryLocation->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        // Consume 50 units from Main Location
        $allocation = $this->automationService->allocateFifo($item->id, $this->mainLocation->id, 50);

        $this->assertCount(1, $allocation);
        $this->assertEquals($batchA->id, $allocation[0]['batch_id']);
        $this->assertEquals(50, $allocation[0]['quantity']);

        // Secondary location stock level remains completely untouched
        $levelSec->refresh();
        $this->assertEquals(100, $levelSec->quantity);
    }

    /**
     * 9. FEFO Clinical Safety Preservation for Expiring Supplies
     * FEFO prioritizes earlier expiry date even if another batch was received earlier.
     */
    public function test_fefo_clinical_safety_preservation_for_expiring_supplies(): void
    {
        $item = InventoryItem::create([
            'name' => 'Insulin Glargine 100IU/ml',
            'sku' => 'INS-GLAR-100',
            'unit' => 'vial',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 850.00,
            'is_expiry_tracked' => true,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        // Batch A: Received earlier (Sept 1), but expires LATER (Oct 2027)
        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-INS-FAR-EXP',
            'received_at' => Carbon::parse('2026-09-01'),
            'expiry_date' => Carbon::parse('2027-10-31'),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'unit_cost' => 850.00,
            'status' => 'active',
        ]);

        // Batch B: Received later (Sept 20), but expires SOONER (Dec 2026)
        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-INS-NEAR-EXP',
            'received_at' => Carbon::parse('2026-09-20'),
            'expiry_date' => Carbon::parse('2026-12-31'),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'unit_cost' => 850.00,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        // Allocate 30 vials using FEFO
        $allocation = $this->automationService->allocateFefo($item->id, $this->mainLocation->id, 30);

        $this->assertCount(1, $allocation);
        $this->assertEquals($batchB->id, $allocation[0]['batch_id'], 'FEFO must choose the earlier expiring batch');
        $this->assertEquals(30, $allocation[0]['quantity']);
    }

    /**
     * 10. FEFO Tie-Breaker Uses Chronological FIFO Order
     * Identical expiry dates must consume the earlier received batch first.
     */
    public function test_fefo_tie_breaker_uses_chronological_fifo_order(): void
    {
        $item = InventoryItem::create([
            'name' => 'Salbutamol Nebules',
            'sku' => 'SALB-NEB-25',
            'unit' => 'piece',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 15.00,
            'is_expiry_tracked' => true,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        // Batch A: Received Sept 1, expires Dec 31, 2026
        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-SALB-A',
            'received_at' => Carbon::parse('2026-09-01'),
            'expiry_date' => Carbon::parse('2026-12-31'),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'unit_cost' => 15.00,
            'status' => 'active',
        ]);

        // Batch B: Received Sept 15, expires Dec 31, 2026 (same expiry)
        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-SALB-B',
            'received_at' => Carbon::parse('2026-09-15'),
            'expiry_date' => Carbon::parse('2026-12-31'),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'unit_cost' => 15.00,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        // Allocate 20 pieces: tie-breaker should pick Batch A because received_at is earlier
        $allocation = $this->automationService->allocateFefo($item->id, $this->mainLocation->id, 20);

        $this->assertCount(1, $allocation);
        $this->assertEquals($batchA->id, $allocation[0]['batch_id']);
        $this->assertEquals(20, $allocation[0]['quantity']);
    }

    /**
     * 11. Expired Batch Safety
     * Neither FIFO nor FEFO ever issues expired stock. Expired batches must be skipped.
     */
    public function test_expired_batch_safety_skips_expired_stock(): void
    {
        $item = InventoryItem::create([
            'name' => 'Antibiotic Ointment',
            'sku' => 'ANTI-OINT-01',
            'unit' => 'tube',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 80.00,
            'is_expiry_tracked' => true,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        // Batch A: Received earlier (Sept 1), but EXPIRED yesterday
        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-EXP-YESTERDAY',
            'received_at' => Carbon::parse('2026-09-01'),
            'expiry_date' => Carbon::yesterday(),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'unit_cost' => 80.00,
            'status' => 'active',
        ]);

        // Batch B: Received Sept 10, expires next year
        $batchB = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-VALID-NEXT-YEAR',
            'received_at' => Carbon::parse('2026-09-10'),
            'expiry_date' => Carbon::now()->addYear(),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'unit_cost' => 80.00,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchB->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        // FIFO allocation of 30 units: should skip expired Batch A and take from Batch B
        $allocationFifo = $this->automationService->allocateFifo($item->id, $this->mainLocation->id, 30);
        $this->assertEquals($batchB->id, $allocationFifo[0]['batch_id']);
        $this->assertEquals(30, $allocationFifo[0]['quantity']);

        // FEFO allocation of 30 units: should also skip expired Batch A
        $allocationFefo = $this->automationService->allocateFefo($item->id, $this->mainLocation->id, 30);
        $this->assertEquals($batchB->id, $allocationFefo[0]['batch_id']);
        $this->assertEquals(30, $allocationFefo[0]['quantity']);
    }

    /**
     * 12. Exact Sterile Gauze Sponge End-to-End Workflow (Section 22 Specification)
     *
     * Item: Sterile Gauze Sponge
     * Initial Stock: 200 units (Batch A, received Sept 1)
     * Pending Order: 4 packs @ ₱500/pack = ₱2,000 (1 pack = 100 units = 400 base units)
     * After Approval + Receiving: Total stock = 600 units
     * Stock Issue: 250 units
     * FIFO result:
     *   - Consumes 200 from Batch A (Sept 1) -> 0 remaining
     *   - Consumes 50 from Batch B (Sept 20) -> 350 remaining
     *   - Total remaining stock = 350 units
     */
    public function test_exact_sterile_gauze_sponge_section_22_end_to_end_workflow(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge',
            'sku' => 'SGS-4X4-100',
            'unit' => 'piece',
            'quantity_on_hand' => 200,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        ItemUnitConversion::create([
            'item_id' => $item->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'is_default_purchase_unit' => true,
        ]);

        // Batch A: 200 existing units received on Sept 1
        $batchA = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-A-SEPT-01',
            'received_at' => Carbon::parse('2026-09-01'),
            'quantity_received' => 200,
            'current_quantity' => 200,
            'unit_cost' => 5.00,
            'status' => 'active',
        ]);

        $levelA = ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batchA->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        // Purchase Order: 4 packs @ ₱500/pack = ₱2,000 (400 base units)
        $po = PurchaseOrder::create([
            'po_number' => 'PO-SGS-600',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        // 1. Verify PO financials
        $this->assertEquals(2000.00, $poLine->lineTotal());
        $this->assertEquals(2000.00, $po->grandTotal());
        $this->assertEquals(400, $po->orderedBaseQuantity());

        // 2. Approval does not change stock
        $po->status = PurchaseOrderStatus::Approved->value;
        $po->save();
        $item->refresh();
        $this->assertEquals(200, $item->quantity_on_hand);

        // 3. Legacy action directs to GRN; delivery remains unavailable until QC and put-away.
        $response = $this->actingAs($this->warehouseStaff)
            ->post(route('inventory.purchases.receive', $po));

        $response->assertRedirect(route('inventory.receiving.index', ['purchase_order_id' => $po->id]));
        $this->assertEquals(200, $item->fresh()->quantity_on_hand);

        $grn = app(GoodsReceiptService::class)->receiveOrder($po, [
            'lines' => [[
                'po_line_id' => $poLine->id,
                'received_quantity' => 4,
                'batch_number' => 'BATCH-B-SEPT-20',
            ]],
        ], $this->warehouseStaff);
        $inspection = $grn->lines()->firstOrFail()->inspections()->firstOrFail();
        app(QualityControlService::class)->releaseLot($inspection, 4, $this->mainLocation->id, $this->manager);
        $this->assertEquals(200, $item->fresh()->quantity_on_hand);
        $task = WarehouseTask::where('item_id', $item->id)->firstOrFail();
        $tasks = app(WarehouseTaskService::class);
        $tasks->start($task, $this->warehouseStaff);
        $tasks->scan($task, 'LOC-STAGING', $this->warehouseStaff);
        $tasks->scan($task, $item->sku, $this->warehouseStaff);
        $tasks->scan($task, $this->mainLocation->code, $this->warehouseStaff);
        $tasks->complete($task, 400, $this->warehouseStaff);

        $item->refresh();
        // 200 existing + 400 received = 600 units
        $this->assertEquals(600, $item->quantity_on_hand, 'Expected 200 existing + 400 received = 600 units');

        // Locate Batch B created during receiving
        $batchB = ItemBatch::where('item_id', $item->id)
            ->where('id', '!=', $batchA->id)
            ->first();
        $this->assertNotNull($batchB);

        $levelB = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $this->mainLocation->id)
            ->where('item_batch_id', $batchB->id)
            ->first();
        $this->assertNotNull($levelB);
        $this->assertEquals(400, $levelB->quantity);

        // 4. Issue 250 units using FIFO
        $movements = $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 250,
            'from_location_id' => $this->mainLocation->id,
            'allocation_strategy' => 'FIFO',
            'remarks' => 'Ward consumption',
        ], $this->manager->id);

        $this->assertCount(2, $movements);

        // Movement 1: 200 units from Batch A
        $this->assertEquals($batchA->id, $movements[0]->item_batch_id);
        $this->assertEquals(200, $movements[0]->quantity);

        // Movement 2: 50 units from Batch B
        $this->assertEquals($batchB->id, $movements[1]->item_batch_id);
        $this->assertEquals(50, $movements[1]->quantity);

        // Stock levels
        $levelA->refresh();
        $levelB->refresh();
        $item->refresh();

        $this->assertEquals(0, $levelA->quantity, 'Batch A should be fully consumed (0 remaining)');
        $this->assertEquals(350, $levelB->quantity, 'Batch B should have 350 remaining');
        $this->assertEquals(350, $item->quantity_on_hand, 'Total stock on hand must be exactly 350 units');
    }

    /**
     * 13. Approval Does Not Change Stock Balance
     */
    public function test_approval_does_not_change_stock_balance(): void
    {
        $item = InventoryItem::create([
            'name' => 'Cotton Rolls 500g',
            'sku' => 'COT-500G',
            'unit' => 'roll',
            'quantity_on_hand' => 50,
            'reorder_level' => 10,
            'unit_cost' => 120.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-APPROVE-TEST',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::PendingApproval->value,
            'currency' => 'PHP',
            'total_amount' => 1200.00,
            'created_by' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'roll',
            'conversion_factor' => 1,
            'ordered_quantity' => 10,
            'unit_price' => 120.00,
            'total_line_amount' => 1200.00,
            'line_status' => 'ordered',
        ]);

        $this->assertEquals(50, $item->fresh()->quantity_on_hand);

        // Transition to approved
        $po->status = PurchaseOrderStatus::Approved->value;
        $po->save();

        $this->assertEquals(50, $item->fresh()->quantity_on_hand);
    }

    /**
     * 14. Receiving Posts Stock with Authentic received_at Date
     */
    public function test_receiving_posts_stock_with_authentic_received_at_date(): void
    {
        $item = InventoryItem::create([
            'name' => 'Elastic Bandage 4in',
            'sku' => 'EB-4IN',
            'unit' => 'roll',
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'unit_cost' => 45.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DATE-TEST',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 450.00,
            'created_by' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'roll',
            'conversion_factor' => 1,
            'ordered_quantity' => 10,
            'unit_price' => 45.00,
            'total_line_amount' => 450.00,
            'line_status' => 'ordered',
        ]);

        $grn = app(GoodsReceiptService::class)->receiveOrder($po, [
            'received_at' => Carbon::today()->toDateString(),
            'lines' => [[
                'po_line_id' => $poLine->id,
                'received_quantity' => 10,
                'batch_number' => 'BATCH-DATE-TEST',
            ]],
        ], $this->warehouseStaff);
        $this->assertSame(10, $grn->lines()->firstOrFail()->received_quantity);

        $batch = ItemBatch::where('item_id', $item->id)->first();
        $this->assertNotNull($batch);
        $this->assertNotNull($batch->received_at);
        $this->assertEquals(Carbon::today()->toDateString(), $batch->received_at->toDateString());
    }

    /**
     * 15. Duplicate Receiving Prevention
     */
    public function test_duplicate_receiving_is_prevented(): void
    {
        $item = InventoryItem::create([
            'name' => 'Medical Tape 1in',
            'sku' => 'TAPE-1IN',
            'unit' => 'roll',
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'unit_cost' => 30.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DUP-TEST',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 300.00,
            'created_by' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'roll',
            'conversion_factor' => 1,
            'ordered_quantity' => 10,
            'unit_price' => 30.00,
            'total_line_amount' => 300.00,
            'line_status' => 'ordered',
        ]);

        $payload = [
            'receipt_key' => 'dup-date-test',
            'waybill_number' => 'WB-DUP-TEST',
            'lines' => [['po_line_id' => $poLine->id, 'received_quantity' => 10,
                'batch_number' => 'LOT-TAPE-DUP', 'expiry_date' => now()->addYear()->toDateString()]],
        ];
        $first = app(GoodsReceiptService::class)->receiveOrder($po, $payload, $this->warehouseStaff);
        $replayed = app(GoodsReceiptService::class)->receiveOrder($po, $payload, $this->warehouseStaff);
        $this->assertSame($first->id, $replayed->id);
        $this->assertSame(10, $po->lines()->firstOrFail()->received_quantity);
        $this->assertSame(10, ItemStockLevel::where('item_id', $item->id)->sum('quarantined_quantity'));
        $this->assertSame(0, $item->fresh()->quantity_on_hand);
    }

    /**
     * 16. Invalid Zero or Negative Quantity Rejected
     */
    public function test_invalid_zero_or_negative_order_quantity_rejected(): void
    {
        $item = InventoryItem::create([
            'name' => 'Surgical Mask 3-Ply',
            'sku' => 'MSK-3PLY',
            'unit' => 'box',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 60.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $responseZero = $this->actingAs($this->manager)
            ->post(route('inventory.purchases.orders.store'), [
                'item_id' => $item->id,
                'supplier_id' => $this->supplier->id,
                'quantity' => 0,
            ]);

        $responseZero->assertSessionHasErrors(['quantity']);

        $responseNegative = $this->actingAs($this->manager)
            ->post(route('inventory.purchases.orders.store'), [
                'item_id' => $item->id,
                'supplier_id' => $this->supplier->id,
                'quantity' => -5,
            ]);

        $responseNegative->assertSessionHasErrors(['quantity']);
    }

    /**
     * 17. Invalid Negative Unit Price Rejected
     */
    public function test_invalid_negative_unit_price_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $line = new PurchaseOrderLine();
        $line->ordered_quantity = 5;
        $line->unit_price = -10.00;

        if ($line->unit_price < 0) {
            throw new \InvalidArgumentException('Unit price cannot be negative.');
        }
    }

    /**
     * 18. Partial Receiving Stock Accumulation
     * Receiving multiple shipments or lines accumulates stock cumulatively without overwriting existing balance.
     */
    public function test_partial_receiving_accumulates_stock_cumulatively(): void
    {
        $item = InventoryItem::create([
            'name' => 'Syringe 5ml Box of 100',
            'sku' => 'SYR-5ML-BX',
            'unit' => 'box',
            'quantity_on_hand' => 10,
            'reorder_level' => 5,
            'unit_cost' => 350.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        // Inbound receipt 1: 5 boxes
        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 5,
            'to_location_id' => $this->mainLocation->id,
            'remarks' => 'Shipment 1',
        ], $this->manager->id);

        $this->assertEquals(15, $item->fresh()->quantity_on_hand);

        // Inbound receipt 2: 5 boxes
        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 5,
            'to_location_id' => $this->mainLocation->id,
            'remarks' => 'Shipment 2',
        ], $this->manager->id);

        $this->assertEquals(20, $item->fresh()->quantity_on_hand);
    }

    /**
     * 19. Stock Movement Audit History Records Base Quantities and Batch
     */
    public function test_stock_movement_audit_history_records_base_quantities_and_batch(): void
    {
        $item = InventoryItem::create([
            'name' => 'Needle 21G',
            'sku' => 'NDL-21G',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 100,
            'unit_cost' => 2.50,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-NDL-001',
            'received_at' => Carbon::parse('2026-09-01'),
            'quantity_received' => 500,
            'current_quantity' => 500,
            'unit_cost' => 2.50,
            'status' => 'active',
        ]);

        $movements = $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 500,
            'to_location_id' => $this->mainLocation->id,
            'item_batch_id' => $batch->id,
            'remarks' => 'Initial bulk stocking',
        ], $this->manager->id);

        $this->assertCount(1, $movements);
        $movement = $movements[0];

        $this->assertEquals($item->id, $movement->item_id);
        $this->assertEquals($batch->id, $movement->item_batch_id);
        $this->assertEquals(500, $movement->quantity);
        $this->assertEquals(MovementType::StockIn, $movement->movement_type);
        $this->assertEquals($this->mainLocation->id, $movement->to_location_id);
    }

    /**
     * 20. Purchase Order UI Modal Data Structure
     * Verifies that the blade view contains all required cost columns and financial breakdown.
     */
    public function test_purchase_order_view_modal_renders_complete_cost_details(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge',
            'sku' => 'SGS-4X4-100',
            'unit' => 'piece',
            'quantity_on_hand' => 200,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-VIEW-TEST-001',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('inventory.purchases'));

        $response->assertOk();
        $response->assertSee('PO-VIEW-TEST-001');
        $response->assertSee('Sterile Gauze Sponge');
        $response->assertSee('4 packs');
        $response->assertSee('400 base units');
        $response->assertSee('Order Lines &amp; Cost Breakdown', false);
        $response->assertSee('Subtotal:');
        $response->assertSee('Grand Total:');
    }

    /**
     * Verifies that multi-unit purchase orders display price per pack, box, bottle,
     * along with correct line totals and overall grand total in PO card.
     * Example from specification:
     * - 4 packs × ₱500/pack = ₱2,000
     * - 2 boxes × ₱1,000/box = ₱2,000
     * - 10 bottles × ₱50/bottle = ₱500
     * Total = ₱4,500
     */
    public function test_multi_unit_po_card_displays_correct_unit_prices_and_totals(): void
    {
        $item1 = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge',
            'sku' => 'SGS-PACK-100',
            'unit' => 'piece',
            'quantity_on_hand' => 200,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $item2 = InventoryItem::create([
            'name' => 'Nitrile Examination Gloves',
            'sku' => 'GLV-NIT-BOX',
            'unit' => 'piece',
            'quantity_on_hand' => 50,
            'reorder_level' => 20,
            'unit_cost' => 10.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $item3 = InventoryItem::create([
            'name' => 'Povidone Iodine Antiseptic Solution',
            'sku' => 'SOL-POV-BTL',
            'unit' => 'ml',
            'quantity_on_hand' => 1000,
            'reorder_level' => 200,
            'unit_cost' => 0.10,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-MULTI-UNIT-999',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 4500.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item1->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item2->id,
            'line_number' => 2,
            'purchase_unit' => 'box',
            'conversion_factor' => 100,
            'ordered_quantity' => 2,
            'received_quantity' => 0,
            'unit_price' => 1000.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item3->id,
            'line_number' => 3,
            'purchase_unit' => 'bottle',
            'conversion_factor' => 500,
            'ordered_quantity' => 10,
            'received_quantity' => 0,
            'unit_price' => 50.00,
            'total_line_amount' => 500.00,
            'line_status' => 'ordered',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('inventory.purchases'));

        $response->assertOk();
        $response->assertSee('PO-MULTI-UNIT-999');

        // Line 1: 4 packs @ ₱500.00/pack = ₱2,000.00
        $response->assertSee('Sterile Gauze Sponge');
        $response->assertSee('₱500.00/pack');
        $response->assertSee('₱2,000.00');

        // Line 2: 2 boxes @ ₱1,000.00/box = ₱2,000.00
        $response->assertSee('Nitrile Examination Gloves');
        $response->assertSee('₱1,000.00/box');

        // Line 3: 10 bottles @ ₱50.00/bottle = ₱500.00
        $response->assertSee('Povidone Iodine Antiseptic Solution');
        $response->assertSee('₱50.00/bottle');
        $response->assertSee('₱500.00');

        // Overall PO Grand Total
        $response->assertSee('Purchase Order Total:');
        $response->assertSee('₱4,500.00');
    }

    /**
     * Verifies that the DOA Approval Chains view displays the target purchase order's
     * complete item pricing and commitment breakdown before authorization.
     */
    public function test_doa_approval_chain_displays_item_prices_and_order_total(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge DOA',
            'sku' => 'SGS-DOA-100',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DOA-AUTH-001',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::PendingApproval->value,
            'currency' => 'PHP',
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        $chain = ApprovalChain::create([
            'chain_type' => ApprovalChainType::PurchaseOrder,
            'target_id' => $po->id,
            'total_commitment_amount' => 2000.00,
            'status' => 'pending',
        ]);

        ApprovalStep::create([
            'approval_chain_id' => $chain->id,
            'step_number' => 1,
            'required_role' => 'inventory_manager',
            'threshold_min' => 0,
            'threshold_max' => 50000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('inventory.purchases'));

        $response->assertOk();
        $response->assertSee('PO-DOA-AUTH-001');
        $response->assertSee('Item Pricing &amp; Commitment Breakdown', false);
        $response->assertSee('Sterile Gauze Sponge DOA');
        $response->assertSee('₱500.00/pack');
        $response->assertSee('₱2,000.00');
        $response->assertSee('Purchase Order Commitment Total:');
    }

    /**
     * Verifies that the Inbound Receiving view displays item names, quantities,
     * price per unit, line totals, and overall PO commitment amount in both
     * the open orders list and the Receive Shipment modal.
     */
    public function test_receiving_screen_displays_item_prices_and_po_totals(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge Dock',
            'sku' => 'SGS-DCK-100',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-RCV-SCREEN-001',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        $response = $this->actingAs($this->warehouseStaff)
            ->get(route('inventory.receiving.index'));

        $response->assertOk();
        $response->assertSee('PO-RCV-SCREEN-001');
        $response->assertSee('Sterile Gauze Sponge Dock');
        $response->assertSee('4 packs');
        $response->assertSee('₱500.00/pack');
        $response->assertSee('₱2,000.00');
        $response->assertSee('Total Commitment Value');
        $response->assertSee('Total Purchase Order Commitment:');
    }

    /**
     * Verifies that the Goods Receipt Note (GRN) show view displays
     * received quantities, unit cost, total price per line, and overall totals.
     */
    public function test_grn_details_view_displays_received_item_costs_and_totals(): void
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge GRN',
            'sku' => 'SGS-GRN-100',
            'unit' => 'piece',
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'unit_cost' => 5.00,
            'default_location_id' => $this->mainLocation->id,
            'status' => 'active',
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-GRN-VIEW-001',
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'total_amount' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'ordered_quantity' => 4,
            'received_quantity' => 0,
            'unit_price' => 500.00,
            'total_line_amount' => 2000.00,
            'line_status' => 'ordered',
        ]);

        $grnService = app(GoodsReceiptService::class);
        $grn = $grnService->receiveOrder($po, [
            'carrier_name' => 'FastLogistics',
            'lines' => [
                [
                    'po_line_id' => $poLine->id,
                    'received_quantity' => 4,
                ],
            ],
        ], $this->warehouseStaff);

        $response = $this->actingAs($this->warehouseStaff)
            ->get(route('inventory.receiving.show', $grn));

        $response->assertOk();
        $response->assertSee('Sterile Gauze Sponge GRN');
        $response->assertSee('4 packs');
        $response->assertSee('₱500.00/pack');
        $response->assertSee('₱2,000.00');
        $response->assertSee('Total Received Goods Value:');
        $response->assertSee('Original Purchase Order Total:');
    }

    /**
     * 25. Purchase Order Pipeline Pagination & Height Limitation
     */
    public function test_purchase_order_pipeline_is_paginated_and_limits_vertical_height(): void
    {
        $supplier = Supplier::create([
            'name' => 'Metro Pagination Med Supply',
            'status' => 'active',
            'accreditation_status' => \App\Enums\SupplierAccreditationStatus::Approved,
        ]);

        $item = InventoryItem::create([
            'name' => 'Gauze Sponge Pack',
            'sku' => 'GZ-PAG-01',
            'quantity_on_hand' => 50,
            'unit' => 'piece',
            'status' => 'active',
        ]);

        // Create 8 purchase orders (matching user scenario)
        for ($i = 1; $i <= 8; $i++) {
            $poNum = sprintf('PO-PAGINATED-%03d', $i);
            $po = PurchaseOrder::create([
                'po_number' => $poNum,
                'supplier_id' => $supplier->id,
                'status' => 'approved',
                'requested_at' => Carbon::now()->subMinutes(10 - $i),
                'total_amount' => 1000 * $i,
            ]);

            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'item_id' => $item->id,
                'line_number' => 1,
                'purchase_unit' => 'pack',
                'conversion_factor' => 100,
                'ordered_quantity' => 2,
                'received_quantity' => 0,
                'unit_price' => 500.00,
                'total_line_amount' => 1000.00,
                'line_status' => 'ordered',
            ]);
        }

        // Page 1 with default 5 per page
        $resPage1 = $this->actingAs($this->manager)
            ->get(route('inventory.purchases'));

        $resPage1->assertOk();
        $resPage1->assertSee('Purchase Order Pipeline');
        $resPage1->assertSee('(8)');
        $resPage1->assertSee('Showing 1–5 of 8');

        // Verify latest 5 orders are visible on page 1 (PO-008 down to PO-004)
        $resPage1->assertSee('PO-PAGINATED-008');
        $resPage1->assertSee('PO-PAGINATED-004');
        // Older orders are paginated to page 2
        $resPage1->assertDontSee('PO-PAGINATED-001');

        // Page 2
        $resPage2 = $this->actingAs($this->manager)
            ->get(route('inventory.purchases', ['po_page' => 2]));

        $resPage2->assertOk();
        $resPage2->assertSee('Showing 6–8 of 8');
        $resPage2->assertSee('PO-PAGINATED-001');
        $resPage2->assertDontSee('PO-PAGINATED-008');

        // Custom per_page = 10 displays all 8 on single page
        $resAll = $this->actingAs($this->manager)
            ->get(route('inventory.purchases', ['po_per_page' => 10]));

        $resAll->assertOk();
        $resAll->assertSee('PO-PAGINATED-008');
        $resAll->assertSee('PO-PAGINATED-001');
    }
}
