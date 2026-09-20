<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualItemActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createInventoryManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function createItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name' => 'Latex Gloves XL',
            'sku' => 'PPE-GLV-XL',
            'description' => 'Powder-free surgical gloves',
            'unit_of_measure' => 'box',
            'unit_cost' => 250.00,
            'quantity_on_hand' => 15,
            'reorder_level' => 50,
            'reorder_point' => 50,
            'safety_stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    private function createLocation(string $code = 'LOC-MAIN-01', string $name = 'Central Storage'): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    public function test_material_requisitions_index_with_item_id_preselects_item(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem();

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $response->assertSee('Contextual Item:');
        $response->assertSee('Latex Gloves XL');
        $response->assertSee('PPE-GLV-XL');
    }

    public function test_material_requisitions_index_with_invalid_item_id_handles_gracefully(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => 999999]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertSessionHas('warning', 'The requested inventory item could not be preselected because it does not exist or is inactive.');
    }

    public function test_stock_adjustments_index_with_item_id_preselects_item_and_type(): void
    {
        $user = $this->createInventoryManager();
        $location = $this->createLocation();
        $item = $this->createItem(['default_location_id' => $location->id]);

        $response = $this->actingAs($user)->get(route('inventory.adjustments', [
            'item_id' => $item->id,
            'adjustment_type' => 'correction',
        ]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $response->assertViewHas('preselectedType', 'correction');
        $response->assertSee('Contextual Item:');
        $response->assertSee('Latex Gloves XL');
    }

    public function test_stock_adjustments_index_with_invalid_item_id_flashes_warning(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.adjustments', ['item_id' => 999999]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertSessionHas('warning', 'The requested inventory item could not be preselected because it does not exist or is inactive.');
    }

    public function test_stock_transfers_index_with_item_id_preselects_item_and_stock_location(): void
    {
        $user = $this->createInventoryManager();
        $loc1 = $this->createLocation('LOC-A', 'Storeroom A');
        $loc2 = $this->createLocation('LOC-B', 'Storeroom B');

        $item = $this->createItem(['quantity_on_hand' => 40, 'default_location_id' => $loc1->id]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $loc1->id,
            'quantity' => 40,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.transfers.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $response->assertViewHas('preselectedSourceLocationId', (string) $loc1->id);
        $response->assertSee('Contextual Stock Transfer:');
        $response->assertSee('Latex Gloves XL');
    }

    public function test_stock_transfers_index_without_item_id_defaults_cleanly(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.transfers.index'));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertViewHas('preselectedSourceLocationId', null);
    }

    public function test_stock_transfers_index_with_invalid_item_id_handles_gracefully(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.transfers.index', ['item_id' => 999999]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertSessionHas('warning', 'The requested inventory item could not be preselected because it does not exist or is inactive.');
    }

    public function test_catalogue_search_filters_by_item_sku(): void
    {
        $user = $this->createInventoryManager();
        $itemA = $this->createItem(['name' => 'Item A', 'sku' => 'SPECIAL-SKU-99']);
        $itemB = $this->createItem(['name' => 'Item B', 'sku' => 'OTHER-SKU-11']);

        $response = $this->actingAs($user)->get(route('inventory.items', ['search' => 'SPECIAL-SKU-99']));

        $response->assertOk();
        $response->assertSee('SPECIAL-SKU-99');
        $response->assertDontSee('OTHER-SKU-11');
    }

    public function test_inventory_items_catalog_renders_contextual_action_links(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem(['name' => 'Gloves XL', 'sku' => 'PPE-GOWN-XL']);

        $response = $this->actingAs($user)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertSee(route('inventory.requisitions.index', ['item_id' => $item->id]));
        $response->assertSee(route('inventory.adjustments', ['item_id' => $item->id]));
    }

    public function test_material_requisitions_preserves_item_context_when_validation_fails(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem(['name' => 'Gloves XL', 'sku' => 'PPE-GOWN-XL']);

        // Submit with invalid requested_quantity (-1) to trigger validation failure
        $response = $this->actingAs($user)
            ->from(route('inventory.requisitions.index', ['item_id' => $item->id]))
            ->post(route('inventory.requisitions.store'), [
                'context_item_id' => $item->id,
                'department' => 'Emergency Department',
                'lines' => [
                    [
                        'item_id' => $item->id,
                        'requested_quantity' => -1, // invalid
                    ],
                ],
            ]);

        $response->assertRedirect(route('inventory.requisitions.index', ['item_id' => $item->id]));
        $response->assertSessionHasErrors(['lines.0.requested_quantity']);

        // Follow redirect
        $followUp = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));
        $followUp->assertOk();
        $followUp->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $followUp->assertSee('Contextual Item:');
        $followUp->assertSee('Gloves XL');
        $followUp->assertSee('PPE-GOWN-XL');
    }
}
