<?php

namespace Tests\Feature;

use App\Enums\SupplierAccreditationStatus;
use App\Models\AuditLog;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Procurement\BudgetEncumbranceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PurchaseOrderWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $name = 'Infusion Pump Set', string $sku = 'MED-PUMP-01', float $cost = 125.50): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'unit' => 'set',
            'quantity_on_hand' => 12,
            'reorder_level' => 20,
            'unit_cost' => $cost,
            'lead_time_days' => 7,
            'status' => 'active',
        ]);
    }

    private function supplier(string $name = 'Accredited Medical Supply'): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'status' => 'active',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'standard_lead_time_days' => 5,
            'payment_terms' => 'Net 30',
        ]);
    }

    private function costCenter(float $budgetAmount = 1000000): CostCenter
    {
        $costCenter = CostCenter::create([
            'name' => 'Clinical Supply',
            'code' => 'CC-CLINICAL',
            'department' => 'Inventory',
            'is_active' => true,
        ]);

        CostCenterBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'allocated_budget' => $budgetAmount,
            'soft_encumbered' => 0,
            'hard_encumbered' => 0,
            'spent_amount' => 0,
            'currency' => 'PHP',
        ]);

        return $costCenter;
    }

    public function test_workspace_renders_real_creation_context_pipeline_filters_review_and_details(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $supplier = $this->supplier();
        $this->costCenter();

        PurchaseOrder::create([
            'po_number' => 'PO-WORKSPACE-DETAIL',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 8,
            'unit_cost' => 125.50,
            'total_amount' => 1004,
            'status' => 'approved',
            'delivery_date' => now()->addDays(5),
            'requested_at' => now(),
        ]);

        $this->actingAs($manager)->get('/inventory/purchases')
            ->assertOk()
            ->assertSee('Procurement &amp; Purchase Orders', false)
            ->assertSee('Prepare Purchase Order')
            ->assertSee('Purchase Order Pipeline')
            ->assertSee('name="item_id"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee('name="supplier_id"', false)
            ->assertSee('On hand')
            ->assertSee('Reorder point')
            ->assertSee('90-day demand')
            ->assertSee('Suggested reorder quantity')
            ->assertSee($item->name)
            ->assertSee($supplier->name)
            ->assertSee('Review Purchase Order')
            ->assertSee('Confirm &amp; create PO', false)
            ->assertSee('id="po-search"', false)
            ->assertSee('id="po-status"', false)
            ->assertSee('id="po-date"', false)
            ->assertSee('More filters')
            ->assertSee('PO-WORKSPACE-DETAIL')
            ->assertSee('purchase-order-details')
            ->assertSee('openPurchaseOrderDetails', false)
            ->assertSee('lg:grid-cols-[minmax(19rem,0.82fr)_minmax(0,1.65fr)]', false)
            ->assertDontSee('name="unit_cost"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_supplier_catalog_price_and_minimum_order_are_enforced_server_side(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item(cost: 125.50);
        $supplier = $this->supplier();
        $costCenter = $this->costCenter();
        $product = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'minimum_order_quantity' => 5,
            'lead_time_days' => 3,
            'is_active' => true,
        ]);
        SupplierPrice::create([
            'supplier_product_id' => $product->id,
            'unit_price' => 99.75,
            'currency' => 'PHP',
            'minimum_order_quantity' => 10,
            'effective_from' => today()->subDay(),
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 5,
        ])->assertSessionHasErrors('purchase_order');
        $this->assertDatabaseCount('purchase_orders', 0);

        $this->actingAs($manager)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 10,
            'unit_cost' => 0.01,
            'status' => 'received',
        ])->assertSessionHas('success');

        $po = PurchaseOrder::firstOrFail();
        $this->assertSame(99.75, (float) $po->unit_cost);
        $this->assertSame(997.50, (float) $po->total_amount);
        $this->assertSame('pending_approval', $po->status);
        $this->assertSame(today()->addDays(3)->toDateString(), $po->delivery_date?->toDateString());
        $this->assertSame(12, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('po_line_items', [
            'purchase_order_id' => $po->id,
            'ordered_quantity' => 10,
            'unit_price' => 99.75,
        ]);
    }

    public function test_invalid_or_ineligible_order_input_creates_no_records(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $supplier = $this->supplier();

        $this->actingAs($manager)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 0,
        ])->assertSessionHasErrors(['cost_center_id', 'quantity']);

        $supplier->update(['status' => 'inactive']);
        $item->update(['status' => 'inactive']);
        $costCenter = $this->costCenter();

        $this->actingAs($manager)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 1,
        ])->assertSessionHasErrors(['supplier_id', 'item_id']);

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('po_line_items', 0);
    }

    public function test_creation_failure_rolls_back_order_line_budget_and_audit_records(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $supplier = $this->supplier();
        $costCenter = $this->costCenter();

        $this->mock(BudgetEncumbranceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('convertSoftToHardEncumbrance')
                ->once()
                ->andThrow(new RuntimeException('Simulated budget write failure'));
        });

        $this->actingAs($manager)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 4,
        ])->assertSessionHasErrors('purchase_order');

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('po_line_items', 0);
        $this->assertDatabaseCount('approval_chains', 0);
        $this->assertDatabaseCount('procurement_audit_logs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(0.0, (float) $costCenter->currentBudget()?->hard_encumbered);
    }

    public function test_search_status_date_and_empty_state_filters_use_real_purchase_orders(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $supplier = $this->supplier();

        PurchaseOrder::create([
            'po_number' => 'PO-FILTER-MATCH',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_cost' => 125.50,
            'total_amount' => 251,
            'status' => 'approved',
            'delivery_date' => today()->subDay(),
            'requested_at' => now()->subDays(2),
        ]);
        PurchaseOrder::create([
            'po_number' => 'PO-FILTER-HIDDEN',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_cost' => 125.50,
            'total_amount' => 125.50,
            'status' => 'cancelled',
            'delivery_date' => today()->addWeek(),
            'requested_at' => now()->subDays(40),
        ]);

        $this->actingAs($manager)->get('/inventory/purchases?po_search=FILTER-MATCH&po_status=approved&po_date=7')
            ->assertOk()
            ->assertSee('PO-FILTER-MATCH')
            ->assertDontSee('PO-FILTER-HIDDEN');

        $this->actingAs($manager)->get('/inventory/purchases?po_date=overdue')
            ->assertOk()
            ->assertSee('PO-FILTER-MATCH')
            ->assertDontSee('PO-FILTER-HIDDEN');

        $this->actingAs($manager)->get('/inventory/purchases?po_search=NO-SUCH-ORDER')
            ->assertOk()
            ->assertSee('No purchase orders found');
    }

    public function test_read_only_users_cannot_create_orders_or_receive_sensitive_price_context(): void
    {
        $viewer = User::factory()->viewer()->create();
        $item = $this->item(cost: 98765.43);
        $supplier = $this->supplier();
        $costCenter = $this->costCenter();

        $this->actingAs($viewer)->get('/inventory/purchases')
            ->assertOk()
            ->assertDontSee('id="direct-po-form"', false)
            ->assertDontSee('98765.43');

        $this->actingAs($viewer)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_existing_approval_actions_enforce_maker_checker_and_audit_the_decision(): void
    {
        $issuer = User::factory()->inventoryManager()->create();
        $approver = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $supplier = $this->supplier();
        $costCenter = $this->costCenter();

        $payload = [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 2,
        ];
        $this->actingAs($issuer)->post('/inventory/purchases/orders', $payload)->assertSessionHas('success');
        $po = PurchaseOrder::firstOrFail();
        $chain = $po->approvalChain;

        $this->actingAs($issuer)
            ->post(route('inventory.purchases.approval-chains.approve', $chain))
            ->assertSessionHasErrors('approval');
        $this->assertSame('pending_approval', $po->fresh()->status);

        $this->actingAs($approver)
            ->post(route('inventory.purchases.approval-chains.approve', $chain), ['decision_notes' => 'Budget and need verified.'])
            ->assertSessionHas('success');
        $this->assertSame('approved', $po->fresh()->status);
        $this->assertDatabaseHas('procurement_audit_logs', [
            'entity_name' => 'PurchaseOrder',
            'entity_id' => $po->id,
            'action_type' => 'approved_purchase_order',
        ]);
        $this->assertTrue(AuditLog::where('target_id', $po->id)
            ->where('action', 'approved_purchase_order')->exists());

        $this->actingAs($issuer)->post('/inventory/purchases/orders', $payload)->assertSessionHas('success');
        $rejectedPo = PurchaseOrder::latest('id')->firstOrFail();
        $this->actingAs($approver)
            ->post(route('inventory.purchases.approval-chains.reject', $rejectedPo->approvalChain), [
                'rejection_reason' => 'Replenishment is no longer required.',
            ])->assertSessionHas('info');

        $this->assertSame('cancelled', $rejectedPo->fresh()->status);
        $this->assertSame(0.0, (float) $rejectedPo->fresh()->total_encumbered_amount);
        $this->assertDatabaseHas('procurement_audit_logs', [
            'entity_name' => 'PurchaseOrder',
            'entity_id' => $rejectedPo->id,
            'action_type' => 'rejected_purchase_order',
        ]);
    }
}
