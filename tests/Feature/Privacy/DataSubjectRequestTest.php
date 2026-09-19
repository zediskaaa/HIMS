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
}
