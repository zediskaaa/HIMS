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

    public function test_page_shells_bootstrap_cross_document_loading_before_rendering_the_overlay(): void
    {
        foreach ([route('login'), route('privacy.notice'), route('terms'), url('/')] as $url) {
            $content = $this->get($url)->assertOk()->getContent();

            $statePosition = strpos($content, "sessionStorage.getItem(storageKey)");
            $overlayPosition = strpos($content, 'data-hims-loading-overlay');

            $this->assertNotFalse($statePosition);
            $this->assertNotFalse($overlayPosition);
            $this->assertLessThan($overlayPosition, $statePosition);
            $this->assertSame(1, preg_match_all('/<div\s+data-hims-loading-overlay\b/', $content));
        }
    }

    public function test_navigation_loader_waits_for_destination_load_instead_of_outgoing_page_timeouts(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("window.addEventListener('load', revealDestination, { once: true })", $script);
        $this->assertStringContainsString("window.sessionStorage.setItem(navigationStorageKey, '1')", $script);
        $this->assertStringContainsString('rememberPageTransition({ coverCurrentPage: false })', $script);
        $this->assertStringNotContainsString('navigationWatchdog', $script);
        $this->assertStringNotContainsString("window.addEventListener('pagehide', reset)", $script);
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

        // Alerts renders nothing server-side, so its loader is the only
        // progress signal until the fetch resolves.
        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.alerts'))
            ->assertOk()
            ->assertSee('id="alerts-api-status"', false)
            ->assertSee('loader loader--sm', false);

        // Inventory items are server-rendered so the catalog, its reorder
        // status, and supplier visibility come from one authorized response.
        // The foreground fetch replaced the full catalog with a single
        // paginated page of itself, so items vanished once loading finished.
        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.items'))
            ->assertOk()
            ->assertSee('Inventory Items Catalog')
            ->assertDontSee('inventory-api-status');

        // Storage locations are server-rendered as well. The loader that used
        // to sit above the registry was left behind by that migration and had
        // no fetch behind it, so it spun forever.
        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.storage-locations'))
            ->assertOk()
            ->assertSee('Location registry')
            ->assertDontSee('locations-api-status');

        // Purchase orders are server-rendered so status, authorization, and
        // financial visibility come from one trusted response.
        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.purchases'))
            ->assertOk()
            ->assertSee('id="purchase-orders"', false)
            ->assertDontSee('purchase-orders-api-status');

        // Supplier Management is now server-rendered because its compliance
        // and authorization state cannot be safely reconstructed by the old
        // foreground CRUD fetch.
        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee('Supplier directory')
            ->assertSee('data-loading-text="Creating supplier..."', false);
    }
}
