<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuthenticationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create([
            'name' => 'Juan Dela Cruz',
            'employee_id' => 'EMP-9000',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validUserPayload(array $overrides = []): array
    {
        return array_replace([
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'email' => 'maria.santos@djnrmhs.test',
            'phone' => '09123456789',
            'department' => 'Pharmacy',
            'role' => UserRole::PharmacyStaff->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function auditLog(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_replace([
            'actor_name' => 'Audit Operator',
            'action' => AuditAction::LoggedIn,
            'target_name' => 'Account',
            'description' => 'Recorded audit activity.',
            'ip_address' => '127.0.0.1',
        ], $overrides));
    }

    public function test_creating_a_user_records_actor_target_context_and_no_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post('/admin/users', $this->validUserPayload())
            ->assertRedirect('/admin/users');

        $created = User::where('email', 'maria.santos@djnrmhs.test')->firstOrFail();
        $log = AuditLog::where('action', AuditAction::CreatedUser->value)->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Juan Dela Cruz', $log->actor_name);
        $this->assertSame('EMP-9000', $log->actor_employee_id);
        $this->assertSame((string) $created->id, $log->target_id);
        $this->assertSame('Maria Reyes Santos', $log->target_name);
        $this->assertSame('203.0.113.10', $log->ip_address);
        $this->assertStringContainsString('Created a new user account', $log->description);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertStringNotContainsString('Password123!', json_encode($log->new_values));
    }

    public function test_updating_a_user_records_only_safe_changed_fields(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create([
            'name' => 'Pedro Santos',
            'department' => 'Warehouse',
        ]);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            ...$staff->nameComponents(),
            'email' => $staff->email,
            'phone' => $staff->phone,
            'department' => 'Finance',
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
        ])->assertRedirect('/admin/users');

        $log = AuditLog::where('action', AuditAction::UpdatedUser->value)->latest('id')->firstOrFail();

        $this->assertSame('Pedro Santos', $log->target_name);
        $this->assertSame('Warehouse', $log->old_values['department']);
        $this->assertSame('Finance', $log->new_values['department']);
        $this->assertSame(UserRole::WarehouseStaff->value, $log->old_values['role']);
        $this->assertSame(UserRole::Viewer->value, $log->new_values['role']);
        $this->assertArrayNotHasKey('password', $log->old_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_password_changes_are_logged_without_password_values(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['name' => 'Pedro Santos']);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            ...$staff->nameComponents(),
            'email' => $staff->email,
            'phone' => $staff->phone,
            'department' => $staff->department,
            'role' => $staff->role->value,
            'status' => $staff->status->value,
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertRedirect('/admin/users');

        $log = AuditLog::where('action', AuditAction::ChangedPassword->value)->firstOrFail();

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertStringNotContainsString('BrandNewPass1!', $log->description);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_deleted_target_snapshot_remains_after_the_user_is_gone(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create([
            'name' => 'Employee To Delete',
            'employee_id' => 'EMP-0042',
        ]);

        $this->actingAs($admin);
        $staff->delete();

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);

        $log = AuditLog::where('action', AuditAction::DeletedUser->value)->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame((string) $staff->id, $log->target_id);
        $this->assertSame('Employee To Delete', $log->target_name);
        $this->assertSame('EMP-0042', $log->old_values['employee_id']);
    }

    public function test_rejected_self_deletion_keeps_the_user_and_creates_no_deletion_audit(): void
    {
        $user = User::factory()->create([
            'name' => 'Retained Employee',
            'employee_id' => 'EMP-0043',
        ]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::DeletedUser->value,
            'target_id' => (string) $user->id,
        ]);
    }

    public function test_successful_login_and_logout_are_recorded(): void
    {
        $user = User::factory()->create(['name' => 'Maria Santos']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'actor_name' => 'Maria Santos',
            'action' => AuditAction::LoggedIn->value,
            'target_name' => 'Account',
        ]);

        $this->post('/logout')->assertRedirect('/');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'actor_name' => 'Maria Santos',
            'action' => AuditAction::LoggedOut->value,
            'target_name' => 'Account',
        ]);
    }

    public function test_only_audit_authorized_roles_can_access_the_audit_trail(): void
    {
        $admin = $this->admin();
        $superAdmin = User::factory()->superAdministrator()->create();
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get('/admin/audit-trail')
            ->assertOk()
            ->assertSee('Audit Trail');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs($auditor, AuthenticationContext::WEB_GUARD)
            ->get('/admin/audit-trail')
            ->assertOk()
            ->assertSee('Audit Trail');

        $this->actingAs($admin)->get('/admin/audit-trail')
            ->assertForbidden();

        $this->actingAs(User::factory()->warehouseStaff()->create())
            ->get('/admin/audit-trail')
            ->assertForbidden();

        $this->app['auth']->guard(AuthenticationContext::SUPER_ADMIN_GUARD)->logout();
        $this->app['auth']->guard(AuthenticationContext::WEB_GUARD)->logout();

        $this->get('/admin/audit-trail')->assertRedirect('/super-admin/login');
    }

    public function test_only_audit_authorized_roles_can_access_audit_suggestions(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'Audit']))
            ->assertOk();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs($auditor, AuthenticationContext::WEB_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'Audit']))
            ->assertOk();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->admin(), AuthenticationContext::ADMIN_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'Audit']))
            ->assertForbidden();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs(User::factory()->warehouseStaff()->create(), AuthenticationContext::WEB_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'Audit']))
            ->assertForbidden();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->getJson(route('admin.audit-logs.suggestions', ['query' => 'Audit']))
            ->assertUnauthorized();
    }

    public function test_audit_suggestions_come_from_matching_stored_data(): void
    {
        $this->auditLog([
            'actor_name' => 'Zediska Administrator',
            'actor_employee_id' => 'ZED-001',
            'action' => AuditAction::UpdatedUser,
            'target_name' => 'Zedrick Santos',
            'description' => 'Updated the matching account.',
            'new_values' => ['email' => 'zediskaaa@gmail.com'],
            'ip_address' => '203.0.113.20',
        ]);
        $this->auditLog([
            'actor_name' => 'Unrelated Operator',
            'target_name' => 'Different Account',
            'description' => 'This row must not be suggested.',
        ]);

        $superAdmin = User::factory()->superAdministrator()->create();
        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'zed']));

        $response->assertOk();

        $suggestions = collect($response->json('data'));

        $this->assertContains('Zediska Administrator', $suggestions->pluck('value'));
        $this->assertContains('Zedrick Santos', $suggestions->pluck('value'));
        $this->assertContains('ZED-001', $suggestions->pluck('value'));
        $this->assertContains('zediskaaa@gmail.com', $suggestions->pluck('value'));
        $this->assertNotContains('Unrelated Operator', $suggestions->pluck('value'));
        $this->assertContains('Email', $suggestions->pluck('category'));
    }

    public function test_suggestions_collapse_historical_values_for_the_same_account(): void
    {
        $person = User::factory()->create();
        $otherPerson = User::factory()->create();

        $this->auditLog([
            'user_id' => $person->id,
            'actor_name' => 'Zedrick Old Name',
        ]);
        $this->auditLog([
            'user_id' => $person->id,
            'actor_name' => 'Zedrick Current Name',
        ]);
        $this->auditLog([
            'user_id' => $otherPerson->id,
            'actor_name' => 'Zedrick Other Account',
        ]);
        $this->auditLog([
            'target_type' => User::class,
            'target_id' => (string) $person->id,
            'target_name' => 'Zedrick Target Snapshot',
            'old_values' => ['email' => 'zedrick.old@example.test'],
            'new_values' => ['email' => 'unrelated@example.test'],
        ]);
        $this->auditLog([
            'target_type' => User::class,
            'target_id' => (string) $person->id,
            'old_values' => ['email' => 'another-unrelated@example.test'],
            'new_values' => ['email' => 'zedrick.current@example.test'],
        ]);

        $superAdmin = User::factory()->superAdministrator()->create();
        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'zedrick']))
            ->assertOk();

        $suggestions = collect($response->json('data'));
        $performedBy = $suggestions->where('category', 'Performed by')->pluck('value');
        $emails = $suggestions->where('category', 'Email')->pluck('value');

        $this->assertEqualsCanonicalizing(
            ['Zedrick Current Name', 'Zedrick Other Account'],
            $performedBy->all(),
        );
        $this->assertNotContains('Zedrick Old Name', $performedBy);
        $this->assertNotContains('Zedrick Target Snapshot', $suggestions->pluck('value'));
        $this->assertSame(['zedrick.current@example.test'], $emails->values()->all());
        $this->assertNotContains('unrelated@example.test', $suggestions->pluck('value'));
    }

    public function test_action_suggestions_are_backed_by_existing_logs_and_remain_searchable(): void
    {
        $this->auditLog([
            'action' => AuditAction::UpdatedUser,
            'description' => 'Only the updated action should match.',
        ]);
        $this->auditLog([
            'action' => AuditAction::LoggedIn,
            'description' => 'Unrelated login activity.',
        ]);
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->getJson(route('admin.audit-logs.suggestions', ['query' => 'Updated']))
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'Updated User',
                'category' => 'Action',
            ])
            ->assertJsonMissing(['value' => 'Created User']);

        $this->get(route('admin.audit-logs.index', ['search' => 'Updated User']))
            ->assertOk()
            ->assertSee('Only the updated action should match.')
            ->assertDontSee('Unrelated login activity.');
    }

    public function test_email_values_in_recorded_details_are_searchable(): void
    {
        $this->auditLog([
            'description' => 'Matched email activity.',
            'new_values' => ['email' => 'zediskaaa@gmail.com'],
        ]);
        $this->auditLog([
            'description' => 'Unrelated email activity.',
            'new_values' => ['email' => 'someone@example.com'],
        ]);
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.audit-logs.index', ['search' => 'zediskaaa@gmail.com']))
            ->assertOk()
            ->assertSee('Matched email activity.')
            ->assertDontSee('Unrelated email activity.');
    }

    public function test_suggestions_are_limited_and_handle_empty_invalid_and_no_match_queries(): void
    {
        foreach (range(1, 12) as $number) {
            $this->auditLog([
                'actor_name' => 'Test Operator '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'description' => "Suggestion row {$number}.",
            ]);
        }

        $superAdmin = User::factory()->superAdministrator()->create();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        $limited = $this->getJson(route('admin.audit-logs.suggestions', ['query' => 'Test']));

        $limited->assertOk();
        $this->assertLessThanOrEqual(8, count($limited->json('data')));
        $this->assertLessThan(12, count($limited->json('data')));

        $this->getJson(route('admin.audit-logs.suggestions', ['query' => '']))
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->getJson(route('admin.audit-logs.suggestions', ['query' => 'NoSuchAuditValue']))
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->getJson(route('admin.audit-logs.suggestions', ['query' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('query');
    }

    public function test_audit_search_renders_the_autocomplete_interaction_contract(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('auditSearchAutocomplete', false)
            ->assertSee('admin\\/audit-trail\\/suggestions', false)
            ->assertSee('!overflow-visible', false)
            ->assertSee('x-on:click.outside="close()"', false)
            ->assertSee('x-on:keydown.down.prevent="move(1)"', false)
            ->assertSee('x-on:keydown.up.prevent="move(-1)"', false)
            ->assertSee('x-on:keydown.enter="selectActive($event)"', false)
            ->assertSee('x-on:keydown.escape.stop="close()"', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('role="listbox"', false)
            ->assertSee('No matching audit data.');

        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('debounceDelay: 300', $script);
        $this->assertStringContainsString('new AbortController()', $script);
    }

    public function test_audit_page_displays_philippine_time_and_supports_filters(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 8, 27, 20, 15, 0, 'Asia/Manila'));
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $this->post('/admin/logout');

        $superAdmin = User::factory()->superAdministrator()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get('/admin/audit-trail?action='.AuditAction::LoggedIn->value.'&search=Juan')
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Logged In')
            ->assertSee('Aug 27, 2026, 8:15:00 PM')
            ->assertSee('PHT (UTC+8)');
    }

    public function test_sidebar_link_is_visible_only_to_audit_authorized_roles(): void
    {
        $this->actingAs($this->admin(), AuthenticationContext::ADMIN_GUARD)->get('/dashboard')
            ->assertDontSee('Audit Trail');
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs(User::factory()->viewer()->create())->get('/dashboard')
            ->assertDontSee('Audit Trail');
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs(User::factory()->auditor()->create())->get('/dashboard')
            ->assertSee('Audit Trail');
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs(
            User::factory()->superAdministrator()->create(),
            AuthenticationContext::SUPER_ADMIN_GUARD,
        )->get('/dashboard')->assertSee('Audit Trail');
    }

    public function test_audit_logs_are_append_only_and_have_no_mutation_routes(): void
    {
        $admin = $this->admin();
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $log = AuditLog::firstOrFail();

        try {
            $log->update(['description' => 'Tampered']);
            $this->fail('Updating an audit log should throw.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Deleting an audit log should throw.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->put("/admin/audit-trail/{$log->id}")->assertMethodNotAllowed();
        $this->delete("/admin/audit-trail/{$log->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_new_events_store_utc_context_and_remove_sensitive_snapshot_values(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 9, 11, 14, 35, 18, 'Asia/Manila'));
        $actor = User::factory()->superAdministrator()->create();

        $log = app(AuditLogger::class)->log(
            AuditAction::UpdatedUser,
            $actor,
            'Updated an account.',
            $actor,
            'Account',
            oldValues: ['email' => 'old@example.test', 'password_hash' => 'must-not-appear'],
            newValues: [
                'email' => 'new@example.test',
                'nested' => ['api_token' => 'must-not-appear', 'status' => 'active'],
            ],
        );

        $this->assertNotNull($log->event_id);
        $this->assertSame(UserRole::SuperAdministrator->value, $log->actor_role);
        $this->assertSame('Administration', $log->event_category);
        $this->assertSame('User Administration', $log->module);
        $this->assertSame('success', $log->outcome);
        $this->assertSame('user', $log->source);
        $this->assertSame('Asia/Manila', $log->display_timezone);
        $this->assertSame('2026-09-11 06:35:18.000000', $log->occurred_at_utc);
        $this->assertSame('September 11, 2026, 2:35:18 PM', $log->displayTimestamp()->format('F j, Y, g:i:s A'));
        $this->assertArrayNotHasKey('password_hash', $log->old_values);
        $this->assertArrayNotHasKey('api_token', $log->new_values['nested']);
        $this->assertSame('active', $log->new_values['nested']['status']);
        $this->assertStringNotContainsString('must-not-appear', $log->toJson());
    }

    public function test_audit_filters_and_detail_view_use_server_side_metadata(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();
        $log = app(AuditLogger::class)->log(
            AuditAction::ScheduledCycleCount,
            $superAdmin,
            'Scheduled a count.',
            targetName: 'CC-2026-001',
        );
        app(AuditLogger::class)->log(AuditAction::LoggedIn, $superAdmin, 'Unrelated activity.');

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.audit-logs.index', [
                'category' => 'Inventory & Warehousing',
                'module' => 'Cycle Counts',
                'outcome' => 'success',
                'source' => 'user',
                'target' => 'CC-2026-001',
            ]))
            ->assertOk()
            ->assertSee('Scheduled a count.')
            ->assertDontSee('Unrelated activity.');

        $this->get(route('admin.audit-logs.show', $log))
            ->assertOk()
            ->assertSee($log->event_id)
            ->assertSee('Authoritative UTC time')
            ->assertSee('PHT (UTC+8)');
    }

    public function test_failed_login_is_recorded_without_attempted_credentials(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'IncorrectPassword123!',
        ])->assertSessionHasErrors('email');

        $log = AuditLog::where('action', AuditAction::FailedLogin->value)->firstOrFail();

        $this->assertNull($log->user_id);
        $this->assertSame((string) $admin->id, $log->target_id);
        $this->assertStringNotContainsString('IncorrectPassword123!', $log->toJson());
        $this->assertStringNotContainsString($admin->email, $log->description);
    }

    public function test_manila_date_filter_uses_utc_boundaries_without_double_conversion(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->travelTo(CarbonImmutable::create(2026, 9, 30, 23, 59, 59, 'Asia/Manila'));
        app(AuditLogger::class)->log(AuditAction::LoggedIn, $superAdmin, 'Inside Manila day.');

        $this->travelTo(CarbonImmutable::create(2026, 10, 1, 0, 0, 1, 'Asia/Manila'));
        app(AuditLogger::class)->log(AuditAction::LoggedIn, $superAdmin, 'Outside Manila day.');

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.audit-logs.index', [
                'date_from' => '2026-09-30',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('Inside Manila day.')
            ->assertDontSee('Outside Manila day.');

        $inside = AuditLog::where('description', 'Inside Manila day.')->firstOrFail();
        $outside = AuditLog::where('description', 'Outside Manila day.')->firstOrFail();
        $this->assertSame('2026-09-30 15:59:59.000000', $inside->occurred_at_utc);
        $this->assertSame('2026-09-30 16:00:01.000000', $outside->occurred_at_utc);
    }
}
