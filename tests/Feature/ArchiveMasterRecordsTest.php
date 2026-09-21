<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ArchiveMasterRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    private function viewer(): User
    {
        return User::factory()->viewer()->create();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::InventoryManager]);
    }

    private function category(): ItemCategory
    {
        return ItemCategory::create([
            'name' => 'Medical Consumables',
            'code' => 'MED-CON-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    private function item(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_replace([
            'name' => 'N95 Respirator Mask',
            'sku' => 'MED-N95-'.fake()->unique()->numberBetween(1000, 9999),
            'barcode_value' => '480'.fake()->unique()->numerify('#########'),
            'category_id' => $this->category()->id,
            'unit' => 'box',
            'quantity_on_hand' => 150,
            'reorder_level' => 30,
            'status' => 'active',
        ], $overrides));
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_replace([
            'name' => 'Apex Healthcare Solutions Inc.',
            'trade_name' => 'Apex Health',
            'business_structure' => 'corporation',
            'tax_number' => '123-456-'.fake()->unique()->numberBetween(100, 999).'-000',
            'email' => 'contact_'.fake()->unique()->numberBetween(100, 999).'@apexhealth.example',
            'status' => SupplierStatus::Active,
        ], $overrides));
    }

    // ==========================================
    // 1. Authorization and Workspace Access
    // ==========================================

    public function test_viewer_without_permission_is_forbidden_from_archive_workspace(): void
    {
        $viewer = $this->viewer();

        $response = $this->actingAs($viewer)->get(route('admin.archive.index'));
        $response->assertForbidden();
    }

    public function test_auditor_with_view_permission_can_view_archive_workspace(): void
    {
        $auditor = $this->auditor();

        $response = $this->actingAs($auditor)->get(route('admin.archive.index'));
        $response->assertOk();
        $response->assertViewIs('admin.archive.index');
    }

    public function test_auditor_cannot_perform_archive_or_unarchive_actions(): void
    {
        $auditor = $this->auditor();
        $item = $this->item();

        $response = $this->actingAs($auditor)->post(route('admin.archive.items.archive', $item));
        $response->assertForbidden();

        $response = $this->actingAs($auditor)->post(route('admin.archive.items.unarchive', $item));
        $response->assertForbidden();
    }

    public function test_administrator_with_manage_permission_can_access_archive(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.archive.index'));
        $response->assertOk();
        $response->assertViewIs('admin.archive.index');
    }

    // ==========================================
    // 2. Inventory Item Archive & Unarchive
    // ==========================================

    public function test_inventory_item_can_be_archived_with_audit_trail_and_stock_preservation(): void
    {
        $admin = $this->admin();
        $item = $this->item(['quantity_on_hand' => 250]);

        $response = $this->actingAs($admin)->post(route('admin.archive.items.archive', $item), [
            'reason' => 'Product discontinued by manufacturer, replaced by newer model.',
        ]);

        $response->assertRedirect();
        $item->refresh();

        $this->assertEquals('archived', $item->status);
        $this->assertTrue($item->isArchived());
        $this->assertNotNull($item->archived_at);
        $this->assertEquals($admin->id, $item->archived_by);
        $this->assertEquals('Product discontinued by manufacturer, replaced by newer model.', $item->archive_reason);
        // Ensure stock quantities and balance are preserved
        $this->assertEquals(250, $item->quantity_on_hand);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ArchivedInventoryItem->value,
            'user_id' => $admin->id,
            'target_type' => $item->getMorphClass(),
            'target_id' => (string) $item->id,
            'business_reason' => 'Product discontinued by manufacturer, replaced by newer model.',
        ]);
    }

    public function test_archived_inventory_item_is_excluded_from_active_catalog(): void
    {
        $admin = $this->admin();
        $activeItem = $this->item(['name' => 'Active Gauze Roll', 'sku' => 'GAUZE-ACT-001']);
        $archivedItem = $this->item([
            'name' => 'Old Archived Gauze',
            'sku' => 'GAUZE-ARC-001',
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('inventory.items'));
        $response->assertOk();
        $response->assertSee('Active Gauze Roll');
        $response->assertDontSee('Old Archived Gauze');
    }

    public function test_archived_inventory_item_is_excluded_from_global_search(): void
    {
        $admin = $this->admin();
        $activeItem = $this->item(['name' => 'Latex Examination Gloves', 'sku' => 'GLV-ACT-001']);
        $archivedItem = $this->item([
            'name' => 'Latex Surgical Gloves',
            'sku' => 'GLV-ARC-001',
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $searchService = app(GlobalSearchService::class);
        $results = $searchService->search($admin, 'Latex');

        $foundItemTitles = collect($results['categories'])
            ->where('key', 'inventory_items')
            ->flatMap(fn ($cat) => collect($cat['items'])->pluck('title'))
            ->all();

        $this->assertContains('Latex Examination Gloves', $foundItemTitles);
        $this->assertNotContains('Latex Surgical Gloves', $foundItemTitles);
    }

    public function test_archived_inventory_item_cannot_be_selected_for_purchase_orders(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();
        $costCenter = CostCenter::create(['name' => 'General Surgery', 'code' => 'SURG-01', 'department' => 'Surgery', 'is_active' => true]);
        $archivedItem = $this->item(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $admin->id]);

        $manager = User::factory()->inventoryManager()->create();
        $response = $this->actingAs($manager)->post(route('inventory.purchases.orders.store'), [
            'supplier_id' => $supplier->id,
            'item_id' => $archivedItem->id,
            'cost_center_id' => $costCenter->id,
            'quantity' => 10,
        ]);

        $response->assertSessionHasErrors('item_id');
    }

    public function test_archived_inventory_item_can_be_unarchived(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'status' => 'archived',
            'archived_at' => now()->subDays(5),
            'archived_by' => $admin->id,
            'archive_reason' => 'Temporary decommissioning',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.archive.items.unarchive', $item));
        $response->assertRedirect();
        $item->refresh();

        $this->assertEquals('active', $item->status);
        $this->assertFalse($item->isArchived());
        $this->assertNull($item->archived_at);
        $this->assertNull($item->archived_by);
        $this->assertNull($item->archive_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UnarchivedInventoryItem->value,
            'user_id' => $admin->id,
            'target_type' => $item->getMorphClass(),
            'target_id' => (string) $item->id,
        ]);
    }

    public function test_unarchiving_already_active_item_is_rejected(): void
    {
        $admin = $this->admin();
        $activeItem = $this->item(['status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.archive.items.unarchive', $activeItem));
        $response->assertSessionHasErrors('archive');
    }

    // ==========================================
    // 3. Supplier Archive & Unarchive
    // ==========================================

    public function test_supplier_can_be_archived_with_audit_trail_and_procurement_revocation(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();

        $response = $this->actingAs($admin)->post(route('admin.archive.suppliers.archive', $supplier), [
            'reason' => 'Vendor operations closed down permanently.',
        ]);

        $response->assertRedirect();
        $supplier->refresh();

        $this->assertEquals(SupplierStatus::Archived, $supplier->status);
        $this->assertTrue($supplier->isArchived());
        $this->assertFalse($supplier->isProcurementEligible());
        $this->assertEquals('Vendor operations closed down permanently.', $supplier->archive_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ArchivedSupplier->value,
            'user_id' => $admin->id,
            'target_type' => $supplier->getMorphClass(),
            'target_id' => (string) $supplier->id,
            'business_reason' => 'Vendor operations closed down permanently.',
        ]);
    }

    public function test_archived_supplier_is_excluded_from_active_suppliers_index(): void
    {
        $admin = $this->admin();
        $activeSupplier = $this->supplier(['name' => 'Active Supplier Corp']);
        $archivedSupplier = $this->supplier([
            'name' => 'Archived Supplier Corp',
            'status' => SupplierStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('inventory.suppliers'));
        $response->assertOk();
        $response->assertSee('Active Supplier Corp');
        $response->assertDontSee('Archived Supplier Corp');
    }

    public function test_archived_supplier_can_be_unarchived(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier([
            'status' => SupplierStatus::Archived,
            'archived_at' => now()->subWeek(),
            'archived_by' => $admin->id,
            'archive_reason' => 'Contract dispute resolved',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.archive.suppliers.unarchive', $supplier));
        $response->assertRedirect();
        $supplier->refresh();

        $this->assertEquals(SupplierStatus::Active, $supplier->status);
        $this->assertFalse($supplier->isArchived());
        $this->assertNull($supplier->archived_at);
        $this->assertNull($supplier->archived_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UnarchivedSupplier->value,
            'user_id' => $admin->id,
            'target_type' => $supplier->getMorphClass(),
            'target_id' => (string) $supplier->id,
        ]);
    }

    public function test_unarchiving_supplier_detects_tax_number_collision(): void
    {
        $admin = $this->admin();
        $archivedSupplier = $this->supplier([
            'tax_number' => '999-888-777-000',
            'status' => SupplierStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        // Active supplier created with matching TIN (valid since tax_number is not uniquely constrained at DB level)
        $this->supplier([
            'name' => 'New Active Supplier with same TIN',
            'tax_number' => '999-888-777-000',
            'status' => SupplierStatus::Active,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.archive.suppliers.unarchive', $archivedSupplier));
        $response->assertSessionHasErrors('unarchive');

        $archivedSupplier->refresh();
        $this->assertEquals(SupplierStatus::Archived, $archivedSupplier->status);
    }

    // ==========================================
    // 4. User Account Archive & Unarchive
    // ==========================================

    public function test_user_can_be_archived_and_deactivated_with_audit_trail(): void
    {
        $admin = $this->admin();
        $staffUser = User::factory()->viewer()->create([
            'name' => 'Maria Santos',
            'employee_id' => 'EMP-099',
            'email' => 'msantos@hospital.example',
            'remember_token' => 'some-token',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.archive.users.archive', $staffUser), [
            'reason' => 'Resigned from employment on 2026-09-15.',
        ]);

        $response->assertRedirect();
        $staffUser->refresh();

        $this->assertEquals(UserStatus::Archived, $staffUser->status);
        $this->assertTrue($staffUser->isArchived());
        $this->assertFalse($staffUser->isActive());
        $this->assertNull($staffUser->remember_token);
        $this->assertEquals('Resigned from employment on 2026-09-15.', $staffUser->archive_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ArchivedUser->value,
            'user_id' => $admin->id,
            'target_type' => $staffUser->getMorphClass(),
            'target_id' => (string) $staffUser->id,
            'business_reason' => 'Resigned from employment on 2026-09-15.',
        ]);
    }

    public function test_self_archiving_is_blocked(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.archive.users.archive', $admin));
        $response->assertSessionHasErrors('archive');

        $admin->refresh();
        $this->assertEquals(UserStatus::Active, $admin->status);
    }

    public function test_archiving_protected_super_admin_is_blocked(): void
    {
        $admin = $this->admin();
        $superAdmin = User::factory()->superAdministrator()->create([
            'email' => config('auth.protected_accounts.super_admin.email', 'superadmin@hims.local'),
            'is_protected' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.archive.users.archive', $superAdmin));
        $response->assertSessionHasErrors('archive');

        $superAdmin->refresh();
        $this->assertNotEquals(UserStatus::Archived, $superAdmin->status);
    }

    public function test_archiving_only_remaining_administrator_is_blocked(): void
    {
        Gate::define(Permission::ManageArchive->value, fn () => true);

        // Operator is a viewer who has ManageArchive permission granted for this test
        $operator = User::factory()->viewer()->create();
        // The only administrator in the database
        $singleAdmin = User::factory()->administrator()->create();

        $response = $this->actingAs($operator)->post(route('admin.archive.users.archive', $singleAdmin));
        $response->assertSessionHasErrors('archive');

        $singleAdmin->refresh();
        $this->assertEquals(UserStatus::Active, $singleAdmin->status);
    }

    public function test_archived_user_is_excluded_from_default_user_index(): void
    {
        $admin = $this->admin();
        $activeUser = User::factory()->viewer()->create(['name' => 'Active Nurse Jane']);
        $archivedUser = User::factory()->viewer()->create([
            'name' => 'Former Nurse Bob',
            'status' => UserStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertOk();
        $response->assertSee('Active Nurse Jane');
        $response->assertDontSee('Former Nurse Bob');
    }

    public function test_archived_user_can_be_unarchived(): void
    {
        $admin = $this->admin();
        $user = User::factory()->viewer()->create([
            'name' => 'Returned Nurse Carla',
            'status' => UserStatus::Archived,
            'archived_at' => now()->subMonths(2),
            'archived_by' => $admin->id,
            'archive_reason' => 'Leave of absence',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.archive.users.unarchive', $user));
        $response->assertRedirect();
        $user->refresh();

        $this->assertEquals(UserStatus::Active, $user->status);
        $this->assertFalse($user->isArchived());
        $this->assertTrue($user->isActive());
        $this->assertNull($user->archived_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UnarchivedUser->value,
            'user_id' => $admin->id,
            'target_type' => $user->getMorphClass(),
            'target_id' => (string) $user->id,
        ]);
    }

    public function test_unarchiving_already_active_user_is_rejected(): void
    {
        $admin = $this->admin();
        $activeUser = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $response = $this->actingAs($admin)->post(route('admin.archive.users.unarchive', $activeUser));
        $response->assertSessionHasErrors('archive');
    }

    // ==========================================
    // 5. Archive Index Tab & Search Filtering
    // ==========================================

    public function test_archive_index_filters_by_tabs_and_search(): void
    {
        $admin = $this->admin();

        $item = $this->item([
            'name' => 'Archived Defibrillator Pads',
            'sku' => 'DEF-001',
            'status' => 'archived',
            'archived_at' => now()->subDays(2),
            'archived_by' => $admin->id,
            'archive_reason' => 'Recalled batch series',
        ]);

        $supplier = $this->supplier([
            'name' => 'Archived BioMedics Corp',
            'status' => SupplierStatus::Archived,
            'archived_at' => now()->subDay(),
            'archived_by' => $admin->id,
            'archive_reason' => 'Licence expired',
        ]);

        $user = User::factory()->viewer()->create([
            'name' => 'Archived Staff Alan',
            'status' => UserStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
            'archive_reason' => 'Contract completed',
        ]);

        // 1. All tab: shows all three
        $response = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'all']));
        $response->assertOk();
        $response->assertSee('Archived Defibrillator Pads');
        $response->assertSee('Archived BioMedics Corp');
        $response->assertSee('Archived Staff Alan');

        // 2. Items tab: shows item only
        $response = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'items']));
        $response->assertOk();
        $response->assertSee('Archived Defibrillator Pads');
        $response->assertDontSee('Archived BioMedics Corp');
        $response->assertDontSee('Archived Staff Alan');

        // 3. Suppliers tab: shows supplier only
        $response = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'suppliers']));
        $response->assertOk();
        $response->assertDontSee('Archived Defibrillator Pads');
        $response->assertSee('Archived BioMedics Corp');
        $response->assertDontSee('Archived Staff Alan');

        // 4. Users tab: shows user only
        $response = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'users']));
        $response->assertOk();
        $response->assertDontSee('Archived Defibrillator Pads');
        $response->assertDontSee('Archived BioMedics Corp');
        $response->assertSee('Archived Staff Alan');

        // 5. Search filter on items
        $response = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'items', 'search' => 'DEF-001']));
        $response->assertOk();
        $response->assertSee('Archived Defibrillator Pads');

        $response = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'items', 'search' => 'NonExistent']));
        $response->assertOk();
        $response->assertDontSee('Archived Defibrillator Pads');
    }

    public function test_archived_user_cannot_be_activated_via_status_toggle(): void
    {
        $admin = $this->admin();
        $user = User::factory()->viewer()->create([
            'status' => UserStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user));
        $response->assertSessionHasErrors('status');

        $user->refresh();
        $this->assertTrue($user->isArchived());
    }

    public function test_archived_user_cannot_be_activated_via_user_edit_form(): void
    {
        $admin = $this->admin();
        $user = User::factory()->viewer()->create([
            'status' => UserStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'surname' => $user->surname ?? 'Doe',
            'first_name' => $user->first_name ?? 'Jane',
            'email' => $user->email,
            'role' => $user->role->value,
            'status' => 'active',
            'department' => $user->department ?? 'General Services',
            'phone' => '09123456789',
        ]);

        $response->assertSessionHasErrors('status');
        $user->refresh();
        $this->assertTrue($user->isArchived());
    }

    public function test_archived_item_cannot_be_linked_as_supplier_product(): void
    {
        $manager = $this->manager();
        $supplier = $this->supplier();
        $item = $this->item(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $manager->id]);

        $response = $this->actingAs($manager)->post(route('inventory.suppliers.products.store', $supplier), [
            'item_id' => $item->id,
            'unit' => 'box',
        ]);

        $response->assertSessionHasErrors('item_id');
    }

    public function test_archived_supplier_cannot_be_inactivated(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier([
            'status' => SupplierStatus::Archived,
            'archived_at' => now(),
            'archived_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('inventory.suppliers.inactivate', $supplier), [
            'inactivation_reason' => 'Testing inactivation',
        ]);

        $response->assertSessionHasErrors('status');
        $supplier->refresh();
        $this->assertTrue($supplier->isArchived());
    }

    public function test_stock_adjustment_cannot_be_requested_for_archived_item(): void
    {
        $manager = $this->manager();
        $item = $this->item(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $manager->id]);
        $location = \App\Models\StorageLocation::create(['name' => 'Main Pharmacy Shelf', 'code' => 'LOC-TEST-01', 'status' => 'active']);

        $this->expectException(ValidationException::class);
        app(\App\Services\Inventory\AdjustmentApprovalService::class)->requestAdjustment([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'adjustment_type' => 'increase',
            'quantity' => 10,
            'reason_code' => 'data_correction',
            'explanation' => 'Attempting adjustment on archived item',
        ], $manager);
    }

    public function test_archive_modal_renders_with_reason_input_for_authorized_users(): void
    {
        $admin = $this->admin();
        $item = $this->item(['name' => 'Amoxicillin 500mg']);
        $supplier = $this->supplier(['name' => 'BioPharma Diagnostics']);

        // 1. Inventory items index has open-archive-modal and the archive modal layout partial
        $response = $this->actingAs($admin)->get(route('inventory.items'));
        $response->assertOk();
        $response->assertSee('open-archive-modal', false);
        $response->assertSee('archive_modal_reason', false);
        $response->assertSee('Reason for Archiving', false);

        // 2. User management index has open-archive-modal and archive modal layout partial
        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertOk();
        $response->assertSee('open-archive-modal', false);
        $response->assertSee('archive_modal_reason', false);

        // 3. Supplier directory index has open-archive-modal and archive modal layout partial
        $response = $this->actingAs($admin)->get(route('inventory.suppliers'));
        $response->assertOk();
        $response->assertSee('open-archive-modal', false);
        $response->assertSee('archive_modal_reason', false);

        // 4. Supplier show page lifecycle modal contains required reason textarea
        $response = $this->actingAs($admin)->get(route('inventory.suppliers.show', $supplier));
        $response->assertOk();
        $response->assertSee('supplier_show_archive_reason', false);
        $response->assertSee('Archive Justification / Reason', false);
    }
}

