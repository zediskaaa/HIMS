<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ChainOfCustodyLog;
use App\Models\CostCenter;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\LogisticsDocument;
use App\Models\PurchaseOrder;
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

    private function createDownloadableDocument(User $uploader, string $contents = "%PDF-1.4\nprotected logistics record\n"): LogisticsDocument
    {
        $path = 'logistics_documents/sales-invoice-88192.pdf';

        Storage::disk('local')->put($path, $contents);

        return LogisticsDocument::create([
            'tracking_number' => 'DOC-SI-202609-88192',
            'document_type' => DocumentType::SalesInvoice,
            'title' => 'Zuellig Pharma Electronic Sales Invoice',
            'reference_number' => 'SI-88192',
            'status' => 'verified',
            'file_path' => $path,
            'file_name' => 'sales-invoice-88192.pdf',
            'original_name' => 'Zuellig Pharma Sales Invoice SI-88192.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($contents),
            'disk' => 'local',
            'sha256_checksum' => hash('sha256', $contents),
            'uploaded_by_id' => $uploader->id,
            'version_number' => 1,
        ]);
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

    public function test_authorized_document_download_returns_the_stored_file_and_records_one_audit_event(): void
    {
        Storage::fake('local');
        extract($this->createSetup());
        $contents = "%PDF-1.4\nprotected logistics record\n";
        $document = $this->createDownloadableDocument($buyer, $contents);

        $response = $this->actingAs($buyer)
            ->get(route('inventory.logistics.documents.download', $document));

        $response->assertOk()
            ->assertDownload('Zuellig Pharma Sales Invoice SI-88192.pdf')
            ->assertHeader('content-type', 'application/pdf');
        $this->assertSame($contents, $response->streamedContent());

        $audit = AuditLog::query()
            ->where('action', AuditAction::DownloadedLogisticsDocument->value)
            ->where('target_type', $document->getMorphClass())
            ->where('target_id', (string) $document->id)
            ->sole();

        $this->assertSame($buyer->id, $audit->user_id);
        $this->assertSame($document->tracking_number, $audit->target_reference);
    }

    public function test_each_successful_document_download_records_exactly_one_audit_event(): void
    {
        Storage::fake('local');
        extract($this->createSetup());
        $document = $this->createDownloadableDocument($buyer);

        $this->actingAs($buyer)
            ->get(route('inventory.logistics.documents.download', $document))
            ->assertOk();
        $this->get(route('inventory.logistics.documents.download', $document))
            ->assertOk();

        $audits = AuditLog::query()
            ->where('action', AuditAction::DownloadedLogisticsDocument->value)
            ->where('target_type', $document->getMorphClass())
            ->where('target_id', (string) $document->id)
            ->get();

        $this->assertCount(2, $audits);
        $this->assertCount(2, $audits->pluck('event_id')->unique());
    }

    public function test_missing_document_record_or_file_returns_not_found_without_a_download_audit(): void
    {
        Storage::fake('local');
        extract($this->createSetup());
        $document = $this->createDownloadableDocument($buyer);
        Storage::disk('local')->delete($document->file_path);

        $this->actingAs($buyer)
            ->get(route('inventory.logistics.documents.download', $document))
            ->assertNotFound();

        $this->get(route('inventory.logistics.documents.download', 999999))
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::DownloadedLogisticsDocument->value,
            'target_type' => $document->getMorphClass(),
            'target_id' => (string) $document->id,
        ]);
    }

    public function test_inaccessible_document_storage_returns_not_found_without_a_download_audit(): void
    {
        Storage::fake('local');
        extract($this->createSetup());
        $document = $this->createDownloadableDocument($buyer);
        $document->update(['disk' => 'unconfigured-logistics-disk']);

        $this->actingAs($buyer)
            ->get(route('inventory.logistics.documents.download', $document))
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::DownloadedLogisticsDocument->value,
            'target_type' => $document->getMorphClass(),
            'target_id' => (string) $document->id,
        ]);
    }

    public function test_user_without_sensitive_logistics_access_cannot_download_document(): void
    {
        Storage::fake('local');
        extract($this->createSetup());
        $document = $this->createDownloadableDocument($buyer);
        $viewer = User::factory()->create([
            'role' => UserRole::Viewer,
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.logistics.documents.download', $document))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::DownloadedLogisticsDocument->value,
            'target_type' => $document->getMorphClass(),
            'target_id' => (string) $document->id,
        ]);
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

    public function test_iar_print_view_is_a_standalone_data_driven_multipage_document(): void
    {
        extract($this->createSetup());

        $costCenter = CostCenter::create([
            'name' => 'Central Pharmacy and Medical Supply Cost Center',
            'code' => 'CPMSC-2026-OPERATIONS',
            'department' => 'Hospital Central Pharmacy, Therapeutics, and Medical Supply Operations',
            'is_active' => true,
        ]);

        $item->update([
            'name' => 'Sterile temperature-controlled injectable medicine with an intentionally long stock description for wrapped report table validation',
            'unit_cost' => 300.00,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-REPORT-2026-000178-LONG-REFERENCE',
            'supplier_id' => $supplier->id,
            'cost_center_id' => $costCenter->id,
            'item_id' => $item->id,
            'quantity' => 171,
            'unit_cost' => 300.00,
            'total_amount' => 51300.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $buyer->id,
            'entity_name' => 'St. Jude General Hospital Central Institutional Medical Center',
            'fund_cluster' => '01 — Regular Agency Fund / Medical Supply Operations',
        ]);

        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-REPORT-2026-000178',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
            'dr_number' => 'DR-REPORT-2026-000178',
            'sales_invoice_number' => 'SI-REPORT-2026-000178',
        ]);

        foreach (range(1, 18) as $lineNumber) {
            GoodsReceiptNoteLine::create([
                'goods_receipt_note_id' => $grn->id,
                'item_id' => $item->id,
                'ordered_quantity' => $lineNumber,
                'received_quantity' => $lineNumber,
                'accepted_quantity' => $lineNumber,
                'unit_cost' => 300.00,
                'batch_number' => 'BATCH-'.str_pad((string) $lineNumber, 3, '0', STR_PAD_LEFT),
                'expiry_date' => now()->addYears(2),
            ]);
        }

        $iar = app(InspectionAcceptanceService::class)->createFromReceipt($grn, [], $buyer);
        $iar->update([
            'inspection_date' => now(),
            'inspected_by_id' => $inspector->id,
            'inspection_status' => 'in_order',
            'inspection_findings' => 'All delivered lots were inspected against the purchase specification and attached Certificate of Analysis; labels, seals, and recorded quantities were verified.',
            'acceptance_date' => now(),
            'accepted_by_id' => $custodian->id,
            'delivery_status' => 'complete',
            'status' => 'accepted',
            'notes' => 'Accepted in full after technical verification and document reconciliation.',
        ]);

        LogisticsDocument::create([
            'tracking_number' => 'DOC-COA-REPORT-2026-000178',
            'document_type' => DocumentType::CertificateOfAnalysis,
            'title' => 'Manufacturer Certificate of Analysis — Delivered Lots',
            'reference_number' => 'COA-LOT-2026-178',
            'inspection_acceptance_report_id' => $iar->id,
            'status' => 'verified',
            'uploaded_by_id' => $buyer->id,
        ]);

        $response = $this->actingAs($buyer)
            ->get(route('inventory.logistics.iar.print', $iar));

        $response->assertOk()
            ->assertSee('<!DOCTYPE html>', false)
            ->assertSee('data-iar-document', false)
            ->assertSee('St. Jude General Hospital Central Institutional Medical Center')
            ->assertSee('Hospital Central Pharmacy, Therapeutics, and Medical Supply Operations')
            ->assertSee('CPMSC-2026-OPERATIONS')
            ->assertSee('SI-REPORT-2026-000178')
            ->assertSee('BATCH-018')
            ->assertSee('&#8369;51,300.00', false)
            ->assertSee('Technical Inspector')
            ->assertSee('Property Custodian')
            ->assertSee('Certificate of Analysis (COA) / CPR')
            ->assertSee('@bottom-right', false)
            ->assertSee('counter(pages)', false)
            ->assertSee('display: table-header-group', false)
            ->assertSee('page-break-inside: avoid', false)
            ->assertDontSee('Search items, POs')
            ->assertDontSee('Print GAM App. 50')
            ->assertDontSee('127.0.0.1')
            ->assertDontSee('localhost');

        $this->get(route('inventory.logistics.iar.show', $iar))
            ->assertOk()
            ->assertSee(route('inventory.logistics.iar.print', ['iar' => $iar, 'print' => 1]), false);
    }

    public function test_iar_print_view_does_not_fabricate_missing_optional_metadata(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-REPORT-MISSING-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_cost' => 1000.00,
            'total_amount' => 1000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $buyer->id,
            'entity_name' => '',
            'fund_cluster' => '',
        ]);

        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-REPORT-MISSING-01',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
        ]);

        GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $grn->id,
            'item_id' => $item->id,
            'ordered_quantity' => 1,
            'received_quantity' => 1,
            'accepted_quantity' => 1,
            'unit_cost' => 1000.00,
        ]);

        $iar = app(InspectionAcceptanceService::class)->createFromReceipt($grn, [], $buyer);

        $this->actingAs($buyer)
            ->get(route('inventory.logistics.iar.print', $iar))
            ->assertOk()
            ->assertSee('Organization not recorded')
            ->assertSee('Not recorded')
            ->assertDontSee('Hospital Central Pharmacy &amp; Supply')
            ->assertDontSee('HIMS-101-02');
    }

    public function test_user_without_sensitive_logistics_access_cannot_print_an_iar(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-REPORT-AUTH-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_cost' => 1000.00,
            'total_amount' => 1000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'created_by_user_id' => $buyer->id,
        ]);
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-REPORT-AUTH-01',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $buyer->id,
            'received_at' => now(),
            'receipt_status' => 'received',
        ]);
        $iar = app(InspectionAcceptanceService::class)->createFromReceipt($grn, [], $buyer);
        $viewer = User::factory()->create([
            'role' => UserRole::Viewer,
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.logistics.iar.print', $iar))
            ->assertForbidden();
    }

    public function test_web_routes_and_controllers_render_screens_with_role_authorization(): void
    {
        Storage::fake('local');
        extract($this->createSetup());
        $this->createDownloadableDocument($buyer);

        // Authenticate as authorized InventoryManager
        $this->actingAs($buyer);

        $this->get(route('inventory.logistics'))
            ->assertStatus(200)
            ->assertSee('Document Tracking')
            ->assertSee('Logistics Records');

        $this->get(route('inventory.logistics.documents'))
            ->assertStatus(200)
            ->assertSee('Document Tracking Registry')
            ->assertSee('data-hims-download', false)
            ->assertSee('data-loading-text="Preparing document..."', false);

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

    public function test_logistics_document_supersede_creates_new_revision_and_archives_original(): void
    {
        Storage::fake('local');
        extract($this->createSetup());

        $originalDoc = $this->createDownloadableDocument($buyer);

        $this->assertEquals(1, $originalDoc->version_number);
        $this->assertNull($originalDoc->superseded_by_id);
        $this->assertEquals('verified', $originalDoc->status);

        $newFile = UploadedFile::fake()->create('revised_invoice_88192.pdf', 150, 'application/pdf');

        $response = $this->actingAs($buyer)->post(
            route('inventory.logistics.documents.supersede', $originalDoc),
            [
                'file' => $newFile,
                'reason' => 'Supplier revised VAT invoice breakdown.',
            ]
        );

        $response->assertRedirect(route('inventory.logistics.documents'));
        $response->assertSessionHas('success');

        $originalDoc->refresh();
        $this->assertEquals('archived', $originalDoc->status);
        $this->assertNotNull($originalDoc->superseded_by_id);

        $newDoc = LogisticsDocument::findOrFail($originalDoc->superseded_by_id);
        $this->assertEquals(2, $newDoc->version_number);
        $this->assertEquals($originalDoc->id, $newDoc->replaces_document_id);
        $this->assertEquals('Supplier revised VAT invoice breakdown.', $newDoc->revision_reason);
        $this->assertEquals('submitted', $newDoc->status);
        $this->assertNotEmpty($newDoc->sha256_checksum);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RevisedLogisticsDocument->value,
            'target_type' => $newDoc->getMorphClass(),
            'target_id' => (string) $newDoc->id,
            'user_id' => $buyer->id,
        ]);

        // Attempting to supersede again on the already-superseded document should fail
        $anotherFile = UploadedFile::fake()->create('attempt_third_revision.pdf', 100, 'application/pdf');
        $duplicateAttemptResponse = $this->actingAs($buyer)->from(route('inventory.logistics.documents'))->post(
            route('inventory.logistics.documents.supersede', $originalDoc),
            [
                'file' => $anotherFile,
                'reason' => 'Another revision attempt.',
            ]
        );

        $duplicateAttemptResponse->assertRedirect(route('inventory.logistics.documents'));
        $duplicateAttemptResponse->assertSessionHas('error');
    }

    public function test_documents_table_actions_column_alignment_and_slots_across_mixed_statuses(): void
    {
        Storage::fake('local');
        extract($this->createSetup());

        LogisticsDocument::create([
            'tracking_number' => 'DOC-SUB-001',
            'version_number' => 1,
            'document_type' => DocumentType::DeliveryReceipt,
            'title' => 'Submitted Delivery Receipt',
            'file_name' => 'sub.pdf',
            'original_name' => 'sub.pdf',
            'file_path' => 'logistics/documents/sub.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => hash('sha256', 'sub'),
            'status' => 'submitted',
            'uploaded_by_id' => $buyer->id,
        ]);

        LogisticsDocument::create([
            'tracking_number' => 'DOC-VER-001',
            'version_number' => 1,
            'document_type' => DocumentType::SalesInvoice,
            'title' => 'Verified Sales Invoice',
            'file_name' => 'ver.pdf',
            'original_name' => 'ver.pdf',
            'file_path' => 'logistics/documents/ver.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'sha256_checksum' => hash('sha256', 'ver'),
            'status' => 'verified',
            'uploaded_by_id' => $buyer->id,
            'verified_by_id' => $buyer->id,
            'verified_at' => now(),
        ]);

        LogisticsDocument::create([
            'tracking_number' => 'DOC-ARC-001',
            'version_number' => 1,
            'document_type' => DocumentType::PurchaseOrder,
            'title' => 'Archived Purchase Order',
            'file_name' => 'arc.pdf',
            'original_name' => 'arc.pdf',
            'file_path' => 'logistics/documents/arc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 3072,
            'sha256_checksum' => hash('sha256', 'arc'),
            'status' => 'archived',
            'uploaded_by_id' => $buyer->id,
        ]);

        $response = $this->actingAs($buyer)->get(route('inventory.logistics.documents'));
        $response->assertOk();

        // Check header actions width and alignment
        $response->assertSee('Actions', false);
        $response->assertSee('w-80 min-w-80', false);
        $response->assertSee('grid-cols-[2rem_6rem_4rem_4rem]', false);

        // Check that submitted doc has both Verify and Revise
        $response->assertSee('Submitted Delivery Receipt');
        $response->assertSee('Verified Sales Invoice');
        $response->assertSee('Archived Purchase Order');
        $response->assertSee('Verify');
        $response->assertSee('Revise');
    }

    public function test_shipments_page_renders_space_efficient_full_width_layout(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-SHIP-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 50,
            'unit_cost' => 1000.00,
            'total_amount' => 50000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'requested_by_id' => $buyer->id,
        ]);

        Shipment::create([
            'shipment_number' => 'SHP-2026-TEST1',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'FastFreight Logistics Inc.',
            'tracking_number' => 'TRK-FF-9901',
            'waybill_number' => 'WB-8812',
            'vehicle_plate_number' => 'XYZ-1234',
            'driver_name' => 'Mario Gomez',
            'driver_contact' => '0917-000-1111',
            'sscc' => '376123450000100082',
            'dispatch_date' => now()->subDays(5),
            'estimated_delivery_date' => now()->subDays(2),
            'actual_delivery_date' => now(),
            'status' => 'received',
            'is_cold_chain' => false,
            'notes' => 'Docked and accepted.',
        ]);

        Shipment::create([
            'shipment_number' => 'SHP-2026-TEST2',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'Polar Pharma Cargo',
            'tracking_number' => 'TRK-PP-7723',
            'waybill_number' => 'WB-9933',
            'vehicle_plate_number' => 'ABC-9876',
            'driver_name' => 'Roberto Cruz',
            'driver_contact' => '0918-222-3333',
            'sscc' => '000123456700000015',
            'dispatch_date' => now()->subDays(1),
            'estimated_delivery_date' => now()->addDays(2),
            'status' => 'in_transit',
            'is_cold_chain' => true,
            'temp_logger_serial' => 'LOG-2026-X1',
            'temp_min' => 3.2,
            'temp_max' => 5.8,
            'temp_excursion' => false,
            'notes' => 'Vaccine consignment.',
        ]);

        $response = $this->actingAs($buyer)->get(route('inventory.logistics.shipments'));
        $response->assertOk();

        // Check full-width layout
        $response->assertSee('max-w-none', false);

        // Check header title & action
        $response->assertSee('Shipments &amp; Carrier Logistics', false);
        $response->assertSee('Register Inbound Shipment', false);

        // Check table headers
        $response->assertSee('Shipment &amp; Origin', false);
        $response->assertSee('Carrier / 3PL Info', false);
        $response->assertSee('GS1 SSCC Barcode', false);
        $response->assertSee('Delivery Schedule', false);
        $response->assertSee('Cold Chain Integrity', false);
        $response->assertSee('Status', false);
        $response->assertSee('Dock Action', false);

        // Check shipment records rendered
        $response->assertSee('SHP-2026-TEST1');
        $response->assertSee('FastFreight Logistics Inc.');
        $response->assertSee('3 7612345 000010008 2');
        $response->assertSee('SHP-2026-TEST2');
        $response->assertSee('Polar Pharma Cargo');
        $response->assertSee('Cold Chain');
        $response->assertSee('LOG-2026-X1');

        // Check Dock Arrival button for in-transit shipment
        $response->assertSee('Dock Arrival');
        $response->assertSee('Docked');

        // Check GS1 SSCC camera scanner integration
        $response->assertSee('camera-scanner-shipment-sscc', false);
        $response->assertSee('Scan Pallet / Shipment GS1 SSCC Barcode', false);
        $response->assertSee("validateFormat: 'sscc'", false);
    }

    public function test_chain_of_custody_page_renders_space_efficient_full_width_layout(): void
    {
        extract($this->createSetup());

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-COC-01',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 20,
            'unit_cost' => 1000.00,
            'total_amount' => 20000.00,
            'delivery_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
            'requested_by_id' => $buyer->id,
        ]);

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-2026-COC-01',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'ColdFleet Express',
            'tracking_number' => 'TRK-CF-1122',
            'status' => 'arrived_at_dock',
        ]);

        ChainOfCustodyLog::create([
            'custody_number' => 'COC-2026-00001',
            'trackable_type' => Shipment::class,
            'trackable_id' => $shipment->id,
            'event_type' => 'dock_arrival',
            'releasing_party_name' => 'Danilo Bautista (Fleet Driver)',
            'receiving_party_name' => 'Ben Santos (Dock Receiving)',
            'origin_location' => 'Zuellig Warehouse Paranaque',
            'destination_location' => 'HIMS Receiving Dock Bay 1',
            'package_condition' => 'good_order',
            'transferred_at' => now()->subHours(2),
            'notes' => 'Dock intake complete with zero excursions.',
            'user_agent' => 'HIMS Dock Handheld Console',
            'ip_address' => '192.168.10.45',
        ]);

        $response = $this->actingAs($buyer)->get(route('inventory.logistics.chain-of-custody'));
        $response->assertOk();

        // Check full-width layout
        $response->assertSee('max-w-none', false);

        // Check header title & badge
        $response->assertSee('Chain of Custody Ledger', false);
        $response->assertSee('Append-Only Immutability Guarded', false);

        // Check table headers
        $response->assertSee('Timestamp', false);
        $response->assertSee('Custody Event', false);
        $response->assertSee('Trackable Reference', false);
        $response->assertSee('Transfer Parties (Released &rarr; Received)', false);
        $response->assertSee('Location &amp; Condition', false);
        $response->assertSee('Forensic Fingerprint', false);

        // Check log record rendered
        $response->assertSee('Dock Arrival');
        $response->assertSee('SHP-2026-COC-01');
        $response->assertSee('Danilo Bautista (Fleet Driver)');
        $response->assertSee('Ben Santos (Dock Receiving)');
        $response->assertSee('HIMS Receiving Dock Bay 1');
        $response->assertSee('Condition: Good Order');
        $response->assertSee('HIMS Dock Handheld Console');
    }
}

