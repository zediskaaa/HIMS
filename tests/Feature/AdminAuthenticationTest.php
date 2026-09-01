<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create([
            'password' => bcrypt('password'),
        ]);
    }

    private function login(User $admin): void
    {
        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_admin_login_page_matches_the_super_admin_design(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Admin Sign in')
            ->assertSee('Restricted administration portal')
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Keep me signed in')
            ->assertSee('Sign in as Admin');
    }

    public function test_admin_can_login_only_through_the_dedicated_admin_guard(): void
    {
        $admin = $this->admin();

        $this->login($admin);

        $this->assertAuthenticatedAs($admin, AuthenticationContext::ADMIN_GUARD);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
        $this->assertIsInt(session(EnforceSessionInactivity::lastActivityKey(
            AuthenticationContext::ADMIN_GUARD
        )));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('Admin Panel');
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = User::factory()->administrator()->inactive()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_staff_and_super_admin_cannot_authenticate_through_admin_login(): void
    {
        $wrongRoles = [
            User::factory()->warehouseStaff()->create(['password' => bcrypt('password')]),
            User::factory()->superAdministrator()->create(['password' => bcrypt('password')]),
        ];

        foreach ($wrongRoles as $user) {
            $this->post(route('admin.login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

            $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        }
    }

    public function test_staff_login_accepts_staff_but_rejects_both_administrative_roles(): void
    {
        $staff = User::factory()->pharmacyStaff()->create(['password' => bcrypt('password')]);

        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($staff, AuthenticationContext::WEB_GUARD);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Staff Dashboard')
            ->assertSee('Staff Panel');

        $this->post(route('logout'));

        foreach ([$this->admin(), User::factory()->superAdministrator()->create()] as $user) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

            $this->assertGuest(AuthenticationContext::WEB_GUARD);
        }
    }

    public function test_admin_and_staff_cannot_authenticate_through_super_admin_login(): void
    {
        foreach ([$this->admin(), User::factory()->viewer()->create()] as $user) {
            $this->post(route('super-admin.login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

            $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
        }
    }

    public function test_admin_can_use_admin_modules_but_cannot_view_audit_trail(): void
    {
        $admin = $this->admin();
        $this->login($admin);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User Management');
        $this->get(route('admin.permissions'))->assertOk();
        $this->get(route('admin.audit-logs.index'))->assertForbidden();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('User Management')
            ->assertDontSee('Audit Trail');
    }

    public function test_staff_cannot_open_admin_super_admin_or_audit_routes(): void
    {
        $staff = User::factory()->warehouseStaff()->create();

        $this->actingAs($staff, AuthenticationContext::WEB_GUARD)
            ->get(route('admin.users.index'))
            ->assertForbidden();
        $this->get(route('admin.permissions'))->assertForbidden();
        $this->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_admin_cannot_open_super_admin_routes(): void
    {
        $admin = $this->admin();
        $this->login($admin);

        $this->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_super_admin_retains_panel_and_audit_access(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertSee('Super Admin Dashboard')
            ->assertSee('Audit Trail');

        $this->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Trail');
    }

    public function test_admin_actions_are_logged_even_though_admin_cannot_view_logs(): void
    {
        $admin = $this->admin();
        $this->login($admin);

        $this->post(route('admin.users.store'), [
            'surname' => 'Worker',
            'first_name' => 'New',
            'email' => 'new.worker@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Internal Audit',
            'phone' => '09171234567',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->getKey(),
            'action' => AuditAction::CreatedUser->value,
        ]);
        $this->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_admin_logout_uses_only_the_admin_guard(): void
    {
        $admin = $this->admin();
        $this->login($admin);

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_admin_inactivity_expires_back_to_admin_login(): void
    {
        config()->set('session.lifetime', 4);
        $admin = $this->admin();
        $this->login($admin);
        $this->travel(4)->minutes();

        $this->get(route('dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('session_timeout', true);

        $this->app['auth']->forgetGuards();
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_admin_session_can_use_stateful_browser_api_routes(): void
    {
        $admin = $this->admin();
        $this->login($admin);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Referer', config('app.url'))
            ->getJson('/api/v1/demand-plans')
            ->assertOk();
    }

    public function test_role_changes_end_the_old_panel_session_immediately(): void
    {
        $admin = $this->admin();
        $this->login($admin);
        $admin->forceFill(['role' => UserRole::Viewer])->saveQuietly();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->app['auth']->forgetGuards();
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);

        $staff = User::factory()->viewer()->create(['password' => bcrypt('password')]);
        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $staff->forceFill(['role' => UserRole::Administrator])->saveQuietly();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->app['auth']->forgetGuards();
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }
}
