<?php

namespace Tests\Feature;

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `issuance` and `return_to_supplier` are stock-decreasing movements that
 * carry a counterparty: an issuance names the ward that consumed the stock,
 * a return names the vendor it went back to. Both ride the existing
 * `reference_type`/`reference_id` morph, so neither needed a new column.
 *
 * Direction is decided by MovementType::decrementsSource(), which is also
 * what routes a movement through FEFO batch allocation — so these tests
 * pin both the arithmetic and the batch picking.
 */
class SpecificMovementTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: InventoryItem, 2: StorageLocation}
     */
    private function stockedItem(int $quantity = 100, int $reorderLevel = 50): array
    {
        // Warehouse staff hold both issue_stock and record_movements, so one
        // actor covers every type this file exercises. The narrower pharmacy
        // case — issuance yes, stock_in no — is pinned in RoleBasedAccessTest.
        $user = User::factory()->warehouseStaff()->create();

        $location = StorageLocation::create([
            'name' => 'Central Pharmacy',
            'code' => 'PHARM-01',
            'type' => 'pharmacy',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PHARMA-PARA-500',
            'quantity_on_hand' => $quantity,
            'reorder_level' => $reorderLevel,
            'unit_cost' => 1.10,
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

    private function ward(string $name = 'Emergency Ward', string $code = 'ER'): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'type' => 'department',
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------- issuance

    public function test_issuance_decreases_stock_and_records_the_receiving_ward(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100);
        $ward = $this->ward();

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 30,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $ward->id,
            'remarks' => 'Dispensed for night shift',
        ])->assertRedirect('/inventory/stock-movements');

        $this->assertSame(70, $item->fresh()->quantity_on_hand);

        // The balance the rollup is derived from, not just the rollup.
        $this->assertSame(70, (int) ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $location->id)->value('quantity'));

        // Stock left the building — the ward is a reference, not a destination
        // balance, so no stock was created there.
        $this->assertDatabaseMissing('item_stock_levels', [
            'item_id' => $item->id,
            'storage_location_id' => $ward->id,
        ]);

        $movement = StockMovement::where('item_id', $item->id)->latest('id')->firstOrFail();
        $this->assertSame(MovementType::Issuance, $movement->movement_type);
        $this->assertSame(30, $movement->quantity);
        $this->assertSame($location->id, $movement->from_location_id);
        $this->assertNull($movement->to_location_id);
        $this->assertSame(StorageLocation::class, $movement->reference_type);
        $this->assertSame($ward->id, $movement->reference_id);
        $this->assertSame('Emergency Ward', $movement->reference->name);
    }

    /**
     * FEFO is not special-cased per type — it is reached through
     * decrementsSource(), so adding Issuance there is what gives it the same
     * earliest-expiry-first picking stock_out already had. This asserts the
     * outcome rather than trusting that wiring.
     */
    public function test_issuance_draws_from_the_earliest_expiring_batch_first(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 0, reorderLevel: 5);

        $expiresLater = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-LATER',
            'expiry_date' => now()->addMonths(9)->toDateString(),
            'received_at' => now()->toDateString(),
            'unit_cost' => 1.10,
            'status' => 'active',
        ]);

        $expiresSooner = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-SOONER',
            'expiry_date' => now()->addMonth()->toDateString(),
            'received_at' => now()->toDateString(),
            'unit_cost' => 1.10,
            'status' => 'active',
        ]);

        // Deliberately create the later-expiring level row first, so passing
        // cannot be an artefact of insertion order.
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'item_batch_id' => $expiresLater->id,
            'quantity' => 40,
            'reserved_quantity' => 0,
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'item_batch_id' => $expiresSooner->id,
            'quantity' => 25,
            'reserved_quantity' => 0,
        ]);

        $item->forceFill(['quantity_on_hand' => 65])->save();

        // 30 units: the whole 25 from the sooner batch, then 5 from the later.
        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 30,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $this->ward()->id,
        ])->assertRedirect('/inventory/stock-movements');

        $this->assertSame(0, (int) ItemStockLevel::where('item_batch_id', $expiresSooner->id)->value('quantity'));
        $this->assertSame(35, (int) ItemStockLevel::where('item_batch_id', $expiresLater->id)->value('quantity'));
        $this->assertSame(35, $item->fresh()->quantity_on_hand);

        // One logical issuance spanning two batches writes one row per batch.
        $movements = StockMovement::where('item_id', $item->id)
            ->ofType(MovementType::Issuance)->get();

        $this->assertCount(2, $movements);
        $this->assertSame(25, (int) $movements->firstWhere('item_batch_id', $expiresSooner->id)->quantity);
        $this->assertSame(5, (int) $movements->firstWhere('item_batch_id', $expiresLater->id)->quantity);
    }

    public function test_issuance_beyond_available_stock_is_rejected(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 20);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 50,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $this->ward()->id,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(20, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_issuance_requires_a_recipient(): void
    {
        [$user, $item, $location] = $this->stockedItem();

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 10,
            'from_location_id' => $location->id,
        ])->assertSessionHasErrors('issued_to_location_id');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    // ------------------------------------------------------- return to supplier

    public function test_return_to_supplier_decreases_stock_and_records_the_supplier(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100);

        $supplier = Supplier::create([
            'name' => 'MedSupply Corp',
            'contact_person' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '09170000000',
            'address' => 'Manila',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'return_to_supplier',
            'quantity' => 25,
            'from_location_id' => $location->id,
            'return_supplier_id' => $supplier->id,
            'remarks' => 'Damaged on delivery',
        ])->assertRedirect('/inventory/stock-movements');

        $this->assertSame(75, $item->fresh()->quantity_on_hand);
        $this->assertSame(75, (int) ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $location->id)->value('quantity'));

        $movement = StockMovement::where('item_id', $item->id)->latest('id')->firstOrFail();
        $this->assertSame(MovementType::ReturnToSupplier, $movement->movement_type);
        $this->assertSame(25, $movement->quantity);
        $this->assertNull($movement->to_location_id);
        $this->assertSame(Supplier::class, $movement->reference_type);
        $this->assertSame($supplier->id, $movement->reference_id);
        $this->assertSame('MedSupply Corp', $movement->reference->name);

        // The reason rides in remarks rather than a dedicated column.
        $this->assertSame('Damaged on delivery', $movement->remarks);
    }

    public function test_return_to_supplier_requires_a_supplier(): void
    {
        [$user, $item, $location] = $this->stockedItem();

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'return_to_supplier',
            'quantity' => 10,
            'from_location_id' => $location->id,
        ])->assertSessionHasErrors('return_supplier_id');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    // ------------------------------------------------------------------ alerts

    /**
     * Mirrors StockAlertReactivityTest's stock_out coverage. Alerts are
     * re-evaluated in syncItemTotals(), which every stock writer passes
     * through — but "should already work" is not evidence, so assert it.
     */
    public function test_issuance_crossing_the_reorder_level_raises_an_alert(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 60,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $this->ward()->id,
        ])->assertRedirect('/inventory/stock-movements');

        $item->refresh();
        $this->assertSame(40, $item->quantity_on_hand);
        $this->assertSame('low_stock', $item->stockStatus());

        $alert = StockAlert::where('item_id', $item->id)->firstOrFail();
        $this->assertSame(AlertType::LowStock, $alert->type);
        $this->assertSame(AlertStatus::Open, $alert->status);
        $this->assertSame(40, (int) $alert->current_value);
        $this->assertSame(50, (int) $alert->threshold_value);
    }

    public function test_return_to_supplier_crossing_the_reorder_level_raises_an_alert(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $supplier = Supplier::create([
            'name' => 'MedSupply Corp',
            'contact_person' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '09170000000',
            'address' => 'Manila',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'return_to_supplier',
            'quantity' => 60,
            'from_location_id' => $location->id,
            'return_supplier_id' => $supplier->id,
            'remarks' => 'Recalled batch',
        ])->assertRedirect('/inventory/stock-movements');

        $item->refresh();
        $this->assertSame(40, $item->quantity_on_hand);
        $this->assertSame('low_stock', $item->stockStatus());

        $this->assertSame(40, (int) StockAlert::where('item_id', $item->id)->value('current_value'));
    }

    public function test_replenishing_after_an_issuance_clears_the_alert(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 60,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $this->ward()->id,
        ]);

        $this->assertSame(AlertStatus::Open, StockAlert::where('item_id', $item->id)->firstOrFail()->status);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 80,
            'to_location_id' => $location->id,
        ]);

        $this->assertSame(120, $item->fresh()->quantity_on_hand);
        $this->assertSame(AlertStatus::Resolved, StockAlert::where('item_id', $item->id)->firstOrFail()->status);
    }

    // -------------------------------------------------------------------- misc

    public function test_the_dashboard_live_endpoint_reflects_an_issuance(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100, reorderLevel: 50);

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 60,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $this->ward()->id,
        ]);

        $this->actingAs($user)->get('/dashboard/live')
            ->assertJsonPath('openAlertCount', 1)
            ->assertJsonPath('lowStockItems', 1)
            ->assertJsonPath('totalOnHand', 40);
    }

    public function test_the_form_offers_both_new_types(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        $this->actingAs($user)->get('/inventory/stock-movements')
            ->assertStatus(200)
            ->assertSee('Issuance — dispense to a ward or department')
            ->assertSee('Return to Supplier — send back to vendor');
    }

    public function test_the_api_accepts_the_new_types(): void
    {
        [$user, $item, $location] = $this->stockedItem(quantity: 100);

        $this->actingAs($user)->postJson('/api/v1/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 15,
            'from_location_id' => $location->id,
        ])->assertStatus(201);

        $this->assertSame(85, $item->fresh()->quantity_on_hand);
    }
}
