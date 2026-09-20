<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseStorageLocationItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehousing_locations_page_renders_with_full_width_and_see_items_action(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $category = ItemCategory::create(['name' => 'Medical Consumables', 'code' => 'MED-CON']);
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge',
            'sku' => 'SGS-001',
            'category_id' => $category->id,
            'unit' => 'pack',
            'status' => 'active',
            'quantity_on_hand' => 100,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.warehousing.locations'));

        $response->assertOk();
        // Layout uses max-w-none (full-width)
        $response->assertSee('max-w-none');
        // Action button is rendered with count
        $response->assertSee('See Items');
        $response->assertSee('viewLocationItems(' . $location->id, false);
        // Contains Alpine modal container
        $response->assertSee('locationItemsManager()', false);
    }

    public function test_unauthenticated_user_cannot_access_location_items_endpoint(): void
    {
        $location = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $response = $this->getJson(route('inventory.warehousing.locations.items', $location));

        $response->assertUnauthorized();
    }

    public function test_unauthorized_user_without_permission_receives_forbidden(): void
    {
        // Viewer role does not possess Permission::ViewWarehouseTasks
        $viewer = User::factory()->viewer()->create();
        $location = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $response = $this->actingAs($viewer)->getJson(route('inventory.warehousing.locations.items', $location));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_retrieve_items_for_location_with_accurate_quantities(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        $locWarehouse = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);
        $locPharmacy = StorageLocation::create([
            'name' => 'Central Pharmacy',
            'code' => 'PHARM-01',
            'type' => 'pharmacy',
            'status' => 'active',
        ]);

        $category = ItemCategory::create(['name' => 'PPE', 'code' => 'PPE-01']);

        // Item stored across two locations: 400 in WH-01 and 200 in PHARM-01 (Global: 600)
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge',
            'sku' => 'SGS-001',
            'category_id' => $category->id,
            'unit' => 'pack',
            'status' => 'active',
            'quantity_on_hand' => 600,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $locWarehouse->id,
            'quantity' => 400,
            'reserved_quantity' => 50,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $locPharmacy->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        // 1. Check WH-01: Quantity must be 400, not 600
        $responseWh = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', $locWarehouse));
        $responseWh->assertOk();
        $dataWh = $responseWh->json();

        $this->assertEquals(400, $dataWh['location']['total_quantity']);
        $this->assertCount(1, $dataWh['items']);
        $this->assertEquals('Sterile Gauze Sponge', $dataWh['items'][0]['name']);
        $this->assertEquals('SGS-001', $dataWh['items'][0]['sku']);
        $this->assertEquals(400, $dataWh['items'][0]['quantity']);
        $this->assertEquals(50, $dataWh['items'][0]['reserved_quantity']);
        $this->assertEquals(350, $dataWh['items'][0]['available_quantity']);
        $this->assertEquals('PPE', $dataWh['items'][0]['category']);

        // 2. Check PHARM-01: Quantity must be 200, not 600
        $responsePharm = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', $locPharmacy));
        $responsePharm->assertOk();
        $dataPharm = $responsePharm->json();

        $this->assertEquals(200, $dataPharm['location']['total_quantity']);
        $this->assertCount(1, $dataPharm['items']);
        $this->assertEquals(200, $dataPharm['items'][0]['quantity']);
        $this->assertEquals(200, $dataPharm['items'][0]['available_quantity']);
    }

    public function test_empty_location_returns_zero_items_without_error(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $emptyLoc = StorageLocation::create([
            'name' => 'Empty Staging Area',
            'code' => 'STG-01',
            'type' => 'zone',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', $emptyLoc));

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(0, $data['location']['total_quantity']);
        $this->assertEquals(0, $data['pagination']['total']);
        $this->assertEmpty($data['items']);
    }

    public function test_multiple_batches_of_same_item_are_itemized_accurately(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $loc = StorageLocation::create([
            'name' => 'Cold Storage Room',
            'code' => 'COLD-01',
            'type' => 'cold_room',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Rabies Vaccine',
            'sku' => 'VAC-RAB-001',
            'unit' => 'vial',
            'status' => 'active',
            'quantity_on_hand' => 600,
        ]);

        $batch1 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-2026-001',
            'lot_number' => 'L01',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'status' => 'active',
        ]);

        $batch2 = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-2027-002',
            'lot_number' => 'L02',
            'expiry_date' => now()->addYears(2)->toDateString(),
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $loc->id,
            'item_batch_id' => $batch1->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $loc->id,
            'item_batch_id' => $batch2->id,
            'quantity' => 400,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', $loc));

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(600, $data['location']['total_quantity']);
        $this->assertCount(2, $data['items']);

        $batchNumbers = collect($data['items'])->pluck('batch_number')->all();
        $this->assertContains('LOT-2026-001', $batchNumbers);
        $this->assertContains('LOT-2027-002', $batchNumbers);
    }

    public function test_search_filters_within_location_inventory(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $loc = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $item1 = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge',
            'sku' => 'SGS-001',
            'barcode_value' => 'BAR-SGS-1234',
            'unit' => 'pack',
            'status' => 'active',
            'quantity_on_hand' => 100,
        ]);

        $item2 = InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'N95-001',
            'barcode_value' => 'BAR-N95-5678',
            'unit' => 'box',
            'status' => 'active',
            'quantity_on_hand' => 50,
        ]);

        ItemStockLevel::create([
            'item_id' => $item1->id,
            'storage_location_id' => $loc->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item2->id,
            'storage_location_id' => $loc->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        // Search for "Gauze"
        $respName = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', ['storageLocation' => $loc, 'search' => 'Gauze']));
        $respName->assertOk();
        $dataName = $respName->json();
        $this->assertCount(1, $dataName['items']);
        $this->assertEquals('Sterile Gauze Sponge', $dataName['items'][0]['name']);

        // Search for SKU "N95"
        $respSku = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', ['storageLocation' => $loc, 'search' => 'N95']));
        $respSku->assertOk();
        $dataSku = $respSku->json();
        $this->assertCount(1, $dataSku['items']);
        $this->assertEquals('N95 Respirator Mask', $dataSku['items'][0]['name']);

        // Search for non-matching term
        $respNone = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', ['storageLocation' => $loc, 'search' => 'NonExistentProduct']));
        $respNone->assertOk();
        $dataNone = $respNone->json();
        $this->assertCount(0, $dataNone['items']);
    }

    public function test_pagination_limits_records_per_page(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $loc = StorageLocation::create([
            'name' => 'Mega Warehouse',
            'code' => 'MEGA-01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        // Create 20 items in this location
        for ($i = 1; $i <= 20; $i++) {
            $item = InventoryItem::create([
                'name' => "Supply Item {$i}",
                'sku' => sprintf('SUP-%03d', $i),
                'unit' => 'piece',
                'status' => 'active',
                'quantity_on_hand' => 10,
            ]);

            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $loc->id,
                'quantity' => 10,
                'reserved_quantity' => 0,
            ]);
        }

        // Page 1 with per_page = 15
        $respPage1 = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', ['storageLocation' => $loc, 'page' => 1, 'per_page' => 15]));
        $respPage1->assertOk();
        $dataPage1 = $respPage1->json();
        $this->assertEquals(20, $dataPage1['pagination']['total']);
        $this->assertEquals(1, $dataPage1['pagination']['current_page']);
        $this->assertEquals(2, $dataPage1['pagination']['last_page']);
        $this->assertCount(15, $dataPage1['items']);

        // Page 2
        $respPage2 = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', ['storageLocation' => $loc, 'page' => 2, 'per_page' => 15]));
        $respPage2->assertOk();
        $dataPage2 = $respPage2->json();
        $this->assertEquals(2, $dataPage2['pagination']['current_page']);
        $this->assertCount(5, $dataPage2['items']);
    }

    public function test_works_across_all_supported_location_types(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $types = ['bin', 'shelf', 'rack', 'aisle', 'zone', 'vault', 'cold_room'];

        foreach ($types as $type) {
            $loc = StorageLocation::create([
                'name' => "Location Type {$type}",
                'code' => strtoupper("LOC-{$type}-01"),
                'type' => $type,
                'status' => 'active',
            ]);

            $item = InventoryItem::create([
                'name' => "Item in {$type}",
                'sku' => "SKU-{$type}",
                'unit' => 'unit',
                'status' => 'active',
                'quantity_on_hand' => 25,
            ]);

            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $loc->id,
                'quantity' => 25,
                'reserved_quantity' => 0,
            ]);

            $resp = $this->actingAs($user)->getJson(route('inventory.warehousing.locations.items', $loc));
            $resp->assertOk();
            $data = $resp->json();
            $this->assertEquals(25, $data['location']['total_quantity']);
            $this->assertCount(1, $data['items']);
        }
    }
}
