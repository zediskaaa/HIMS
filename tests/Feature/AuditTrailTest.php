<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
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

    public function test_self_deletion_keeps_the_audit_snapshot_without_recreating_the_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Self Deleting User',
            'employee_id' => 'EMP-0043',
        ]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $log = AuditLog::where('action', AuditAction::DeletedUser->value)->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame('Self Deleting User', $log->actor_name);
        $this->assertSame('Self Deleting User', $log->target_name);
        $this->assertSame('EMP-0043', $log->old_values['employee_id']);
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

    public function test_only_administrators_can_access_the_audit_trail(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/audit-trail')
            ->assertOk()
            ->assertSee('Audit Trail');

        $this->actingAs(User::factory()->warehouseStaff()->create())
            ->get('/admin/audit-trail')
            ->assertForbidden();

        $this->post('/logout')->assertRedirect('/');

        $this->get('/admin/audit-trail')->assertRedirect('/login');
    }

    public function test_audit_page_displays_philippine_time_and_supports_filters(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 8, 27, 20, 15, 0, 'Asia/Manila'));
        $admin = $this->admin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->get('/admin/audit-trail?action='.AuditAction::LoggedIn->value.'&search=Juan')
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Logged In')
            ->assertSee('Aug 27, 2026, 8:15 PM')
            ->assertSee('PHT (UTC+8)');
    }

    public function test_sidebar_link_is_visible_only_to_an_administrator(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('Audit Trail');

        $this->actingAs(User::factory()->viewer()->create())->get('/dashboard')
            ->assertDontSee('Audit Trail');
    }

    public function test_audit_logs_are_append_only_and_have_no_mutation_routes(): void
    {
        $admin = $this->admin();
        $this->post('/login', [
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

        $this->put("/admin/audit-trail/{$log->id}")->assertNotFound();
        $this->delete("/admin/audit-trail/{$log->id}")->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }
}
