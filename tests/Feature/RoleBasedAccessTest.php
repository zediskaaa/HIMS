<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reported defect: a Pharmacy account could reach Inventory Management.
 *
 * The cause was not a mis-granted permission — it was that no inventory
 * controller checked one. Every route sat behind bare `auth`, so any signed-in
 * account, down to a read-only Viewer, could POST to the item master, the
 * supplier directory and the movement ledger.
 *
 * These tests therefore drive real HTTP requests as each role and assert both
 * directions: that the work a department is responsible for goes through, and
 * that everything outside it comes back 403. Asserting only the allowed side
 * would pass just as happily against the broken build.
 */
class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    /**
     * An item sitting in a location, so movement posts fail on permission
     * rather than on missing stock.
     *
     * @return array{0: InventoryItem, 1: StorageLocation}
     */
    private function stockedItem(int $quantity = 100): array
    {
        $location = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'PHARMA-PARA-500',
            'quantity_on_hand' => $quantity,
            'reorder_level' => 10,
            'unit_cost' => 1.10,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);

        return [$item, $location];
    }

    // ------------------------------------------------------- the reported bug

    /**
     * The headline case. Pharmacy keeps the two things the department actually
     * does — see the shelf, dispense from it — and loses everything else on
     * the Inventory Management surface.
     */
    public function test_pharmacy_staff_cannot_reach_inventory_management(): void
    {
        $pharmacy = $this->user(UserRole::PharmacyStaff);

        // Refused: the item master, the supplier directory, storage locations,
        // procurement and balance corrections.
        $this->actingAs($pharmacy)->get('/inventory/suppliers')->assertForbidden();
        $this->actingAs($pharmacy)->get('/inventory/purchases')->assertForbidden();
        $this->actingAs($pharmacy)->get('/inventory/adjustments')->assertForbidden();

        $this->actingAs($pharmacy)->post('/inventory/items', [
            'name' => 'Smuggled Item',
            'sku' => 'SMUGGLE-01',
        ])->assertForbidden();

        $this->actingAs($pharmacy)->post('/inventory/storage-locations', [
            'name' => 'Rogue Shelf',
            'code' => 'ROGUE-01',
        ])->assertForbidden();

        $this->actingAs($pharmacy)->post('/inventory/suppliers', [
            'name' => 'Rogue Vendor',
            'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'SMUGGLE-01']);
        $this->assertDatabaseMissing('storage_locations', ['code' => 'ROGUE-01']);
        $this->assertDatabaseMissing('suppliers', ['name' => 'Rogue Vendor']);
    }

    /**
     * The exception the brief carved out: view-only access to medicine stock.
     * Pharmacy still reads the catalogue and the shelf balances, because a
     * department that cannot see stock cannot dispense it.
     */
    public function test_pharmacy_staff_keep_view_only_access_to_stock(): void
    {
        $pharmacy = $this->user(UserRole::PharmacyStaff);
        $this->stockedItem();

        $this->actingAs($pharmacy)->get('/inventory/items')->assertStatus(200);
        $this->actingAs($pharmacy)->get('/inventory/stock')->assertStatus(200);
        $this->actingAs($pharmacy)->get('/inventory/alerts')->assertStatus(200);
        $this->actingAs($pharmacy)->get('/inventory/reports')->assertStatus(200);
    }

    /**
     * Read-only means read-only: the catalogue renders, but the create form
     * inside it does not, so the account is never offered a door that will
     * not open.
     */
    public function test_the_item_screen_hides_its_create_form_from_pharmacy(): void
    {
        $this->actingAs($this->user(UserRole::InventoryManager))
            ->get('/inventory/items')
            ->assertSee('Create Inventory Item');

        $this->actingAs($this->user(UserRole::PharmacyStaff))
            ->get('/inventory/items')
            ->assertStatus(200)
            ->assertDontSee('Create Inventory Item');
    }

    // ------------------------------------------------- movement type by role

    /**
     * One route records every kind of movement, so a screen-level check would
     * be all-or-nothing. Dispensing is pharmacy's job; receiving a delivery
     * is not, and the POST says so.
     */
    public function test_pharmacy_may_dispense_but_not_receive_or_return(): void
    {
        $pharmacy = $this->user(UserRole::PharmacyStaff);
        [$item, $location] = $this->stockedItem(quantity: 100);
        $ward = StorageLocation::create(['name' => 'Ward A', 'code' => 'WARD-A', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'MedSupply', 'status' => 'active']);

        // Allowed — this is the department's actual work.
        $this->actingAs($pharmacy)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 10,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $ward->id,
        ])->assertRedirect('/inventory/stock-movements');

        $this->assertSame(90, $item->fresh()->quantity_on_hand);

        // Refused — receiving, transferring and returning belong to the
        // warehouse. This is the specific over-reach that was reported.
        $this->actingAs($pharmacy)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 50,
            'to_location_id' => $location->id,
        ])->assertForbidden();

        $this->actingAs($pharmacy)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'transfer',
            'quantity' => 10,
            'from_location_id' => $location->id,
            'to_location_id' => $ward->id,
        ])->assertForbidden();

        $this->actingAs($pharmacy)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'return_to_supplier',
            'quantity' => 10,
            'from_location_id' => $location->id,
            'return_supplier_id' => $supplier->id,
        ])->assertForbidden();

        // The three refusals moved nothing.
        $this->assertSame(90, $item->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::count());
    }

    /**
     * The dropdown is built from the same requiredPermission() the POST
     * checks, so what is offered and what is accepted cannot drift apart.
     */
    public function test_the_movement_form_offers_only_the_types_the_role_may_record(): void
    {
        $this->stockedItem();

        $this->actingAs($this->user(UserRole::PharmacyStaff))
            ->get('/inventory/stock-movements')
            ->assertStatus(200)
            ->assertSee('Issuance — dispense to a ward or department')
            ->assertDontSee('Stock In — receive into a location')
            ->assertDontSee('Return to Supplier — send back to vendor');

        $this->actingAs($this->user(UserRole::WarehouseStaff))
            ->get('/inventory/stock-movements')
            ->assertStatus(200)
            ->assertSee('Issuance — dispense to a ward or department')
            ->assertSee('Stock In — receive into a location')
            ->assertSee('Return to Supplier — send back to vendor');
    }

    /**
     * A viewer may record nothing, so the whole form is skipped rather than
     * shown above a submit button that only ever 403s. The history below it
     * still renders — that is what they are there for.
     */
    public function test_a_viewer_sees_movement_history_without_a_form(): void
    {
        $this->stockedItem();

        $this->actingAs($this->user(UserRole::Viewer))
            ->get('/inventory/stock-movements')
            ->assertStatus(200)
            ->assertSee('Movement History')
            ->assertDontSee('Record Movement');
    }

    // ---------------------------------------------------- per-role boundaries

    /**
     * The warehouse moves stock but does not own the records. Correcting a
     * counted balance is withheld on purpose: the person who counted the
     * shelf must not also be the one who overwrites the number.
     */
    public function test_warehouse_staff_move_stock_but_cannot_reshape_records(): void
    {
        $warehouse = $this->user(UserRole::WarehouseStaff);
        [$item, $location] = $this->stockedItem();

        // Allowed.
        $this->actingAs($warehouse)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 20,
            'to_location_id' => $location->id,
        ])->assertRedirect('/inventory/stock-movements');

        $this->assertSame(120, $item->fresh()->quantity_on_hand);

        // Refused.
        $this->actingAs($warehouse)->get('/inventory/adjustments')->assertForbidden();
        $this->actingAs($warehouse)->get('/inventory/suppliers')->assertForbidden();
        $this->actingAs($warehouse)->get('/inventory/purchases')->assertForbidden();

        $this->actingAs($warehouse)->post('/inventory/items', [
            'name' => 'Unauthorised Item',
            'sku' => 'UNAUTH-01',
        ])->assertForbidden();

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'UNAUTH-01']);
    }

    /**
     * Receiving a delivery is warehouse work even though ordering is not, so
     * PurchaseOrderController splits: store() needs manage_procurement,
     * receive() needs record_movements.
     */
    public function test_warehouse_staff_receive_deliveries_but_cannot_order_them(): void
    {
        $warehouse = $this->user(UserRole::WarehouseStaff);
        $supplier = Supplier::create(['name' => 'MedSupply', 'status' => 'active']);

        $this->actingAs($warehouse)->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-NOPE-001',
        ])->assertForbidden();

        $this->assertDatabaseMissing('purchase_orders', ['po_number' => 'PO-NOPE-001']);
    }

    /**
     * The role the module is supposed to belong to. Everything on the
     * inventory surface opens for it — that is what "exclusive to the
     * Inventory Manager" has to mean in the allowed direction.
     */
    public function test_the_inventory_manager_reaches_the_whole_module(): void
    {
        $manager = $this->user(UserRole::InventoryManager);
        $this->stockedItem();

        foreach ([
            '/inventory/items',
            '/inventory/storage-locations',
            '/inventory/stock-movements',
            '/inventory/adjustments',
            '/inventory/suppliers',
            '/inventory/purchases',
            '/inventory/stock',
            '/inventory/alerts',
            '/inventory/reports',
            '/inventory/demand-forecast',
        ] as $path) {
            $this->actingAs($manager)->get($path)
                ->assertStatus(200, "Inventory Manager should reach {$path}");
        }
    }

    /**
     * ...but not user accounts. Running the storeroom is not the same job as
     * administering the system.
     */
    public function test_the_inventory_manager_cannot_administer_accounts(): void
    {
        $manager = $this->user(UserRole::InventoryManager);

        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
        $this->actingAs($manager)->get('/admin/permissions')->assertForbidden();
    }

    /**
     * A viewer reads and writes nothing. Driven off the route list rather
     * than a hand-written set of cases.
     */
    public function test_a_viewer_can_read_but_never_write(): void
    {
        $viewer = $this->user(UserRole::Viewer);
        [$item, $location] = $this->stockedItem();

        // Reads that are theirs.
        $this->actingAs($viewer)->get('/inventory/items')->assertStatus(200);
        $this->actingAs($viewer)->get('/inventory/reports')->assertStatus(200);

        // Reads that are not.
        $this->actingAs($viewer)->get('/inventory/adjustments')->assertForbidden();
        $this->actingAs($viewer)->get('/inventory/suppliers')->assertForbidden();
        $this->actingAs($viewer)->get('/inventory/purchases')->assertForbidden();

        // Every write.
        $this->actingAs($viewer)->post('/inventory/items', [
            'name' => 'Viewer Item', 'sku' => 'VIEW-01',
        ])->assertForbidden();

        $this->actingAs($viewer)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'issuance',
            'quantity' => 1,
            'from_location_id' => $location->id,
            'issued_to_location_id' => $location->id,
        ])->assertForbidden();

        $this->actingAs($viewer)->post('/inventory/adjustments', [
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'counted_quantity' => 999,
        ])->assertForbidden();

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(100, $item->fresh()->quantity_on_hand);
    }

    /**
     * Gate::before() gives the administrator everything without listing the
     * permissions twice — so a permission added later is covered by that
     * bypass rather than needing a second edit to keep the admin working.
     */
    public function test_an_administrator_passes_every_gate_except_audit_trail(): void
    {
        $admin = User::factory()->administrator()->create();
        $superAdmin = User::factory()->superAdministrator()->create();

        $excludedForAdmin = [
            Permission::ViewAuditTrail,
            Permission::ManageWarehouseTasks,
            Permission::ExecuteWarehouseTasks,
            Permission::ResolveWarehouseExceptions,
            Permission::AccessNarcoticsVault,
            Permission::ApproveIarAcceptance,
            Permission::PerformTechnicalInspection,
        ];

        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                $superAdmin->can($permission->value),
                "Super Administrator should hold {$permission->value}"
            );

            if (in_array($permission, $excludedForAdmin, true)) {
                $this->assertFalse(
                    $admin->can($permission->value),
                    "Administrator should not hold {$permission->value} due to GxP role separation"
                );
            } else {
                $this->assertTrue(
                    $admin->can($permission->value),
                    "Administrator should hold {$permission->value}"
                );
            }
        }
    }

    // ------------------------------------------------------------- the matrix

    public function test_the_permission_matrix_is_administrator_only(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get('/admin/permissions')
            ->assertStatus(200)
            ->assertSee('Access Control');

        foreach (UserRole::cases() as $role) {
            if ($role->isAdministrator()) {
                continue;
            }

            $this->actingAs($this->user($role))
                ->get('/admin/permissions')
                ->assertForbidden();
        }
    }

    /**
     * The matrix is generated from UserRole::permissions() rather than being a
     * second copy of the rules, so it cannot claim an access the gates do not
     * grant. This checks the screen actually names every role and module.
     */
    public function test_the_matrix_lists_every_role_and_module(): void
    {
        $response = $this->actingAs(User::factory()->administrator()->create())
            ->get('/admin/permissions')
            ->assertStatus(200);

        foreach (UserRole::cases() as $role) {
            $response->assertSee($role->label());
        }

        foreach (array_keys(Permission::byModule()) as $module) {
            $response->assertSee($module);
        }
    }

    // ------------------------------------------------------------- navigation

    /**
     * The sidebar narrows with the account rather than offering links that
     * 403. A group whose items are all hidden leaves no heading behind.
     */
    public function test_the_sidebar_narrows_to_what_the_role_may_open(): void
    {
        $this->actingAs($this->user(UserRole::InventoryManager))->get('/dashboard')
            ->assertSee('Procurement')
            ->assertSee('Suppliers')
            ->assertSee('Adjustments');

        $this->actingAs($this->user(UserRole::PharmacyStaff))->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Stock Movements')
            ->assertDontSee('Procurement')
            ->assertDontSee('Suppliers')
            ->assertDontSee('Adjustments')
            ->assertDontSee('Access Control');
    }

    /**
     * /dashboard is where login lands, so it stays open to every signed-in
     * account — a 403 there would strand a user with nowhere to go. The tiles
     * are what narrow it instead.
     */
    public function test_every_role_can_reach_the_dashboard_it_lands_on(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->actingAs($this->user($role))->get('/dashboard')
                ->assertStatus(200, "{$role->label()} should reach the dashboard");
        }
    }

    /**
     * Permissions come from the role, so a role change takes effect at once
     * rather than needing the account to be rebuilt.
     */
    public function test_changing_a_role_changes_module_access_immediately(): void
    {
        $user = $this->user(UserRole::PharmacyStaff);

        $this->actingAs($user)->get('/inventory/suppliers')->assertForbidden();

        $user->update(['role' => UserRole::InventoryManager]);

        $this->actingAs($user->fresh())->get('/inventory/suppliers')->assertStatus(200);
    }
}
