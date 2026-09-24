<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\InventoryAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $manager = User::factory()->create([
            'name' => 'Inventory Manager',
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $staff = User::factory()->create([
            'name' => 'Warehouse Staff',
            'role' => UserRole::WarehouseStaff,
            'status' => 'active',
        ]);

        $viewer = User::factory()->create([
            'name' => 'Auditor Viewer',
            'role' => UserRole::Viewer,
            'status' => 'active',
        ]);

        // Source warehouse (zone is null)
        $source = StorageLocation::create([
            'name' => 'Main Pharmacy Storeroom',
            'code' => 'MAIN-PHARM',
            'type' => 'storeroom',
            'zone' => null,
            'status' => 'active',
        ]);

        // Destination ward (zone specified)
        $destination = StorageLocation::create([
            'name' => 'Emergency Room Sat-Store',
            'code' => 'ER-STORE',
            'type' => 'ward',
            'zone' => 'Emergency',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'sku' => 'MED-PAR-500',
            'name' => 'Paracetamol 500mg Tablets',
            'unit' => 'box',
            'unit_cost' => 120.00,
            'quantity_on_hand' => 100,
            'default_location_id' => $source->id,
            'status' => 'active',
        ]);

        // Stock balance at source location
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $source->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'quarantined_quantity' => 0,
            'blocked_quantity' => 0,
            'in_transit_quantity' => 0,
        ]);

        return compact('manager', 'staff', 'viewer', 'source', 'destination', 'item');
    }

    public function test_transfers_index_screen_renders_initiate_button_in_same_alpine_scope_for_authorized_user(): void
    {
        extract($this->createSetup());

        $this->actingAs($manager);

        $response = $this->get(route('inventory.transfers.index'));

        $response->assertStatus(200);

        // Verify button exists with descriptive ID and Alpine click action
        $response->assertSee('id="btn-initiate-stock-transfer"', false);
        $response->assertSee('Initiate Stock Transfer');
        $response->assertSee('@click="newTransferModal = true"', false);

        // Verify Alpine component scope and listeners are on the container enclosing the button and modal
        $response->assertSee('newTransferModal: false', false);
        $response->assertSee('@open-new-transfer-modal.window="newTransferModal = true"', false);
        $response->assertSee('@keydown.escape.window="newTransferModal = false"', false);
        $response->assertSee('x-show="newTransferModal"', false);

        // Verify storage locations with null zone are included in view data
        $locations = $response->viewData('locations');
        $this->assertTrue($locations->contains('id', $source->id), 'Location with null zone must be included in transfer locations');
        $this->assertTrue($locations->contains('id', $destination->id));
    }

    public function test_unauthorized_user_cannot_see_initiate_stock_transfer_button_or_dispatch_transfer(): void
    {
        extract($this->createSetup());

        // Viewer role has ViewInventory but NOT TransferStock
        $this->actingAs($viewer);

        $response = $this->get(route('inventory.transfers.index'));
        $response->assertStatus(200);
        $response->assertDontSee('id="btn-initiate-stock-transfer"', false);

        // Direct POST to store is blocked by authorization middleware
        $postResponse = $this->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $postResponse->assertStatus(403);
    }

    public function test_initiating_stock_transfer_dispatches_items_to_in_transit_buffer(): void
    {
        extract($this->createSetup());

        $this->actingAs($staff);

        $response = $this->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'notes' => 'Replenishment for emergency room ward cache.',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 25],
            ],
        ]);

        $response->assertRedirect(route('inventory.transfers.index'));
        $response->assertSessionHas('success');

        // Verify transfer created with in_transit status
        $transfer = StockTransfer::where('source_location_id', $source->id)
            ->where('destination_location_id', $destination->id)
            ->first();

        $this->assertNotNull($transfer);
        $this->assertEquals('in_transit', $transfer->status);
        $this->assertEquals($staff->id, $transfer->dispatched_by_id);

        // Verify ledger movement posted
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::TransferDispatch->value,
            'from_location_id' => $source->id,
            'quantity' => 25,
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
        ]);

        // Verify source stock level was decremented by 25
        $sourceLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $source->id)
            ->first();
        $this->assertEquals(75, $sourceLevel->quantity);

        // Verify in-transit buffer level was incremented by 25
        $inTransitLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $transfer->in_transit_location_id)
            ->first();
        $this->assertEquals(25, $inTransitLevel->in_transit_quantity);
    }

    public function test_receiving_stock_transfer_at_destination_completes_movement(): void
    {
        extract($this->createSetup());

        $this->actingAs($staff);

        // 1. Dispatch transfer
        $this->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 20],
            ],
        ]);

        $transfer = StockTransfer::where('source_location_id', $source->id)->first();
        $line = $transfer->lines->first();

        // 2. Receive at destination
        $receiveResponse = $this->post(route('inventory.transfers.receive', $transfer), [
            'lines' => [
                [
                    'line_id' => $line->id,
                    'received_quantity' => 20,
                    'damaged_quantity' => 0,
                    'lost_quantity' => 0,
                ],
            ],
        ]);

        $receiveResponse->assertRedirect(route('inventory.transfers.show', $transfer));
        $receiveResponse->assertSessionHas('success');

        $this->assertEquals('received', $transfer->fresh()->status);
        $this->assertEquals($staff->id, $transfer->fresh()->received_by_id);

        // Verify destination stock incremented
        $destLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $destination->id)
            ->first();
        $this->assertNotNull($destLevel);
        $this->assertEquals(20, $destLevel->quantity);

        // Verify in-transit buffer decremented back to 0
        $inTransitLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $transfer->in_transit_location_id)
            ->first();
        $this->assertEquals(0, $inTransitLevel->in_transit_quantity);
    }

    public function test_receive_button_opens_a_modal_inside_the_same_alpine_scope(): void
    {
        extract($this->createSetup());

        $this->actingAs($staff)->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 20]],
        ]);

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();
        $response = $this->get(route('inventory.transfers.show', $transfer));

        $response->assertOk()
            ->assertSee('id="btn-receive-stock-destination"', false)
            ->assertSee('@open-receive-modal.window="receiveModalOpen = true"', false)
            ->assertSee('id="receive-stock-modal"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(
            1,
            $xpath->query('//*[@id="receive-stock-modal" and ancestor::*[@id="stock-transfer-receiving-workflow"]]')->length,
            'The receive modal must be inside the Alpine component that owns receiveModalOpen.',
        );
    }

    public function test_destination_reconciliation_rejects_invalid_totals_without_changing_stock(): void
    {
        extract($this->createSetup());

        $this->actingAs($staff)->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 20]],
        ]);

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();
        $line = $transfer->lines()->firstOrFail();

        $this->from(route('inventory.transfers.show', $transfer))
            ->post(route('inventory.transfers.receive', $transfer), [
                'lines' => [[
                    'line_id' => $line->id,
                    'received_quantity' => 20,
                    'damaged_quantity' => 1,
                    'lost_quantity' => 0,
                ]],
                'discrepancy_reason' => 'One carton was damaged.',
            ])
            ->assertRedirect(route('inventory.transfers.show', $transfer))
            ->assertSessionHasErrors('lines');

        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertSame(20, ItemStockLevel::query()
            ->where('item_id', $item->id)
            ->where('storage_location_id', $transfer->in_transit_location_id)
            ->value('in_transit_quantity'));
        $this->assertDatabaseMissing('item_stock_levels', [
            'item_id' => $item->id,
            'storage_location_id' => $destination->id,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
            'movement_type' => MovementType::TransferReceipt->value,
        ]);
    }

    public function test_duplicate_destination_receipt_does_not_post_stock_twice(): void
    {
        extract($this->createSetup());

        $this->actingAs($staff)->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 20]],
        ]);

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();
        $line = $transfer->lines()->firstOrFail();
        $payload = [
            'lines' => [[
                'line_id' => $line->id,
                'received_quantity' => 20,
                'damaged_quantity' => 0,
                'lost_quantity' => 0,
            ]],
        ];

        $this->post(route('inventory.transfers.receive', $transfer), $payload)
            ->assertSessionHas('success');
        $this->post(route('inventory.transfers.receive', $transfer), $payload)
            ->assertSessionHasErrors('receive');

        $this->assertSame(20, ItemStockLevel::query()
            ->where('item_id', $item->id)
            ->where('storage_location_id', $destination->id)
            ->value('quantity'));
        $this->assertSame(1, StockMovement::query()
            ->where('reference_type', StockTransfer::class)
            ->where('reference_id', $transfer->id)
            ->where('movement_type', MovementType::TransferReceipt)
            ->count());
    }

    public function test_initiating_stock_transfer_validates_stock_availability_and_rejects_insufficient_quantity(): void
    {
        extract($this->createSetup());

        $this->actingAs($staff);

        // Source location has 100 units. Requesting 150 units must fail validation.
        $response = $this->from(route('inventory.transfers.index'))->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'notes' => 'Excess transfer request',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 150],
            ],
        ]);

        $response->assertRedirect(route('inventory.transfers.index'));
        $response->assertSessionHasErrors(['lines.0.quantity']);

        // Verify index view provides locationStockMap for reactive validation
        $indexResponse = $this->get(route('inventory.transfers.index'));
        $indexResponse->assertOk();
        $locationStockMap = $indexResponse->viewData('locationStockMap');
        $this->assertArrayHasKey($source->id, $locationStockMap);
        $this->assertEquals(100, $locationStockMap[$source->id][$item->id]);

        // Verify that no transfer was created
        $this->assertEquals(0, StockTransfer::count());
    }

    public function test_initiating_and_receiving_batch_tracked_stock_transfer_decrements_origin_and_increments_destination(): void
    {
        extract($this->createSetup());

        $batch = \App\Models\ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-TEST-001',
            'expiry_date' => now()->addYear()->toDateString(),
            'received_at' => now()->toDateString(),
            'unit_cost' => 12.50,
            'status' => 'active',
        ]);

        // Put stock specifically in a batch level at the source location
        ItemStockLevel::where('item_id', $item->id)->delete();
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $source->id,
            'item_batch_id' => $batch->id,
            'quantity' => 40,
        ]);
        app(InventoryAutomationService::class)->syncItemTotals($item);

        $this->actingAs($staff);

        // 1. Dispatch 15 units of Dobutrex/batch-tracked item
        $response = $this->post(route('inventory.transfers.store'), [
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'notes' => 'Batch-tracked dispatch',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 15],
            ],
        ]);

        $response->assertRedirect(route('inventory.transfers.index'));

        // Verify source batch stock was decremented from 40 to 25
        $sourceLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $source->id)
            ->where('item_batch_id', $batch->id)
            ->first();
        $this->assertEquals(25, $sourceLevel->quantity);

        // Verify available count in stock map is now 25
        $indexResponse = $this->get(route('inventory.transfers.index'));
        $locationStockMap = $indexResponse->viewData('locationStockMap');
        $this->assertEquals(25, $locationStockMap[$source->id][$item->id]);

        // 2. Receive transfer at destination
        $transfer = StockTransfer::latest('id')->first();
        $line = $transfer->lines->first();
        $this->assertEquals($batch->id, $line->item_batch_id);
        $this->assertEquals(15, $line->dispatched_quantity);

        $receiveResponse = $this->post(route('inventory.transfers.receive', $transfer), [
            'lines' => [
                [
                    'line_id' => $line->id,
                    'received_quantity' => 15,
                    'damaged_quantity' => 0,
                    'lost_quantity' => 0,
                ],
            ],
        ]);

        $receiveResponse->assertRedirect(route('inventory.transfers.show', $transfer));

        // Destination received the 15 units into its batch level
        $destLevel = ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $destination->id)
            ->where('item_batch_id', $batch->id)
            ->first();
        $this->assertNotNull($destLevel);
        $this->assertEquals(15, $destLevel->quantity);

        // Total item stock equals 25 (source) + 15 (dest) = 40
        $this->assertEquals(40, $item->fresh()->quantity_on_hand);
    }
}
