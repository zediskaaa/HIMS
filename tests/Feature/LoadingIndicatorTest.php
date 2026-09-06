<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoadingIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_login_panel_has_the_shared_overlay_and_immediate_button_state(): void
    {
        foreach (['login', 'admin.login', 'super-admin.login'] as $route) {
            $response = $this->get(route($route));

            $response
                ->assertOk()
                ->assertSee('data-hims-loading-overlay', false)
                ->assertSee('aria-hidden="true"', false)
                ->assertSee('aria-live="polite"', false)
                ->assertSee('data-loading-text="Signing in..."', false);

            $this->assertMatchesRegularExpression(
                '/<div\s+data-hims-loading-overlay(?=[^>]*\saria-hidden="true")(?=[^>]*\shidden(?:\s|>))[^>]*>/s',
                $response->getContent(),
            );
        }
    }

    public function test_login_mfa_actions_have_specific_loading_messages(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create([
            'password' => bcrypt('password'),
            'mfa_enabled' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.mfa'));
        Notification::assertSentTo($admin, LoginMfaOtp::class);

        $this->get(route('admin.login.mfa'))
            ->assertOk()
            ->assertSee('data-hims-loading-overlay', false)
            ->assertSee('data-loading-text="Verifying..."', false)
            ->assertSee('data-loading-text="Sending..."', false);
    }

    public function test_every_authenticated_panel_uses_the_shared_hidden_overlay(): void
    {
        $panels = [
            [User::factory()->warehouseStaff()->create(), AuthenticationContext::WEB_GUARD, 'dashboard'],
            [User::factory()->administrator()->create(), AuthenticationContext::ADMIN_GUARD, 'dashboard'],
            [User::factory()->superAdministrator()->create(), AuthenticationContext::SUPER_ADMIN_GUARD, 'super-admin.dashboard'],
        ];

        foreach ($panels as [$user, $guard, $route]) {
            $this->actingAs($user, $guard)
                ->get(route($route))
                ->assertOk()
                ->assertSee('data-hims-loading-overlay', false)
                ->assertSee('aria-hidden="true"', false);

            $this->app['auth']->guard($guard)->logout();
        }
    }

    public function test_account_settings_forms_have_accessible_specific_loading_states(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-hims-loading-overlay', false)
            ->assertSee('data-loading-text="Saving profile..."', false)
            ->assertSee('data-loading-text="Updating password..."', false)
            ->assertSee('data-loading-text="Saving MFA setting..."', false);
    }

    public function test_user_management_and_audit_actions_have_contextual_loading_states(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->warehouseStaff()->create();

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-loading-text="Loading accounts..."', false)
            ->assertSee('data-loading-text="Updating account..."', false);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('data-loading-text="Creating account..."', false);

        $superAdmin = User::factory()->superAdministrator()->create();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('data-loading-text="Loading activity..."', false)
            ->assertSee('Finding suggestions...')
            ->assertSee('loader loader--sm', false);
    }

    public function test_foreground_api_pages_render_inline_loading_statuses(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        foreach ([
            [route('inventory.items'), 'inventory-api-status'],
            [route('inventory.suppliers'), 'suppliers-api-status'],
            [route('inventory.storage-locations'), 'locations-api-status'],
            [route('inventory.alerts'), 'alerts-api-status'],
            [route('inventory.purchases'), 'purchase-orders-api-status'],
        ] as [$url, $statusId]) {
            $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
                ->get($url)
                ->assertOk()
                ->assertSee('id="'.$statusId.'"', false)
                ->assertSee('loader loader--sm', false);
        }
    }
}
