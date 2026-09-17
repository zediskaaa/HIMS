<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;
    public function test_privacy_notice_page_is_publicly_accessible(): void
    {
        $response = $this->get(route('privacy.notice'));

        $response->assertOk();
        $response->assertSee('System Privacy Notice');
        $response->assertSee('Republic Act No. 10173');
        $response->assertSee('Data Controller Information');
        $response->assertSee('Lawful Basis for Processing');
        $response->assertSee('hims-session');
        $response->assertSee('hims_inactivity');
        $response->assertSee('Immutable Audit Trail');
    }

    public function test_terms_of_use_page_is_publicly_accessible(): void
    {
        $response = $this->get(route('terms'));

        $response->assertOk();
        $response->assertSee('Terms of Use');
        $response->assertSee('Authorized Access &amp; Credential Responsibility', false);
        $response->assertSee('System Monitoring &amp; Audit Logging Notice', false);
        $response->assertSee('Administrative and Legal Notice');
    }

    public function test_privacy_and_terms_aliases_redirect_properly(): void
    {
        $this->get('/privacy-policy')->assertRedirect(route('privacy.notice'));
        $this->get('/privacy')->assertRedirect(route('privacy.notice'));
        $this->get('/terms-and-conditions')->assertRedirect(route('terms'));
        $this->get('/terms')->assertRedirect(route('terms'));
    }

    public function test_guest_login_page_renders_privacy_and_terms_links(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('privacy.notice'));
        $response->assertSee(route('terms'));
        $response->assertSee('Privacy Notice');
        $response->assertSee('Terms of Use');
    }

    public function test_admin_login_page_renders_privacy_and_terms_links(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertSee(route('privacy.notice'));
        $response->assertSee(route('terms'));
    }

    public function test_super_admin_login_page_renders_privacy_and_terms_links(): void
    {
        $response = $this->get(route('super-admin.login'));

        $response->assertOk();
        $response->assertSee(route('privacy.notice'));
        $response->assertSee(route('terms'));
    }

    public function test_landing_page_renders_privacy_and_terms_links(): void
    {
        $response = $this->get(url('/'));

        $response->assertOk();
        $response->assertSee(route('privacy.notice'));
        $response->assertSee(route('terms'));
    }

    public function test_registration_page_renders_privacy_and_terms_links(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee(route('privacy.notice'));
        $response->assertSee(route('terms'));
    }

    public function test_admin_user_create_page_renders_privacy_notice_reference(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => \App\Enums\UserRole::Administrator,
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee(route('privacy.notice'));
        $response->assertSee('Privacy Notice');
        $response->assertSee('return=' . urlencode(route('admin.users.create')));
    }

    public function test_privacy_notice_shows_contextual_back_button_when_opened_from_user_creation(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => \App\Enums\UserRole::Administrator,
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $createUrl = route('admin.users.create');
        $response = $this->actingAs($admin)->get(route('privacy.notice', ['return' => $createUrl]));

        $response->assertOk();
        $response->assertSee('Back to Create User');
        $response->assertSee($createUrl);
        $response->assertSee('Admin Panel');
        $response->assertSee(route('admin.users.index'));
        $response->assertDontSee('Return to Home');
    }

    public function test_terms_shows_contextual_back_button_when_opened_from_user_creation(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => \App\Enums\UserRole::Administrator,
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $createUrl = route('admin.users.create');
        $response = $this->actingAs($admin)->get(route('terms', ['return' => $createUrl]));

        $response->assertOk();
        $response->assertSee('Back to Create User');
        $response->assertSee($createUrl);
        $response->assertSee('Admin Panel');
        $response->assertSee(route('admin.users.index'));
        $response->assertDontSee('Return to Home');
    }

    public function test_switching_between_legal_pages_preserves_return_parameter(): void
    {
        $createUrl = route('admin.users.create');
        $response = $this->get(route('privacy.notice', ['return' => $createUrl]));

        $response->assertOk();
        $response->assertSee(route('terms', ['return' => $createUrl]));
    }

    public function test_authenticated_staff_sees_dashboard_portal_link(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create([
            'role' => \App\Enums\UserRole::WarehouseStaff,
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $response = $this->actingAs($staff)->get(route('privacy.notice'));

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee(route('dashboard'));
        $response->assertDontSee('Staff Portal');
    }
}
