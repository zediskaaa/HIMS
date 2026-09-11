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

    public function test_inventory_workflows_are_contextual_buttons_instead_of_sidebar_links(): void
    {
        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $viewerSidebar = $this->mainNavigationFor($viewer);
        $this->assertStringContainsString('Inventory', $viewerSidebar);
        $this->assertStringNotContainsString('Store Requisitions', $viewerSidebar);
        $this->assertStringNotContainsString('Cycle Counts', $viewerSidebar);

        $this->actingAs($viewer)->get('/inventory/items')
            ->assertOk()
            ->assertDontSee('Store Requisitions')
            ->assertDontSee('Cycle Counts');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();
        $warehouseSidebar = $this->mainNavigationFor($warehouse);
        $this->assertStringNotContainsString('Store Requisitions', $warehouseSidebar);
        $this->assertStringNotContainsString('Cycle Counts', $warehouseSidebar);

        $this->actingAs($warehouse)->get('/inventory/items')
            ->assertOk()
            ->assertSee('Store Requisitions')
            ->assertSee('Cycle Counts');

        $this->actingAs($warehouse)->get('/inventory/cycle-counts')
            ->assertOk()
            ->assertSee('Back to Inventory');
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
        // The left rail contains only major modules. Workflow links live on
        // their parent page and retain the same role permission checks.
        $pharmacy = User::factory()->role(UserRole::PharmacyStaff)->create();
        $sidebar = $this->mainNavigationFor($pharmacy);
        $this->assertStringContainsString('Inventory', $sidebar);
        $this->assertStringContainsString('Procurement &amp; Sourcing', $sidebar);
        $this->assertStringContainsString('Documents &amp; Logistics', $sidebar);
        $this->assertStringNotContainsString('Smart Warehousing', $sidebar);
        $this->assertStringNotContainsString('Store Requisitions', $sidebar);
        $this->assertStringNotContainsString('Transfers', $sidebar);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $warehouse = User::factory()->role(UserRole::WarehouseStaff)->create();
        $sidebar = $this->mainNavigationFor($warehouse);
        $this->assertStringContainsString('Smart Warehousing', $sidebar);
        $this->assertStringNotContainsString('Dock Receiving', $sidebar);
        $this->assertStringNotContainsString('QC Inspection Queue', $sidebar);
        $this->assertStringNotContainsString('Warehouse Tasks', $sidebar);

        $this->actingAs($warehouse)->get('/inventory/warehousing')
            ->assertOk()
            ->assertSee('Dock Receiving')
            ->assertSee('QC Inspection')
            ->assertSee('Warehouse Tasks');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $manager = User::factory()->role(UserRole::InventoryManager)->create();
        $sidebar = $this->mainNavigationFor($manager);
        $this->assertStringContainsString('Smart Warehousing', $sidebar);
        $this->assertStringContainsString('Procurement &amp; Sourcing', $sidebar);
        $this->assertStringContainsString('Process Reviews', $sidebar);
        $this->assertStringNotContainsString('Suppliers', $sidebar);
        $this->assertStringNotContainsString('Demand Forecast', $sidebar);

        $this->actingAs($manager)->get('/inventory/purchases')
            ->assertOk()
            ->assertSee('Suppliers')
            ->assertSee('Demand Forecasts');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $auditor = User::factory()->role(UserRole::Auditor)->create();
        $sidebar = $this->mainNavigationFor($auditor);
        $this->assertStringContainsString('Smart Warehousing', $sidebar);
        $this->assertStringContainsString('Process Reviews', $sidebar);
        $this->assertStringContainsString('Audit Trail', $sidebar);
        $this->assertStringNotContainsString('Warehouse Tasks', $sidebar);
        $this->assertStringNotContainsString('Suppliers', $sidebar);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $sidebar = $this->mainNavigationFor($viewer);
        $this->assertStringContainsString('Inventory', $sidebar);
        $this->assertStringContainsString('Process Reviews', $sidebar);
        $this->assertStringNotContainsString('Smart Warehousing', $sidebar);
        $this->assertStringNotContainsString('Suppliers', $sidebar);
        $this->assertStringNotContainsString('Store Requisitions', $sidebar);
        $this->assertStringNotContainsString('Adjustments', $sidebar);
    }

    public function test_secondary_pages_link_back_to_their_major_module(): void
    {
        $manager = User::factory()->role(UserRole::InventoryManager)->create();

        $this->actingAs($manager)->get('/inventory/stock-movements')
            ->assertOk()
            ->assertSee('Back to Inventory');

        $this->actingAs($manager)->get('/inventory/suppliers')
            ->assertOk()
            ->assertSee('Back to Procurement');

        $this->actingAs($manager)->get('/inventory/demand-forecast')
            ->assertOk()
            ->assertSee('Back to Procurement');

        $this->actingAs($manager)->get('/inventory/warehousing/scan-station')
            ->assertOk()
            ->assertSee('Back to Smart Warehousing');

        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->get('/admin/users')
            ->assertOk()
            ->assertSee('Access Control');

        $this->actingAs($administrator)->get('/admin/permissions')
            ->assertOk()
            ->assertSee('Back to User Management');
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

    private function mainNavigationFor(User $user): string
    {
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertMatchesRegularExpression('/<nav[^>]+aria-label="Main navigation"[^>]*>/i', $html);
        preg_match('/<nav[^>]+aria-label="Main navigation"[^>]*>(.*?)<\/nav>/is', $html, $matches);

        return $matches[1] ?? '';
    }
}
