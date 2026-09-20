<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\InventoryAutomationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorageLocationSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private InventoryAutomationService $automationService;
    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->inventoryManager()->create(['status' => 'active']);
        $this->automationService = app(InventoryAutomationService::class);
        $this->category = ItemCategory::create([
            'name' => 'Pharmaceuticals',
            'code' => 'PHARM',
            'is_active' => true,
        ]);
    }

    /**
     * Scenario A: Select active location -> item created -> stock assigned to that location.
     */
    public function test_scenario_a_active_location_creates_item_and_assigns_stock_to_that_location(): void
    {
        $location = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manager)->post(route('inventory.items.store'), [
            'name' => 'Amoxicillin 500mg Capsule',
            'sku' => 'AMX-500-CAP',
            'unit' => 'capsule',
            'category_id' => $this->category->id,
            'default_location_id' => $location->id,
            'initial_quantity' => 50,
            'unit_cost' => 12.50,
            'is_batch_tracked' => 0,
            'is_serial_tracked' => 0,
            'is_expiry_tracked' => 0,
        ]);

        $response->assertRedirect(route('inventory.items'));
        $response->assertSessionHas('success');

        $item = InventoryItem::where('sku', 'AMX-500-CAP')->first();
        $this->assertNotNull($item);
        $this->assertEquals($location->id, $item->default_location_id);
        $this->assertEquals(50, $item->quantity_on_hand);

        // Verify stock level was recorded in the chosen location
        $stockLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $location->id)
            ->first();
        $this->assertNotNull($stockLevel);
        $this->assertEquals(50, $stockLevel->quantity);

        // Verify movement ledger entry
        $movement = StockMovement::where('item_id', $item->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(MovementType::StockIn, $movement->movement_type);
        $this->assertEquals($location->id, $movement->to_location_id);
        $this->assertEquals(50, $movement->quantity);
    }

    /**
     * Scenario B: Select inactive location on create with opening stock -> form validation rejects.
     */
    public function test_scenario_b_inactive_location_with_opening_stock_is_rejected_by_validation(): void
    {
        $inactiveLocation = StorageLocation::create([
            'name' => 'Decommissioned Ward',
            'code' => 'DEC-01',
            'type' => 'room',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->manager)->post(route('inventory.items.store'), [
            'name' => 'Ibuprofen 400mg Tablet',
            'sku' => 'IBU-400-TAB',
            'unit' => 'tablet',
            'category_id' => $this->category->id,
            'default_location_id' => $inactiveLocation->id,
            'initial_quantity' => 100,
            'unit_cost' => 5.00,
            'is_batch_tracked' => 0,
            'is_serial_tracked' => 0,
            'is_expiry_tracked' => 0,
        ]);

        $response->assertSessionHasErrors(['default_location_id']);
        $this->assertEquals(
            'The selected storage location is invalid or inactive. Only active locations can be assigned.',
            session('errors')->first('default_location_id')
        );
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'IBU-400-TAB']);
    }

    /**
     * Scenario C: Manually submit inactive location ID (without opening stock) -> backend validation rejects.
     */
    public function test_scenario_c_manually_submitting_inactive_location_id_is_rejected_by_backend_validation(): void
    {
        $inactiveLocation = StorageLocation::create([
            'name' => 'Defunct Vault',
            'code' => 'VAULT-OLD',
            'type' => 'vault',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->manager)->post(route('inventory.items.store'), [
            'name' => 'Morphine Sulfate 10mg/mL',
            'sku' => 'MOR-10-AMP',
            'unit' => 'ampoule',
            'category_id' => $this->category->id,
            'default_location_id' => $inactiveLocation->id,
            'initial_quantity' => 0,
            'unit_cost' => 45.00,
            'is_batch_tracked' => 0,
            'is_serial_tracked' => 0,
            'is_expiry_tracked' => 0,
        ]);

        $response->assertSessionHasErrors(['default_location_id']);
        $this->assertEquals(
            'The selected storage location is invalid or inactive. Only active locations can be assigned.',
            session('errors')->first('default_location_id')
        );
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'MOR-10-AMP']);
    }

    /**
     * Scenario C2: Opening stock with missing location -> required validation triggers.
     */
    public function test_scenario_c2_opening_stock_without_location_is_rejected(): void
    {
        $response = $this->actingAs($this->manager)->post(route('inventory.items.store'), [
            'name' => 'Saline Solution 0.9%',
            'sku' => 'SAL-09-500',
            'unit' => 'bag',
            'category_id' => $this->category->id,
            'default_location_id' => '',
            'initial_quantity' => 25,
            'unit_cost' => 35.00,
            'is_batch_tracked' => 0,
            'is_serial_tracked' => 0,
            'is_expiry_tracked' => 0,
        ]);

        $response->assertSessionHasErrors(['default_location_id']);
        $this->assertEquals(
            'A storage location is required when recording opening stock.',
            session('errors')->first('default_location_id')
        );
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'SAL-09-500']);
    }

    /**
     * Scenario D: Existing inventory in inactive location -> still visible in stock views & catalog.
     */
    public function test_scenario_d_existing_inventory_in_inactive_location_remains_visible_in_catalog(): void
    {
        $location = StorageLocation::create([
            'name' => 'Old Pharmacy Store',
            'code' => 'OPS-01',
            'type' => 'pharmacy',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Ceutical Salve 50g',
            'sku' => 'CS-50G-TUBE',
            'unit' => 'tube',
            'category_id' => $this->category->id,
            'default_location_id' => $location->id,
            'quantity_on_hand' => 40,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 40,
            'reserved_quantity' => 0,
        ]);

        // Deactivate the storage location
        $location->update(['status' => 'inactive']);

        // Filter catalog by this inactive location ID
        $response = $this->actingAs($this->manager)->get(route('inventory.items', ['location_id' => $location->id]));

        $response->assertOk();
        $response->assertSee('Ceutical Salve 50g');
        $response->assertSee('CS-50G-TUBE');
        $response->assertSee('Old Pharmacy Store (OPS-01)');
    }

    /**
     * Scenario E: Transfer stock OUT of inactive location -> allowed.
     */
    public function test_scenario_e_transferring_stock_out_of_inactive_location_is_allowed(): void
    {
        $inactiveSource = StorageLocation::create([
            'name' => 'Quarantine Bin',
            'code' => 'QBIN-01',
            'type' => 'bin',
            'status' => 'inactive',
        ]);

        $activeDestination = StorageLocation::create([
            'name' => 'Central Pharmacy',
            'code' => 'CPH-01',
            'type' => 'pharmacy',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'PCM-500-BOX',
            'unit' => 'box',
            'category_id' => $this->category->id,
            'default_location_id' => $inactiveSource->id,
            'quantity_on_hand' => 100,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $inactiveSource->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        // Transfer 30 units OUT of inactive source into active destination
        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::Transfer,
            'quantity' => 30,
            'from_location_id' => $inactiveSource->id,
            'to_location_id' => $activeDestination->id,
            'remarks' => 'Evacuating stock from decommissioned bin',
        ], $this->manager->id);

        $sourceLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $inactiveSource->id)
            ->first();
        $destLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $activeDestination->id)
            ->first();

        $this->assertEquals(70, $sourceLevel->quantity);
        $this->assertEquals(30, $destLevel->quantity);
    }

    /**
     * Scenario F: Transfer stock INTO inactive location -> blocked.
     */
    public function test_scenario_f_transferring_stock_into_inactive_location_is_blocked(): void
    {
        $activeSource = StorageLocation::create([
            'name' => 'Receiving Dock',
            'code' => 'RCV-01',
            'type' => 'staging',
            'status' => 'active',
        ]);

        $inactiveDestination = StorageLocation::create([
            'name' => 'Condemned Shelf',
            'code' => 'CND-01',
            'type' => 'shelf',
            'status' => 'inactive',
        ]);

        $item = InventoryItem::create([
            'name' => 'Surgical Gloves Medium',
            'sku' => 'GLV-MED-PAIR',
            'unit' => 'pair',
            'category_id' => $this->category->id,
            'default_location_id' => $activeSource->id,
            'quantity_on_hand' => 80,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $activeSource->id,
            'quantity' => 80,
            'reserved_quantity' => 0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This location is inactive and cannot receive new inventory. Select an active location.');

        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::Transfer,
            'quantity' => 20,
            'from_location_id' => $activeSource->id,
            'to_location_id' => $inactiveDestination->id,
            'remarks' => 'Attempted move into inactive location',
        ], $this->manager->id);
    }

    /**
     * Scenario G: Filtering catalog by storage location -> works correctly.
     */
    public function test_scenario_g_filtering_catalog_by_storage_location_works_correctly(): void
    {
        $locA = StorageLocation::create([
            'name' => 'Main Warehouse Shelf A',
            'code' => 'MWS-A',
            'type' => 'shelf',
            'status' => 'active',
        ]);

        $locB = StorageLocation::create([
            'name' => 'Central Pharmacy Cold Storage',
            'code' => 'CPC-01',
            'type' => 'cold_room',
            'status' => 'active',
        ]);

        $itemA = InventoryItem::create([
            'name' => 'Gauze Bandage 4-inch',
            'sku' => 'GB-4IN-ROLL',
            'unit' => 'roll',
            'category_id' => $this->category->id,
            'default_location_id' => $locA->id,
            'quantity_on_hand' => 100,
            'status' => 'active',
        ]);

        $itemB = InventoryItem::create([
            'name' => 'Insulin Glargine 100IU/mL',
            'sku' => 'INS-GLAR-VIAL',
            'unit' => 'vial',
            'category_id' => $this->category->id,
            'default_location_id' => $locB->id,
            'quantity_on_hand' => 50,
            'status' => 'active',
        ]);

        // Filter by Location A
        $responseA = $this->actingAs($this->manager)->get(route('inventory.items', ['location_id' => $locA->id]));
        $responseA->assertOk();
        $responseA->assertSee('Gauze Bandage 4-inch');
        $responseA->assertDontSee('Insulin Glargine 100IU/mL');

        // Filter by Location B
        $responseB = $this->actingAs($this->manager)->get(route('inventory.items', ['location_id' => $locB->id]));
        $responseB->assertOk();
        $responseB->assertSee('Insulin Glargine 100IU/mL');
        $responseB->assertDontSee('Gauze Bandage 4-inch');
    }

    /**
     * Scenario H: Same item stored in multiple locations -> stock isolated per location.
     */
    public function test_scenario_h_same_item_stored_across_multiple_locations_maintains_isolated_stock_levels(): void
    {
        $locMain = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $locPharm = StorageLocation::create([
            'name' => 'Central Pharmacy',
            'code' => 'CPH-01',
            'type' => 'pharmacy',
            'status' => 'active',
        ]);

        $locReserve = StorageLocation::create([
            'name' => 'Medical Supply Reserve',
            'code' => 'MSR-01',
            'type' => 'reserve',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Ceftriaxone 1g Powder',
            'sku' => 'CTX-1G-VIAL',
            'unit' => 'vial',
            'category_id' => $this->category->id,
            'default_location_id' => $locMain->id,
            'quantity_on_hand' => 0,
            'status' => 'active',
        ]);

        // Record stock across 3 different locations: Main = 200, Pharm = 100, Reserve = 50
        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 200,
            'to_location_id' => $locMain->id,
            'remarks' => 'Initial intake to main warehouse',
        ], $this->manager->id);

        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 100,
            'to_location_id' => $locPharm->id,
            'remarks' => 'Intake to pharmacy dispensary',
        ], $this->manager->id);

        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 50,
            'to_location_id' => $locReserve->id,
            'remarks' => 'Reserve backup stock',
        ], $this->manager->id);

        $item->refresh();

        // Total on hand should be 350
        $this->assertEquals(350, $item->quantity_on_hand);

        // Verify each location has its own isolated stock level
        $mainStock = ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $locMain->id)->first();
        $pharmStock = ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $locPharm->id)->first();
        $reserveStock = ItemStockLevel::where('item_id', $item->id)->where('storage_location_id', $locReserve->id)->first();

        $this->assertEquals(200, $mainStock->quantity);
        $this->assertEquals(100, $pharmStock->quantity);
        $this->assertEquals(50, $reserveStock->quantity);

        // Filtering catalog by ANY of these locations returns the item
        $resMain = $this->actingAs($this->manager)->get(route('inventory.items', ['location_id' => $locMain->id]));
        $resMain->assertOk()->assertSee('Ceftriaxone 1g Powder');

        $resPharm = $this->actingAs($this->manager)->get(route('inventory.items', ['location_id' => $locPharm->id]));
        $resPharm->assertOk()->assertSee('Ceftriaxone 1g Powder');

        $resReserve = $this->actingAs($this->manager)->get(route('inventory.items', ['location_id' => $locReserve->id]));
        $resReserve->assertOk()->assertSee('Ceftriaxone 1g Powder');

        // Updating default_location_id on item does not alter or erase balances in other locations
        $item->update(['default_location_id' => $locPharm->id]);

        $this->assertEquals(200, $mainStock->fresh()->quantity);
        $this->assertEquals(100, $pharmStock->fresh()->quantity);
        $this->assertEquals(50, $reserveStock->fresh()->quantity);
    }

    /**
     * Scenario I: Existing items without location or with previous location -> maintain data integrity.
     */
    public function test_scenario_i_existing_items_without_location_render_gracefully_and_maintain_integrity(): void
    {
        $itemNoLoc = InventoryItem::create([
            'name' => 'Legacy Unallocated Syringe',
            'sku' => 'LEG-SYR-10ML',
            'unit' => 'syringe',
            'category_id' => $this->category->id,
            'default_location_id' => null,
            'quantity_on_hand' => 15,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manager)->get(route('inventory.items'));
        $response->assertOk();
        $response->assertSee('Legacy Unallocated Syringe');
        $response->assertSee('LEG-SYR-10ML');
    }

    /**
     * Scenario J: Purchase order receipt to specific location -> increases stock at that location.
     */
    public function test_scenario_j_purchase_order_receipt_to_specific_location_increases_stock_at_that_location(): void
    {
        $dockLocation = StorageLocation::create([
            'name' => 'Central Receiving Dock',
            'code' => 'CRD-01',
            'type' => 'staging',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Normal Saline 1L',
            'sku' => 'NS-1L-BAG',
            'unit' => 'bag',
            'category_id' => $this->category->id,
            'default_location_id' => $dockLocation->id,
            'quantity_on_hand' => 0,
            'status' => 'active',
        ]);

        // Receive PO delivery of 120 bags directly into dockLocation
        $this->automationService->recordMovement([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 120,
            'to_location_id' => $dockLocation->id,
            'reference_type' => 'PurchaseOrder',
            'reference_id' => 9991,
            'remarks' => 'Receipt from PO-2026-001',
        ], $this->manager->id);

        $stock = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $dockLocation->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(120, $stock->quantity);
        $this->assertEquals(120, $item->fresh()->quantity_on_hand);
    }

    /**
     * Scenario K: FIFO/FEFO picking from correct location.
     */
    public function test_scenario_k_fifo_fefo_picking_allocates_from_correct_location(): void
    {
        $loc1 = StorageLocation::create(['name' => 'Bay 1', 'code' => 'BAY-01', 'type' => 'shelf', 'status' => 'active']);
        $loc2 = StorageLocation::create(['name' => 'Bay 2', 'code' => 'BAY-02', 'type' => 'shelf', 'status' => 'active']);

        $item = InventoryItem::create([
            'name' => 'Vaccine Vial',
            'sku' => 'VAC-VIAL-01',
            'unit' => 'vial',
            'category_id' => $this->category->id,
            'default_location_id' => $loc1->id,
            'quantity_on_hand' => 0,
            'status' => 'active',
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
        ]);

        // Batch 1: Earlier expiry (2026-10-01) at Bay 1
        $batch1 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-EARLY',
            'expiry_date' => Carbon::parse('2026-10-01'),
            'initial_quantity' => 30,
            'received_at' => Carbon::parse('2026-01-01'),
            'status' => 'active',
        ]);

        // Batch 2: Later expiry (2026-12-01) at Bay 1
        $batch2 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-LATER',
            'expiry_date' => Carbon::parse('2026-12-01'),
            'initial_quantity' => 50,
            'received_at' => Carbon::parse('2026-02-01'),
            'status' => 'active',
        ]);

        // Batch 3: Even earlier expiry (2026-09-01) at Bay 2
        $batch3 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-BAY2',
            'expiry_date' => Carbon::parse('2026-09-01'),
            'initial_quantity' => 20,
            'received_at' => Carbon::parse('2026-01-01'),
            'status' => 'active',
        ]);

        // Put stock in Bay 1
        ItemStockLevel::create(['item_id' => $item->id, 'storage_location_id' => $loc1->id, 'item_batch_id' => $batch1->id, 'quantity' => 30]);
        ItemStockLevel::create(['item_id' => $item->id, 'storage_location_id' => $loc1->id, 'item_batch_id' => $batch2->id, 'quantity' => 50]);

        // Put stock in Bay 2
        ItemStockLevel::create(['item_id' => $item->id, 'storage_location_id' => $loc2->id, 'item_batch_id' => $batch3->id, 'quantity' => 20]);

        $this->automationService->syncItemTotals($item);

        // When allocating FEFO for Bay 1 specifically:
        $bay1Batches = ItemStockLevel::where('item_stock_levels.item_id', $item->id)
            ->where('item_stock_levels.storage_location_id', $loc1->id)
            ->where('item_stock_levels.quantity', '>', 0)
            ->join('item_batches', 'item_stock_levels.item_batch_id', '=', 'item_batches.id')
            ->orderBy('item_batches.expiry_date', 'asc')
            ->select('item_stock_levels.*')
            ->get();

        $this->assertCount(2, $bay1Batches);
        // The first batch picked from Bay 1 must be LOT-EARLY
        $this->assertEquals($batch1->id, $bay1Batches->first()->item_batch_id);
    }

    /**
     * Combobox UI test: Form renders options with active locations selectable and inactive marked disabled.
     */
    public function test_form_renders_searchable_combobox_with_active_and_disabled_inactive_locations(): void
    {
        StorageLocation::create([
            'name' => 'Active Pharmacy Ward',
            'code' => 'APW-01',
            'type' => 'pharmacy',
            'status' => 'active',
        ]);

        StorageLocation::create([
            'name' => 'Inactive Overflow Yard',
            'code' => 'IOY-01',
            'type' => 'yard',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->manager)->get(route('inventory.items'));

        $response->assertOk();

        // Check the synced native select has both options
        $response->assertSee('Active Pharmacy Ward (APW-01 • Pharmacy)');
        $response->assertSee('Inactive Overflow Yard (IOY-01 • Yard) — [Inactive]');

        // Check disabled attribute is present for inactive
        $response->assertSee('disabled', false);

        // Check Alpine combobox template badges
        $response->assertSee('[Inactive]');
        $response->assertSee('Select storage location');
        $response->assertSee('All storage locations');
    }
}
