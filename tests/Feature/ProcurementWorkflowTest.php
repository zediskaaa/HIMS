<?php

namespace Tests\Feature;

use App\Enums\SupplierAccreditationStatus;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The reported symptom: after creating a supplier and a procurement request,
 * the Supplier quotations card stayed empty no matter what.
 *
 * The cause was not the data. `POST /inventory/purchases/quotes` and
 * ProcurementController::storeQuote() had both existed since the table was
 * created, but nothing on the screen posted to them — the card was a read-only
 * list fed by the API, so a quote could never be raised in the first place.
 * The approve route had the same hole: it existed, no button called it, and a
 * request therefore stayed `pending` forever.
 *
 * These tests pin the two ends that were disconnected: that the screen offers
 * the controls, and that posting them writes what the next stage reads.
 */
class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function procurementOfficer(): User
    {
        // Requisitions and quote evaluation are manage_procurement, which the
        // inventory manager holds and the warehouse and pharmacy do not.
        return User::factory()->inventoryManager()->create();
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'PPE-N95',
            'quantity_on_hand' => 0,
            'reorder_level' => 100,
            'unit_cost' => 45,
            'status' => 'active',
        ]);
    }

    private function supplier(string $name = 'Jeffrey Corporation'): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'status' => 'active',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
    }

    private function request(?Supplier $supplier = null): ProcurementRequest
    {
        return ProcurementRequest::create([
            'request_number' => 'REQ-20260808120000',
            'title' => 'Jeff Corp Request',
            'item_id' => $this->item()->id,
            'requested_quantity' => 1000,
            'priority' => 'medium',
            'supplier_id' => $supplier?->id,
        ]);
    }

    // ------------------------------------------------------ stage 3: the quote

    /**
     * The missing form itself. Asserting the list card renders would have
     * passed against the broken build — it always did.
     */
    public function test_the_procurement_screen_offers_a_quote_form(): void
    {
        $this->request($this->supplier());

        $this->actingAs($this->procurementOfficer())
            ->get('/inventory/purchases')
            ->assertStatus(200)
            ->assertSee('Stage 3 &bull; Supplier quotation', false)
            ->assertSee('name="procurement_request_id"', false)
            ->assertSee('name="quoted_price"', false)
            // The request has to be selectable, not just the field present.
            ->assertSee('REQ-20260808120000');
    }

    public function test_submitting_a_quote_records_it_against_the_request(): void
    {
        $supplier = $this->supplier();
        $procurementRequest = $this->request($supplier);

        $this->actingAs($this->procurementOfficer())
            ->post('/inventory/purchases/quotes', [
                'procurement_request_id' => $procurementRequest->id,
                'supplier_id' => $supplier->id,
                'quoted_price' => 41500.50,
                'notes' => '7-day lead time, VAT inclusive',
            ])
            ->assertRedirect('/inventory/purchases')
            ->assertSessionHas('success');

        $quote = SupplierQuote::firstOrFail();

        $this->assertSame($procurementRequest->id, $quote->procurement_request_id);
        $this->assertSame($supplier->id, $quote->supplier_id);
        $this->assertSame(41500.50, (float) $quote->quoted_price);
        $this->assertSame('7-day lead time, VAT inclusive', $quote->notes);

        // storeQuote() never sets a status, so the migration default stands.
        $this->assertSame('submitted', $quote->status);
    }

    /**
     * Canvassing is the point of the stage: several vendors quote the same
     * request and procurement compares them.
     */
    public function test_one_request_accepts_competing_quotes_from_several_vendors(): void
    {
        $procurementRequest = $this->request();
        $officer = $this->procurementOfficer();

        foreach ([['Jeffrey Corporation', 41500], ['Metro Med Supply', 39800]] as [$name, $price]) {
            $this->actingAs($officer)->post('/inventory/purchases/quotes', [
                'procurement_request_id' => $procurementRequest->id,
                'supplier_id' => $this->supplier($name)->id,
                'quoted_price' => $price,
            ])->assertRedirect('/inventory/purchases');
        }

        $this->assertSame(2, SupplierQuote::where('procurement_request_id', $procurementRequest->id)->count());
        $this->assertSame(39800.0, (float) SupplierQuote::orderBy('quoted_price')->value('quoted_price'));
    }

    public function test_a_quote_must_name_a_real_request_and_supplier(): void
    {
        $this->actingAs($this->procurementOfficer())
            ->post('/inventory/purchases/quotes', [
                'procurement_request_id' => 999,
                'supplier_id' => 999,
                'quoted_price' => -5,
            ])
            ->assertSessionHasErrors(['procurement_request_id', 'supplier_id', 'quoted_price']);

        $this->assertDatabaseCount('supplier_quotes', 0);
    }

    /**
     * With nothing to attach to, the form would submit into a validation error
     * every time, so the screen says what to do instead of offering it.
     */
    public function test_the_quote_form_is_withheld_until_a_request_exists(): void
    {
        $this->supplier();

        $this->actingAs($this->procurementOfficer())
            ->get('/inventory/purchases')
            ->assertStatus(200)
            ->assertSee('Create a procurement request first')
            ->assertDontSee('name="quoted_price"', false);
    }

    // --------------------------------------------------- stage 4: the approval

    public function test_the_request_list_can_approve_from_the_screen(): void
    {
        $this->request($this->supplier());

        $this->actingAs($this->procurementOfficer())
            ->get('/inventory/purchases')
            ->assertStatus(200)
            // The button is written by renderApprovalForm() into the API-fed
            // list, so what the page must carry is the renderer and the route.
            ->assertSee('renderApprovalForm', false)
            ->assertSee('/inventory/purchases/requests/${request.id}/approve', false)
            ->assertSee('Approve request', false);
    }

    public function test_approving_a_request_flips_its_status_and_records_who_signed_off(): void
    {
        $procurementRequest = $this->request($this->supplier());

        // Read it back rather than trusting the instance create() returned —
        // `pending` is a database default, so it only exists after a select.
        $this->assertSame('pending', $procurementRequest->fresh()->status);

        $this->actingAs($this->procurementOfficer())
            ->post("/inventory/purchases/requests/{$procurementRequest->id}/approve", [
                'approved_by' => 'Dr. Ramirez',
                'approval_notes' => 'Cheapest compliant quote',
            ])
            ->assertRedirect('/inventory/purchases')
            ->assertSessionHas('success');

        $procurementRequest->refresh();

        $this->assertSame('approved', $procurementRequest->status);
        $this->assertSame('Dr. Ramirez', $procurementRequest->approved_by);
        $this->assertSame('Cheapest compliant quote', $procurementRequest->approval_notes);
    }

    /**
     * approve() may also switch the request onto the vendor whose quote won,
     * which is what makes the canvass mean something.
     */
    public function test_approval_may_switch_the_request_to_the_winning_vendor(): void
    {
        $procurementRequest = $this->request($this->supplier('Jeffrey Corporation'));
        $winner = $this->supplier('Metro Med Supply');

        $this->actingAs($this->procurementOfficer())
            ->post("/inventory/purchases/requests/{$procurementRequest->id}/approve", [
                'approved_by' => 'Dr. Ramirez',
                'supplier_id' => $winner->id,
            ])
            ->assertRedirect('/inventory/purchases');

        $this->assertSame($winner->id, $procurementRequest->fresh()->supplier_id);
    }

    // ------------------------------------------------------------ who may act

    /**
     * Both stages commit hospital money, so they sit behind manage_procurement
     * like the rest of ProcurementController.
     */
    public function test_neither_stage_is_open_to_departments_without_procurement(): void
    {
        $procurementRequest = $this->request($supplier = $this->supplier());

        foreach ([
            User::factory()->pharmacyStaff()->create(),
            User::factory()->warehouseStaff()->create(),
            User::factory()->viewer()->create(),
        ] as $user) {
            $this->actingAs($user)->post('/inventory/purchases/quotes', [
                'procurement_request_id' => $procurementRequest->id,
                'supplier_id' => $supplier->id,
                'quoted_price' => 100,
            ])->assertForbidden();

            $this->actingAs($user)
                ->post("/inventory/purchases/requests/{$procurementRequest->id}/approve", [
                    'approved_by' => 'Impostor',
                ])->assertForbidden();
        }

        $this->assertDatabaseCount('supplier_quotes', 0);
        $this->assertSame('pending', $procurementRequest->fresh()->status);
    }

    // ------------------------------------------------- the stage that follows

    /**
     * The gap worth naming at the defense: nothing in the schema ties the
     * approved requisition to the purchase order that answers it. This asserts
     * the seam as it actually is, so the day it is closed this test fails and
     * says so rather than quietly passing.
     */
    public function test_the_purchase_order_it_leads_to_is_not_linked_back_to_the_request(): void
    {
        $procurementRequest = $this->request($supplier = $this->supplier());
        $officer = $this->procurementOfficer();

        $this->actingAs($officer)
            ->post("/inventory/purchases/requests/{$procurementRequest->id}/approve", ['approved_by' => 'Dr. Ramirez'])
            ->assertRedirect('/inventory/purchases');

        $this->actingAs($officer)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $procurementRequest->item_id,
            'quantity' => 1000,
            'unit_cost' => 41.50,
        ])->assertRedirect('/inventory/purchases');

        $purchaseOrder = PurchaseOrder::firstOrFail();

        // A new PO opens at pending — there is no approved state on this side.
        $this->assertSame('pending', $purchaseOrder->status);
        $this->assertSame(41500.0, (float) $purchaseOrder->total_amount);

        // And it carries no column pointing at the requisition it came from.
        $this->assertFalse(
            Schema::hasColumn('purchase_orders', 'procurement_request_id'),
            'purchase_orders gained a requisition link — wire storeOrder() to it and update this test.'
        );
    }

    // ------------------------------------------------- stage 6: the receiving

    /**
     * A pending order sits there until somebody books in the delivery. The
     * receive route existed from the start, but the Action cell in the PO table
     * read "Live API" — a placeholder, not a control — so nothing on the screen
     * could move an order off pending.
     */
    public function test_the_order_table_offers_a_receive_control(): void
    {
        $this->actingAs($this->procurementOfficer())
            ->get('/inventory/purchases')
            ->assertStatus(200)
            ->assertSee('renderReceiveForm', false)
            ->assertSee('/inventory/purchases/${order.id}/receive', false)
            ->assertDontSee('Live API');
    }

    /**
     * Receiving is the moment a purchase order becomes stock. It is not a
     * status flip — it posts a stock_in through InventoryAutomationService, so
     * the balance, the per-location level row and the movement ledger all move
     * together, with the PO recorded as the movement's reference.
     */
    public function test_receiving_an_order_posts_it_into_stock_and_stamps_the_order(): void
    {
        $item = $this->item();
        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-20260808120000',
            'supplier_id' => $this->supplier()->id,
            'item_id' => $item->id,
            'quantity' => 500,
            'unit_cost' => 41.50,
            'total_amount' => 20750,
            'status' => 'pending',
        ]);

        // Booking in a delivery is record_movements — the warehouse's job, not
        // procurement's, even though the order was procurement's to raise.
        $this->actingAs(User::factory()->inventoryManager()->create())
            ->post("/inventory/purchases/{$purchaseOrder->id}/receive")
            ->assertRedirect('/inventory/purchases')
            ->assertSessionHas('success');

        $purchaseOrder->refresh();
        $this->assertSame('received', $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->received_at);

        // The rollup and the row it is derived from, not just the rollup.
        $this->assertSame(500, $item->fresh()->quantity_on_hand);
        $this->assertSame(500, (int) ItemStockLevel::where('item_id', $item->id)
            ->where('storage_location_id', $location->id)->value('quantity'));

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 500,
            'to_location_id' => $location->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);
    }

    /**
     * Receiving twice would double the stock, so the second attempt is refused.
     * The button hides itself once an order reads `received`, but a stale tab
     * or a direct POST must not get through either.
     */
    public function test_receiving_the_same_order_twice_does_not_double_the_stock(): void
    {
        $item = $this->item();
        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-20260808120001',
            'supplier_id' => $this->supplier()->id,
            'item_id' => $item->id,
            'quantity' => 500,
            'unit_cost' => 41.50,
            'total_amount' => 20750,
            'status' => 'pending',
        ]);

        $officer = $this->procurementOfficer();

        $this->actingAs($officer)->post("/inventory/purchases/{$purchaseOrder->id}/receive");
        $this->actingAs($officer)->post("/inventory/purchases/{$purchaseOrder->id}/receive")
            ->assertRedirect('/inventory/purchases')
            // The neutral notice used to flash into nothing: the layout rendered
            // success and error only, so a double receive looked like a no-op.
            ->assertSessionHas('info');

        $this->assertSame(500, $item->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::where('item_id', $item->id)->count());
    }

    /**
     * Receiving needs somewhere to put the goods. Without a location the
     * service cannot write a level row, so the order stays pending rather than
     * being stamped received against stock that landed nowhere.
     */
    public function test_receiving_is_refused_when_no_storage_location_exists(): void
    {
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-20260808120002',
            'supplier_id' => $this->supplier()->id,
            'item_id' => $this->item()->id,
            'quantity' => 500,
            'unit_cost' => 41.50,
            'total_amount' => 20750,
            'status' => 'pending',
        ]);

        $this->actingAs($this->procurementOfficer())
            ->post("/inventory/purchases/{$purchaseOrder->id}/receive")
            ->assertSessionHasErrors('receive');

        $this->assertSame('pending', $purchaseOrder->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
