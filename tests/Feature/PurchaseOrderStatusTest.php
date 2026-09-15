<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Logistics\InspectionAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The purchase order status column, the API validation rule, and the enum used
 * to be three different vocabularies. These tests pin them to one.
 */
class PurchaseOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rejects_a_status_outside_the_purchase_order_vocabulary(): void
    {
        // 'pending' was the old column default and was never an enum case. A row
        // holding it could not be received and was not counted as closed either,
        // so no endpoint may still be able to write it.
        $order = $this->purchaseOrder(PurchaseOrderStatus::Approved);

        $this->putJson('/api/v1/purchase-orders/'.$order->id, ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(PurchaseOrderStatus::Approved, $order->fresh()->statusEnum());
    }

    public function test_api_accepts_the_status_the_receive_action_records(): void
    {
        // receive() writes 'received' directly, so it has to be a valid status
        // here too — otherwise the receive path itself would be unfixable
        // through the API that exposes the same column.
        $order = $this->purchaseOrder(PurchaseOrderStatus::Approved);

        $this->putJson('/api/v1/purchase-orders/'.$order->id, [
            'status' => PurchaseOrderStatus::Received->value,
        ])->assertOk()->assertJsonFragment(['status' => 'received']);

        $refreshed = $order->fresh();
        $this->assertSame(PurchaseOrderStatus::Received, $refreshed->statusEnum());
        $this->assertFalse($refreshed->statusEnum()->isOpen());
        $this->assertTrue($refreshed->isFullyReceived());
    }

    public function test_purchase_orders_inserted_without_a_status_start_in_draft(): void
    {
        // The column default is the vocabulary's own first state, so an insert
        // that omits the status no longer creates an order that nothing can
        // move forward from.
        $supplier = Supplier::create([
            'name' => 'Default Status Supply',
            'contact_person' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '09170000000',
            'address' => 'Manila',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Gauze Pads',
            'sku' => 'GAUZE-001',
            'quantity_on_hand' => 0,
            'reorder_level' => 5,
            'unit_cost' => 2.5,
            'status' => 'active',
        ]);

        DB::table('purchase_orders')->insert([
            'po_number' => 'PO-DEFAULT-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
        ]);

        $created = PurchaseOrder::where('po_number', 'PO-DEFAULT-001')->firstOrFail();

        $this->assertSame(PurchaseOrderStatus::Draft, $created->statusEnum());
        $this->assertSame(PurchaseOrderStatus::Draft->value, $created->status);

        // change() rebuilds the column from what the migration states, so the
        // constraints the original migration set have to survive it.
        $column = collect(Schema::getColumns('purchase_orders'))->firstWhere('name', 'status');
        $this->assertFalse($column['nullable'], 'The status column must stay NOT NULL.');
    }

    public function test_partial_ia_acceptance_leaves_the_order_in_an_enum_status(): void
    {
        // The IA acceptance path used to write 'partially_received', which is
        // not an enum case: tryFrom() returned null, so the order displayed as
        // Draft and fell out of every enum-driven guard. The goods receipt path
        // writes PartiallyFulfilled for the very same condition.
        $location = StorageLocation::create([
            'name' => 'Central Receiving',
            'code' => 'TEST-RECV-01',
            'type' => 'room',
            'status' => 'active',
        ]);

        $supplier = Supplier::create([
            'name' => 'Zuellig Pharma Test',
            'contact_person' => 'Danilo Bautista',
            'email' => 'zuellig.test@example.com',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'sku' => 'TEST-RAB-001',
            'name' => 'Rabies Vaccine Test 0.5mL',
            'unit' => 'vial',
            'unit_cost' => 1000.00,
            'supplier_id' => $supplier->id,
            'default_location_id' => $location->id,
            'status' => 'active',
        ]);

        $buyer = User::factory()->create(['role' => UserRole::InventoryManager, 'status' => 'active']);
        $inspector = User::factory()->create(['role' => UserRole::InventoryManager, 'status' => 'active']);
        $custodian = User::factory()->create(['role' => UserRole::InventoryManager, 'status' => 'active']);

        $order = PurchaseOrder::create([
            'po_number' => 'PO-PARTIAL-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 20,
            'unit_cost' => 1000.00,
            'total_amount' => 20000.00,
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $buyer->id,
        ]);

        $receipt = GoodsReceiptNote::create([
            'grn_number' => 'GRN-PARTIAL-001',
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
        ]);

        // Ten of the twenty ordered units, so the order stays open.
        GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $receipt->id,
            'item_id' => $item->id,
            'ordered_quantity' => 20,
            'received_quantity' => 10,
            'accepted_quantity' => 10,
            'unit_cost' => 1000.00,
        ]);

        $service = app(InspectionAcceptanceService::class);
        $report = $service->createFromReceipt($receipt, [], $buyer);

        $service->performTechnicalInspection($report, [
            'inspection_status' => 'in_order',
            'inspection_findings' => 'Partial delivery passed inspection.',
        ], $inspector);

        $service->performCustodialAcceptance($report, ['delivery_status' => 'partial'], $custodian);

        $refreshed = $order->fresh();
        $this->assertSame(PurchaseOrderStatus::PartiallyFulfilled->value, $refreshed->status);
        $this->assertSame(PurchaseOrderStatus::PartiallyFulfilled, $refreshed->statusEnum());
        // The balance is still receivable, which is what the null tryFrom() cost.
        $this->assertTrue($refreshed->statusEnum()->canReceiveStock());
    }

    public function test_the_backfill_normalises_legacy_status_spellings(): void
    {
        $order = $this->purchaseOrder(PurchaseOrderStatus::Approved);

        // The two spellings no guard recognised, written behind the model so the
        // enum cast cannot normalise them on the way in.
        DB::table('purchase_orders')->where('id', $order->id)->update(['status' => 'pending']);

        $second = PurchaseOrder::create([
            'po_number' => 'PO-STATUS-002',
            'supplier_id' => $order->supplier_id,
            'item_id' => $order->item_id,
            'quantity' => 5,
            'unit_cost' => 2.5,
            'total_amount' => 12.5,
            'status' => PurchaseOrderStatus::Approved->value,
        ]);
        DB::table('purchase_orders')->where('id', $second->id)->update(['status' => 'partially_received']);

        $migration = require database_path('migrations/2026_09_15_000002_backfill_legacy_purchase_order_status_values.php');
        $migration->up();

        $this->assertSame(PurchaseOrderStatus::Draft, $order->fresh()->statusEnum());
        $this->assertSame(PurchaseOrderStatus::PartiallyFulfilled, $second->fresh()->statusEnum());
    }

    private function purchaseOrder(PurchaseOrderStatus $status): PurchaseOrder
    {
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
            'sku' => 'BAND-STATUS-001',
            'quantity_on_hand' => 0,
            'reorder_level' => 5,
            'unit_cost' => 2.5,
            'status' => 'active',
        ]);

        // Approving purchase orders is the permission the API update route is
        // gated on, and the inventory manager is the role that holds it.
        Sanctum::actingAs(User::factory()->role(UserRole::InventoryManager)->create(), ['*']);

        return PurchaseOrder::create([
            'po_number' => 'PO-STATUS-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_cost' => 2.5,
            'total_amount' => 12.5,
            'status' => $status->value,
        ]);
    }
}
