<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementValidationUxTest extends TestCase
{
    use RefreshDatabase;

    private function createStockedItem(int $quantity = 25, string $unit = 'box'): array
    {
        static $counter = 1;
        $idx = $counter++;

        $location = StorageLocation::create([
            'name' => "Ambient Storage Bin A0{$idx}",
            'code' => "W1-Z1-A0{$idx}",
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => "Surgical Gloves ({$unit} {$idx})",
            'sku' => "PPE-GLOVE-{$idx}",
            'unit' => $unit,
            'quantity_on_hand' => $quantity,
            'reorder_level' => 5,
            'unit_cost' => 15.50,
            'total_value' => $quantity * 15.50,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);

        return [$item, $location];
    }

    public function test_stock_movements_page_renders_item_data_with_units_and_per_location_stocks(): void
    {
        [$item, $location] = $this->createStockedItem(25, 'box');
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->get(route('inventory.stock-movements'));

        $response->assertOk();
        $response->assertSee('Stock Movements &amp; Ledger', false);
        $response->assertSee($item->sku);
        $response->assertSee("{$item->sku}) — [box]");
        $response->assertSee('itemsData:');
        $response->assertSee('unitPlural');
        $response->assertSee(':disabled="isZeroStockAtSource"', false);
        $response->assertSee('No available stock');
        $response->assertSee('Confirm Stock Movement');
        $response->assertSee('btn-save-movement');
        $response->assertSee('Record Movement');
        $response->assertSee('record-quick-movement');
    }

    public function test_outbound_movement_with_sufficient_stock_succeeds_and_updates_ledger(): void
    {
        [$item, $location] = $this->createStockedItem(25, 'box');
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->post(route('inventory.stock-movements.store'), [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 10,
            'from_location_id' => $location->id,
            'remarks' => 'Issued to Surgery Ward',
        ]);

        $response->assertRedirect(route('inventory.stock-movements'));
        $response->assertSessionHas('success');

        $this->assertSame(15, $item->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::count());
        $movement = StockMovement::first();
        $this->assertSame(10, $movement->quantity);
        $this->assertSame(MovementType::StockOut, $movement->movement_type);
        $this->assertSame($location->id, $movement->from_location_id);
    }

    public function test_outbound_movement_with_insufficient_stock_fails_with_specific_field_error(): void
    {
        [$item, $location] = $this->createStockedItem(25, 'box');
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)
            ->from(route('inventory.stock-movements'))
            ->post(route('inventory.stock-movements.store'), [
                'item_id' => $item->id,
                'movement_type' => 'stock_out',
                'quantity' => 26,
                'from_location_id' => $location->id,
            ]);

        $response->assertRedirect(route('inventory.stock-movements'));
        $response->assertSessionHasErrors('quantity');

        $this->assertSame(25, $item->fresh()->quantity_on_hand);
        $this->assertSame(0, StockMovement::count());

        $followUp = $this->actingAs($user)->get(route('inventory.stock-movements'));
        $followUp->assertSee('Insufficient stock at the selected location. Short by 1.');
    }

    public function test_stock_in_without_destination_location_fails_validation(): void
    {
        [$item] = $this->createStockedItem(10);
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->post(route('inventory.stock-movements.store'), [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 5,
            'to_location_id' => '',
        ]);

        $response->assertSessionHasErrors('to_location_id');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_issuance_without_ward_fails_validation(): void
    {
        [$item, $location] = $this->createStockedItem(20);
        $user = User::factory()->pharmacyStaff()->create();

        $response = $this->actingAs($user)->post(route('inventory.stock-movements.store'), [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 5,
            'from_location_id' => $location->id,
            'issued_to_location_id' => '',
        ]);

        $response->assertSessionHasErrors('issued_to_location_id');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_return_to_supplier_without_supplier_fails_validation(): void
    {
        [$item, $location] = $this->createStockedItem(20);
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->post(route('inventory.stock-movements.store'), [
            'item_id' => $item->id,
            'movement_type' => 'return_to_supplier',
            'quantity' => 5,
            'from_location_id' => $location->id,
            'return_supplier_id' => '',
        ]);

        $response->assertSessionHasErrors('return_supplier_id');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_non_positive_quantity_fails_validation(): void
    {
        [$item, $location] = $this->createStockedItem(20);
        $user = User::factory()->warehouseStaff()->create();

        foreach ([0, -5] as $badQty) {
            $response = $this->actingAs($user)->post(route('inventory.stock-movements.store'), [
                'item_id' => $item->id,
                'movement_type' => 'stock_out',
                'quantity' => $badQty,
                'from_location_id' => $location->id,
            ]);

            $response->assertSessionHasErrors('quantity');
        }

        $this->assertSame(0, StockMovement::count());
    }

    public function test_unrealistic_quantity_fails_validation(): void
    {
        [$item, $location] = $this->createStockedItem(20);
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->post(route('inventory.stock-movements.store'), [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 1_000_000,
            'to_location_id' => $location->id,
        ]);

        $response->assertSessionHasErrors(['quantity' => 'Quantity cannot exceed 999,999 units per transaction.']);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_transfer_with_unrealistic_quantity_fails_validation(): void
    {
        [$item, $source] = $this->createStockedItem(20);
        $destination = StorageLocation::create([
            'name' => 'Central Pharmacy Staging',
            'code' => 'PHARM-STG-01',
            'status' => 'active',
        ]);
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1_000_000],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.quantity' => 'Quantity cannot exceed 999,999 units per line item.']);
    }

    public function test_optional_unit_filter_renders_available_units_and_supports_proportional_layout(): void
    {
        $this->createStockedItem(10, 'box');
        $this->createStockedItem(15, 'vial');
        $this->createStockedItem(20, 'bottle');

        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->get(route('inventory.stock-movements'));

        $response->assertOk();
        $response->assertSee('unit_filter');
        $response->assertSee('All Units');
        $response->assertSee('Box');
        $response->assertSee('Vial');
        $response->assertSee('Bottle');
        $response->assertSee('md:grid-cols-12', false);
        $response->assertSee('max="999999"', false);
    }

    public function test_movement_history_renders_filter_toolbar_and_scrollable_container(): void
    {
        [$item, $location] = $this->createStockedItem(15, 'vial');
        $user = User::factory()->warehouseStaff()->create();

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 15,
            'to_location_id' => $location->id,
            'user_id' => $user->id,
            'moved_at' => now(),
            'remarks' => 'Initial delivery intake',
        ]);

        $response = $this->actingAs($user)->get(route('inventory.stock-movements'));

        $response->assertOk();
        $response->assertSee('Movement History');
        $response->assertSee('placeholder="Search movements..."', false);
        $response->assertSee('All Movement Types');
        $response->assertSee('All Locations');
        $response->assertSee('table-fixed', false);
        $response->assertSee('sticky top-0', false);
        $response->assertSee('Initial delivery intake');
    }
}
