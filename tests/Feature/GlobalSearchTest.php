<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\CycleCountDoc;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryItem;
use App\Models\LogisticsDocument;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_global_search_endpoint(): void
    {
        $response = $this->getJson(route('global-search', ['query' => 'gloves']));

        $response->assertUnauthorized();
    }

    public function test_search_returns_empty_when_query_is_under_two_characters(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        $response = $this->actingAs($user)->getJson(route('global-search', ['query' => 'a']));

        $response->assertOk()
            ->assertJson([
                'query' => 'a',
                'total_results' => 0,
                'categories' => [],
            ]);
    }

    public function test_inventory_manager_can_search_items_by_name_sku_and_barcode(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        $item = InventoryItem::create([
            'name' => 'Surgical Nitrile Gloves Extra Large',
            'sku' => 'NIT-GLV-XL-099',
            'barcode_value' => '4800123456789',
            'quantity_on_hand' => 120,
            'reorder_level' => 20,
            'unit_cost' => 15.50,
            'total_value' => 1860.00,
        ]);

        // Search by Name
        $responseName = $this->actingAs($user)->getJson(route('global-search', ['query' => 'Nitrile']));
        $responseName->assertOk()
            ->assertJsonPath('total_results', 1)
            ->assertJsonPath('categories.0.key', 'inventory_items')
            ->assertJsonPath('categories.0.items.0.title', 'Surgical Nitrile Gloves Extra Large')
            ->assertJsonPath('categories.0.items.0.badge', 'In Stock');

        // Search by SKU
        $responseSku = $this->actingAs($user)->getJson(route('global-search', ['query' => 'GLV-XL']));
        $responseSku->assertOk()
            ->assertJsonPath('categories.0.items.0.title', 'Surgical Nitrile Gloves Extra Large');

        // Search by Barcode
        $responseBarcode = $this->actingAs($user)->getJson(route('global-search', ['query' => '48001234']));
        $responseBarcode->assertOk()
            ->assertJsonPath('categories.0.items.0.title', 'Surgical Nitrile Gloves Extra Large');
    }

    public function test_search_filters_results_by_role_permissions_non_admin_cannot_see_users(): void
    {
        $viewer = User::factory()->role(UserRole::Viewer)->create();
        $admin = User::factory()->role(UserRole::Administrator)->create();

        $targetUser = User::factory()->create([
            'first_name' => 'Fernando',
            'surname' => 'Santos',
            'employee_id' => 'EMP-SANTO-999',
            'department' => 'Cardiology',
        ]);

        // Viewer does not have manage_users, so Users category must not appear
        $viewerResponse = $this->actingAs($viewer)->getJson(route('global-search', ['query' => 'Santos']));
        $viewerResponse->assertOk();
        $viewerCategories = collect($viewerResponse->json('categories'))->pluck('key')->all();
        $this->assertNotContains('users', $viewerCategories);

        // Administrator has manage_users, so Users category must appear
        $adminResponse = $this->actingAs($admin)->getJson(route('global-search', ['query' => 'Santos']));
        $adminResponse->assertOk();
        $adminCategories = collect($adminResponse->json('categories'))->pluck('key')->all();
        $this->assertContains('users', $adminCategories);

        $adminResponse->assertJsonPath('categories.0.items.0.title', $targetUser->name)
            ->assertJsonPath('categories.0.items.0.url', route('admin.users.show', $targetUser));
    }

    public function test_search_finds_suppliers_purchase_orders_and_requisitions(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        $supplier = Supplier::create([
            'name' => 'Apex Pharma Solutions Inc.',
            'trade_name' => 'Apex Pharma',
            'contact_person' => 'Maria Clara',
            'phone' => '+63 917 555 1234',
            'email' => 'sales@apexpharma.test',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-APEX-001',
            'supplier_id' => $supplier->id,
            'total_amount' => 85000.00,
            'status' => 'approved',
        ]);

        $requisition = MaterialRequisition::create([
            'requisition_number' => 'REQ-2026-EMERG-44',
            'requesting_user_id' => $user->id,
            'department' => 'Emergency Department',
            'urgency' => 'stat',
            'justification' => 'Immediate stock for ER ward',
            'status' => 'approved',
        ]);

        // Search supplier
        $supplierRes = $this->actingAs($user)->getJson(route('global-search', ['query' => 'Apex Pharma']));
        $supplierRes->assertOk()
            ->assertJsonFragment(['title' => 'Apex Pharma Solutions Inc.']);

        // Search PO
        $poRes = $this->actingAs($user)->getJson(route('global-search', ['query' => 'APEX-001']));
        $poRes->assertOk()
            ->assertJsonFragment(['title' => 'PO #PO-2026-APEX-001']);

        // Search Requisition
        $reqRes = $this->actingAs($user)->getJson(route('global-search', ['query' => 'EMERG-44']));
        $reqRes->assertOk()
            ->assertJsonFragment(['title' => 'Requisition #REQ-2026-EMERG-44']);
    }

    public function test_search_finds_shipments_and_logistics_documents_for_authorized_users(): void
    {
        $superAdmin = User::factory()->role(UserRole::SuperAdministrator)->create();
        $viewer = User::factory()->role(UserRole::Viewer)->create();

        $supplier = Supplier::create([
            'name' => 'Global Logistics Medical Inc.',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-GLM-002',
            'supplier_id' => $supplier->id,
            'total_amount' => 12000.00,
            'status' => 'approved',
        ]);

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-2026-GLM-901',
            'tracking_number' => 'TRACK-99887766',
            'carrier_name' => 'DHL Express Philippines',
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'status' => 'in_transit',
        ]);

        $document = LogisticsDocument::create([
            'tracking_number' => 'DOC-2026-WAYBILL-55',
            'reference_number' => 'REF-WB-5544',
            'document_type' => \App\Enums\DocumentType::Waybill,
            'uploaded_by_id' => $superAdmin->id,
            'title' => 'Carrier Waybill & Customs Clearance',
            'status' => 'verified',
        ]);

        // Super Admin has logistics sensitive data permission
        $res = $this->actingAs($superAdmin)->getJson(route('global-search', ['query' => '99887766']));
        $res->assertOk()
            ->assertJsonFragment(['title' => 'Shipment #SHP-2026-GLM-901']);

        $docRes = $this->actingAs($superAdmin)->getJson(route('global-search', ['query' => 'WAYBILL-55']));
        $docRes->assertOk()
            ->assertJsonFragment(['title' => 'Carrier Waybill & Customs Clearance']);

        // Viewer does not have logistics sensitive data permission
        $viewerDocRes = $this->actingAs($viewer)->getJson(route('global-search', ['query' => 'WAYBILL-55']));
        $viewerDocRes->assertOk();
        $viewerCategories = collect($viewerDocRes->json('categories'))->pluck('key')->all();
        $this->assertNotContains('documents', $viewerCategories);
    }

    public function test_topbar_renders_global_search_component_and_endpoint(): void
    {
        $user = User::factory()->role(UserRole::WarehouseStaff)->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('himsGlobalSearch', false)
            ->assertSee('global-search', false)
            ->assertSee('Search items, SKU, barcode...', false)
            ->assertSee('id="global-search-dropdown"', false);
    }

    public function test_search_finds_goods_receipts_transfers_and_locations(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        $supplier = Supplier::create([
            'name' => 'MediSupply Logistics Corp.',
            'status' => 'active',
        ]);

        $loc1 = \App\Models\StorageLocation::create([
            'code' => 'ZONE-A-SHELF-01',
            'name' => 'Main Warehouse Shelf 01',
            'type' => 'shelf',
            'zone' => 'Zone A',
            'status' => 'active',
        ]);

        $loc2 = \App\Models\StorageLocation::create([
            'code' => 'ZONE-B-RACK-05',
            'name' => 'Pharmacy Bulk Rack 05',
            'type' => 'rack',
            'zone' => 'Zone B',
            'status' => 'active',
        ]);

        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-2026-MEDISUP-101',
            'supplier_id' => $supplier->id,
            'dr_number' => 'DR-998811',
            'sales_invoice_number' => 'SI-445566',
            'status' => 'received',
            'received_by_id' => $user->id,
            'received_at' => now(),
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-2026-INT-88',
            'source_location_id' => $loc1->id,
            'destination_location_id' => $loc2->id,
            'status' => 'in_transit',
            'dispatched_by_id' => $user->id,
            'dispatched_at' => now(),
        ]);

        // Search GRN
        $grnRes = $this->actingAs($user)->getJson(route('global-search', ['query' => 'MEDISUP-101']));
        $grnRes->assertOk()
            ->assertJsonFragment(['title' => 'GRN #GRN-2026-MEDISUP-101']);

        // Search Location
        $locRes = $this->actingAs($user)->getJson(route('global-search', ['query' => 'SHELF-01']));
        $locRes->assertOk()
            ->assertJsonFragment(['title' => 'ZONE-A-SHELF-01 - Main Warehouse Shelf 01']);

        // Search Transfer
        $trfRes = $this->actingAs($user)->getJson(route('global-search', ['query' => 'INT-88']));
        $trfRes->assertOk()
            ->assertJsonFragment(['title' => 'Transfer #TRF-2026-INT-88']);
    }

    public function test_search_finds_cycle_counts_and_warehouse_tasks_for_authorized_staff(): void
    {
        $staff = User::factory()->role(UserRole::WarehouseStaff)->create();

        $loc = \App\Models\StorageLocation::create([
            'code' => 'COLD-VAULT-01',
            'name' => 'Cold Storage Vault 01',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Rabies Vaccine 2.5 IU Vial',
            'sku' => 'VAC-RAB-001',
            'quantity_on_hand' => 45,
            'reorder_level' => 10,
            'unit_cost' => 1200,
            'total_value' => 54000,
        ]);

        $count = CycleCountDoc::create([
            'document_number' => 'CC-2026-COLD-VAULT',
            'storage_location_id' => $loc->id,
            'status' => 'in_progress',
            'assigned_counter_id' => $staff->id,
            'scheduled_date' => now(),
            'snapshot_timestamp' => now(),
        ]);

        $task = \App\Models\WarehouseTask::create([
            'task_number' => 'TSK-2026-REPLEN-007',
            'task_type' => 'replenishment',
            'inventory_item_id' => $item->id,
            'source_location_id' => $loc->id,
            'status' => \App\Enums\WarehouseTaskStatus::Ready,
            'created_by_user_id' => $staff->id,
        ]);

        $countRes = $this->actingAs($staff)->getJson(route('global-search', ['query' => 'COLD-VAULT']));
        $countRes->assertOk()
            ->assertJsonFragment(['title' => 'Cycle Count #CC-2026-COLD-VAULT']);

        $taskRes = $this->actingAs($staff)->getJson(route('global-search', ['query' => 'REPLEN-007']));
        $taskRes->assertOk()
            ->assertJsonFragment(['title' => 'Task #TSK-2026-REPLEN-007']);
    }

    public function test_search_normalizes_whitespace_and_handles_case_insensitivity(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablet',
            'sku' => 'MED-PCM-500',
            'quantity_on_hand' => 500,
            'reorder_level' => 50,
            'unit_cost' => 2.50,
            'total_value' => 1250,
        ]);

        // Lowercase with extra whitespace
        $res = $this->actingAs($user)->getJson(route('global-search', ['query' => '   paracetamol   ']));
        $res->assertOk()
            ->assertJsonFragment(['title' => 'Paracetamol 500mg Tablet']);

        // Uppercase SKU with whitespace
        $resSku = $this->actingAs($user)->getJson(route('global-search', ['query' => '  med-pcm-500  ']));
        $resSku->assertOk()
            ->assertJsonFragment(['title' => 'Paracetamol 500mg Tablet']);
    }

    public function test_search_finds_item_via_barcode_alias(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        $item = InventoryItem::create([
            'name' => 'Ceftriaxone 1g Powder for Injection',
            'sku' => 'ANT-CEF-001',
            'barcode_value' => '7890123456789',
            'quantity_on_hand' => 100,
            'reorder_level' => 10,
            'unit_cost' => 85,
            'total_value' => 8500,
        ]);

        \App\Models\BarcodeAlias::create([
            'code' => 'ALT-BARCODE-998811',
            'symbology' => 'code128',
            'target_type' => 'inventory_item',
            'target_id' => $item->id,
            'is_active' => true,
        ]);

        $res = $this->actingAs($user)->getJson(route('global-search', ['query' => 'ALT-BARCODE-998811']));
        $res->assertOk()
            ->assertJsonFragment(['title' => 'Ceftriaxone 1g Powder for Injection']);
    }

    public function test_user_search_matches_names_accurately_without_false_positive_email_substrings(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();

        $matchingUser = User::factory()->create([
            'first_name' => 'Jayson',
            'surname' => 'Pinggoy',
            'name' => 'Jayson Pinggoy',
            'email' => 'jayson.pinggoy.test@example.com',
            'employee_id' => 'EMP-JAY-101',
        ]);

        $unrelatedUserWithSimilarEmail = User::factory()->create([
            'first_name' => 'Jeffrey',
            'surname' => 'Arbolante',
            'name' => 'Jeffrey Arbolante',
            'email' => 'jayson.shared@example.com',
            'employee_id' => 'EMP-JEFF-202',
        ]);

        $res = $this->actingAs($admin)->getJson(route('global-search', ['query' => 'Jay']));
        $res->assertOk();

        $userCategory = collect($res->json('categories'))->firstWhere('key', 'users');
        $this->assertNotNull($userCategory);

        $returnedTitles = collect($userCategory['items'])->pluck('title')->all();
        $this->assertContains('Jayson Pinggoy', $returnedTitles);
        $this->assertNotContains('Jeffrey Arbolante', $returnedTitles);
    }
}

