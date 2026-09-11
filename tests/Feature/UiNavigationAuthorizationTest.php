<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Laravel\Sanctum\Sanctum;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class UiNavigationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_navigation_icons_render_from_the_shared_icon_system(): void
    {
        foreach (['document-duplicate', 'clipboard-document-check'] as $icon) {
            $html = Blade::render('<x-ui.icon name="'.$icon.'" />');

            $this->assertStringContainsString('<svg', $html);
            $this->assertStringContainsString('<path', $html);
            $this->assertStringContainsString('aria-hidden="true"', $html);
        }
    }

    public function test_sidebar_visibility_matches_requisition_and_cycle_count_permissions(): void
    {
        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Store Requisitions')
            ->assertDontSee('Cycle Counts');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();
        $this->actingAs($warehouse)->get('/dashboard')
            ->assertOk()
            ->assertSee('Store Requisitions')
            ->assertSee('Cycle Counts');
    }

    public function test_viewer_cannot_mutate_inventory_through_the_api(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Viewer)->create(), ['*']);

        $this->getJson('/api/v1/inventory-items')->assertOk();
        $this->postJson('/api/v1/inventory-items', [
            'sku' => 'FORBIDDEN-001',
            'name' => 'Forbidden Item',
            'quantity_on_hand' => 1,
            'unit_cost' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'FORBIDDEN-001']);
    }

    public function test_cycle_count_navigation_and_direct_access_use_the_same_permission(): void
    {
        $pharmacy = User::factory()->role(UserRole::PharmacyStaff)->create();

        $this->actingAs($pharmacy)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Cycle Counts');
        $this->get('/inventory/cycle-counts')->assertForbidden();

        Sanctum::actingAs($pharmacy, ['*']);
        $this->getJson('/api/v1/inventory/cycle-counts')->assertForbidden();

        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();

        $this->actingAs($warehouse)->get('/inventory/cycle-counts')->assertOk();
        Sanctum::actingAs($warehouse, ['*']);
        $this->getJson('/api/v1/inventory/cycle-counts')->assertOk();
    }

    public function test_application_owned_views_contain_no_emoji_or_symbol_icons(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
        $violations = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (is_string($contents) && preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $contents) === 1) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertSame([], $violations, 'Emoji or symbol icons remain in: '.implode(', ', $violations));
    }

    public function test_shared_layout_contains_viewport_overflow_and_mobile_width_safeguards(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $field = file_get_contents(resource_path('views/components/ui/field.blade.php'));
        $table = file_get_contents(resource_path('views/components/ui/table.blade.php'));

        $this->assertIsString($css);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('scrollbar-width: none', $css);
        $this->assertStringContainsString('.overflow-x-auto::-webkit-scrollbar', $css);
        $this->assertStringContainsString('hims-app-shell', $layout);
        $this->assertStringContainsString('hims-app-content', $layout);
        $this->assertStringContainsString('min-w-0 max-w-full w-full', $field);
        $this->assertStringContainsString('w-full min-w-0 max-w-full touch-pan-x', $table);
    }

    public function test_sidebar_navigation_strictly_reflects_role_and_panel_boundaries(): void
    {
        // 1. Pharmacy Staff: Dispensary care, no warehousing access
        $pharmacy = User::factory()->role(UserRole::PharmacyStaff)->create();
        $this->actingAs($pharmacy)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Warehousing')
            ->assertDontSee('QC Inspection Queue')
            ->assertDontSee('Suppliers')
            ->assertDontSee('Demand Forecast')
            ->assertDontSee('Process Reviews')
            ->assertDontSee('User Management')
            ->assertDontSee('Audit Trail')
            ->assertSee('Store Requisitions')
            ->assertSee('Transfers')
            ->assertSee('Requisitions &amp; POs', false)
            ->assertSee('Documents &amp; Logistics', false);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // 2. Warehouse Staff: Storage dock operations, no supplier management or forecasting
        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();
        $this->actingAs($warehouse)->get('/dashboard')
            ->assertOk()
            ->assertSee('Warehousing')
            ->assertSee('Smart Warehousing')
            ->assertSee('Dock Receiving')
            ->assertSee('QC Inspection Queue')
            ->assertSee('Warehouse Tasks')
            ->assertDontSee('Suppliers')
            ->assertDontSee('Demand Forecast')
            ->assertDontSee('Process Reviews')
            ->assertDontSee('User Management')
            ->assertDontSee('Audit Trail');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // 3. Inventory Manager: Full storeroom, procurement, forecasting, and warehousing
        $manager = User::factory()->role(UserRole::InventoryManager)->create();
        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('Warehousing')
            ->assertSee('Suppliers')
            ->assertSee('Demand Forecast')
            ->assertSee('Process Reviews')
            ->assertDontSee('User Management')
            ->assertDontSee('Access Control');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // 4. Auditor: Read-only governance & audit logs, including warehousing oversight
        $auditor = User::factory()->role(UserRole::Auditor)->create();
        $this->actingAs($auditor)->get('/dashboard')
            ->assertOk()
            ->assertSee('Warehousing')
            ->assertSee('Smart Warehousing')
            ->assertSee('Warehouse Tasks')
            ->assertDontSee('Dock Receiving')
            ->assertDontSee('QC Inspection Queue')
            ->assertSee('Suppliers')
            ->assertSee('Process Reviews')
            ->assertSee('Audit Trail')
            ->assertDontSee('User Management')
            ->assertDontSee('Access Control');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // 5. Viewer: Pure read-only observer across inventory, procurement, and reports
        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Warehousing')
            ->assertSee('Suppliers')
            ->assertDontSee('Demand Forecast')
            ->assertSee('Process Reviews')
            ->assertDontSee('User Management')
            ->assertDontSee('Audit Trail')
            ->assertDontSee('Store Requisitions')
            ->assertDontSee('Adjustments');
    }

    public function test_procurement_workspace_tabs_dynamically_adapt_to_role_permissions(): void
    {
        // 1. Warehouse Staff only views POs for receiving; no S2P, RFQs, DOA, or Audit tabs
        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();
        $this->actingAs($warehouse)->get('/inventory/purchases')
            ->assertOk()
            ->assertDontSee('Enterprise Source-to-Pay Workspace')
            ->assertDontSee('Sourcing Events &amp; RFQs', false)
            ->assertDontSee('Delegation of Authority (DOA) Hub')
            ->assertDontSee('Procurement Audit Trail')
            ->assertSee('Purchase Orders &amp; Revisions', false);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // 2. Pharmacy Staff can enter purchase requisitions in S2P; blocked from RFQs, DOA, and Audit tabs
        $pharmacy = User::factory()->role(UserRole::PharmacyStaff)->create();
        $this->actingAs($pharmacy)->get('/inventory/purchases')
            ->assertOk()
            ->assertSee('Enterprise Source-to-Pay Workspace')
            ->assertDontSee('Sourcing Events &amp; RFQs', false)
            ->assertDontSee('Delegation of Authority (DOA) Hub')
            ->assertDontSee('Procurement Audit Trail')
            ->assertSee('Purchase Orders &amp; Revisions', false);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // 3. Inventory Manager handles S2P, RFQs, and Evaluations
        $manager = User::factory()->role(UserRole::InventoryManager)->create();
        $this->actingAs($manager)->get('/inventory/purchases')
            ->assertOk()
            ->assertSee('Enterprise Source-to-Pay Workspace')
            ->assertSee('Sourcing Events &amp; RFQs', false)
            ->assertSee('Comparative Evaluation &amp; Landed Cost Matrix', false)
            ->assertSee('Purchase Orders &amp; Revisions', false);
    }
}
