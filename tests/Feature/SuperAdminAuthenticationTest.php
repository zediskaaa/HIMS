<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdministrator()->create([
            'password' => bcrypt('password'),
        ]);
    }

    private function login(User $user): void
    {
        $this->post(route('super-admin.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('super-admin.dashboard', absolute: false));
    }

    public function test_super_admin_login_page_has_a_distinct_privileged_identity(): void
    {
        $this->get(route('super-admin.login'))
            ->assertOk()
            ->assertSee('Super Admin Login')
            ->assertSee('Privileged system access')
            ->assertSee('System-wide governance, secured at the highest level.')
            ->assertSee('Highest privilege tier')
            ->assertSee('Access governance')
            ->assertSee('Security control')
            ->assertSee('Audit oversight')
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Keep me signed in')
            ->assertSee('Sign in as Super Admin')
            ->assertDontSee('Access is limited to the administration modules assigned to your account.');
    }

    public function test_valid_super_admin_credentials_use_the_dedicated_guard(): void
    {
        $superAdmin = $this->superAdmin();

        $this->login($superAdmin);

        $this->assertAuthenticatedAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertIsInt(session(EnforceSessionInactivity::lastActivityKey(
            AuthenticationContext::SUPER_ADMIN_GUARD
        )));
    }

    public function test_invalid_credentials_are_rejected_without_changing_the_message(): void
    {
        $superAdmin = $this->superAdmin();

        $this->from(route('super-admin.login'))->post(route('super-admin.login'), [
            'email' => $superAdmin->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('super-admin.login'))
            ->assertSessionHasErrors([
                'email' => 'Incorrect email or password. You have 4 attempts remaining.',
            ]);

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_administrator_credentials_cannot_enter_the_super_admin_guard(): void
    {
        $administrator = User::factory()->administrator()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('super-admin.login'), [
            'email' => $administrator->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_inactive_super_admin_credentials_are_rejected(): void
    {
        $superAdmin = User::factory()->superAdministrator()->inactive()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('super-admin.login'), [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_super_admin_credentials_are_reserved_for_the_separate_login(): void
    {
        $superAdmin = $this->superAdmin();

        $this->post(route('login'), [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_unauthenticated_user_is_redirected_to_super_admin_login(): void
    {
        $this->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_standard_admin_session_cannot_open_the_super_admin_panel(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator, AuthenticationContext::WEB_GUARD)
            ->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_role_middleware_rejects_a_non_super_admin_in_the_dedicated_guard(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_panel_and_shared_modules_match_admin_access(): void
    {
        $superAdmin = $this->superAdmin();
        $this->login($superAdmin);

        $this->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertSee('Super Admin Dashboard')
            ->assertSee('User Management')
            ->assertSee('Access Control')
            ->assertSee('Audit Trail');

        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.permissions'))->assertOk();
        $this->get(route('admin.audit-logs.index'))->assertOk();
        $this->get(route('inventory.items'))->assertOk();
        $this->get(route('inventory.purchases'))->assertOk();
        $this->get(route('inventory.reports'))->assertOk();
    }

    public function test_super_admin_can_perform_the_same_user_management_actions_as_admin(): void
    {
        $superAdmin = $this->superAdmin();
        $this->login($superAdmin);
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Create Account')
            ->assertSee('value="administrator"', false)
            ->assertDontSee('value="super_administrator"', false);

        $this->post(route('admin.users.store'), [
            'surname' => 'Administrator',
            'first_name' => 'Vera',
            'email' => 'vera.administrator@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Administrator->value,
            'department' => 'Administration',
            'phone' => '09179876543',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('account_created_success', 'Account created successfully.')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'vera.administrator@example.com',
            'role' => UserRole::Administrator->value,
        ]);

        $created = User::query()->where('email', 'vera.administrator@example.com')->firstOrFail();

        $this->assertNotSame('Password123!', $created->password);
        $this->assertTrue(password_verify('Password123!', $created->password));

        $this->postJson(route('super-admin.session.activity'))->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.users.index', ['search' => $created->email]))
            ->assertOk()
            ->assertSee('Account created successfully.')
            ->assertSee($created->name)
            ->assertSessionMissing('account_created_success');

        $this->get(route('admin.users.index', ['search' => $created->email]))
            ->assertOk()
            ->assertDontSee('Account created successfully.');
    }

    public function test_super_admin_session_can_use_stateful_browser_api_routes(): void
    {
        $superAdmin = $this->superAdmin();
        $this->login($superAdmin);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Referer', config('app.url'))
            ->getJson('/api/v1/demand-plans')
            ->assertOk();
    }

    public function test_super_admin_logout_only_uses_the_dedicated_route(): void
    {
        $superAdmin = $this->superAdmin();
        $this->login($superAdmin);

        $this->post(route('super-admin.logout'))
            ->assertRedirect(route('super-admin.login'))
            ->assertSessionMissing('session_timeout');

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
        $this->get(route('super-admin.login'))
            ->assertDontSee('Your session has expired due to inactivity. Please log in again.');
    }

    public function test_super_admin_inactivity_uses_the_existing_four_minute_policy(): void
    {
        $superAdmin = $this->superAdmin();
        $this->login($superAdmin);
        $this->travel(4)->minutes();

        $this->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'))
            ->assertSessionHas('session_timeout', true);

        $this->get(route('super-admin.login'))
            ->assertSee('Session Timeout')
            ->assertSee('Your session has expired due to inactivity. Please log in again.');

        $this->app['auth']->forgetGuards();
        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_remember_me_cannot_restore_an_expired_super_admin_session(): void
    {
        $superAdmin = $this->superAdmin();
        $guard = $this->app['auth']->guard(AuthenticationContext::SUPER_ADMIN_GUARD);
        $recallerName = $guard->getRecallerName();

        $loginResponse = $this->post(route('super-admin.login'), [
            'email' => $superAdmin->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $recallerCookie = $loginResponse->getCookie($recallerName);
        $this->assertNotNull($recallerCookie);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withCookie($recallerName, $recallerCookie->getValue())
            ->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'))
            ->assertSessionHas('session_timeout', true);

        $this->app['auth']->forgetGuards();
        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_existing_admin_cannot_create_a_super_admin(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->post(route('admin.users.store'), [
            'surname' => 'Root',
            'first_name' => 'Sofia',
            'email' => 'sofia.root@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::SuperAdministrator->value,
            'department' => 'Administration',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'sofia.root@example.com',
        ]);
    }
}
