<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockAlert;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adjustments used to write `quantity_on_hand` directly and record nothing
 * else, so the change was silently discarded the next time the rollup was
 * recomputed from `item_stock_levels`. They now post through
 * InventoryAutomationService like every other stock writer.
 */
class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: InventoryItem, 2: StorageLocation}
     */
    private function stockedItem(int $quantity = 100, int $reorderLevel = 50): array
    {
        // Correcting a counted balance needs adjust_stock, which sits with the
        // inventory manager rather than the staff who did the counting.
        $user = User::factory()->inventoryManager()->create();

        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Alcohol Swabs',
            'sku' => 'SWAB-001',
            'quantity_on_hand' => $quantity,
            'reorder_level' => $reorderLevel,
            'unit_cost' => 1.25,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);

        return [$user, $item, $location];
    }

    public function test_an_increase_writes_the_stock_level_and_a_movement(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100);

        $this->actingAs($user)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'adjustment_type' => 'increase',
            'quantity' => 20,
            'reason' => 'Found in back store',
        ])->assertRedirect('/inventory/adjustments');

        $this->assertSame(120, $item->fresh()->quantity_on_hand);

        // The row the rollup is derived from, not just the rollup.
        $this->assertSame(120, (int) ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $location->id)->value('quantity'));

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'adjustment',
            'quantity' => 20,
            'remarks' => 'Found in back store',
        ]);
    }

    public function test_a_decrease_that_crosses_the_reorder_level_raises_an_alert(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'adjustment_type' => 'decrease',
            'quantity' => 55,
            'reason' => 'Damaged in transit',
        ])->assertRedirect('/inventory/adjustments');

        $item->refresh();
        $this->assertSame(45, $item->quantity_on_hand);
        $this->assertSame('low_stock', $item->stockStatus());

        $this->assertSame(45, (int) StockAlert::where('item_id', $item->id)->value('current_value'));
    }

    /**
     * A correction states the count the shelf should read, so the service is
     * handed the gap rather than the target.
     */
    public function test_a_correction_sets_the_location_to_the_counted_quantity(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100);

        $this->actingAs($user)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'adjustment_type' => 'correction',
            'quantity' => 82,
            'reason' => 'Physical count',
        ])->assertRedirect('/inventory/adjustments');

        $this->assertSame(82, $item->fresh()->quantity_on_hand);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'adjustment',
            'quantity' => -18,
        ]);
    }

    public function test_a_decrease_below_zero_is_rejected_and_changes_nothing(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 10);

        $this->actingAs($user)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'adjustment_type' => 'decrease',
            'quantity' => 25,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(10, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_correction_matching_the_current_count_records_nothing(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100);

        $this->actingAs($user)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'adjustment_type' => 'correction',
            'quantity' => 100,
        ])->assertRedirect('/inventory/adjustments');

        $this->assertSame(100, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_an_adjustment_requires_a_storage_location(): void
    {
        [$user, $item] = $this->stockedItem();

        $this->actingAs($user)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'adjustment_type' => 'increase',
            'quantity' => 5,
        ])->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
