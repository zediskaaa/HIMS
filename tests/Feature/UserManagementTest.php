<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Access is decided by permission, never by role name: routes and views ask
 * `can:manage_users`, and UserRole::permissions() is the only place that says
 * which roles hold it. These tests therefore drive the real HTTP endpoints
 * rather than calling the gate directly, so a broken registration in
 * AppServiceProvider fails here too.
 *
 * The lockout guards in UserAccountService get the most attention, because
 * they protect the one mistake that cannot be undone through the UI: ending
 * up with no active administrator.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    // ------------------------------------------------------------------ access

    public function test_an_administrator_reaches_the_user_list(): void
    {
        $admin = $this->admin();
        User::factory()->warehouseStaff()->create(['name' => 'Ben Santos']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertStatus(200)
            ->assertSee('Ben Santos');
    }

    public function test_only_super_administrator_can_assign_the_auditor_role(): void
    {
        $service = app(UserAccountService::class);
        $admin = $this->admin();
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->assertNotContains(UserRole::Auditor, $service->assignableRoles($admin));
        $this->assertContains(UserRole::Auditor, $service->assignableRoles($superAdmin));
    }

    /**
     * Every non-administrator role, driven off the enum rather than a hand
     * written list — a role added later is covered without editing this test.
     */
    public function test_no_other_role_reaches_user_management(): void
    {
        foreach (UserRole::cases() as $role) {
            if ($role->isAdministrator()) {
                continue;
            }

            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/admin/users')
                ->assertForbidden();

            $this->actingAs($user)->get('/admin/users/create')
                ->assertForbidden();

            $this->actingAs($user)->post('/admin/users', [
                'surname' => 'Hire',
                'first_name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRole::Administrator->value,
                'phone' => '09171234567',
            ])->assertForbidden()
                ->assertSessionMissing('account_created_success');
        }

        // Not one of those attempts created anything.
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    /**
     * A deactivated administrator keeps the role but holds no permissions, so
     * the gate would refuse them anyway. EnsureUserIsActive gets there first
     * and ends the session outright, which is the stronger outcome: the block
     * lands on their next click rather than at their next login.
     */
    public function test_a_deactivated_administrator_holds_no_permissions(): void
    {
        $admin = User::factory()->administrator()->inactive()->create();

        $this->assertFalse($admin->hasPermission(Permission::ManageUsers));
        $this->assertSame([], $admin->permissions());

        $this->actingAs($admin)->get('/admin/users')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    /**
     * Deactivation therefore takes effect mid-session rather than at next
     * login — the point of the switch on the user list.
     */
    public function test_deactivating_a_signed_in_user_ends_their_session(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        $this->actingAs($staff)->get('/dashboard')->assertStatus(200);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->actingAs($staff->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_deactivated_employee_cannot_log_in(): void
    {
        $staff = User::factory()->warehouseStaff()->inactive()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 4 attempts remaining.',
        ]);

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    // ------------------------------------------------------------------ create

    public function test_an_administrator_creates_a_staff_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'email' => 'juan.delacruz@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::InventoryManager->value,
            'employee_id' => 'EMP-9999', // A forged value must be ignored.
            'department' => 'Central Supply',
            'phone' => '09171234567',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('account_created_success', 'Account created successfully.')
            ->assertRedirect('/admin/users');

        $created = User::where('email', 'juan.delacruz@djnrmhs.test')->firstOrFail();

        $this->assertSame('Dela Cruz', $created->surname);
        $this->assertSame('Juan', $created->first_name);
        $this->assertSame('Santos', $created->middle_name);
        $this->assertSame('Juan Santos Dela Cruz', $created->name);
        $this->assertSame(UserRole::InventoryManager, $created->role);
        $this->assertSame(UserStatus::Active, $created->status);
        $this->assertSame('EMP-0001', $created->employee_id);

        // Stored hashed by the model's 'hashed' cast, never in the clear.
        $this->assertNotSame('Password123!', $created->password);
        $this->assertTrue(Hash::check('Password123!', $created->password));

        // Created by an administrator in person, so no verification email step.
        $this->assertNotNull($created->email_verified_at);

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('Account created successfully.')
            ->assertSee('Juan Santos Dela Cruz')
            ->assertSee('09171234567')
            ->assertSeeInOrder([
                'Employee ID', 'Surname', 'First Name', 'Middle Name', 'Department', 'Contact Number', 'Role',
            ]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertDontSee('Account created successfully.');
    }

    public function test_a_new_account_gets_exactly_its_role_permissions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Dizon',
            'first_name' => 'Cely',
            'email' => 'cely@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::PharmacyStaff->value,
            'department' => 'Pharmacy',
            'phone' => '09171234567',
        ])->assertRedirect('/admin/users');

        $pharmacy = User::where('email', 'cely@djnrmhs.test')->firstOrFail();

        // What the department actually does: read the shelf and dispense from it.
        $this->assertTrue($pharmacy->hasPermission(Permission::ViewInventory));
        $this->assertTrue($pharmacy->hasPermission(Permission::IssueStock));
        $this->assertTrue($pharmacy->hasPermission(Permission::ViewReports));

        // And nothing beyond it. record_movements is the one to watch: it used
        // to be granted here, which is what let a pharmacy account book in
        // deliveries and return stock to suppliers.
        $this->assertFalse($pharmacy->hasPermission(Permission::RecordMovements));
        $this->assertFalse($pharmacy->hasPermission(Permission::AdjustStock));
        $this->assertFalse($pharmacy->hasPermission(Permission::ManageItems));
        $this->assertFalse($pharmacy->hasPermission(Permission::ManageUsers));
        $this->assertFalse($pharmacy->hasPermission(Permission::ManageProcurement));
    }

    public function test_duplicate_email_is_rejected_and_employee_id_input_is_ignored(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@djnrmhs.test', 'employee_id' => 'EMP-9001']);

        $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Account',
            'first_name' => 'Clash',
            'email' => 'taken@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'employee_id' => 'EMP-9001',
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('email')
            ->assertSessionMissing('account_created_success')
            ->assertSessionDoesntHaveErrors('employee_id');
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'surname' => 'Account',
            'first_name' => 'Typo',
            'email' => 'typo@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password456!',
            'role' => UserRole::Viewer->value,
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('password')
            ->assertSessionMissing('account_created_success');

        $this->assertDatabaseMissing('users', ['email' => 'typo@djnrmhs.test']);
    }

    public function test_a_database_failure_does_not_create_an_account_or_show_success(): void
    {
        $admin = $this->admin();
        $nextEmployeeNumber = DB::table('employee_id_sequences')->value('next_value');

        User::creating(function (): void {
            throw new \RuntimeException('Simulated database failure.');
        });

        $response = $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Failed',
            'first_name' => 'Creation',
            'email' => 'failed.creation@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Internal Audit',
            'phone' => '09171234567',
        ]);

        $response
            ->assertServerError()
            ->assertSessionMissing('account_created_success');

        $this->assertDatabaseMissing('users', [
            'email' => 'failed.creation@djnrmhs.test',
        ]);
        $this->assertSame($nextEmployeeNumber, DB::table('employee_id_sequences')->value('next_value'));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'surname' => 'Up',
            'first_name' => 'Made',
            'email' => 'madeup@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'chief_wizard',
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('role');
    }

    public function test_surname_and_first_name_are_required_but_middle_name_is_optional(): void
    {
        $admin = $this->admin();

        $before = User::count();

        $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['surname', 'first_name', 'email', 'password', 'role', 'department', 'phone']);

        $this->assertSame($before, User::count());

        $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
            'surname' => 'Reyes',
            'first_name' => 'Ana',
            'email' => 'ana.reyes@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Central Supply',
            'phone' => '09171234567',
        ])->assertRedirect('/admin/users');

        $created = User::where('email', 'ana.reyes@djnrmhs.test')->firstOrFail();
        $this->assertNull($created->middle_name);
        $this->assertSame('Ana Reyes', $created->name);
        $this->assertSame('09171234567', $created->phone);
    }

    public function test_a_missing_phone_number_prevents_user_creation(): void
    {
        $admin = $this->admin();
        $nextEmployeeNumber = DB::table('employee_id_sequences')->value('next_value');

        $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
            'surname' => 'No Phone',
            'first_name' => 'User',
            'email' => 'no-phone@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Administration',
            'phone' => '',
        ])->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors([
                'phone' => 'Phone number is required.',
            ])
            ->assertSessionHasInput('surname', 'No Phone')
            ->assertSessionHasInput('email', 'no-phone@djnrmhs.test');

        $this->assertDatabaseMissing('users', ['email' => 'no-phone@djnrmhs.test']);
        $this->assertSame($nextEmployeeNumber, DB::table('employee_id_sequences')->value('next_value'));
    }

    public function test_every_invalid_phone_format_prevents_user_creation(): void
    {
        $admin = $this->admin();
        $invalidNumbers = [
            '0912345678',
            '091234567890',
            '9123456789',
            '08123456789',
            '09123abc789',
            '0912 345 6789',
            '09-1234-56789',
            '09(123)456789',
            '+639123456789',
            'abc09123456789',
            '09@123456789',
        ];

        foreach ($invalidNumbers as $index => $phone) {
            $email = "bad-phone-{$index}@djnrmhs.test";

            $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
                'surname' => 'Bad Phone',
                'first_name' => 'User',
                'email' => $email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRole::Viewer->value,
                'department' => 'Administration',
                'phone' => $phone,
            ])->assertRedirect('/admin/users/create')
                ->assertSessionHasErrors('phone');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_each_supported_phone_number_is_saved_exactly_with_its_leading_zero(): void
    {
        $admin = $this->admin();

        foreach (['09123456789', '09987654321', '09051234567'] as $index => $phone) {
            $email = "valid-phone-{$index}@djnrmhs.test";
            $password = "ValidPhone{$index}!";

            $this->actingAs($admin)->post('/admin/users', [
                'surname' => 'Valid Phone',
                'first_name' => 'User',
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'role' => UserRole::Viewer->value,
                'department' => 'Administration',
                'phone' => $phone,
            ])->assertRedirect('/admin/users');

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'phone' => $phone,
            ]);
        }
    }

    public function test_employee_ids_are_automatic_sequential_and_unique(): void
    {
        $admin = $this->admin();

        foreach ([1 => 'first', 2 => 'second'] as $number => $emailPrefix) {
            $password = "EmployeePassword{$number}!";

            $this->actingAs($admin)->post('/admin/users', [
                'surname' => 'Employee',
                'first_name' => ucfirst($emailPrefix),
                'email' => $emailPrefix.'@djnrmhs.test',
                'password' => $password,
                'password_confirmation' => $password,
                'role' => UserRole::Viewer->value,
                'department' => 'Records Management',
                'employee_id' => 'EMP-9001',
                'phone' => '09171234567',
            ])->assertRedirect('/admin/users');

            $this->assertDatabaseHas('users', [
                'email' => $emailPrefix.'@djnrmhs.test',
                'employee_id' => 'EMP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            ]);
        }

        $assigned = User::whereIn('email', ['first@djnrmhs.test', 'second@djnrmhs.test'])
            ->pluck('employee_id');
        $this->assertCount(2, $assigned);
        $this->assertCount(2, $assigned->unique());
        $this->assertSame(3, DB::table('employee_id_sequences')->value('next_value'));
    }

    public function test_an_invalid_department_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'surname' => 'Intruder',
            'first_name' => 'Department',
            'email' => 'invalid-department@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Made Up Department',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('department');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-department@djnrmhs.test']);
    }

    // ------------------------------------------------------------------ update

    public function test_changing_a_role_changes_what_that_account_may_do(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        $this->assertFalse($staff->hasPermission(Permission::ManageProcurement));

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            ...$staff->nameComponents(),
            'email' => $staff->email,
            'role' => UserRole::InventoryManager->value,
            'status' => UserStatus::Active->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
        ])->assertRedirect('/admin/users');

        $this->assertTrue($staff->fresh()->hasPermission(Permission::ManageProcurement));
    }

    /**
     * The edit form does not echo the existing password back, so an empty
     * field means "leave it alone" rather than "clear it".
     */
    public function test_a_blank_password_on_edit_leaves_the_existing_one_intact(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['password' => Hash::make('OriginalPass1!')]);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            'surname' => 'Person',
            'first_name' => 'Renamed',
            'middle_name' => null,
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => UserStatus::Active->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
            'password' => '',
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertSame('Renamed Person', $staff->name);
        $this->assertTrue(Hash::check('OriginalPass1!', $staff->password));
    }

    public function test_a_supplied_password_on_edit_replaces_the_old_one(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['password' => Hash::make('OriginalPass1!')]);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            ...$staff->nameComponents(),
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => UserStatus::Active->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertFalse(Hash::check('OriginalPass1!', $staff->password));
        $this->assertTrue(Hash::check('BrandNewPass1!', $staff->password));
    }

    public function test_an_invalid_phone_number_cannot_update_a_user(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['phone' => '09123456789']);

        $this->actingAs($admin)
            ->from("/admin/users/{$staff->id}/edit")
            ->put("/admin/users/{$staff->id}", [
                ...$staff->nameComponents(),
                'email' => $staff->email,
                'role' => $staff->role->value,
                'status' => $staff->status->value,
                'department' => $staff->department,
                'phone' => '+639123456789',
            ])->assertRedirect("/admin/users/{$staff->id}/edit")
            ->assertSessionHasErrors('phone');

        $this->assertSame('09123456789', $staff->fresh()->phone);
    }

    // ------------------------------------------------------------------ status

    public function test_toggling_status_deactivates_then_reactivates_an_account(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertSame(UserStatus::Inactive, $staff->fresh()->status);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertSame(UserStatus::Active, $staff->fresh()->status);
    }

    public function test_deactivation_preserves_employee_inventory_and_audit_ownership(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();
        $item = InventoryItem::create([
            'name' => 'Retention Test Item',
            'sku' => 'RETENTION-001',
            'unit' => 'box',
            'status' => 'active',
        ]);
        $movement = StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 1,
            'user_id' => $staff->id,
            'moved_at' => now(),
        ]);
        $activity = AuditLog::create([
            'user_id' => $staff->id,
            'actor_name' => $staff->name,
            'actor_employee_id' => $staff->employee_id,
            'action' => AuditAction::LoggedIn,
            'target_name' => 'Account',
            'description' => 'Historical employee activity.',
        ]);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'status' => UserStatus::Inactive->value,
        ]);
        $this->assertSame($staff->id, $movement->fresh()->user_id);
        $this->assertTrue($movement->fresh()->user->is($staff));
        $this->assertSame($staff->id, $activity->fresh()->user_id);
        $this->assertTrue($activity->fresh()->actor->is($staff));
    }

    /**
     * Deactivation replaces deletion, so the stock movements an account
     * recorded keep naming a real person.
     */
    public function test_there_is_no_delete_route_for_an_account(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/users/{$staff->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    // ------------------------------------------------------------- lockout guards

    public function test_an_administrator_cannot_demote_themselves(): void
    {
        $admin = $this->admin();
        // A second administrator exists, so this can only fail on the self check.
        $this->admin();

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", [
                ...$admin->nameComponents(),
                'email' => $admin->email,
                'role' => UserRole::Viewer->value,
                'status' => UserStatus::Active->value,
                'department' => $admin->department,
                'phone' => $admin->phone,
            ])->assertForbidden();

        $this->assertSame(UserRole::Administrator, $admin->fresh()->role);
    }

    public function test_an_administrator_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status")
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $admin->fresh()->status);
    }

    /**
     * The count guard, exercised against the service directly.
     *
     * It cannot be reached over HTTP: the actor must hold manage_users, which
     * only an active administrator has, so an actor distinct from the target
     * already guarantees a survivor. It is defence in depth for the callers
     * that do not go through the gate — the API, a console command, a seeder —
     * and this test stands in for them.
     */
    public function test_the_service_refuses_a_non_super_admin_demoting_an_administrator(): void
    {
        $onlyAdmin = $this->admin();
        $actor = User::factory()->warehouseStaff()->create();

        $this->assertSame(1, User::administrators()->active()->count());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only a Super Administrator may manage administrative accounts.');

        app(UserAccountService::class)->update($onlyAdmin, [
            'name' => $onlyAdmin->name,
            'email' => $onlyAdmin->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
        ], $actor);
    }

    public function test_the_service_refuses_a_non_super_admin_deactivating_an_administrator(): void
    {
        $onlyAdmin = $this->admin();
        $actor = User::factory()->warehouseStaff()->create();

        try {
            app(UserAccountService::class)->toggleStatus($onlyAdmin, $actor);
            $this->fail('A non-Super Administrator must not deactivate an administrator.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only a Super Administrator', $e->getMessage());
        }

        // Refused inside a transaction, so nothing was written.
        $this->assertSame(UserStatus::Active, $onlyAdmin->fresh()->status);
    }

    /**
     * The guard is about administrators specifically — an ordinary account
     * being the last of its role is not a lockout and must still be editable.
     */
    public function test_demoting_a_non_administrator_is_never_blocked(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($admin)->put("/admin/users/{$manager->id}", [
            ...$manager->nameComponents(),
            'email' => $manager->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Inactive->value,
            'department' => $manager->department,
            'phone' => $manager->phone,
        ])->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame(UserRole::Viewer, $manager->role);
        $this->assertSame(UserStatus::Inactive, $manager->status);
    }

    /**
     * Demoting one administrator while another remains is the normal case and
     * must go through — the guard is not allowed to be over-eager.
     */
    public function test_an_administrator_cannot_demote_another_administrator(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$other->id}", [
            ...$other->nameComponents(),
            'email' => $other->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
            'department' => $other->department,
            'phone' => $other->phone,
        ])->assertForbidden();

        $this->assertSame(UserRole::Administrator, $other->fresh()->role);
        $this->assertSame(2, User::administrators()->active()->count());
    }

    // -------------------------------------------------------- filters and views

    public function test_the_list_filters_by_role_status_and_search(): void
    {
        $admin = $this->admin();
        User::factory()->warehouseStaff()->create(['name' => 'Ben Santos', 'department' => 'Warehouse']);
        User::factory()->inventoryManager()->create(['name' => 'Ana Reyes', 'department' => 'Central Supply']);
        User::factory()->role(UserRole::Viewer)->inactive()->create(['name' => 'Dino Cruz']);

        $this->actingAs($admin)->get('/admin/users?role='.UserRole::WarehouseStaff->value)
            ->assertSee('Ben Santos')
            ->assertDontSee('Ana Reyes');

        $this->actingAs($admin)->get('/admin/users?status='.UserStatus::Inactive->value)
            ->assertSee('Dino Cruz')
            ->assertDontSee('Ben Santos');

        $this->actingAs($admin)->get('/admin/users?search=Central+Supply')
            ->assertSee('Ana Reyes')
            ->assertDontSee('Ben Santos');
    }

    public function test_the_detail_screen_lists_the_movements_that_account_recorded(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['name' => 'Ben Santos']);

        $this->actingAs($admin)->get("/admin/users/{$staff->id}")
            ->assertStatus(200)
            ->assertSee('Ben Santos');
    }

    public function test_the_create_and_edit_screens_render(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users/create')
            ->assertStatus(200)
            ->assertSee('name="surname"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="middle_name"', false)
            ->assertSee('name="employee_id"', false)
            ->assertSee('Generated automatically after creation')
            ->assertSee('name="department"', false)
            ->assertSee('Select a department')
            ->assertSee('name="role"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('maxlength="11"', false)
            ->assertSee('pattern="09[0-9]{9}"', false)
            ->assertSee('placeholder="09XXXXXXXXX"', false)
            ->assertDontSee('name="name"', false);

        $this->actingAs($admin)->get("/admin/users/{$staff->id}/edit")
            ->assertStatus(200)
            ->assertSee('value="'.e($staff->surname).'"', false)
            ->assertSee('value="'.e($staff->first_name).'"', false)
            ->assertSee('value="'.e($staff->employee_id).'"', false)
            ->assertSee('value="'.e($staff->department).'" selected', false)
            ->assertSee('value="'.$staff->role->value.'"', false);
    }

    public function test_editing_updates_each_name_part_and_the_complete_name(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['name' => 'Ana Reyes']);
        $originalEmployeeId = $staff->employee_id;

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => $staff->status->value,
            'department' => 'Finance',
            'employee_id' => 'EMP-9999',
            'phone' => $staff->phone,
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertSame('Dela Cruz', $staff->surname);
        $this->assertSame('Juan', $staff->first_name);
        $this->assertSame('Santos', $staff->middle_name);
        $this->assertSame('Juan Santos Dela Cruz', $staff->name);
        $this->assertSame('Finance', $staff->department);
        $this->assertSame($originalEmployeeId, $staff->employee_id);
    }

    public function test_a_legacy_name_still_displays_and_populates_the_edit_form(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        // Simulate an existing row from before the structured columns existed.
        DB::table('users')->where('id', $staff->id)->update([
            'name' => 'Ben Santos',
            'surname' => null,
            'first_name' => null,
            'middle_name' => null,
        ]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('Ben Santos');

        $this->actingAs($admin)->get("/admin/users/{$staff->id}/edit")
            ->assertStatus(200)
            ->assertSee('value="Santos"', false)
            ->assertSee('value="Ben"', false);
    }

    /**
     * The sidebar link is hidden rather than shown-and-refused, so a
     * non-administrator is never offered a door that will not open.
     */
    public function test_the_sidebar_shows_user_management_only_to_administrators(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('User Management');

        $this->actingAs(User::factory()->warehouseStaff()->create())->get('/dashboard')
            ->assertDontSee('User Management');
    }
}
