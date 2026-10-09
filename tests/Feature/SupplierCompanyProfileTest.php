<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierCompanyProfileStatus;
use App\Enums\SupplierDocumentStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierCompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_supplier_can_receive_a_vendor_administrator_invitation_only(): void
    {
        $admin = User::factory()->administrator()->create();
        $supplier = $this->supplier();

        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.portal-users.store', $supplier), [
            'first_name' => 'Ana',
            'surname' => 'Santos',
            'email' => 'ana@supplier.test',
            'role' => UserRole::VendorAdministrator->value,
        ])->assertRedirect();

        $this->post(route('inventory.suppliers.portal-users.store', $supplier), [
            'first_name' => 'Omar',
            'surname' => 'Reyes',
            'email' => 'omar@supplier.test',
            'role' => UserRole::VendorOperations->value,
        ])->assertSessionHasErrors('role');
    }

    public function test_vendor_administrator_saves_a_draft_without_overwriting_official_supplier_data(): void
    {
        [$supplier, $administrator] = $this->supplierWithAdministrator();

        $this->actingAs($administrator)->get(route('supplier.dashboard'))
            ->assertRedirect(route('supplier.company-profile.edit'));
        $this->get(route('supplier.company-profile.edit'))
            ->assertOk()
            ->assertSee('Complete Supplier Information')
            ->assertSee('Onboarding progress')
            ->assertSee('Save Draft')
            ->assertSee('Submit for Review')
            ->assertSee('name="contact_first_name"', false)
            ->assertSee('name="contact_middle_name"', false)
            ->assertSee('name="contact_surname"', false)
            ->assertDontSee('name="contact_person"', false)
            ->assertSee('data-confirm-title="Submit company profile for review?"', false)
            ->assertSee('name="payment_terms_choice"', false)
            ->assertSee('Decrease standard lead time')
            ->assertSee('Increase standard lead time')
            ->assertSee('billing_same_as_registered', false)
            ->assertSee('delivery_same_as_registered', false)
            ->assertSee('Supporting documents')
            ->assertSee('x-bind:disabled="!canSubmit', false)
            ->assertSee('x-bind:disabled="!canUpload"', false);

        $this->patch(route('supplier.company-profile.update'), $this->profilePayload(['name' => 'Proposed Legal Name']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $supplier->refresh();
        $this->assertSame('Invited Supplier', $supplier->name);
        $this->assertSame('Proposed Legal Name', $supplier->company_profile_draft['name']);
        $this->assertSame(SupplierCompanyProfileStatus::Draft, $supplier->company_profile_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SavedSupplierProfileDraft->value,
            'user_id' => $administrator->id,
            'target_id' => (string) $supplier->id,
        ]);
    }

    public function test_company_profile_normalizes_constrained_fields_and_supports_custom_payment_terms(): void
    {
        [$supplier, $administrator] = $this->supplierWithAdministrator();

        $this->actingAs($administrator)->patch(route('supplier.company-profile.update'), $this->profilePayload([
            'tax_number' => '123456789000',
            'payment_terms_choice' => 'custom',
            'payment_terms_custom' => '50% deposit, balance on delivery',
            'payment_terms' => null,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $draft = $supplier->fresh()->company_profile_draft;
        $this->assertSame('123-456-789-000', $draft['tax_number']);
        $this->assertSame('Maria Elena Santos', $draft['contact_person']);
        $this->assertSame('Maria', $draft['contact_first_name']);
        $this->assertSame('Elena', $draft['contact_middle_name']);
        $this->assertSame('Santos', $draft['contact_surname']);
        $this->assertSame('50% deposit, balance on delivery', $draft['payment_terms']);

        $this->patch(route('supplier.company-profile.update'), $this->profilePayload([
            'tax_number' => '1234567890009',
            'phone' => '09A7-555-0100',
            'standard_lead_time_days' => '99999',
        ]))->assertSessionHasErrors(['tax_number', 'phone', 'standard_lead_time_days']);
    }

    public function test_company_profile_submission_changes_request_resubmission_and_approval_are_controlled(): void
    {
        [$supplier, $administrator] = $this->supplierWithAdministrator();
        $reviewer = User::factory()->administrator()->create();
        $document = $this->document($supplier, $administrator, SupplierDocumentStatus::Verified);

        $this->actingAs($administrator)->patch(route('supplier.company-profile.submit'), $this->profilePayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $supplier->refresh();
        $this->assertSame(SupplierCompanyProfileStatus::PendingReview, $supplier->company_profile_status);
        $this->assertSame(SupplierAccreditationStatus::PendingReview, $supplier->accreditation_status);
        $this->assertSame('Invited Supplier', $supplier->name);

        $this->actingAs($administrator->fresh())->get(route('supplier.dashboard'))
            ->assertOk()
            ->assertSee('Supplier Dashboard');

        $this->actingAs($reviewer, 'admin')->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Hospital review checklist')
            ->assertSee('At least one current supporting document has been verified.')
            ->assertSee('Open Final Decision')
            ->assertDontSee('Current supplier master')
            ->assertDontSee('Edit Supplier Information')
            ->assertDontSee('Manage Lifecycle Actions')
            ->assertDontSee('Not eligible for new procurement')
            ->assertDontSee('aria-label="Supplier profile sections"', false)
            ->assertSee('id="compliance"', false)
            ->assertDontSee('Expiry alerts')
            ->assertSee('Regulated health products')
            ->assertSee('Standard lead time')
            ->assertSee('7 days')
            ->assertSee('Payment terms')
            ->assertSee('Net 30')
            ->assertSee('name="approval_notes"', false)
            ->assertSee('name="correction_notes"', false)
            ->assertSee('name="rejection_notes"', false)
            ->assertDontSee('name="decision_notes"', false);

        $this->post(route('inventory.suppliers.request-profile-changes', $supplier), [
            'correction_notes' => 'Provide the complete registered address.',
        ])->assertRedirect()->assertSessionHas('success');

        $supplier->refresh();
        $this->assertSame(SupplierCompanyProfileStatus::ChangesRequested, $supplier->company_profile_status);
        $this->assertSame('Provide the complete registered address.', $supplier->company_profile_feedback);
        $this->actingAs($administrator->fresh(), 'web')->get(route('supplier.company-profile.edit'))
            ->assertOk()
            ->assertSee('Hospital feedback')
            ->assertSee('Provide the complete registered address.');

        $this->patch(route('supplier.company-profile.submit'), $this->profilePayload([
            'address' => '100 Complete Health Avenue, Manila',
        ]))->assertRedirect();

        $this->actingAs($reviewer, 'admin')->post(route('inventory.suppliers.approve', $supplier), [
            'compliance_attested' => '1',
            'approval_notes' => 'Verified against submitted evidence.',
        ])->assertRedirect()->assertSessionHas('success');

        $supplier->refresh();
        $this->assertSame(SupplierCompanyProfileStatus::Approved, $supplier->company_profile_status);
        $this->assertSame(SupplierAccreditationStatus::Approved, $supplier->accreditation_status);
        $this->assertSame('Acme Medical Distribution Corp.', $supplier->name);
        $this->assertSame('100 Complete Health Avenue, Manila', $supplier->address);
        $this->assertNull($supplier->company_profile_draft);
        $this->assertDatabaseHas('supplier_contacts', [
            'supplier_id' => $supplier->id,
            'name' => 'Maria Elena Santos',
            'position' => 'Authorized Representative',
            'is_primary' => true,
        ]);
        $this->assertSame(SupplierDocumentStatus::Verified, $document->fresh()->verification_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApprovedSupplierProfile->value,
            'target_id' => (string) $supplier->id,
        ]);
    }

    public function test_review_checklist_guides_document_verifier_to_a_different_final_approver(): void
    {
        [$supplier, $administrator] = $this->supplierWithAdministrator();
        $reviewer = User::factory()->administrator()->create();
        $document = $this->document($supplier, $administrator, SupplierDocumentStatus::Pending);

        $this->actingAs($administrator)->patch(route('supplier.company-profile.submit'), $this->profilePayload())
            ->assertRedirect();

        $this->actingAs($reviewer, 'admin')->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Hospital review checklist')
            ->assertSee('1 current document(s) still need a verification decision.')
            ->assertSee('Go to Compliance');

        $this->patch(route('inventory.suppliers.documents.verify', [$supplier, $document]), [
            'decision' => 'verified',
        ])->assertRedirect()->assertSessionHas('success');

        $this->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Different approver')
            ->assertSee('You participated in this review. Ask a different authorized approver to record the final decision.')
            ->assertDontSee('Open Final Decision');
    }

    public function test_submission_rejects_incomplete_invalid_duplicate_and_replayed_profiles(): void
    {
        [$supplier, $administrator] = $this->supplierWithAdministrator();

        $this->actingAs($administrator)->patch(route('supplier.company-profile.submit'), [
            'name' => 'Incomplete Supplier',
            'tax_number' => '12345',
        ])->assertSessionHasErrors(['business_structure', 'tax_number', 'address', 'contact_first_name', 'contact_surname', 'email']);

        $this->patch(route('supplier.company-profile.submit'), $this->profilePayload())
            ->assertSessionHasErrors('documents');

        $duplicate = $this->supplier([
            'name' => 'Existing Legal Supplier',
            'tax_number' => '123-456-789-000',
            'identity_key' => 'tax:123456789000',
        ]);
        $this->document($supplier, $administrator, SupplierDocumentStatus::Pending);

        $this->patch(route('supplier.company-profile.submit'), $this->profilePayload())
            ->assertSessionHasErrors('tax_number');

        $duplicate->update(['identity_key' => 'tax:999999999000', 'tax_number' => '999-999-999-000']);
        $this->patch(route('supplier.company-profile.submit'), $this->profilePayload())->assertRedirect();
        $this->patch(route('supplier.company-profile.submit'), $this->profilePayload())
            ->assertSessionHasErrors('profile');
    }

    public function test_approved_supplier_amendment_preserves_verified_data_until_approval(): void
    {
        $supplier = $this->supplier([
            'name' => 'Verified Supplier Name',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'company_profile_status' => SupplierCompanyProfileStatus::Approved,
        ]);
        $administrator = User::factory()->role(UserRole::VendorAdministrator)->create(['supplier_id' => $supplier->id]);
        $reviewer = User::factory()->administrator()->create();
        $this->document($supplier, $administrator, SupplierDocumentStatus::Verified);

        $payload = $this->profilePayload(['name' => 'Amended Supplier Name']);
        $this->actingAs($administrator)->patch(route('supplier.company-profile.update'), $payload)->assertRedirect();
        $this->assertSame('Verified Supplier Name', $supplier->fresh()->name);

        $this->patch(route('supplier.company-profile.submit'), $payload)->assertRedirect();
        $supplier->refresh();
        $this->assertSame('Verified Supplier Name', $supplier->name);
        $this->assertSame(SupplierAccreditationStatus::Approved, $supplier->accreditation_status);
        $this->assertSame(SupplierCompanyProfileStatus::PendingReview, $supplier->company_profile_status);

        $this->actingAs($reviewer, 'admin')->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Proposed amendment')
            ->assertSee('Current supplier master')
            ->assertSee('aria-label="Supplier profile sections"', false)
            ->assertSee('Expiry alerts')
            ->assertSee('Approve Profile Amendment')
            ->assertSee('Reject Profile Amendment')
            ->assertSee('The current accreditation remains active.');

        $this->post(route('inventory.suppliers.approve', $supplier), [
            'compliance_attested' => '1',
        ])->assertRedirect();
        $this->assertSame('Amended Supplier Name', $supplier->fresh()->name);
    }

    public function test_rejected_amendment_preserves_approved_data_and_returns_feedback_to_the_supplier(): void
    {
        $supplier = $this->supplier([
            'name' => 'Verified Supplier Name',
            'accreditation_status' => SupplierAccreditationStatus::Approved,
            'company_profile_status' => SupplierCompanyProfileStatus::Approved,
        ]);
        $administrator = User::factory()->role(UserRole::VendorAdministrator)->create(['supplier_id' => $supplier->id]);
        $reviewer = User::factory()->administrator()->create();
        $this->document($supplier, $administrator, SupplierDocumentStatus::Verified);

        $payload = $this->profilePayload(['name' => 'Unverified Amendment']);
        $this->actingAs($administrator)->patch(route('supplier.company-profile.submit'), $payload)->assertRedirect();

        $this->actingAs($reviewer, 'admin')->post(route('inventory.suppliers.reject', $supplier), [
            'rejection_notes' => 'The legal name does not match the registration document.',
        ])->assertRedirect()->assertSessionHas('success');

        $supplier->refresh();
        $this->assertSame('Verified Supplier Name', $supplier->name);
        $this->assertSame(SupplierAccreditationStatus::Approved, $supplier->accreditation_status);
        $this->assertSame(SupplierCompanyProfileStatus::Rejected, $supplier->company_profile_status);
        $this->assertSame('Unverified Amendment', $supplier->company_profile_draft['name']);
        $this->assertSame('The legal name does not match the registration document.', $supplier->company_profile_feedback);
        $audit = AuditLog::query()->where('action', AuditAction::RejectedSupplierProfile->value)->latest('id')->firstOrFail();
        $this->assertSame('approved', $audit->new_values['accreditation_status']);
        $this->assertSame('rejected', $audit->new_values['company_profile_status']);

        $this->actingAs($administrator->fresh(), 'web')->get(route('supplier.company-profile.edit'))
            ->assertOk()
            ->assertSee('The legal name does not match the registration document.')
            ->assertSee('Unverified Amendment');
    }

    public function test_rejected_documents_do_not_count_as_submission_ready_and_the_error_is_visible(): void
    {
        [$supplier, $administrator] = $this->supplierWithAdministrator();
        $this->document($supplier, $administrator, SupplierDocumentStatus::Rejected);

        $this->actingAs($administrator)->patch(route('supplier.company-profile.submit'), $this->profilePayload())
            ->assertSessionHasErrors('documents');

        $this->get(route('supplier.company-profile.edit'))
            ->assertOk()
            ->assertSee('All current documents were rejected.')
            ->assertSee('Upload at least one current business or supplier verification document before submitting.')
            ->assertSee('x-bind:disabled="!canSubmit || true"', false);
    }

    public function test_profile_and_document_mutations_are_supplier_scoped_and_role_restricted(): void
    {
        Storage::fake('local');
        [$supplierA, $administratorA] = $this->supplierWithAdministrator();
        [$supplierB, $administratorB] = $this->supplierWithAdministrator(['name' => 'Other Supplier']);
        $operations = User::factory()->role(UserRole::VendorOperations)->create(['supplier_id' => $supplierA->id]);
        $document = $this->document($supplierB, $administratorB, SupplierDocumentStatus::Pending);
        Storage::disk('local')->put($document->path, 'test');

        $this->actingAs($operations)->get(route('supplier.company-profile.edit'))->assertForbidden();
        $this->actingAs($administratorA)->delete(route('supplier.company-profile.documents.destroy', $document))->assertForbidden();
        $this->assertDatabaseHas('supplier_documents', ['id' => $document->id]);

        $this->actingAs($administratorB)->delete(route('supplier.company-profile.documents.destroy', $document))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('supplier_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->path);
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_replace([
            'name' => 'Invited Supplier',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ], $overrides));
    }

    private function supplierWithAdministrator(array $overrides = []): array
    {
        $supplier = $this->supplier($overrides);
        $administrator = User::factory()->role(UserRole::VendorAdministrator)->create([
            'supplier_id' => $supplier->id,
            'department' => 'External Supplier',
        ]);

        return [$supplier, $administrator];
    }

    private function document(Supplier $supplier, User $uploader, SupplierDocumentStatus $status): SupplierDocument
    {
        return SupplierDocument::create([
            'supplier_id' => $supplier->id,
            'document_type' => 'Business Registration',
            'document_number' => 'REG-'.$supplier->id,
            'disk' => 'local',
            'path' => 'supplier-documents/'.$supplier->id.'/registration.pdf',
            'original_name' => 'registration.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'verification_status' => $status,
            'uploaded_by' => $uploader->id,
            'is_current' => true,
        ]);
    }

    private function profilePayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Acme Medical Distribution Corp.',
            'trade_name' => 'Acme Medical',
            'business_structure' => 'corporation',
            'tax_number' => '123-456-789-000',
            'provides_regulated_health_products' => '1',
            'address' => '100 Health Avenue, Manila',
            'billing_address' => '100 Health Avenue, Manila',
            'delivery_address' => 'Warehouse 2, Manila',
            'contact_first_name' => 'Maria',
            'contact_middle_name' => 'Elena',
            'contact_surname' => 'Santos',
            'contact_position' => 'Authorized Representative',
            'email' => 'maria@acme.test',
            'phone' => '09175550100',
            'standard_lead_time_days' => '7',
            'payment_terms' => 'Net 30',
        ], $overrides);
    }
}
