<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackNavigationCacheProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_staff_page_responses_include_strict_cache_prevention_headers(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('Fri, 01 Jan 1990 00:00:00 GMT', $response->headers->get('Expires'));
    }

    public function test_staff_logout_clears_session_and_subsequent_back_navigation_to_protected_page_is_denied(): void
    {
        $user = User::factory()->warehouseStaff()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated(AuthenticationContext::WEB_GUARD);

        $logoutResponse = $this->post(route('logout'));

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $logoutResponse->assertRedirect(route('login'));
        $this->assertStringContainsString('no-store', (string) $logoutResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $logoutResponse->headers->get('Cache-Control'));

        // Simulate browser Back button navigating back to dashboard after logout
        $backResponse = $this->get(route('dashboard'));

        $backResponse->assertRedirect(route('login'));
        $this->assertStringContainsString('no-store', (string) $backResponse->headers->get('Cache-Control'));
    }

    public function test_admin_logout_clears_session_and_subsequent_back_navigation_to_admin_route_is_denied(): void
    {
        $admin = User::factory()->administrator()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated(AuthenticationContext::ADMIN_GUARD);

        $logoutResponse = $this->post(route('admin.logout'));

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $logoutResponse->assertRedirect(route('admin.login'));
        $this->assertStringContainsString('no-store', (string) $logoutResponse->headers->get('Cache-Control'));

        // Simulate browser Back button navigating back to admin users after logout
        $backResponse = $this->get(route('admin.users.index'));

        $backResponse->assertRedirect(route('admin.login'));
        $this->assertStringContainsString('no-store', (string) $backResponse->headers->get('Cache-Control'));
    }

    public function test_super_admin_logout_clears_session_and_subsequent_back_navigation_to_super_admin_dashboard_is_denied(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('super-admin.login'), [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertRedirect(route('super-admin.dashboard', absolute: false));

        $this->assertAuthenticated(AuthenticationContext::SUPER_ADMIN_GUARD);

        $logoutResponse = $this->post(route('super-admin.logout'));

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
        $logoutResponse->assertRedirect(route('super-admin.login'));
        $this->assertStringContainsString('no-store', (string) $logoutResponse->headers->get('Cache-Control'));

        // Simulate browser Back button navigating back to super-admin dashboard after logout
        $backResponse = $this->get(route('super-admin.dashboard'));

        $backResponse->assertRedirect(route('super-admin.login'));
        $this->assertStringContainsString('no-store', (string) $backResponse->headers->get('Cache-Control'));
    }

    public function test_unauthenticated_request_to_protected_routes_returns_no_cache_headers_on_redirect(): void
    {
        $superAdminRoute = $this->get(route('super-admin.dashboard'));
        $superAdminRoute->assertRedirect(route('super-admin.login'));
        $this->assertStringContainsString('no-store', (string) $superAdminRoute->headers->get('Cache-Control'));

        $adminRoute = $this->get(route('admin.users.index'));
        $adminRoute->assertRedirect(route('admin.login'));
        $this->assertStringContainsString('no-store', (string) $adminRoute->headers->get('Cache-Control'));

        $staffRoute = $this->get(route('dashboard'));
        $staffRoute->assertRedirect(route('login'));
        $this->assertStringContainsString('no-store', (string) $staffRoute->headers->get('Cache-Control'));
    }

    public function test_public_routes_remain_accessible_without_forced_redirects(): void
    {
        $this->get('/')->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('admin.login'))->assertOk();
        $this->get(route('super-admin.login'))->assertOk();
    }

    public function test_active_authenticated_session_navigates_normally_between_protected_routes(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_session_inactivity_expiration_responses_include_no_store_headers(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => now()->subMinutes(5)->getTimestamp(),
            ])
            ->get(\Illuminate\Support\Facades\URL::signedRoute('session.expired', absolute: false));

        $response->assertRedirect(route('login'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));

        $this->assertGuest();
    }
}
