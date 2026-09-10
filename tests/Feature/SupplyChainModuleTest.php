<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyChainModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_inventory_manager_can_create_an_inventory_item(): void
    {
        // The item master needs manage_items — the storeroom owns the catalogue.
        $user = User::factory()->inventoryManager()->create();
        $supplier = Supplier::create([
            'name' => 'Acme Medical Supplies',
            'email' => 'sales@acme.com',
            'status' => 'active',
            'accreditation_status' => 'approved',
        ]);
        $category = ItemCategory::create([
            'name' => 'Consumables',
            'code' => 'CONS',
            'is_active' => true,
        ]);
        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post('/inventory/items', [
            'name' => 'Gloves',
            'sku' => 'GL-100',
            'category_id' => $category->id,
            'unit' => 'box',
            'is_batch_tracked' => '0',
            'initial_quantity' => 50,
            'reorder_level' => 10,
            'expiry_alert_days' => 30,
            'unit_cost' => 5.50,
            'supplier_id' => $supplier->id,
            'default_location_id' => $location->id,
        ]);

        $response->assertRedirect('/inventory/items');
        $item = InventoryItem::where('sku', 'GL-100')->firstOrFail();
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'category_id' => $category->id,
            'default_location_id' => $location->id,
            'quantity_on_hand' => 50,
            'reorder_level' => 10,
            'supplier_id' => $supplier->id,
        ]);
        $this->assertDatabaseHas('item_stock_levels', [
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'item_batch_id' => null,
            'quantity' => 50,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 50,
            'to_location_id' => $location->id,
        ]);
    }

    public function test_batch_tracked_opening_stock_requires_and_records_a_batch(): void
    {
        $user = User::factory()->inventoryManager()->create();
        $location = StorageLocation::create([
            'name' => 'Pharmacy Store',
            'code' => 'PHARM',
            'type' => 'warehouse',
            'status' => 'active',
        ]);
        $payload = [
            'name' => 'Paracetamol 500mg',
            'sku' => 'MED-PARA-500',
            'unit' => 'bottle',
            'is_batch_tracked' => '1',
            'initial_quantity' => 20,
            'reorder_level' => 5,
            'expiry_alert_days' => 90,
            'unit_cost' => 8.50,
            'default_location_id' => $location->id,
        ];

        $this->actingAs($user)->post('/inventory/items', $payload)
            ->assertSessionHasErrors('batch_number');
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'MED-PARA-500']);

        $this->actingAs($user)->post('/inventory/items', $payload + [
            'batch_number' => 'PARA-OPEN-001',
            'expiry_date' => today()->addYear()->toDateString(),
        ])->assertRedirect('/inventory/items');

        $item = InventoryItem::where('sku', 'MED-PARA-500')->firstOrFail();
        $this->assertDatabaseHas('item_batches', [
            'item_id' => $item->id,
            'batch_number' => 'PARA-OPEN-001',
            'initial_quantity' => 20,
        ]);
        $this->assertDatabaseHas('item_stock_levels', [
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 20,
        ]);
    }

    public function test_inventory_item_form_rejects_non_integer_or_negative_operational_values(): void
    {
        $user = User::factory()->inventoryManager()->create();
        $draftSupplier = Supplier::create([
            'name' => 'Draft Supplier',
            'status' => 'active',
            'accreditation_status' => 'draft',
        ]);
        $inactiveCategory = ItemCategory::create([
            'name' => 'Inactive Category',
            'code' => 'INACTIVE',
            'is_active' => false,
        ]);
        $inactiveLocation = StorageLocation::create([
            'name' => 'Closed Store',
            'code' => 'CLOSED',
            'type' => 'warehouse',
            'status' => 'inactive',
        ]);

        $this->actingAs($user)->post('/inventory/items', [
            'name' => 'Invalid Item',
            'sku' => 'INVALID-001',
            'category_id' => $inactiveCategory->id,
            'is_batch_tracked' => '0',
            'initial_quantity' => '2.5',
            'reorder_level' => -1,
            'expiry_alert_days' => -1,
            'unit_cost' => 'not-a-number',
            'supplier_id' => $draftSupplier->id,
            'default_location_id' => $inactiveLocation->id,
        ])->assertSessionHasErrors([
            'category_id',
            'initial_quantity',
            'reorder_level',
            'expiry_alert_days',
            'unit_cost',
            'supplier_id',
            'default_location_id',
        ]);

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'INVALID-001']);
    }
}
