<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InventoryDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_dashboard_is_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Staff Dashboard');
        $response->assertSee('Monitor inventory health');
    }

    public function test_dashboard_renders_populated_alerts_purchase_orders_and_movements(): void
    {
        // The empty-state test above never exercises the enum casts or the
        // relationship lookups, so drive the dashboard with real demo data.
        $this->seed(InventoryDemoSeeder::class);
        Artisan::call('inventory:check-alerts');

        $item = InventoryItem::where('sku', 'PPE-GLOVE-L')->firstOrFail();
        $location = StorageLocation::where('code', 'WH-01-A')->firstOrFail();
        $supplier = Supplier::firstOrFail();

        PurchaseOrder::create([
            'po_number' => 'PO-DASH-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 20,
            'unit_cost' => 120,
            'total_amount' => 2400,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Needs to both record a movement and see the purchase-order card, so
        // the actor is the manager rather than the warehouse — a warehouse
        // account is refused procurement and the card is hidden from it.
        $user = User::factory()->inventoryManager()->create();

        $this->actingAs($user)
            ->post('/inventory/stock-movements', [
                'item_id' => $item->id,
                'movement_type' => 'stock_out',
                'quantity' => 5,
                'from_location_id' => $location->id,
                'remarks' => 'Issued to ward',
            ])
            ->assertRedirect('/inventory/stock-movements');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Staff Dashboard');
        $response->assertSee('PO-DASH-001');
        $response->assertSee('Surgical Gloves (Large)');
        // Alert sweep flags the gloves; the badge renders the enum label.
        $response->assertSee('Low Stock');
        $response->assertSee('Stock Out');
    }

    public function test_stock_out_updates_inventory_balance_and_status_automatically(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create(['name' => 'Warehouse', 'code' => 'WH01', 'status' => 'active']);

        $item = InventoryItem::create([
            'name' => 'Gloves',
            'sku' => 'GL-100',
            'quantity_on_hand' => 25,
            'reorder_level' => 10,
            'unit_cost' => 5,
            'total_value' => 125,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 25,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 15,
            'from_location_id' => $location->id,
            'remarks' => 'Issued to ward',
        ]);

        $response->assertRedirect('/inventory/stock-movements');
        $item->refresh();
        $this->assertSame(10, $item->quantity_on_hand);
        $this->assertSame('low_stock', $item->status);
        $this->assertSame(50.0, (float) $item->total_value);
    }

    public function test_transfer_rejects_insufficient_stock_before_updating_balance(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $source = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);
        $destination = StorageLocation::create([
            'name' => 'Ward A',
            'code' => 'WARD-A',
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Syringes',
            'sku' => 'SYR-100',
            'quantity_on_hand' => 3,
            'reorder_level' => 1,
            'unit_cost' => 2,
            'total_value' => 6,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $source->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'transfer',
            'quantity' => 5,
            'from_location_id' => $source->id,
            'to_location_id' => $destination->id,
            'remarks' => 'Transfer request',
        ]);

        $response->assertSessionHasErrors('quantity');
        $item->refresh();
        $this->assertSame(3, $item->quantity_on_hand);
    }

    /**
     * Receiving a PO used to add straight onto `quantity_on_hand` and record
     * nothing else, so the receipt vanished the next time anything recomputed
     * the rollup from `item_stock_levels`. It now posts through
     * InventoryAutomationService, which is what this asserts: a level row, a
     * movement row pointing back at the PO, and a rollup that agrees with both.
     */
    public function test_purchase_order_receive_posts_stock_through_the_service(): void
    {
        // Receiving a delivery needs record_movements, which the warehouse holds
        // and procurement-only accounts do not.
        $user = User::factory()->warehouseStaff()->create();
        $supplier = Supplier::create([
            'name' => 'Metro Med Supply',
            'contact_person' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '09170000000',
            'address' => 'Manila',
            'status' => 'active',
        ]);
        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Bandages',
            'sku' => 'BAND-001',
            'quantity_on_hand' => 10,
            'reorder_level' => 5,
            'unit_cost' => 2.5,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_cost' => 2.5,
            'total_amount' => 12.5,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post('/inventory/purchases/'.$purchaseOrder->id.'/receive');

        $response->assertRedirect('/inventory/purchases');
        $this->assertSame('received', $purchaseOrder->fresh()->status);
        $this->assertSame(15, $item->fresh()->quantity_on_hand);

        // The balance the rollup is derived from, not just the rollup itself.
        $this->assertSame(15, (int) ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $location->id)
            ->value('quantity'));

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 5,
            'to_location_id' => $location->id,
            'reference_id' => $purchaseOrder->id,
        ]);
    }

    public function test_purchase_order_receive_is_rejected_when_no_storage_location_exists(): void
    {
        // Receiving a delivery needs record_movements, which the warehouse holds
        // and procurement-only accounts do not.
        $user = User::factory()->warehouseStaff()->create();
        $supplier = Supplier::create([
            'name' => 'Metro Med Supply',
            'contact_person' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '09170000000',
            'address' => 'Manila',
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Bandages',
            'sku' => 'BAND-002',
            'quantity_on_hand' => 0,
            'reorder_level' => 5,
            'unit_cost' => 2.5,
            'status' => 'active',
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-TEST-002',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_cost' => 2.5,
            'total_amount' => 12.5,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post('/inventory/purchases/'.$purchaseOrder->id.'/receive');

        $response->assertSessionHasErrors('receive');
        $this->assertSame('pending', $purchaseOrder->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_inventory_alert_command_marks_low_stock_and_out_of_stock_items(): void
    {
        $location = StorageLocation::create(['name' => 'Main Warehouse', 'code' => 'MWH', 'status' => 'active']);

        $mask = InventoryItem::create([
            'name' => 'Masks',
            'sku' => 'MASK-001',
            'quantity_on_hand' => 6,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 6,
            'status' => 'active',
        ]);

        $needle = InventoryItem::create([
            'name' => 'Needles',
            'sku' => 'NEEDLE-001',
            'quantity_on_hand' => 0,
            'reorder_level' => 5,
            'unit_cost' => 2,
            'total_value' => 0,
            'status' => 'active',
        ]);

        // Phase 2 made quantity_on_hand a cached rollup; create backing stock levels
        ItemStockLevel::create([
            'item_id' => $mask->id,
            'storage_location_id' => $location->id,
            'quantity' => 6,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $needle->id,
            'storage_location_id' => $location->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $exitCode = Artisan::call('inventory:check-alerts');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'MASK-001', 'status' => 'low_stock']);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'NEEDLE-001', 'status' => 'out_of_stock']);
    }
}
