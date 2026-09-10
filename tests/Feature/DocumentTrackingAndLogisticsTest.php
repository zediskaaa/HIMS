<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\ChainOfCustodyLog;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\LogisticsDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Shipment;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Logistics\DocumentTrackingService;
use App\Services\Logistics\InspectionAcceptanceService;
use App\Services\Logistics\ShipmentTrackingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class DocumentTrackingAndLogisticsTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $supplier = Supplier::create([
            'name' => 'Zuellig Pharma Test',
            'contact_person' => 'Danilo Bautista',
            'email' => 'zuellig.test@example.com',
            'status' => 'active',
        ]);

        $location = StorageLocation::create([
            'name' => 'Central Cold Room Test',
            'code' => 'TEST-COLD-01',
            'type' => 'room',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'sku' => 'TEST-RAB-001',
            'name' => 'Rabies Vaccine Test 0.5mL',
            'generic_name' => 'Rabies Vaccine',
            'unit' => 'vial',
            'unit_cost' => 1000.00,
            'storage_temp_min' => 2.0,
            'storage_temp_max' => 8.0,
            'temperature_classification' => 'COLD_CHAIN',
            'supplier_id' => $supplier->id,
            'default_location_id' => $location->id,
            'status' => 'active',
        ]);

        $buyer = User::factory()->create([
            'name' => 'Buyer Officer',
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $inspector = User::factory()->create([
            'name' => 'Technical Inspector',
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $custodian = User::factory()->create([
            'name' => 'Property Custodian',
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        return compact('supplier', 'location', 'item', 'buyer', 'inspector', 'custodian');
    }

    public function test_liquidated_damages_calculated_on_delayed_po_delivery_per_coa_gam_app_61(): void
    {
        extract($this->createSetup());

        // PO expected 10 days ago
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-DELAY-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 100,
            'unit_cost' => 1000.00,
            'total_amount' => 100000.00,
            'delivery_date' => now()->subDays(10)->toDateString(),
            'penalty_clause_rate' => 0.00100, // 1/10 of 1%
            'status' => PurchaseOrderStatus::Approved->value,
            'requested_by_id' => $buyer->id,
        ]);

        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-TEST-001',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
            'dr_number' => 'DR-TEST-01',
            'sales_invoice_number' => 'SI-TEST-01',
        ]);

        GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $grn->id,
            'item_id' => $item->id,
            'ordered_quantity' => 100,
            'received_quantity' => 100,
            'accepted_quantity' => 100,
            'unit_cost' => 1000.00,
        ]);

        $service = app(InspectionAcceptanceService::class);
        $iar = $service->createFromReceipt($grn, [], $buyer);

        // 100 units * ₱1,000 = ₱100,000 value
        // 10 days delay * 0.001 * ₱100,000 = ₱1,000.00 liquidated damages
        $this->assertEquals(10, $iar->days_delayed);
        $this->assertEquals(1000.00, (float) $iar->liquidated_damages_amount);
        $this->assertEquals('pending_inspection', $iar->status);
    }

    public function test_cold_chain_excursion_blocks_technical_inspection_approval(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-COLD-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 50,
            'unit_cost' => 1000.00,
            'total_amount' => 50000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'requested_by_id' => $buyer->id,
        ]);

        // Cold chain breach recorded at dock
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-COLD-BREACH',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'is_cold_chain' => true,
            'temp_excursion' => true, // EXCURSION!
            'transit_temp_min' => 1.2,
            'transit_temp_max' => 14.5,
            'receipt_status' => 'received',
        ]);

        GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $grn->id,
            'item_id' => $item->id,
            'ordered_quantity' => 50,
            'received_quantity' => 50,
            'accepted_quantity' => 0,
            'unit_cost' => 1000.00,
        ]);

        $service = app(InspectionAcceptanceService::class);
        $iar = $service->createFromReceipt($grn, [], $buyer);

        // Attempting to pass inspection on breached cold-chain cargo MUST throw ValidationException
        $this->expectException(ValidationException::class);

        $service->performTechnicalInspection($iar, [
            'inspection_status' => 'in_order',
            'inspection_findings' => 'Trying to pass breached lot',
        ], $inspector);
    }

    public function test_segregation_of_duties_enforced_between_buyer_inspector_and_custodian(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-SOD-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => 1000.00,
            'total_amount' => 10000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $buyer->id,
        ]);

        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-SOD-01',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
        ]);

        GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $grn->id,
            'item_id' => $item->id,
            'ordered_quantity' => 10,
            'received_quantity' => 10,
            'accepted_quantity' => 10,
            'unit_cost' => 1000.00,
        ]);

        $service = app(InspectionAcceptanceService::class);
        $iar = $service->createFromReceipt($grn, [], $buyer);

        // 1. Buyer who drafted PO cannot inspect delivery
        try {
            $service->performTechnicalInspection($iar, [
                'inspection_status' => 'in_order',
                'inspection_findings' => 'Inspected by buyer',
            ], $buyer);
            $this->fail('Expected DomainException when buyer inspects delivery.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Segregation of duties', $e->getMessage());
        }

        // 2. Technical inspector successfully inspects
        $service->performTechnicalInspection($iar, [
            'inspection_status' => 'in_order',
            'inspection_findings' => 'Inspected by independent technical officer.',
        ], $inspector);

        $this->assertEquals('inspected_passed', $iar->fresh()->status);

        // 3. Technical inspector cannot accept their own inspection as custodian
        try {
            $service->performCustodialAcceptance($iar, [
                'delivery_status' => 'complete',
            ], $inspector);
            $this->fail('Expected DomainException when inspector accepts own inspection.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Segregation of duties', $e->getMessage());
        }

        // 4. Property custodian successfully accepts
        $service->performCustodialAcceptance($iar, [
            'delivery_status' => 'complete',
        ], $custodian);

        $this->assertEquals('accepted', $iar->fresh()->status);
        $this->assertEquals($custodian->id, $iar->fresh()->accepted_by_id);
    }

    public function test_custodial_acceptance_posts_inventory_movements_and_updates_stock(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-STOCK-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 20,
            'unit_cost' => 1000.00,
            'total_amount' => 20000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $buyer->id,
        ]);

        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-STOCK-01',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
        ]);

        GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $grn->id,
            'item_id' => $item->id,
            'ordered_quantity' => 20,
            'received_quantity' => 20,
            'accepted_quantity' => 20,
            'unit_cost' => 1000.00,
        ]);

        $service = app(InspectionAcceptanceService::class);
        $iar = $service->createFromReceipt($grn, [], $buyer);

        $service->performTechnicalInspection($iar, [
            'inspection_status' => 'in_order',
            'inspection_findings' => 'Passed.',
        ], $inspector);

        $service->performCustodialAcceptance($iar, [
            'delivery_status' => 'complete',
        ], $custodian);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn->value,
            'quantity' => 20,
        ]);

        $this->assertEquals('posted', $grn->fresh()->receipt_status);
    }

    public function test_logistics_document_upload_calculates_sha256_and_sets_nap_retention(): void
    {
        Storage::fake('local');
        extract($this->createSetup());

        $service = app(DocumentTrackingService::class);
        $file = UploadedFile::fake()->create('sales_invoice_88192.pdf', 120, 'application/pdf');

        $doc = $service->uploadDocument([
            'document_type' => DocumentType::SalesInvoice,
            'title' => 'Zuellig Pharma Electronic Sales Invoice',
            'reference_number' => 'SI-88192',
            'supplier_id' => $supplier->id,
        ], $file, $buyer);

        $this->assertNotEmpty($doc->sha256_checksum);
        $this->assertEquals(64, strlen($doc->sha256_checksum));
        $this->assertEquals('tax_invoice_5yr', $doc->retention_class);
        $this->assertNotNull($doc->retention_until);
        $this->assertFalse($doc->isVerified());

        // Verify document
        $service->verifyDocument($doc, 'verified', 'BIR stamp confirmed authentic.', $inspector);
        $this->assertTrue($doc->fresh()->isVerified());
        $this->assertEquals($inspector->id, $doc->fresh()->verified_by_id);

        // Download document stream
        $response = $service->downloadDocument($doc);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_chain_of_custody_log_is_strictly_append_only_and_immutable(): void
    {
        extract($this->createSetup());

        $log = ChainOfCustodyLog::create([
            'custody_number' => 'COC-TEST-00001',
            'trackable_type' => Supplier::class,
            'trackable_id' => $supplier->id,
            'event_type' => 'dock_arrival',
            'releasing_party_name' => 'Courier A',
            'receiving_party_name' => 'Receiving B',
            'transferred_at' => now(),
            'notes' => 'Original notes',
        ]);

        // Attempt update MUST throw LogicException
        try {
            $log->update(['notes' => 'Attempted tamper']);
            $this->fail('Expected LogicException on ChainOfCustodyLog update.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('immutable and append-only', $e->getMessage());
        }

        // Attempt delete MUST throw LogicException
        try {
            $log->delete();
            $this->fail('Expected LogicException on ChainOfCustodyLog delete.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('immutable and cannot be deleted', $e->getMessage());
        }
    }

    public function test_gs1_sscc_18_check_digit_validation(): void
    {
        $service = app(ShipmentTrackingService::class);

        $this->assertTrue($service->validateSscc('000123456700000015'));
        $this->assertTrue($service->validateSscc('376123450000100082'));
        $this->assertFalse($service->validateSscc('000123456700000018')); // Wrong check digit
        $this->assertFalse($service->validateSscc('12345')); // Wrong length

        extract($this->createSetup());
        $this->expectException(InvalidArgumentException::class);

        $service->registerInboundShipment([
            'carrier_name' => 'Test Carrier',
            'sscc' => '000123456700000018', // invalid
        ], $buyer);
    }

    public function test_web_routes_and_controllers_render_screens_with_role_authorization(): void
    {
        extract($this->createSetup());

        // Authenticate as authorized InventoryManager
        $this->actingAs($buyer);

        $this->get(route('inventory.logistics'))
            ->assertStatus(200)
            ->assertSee('Document Tracking')
            ->assertSee('Logistics Records');

        $this->get(route('inventory.logistics.documents'))
            ->assertStatus(200)
            ->assertSee('Document Tracking Registry');

        $this->get(route('inventory.logistics.shipments'))
            ->assertStatus(200)
            ->assertSee('Shipments')
            ->assertSee('Carrier Logistics');

        $this->get(route('inventory.logistics.iar.index'))
            ->assertStatus(200)
            ->assertSee('Inspection')
            ->assertSee('Acceptance Reports');

        $this->get(route('inventory.logistics.chain-of-custody'))
            ->assertStatus(200)
            ->assertSee('Chain of Custody Ledger');
    }
}
