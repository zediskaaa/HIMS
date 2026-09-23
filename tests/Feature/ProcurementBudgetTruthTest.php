<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Procurement\BudgetEncumbranceService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementBudgetTruthTest extends TestCase
{
    use RefreshDatabase;

    private function costCenter(string $code): CostCenter
    {
        return CostCenter::create([
            'name' => "Cost Center {$code}",
            'code' => $code,
            'department' => 'Inventory',
            'is_active' => true,
        ]);
    }

    public function test_purchase_request_form_distinguishes_missing_budget_from_real_available_balance(): void
    {
        $withoutBudget = $this->costCenter('CC-NO-BUDGET');
        $withBudget = $this->costCenter('CC-FUNDED');

        CostCenterBudget::create([
            'cost_center_id' => $withBudget->id,
            'fiscal_year' => (int) now()->format('Y'),
            'allocated_budget' => 2000,
            'soft_encumbered' => 250,
            'hard_encumbered' => 500,
            'spent_amount' => 0,
            'currency' => 'PHP',
        ]);

        $this->actingAs(User::factory()->inventoryManager()->create())
            ->get(route('inventory.purchases'))
            ->assertOk()
            ->assertSeeText('No budget configured')
            ->assertSeeText('Avail: ₱1,250.00')
            ->assertDontSee('Avail: ₱1,000,000.00');

        $this->assertNull($withoutBudget->currentBudget());
        $this->assertDatabaseCount('cost_center_budgets', 1);
    }

    public function test_purchase_request_without_a_current_budget_is_refused_without_creating_one(): void
    {
        $costCenter = $this->costCenter('CC-NO-BUDGET');
        $item = InventoryItem::create([
            'name' => 'Sterile Test Kit',
            'sku' => 'KIT-STERILE',
            'unit' => 'kit',
            'unit_cost' => 10,
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->inventoryManager()->create())
            ->post(route('inventory.purchases.enterprise-requests.store'), [
                'title' => 'Sterile Test Kits',
                'cost_center_id' => $costCenter->id,
                'priority' => 'medium',
                'item_id' => $item->id,
                'quantity' => 5,
                'estimated_unit_price' => 10,
            ])
            ->assertRedirect(route('inventory.purchases'))
            ->assertSessionHasErrors('budget');

        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('cost_center_budgets', 0);
    }

    public function test_soft_commitment_cannot_create_a_budget_when_it_is_missing(): void
    {
        $costCenter = $this->costCenter('CC-NO-BUDGET');
        $requester = User::factory()->inventoryManager()->create();
        $request = PurchaseRequest::create([
            'pr_number' => 'PR-NO-BUDGET',
            'title' => 'Test Purchase Request',
            'requester_id' => $requester->id,
            'cost_center_id' => $costCenter->id,
            'total_estimated_amount' => 50,
            'currency' => 'PHP',
            'priority' => 'medium',
            'status' => 'pending_approval',
            'submitted_at' => now(),
        ]);

        try {
            app(BudgetEncumbranceService::class)->reserveSoftCommitment($request);
            $this->fail('A purchase request must not reserve against an unconfigured budget.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('No budget configured', $exception->getMessage());
        }

        $this->assertDatabaseCount('cost_center_budgets', 0);
    }

    public function test_purchase_order_conversion_cannot_create_or_infer_a_budget(): void
    {
        $costCenter = $this->costCenter('CC-NO-BUDGET');
        $item = InventoryItem::create([
            'name' => 'Sterile Test Kit',
            'sku' => 'KIT-STERILE',
            'unit_cost' => 10,
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Test Medical Supplier', 'status' => 'active']);
        $order = PurchaseOrder::create([
            'po_number' => 'PO-NO-BUDGET',
            'supplier_id' => $supplier->id,
            'cost_center_id' => $costCenter->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_cost' => 10,
            'total_amount' => 50,
            'currency' => 'PHP',
            'status' => 'pending_approval',
            'requested_at' => now(),
        ]);

        try {
            app(BudgetEncumbranceService::class)->convertSoftToHardEncumbrance($order);
            $this->fail('A purchase order must not create an unconfigured budget.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('No budget configured', $exception->getMessage());
        }

        $this->assertDatabaseCount('cost_center_budgets', 0);
        $this->assertSame(0.0, (float) $order->fresh()->total_encumbered_amount);
    }

    public function test_direct_purchase_order_does_not_release_unrelated_soft_commitments(): void
    {
        $costCenter = $this->costCenter('CC-DIRECT-PO');
        $budget = CostCenterBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'allocated_budget' => 500,
            'soft_encumbered' => 100,
            'hard_encumbered' => 50,
            'spent_amount' => 25,
            'currency' => 'PHP',
        ]);
        $order = $this->purchaseOrder($costCenter, 200);

        app(BudgetEncumbranceService::class)->convertSoftToHardEncumbrance($order);

        $budget->refresh();
        $this->assertSame(100.0, (float) $budget->soft_encumbered);
        $this->assertSame(250.0, (float) $budget->hard_encumbered);
        $this->assertSame(125.0, $budget->availableBudget());
        $this->assertSame($costCenter->id, $order->fresh()->cost_center_id);
    }

    public function test_direct_purchase_order_exceeding_real_available_budget_is_refused(): void
    {
        $costCenter = $this->costCenter('CC-LIMITED');
        $budget = CostCenterBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'allocated_budget' => 200,
            'soft_encumbered' => 100,
            'hard_encumbered' => 0,
            'spent_amount' => 0,
            'currency' => 'PHP',
        ]);
        $order = $this->purchaseOrder($costCenter, 120);

        try {
            app(BudgetEncumbranceService::class)->convertSoftToHardEncumbrance($order);
            $this->fail('A purchase order must not exceed the real available budget.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Insufficient budget', $exception->getMessage());
        }

        $budget->refresh();
        $this->assertSame(100.0, (float) $budget->soft_encumbered);
        $this->assertSame(0.0, (float) $budget->hard_encumbered);
        $this->assertSame(0.0, (float) $order->fresh()->total_encumbered_amount);
    }

    public function test_linked_purchase_request_commitment_converts_to_hard_encumbrance(): void
    {
        $costCenter = $this->costCenter('CC-LINKED-PR');
        $budget = CostCenterBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => (int) now()->format('Y'),
            'allocated_budget' => 500,
            'soft_encumbered' => 100,
            'hard_encumbered' => 50,
            'spent_amount' => 0,
            'currency' => 'PHP',
        ]);
        $requester = User::factory()->inventoryManager()->create();
        $request = PurchaseRequest::create([
            'pr_number' => 'PR-LINKED-BUDGET',
            'title' => 'Linked Request',
            'requester_id' => $requester->id,
            'cost_center_id' => $costCenter->id,
            'total_estimated_amount' => 100,
            'currency' => 'PHP',
            'priority' => 'medium',
            'status' => 'approved',
            'submitted_at' => now(),
        ]);
        $order = $this->purchaseOrder($costCenter, 125, $request);

        app(BudgetEncumbranceService::class)->convertSoftToHardEncumbrance($order);

        $budget->refresh();
        $this->assertSame(0.0, (float) $budget->soft_encumbered);
        $this->assertSame(175.0, (float) $budget->hard_encumbered);
        $this->assertSame(325.0, $budget->availableBudget());
        $this->assertSame(125.0, (float) $order->fresh()->total_encumbered_amount);
    }

    private function purchaseOrder(CostCenter $costCenter, float $amount, ?PurchaseRequest $request = null): PurchaseOrder
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Test Kit',
            'sku' => 'KIT-STERILE',
            'unit_cost' => 10,
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Test Medical Supplier', 'status' => 'active']);

        return PurchaseOrder::create([
            'po_number' => 'PO-BUDGET-TEST',
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $request?->id,
            'cost_center_id' => $costCenter->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_cost' => $amount,
            'total_amount' => $amount,
            'currency' => 'PHP',
            'status' => 'pending_approval',
            'requested_at' => now(),
        ]);
    }
}
