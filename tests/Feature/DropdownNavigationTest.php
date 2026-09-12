<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropdownNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $inventoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdministrator()->create([
            'name' => 'Super Admin Officer',
            'employee_id' => 'EMP-0001',
        ]);

        $this->inventoryManager = User::factory()->inventoryManager()->create([
            'name' => 'Inventory Manager Officer',
            'employee_id' => 'EMP-0002',
        ]);
    }

    public function test_sidebar_renders_collapsible_dropdowns_for_major_modules(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->get(route('dashboard'));

        $response->assertOk();
        // Check major dropdown modules
        $response->assertSee('Inventory');
        $response->assertSee('Smart Warehousing');
        $response->assertSee('Procurement');
        $response->assertSee('Documents &amp; Logistics', false);
        $response->assertSee('Administration');

        // Check submodules inside dropdowns
        $response->assertSee('Inventory Items');
        $response->assertSee('Stock Levels');
        $response->assertSee('Warehouse Dashboard');
        $response->assertSee('Purchase Orders &amp; S2P', false);
        $response->assertSee('Logistics Overview');
        $response->assertSee('Recovery Center');
    }

    public function test_sidebar_coordinates_exclusive_accordion_state(): void
    {
        // When visiting an inventory page, activeDropdown should be initialized to 'inventory'
        $response = $this->actingAs($this->superAdmin)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertSee("activeDropdown: 'inventory'", false);
        $response->assertSee("activeDropdown === 'inventory'", false);
        $response->assertSee("activeDropdown === 'warehousing'", false);
        $response->assertSee("activeDropdown === 'procurement'", false);
        $response->assertSee("activeDropdown = (activeDropdown === 'warehousing' ? null : 'warehousing')", false);
    }

    public function test_inventory_items_page_renders_grouped_workflow_dropdowns(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertSee('Inventory Workflows &amp; Tools', false);
        $response->assertSee('Stock &amp; Movements', false);
        $response->assertSee('Requisitions &amp; Transfers', false);
        $response->assertSee('Audits &amp; Data Operations', false);
    }

    public function test_procurement_page_renders_major_dropdown_tabs(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('inventory.purchases'));

        $response->assertOk();
        $response->assertSee('Purchasing &amp; Orders', false);
        $response->assertSee('Strategic Sourcing', false);
        $response->assertSee('Governance &amp; Approvals', false);
    }

    public function test_logistics_page_renders_major_dropdown_tabs(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('inventory.logistics'));

        $response->assertOk();
        $response->assertSee('Operations &amp; Freight', false);
        $response->assertSee('Compliance &amp; Governance', false);
    }

    public function test_recovery_center_diagnostics_modal_handles_null_source_location_safely(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.recovery.index'));

        $response->assertOk();
        // Assert that the template safely checks technical_details.file rather than raw undefined concatenation
        $response->assertSee('selectedRecord.technical_details.file', false);
        $response->assertSee('Source Location', false);
    }
}
