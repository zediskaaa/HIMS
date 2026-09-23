<?php

namespace Tests\Feature;

use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierContract;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\User;
use Database\Seeders\HospitalMasterDataCompletionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalMasterDataCompletionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function setupBaseDatabase(): void
    {
        // Setup admin user
        User::factory()->create([
            'id' => 1,
            'role' => 'super_administrator',
            'name' => 'HIMS Administrator',
            'email' => 'admin@hims.test',
        ]);

        // Setup categories
        $categories = [
            ['id' => 1, 'code' => 'MED', 'name' => 'Medical Supplies'],
            ['id' => 2, 'code' => 'PPE', 'name' => 'PPE'],
            ['id' => 3, 'code' => 'PHARMA', 'name' => 'Pharmaceuticals'],
            ['id' => 4, 'code' => 'MED-CONS', 'name' => 'Medical Consumables'],
            ['id' => 5, 'code' => 'DIAG-SUP', 'name' => 'Diagnostic Supplies'],
            ['id' => 30001, 'code' => 'SWS-DEMO-MED', 'name' => 'Demo Med Supplies'],
            ['id' => 30002, 'code' => 'SWS-DEMO-PHARMA', 'name' => 'Demo Pharma'],
            ['id' => 30003, 'code' => 'SWS-DEMO-SURG', 'name' => 'Demo Surgery'],
        ];
        foreach ($categories as $cat) {
            ItemCategory::create($cat + ['is_active' => true]);
        }

        // Setup locations
        $locations = [
            ['id' => 1, 'code' => 'WH-01', 'name' => 'Main Warehouse', 'type' => 'warehouse'],
            ['id' => 2, 'code' => 'WH-01-A', 'name' => 'Zone A', 'type' => 'zone'],
            ['id' => 3, 'code' => 'PHARM-01', 'name' => 'Central Pharmacy', 'type' => 'pharmacy'],
            ['id' => 30005, 'code' => 'SWS-DEMO-PICK', 'name' => 'Pick Face', 'type' => 'bin'],
            ['id' => 30008, 'code' => 'W1-Z1-A01-R01-B01', 'name' => 'Ambient Bin 1', 'type' => 'bin'],
            ['id' => 30011, 'code' => 'W1-Z2-COLD-R01-B01', 'name' => 'Cold Shelf 1', 'type' => 'bin'],
            ['id' => 30013, 'code' => 'W1-Z3-VAULT-S01-B01', 'name' => 'Narcotics Drawer 1', 'type' => 'bin'],
            ['id' => 30015, 'code' => 'W1-OR-CONS-R01-B01', 'name' => 'OR Consignment Cabinet', 'type' => 'bin'],
            ['id' => 30016, 'code' => 'COLD-01-A', 'name' => 'Biological Cold Room A', 'type' => 'room'],
            ['id' => 30017, 'code' => 'MAIN-A1-01', 'name' => 'Rack A1', 'type' => 'shelf'],
        ];
        foreach ($locations as $loc) {
            StorageLocation::forceCreate($loc + ['status' => 'active']);
        }

        // Setup initial suppliers including legacy/placeholder ones
        Supplier::forceCreate([
            'id' => 1,
            'name' => 'MedSupply Corp',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ]);
        Supplier::forceCreate([
            'id' => 2,
            'name' => 'Bayanihan Community Hospital Supply Cooperative',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
        Supplier::forceCreate([
            'id' => 30001,
            'name' => 'Apex Medical Supplies Corp',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
        Supplier::forceCreate([
            'id' => 30002,
            'name' => 'Sterling Healthcare Diagnostics Inc',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
        Supplier::forceCreate([
            'id' => 30003,
            'name' => 'BioCare Hospital Solutions Ltd',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
        Supplier::forceCreate([
            'id' => 30004,
            'name' => 'Zuellig Pharma Philippines, Inc.',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ]);
        Supplier::forceCreate([
            'id' => 30005,
            'name' => 'Metro Drug, Inc.',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ]);
        Supplier::forceCreate([
            'id' => 60001,
            'name' => 'Andres',
            'contact_person' => 'Jeffrey',
            'email' => 'jeffrey.emil@gmail.com',
            'phone' => '0912392392',
            'address' => 'San andres',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ]);

        // Setup items
        $skus = [
            'PPE-MASK-N95', 'PHARMA-PARA-500', 'PPE-GLOVE-L', 'FCAST-MASK-3PLY',
            'FCAST-GLOVE-M', 'FCAST-SYRINGE-5ML', 'FCAST-IVC-22G', 'FCAST-GAUZE-4X4',
            'FCAST-SALINE-1L', 'FCAST-CEFTRI-1G', 'FCAST-ALCOHOL-500', 'FCAST-ECG-ELECTRODE',
            'FCAST-GLUCOSE-STRIP', 'FCAST-CATHETER-16FR', 'FCAST-SUTURE-3-0', 'MED-VENT-01',
            'MED-GAUZE-ST', 'MED-TITAN-PL', 'MED-INF-SET', 'SWS-DEMO-SYRINGE-5ML',
            'DRG-MORS-002', 'DRG-RABV-003', 'DRG-DOBU-004', 'DRG-DOPA-005',
            'MED-STNT-006', 'VAC-RAB-VER05', 'ANT-MER-1G00', 'PPE-GOWN-XL',
        ];

        foreach ($skus as $index => $sku) {
            $item = InventoryItem::create([
                'id' => $index + 1,
                'sku' => $sku,
                'name' => 'Hospital Item '.$sku,
                'unit' => 'box',
                'status' => 'active',
                'quantity_on_hand' => 50,
                'unit_cost' => 150.00,
            ]);

            // create a stock level for initial item
            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => 1,
                'quantity' => 50,
                'reserved_quantity' => 0,
            ]);
        }

        // Setup PO line 3 anomaly
        $po = PurchaseOrder::forceCreate([
            'id' => 3,
            'po_number' => 'PO-TEST-0003',
            'supplier_id' => 30004,
            'status' => 'received',
            'total_amount' => 725000,
        ]);
        PurchaseOrderLine::forceCreate([
            'id' => 3,
            'purchase_order_id' => $po->id,
            'item_id' => 1,
            'line_number' => 1,
            'ordered_quantity' => 500,
            'received_quantity' => 1000,
            'unit_price' => 1450,
            'total_line_amount' => 725000,
        ]);
    }

    public function test_hospital_master_data_seeder_completes_records_and_is_idempotent(): void
    {
        $this->setupBaseDatabase();

        // 1. Run seeder
        $this->seed(HospitalMasterDataCompletionSeeder::class);

        // Verify total suppliers (8 existing updated + 4 additional = 12 total)
        $this->assertGreaterThanOrEqual(12, Supplier::count());

        // Verify zero real companies
        $this->assertDatabaseMissing('suppliers', ['name' => 'Zuellig Pharma Philippines, Inc.']);
        $this->assertDatabaseMissing('suppliers', ['name' => 'Metro Drug, Inc.']);

        // Verify zero placeholder data
        $this->assertDatabaseMissing('suppliers', ['name' => 'Andres']);
        $this->assertDatabaseMissing('suppliers', ['email' => 'jeffrey.emil@gmail.com']);

        // Verify realistic replacements exist
        $this->assertDatabaseHas('suppliers', ['name' => 'Pan-Island Pharmaceuticals Distribution Corp.']);
        $this->assertDatabaseHas('suppliers', ['name' => 'Archipelago Health Drug Distribution Inc.']);
        $this->assertDatabaseHas('suppliers', ['name' => 'St. Jude Biomedical & Surgical Systems Corp.']);
        $this->assertDatabaseHas('suppliers', ['name' => 'Luzon Lifescience Laboratories Inc.']);
        $this->assertDatabaseHas('suppliers', ['name' => 'Archipelago Renal & Dialysis Supplies Corp.']);
        $this->assertDatabaseHas('suppliers', ['name' => 'Vitalis Respiratory & Anesthesia Systems Inc.']);
        $this->assertDatabaseHas('suppliers', ['name' => 'SafeShield Hygiene & Infection Control Co.']);

        // Verify all suppliers have complete required procurement attributes
        foreach (Supplier::all() as $supplier) {
            $this->assertNotEmpty($supplier->name);
            $this->assertNotEmpty($supplier->tax_number, "Supplier {$supplier->name} is missing tax_number");
            $this->assertNotEmpty($supplier->identity_key, "Supplier {$supplier->name} is missing identity_key");
            $this->assertNotEmpty($supplier->email, "Supplier {$supplier->name} is missing email");
            $this->assertNotEmpty($supplier->phone, "Supplier {$supplier->name} is missing phone");
            $this->assertNotEmpty($supplier->address, "Supplier {$supplier->name} is missing address");
            $this->assertNotEmpty($supplier->billing_address, "Supplier {$supplier->name} is missing billing_address");
            $this->assertNotEmpty($supplier->delivery_address, "Supplier {$supplier->name} is missing delivery_address");
            $this->assertNotEmpty($supplier->payment_terms, "Supplier {$supplier->name} is missing payment_terms");
            $this->assertGreaterThan(0, $supplier->standard_lead_time_days);
            $this->assertSame(SupplierStatus::Active, $supplier->status);
            $this->assertSame(SupplierAccreditationStatus::Approved, $supplier->accreditation_status);
            $this->assertTrue($supplier->isProcurementEligible(), "Supplier {$supplier->name} is not procurement eligible");

            // Verify contact exists
            $this->assertTrue($supplier->contacts()->where('is_primary', true)->exists(), "Supplier {$supplier->name} has no primary contact");

            // Verify contract exists
            $this->assertTrue($supplier->contracts()->where('status', 'active')->exists(), "Supplier {$supplier->name} has no active contract");

            // Verify accreditation cycle exists
            $this->assertTrue($supplier->accreditations()->where('status', 'approved')->exists(), "Supplier {$supplier->name} has no approved accreditation cycle");
        }

        // Verify inventory items completion
        foreach (InventoryItem::all() as $item) {
            $this->assertNotNull($item->category_id, "Item {$item->sku} missing category");
            $this->assertNotNull($item->default_location_id, "Item {$item->sku} missing default location");
            $this->assertNotNull($item->supplier_id, "Item {$item->sku} missing supplier link");
            $this->assertNotEmpty($item->barcode_value, "Item {$item->sku} missing barcode_value");
            $this->assertNotEmpty($item->gtin, "Item {$item->sku} missing gtin");

            // Verify supplier product relationship exists
            $this->assertDatabaseHas('supplier_products', [
                'supplier_id' => $item->supplier_id,
                'item_id' => $item->id,
            ]);

            // Verify pricing exists
            $product = SupplierProduct::where('supplier_id', $item->supplier_id)->where('item_id', $item->id)->firstOrFail();
            $this->assertTrue($product->prices()->exists(), "Item {$item->sku} has no supplier price");
        }

        // Verify PO Line 3 quantity anomaly is fixed
        $poLine3 = PurchaseOrderLine::find(3);
        $this->assertSame(500, (int) $poLine3->received_quantity);

        // Verify stock cache rollup consistency
        foreach (InventoryItem::all() as $item) {
            $levelQty = (int) ItemStockLevel::where('item_id', $item->id)->sum('quantity');
            $levelReserved = (int) ItemStockLevel::where('item_id', $item->id)->sum('reserved_quantity');

            $this->assertSame($levelQty, (int) $item->quantity_on_hand);
            $this->assertSame($levelReserved, (int) $item->reserved_quantity);
        }

        // 2. Idempotency test: Run seeder second time and assert counts stay identical
        $supplierCountBefore = Supplier::count();
        $contactCountBefore = SupplierContact::count();
        $contractCountBefore = SupplierContract::count();
        $productCountBefore = SupplierProduct::count();
        $priceCountBefore = SupplierPrice::count();

        $this->seed(HospitalMasterDataCompletionSeeder::class);

        $this->assertSame($supplierCountBefore, Supplier::count());
        $this->assertSame($contactCountBefore, SupplierContact::count());
        $this->assertSame($contractCountBefore, SupplierContract::count());
        $this->assertSame($productCountBefore, SupplierProduct::count());
        $this->assertSame($priceCountBefore, SupplierPrice::count());
    }

    public function test_supplier_ui_views_render_completed_master_data(): void
    {
        $this->setupBaseDatabase();
        $this->seed(HospitalMasterDataCompletionSeeder::class);

        $admin = User::where('role', 'super_administrator')->firstOrFail();

        // 1. Directory renders all active suppliers and correct metrics
        $response = $this->actingAs($admin)->get(route('inventory.suppliers'));
        $response->assertOk();
        $response->assertSee('Supplier Vendor Analytics');
        $response->assertSee('Pan-Island Pharmaceuticals Distribution Corp.');
        $response->assertSee('MedSupply Healthcare Corporation');
        $response->assertSee('St. Jude Biomedical & Surgical Systems Corp.');
        $response->assertSee('Luzon Lifescience Laboratories Inc.');
        $response->assertDontSee('Zuellig Pharma Philippines, Inc.');
        $response->assertDontSee('Metro Drug, Inc.');
        $response->assertDontSee('Andres');

        // 2. Individual Supplier profile renders all tabs with real data
        $panIsland = Supplier::where('name', 'Pan-Island Pharmaceuticals Distribution Corp.')->firstOrFail();
        $showResponse = $this->actingAs($admin)->get(route('inventory.suppliers.show', $panIsland));
        $showResponse->assertOk();
        $showResponse->assertSee('Pan-Island Pharmaceuticals Distribution Corp.');
        $showResponse->assertSee('Roberto M. Dela Cruz');
        $showResponse->assertSee('HIMS-CNT-2026-006');
        $showResponse->assertSee('Vaccines & Temperature-Sensitive Therapeutics Agreement');
        $showResponse->assertSee('Overview');
        $showResponse->assertSee('Contacts');
        $showResponse->assertSee('Compliance');
        $showResponse->assertSee('Products & Pricing');
        $showResponse->assertSee('Contracts');
        $showResponse->assertSee('Performance');
    }
}
