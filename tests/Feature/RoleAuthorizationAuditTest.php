<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RfqStatus;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\PurchaseOrder;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_grants_are_strictly_read_only(): void
    {
        $auditor = User::factory()->role(UserRole::Auditor)->create();

        foreach (Permission::cases() as $permission) {
            if (! str_starts_with($permission->value, 'view_')) {
                $this->assertFalse($auditor->can($permission->value), "Auditor must not hold {$permission->value}");
            }
        }

        $this->assertTrue($auditor->can(Permission::ViewAuditTrail->value));
    }

    public function test_auditor_and_viewer_do_not_receive_operational_forms_on_read_pages(): void
    {
        $supplier = Supplier::create(['name' => 'Read-only Test Supplier', 'status' => 'active']);
        $auditor = User::factory()->role(UserRole::Auditor)->create();

        $this->actingAs($auditor)->get(route('inventory.logistics.shipments'))
            ->assertOk()
            ->assertDontSee('Register Inbound Shipment');

        $this->actingAs($auditor)->get(route('inventory.logistics.documents'))
            ->assertOk()
            ->assertDontSee('Upload Document');

        foreach ([$auditor, User::factory()->role(UserRole::Viewer)->create()] as $user) {
            $this->actingAs($user)->get(route('inventory.suppliers.show', $supplier))
                ->assertOk()
                ->assertDontSee('Add Contact')
                ->assertDontSee('Upload for Verification')
                ->assertDontSee('Link Product')
                ->assertDontSee('Record Contract')
                ->assertDontSee('Submit for Accreditation Review');
        }

        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $this->actingAs($viewer)->get(route('inventory.logistics'))
            ->assertOk()
            ->assertSee('Summary access only')
            ->assertDontSee('Recent Documents');
        $this->actingAs($viewer)->get(route('inventory.logistics.shipments'))->assertForbidden();
        $this->actingAs($viewer)->get(route('inventory.logistics.documents'))->assertForbidden();
    }

    public function test_auditor_direct_operational_requests_are_forbidden_and_do_not_persist(): void
    {
        $auditor = User::factory()->role(UserRole::Auditor)->create();
        $supplier = Supplier::create(['name' => 'Protected Supplier', 'status' => 'active']);
        $review = KpiProcessReview::create([
            'review_number' => 'REV-AUDIT-LOCK',
            'title' => 'Protected Review',
            'period_start' => today()->subWeek(),
            'period_end' => today(),
            'evaluator_id' => User::factory()->role(UserRole::InventoryManager)->create()->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($auditor)->post(route('inventory.logistics.shipments.store'), [
            'carrier_name' => 'Unauthorized Carrier',
        ])->assertForbidden();

        $this->actingAs($auditor)->post(route('inventory.suppliers.products.store', $supplier), [
            'item_id' => 1,
        ])->assertForbidden();

        $this->actingAs($auditor)->post(route('inventory.suppliers.prices.store', $supplier), [
            'supplier_product_id' => 1,
            'unit_price' => 1,
        ])->assertForbidden();

        $this->actingAs($auditor)->post(route('inventory.suppliers.contracts.store', $supplier), [
            'contract_number' => 'FORBIDDEN-CONTRACT',
        ])->assertForbidden();

        $this->actingAs($auditor)->post(route('reviews.approve', $review))->assertForbidden();
        $this->actingAs($auditor)->post(route('reviews.reject', $review), [
            'rejection_reason' => 'Unauthorized decision',
        ])->assertForbidden();

        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseCount('supplier_products', 0);
        $this->assertDatabaseCount('supplier_prices', 0);
        $this->assertDatabaseCount('supplier_contracts', 0);
        $this->assertSame('submitted', $review->fresh()->status);
    }

    public function test_viewer_gets_supplier_summary_without_sensitive_fields_on_web_or_api(): void
    {
        $supplier = Supplier::create([
            'name' => 'Sensitive Supplier',
            'status' => 'active',
            'email' => 'private-contact@example.test',
            'tax_number' => 'TIN-PRIVATE-123',
            'payment_terms' => 'Confidential net terms',
            'notes' => 'Internal risk note',
        ]);
        $viewer = User::factory()->role(UserRole::Viewer)->create();

        $this->actingAs($viewer)->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Sensitive Supplier')
            ->assertDontSee('private-contact@example.test')
            ->assertDontSee('TIN-PRIVATE-123')
            ->assertDontSee('Confidential net terms')
            ->assertDontSee('Internal risk note')
            ->assertDontSee('Contacts')
            ->assertDontSee('Products &amp; Pricing', false);

        Sanctum::actingAs($viewer, ['*']);
        $this->getJson("/api/v1/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.tax_number')
            ->assertJsonMissingPath('data.payment_terms')
            ->assertJsonMissingPath('data.notes');

        $auditor = User::factory()->role(UserRole::Auditor)->create();
        $this->actingAs($auditor)->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('private-contact@example.test')
            ->assertSee('TIN-PRIVATE-123')
            ->assertSee('Internal risk note');
    }

    public function test_procurement_financials_and_internal_evaluations_require_sensitive_read_access(): void
    {
        $supplier = Supplier::create(['name' => 'Commercial Supplier', 'status' => 'active']);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-SENSITIVE-001',
            'supplier_id' => $supplier->id,
            'quantity' => 10,
            'unit_cost' => 125.50,
            'total_amount' => 1255.00,
            'status' => 'issued',
            'notes' => 'Confidential commercial note',
        ]);
        $viewer = User::factory()->role(UserRole::Viewer)->create();

        $this->actingAs($viewer)->get(route('inventory.purchases'))
            ->assertOk()
            ->assertDontSee('Sourcing Events &amp; RFQs', false)
            ->assertDontSee('Comparative Evaluation &amp; Landed Cost Matrix', false)
            ->assertDontSee('Commercial Terms')
            ->assertDontSee('Total Encumbered');

        Sanctum::actingAs($viewer, ['*']);
        $this->getJson("/api/v1/purchase-orders/{$purchaseOrder->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.unit_cost')
            ->assertJsonMissingPath('data.total_amount')
            ->assertJsonMissingPath('data.notes');

        $auditor = User::factory()->role(UserRole::Auditor)->create();
        $this->actingAs($auditor)->get(route('inventory.purchases'))
            ->assertOk()
            ->assertSee('Sourcing Events &amp; RFQs', false)
            ->assertSee('Comparative Evaluation &amp; Landed Cost Matrix', false)
            ->assertSee('Commercial Terms')
            ->assertSee('Total Encumbered');
    }

    public function test_historical_stock_movements_cannot_be_updated_or_deleted_through_api(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::InventoryManager)->create(), ['*']);

        $this->putJson('/api/v1/stock-movements/999999', ['remarks' => 'rewrite history'])
            ->assertMethodNotAllowed();
        $this->deleteJson('/api/v1/stock-movements/999999')
            ->assertMethodNotAllowed();
    }

    public function test_viewer_dashboard_and_reports_omit_mutation_shortcuts_and_financial_values(): void
    {
        InventoryItem::create([
            'name' => 'Sensitive Value Item',
            'sku' => 'SENSITIVE-VALUE-001',
            'quantity_on_hand' => 7,
            'unit_cost' => 4321.09,
            'total_value' => 30247.63,
            'status' => 'in_stock',
        ]);
        $viewer = User::factory()->role(UserRole::Viewer)->create();

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Record movement')
            ->assertDontSee('New item')
            ->assertDontSee('Inventory value')
            ->assertDontSee('30,247.63');

        $this->actingAs($viewer)->get(route('inventory.reports'))
            ->assertOk()
            ->assertDontSee('Stock valuation')
            ->assertDontSee('Procurement Expense')
            ->assertDontSee('Spend by Supplier')
            ->assertDontSee('Consumption value')
            ->assertDontSee('30,247.63');

        $this->actingAs($viewer)->getJson(route('dashboard.live'))
            ->assertOk()
            ->assertJsonMissingPath('totalInventoryValue');

        Sanctum::actingAs($viewer, ['*']);
        $this->getJson('/api/v1/dashboard-summary')
            ->assertOk()
            ->assertJsonMissingPath('total_inventory_value');
        $this->getJson('/api/v1/inventory-items')
            ->assertOk()
            ->assertJsonMissingPath('data.0.unit_cost')
            ->assertJsonMissingPath('data.0.total_value');
    }

    public function test_reading_procurement_does_not_change_expired_rfq_state(): void
    {
        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-READ-ONLY-001',
            'title' => 'Expired but unswept RFQ',
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->subHour(),
            'created_by_user_id' => $viewer->id,
        ]);

        $this->actingAs($viewer)->get(route('inventory.purchases'))->assertOk();

        $this->assertSame(RfqStatus::Published, $rfq->fresh()->status);
    }

    public function test_historical_item_and_forecast_records_have_no_delete_api_route(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::SuperAdministrator)->create(), ['*']);

        $this->deleteJson('/api/v1/inventory-items/999999')->assertMethodNotAllowed();
        $this->deleteJson('/api/v1/demand-plans/999999')->assertMethodNotAllowed();
    }

    public function test_authorized_roles_keep_the_operational_controls_they_need(): void
    {
        $supplier = Supplier::create(['name' => 'Managed Supplier', 'status' => 'active']);
        $manager = User::factory()->role(UserRole::InventoryManager)->create();
        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();

        $this->actingAs($manager)->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Add Contact')
            ->assertSee('Upload for Verification')
            ->assertSee('Record Contract');

        $this->actingAs($warehouse)->get(route('inventory.logistics.shipments'))
            ->assertOk()
            ->assertSee('Register Inbound Shipment');
    }

    public function test_auditor_does_not_see_operational_smart_warehousing_buttons_or_narcotics_vault(): void
    {
        $auditor = User::factory()->role(UserRole::Auditor)->create();

        $this->actingAs($auditor)->get(route('inventory.warehousing.dashboard'))
            ->assertOk()
            ->assertDontSee('PDEA Narcotics Vault')
            ->assertDontSee('Scan Workstation')
            ->assertDontSee('Launch Scanner');

        $this->actingAs($auditor)->get(route('inventory.warehousing.scan-station'))
            ->assertForbidden();

        $this->actingAs($auditor)->get(route('inventory.transfers.index'))
            ->assertOk()
            ->assertDontSee('Dispatch to In-Transit Buffer')
            ->assertDontSee('Initiate Inter-Location Stock Transfer');

        $this->actingAs($auditor)->get(route('inventory.requisitions.index'))
            ->assertOk()
            ->assertDontSee('Submit Store Requisition')
            ->assertDontSee('Create Material Store Requisition');

        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $this->actingAs($viewer)->get(route('inventory.logistics'))
            ->assertOk()
            ->assertDontSee('IAR Processing');

        $this->actingAs($auditor)->get(route('inventory.qc.index'))
            ->assertForbidden();

        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();
        $this->actingAs($warehouse)->get(route('inventory.qc.index'))
            ->assertOk();

        $manager = User::factory()->role(UserRole::InventoryManager)->create();
        $this->actingAs($manager)->get(route('inventory.warehousing.dashboard'))
            ->assertOk()
            ->assertSee('PDEA Narcotics Vault')
            ->assertSee('Scan Workstation')
            ->assertSee('Launch Scanner');
    }
}
