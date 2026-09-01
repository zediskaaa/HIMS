<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuthenticationContext;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'zediskaaa@gmail.com';

    private const INITIAL_PASSWORD = 'superadminzediskaaa123';

    /**
     * @return array<string, string>
     */
    private function validUserPayload(UserRole $role, string $email): array
    {
        return [
            'surname' => 'Account',
            'first_name' => 'Test',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => $role->value,
            'department' => 'Administration',
            'phone' => '09171234567',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validUpdatePayload(User $user, UserRole $role): array
    {
        return [
            ...$user->nameComponents(),
            'email' => $user->email,
            'role' => $role->value,
            'status' => $user->status->value,
            'department' => $user->department,
            'phone' => $user->phone,
        ];
    }

    private function provisionedSuperAdmin(): User
    {
        $this->seed(SuperAdminSeeder::class);

        return User::query()->where('email', self::EMAIL)->firstOrFail();
    }

    public function test_seeder_provisions_the_exact_protected_account_with_a_hashed_password(): void
    {
        $superAdmin = $this->provisionedSuperAdmin();

        $this->assertSame(UserRole::SuperAdministrator, $superAdmin->role);
        $this->assertSame(UserStatus::Active, $superAdmin->status);
        $this->assertTrue($superAdmin->isProtected());
        $this->assertNotSame(self::INITIAL_PASSWORD, $superAdmin->password);
        $this->assertTrue(Hash::check(self::INITIAL_PASSWORD, $superAdmin->password));
    }

    public function test_seeder_is_idempotent_and_preserves_a_later_password_change(): void
    {
        $superAdmin = $this->provisionedSuperAdmin();
        $originalId = $superAdmin->getKey();
        $newPassword = 'ChangedPassword456!';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => self::INITIAL_PASSWORD,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->seed(SuperAdminSeeder::class);

        $superAdmin->refresh();
        $this->assertSame($originalId, $superAdmin->getKey());
        $this->assertSame(1, User::query()->where('email', self::EMAIL)->count());
        $this->assertTrue(Hash::check($newPassword, $superAdmin->password));
        $this->assertFalse(Hash::check(self::INITIAL_PASSWORD, $superAdmin->password));
    }

    public function test_seeded_credentials_sign_in_only_through_the_super_admin_panel(): void
    {
        $superAdmin = $this->provisionedSuperAdmin();

        $this->post(route('super-admin.login'), [
            'email' => self::EMAIL,
            'password' => self::INITIAL_PASSWORD,
        ])->assertRedirect(route('super-admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_seeding_does_not_change_unrelated_existing_users(): void
    {
        $existing = User::factory()->warehouseStaff()->create([
            'name' => 'Existing Staff',
            'employee_id' => 'SA-0001',
        ]);

        $this->seed(SuperAdminSeeder::class);

        $existing->refresh();
        $this->assertSame('Existing Staff', $existing->name);
        $this->assertSame(UserRole::WarehouseStaff, $existing->role);
        $this->assertSame('SA-0001', $existing->employee_id);
        $this->assertSame('SA-0002', User::query()->where('email', self::EMAIL)->value('employee_id'));
    }

    public function test_normal_admin_role_dropdown_excludes_both_administrative_roles(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('value="administrator"', false)
            ->assertDontSee('value="super_administrator"', false);
    }

    public function test_normal_admin_cannot_create_admin_or_super_admin_by_direct_request(): void
    {
        $administrator = User::factory()->administrator()->create();

        foreach ([UserRole::Administrator, UserRole::SuperAdministrator] as $role) {
            $email = $role->value.'@example.com';

            $this->actingAs($administrator)
                ->post(route('admin.users.store'), $this->validUserPayload($role, $email))
                ->assertSessionHasErrors('role');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_normal_admin_cannot_promote_a_regular_user_to_an_administrative_role(): void
    {
        $administrator = User::factory()->administrator()->create();

        foreach ([UserRole::Administrator, UserRole::SuperAdministrator] as $role) {
            $staff = User::factory()->viewer()->create();

            $this->actingAs($administrator)
                ->put(route('admin.users.update', $staff), $this->validUpdatePayload($staff, $role))
                ->assertSessionHasErrors('role');

            $this->assertSame(UserRole::Viewer, $staff->fresh()->role);
        }
    }

    public function test_normal_admin_cannot_modify_or_deactivate_the_protected_super_admin(): void
    {
        $protected = $this->provisionedSuperAdmin();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('admin.users.edit', $protected))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->put(route('admin.users.update', $protected), $this->validUpdatePayload($protected, UserRole::Viewer))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->patch(route('admin.users.toggle-status', $protected))
            ->assertForbidden();

        $protected->refresh();
        $this->assertSame(UserRole::SuperAdministrator, $protected->role);
        $this->assertSame(UserStatus::Active, $protected->status);
    }

    public function test_super_admin_can_create_update_and_deactivate_an_admin_account(): void
    {
        $superAdmin = $this->provisionedSuperAdmin();
        $email = 'new.admin@example.com';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('admin.users.store'), $this->validUserPayload(UserRole::Administrator, $email))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $administrator = User::query()->where('email', $email)->firstOrFail();
        $payload = $this->validUpdatePayload($administrator, UserRole::Administrator);
        $payload['first_name'] = 'Updated';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->put(route('admin.users.update', $administrator), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $administrator))
            ->assertSessionHasNoErrors();

        $administrator->refresh();
        $this->assertSame('Updated', $administrator->first_name);
        $this->assertSame(UserStatus::Inactive, $administrator->status);
    }

    public function test_even_super_admin_cannot_create_another_super_admin(): void
    {
        $superAdmin = $this->provisionedSuperAdmin();
        $email = 'second.super@example.com';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(
                route('admin.users.store'),
                $this->validUserPayload(UserRole::SuperAdministrator, $email)
            )->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertSame(1, User::superAdministrators()->count());
    }

    public function test_protected_super_admin_cannot_change_its_email_or_delete_itself(): void
    {
        $superAdmin = $this->provisionedSuperAdmin();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('profile.update'), [
                'name' => $superAdmin->name,
                'email' => 'changed@example.com',
            ])->assertSessionHasErrors('email');

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->delete(route('profile.destroy'), ['password' => self::INITIAL_PASSWORD])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->getKey(),
            'email' => self::EMAIL,
            'is_protected' => true,
        ]);
    }
}
