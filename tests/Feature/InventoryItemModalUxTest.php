<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemModalUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_items_page_renders_modal_trigger_and_modal_dialog(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $location = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH-01',
            'status' => 'active',
            'type' => 'warehouse',
        ]);
        $category = ItemCategory::create([
            'name' => 'Pharmaceuticals',
            'code' => 'PHARM',
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'name' => 'Metro Drug Corp',
            'email' => 'sales@metrodrug.example.com',
            'status' => 'active',
            'accreditation_status' => 'approved',
        ]);

        $response = $this->actingAs($manager)->get(route('inventory.items'));

        $response->assertOk();
        // Check for modal trigger
        $response->assertSee('Create Inventory Item');
        $response->assertSee('id="btn-open-create-item-modal"', false);

        // Check for modal dialog attributes
        $response->assertSee('x-show="createItemModal"', false);
        $response->assertSee('id="create-inventory-item-form"', false);
        $response->assertSee('id="btn-save-inventory-item"', false);

        // Check for all 5 section indicators
        $response->assertSee('1. Basic Information');
        $response->assertSee('2. Tracking &amp; Classifications', false);
        $response->assertSee('3. Storage &amp; Procurement', false);
        $response->assertSee('4. Inventory Settings &amp; Quantities', false);
        $response->assertSee('5. Opening Stock Batch Details');

        // Check presence of key form inputs
        $response->assertSee('name="name"', false);
        $response->assertSee('name="sku"', false);
        $response->assertSee('name="barcode_value"', false);
        $response->assertSee('name="gtin"', false);
        $response->assertSee('name="category_id"', false);
        $response->assertSee('name="unit"', false);
        $response->assertSee('name="is_batch_tracked"', false);
        $response->assertSee('name="is_serial_tracked"', false);
        $response->assertSee('name="is_expiry_tracked"', false);
        $response->assertSee('name="storage_classification"', false);
        $response->assertSee('name="temperature_classification"', false);
        $response->assertSee('name="default_location_id"', false);
        $response->assertSee('name="initial_quantity"', false);
        $response->assertSee('name="reorder_level"', false);
        $response->assertSee('name="pick_face_minimum"', false);
        $response->assertSee('name="pick_face_maximum"', false);
        $response->assertSee('name="expiry_alert_days"', false);
        $response->assertSee('name="unit_cost"', false);
        $response->assertSee('name="batch_number"', false);
        $response->assertSee('name="expiry_date"', false);
        $response->assertSee('name="supplier_id"', false);

        // Check storage location dropdown integration
        $response->assertSee('id="field-default_location_id"', false);
        $response->assertSee('Select storage location');
        $response->assertSee('Main Warehouse (MWH-01 • Warehouse)');

        // Check unit dropdown integration
        $response->assertSee('id="field-unit"', false);
        $response->assertSee('Select unit');
        $response->assertSee('Piece (pc)');
        $response->assertSee('Box (box)');
        $response->assertSee('Vial (vial)');
        $response->assertDontSee('id="inventory-unit-options"', false);
    }

    public function test_modal_and_trigger_are_hidden_from_unauthorized_users(): void
    {
        $pharmacy = User::factory()->pharmacyStaff()->create();

        $response = $this->actingAs($pharmacy)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertDontSee('id="btn-open-create-item-modal"', false);
        $response->assertDontSee('id="create-inventory-item-form"', false);
        $response->assertDontSee('Create Inventory Item');
    }
}
