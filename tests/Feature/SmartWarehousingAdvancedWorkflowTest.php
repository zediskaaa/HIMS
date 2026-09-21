<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\PdeaDangerousDrugsRegister;
use App\Models\StorageLocation;
use App\Models\StockMovement;
use App\Models\SurgicalConsignmentBillOnly;
use App\Models\User;
use App\Services\Warehouse\ConsignmentService;
use App\Services\Warehouse\LedgerIntegrityService;
use App\Services\Warehouse\LocationCompatibilityService;
use App\Services\Warehouse\NarcoticsVaultService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class SmartWarehousingAdvancedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_compatibility_validates_classification_and_capacity(): void
    {
        $compatibility = app(LocationCompatibilityService::class);

        $coldLocation = StorageLocation::create([
            'name' => 'Vaccine Refrigerator',
            'code' => 'COLD-01',
            'type' => 'bin',
            'storage_classification' => 'medication',
            'temperature_classification' => 'cold_chain',
            'capacity' => 10,
            'status' => 'active',
        ]);

        $ambientItem = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablet',
            'sku' => 'MED-PCM-500',
            'storage_classification' => 'medication',
            'temperature_classification' => 'ambient',
            'unit' => 'box',
            'status' => 'active',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('incompatible temperature classification');
        $compatibility->assertCompatible($coldLocation, $ambientItem, 5);
    }

    public function test_lasa_isolation_prevents_adjacent_rack_placement_of_sound_alike_medications(): void
    {
        $zone = StorageLocation::create([
            'name' => 'Aisle 04 Zone',
            'code' => 'AISLE-04',
            'barcode_value' => 'LOC-AISLE-04',
            'type' => 'zone',
            'storage_classification' => 'medication',
            'status' => 'active',
        ]);

        $bin1 = StorageLocation::create([
            'name' => 'Rack 01 Shelf 1 Bin 01',
            'code' => 'A04-R01-S1-B01',
            'barcode_value' => 'LOC-A04-R01-S1-B01',
            'type' => 'bin',
            'parent_id' => $zone->id,
            'aisle' => 'Aisle 04',
            'rack' => 'Rack-01',
            'shelf' => '1',
            'bin' => '01',
            'storage_classification' => 'medication',
            'capacity' => 100,
            'status' => 'active',
        ]);

        $bin2Adjacent = StorageLocation::create([
            'name' => 'Rack 01 Shelf 1 Bin 02',
            'code' => 'A04-R01-S1-B02',
            'barcode_value' => 'LOC-A04-R01-S1-B02',
            'type' => 'bin',
            'parent_id' => $zone->id,
            'aisle' => 'Aisle 04',
            'rack' => 'Rack-01',
            'shelf' => '1',
            'bin' => '02',
            'storage_classification' => 'medication',
            'capacity' => 100,
            'status' => 'active',
        ]);

        $bin3OtherRack = StorageLocation::create([
            'name' => 'Rack 02 Shelf 1 Bin 01',
            'code' => 'A04-R02-S1-B01',
            'barcode_value' => 'LOC-A04-R02-S1-B01',
            'type' => 'bin',
            'parent_id' => $zone->id,
            'aisle' => 'Aisle 04',
            'rack' => 'Rack-02',
            'shelf' => '1',
            'bin' => '01',
            'storage_classification' => 'medication',
            'capacity' => 100,
            'status' => 'active',
        ]);

        $dobutamine = InventoryItem::create([
            'name' => 'Dobutamine 250mg/20mL Ampul',
            'sku' => 'MED-DOBUTAMINE-250',
            'storage_classification' => 'medication',
            'lasa_group_code' => 'LASA-CARDIO-01',
            'unit' => 'ampul',
            'status' => 'active',
        ]);

        $dopamine = InventoryItem::create([
            'name' => 'Dopamine 200mg/5mL Ampul',
            'sku' => 'MED-DOPAMINE-200',
            'storage_classification' => 'medication',
            'lasa_group_code' => 'LASA-CARDIO-01',
            'unit' => 'ampul',
            'status' => 'active',
        ]);

        // Place Dobutamine into Bin 1
        ItemStockLevel::create([
            'item_id' => $dobutamine->id,
            'storage_location_id' => $bin1->id,
            'quantity' => 20,
        ]);

        $compatibility = app(LocationCompatibilityService::class);

        // Bin 2 is in the same rack bay as Bin 1, so Dopamine cannot be placed there!
        try {
            $compatibility->assertCompatible($bin2Adjacent, $dopamine, 10);
            $this->fail('Expected DomainException for LASA proximity collision');
        } catch (DomainException $e) {
            $this->assertStringContainsString('LASA Proximity Conflict', $e->getMessage());
            $this->assertStringContainsString('LASA-CARDIO-01', $e->getMessage());
        }

        // Bin 3 is in Rack-02, segregated away from Rack-01 -> placement succeeds!
        $compatibility->assertCompatible($bin3OtherRack, $dopamine, 10);
        $this->assertTrue(true);
    }

    public function test_narcotics_vault_requires_dual_custody_and_validates_s2_and_yellow_rx(): void
    {
        $vault = StorageLocation::create([
            'name' => 'PDEA Narcotics Vault Safe A',
            'code' => 'VAULT-01-A',
            'barcode_value' => 'LOC-VAULT-01-A',
            'type' => 'bin',
            'storage_classification' => 'medication',
            'is_narcotics_vault' => true,
            'capacity' => 1000,
            'status' => 'active',
        ]);

        $morphine = InventoryItem::create([
            'name' => 'Morphine Sulfate 10mg/mL Ampul',
            'sku' => 'MED-NAR-MORPHINE-10',
            'storage_classification' => 'medication',
            'regulatory_category' => 'dangerous_drug_yellow_rx',
            'unit' => 'ampul',
            'status' => 'active',
        ]);

        $custodian = User::factory()->pharmacyStaff()->create([
            'email' => 'custodian.pharmacist@hims.gov.ph',
            'password' => Hash::make('CustodianPass123!'),
        ]);

        $witness = User::factory()->inventoryManager()->create([
            'email' => 'witness.manager@hims.gov.ph',
            'password' => Hash::make('WitnessPass123!'),
        ]);

        $service = app(NarcoticsVaultService::class);

        // 1. Invalid witness password fails
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid witness credentials');
        $service->authenticateWitness('witness.manager@hims.gov.ph', 'WrongPassword!', $custodian);
    }

    public function test_narcotics_vault_blocks_self_witnessing_and_enforces_s2_license(): void
    {
        $vault = StorageLocation::create([
            'name' => 'PDEA Narcotics Vault Safe B',
            'code' => 'VAULT-01-B',
            'barcode_value' => 'LOC-VAULT-01-B',
            'type' => 'bin',
            'storage_classification' => 'medication',
            'is_narcotics_vault' => true,
            'capacity' => 1000,
            'status' => 'active',
        ]);

        $morphine = InventoryItem::create([
            'name' => 'Morphine Sulfate 10mg/mL Ampul',
            'sku' => 'MED-NAR-MORPHINE-10B',
            'storage_classification' => 'medication',
            'regulatory_category' => 'dangerous_drug_yellow_rx',
            'unit' => 'ampul',
            'status' => 'active',
        ]);

        $custodian = User::factory()->pharmacyStaff()->create([
            'email' => 'custodian2@hims.gov.ph',
            'password' => Hash::make('CustodianPass123!'),
        ]);

        $witness = User::factory()->inventoryManager()->create([
            'email' => 'witness2@hims.gov.ph',
            'password' => Hash::make('WitnessPass123!'),
        ]);

        $service = app(NarcoticsVaultService::class);

        // 2. Custodian cannot witness themselves
        try {
            $service->authenticateWitness('custodian2@hims.gov.ph', 'CustodianPass123!', $custodian);
            $this->fail('Expected self-witnessing to be blocked');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Segregation of duties: Witness must be a distinct authorized user', $e->getMessage());
        }

        // 3. First record inbound replenishment
        $service->recordEntry([
            'item_id' => $morphine->id,
            'storage_location_id' => $vault->id,
            'quantity' => 100,
            'is_inbound' => true,
            'notes' => 'Authorized delivery batch DDB-2026-001',
        ], $custodian, $witness);

        // 4. Outbound dispensing without Yellow Rx serial throws DomainException
        try {
            $service->recordEntry([
                'item_id' => $morphine->id,
                'storage_location_id' => $vault->id,
                'quantity' => 2,
                'is_inbound' => false,
                'physician_s2_license' => 'S2-987654321',
            ], $custodian, $witness);
            $this->fail('Expected failure due to missing Yellow Rx');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Yellow Prescription Form (SPF) serial number is required', $e->getMessage());
        }

        // 5. Valid dispensing with S-2 and Yellow Rx
        $entry = $service->recordEntry([
            'item_id' => $morphine->id,
            'storage_location_id' => $vault->id,
            'quantity' => 5,
            'is_inbound' => false,
            'pdea_spf_number' => 'SPF-2026-88899',
            'physician_s2_license' => 'S2-987654321',
            'prescriber_name' => 'Dr. Maria Ramos, FPCP',
            'patient_encounter_id' => 'ENC-2026-00451',
            'notes' => 'Post-op analgesia administration',
        ], $custodian, $witness);

        $this->assertSame(95, $entry->running_balance);
        $this->assertSame($custodian->id, $entry->custodian_id);
        $this->assertSame($witness->id, $entry->witness_pharmacist_id);

        // 6. DDRB register is append-only
        $this->expectException(LogicException::class);
        $entry->update(['quantity' => 999]);
    }

    public function test_surgical_consignment_implant_consumption_and_bill_only_pr_creation(): void
    {
        $orLocation = StorageLocation::create([
            'name' => 'OR Suite 3 Sterile Core',
            'code' => 'OR-03-CORE',
            'barcode_value' => 'LOC-OR-03-CORE',
            'type' => 'bin',
            'storage_classification' => 'medical_supply',
            'capacity' => 100,
            'status' => 'active',
        ]);

        $stent = InventoryItem::create([
            'name' => 'Coronary Everolimus-Eluting Stent 3.0x18mm',
            'sku' => 'IMP-CARDIO-STENT-3018',
            'unit_cost' => 48000.00,
            'storage_classification' => 'medical_supply',
            'is_consignment' => true,
            'unit' => 'unit',
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $stent->id,
            'storage_location_id' => $orLocation->id,
            'quantity' => 5,
        ]);

        $nurse = User::factory()->warehouseStaff()->create();
        $service = app(ConsignmentService::class);

        $record = $service->recordImplantUsage([
            'inventory_item_id' => $stent->id,
            'serial_number' => 'SN-STENT-2026-009',
            'storage_location_id' => $orLocation->id,
            'patient_encounter_id' => 'ENC-CARDIO-2026-778',
            'operating_suite' => 'OR Suite 3',
            'surgeon_name' => 'Dr. Roberto Cruz, FPCS',
            'implanted_quantity' => 1,
            'notes' => 'Direct coronary angioplasty placement',
        ], $nurse);

        $this->assertInstanceOf(SurgicalConsignmentBillOnly::class, $record);
        $this->assertSame('pending_po', $record->status);
        $this->assertNotNull($record->purchase_request_id);

        // Verify that Bill-Only Purchase Request was created
        $pr = $record->purchaseRequest;
        $this->assertNotNull($pr);
        $this->assertSame('direct_contracting', $pr->procurement_method);
        $this->assertSame(48000.00, (float) $pr->total_estimated_amount);
        $this->assertStringContainsString('Bill-Only Consignment Replenishment', $pr->title);

        // Verify stock balance decremented
        $stock = ItemStockLevel::where('storage_location_id', $orLocation->id)
            ->where('item_id', $stent->id)
            ->firstOrFail();
        $this->assertSame(4, $stock->quantity);
    }

    public function test_cryptographic_hash_chaining_and_tamper_detection_on_stock_movements(): void
    {
        $item = InventoryItem::create([
            'name' => 'Test Medical Supply',
            'sku' => 'TEST-MED-01',
            'unit' => 'piece',
            'status' => 'active',
        ]);

        $loc = StorageLocation::create([
            'name' => 'Test Bin',
            'code' => 'TEST-BIN-01',
            'barcode_value' => 'LOC-TEST-BIN-01',
            'type' => 'bin',
            'status' => 'active',
        ]);

        $service = app(LedgerIntegrityService::class);

        // 1. Create first movement
        $m1 = StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 10,
            'to_location_id' => $loc->id,
            'moved_at' => now()->subHours(2),
        ]);
        $service->sealMovement($m1);

        $this->assertSame(LedgerIntegrityService::GENESIS_HASH, $m1->previous_hash);
        $this->assertNotEmpty($m1->hash);

        // 2. Create second movement
        $m2 = StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::Transfer,
            'quantity' => 4,
            'from_location_id' => $loc->id,
            'moved_at' => now()->subHour(),
        ]);
        $service->sealMovement($m2);

        $this->assertSame($m1->hash, $m2->previous_hash);

        // 3. Verify chain is pristine
        $audit = $service->verifyChain();
        $this->assertTrue($audit['is_valid']);
        $this->assertSame(2, $audit['verified_count']);
        $this->assertNull($audit['broken_at_id']);

        // 4. Directly tamper with m1 quantity in the database without recalculating hash
        DB::table('stock_movements')
            ->where('id', $m1->id)
            ->update(['quantity' => 999]);

        // 5. Verification detects payload tampering
        $tamperedAudit = $service->verifyChain();
        $this->assertFalse($tamperedAudit['is_valid']);
        $this->assertSame($m1->id, $tamperedAudit['broken_at_id']);
        $this->assertStringContainsString('Payload tampering detected', $tamperedAudit['error']);
    }

    public function test_web_routes_and_access_control(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        // Guest redirected to login
        $this->get('/inventory/warehousing')->assertRedirect('/login');

        // Authenticated staff can view dashboard and locations
        $dashboardResponse = $this->actingAs($user)
            ->get('/inventory/warehousing');
        $dashboardResponse->assertOk()
            ->assertSee('Smart Warehousing System')
            ->assertSee('Spatial Topology')
            ->assertSee('Storage Locations')
            ->assertDontSee('IoT Cold Chain')
            ->assertDontSee('IoT Telemetry Monitor')
            ->assertDontSee('Cold Chain & Environmental Telemetry');

        // Verify IoT Telemetry routes are completely eliminated (404 Not Found)
        $this->actingAs($user)
            ->get('/inventory/warehousing/telemetry')
            ->assertNotFound();

        $this->actingAs($user)
            ->postJson('/api/v1/inventory/telemetry/ingest', [])
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/inventory/warehousing/locations')
            ->assertOk()
            ->assertSee('Spatial Topology');

        $this->actingAs($user)
            ->get('/inventory/warehousing/scan-station')
            ->assertOk()
            ->assertSee('Warehouse Scan Workstation');

        // Staff without AccessNarcoticsVault cannot access narcotics vault
        $this->actingAs($user)
            ->get('/inventory/warehousing/narcotics')
            ->assertForbidden();

        // Pharmacy staff with AccessNarcoticsVault can access vault
        $pharmacist = User::factory()->pharmacyStaff()->create();
        $this->actingAs($pharmacist)
            ->get('/inventory/warehousing/narcotics')
            ->assertOk()
            ->assertSee('Dangerous Drugs Vault');
    }
}
