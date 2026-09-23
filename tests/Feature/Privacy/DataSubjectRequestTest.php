<?php

namespace Tests\Feature\Privacy;

use App\Enums\UserRole;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataSubjectRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_submit_data_subject_request(): void
    {
        $this->post(route('privacy.requests.store'), [
            'request_type' => 'access',
            'details' => 'I would like an export of my account data.',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_submit_data_subject_request(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('privacy.requests.store'), [
                'request_type' => 'access',
                'details' => 'Please provide an export of all my profile and activity records under RA 10173.',
            ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('privacy_requests', [
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
        ]);

        $request = PrivacyRequest::where('user_id', $user->id)->first();
        $this->assertNotNull($request);
        $this->assertStringStartsWith('DSR-', $request->ticket_number);
    }

    public function test_dsr_validation_rejects_invalid_type_or_short_description(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('privacy.requests.store'), [
                'request_type' => 'invalid_category',
                'details' => 'short',
            ]);

        $response->assertSessionHasErrors(['request_type', 'details']);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('x-init="$nextTick(() => { $dispatch(\'open-modal\', \'submit-privacy-request\') })"', false);
    }

    public function test_staff_without_permission_cannot_access_governance_panel(): void
    {
        $staff = User::factory()->pharmacyStaff()->create();

        $this->actingAs($staff)
            ->get(route('admin.privacy.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_governance_panel_and_view_dsr(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260920-9999',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Formal access request for personal data portability.',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.privacy.index', ['tab' => 'dsr']));

        $response->assertOk();
        $response->assertSee('#DSR-20260920-9999');
        $response->assertSee('Formal access request');
    }

    public function test_super_admin_can_fulfill_dsr(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260920-1111',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Access request.',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.privacy.requests.fulfill', $dsr), [
                'resolution_notes' => 'Export generated and provided to data subject.',
            ]);

        $response->assertSessionHas('status');

        $dsr->refresh();
        $this->assertSame('fulfilled', $dsr->status);
        $this->assertSame($admin->id, $dsr->resolved_by);
        $this->assertNotNull($dsr->resolved_at);
        $this->assertSame('Export generated and provided to data subject.', $dsr->resolution_notes);
    }

    public function test_super_admin_cannot_reject_dsr_without_statutory_reason(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260920-2222',
            'user_id' => $user->id,
            'request_type' => 'erasure',
            'status' => 'pending',
            'details' => 'Please erase all my inventory records.',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.privacy.requests.reject', $dsr), [
                'reason' => 'No', // Too short
            ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_super_admin_can_reject_dsr_with_valid_statutory_reason(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260920-3333',
            'user_id' => $user->id,
            'request_type' => 'erasure',
            'status' => 'pending',
            'details' => 'Please erase all my historical transaction entries.',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.privacy.requests.reject', $dsr), [
                'reason' => 'Refused pursuant to National Archives of the Philippines (NAP) GRDS-9 and COA Circular No. 2012-001 requiring 10-year preservation of public hospital pharmaceutical supply records.',
            ]);

        $response->assertSessionHas('status');

        $dsr->refresh();
        $this->assertSame('rejected', $dsr->status);
        $this->assertStringContainsString('National Archives', (string) $dsr->resolution_notes);
    }

    public function test_portable_personal_data_export_returns_valid_json(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create([
            'name' => 'Nurse Maria Santos',
            'email' => 'maria.santos@hospital.gov.ph',
            'employee_id' => 'EMP-NUR-7788',
        ]);

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260920-4444',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Data export request.',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.privacy.requests.export', $dsr));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $data = json_decode($response->streamedContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Nurse Maria Santos', $data['account_profile']['name']);
        $this->assertSame('EMP-NUR-7788', $data['account_profile']['employee_id']);
        $this->assertSame('maria.santos@hospital.gov.ph', $data['account_profile']['email']);
        // Crucial security check: passwords and hashes must never be exposed
        $this->assertArrayNotHasKey('password', $data['account_profile']);
        $this->assertArrayNotHasKey('two_factor_secret', $data['account_profile']);
    }

    public function test_dpo_approval_triggers_full_fulfillment_and_generates_zip_package(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create([
            'name' => 'Pharmacist Juan Dela Cruz',
            'email' => 'juan.delacruz@hospital.gov.ph',
            'employee_id' => 'EMP-PHARM-1001',
        ]);

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260924-8888',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Full access and data portability export request.',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.privacy.requests.approve', $dsr), [
                'resolution_notes' => 'Approved by DPO. Disclosure authorized under RA 10173 Section 16(c).',
            ]);

        $response->assertSessionHas('status');

        $dsr->refresh();
        $this->assertSame('fulfilled', $dsr->status);
        $this->assertNotNull($dsr->approved_at);
        $this->assertSame($admin->id, $dsr->approved_by_user_id);
        $this->assertNotNull($dsr->fulfilled_at);
        $this->assertNotNull($dsr->package_filename);
        $this->assertNotNull($dsr->package_path);
        $this->assertNotNull($dsr->package_hash);
        $this->assertGreaterThan(0, $dsr->package_size_bytes);
        $this->assertTrue($dsr->isDownloadable());

        // Verify package file exists on private storage disk
        $fullPath = storage_path("app/private/{$dsr->package_path}");
        $this->assertFileExists($fullPath);

        // Verify ZIP contents
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($fullPath));
        $this->assertNotFalse($zip->locateName('account-profile-and-roles.json'));
        $this->assertNotFalse($zip->locateName('activity-metadata.csv'));
        $this->assertNotFalse($zip->locateName('data-access-and-processing-transparency-report.pdf'));
        $this->assertNotFalse($zip->locateName('dpo-resolution.pdf'));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('README.txt'));

        // Inspect JSON artifact
        $jsonContent = $zip->getFromName('account-profile-and-roles.json');
        $this->assertIsString($jsonContent);
        $jsonData = json_decode($jsonContent, true);
        $this->assertSame('Pharmacist Juan Dela Cruz', $jsonData['data_subject']['name']);
        $this->assertSame('EMP-PHARM-1001', $jsonData['data_subject']['employee_id']);
        $this->assertArrayNotHasKey('password', $jsonData['account_profile']['personal_identity']);
        $this->assertArrayNotHasKey('authenticator_secret', $jsonData['account_profile']['security_settings']);

        // Inspect Manifest and Checksums
        $manifestContent = $zip->getFromName('manifest.json');
        $this->assertIsString($manifestContent);
        $manifest = json_decode($manifestContent, true);
        $this->assertSame("HIMS-DSAR-{$dsr->ticket_number}.zip", $manifest['package_reference']);
        $this->assertCount(5, $manifest['files']);

        $zip->close();
    }

    public function test_activity_metadata_redacts_patient_identifying_information(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        // Create an audit log record with patient info
        \App\Models\AuditLog::create([
            'event_id' => 'EVT-TEST-001',
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_employee_id' => $user->employee_id,
            'actor_role' => $user->role->value,
            'action' => \App\Enums\AuditAction::CreatedMaterialRequisition,
            'event_category' => 'Store Requisitions',
            'module' => 'Store Requisitions',
            'description' => 'Issued 50 ampoules Paracetamol for Patient ID: PAT-99944 at Ward 3.',
            'target_name' => 'Patient: John Doe Room 102',
            'business_reason' => 'Emergency clinical requisition for patient MRN: 888771.',
            'outcome' => 'success',
            'occurred_at_utc' => now()->toIso8601String(),
        ]);

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260924-7777',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Export my activity records.',
        ]);

        $this->actingAs($admin)->post(route('admin.privacy.requests.approve', $dsr));

        $dsr->refresh();
        $fullPath = storage_path("app/private/{$dsr->package_path}");
        $zip = new \ZipArchive();
        $zip->open($fullPath);
        $csvContent = $zip->getFromName('activity-metadata.csv');
        $zip->close();

        $this->assertStringContainsString('[REDACTED - PATIENT PRIVACY]', $csvContent);
        $this->assertStringNotContainsString('PAT-99944', $csvContent);
        $this->assertStringNotContainsString('888771', $csvContent);
    }

    public function test_authenticated_user_can_download_their_own_fulfilled_package(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260924-6666',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Access and portability request.',
        ]);

        // Fulfill the request
        $this->actingAs($admin)->post(route('admin.privacy.requests.approve', $dsr));
        $dsr->refresh();

        // User downloads their own package
        $response = $this->actingAs($user)
            ->get(route('privacy.requests.download', $dsr));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));

        $dsr->refresh();
        $this->assertSame(1, $dsr->download_count);
        $this->assertNotNull($dsr->last_downloaded_at);

        // Verify audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => \App\Enums\AuditAction::DownloadedPrivacyPackage->value,
            'target_id' => (string) $dsr->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthorized_user_cannot_download_another_users_package(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260924-5555',
            'user_id' => $userA->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Data access request.',
        ]);

        $this->actingAs($admin)->post(route('admin.privacy.requests.approve', $dsr));
        $dsr->refresh();

        // User B tries to download User A's package
        $response = $this->actingAs($userB)
            ->get(route('privacy.requests.download', $dsr));

        $response->assertForbidden();

        $dsr->refresh();
        $this->assertSame(0, $dsr->download_count);
    }

    public function test_expired_package_cannot_be_downloaded(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();

        $dsr = PrivacyRequest::create([
            'ticket_number' => 'DSR-20260924-4444',
            'user_id' => $user->id,
            'request_type' => 'access',
            'status' => 'pending',
            'details' => 'Data access request.',
        ]);

        $this->actingAs($admin)->post(route('admin.privacy.requests.approve', $dsr));
        $dsr->refresh();

        // Force expiration
        $dsr->update(['package_expires_at' => now()->subDay()]);

        $response = $this->actingAs($user)
            ->get(route('privacy.requests.download', $dsr));

        $response->assertStatus(410);
    }
}

