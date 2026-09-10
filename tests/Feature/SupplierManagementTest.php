<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierDocumentStatus;
use App\Enums\SupplierStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierComplianceAlert;
use App\Models\SupplierContract;
use App\Models\SupplierDocument;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_replace([
            'name' => 'Acme Medical Supplies',
            'business_structure' => 'corporation',
            'address' => '100 Health Avenue, Manila',
            'email' => 'procurement@acme.example',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ], $overrides));
    }

    private function item(string $sku = 'MED-001'): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'Sterile Gauze',
            'sku' => $sku,
            'unit' => 'box',
            'status' => 'active',
        ]);
    }

    public function test_manager_creates_a_draft_supplier_and_audit_event(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post('/inventory/suppliers', [
            'name' => 'Acme Medical Supplies',
            'business_structure' => 'corporation',
            'email' => 'procurement@acme.example',
            'tax_number' => '123-456-789',
            'provides_regulated_health_products' => '1',
        ])->assertRedirect('/inventory/suppliers/1');

        $supplier = Supplier::firstOrFail();
        $this->assertSame(SupplierAccreditationStatus::Draft, $supplier->accreditation_status);
        $this->assertFalse($supplier->isProcurementEligible());
        $this->assertSame($manager->id, $supplier->created_by);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CreatedSupplier->value, 'target_id' => (string) $supplier->id]);
    }

    public function test_directory_and_complete_supplier_profile_render_real_empty_states(): void
    {
        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->get('/inventory/suppliers')
            ->assertOk()
            ->assertSee('Supplier directory')
            ->assertSee('Not eligible');

        $this->actingAs($manager)->get("/inventory/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertSee('Compliance evidence')
            ->assertSee('No compliance evidence')
            ->assertSee('No products linked')
            ->assertSee('No contracts recorded')
            ->assertSee('No defensible performance score yet');
    }

    public function test_inventory_item_page_only_offers_procurement_eligible_suppliers(): void
    {
        $eligible = $this->supplier([
            'name' => 'Eligible Medical Supplier',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addYear(),
        ]);
        $unverified = $this->supplier([
            'name' => 'Unverified Medical Supplier',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addYear(),
        ]);
        $unverified->documents()->create([
            'document_type' => 'Operating license',
            'disk' => 'local',
            'path' => 'unverified-license.pdf',
            'original_name' => 'unverified-license.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'verification_status' => SupplierDocumentStatus::Pending,
            'blocks_procurement_when_invalid' => true,
        ]);
        $expired = $this->supplier([
            'name' => 'Expired Medical Supplier',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addYear(),
        ]);
        $expired->documents()->create([
            'document_type' => 'Operating license',
            'disk' => 'local',
            'path' => 'expired-license.pdf',
            'original_name' => 'expired-license.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'verification_status' => SupplierDocumentStatus::Verified,
            'expires_at' => today()->subDay(),
            'blocks_procurement_when_invalid' => true,
        ]);
        $draft = $this->supplier(['name' => 'Draft Medical Supplier']);

        $this->actingAs($this->manager())->get('/inventory/items')
            ->assertOk()
            ->assertSee('Eligible Medical Supplier')
            ->assertSee('Unverified Medical Supplier')
            ->assertSee('Expired Medical Supplier')
            ->assertSee('Draft Medical Supplier')
            ->assertSee('Compliance action required')
            ->assertSee('Draft accreditation')
            ->assertSee('Review supplier accreditation')
            ->assertViewHas('eligibleSuppliers', fn ($suppliers) => $suppliers->modelKeys() === [$eligible->id]
                && ! $suppliers->contains($unverified)
                && ! $suppliers->contains($expired)
                && ! $suppliers->contains($draft));
    }

    public function test_supplier_creation_validates_required_and_structured_fields(): void
    {
        $this->actingAs($this->manager())->post('/inventory/suppliers', [
            'name' => '',
            'email' => 'not-an-email',
            'business_structure' => 'invented-type',
            'standard_lead_time_days' => -1,
        ])->assertSessionHasErrors(['name', 'email', 'business_structure', 'standard_lead_time_days']);

        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_duplicate_tax_identity_is_rejected_after_normalization(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post('/inventory/suppliers', ['name' => 'First Entity', 'tax_number' => '123-456-789']);

        $this->actingAs($manager)->post('/inventory/suppliers', ['name' => 'Different Trading Name', 'tax_number' => '123456789'])
            ->assertSessionHasErrors('tax_number');

        $this->actingAs($manager)->post('/inventory/suppliers', ['name' => 'Address Identity', 'address' => '10 Main Street']);
        $this->actingAs($manager)->post('/inventory/suppliers', ['name' => '  ADDRESS   IDENTITY ', 'address' => '  10 Main Street  '])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('suppliers', 2);
    }

    public function test_document_upload_is_private_pending_and_not_automatically_verified(): void
    {
        Storage::fake('local');
        $supplier = $this->supplier();

        $this->actingAs($this->manager())->post("/inventory/suppliers/{$supplier->id}/documents", [
            'document_type' => 'FDA License to Operate',
            'document_number' => 'LTO-TEST-001',
            'expires_at' => now()->addYear()->toDateString(),
            'required_for_accreditation' => '1',
            'blocks_procurement_when_invalid' => '1',
            'file' => UploadedFile::fake()->create('lto.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = SupplierDocument::firstOrFail();
        $this->assertSame(SupplierDocumentStatus::Pending, $document->verification_status);
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('supplier-documents/'.$supplier->id.'/', $document->path);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::UploadedSupplierDocument->value]);

        $this->actingAs($this->manager())->get("/inventory/suppliers/{$supplier->id}/documents/{$document->id}")
            ->assertOk()
            ->assertDownload('lto.pdf');
        $this->actingAs(User::factory()->warehouseStaff()->create())
            ->get("/inventory/suppliers/{$supplier->id}/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_document_upload_rejects_executable_and_bad_dates(): void
    {
        Storage::fake('local');
        $supplier = $this->supplier();

        $this->actingAs($this->manager())->post("/inventory/suppliers/{$supplier->id}/documents", [
            'document_type' => 'Invalid evidence',
            'issued_at' => '2026-09-10',
            'expires_at' => '2026-09-09',
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors(['expires_at', 'file']);

        $this->assertDatabaseCount('supplier_documents', 0);
    }

    public function test_reviewer_can_verify_or_reject_documents_and_cross_supplier_ids_are_hidden(): void
    {
        $uploader = $this->manager();
        $reviewer = $this->manager();
        $supplier = $this->supplier();
        $other = $this->supplier(['name' => 'Other Supplier']);
        $document = $supplier->documents()->create([
            'document_type' => 'Business Permit', 'disk' => 'local', 'path' => 'missing.pdf', 'original_name' => 'permit.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 100, 'uploaded_by' => $uploader->id,
        ]);

        $this->actingAs($uploader)->patch("/inventory/suppliers/{$supplier->id}/documents/{$document->id}/verification", ['decision' => 'verified'])
            ->assertSessionHasErrors('document');
        $this->actingAs($reviewer)->patch("/inventory/suppliers/{$supplier->id}/documents/{$document->id}/verification", ['decision' => 'verified'])
            ->assertRedirect();
        $this->assertSame(SupplierDocumentStatus::Verified, $document->fresh()->verification_status);

        $this->actingAs($reviewer)->patch("/inventory/suppliers/{$other->id}/documents/{$document->id}/verification", ['decision' => 'rejected', 'review_notes' => 'Mismatch'])
            ->assertNotFound();
    }

    public function test_accreditation_requires_submission_and_valid_required_evidence(): void
    {
        $manager = $this->manager();
        $admin = $this->admin();
        $supplier = $this->supplier();
        $supplier->documents()->create([
            'document_type' => 'Applicable permit', 'disk' => 'local', 'path' => 'permit.pdf', 'original_name' => 'permit.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 100, 'required_for_accreditation' => true,
            'verification_status' => SupplierDocumentStatus::Pending,
        ]);

        $this->actingAs($admin)->post("/inventory/suppliers/{$supplier->id}/approve", ['compliance_attested' => '1'])
            ->assertSessionHasErrors('accreditation');

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/submit")->assertRedirect();
        $this->actingAs($admin)->post("/inventory/suppliers/{$supplier->id}/approve", ['compliance_attested' => '1'])
            ->assertSessionHasErrors('accreditation');

        $document = $supplier->documents()->firstOrFail();
        $document->update(['verification_status' => SupplierDocumentStatus::Verified, 'verified_by' => $manager->id, 'verified_at' => now()]);
        $this->actingAs($admin)->post("/inventory/suppliers/{$supplier->id}/approve", [
            'compliance_attested' => '1', 'expires_at' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $supplier->refresh();
        $this->assertSame(SupplierAccreditationStatus::Approved, $supplier->accreditation_status);
        $this->assertTrue($supplier->isProcurementEligible());
        $this->assertDatabaseHas('supplier_accreditations', ['supplier_id' => $supplier->id, 'cycle_number' => 1, 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ApprovedSupplier->value]);

        $selfReviewed = $this->supplier();
        $this->actingAs($admin)->post("/inventory/suppliers/{$selfReviewed->id}/submit")->assertRedirect();
        $this->actingAs($admin)->post("/inventory/suppliers/{$selfReviewed->id}/approve", ['compliance_attested' => '1'])
            ->assertSessionHasErrors('accreditation');
        $this->assertSame(SupplierAccreditationStatus::PendingReview, $selfReviewed->fresh()->accreditation_status);
    }

    public function test_rejection_preserves_cycle_and_allows_resubmission_as_a_new_cycle(): void
    {
        $supplier = $this->supplier();
        $manager = $this->manager();
        $admin = $this->admin();

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/submit");
        $this->actingAs($admin)->post("/inventory/suppliers/{$supplier->id}/reject", ['decision_notes' => 'Evidence could not be validated'])->assertRedirect();
        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/submit")->assertRedirect();

        $this->assertSame(2, $supplier->accreditations()->count());
        $this->assertEquals(['rejected', 'pending_review'], $supplier->accreditations()->orderBy('cycle_number')->pluck('status')->map(fn ($status) => $status->value)->all());
    }

    public function test_inventory_manager_cannot_approve_suspend_or_reactivate(): void
    {
        $manager = $this->manager();
        $supplier = $this->supplier(['accreditation_status' => SupplierAccreditationStatus::PendingReview]);

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/approve", ['compliance_attested' => '1'])->assertForbidden();
        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/suspend", ['suspension_reason' => 'Unauthorized'])->assertForbidden();
        $supplier->update(['status' => SupplierStatus::Suspended]);
        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/reactivate")->assertForbidden();
    }

    public function test_expired_accreditation_or_blocking_document_removes_procurement_eligibility(): void
    {
        $supplier = $this->supplier([
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->subDay(),
        ]);
        $this->assertSame(SupplierAccreditationStatus::Expired, $supplier->effectiveAccreditationStatus());
        $this->assertFalse($supplier->isProcurementEligible());

        $supplier->update(['accreditation_expires_at' => today()->addYear()]);
        $expiredDocument = $supplier->documents()->create([
            'document_type' => 'Critical license', 'disk' => 'local', 'path' => 'license.pdf', 'original_name' => 'license.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 100, 'verification_status' => SupplierDocumentStatus::Verified,
            'expires_at' => today()->subDay(), 'blocks_procurement_when_invalid' => true,
        ]);
        $this->assertFalse($supplier->fresh()->isProcurementEligible());
        $this->assertNull(Supplier::procurementEligible()->whereKey($supplier->id)->first());

        Storage::fake('local');
        $this->actingAs($this->manager())->post("/inventory/suppliers/{$supplier->id}/documents", [
            'document_type' => 'Ignored replacement label',
            'document_number' => 'LIC-RENEWED',
            'expires_at' => today()->addYear()->toDateString(),
            'replaces_document_id' => $expiredDocument->id,
            'file' => UploadedFile::fake()->create('renewed-license.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $replacement = $supplier->documents()->where('is_current', true)->firstOrFail();
        $this->assertFalse($expiredDocument->fresh()->is_current);
        $this->assertSame($replacement->id, $expiredDocument->fresh()->superseded_by_id);
        $this->assertSame('Critical license', $replacement->document_type);
        $this->assertTrue($replacement->blocks_procurement_when_invalid);
        $this->assertFalse($supplier->fresh()->isProcurementEligible());

        $this->actingAs($this->manager())->patch("/inventory/suppliers/{$supplier->id}/documents/{$replacement->id}/verification", [
            'decision' => 'verified',
        ])->assertRedirect();
        $this->assertTrue($supplier->fresh()->isProcurementEligible());
    }

    public function test_unapproved_supplier_cannot_be_used_for_a_new_purchase_order(): void
    {
        $supplier = $this->supplier();
        $item = $this->item();

        $this->actingAs($this->manager())->post('/inventory/purchases/orders', [
            'supplier_id' => $supplier->id, 'item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 10,
        ])->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_products_prices_and_contracts_keep_supplier_specific_history(): void
    {
        $manager = $this->manager();
        $supplier = $this->supplier();
        $item = $this->item();

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/products", [
            'item_id' => $item->id, 'supplier_sku' => 'ACME-GAUZE', 'minimum_order_quantity' => 10, 'lead_time_days' => 7,
        ])->assertRedirect();
        $product = $supplier->supplierProducts()->firstOrFail();

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/contracts", [
            'contract_number' => 'CTR-BAD', 'starts_at' => '2026-12-31', 'ends_at' => '2026-01-01', 'status' => 'active',
        ])->assertSessionHasErrors('ends_at');

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/contracts", [
            'contract_number' => 'CTR-001', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'active',
        ])->assertRedirect();
        $contract = $supplier->contracts()->firstOrFail();

        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}/contracts/{$contract->id}", ['status' => 'inactive'])
            ->assertRedirect();
        $this->assertSame('inactive', $contract->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::UpdatedSupplierContract->value]);
        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}/contracts/{$contract->id}", ['status' => 'active'])
            ->assertRedirect();

        foreach ([['2026-01-01', '2026-06-30', 100], ['2026-07-01', null, 95]] as [$from, $until, $amount]) {
            $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/prices", [
                'supplier_product_id' => $product->id, 'supplier_contract_id' => $contract->id, 'unit_price' => $amount,
                'currency' => 'PHP', 'minimum_order_quantity' => 10, 'effective_from' => $from, 'effective_until' => $until,
            ])->assertRedirect();
        }

        $this->assertSame(2, SupplierPrice::count());
        $this->assertSame(0.0, (float) $item->fresh()->unit_cost);

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/products", ['item_id' => $item->id])
            ->assertSessionHasErrors('item_id');

        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}/products/{$product->id}/deactivate")
            ->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(2, SupplierPrice::count());
    }

    public function test_invalid_and_expired_prices_are_distinguished_without_overwriting_history(): void
    {
        $manager = $this->manager();
        $item = $this->item();
        $first = $this->supplier();
        $second = $this->supplier(['name' => 'Second Supplier']);

        foreach ([$first, $second] as $supplier) {
            $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/products", ['item_id' => $item->id]);
            $product = $supplier->supplierProducts()->firstOrFail();
            $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/prices", [
                'supplier_product_id' => $product->id, 'unit_price' => $supplier->is($first) ? 80 : 75,
                'currency' => 'PHP', 'minimum_order_quantity' => 1, 'effective_from' => '2025-01-01', 'effective_until' => '2025-12-31',
            ])->assertRedirect();
        }

        $this->assertSame(2, SupplierPrice::count());
        $this->assertFalse(SupplierPrice::firstOrFail()->isCurrent());

        $product = $first->supplierProducts()->firstOrFail();
        $this->actingAs($manager)->post("/inventory/suppliers/{$first->id}/prices", [
            'supplier_product_id' => $product->id, 'unit_price' => 0, 'currency' => 'PHP',
            'minimum_order_quantity' => 0, 'effective_from' => '2026-12-31', 'effective_until' => '2026-01-01',
        ])->assertSessionHasErrors(['unit_price', 'minimum_order_quantity', 'effective_until']);
        $this->assertSame(2, SupplierPrice::count());
    }

    public function test_supplier_suspension_preserves_purchase_order_and_product_price_history(): void
    {
        $supplier = $this->supplier(['accreditation_status' => SupplierAccreditationStatus::Approved]);
        $item = $this->item();
        $order = PurchaseOrder::create([
            'po_number' => 'PO-HISTORY-001', 'supplier_id' => $supplier->id, 'item_id' => $item->id,
            'quantity' => 10, 'unit_cost' => 5, 'total_amount' => 50, 'status' => 'received', 'received_at' => now(),
        ]);

        $this->actingAs($this->admin())->post("/inventory/suppliers/{$supplier->id}/suspend", ['suspension_reason' => 'Institutional compliance hold'])->assertRedirect();

        $this->assertSame(SupplierStatus::Suspended, $supplier->fresh()->status);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'supplier_id' => $supplier->id]);
        $this->assertFalse($supplier->fresh()->isProcurementEligible());

        $this->actingAs($this->admin())->post("/inventory/suppliers/{$supplier->id}/reactivate")->assertRedirect();
        $this->assertTrue($supplier->fresh()->isProcurementEligible());

        $this->actingAs($this->admin())->post("/inventory/suppliers/{$supplier->id}/inactivate", ['inactivation_reason' => 'Supplier requested account closure'])->assertRedirect();
        $this->assertSame(SupplierStatus::Inactive, $supplier->fresh()->status);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_daily_compliance_check_raises_displays_and_resolves_real_expiry_alerts(): void
    {
        $supplier = $this->supplier([
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addDays(10),
        ]);
        $document = $supplier->documents()->create([
            'document_type' => 'Applicable FDA authorization',
            'expires_at' => today()->subDay(),
            'disk' => 'local',
            'path' => 'supplier-documents/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'verification_status' => SupplierDocumentStatus::Verified,
            'blocks_procurement_when_invalid' => true,
        ]);
        $contract = $supplier->contracts()->create([
            'contract_number' => 'CTR-ALERT-001',
            'starts_at' => today()->subMonth(),
            'ends_at' => today()->addDays(20),
            'status' => 'active',
        ]);

        $this->assertSame(0, Artisan::call('suppliers:check-compliance'));
        $this->assertSame(3, SupplierComplianceAlert::active()->count());
        $this->assertDatabaseHas('supplier_compliance_alerts', [
            'source_key' => 'document:'.$document->id,
            'severity' => 'critical',
            'status' => 'open',
        ]);
        $this->actingAs($this->manager())->get('/inventory/suppliers/'.$supplier->id)
            ->assertOk()
            ->assertSee('CTR-ALERT-001')
            ->assertSee('Applicable FDA authorization expired');

        $supplier->update(['accreditation_expires_at' => null]);
        $document->update(['expires_at' => today()->addDays(60)]);
        $contract->update(['status' => 'inactive']);

        Artisan::call('suppliers:check-compliance');
        $this->assertSame(0, SupplierComplianceAlert::active()->count());
        $this->assertSame(3, SupplierComplianceAlert::where('status', 'resolved')->count());
    }

    public function test_direct_api_access_is_permission_checked_and_supplier_delete_is_not_exposed(): void
    {
        $supplier = $this->supplier();
        $warehouse = User::factory()->warehouseStaff()->create();

        $this->actingAs($warehouse)->getJson('/api/v1/suppliers')->assertForbidden();
        $this->actingAs($warehouse)->postJson('/api/v1/suppliers', ['name' => 'Unauthorized'])->assertForbidden();
        $this->actingAs($this->manager())->deleteJson("/api/v1/suppliers/{$supplier->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_procurement_apis_require_procurement_permission_and_do_not_expose_destructive_history_routes(): void
    {
        $viewer = User::factory()->viewer()->create();
        $manager = $this->manager();
        $supplier = $this->supplier(['accreditation_status' => SupplierAccreditationStatus::Approved]);
        $item = $this->item();
        $request = ProcurementRequest::create([
            'request_number' => 'REQ-HISTORY-001', 'title' => 'Historical request', 'item_id' => $item->id,
            'requested_quantity' => 5, 'priority' => 'medium', 'supplier_id' => $supplier->id,
        ]);
        $quote = SupplierQuote::create([
            'procurement_request_id' => $request->id, 'supplier_id' => $supplier->id,
            'quoted_price' => 100, 'status' => 'submitted',
        ]);
        $order = PurchaseOrder::create([
            'po_number' => 'PO-HISTORY-API-001', 'supplier_id' => $supplier->id, 'item_id' => $item->id,
            'quantity' => 5, 'unit_cost' => 20, 'total_amount' => 100, 'status' => 'pending',
        ]);

        foreach (['procurement-requests', 'supplier-quotes', 'purchase-orders'] as $resource) {
            $this->actingAs($viewer)->getJson("/api/v1/{$resource}")->assertForbidden();
            $this->actingAs($viewer)->postJson("/api/v1/{$resource}", [])->assertForbidden();
        }

        foreach ([
            "procurement-requests/{$request->id}",
            "supplier-quotes/{$quote->id}",
            "purchase-orders/{$order->id}",
        ] as $resource) {
            $this->actingAs($manager)->deleteJson("/api/v1/{$resource}")->assertMethodNotAllowed();
        }

        $this->assertDatabaseHas('procurement_requests', ['id' => $request->id, 'supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('supplier_quotes', ['id' => $quote->id, 'supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'supplier_id' => $supplier->id]);
    }

    public function test_inventory_item_exposes_the_real_supplier_product_relationship(): void
    {
        $item = $this->item();
        $product = SupplierProduct::create(['supplier_id' => $this->supplier()->id, 'item_id' => $item->id]);

        $this->assertTrue($item->supplierProducts->contains($product));
    }

    public function test_submission_requires_a_reviewable_supplier_profile(): void
    {
        $supplier = $this->supplier([
            'business_structure' => null,
            'address' => null,
            'email' => null,
            'phone' => null,
        ]);

        $manager = $this->manager();
        $this->actingAs($manager)->get("/inventory/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertSee('Profile not ready for review')
            ->assertDontSee('Submit for Accreditation Review');

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/submit")
            ->assertSessionHasErrors('accreditation');

        $this->assertSame(SupplierAccreditationStatus::Draft, $supplier->fresh()->accreditation_status);
        $this->assertDatabaseCount('supplier_accreditations', 0);
    }

    public function test_critical_master_data_changes_invalidate_approval_and_pending_reviews_cannot_be_silently_changed(): void
    {
        $manager = $this->manager();
        $approved = $this->supplier([
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addYear(),
            'approved_by' => $this->admin()->id,
        ]);

        $this->actingAs($manager)->patch("/inventory/suppliers/{$approved->id}", ['name' => 'Acme Medical Distribution'])
            ->assertRedirect();
        $this->assertSame(SupplierAccreditationStatus::Draft, $approved->fresh()->accreditation_status);
        $this->assertFalse($approved->fresh()->isProcurementEligible());
        $this->assertNull($approved->fresh()->approved_by);

        $pending = $this->supplier(['accreditation_status' => SupplierAccreditationStatus::PendingReview]);
        $this->actingAs($manager)->patch("/inventory/suppliers/{$pending->id}", ['tax_number' => '999-000-111'])
            ->assertSessionHasErrors('accreditation');
        $this->assertNull($pending->fresh()->tax_number);
    }

    public function test_document_versions_are_immutable_after_review_and_upload_validation_rejects_future_or_empty_evidence(): void
    {
        Storage::fake('local');
        $supplier = $this->supplier();
        $uploader = $this->manager();
        $reviewer = $this->manager();
        $document = $supplier->documents()->create([
            'document_type' => 'Business Permit', 'disk' => 'local', 'path' => 'permit.pdf',
            'original_name' => 'permit.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'uploaded_by' => $uploader->id, 'verification_status' => SupplierDocumentStatus::Verified,
        ]);

        $this->actingAs($reviewer)->patch("/inventory/suppliers/{$supplier->id}/documents/{$document->id}/verification", ['decision' => 'rejected', 'review_notes' => 'Changed mind'])
            ->assertSessionHasErrors('document');
        $this->assertSame(SupplierDocumentStatus::Verified, $document->fresh()->verification_status);

        $this->actingAs($uploader)->post("/inventory/suppliers/{$supplier->id}/documents", [
            'document_type' => 'Future evidence',
            'issued_at' => today()->addDay()->toDateString(),
            'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
        ])->assertSessionHasErrors(['issued_at', 'file']);
    }

    public function test_inactive_products_and_contracts_cannot_receive_prices_and_overlapping_prices_are_rejected(): void
    {
        $manager = $this->manager();
        $supplier = $this->supplier();
        $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'item_id' => $this->item()->id]);
        $contract = SupplierContract::create([
            'supplier_id' => $supplier->id, 'contract_number' => 'CTR-INACTIVE',
            'starts_at' => today()->subDay(), 'ends_at' => today()->addYear(), 'status' => 'inactive',
        ]);

        $payload = [
            'supplier_product_id' => $product->id, 'unit_price' => 100, 'currency' => 'PHP',
            'minimum_order_quantity' => 1, 'effective_from' => today()->toDateString(),
        ];

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/prices", $payload + ['supplier_contract_id' => $contract->id])
            ->assertSessionHasErrors('supplier_contract_id');

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/prices", $payload)->assertRedirect();
        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/prices", $payload + ['effective_from' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('effective_from');

        $product->update(['is_active' => false]);
        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/prices", $payload + ['effective_from' => today()->addMonth()->toDateString()])
            ->assertSessionHasErrors('supplier_product_id');

        $this->assertDatabaseCount('supplier_prices', 1);
    }

    public function test_deactivated_supplier_product_can_be_reactivated_without_losing_price_history(): void
    {
        $supplier = $this->supplier();
        $product = SupplierProduct::create([
            'supplier_id' => $supplier->id, 'item_id' => $this->item()->id, 'is_active' => false,
        ]);

        $this->actingAs($this->manager())->patch("/inventory/suppliers/{$supplier->id}/products/{$product->id}/reactivate")
            ->assertRedirect();

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_supplier_foreign_keys_prevent_deleting_procurement_attribution(): void
    {
        $supplier = $this->supplier();
        $request = ProcurementRequest::create([
            'request_number' => 'REQ-FK-001', 'title' => 'Retained request', 'item_id' => $this->item()->id,
            'requested_quantity' => 1, 'priority' => 'medium', 'supplier_id' => $supplier->id,
        ]);

        try {
            $supplier->delete();
            $this->fail('The supplier deletion should have been blocked by procurement history.');
        } catch (QueryException) {
            $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
            $this->assertDatabaseHas('procurement_requests', ['id' => $request->id, 'supplier_id' => $supplier->id]);
        }
    }

    public function test_ui_hides_actions_that_separation_of_duties_would_reject(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier([
            'created_by' => $admin->id,
            'accreditation_status' => SupplierAccreditationStatus::PendingReview,
        ]);
        $document = $supplier->documents()->create([
            'document_type' => 'Permit', 'disk' => 'local', 'path' => 'permit.pdf', 'original_name' => 'permit.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 100, 'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/inventory/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertSee('Independent decision required')
            ->assertDontSee('Approve Accreditation')
            ->assertDontSee('inventory/suppliers/'.$supplier->id.'/documents/'.$document->id.'/verification', false);
    }

    public function test_no_op_updates_and_repeated_state_changes_do_not_create_misleading_audit_events(): void
    {
        $manager = $this->manager();
        $supplier = $this->supplier();
        $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'item_id' => $this->item()->id]);
        $contract = SupplierContract::create([
            'supplier_id' => $supplier->id, 'contract_number' => 'CTR-NOOP',
            'starts_at' => today(), 'status' => 'active',
        ]);

        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}", ['name' => $supplier->name])->assertRedirect();
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::UpdatedSupplier->value, 'target_id' => (string) $supplier->id]);

        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}/contracts/{$contract->id}", ['status' => 'active'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::UpdatedSupplierContract->value, 'target_id' => (string) $supplier->id]);

        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}/products/{$product->id}/deactivate")->assertRedirect();
        $auditCount = $supplier->fresh()->getMorphClass();
        $this->actingAs($manager)->patch("/inventory/suppliers/{$supplier->id}/products/{$product->id}/deactivate")
            ->assertSessionHasErrors('product');
        $this->assertSame(1, AuditLog::where('action', AuditAction::UpdatedSupplierProduct->value)
            ->where('target_type', $auditCount)->where('target_id', (string) $supplier->id)->count());
    }

    public function test_current_document_reference_cannot_be_duplicated_and_storage_uses_a_generated_private_path(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $supplier = $this->supplier();
        $supplier->documents()->create([
            'document_type' => 'Business Permit', 'document_number' => 'BP-001', 'disk' => 'local',
            'path' => 'existing.pdf', 'original_name' => 'existing.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
        ]);

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/documents", [
            'document_type' => 'Business Permit', 'document_number' => 'BP-001',
            'file' => UploadedFile::fake()->create('duplicate.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('document_number');

        $this->actingAs($manager)->post("/inventory/suppliers/{$supplier->id}/documents", [
            'document_type' => 'Tax Registration', 'document_number' => 'TAX-001',
            'file' => UploadedFile::fake()->create('../unsafe.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $stored = $supplier->documents()->where('document_number', 'TAX-001')->firstOrFail();
        $this->assertStringStartsWith('supplier-documents/'.$supplier->id.'/', $stored->path);
        $this->assertStringNotContainsString('..', $stored->path);
    }

    public function test_directory_supports_literal_special_character_and_operational_filters(): void
    {
        $manager = $this->manager();
        $category = ItemCategory::create(['name' => 'Clinical Supplies', 'code' => 'CLIN', 'is_active' => true]);
        $matching = $this->supplier([
            'name' => 'Care 100% Medical',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'accreditation_expires_at' => today()->addDays(10),
        ]);
        $other = $this->supplier(['name' => 'Ordinary Vendor']);
        $item = $this->item('CLIN-001');
        $item->update(['category_id' => $category->id]);
        SupplierProduct::create(['supplier_id' => $matching->id, 'item_id' => $item->id]);
        SupplierContract::create([
            'supplier_id' => $matching->id, 'contract_number' => 'CTR-FILTER',
            'starts_at' => today()->subDay(), 'ends_at' => today()->addDays(10), 'status' => 'active',
        ]);

        $this->actingAs($manager)->get('/inventory/suppliers?search=%25')
            ->assertOk()->assertSee($matching->name)->assertDontSee($other->name);
        $this->actingAs($manager)->get('/inventory/suppliers?eligibility=eligible&product_category_id='.$category->id.'&expiry=within_30_days&contract=active')
            ->assertOk()->assertSee($matching->name)->assertDontSee($other->name);
    }

    public function test_directory_pagination_preserves_active_filters(): void
    {
        foreach (range(1, 16) as $index) {
            $this->supplier(['name' => sprintf('Paged Supplier %02d', $index)]);
        }

        $this->actingAs($this->manager())->get('/inventory/suppliers?status=active&sort=name&direction=desc')
            ->assertOk()
            ->assertSee('status=active', false)
            ->assertSee('sort=name', false)
            ->assertSee('direction=desc', false)
            ->assertSee('page=2', false);
    }

    public function test_future_contract_is_scheduled_and_does_not_make_a_price_current(): void
    {
        $supplier = $this->supplier();
        $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'item_id' => $this->item()->id]);
        $contract = SupplierContract::create([
            'supplier_id' => $supplier->id,
            'contract_number' => 'CTR-FUTURE',
            'starts_at' => today()->addDay(),
            'ends_at' => today()->addYear(),
            'status' => 'active',
        ]);
        $price = SupplierPrice::create([
            'supplier_product_id' => $product->id,
            'supplier_contract_id' => $contract->id,
            'unit_price' => 100,
            'currency' => 'PHP',
            'minimum_order_quantity' => 1,
            'effective_from' => today(),
        ]);

        $this->assertSame('scheduled', $contract->effectiveStatus());
        $this->assertFalse($price->isCurrent());
    }

    public function test_cancelled_orders_are_not_reported_as_open_supplier_performance(): void
    {
        $supplier = $this->supplier();
        PurchaseOrder::create([
            'po_number' => 'PO-CANCELLED-001', 'supplier_id' => $supplier->id, 'item_id' => $this->item()->id,
            'quantity' => 1, 'unit_cost' => 10, 'total_amount' => 10, 'status' => 'cancelled',
        ]);

        $this->actingAs($this->manager())->get("/inventory/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertViewHas('performance', fn (array $performance) => $performance === [
                'purchase_orders' => 1,
                'received_orders' => 0,
                'pending_orders' => 0,
            ]);
    }
}
