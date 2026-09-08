<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authenticated_role_has_the_shared_manual_logout_confirmation(): void
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
                ->assertSee('data-decision-confirmation', false)
                ->assertSee('aria-modal="true"', false)
                ->assertSee('data-manual-logout', false)
                ->assertSee('data-confirm-message="Are you sure you want to log out?"', false)
                ->assertSee('data-confirm-label="Log Out"', false);

            $this->app['auth']->guard($guard)->logout();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_mfa_confirmation_is_available_only_to_admin_roles(): void
    {
        $staff = User::factory()->warehouseStaff()->create();
        $this->actingAs($staff, AuthenticationContext::WEB_GUARD)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('data-confirm-mfa', false);

        $this->app['auth']->guard(AuthenticationContext::WEB_GUARD)->logout();
        $this->app['auth']->forgetGuards();

        foreach ([
            [User::factory()->administrator()->create(), AuthenticationContext::ADMIN_GUARD],
            [User::factory()->superAdministrator()->create(), AuthenticationContext::SUPER_ADMIN_GUARD],
        ] as [$user, $guard]) {
            $this->actingAs($user, $guard)
                ->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('data-confirm-mfa', false)
                ->assertSee('Saving MFA setting...', false);

            $this->app['auth']->guard($guard)->logout();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_sensitive_profile_and_user_management_changes_opt_in_to_confirmation(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->warehouseStaff()->create();
        User::factory()->warehouseStaff()->inactive()->create();

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-confirm-email-change', false)
            ->assertSee('Are you sure you want to change your password?');

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Are you sure you want to deactivate this user?')
            ->assertSee('Are you sure you want to reactivate this user?');

        $account = User::factory()->warehouseStaff()->create();
        $this->get(route('admin.users.edit', $account))
            ->assertOk()
            ->assertSee('Are you sure you want to save these account changes?');
    }

    public function test_inventory_commits_that_change_stock_or_workflow_require_confirmation(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.stock-movements'))
            ->assertOk()
            ->assertSee('Are you sure you want to record this stock movement?');

        $this->get(route('inventory.adjustments'))
            ->assertOk()
            ->assertSee('Are you sure you want to apply this stock adjustment?');

        $this->get(route('inventory.purchases'))
            ->assertOk()
            ->assertSee('Are you sure you want to approve this procurement request?')
            ->assertSee('This will add the ordered stock to inventory.');
    }

    public function test_confirmation_gate_runs_before_session_and_loading_submission_handlers(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('event.preventDefault();', $script);
        $this->assertStringContainsString('event.defaultPrevented', $script);
        $this->assertStringContainsString('const confirmedForms = new WeakSet();', $script);
        $this->assertStringContainsString('form.requestSubmit(submitter ?? undefined);', $script);
        $this->assertLessThan(
            strpos($script, 'startSessionMonitor();'),
            strpos($script, 'startDecisionConfirmations();'),
        );
        $this->assertLessThan(
            strpos($script, 'startLoadingIndicators();'),
            strpos($script, 'startDecisionConfirmations();'),
        );
    }
}
