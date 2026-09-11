<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/dashboard/live` is what the Alpine poller hits every 30s. It returns the
 * alert table as rendered HTML plus the counters in the stat tiles, so the
 * page can reflect stock recorded elsewhere without a full reload.
 */
class DashboardLiveEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: InventoryItem, 2: StorageLocation}
     */
    private function stockedItem(int $quantity = 100, int $reorderLevel = 50): array
    {
        // The counters are driven by recording movements and financial totals,
        // so the actor needs record_movements and view_procurement_sensitive_data.
        $user = User::factory()->inventoryManager()->create();

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

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->get('/dashboard/live')->assertRedirect('/login');
    }

    public function test_it_returns_the_alert_markup_and_the_tile_counters(): void
    {
        [$user] = $this->stockedItem();

        $response = $this->actingAs($user)->get('/dashboard/live');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'alertsHtml',
                'openAlertCount',
                'lowStockItems',
                'outOfStockItems',
                'totalOnHand',
                'totalInventoryValue',
            ]);

        $this->assertSame(0, $response->json('openAlertCount'));
        $this->assertSame(0, $response->json('lowStockItems'));
        $this->assertSame(100, $response->json('totalOnHand'));
        $this->assertStringContainsString('No active alerts', $response->json('alertsHtml'));
    }

    /**
     * This is the behaviour the poll exists for: stock recorded on another
     * screen shows up on the next tick without a page reload.
     */
    public function test_it_reflects_a_stock_movement_recorded_after_the_page_loaded(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->get('/dashboard/live')
            ->assertJsonPath('openAlertCount', 0)
            ->assertJsonPath('lowStockItems', 0);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 60,
            'from_location_id' => $location->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard/live');

        $response->assertJsonPath('openAlertCount', 1)
            ->assertJsonPath('lowStockItems', 1)
            ->assertJsonPath('totalOnHand', 40);

        // The rendered partial carries the live quantity, not the snapshot.
        $this->assertStringContainsString('Surgical Gloves (Large)', $response->json('alertsHtml'));
        $this->assertStringContainsString('40', $response->json('alertsHtml'));
    }

    public function test_it_clears_the_counters_once_stock_is_replenished(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 60,
            'from_location_id' => $location->id,
        ]);

        $this->actingAs($user)->get('/dashboard/live')->assertJsonPath('openAlertCount', 1);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 100,
            'to_location_id' => $location->id,
        ]);

        $this->actingAs($user)->get('/dashboard/live')
            ->assertJsonPath('openAlertCount', 0)
            ->assertJsonPath('lowStockItems', 0)
            ->assertJsonPath('totalOnHand', 140);
    }
}
