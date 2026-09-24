<?php

namespace Tests\Feature;

use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use App\Models\SystemRecoveryRecord;
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

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Are you sure you want to create this user account and issue an initial temporary password?');
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
            // The redesign moved the stock-posting consequence onto the receive
            // confirmation it belongs to.
            ->assertSee('Record delivery');

        $this->get(route('inventory.transfers.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Dispatch stock transfer"', false);

        $this->get(route('inventory.requisitions.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Submit store requisition"', false);

        $this->get(route('inventory.items'))
            ->assertOk()
            ->assertSee('data-confirm-title="Create inventory item"', false)
            ->assertSee('data-confirm-label="Create item"', false);
    }

    public function test_supplier_lifecycle_actions_have_specific_danger_confirmations(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $admin = User::factory()->administrator()->create();
        $supplier = \App\Models\Supplier::create([
            'name' => 'Acme Medical Supplies',
            'business_structure' => 'corporation',
            'address' => '100 Health Avenue, Manila',
            'email' => 'procurement@acme.example',
            'status' => \App\Enums\SupplierStatus::Active,
            'accreditation_status' => \App\Enums\SupplierAccreditationStatus::PendingReview,
        ]);
        \App\Models\InventoryItem::create([
            'name' => 'Syringes 5ml',
            'sku' => 'SYR-005',
            'unit' => 'box',
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee('data-confirm-title="Create draft supplier"', false);

        $this->actingAs($manager, AuthenticationContext::WEB_GUARD)
            ->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('data-confirm-title="Link catalog item"', false)
            ->assertSee('data-confirm-title="Register contract"', false);

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('data-confirm-title="Approve supplier accreditation"', false)
            ->assertSee('data-confirm-title="Reject supplier accreditation"', false)
            ->assertSee('data-confirm-title="Suspend supplier"', false)
            ->assertSee('data-confirm-variant="danger"', false);
    }

    public function test_system_recovery_actions_use_hims_dialog_without_browser_alerts(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();

        // A retryable incident, so the retry control the dialog guards is present.
        SystemRecoveryRecord::create([
            'error_id' => 'REC-CONFIRM-001',
            'module' => 'Queue',
            'failure_type' => RecoveryFailureType::QueueJob,
            'operation' => 'queue_job',
            'error_summary' => 'Connection could not be established with host smtp.hospital.local:587',
            'status' => RecoveryStatus::Failed,
            'strategy_applied' => 'queue_worker_failure',
            'is_retryable' => true,
            'retry_handler' => RecoveryRetryHandler::QueueJob,
            'retry_payload' => ['failed_job_uuid' => 'e9b1c4a7-2d63-4f80-9a15-6b3c8e0d7f42'],
        ]);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.recovery.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Clear application cache"', false)
            ->assertSee('data-confirm-title="Run recovery attempt"', false)
            ->assertDontSee('onclick="return confirm', false);

        // The confirm copy must say what the retry does not do.
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.recovery.show', SystemRecoveryRecord::where('error_id', 'REC-CONFIRM-001')->firstOrFail()))
            ->assertOk()
            ->assertSee('data-confirm-title="Run recovery attempt"', false)
            ->assertSee('does not mark the incident recovered unless the operation genuinely succeeds.', false)
            ->assertDontSee('onclick="return confirm', false);
    }

    public function test_confirmation_dialog_and_script_support_variants_and_submitter_inspection(): void
    {
        $dialog = file_get_contents(resource_path('views/layouts/partials/decision-confirmation.blade.php'));
        $this->assertStringContainsString('data-decision-icon-container', $dialog);
        $this->assertStringContainsString('data-decision-icon-danger', $dialog);

        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('submitter?.dataset?.confirmMessage', $script);
        $this->assertStringContainsString('data-confirm-destructive', $script);
        $this->assertStringContainsString('decision.variant === \'danger\'', $script);
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
