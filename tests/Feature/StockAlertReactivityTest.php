<?php

namespace Tests\Feature;

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockAlert;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reported bug: alerts only ever changed when the nightly
 * `inventory:check-alerts` command ran, so the dashboard could be up to 24
 * hours behind the stock it was describing. Alerts are now re-evaluated
 * inside the same transaction as the stock change that caused them, and
 * these tests hold that line.
 */
class StockAlertReactivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: InventoryItem, 2: StorageLocation}
     */
    private function stockedItem(int $quantity = 100, int $reorderLevel = 50): array
    {
        $user = User::factory()->warehouseStaff()->create();

        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Surgical Gloves (Large)',
            'sku' => 'PPE-GLOVE-L',
            'quantity_on_hand' => $quantity,
            'reorder_level' => $reorderLevel,
            'unit_cost' => 4.5,
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

    public function test_stock_out_crossing_the_reorder_level_raises_an_alert_immediately(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->assertDatabaseCount('stock_alerts', 0);

        // 100 - 60 = 40, which is below the reorder level of 50.
        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 60,
            'from_location_id' => $location->id,
        ])->assertRedirect('/inventory/stock-movements');

        $item->refresh();
        $this->assertSame(40, $item->quantity_on_hand);
        $this->assertSame('low_stock', $item->status);

        // No scheduled command was run — the alert exists because the
        // movement raised it.
        $alert = StockAlert::where('item_id', $item->id)->firstOrFail();
        $this->assertSame(AlertType::LowStock, $alert->type);
        $this->assertSame(AlertStatus::Open, $alert->status);
        $this->assertSame(40, (int) $alert->current_value);
        $this->assertSame(50, (int) $alert->threshold_value);
    }

    public function test_stock_in_above_the_reorder_level_resolves_the_open_alert(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 25, reorderLevel: 30);

        // Raise the alert the same way the app would.
        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 1,
            'from_location_id' => $location->id,
        ]);

        $this->assertSame(
            AlertStatus::Open,
            StockAlert::where('item_id', $item->id)->firstOrFail()->status
        );

        // Replenish well above the reorder level.
        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 100,
            'to_location_id' => $location->id,
        ])->assertRedirect('/inventory/stock-movements');

        $item->refresh();
        $this->assertSame(124, $item->quantity_on_hand);
        $this->assertSame('in_stock', $item->status);

        $this->assertSame(
            AlertStatus::Resolved,
            StockAlert::where('item_id', $item->id)->firstOrFail()->status
        );
    }

    /**
     * `current_value` is a snapshot taken when the alert is raised, and the
     * dashboard reads it. An un-refreshed row keeps reporting the quantity
     * from whenever the condition first tripped — which is exactly what the
     * screenshot in the bug report showed.
     */
    public function test_an_already_open_alert_has_its_numbers_refreshed_not_frozen(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 60,
            'from_location_id' => $location->id,
        ]);

        $this->assertSame(40, (int) StockAlert::where('item_id', $item->id)->value('current_value'));

        // Still below the reorder level, so the same alert stays open.
        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 15,
            'from_location_id' => $location->id,
        ]);

        $this->assertSame(1, StockAlert::where('item_id', $item->id)->count());
        $this->assertSame(25, (int) StockAlert::where('item_id', $item->id)->value('current_value'));
    }

    public function test_dropping_to_zero_replaces_the_low_stock_alert_with_out_of_stock(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 60,
            'from_location_id' => $location->id,
        ]);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 40,
            'from_location_id' => $location->id,
        ]);

        $item->refresh();
        $this->assertSame(0, $item->quantity_on_hand);
        $this->assertSame('out_of_stock', $item->status);

        $this->assertSame(AlertStatus::Resolved, StockAlert::where('item_id', $item->id)
            ->where('type', AlertType::LowStock->value)->firstOrFail()->status);

        $this->assertSame(AlertStatus::Open, StockAlert::where('item_id', $item->id)
            ->where('type', AlertType::OutOfStock->value)->firstOrFail()->status);
    }

    public function test_the_dashboard_shows_the_live_quantity_rather_than_the_alert_snapshot(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 60,
            'from_location_id' => $location->id,
        ]);

        // Freeze the alert at a value the item no longer holds, mimicking a
        // row written before the fix landed.
        StockAlert::where('item_id', $item->id)->update(['current_value' => 999]);

        $this->actingAs($user)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Surgical Gloves (Large)')
            ->assertDontSeeText('999');
    }
}
