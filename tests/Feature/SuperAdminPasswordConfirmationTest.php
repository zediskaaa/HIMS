<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuthenticationContext;
use App\Support\SuperAdminPasswordConfirmation;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminPasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const SUPER_ADMIN_EMAIL = 'zediskaaa@gmail.com';

    private const SUPER_ADMIN_PASSWORD = 'SuperAdminZediskaaa123!';

    private function getSuperAdmin(): User
    {
        $this->seed(SuperAdminSeeder::class);

        return User::query()->where('email', self::SUPER_ADMIN_EMAIL)->firstOrFail();
    }

    private function validCreatePayload(string $email = 'new.user@example.com'): array
    {
        return [
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::WarehouseStaff->value,
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ];
    }

    private function validUpdatePayload(User $user): array
    {
        $components = $user->nameComponents();

        return [
            'surname' => $user->surname ?? $components['surname'] ?? 'Dela Cruz',
            'first_name' => $user->first_name ?? $components['first_name'] ?? 'Juan',
            'middle_name' => $user->middle_name ?? $components['middle_name'] ?? 'Santos',
            'email' => $user->email,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'department' => $user->department ?? 'Warehouse',
            'phone' => $user->phone ?? '09171234567',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Password Confirmation Endpoint (POST /admin/users/confirm-password)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_confirm_password_endpoint(): void
    {
        $this->postJson(route('admin.users.confirm-password'), [
            'current_password' => 'any-password',
        ])->assertUnauthorized();
    }

    public function test_non_super_admin_cannot_access_confirm_password_endpoint(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => 'password',
            ])->assertForbidden();

        $staff = User::factory()->viewer()->create();

        $this->actingAs($staff, AuthenticationContext::WEB_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => 'password',
            ])->assertForbidden();
    }

    public function test_super_admin_confirm_password_requires_password(): void
    {
        $superAdmin = $this->getSuperAdmin();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => '',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_super_admin_confirm_password_rejects_incorrect_password(): void
    {
        $superAdmin = $this->getSuperAdmin();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => 'IncorrectPassword123!',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_super_admin_confirm_password_succeeds_and_issues_token(): void
    {
        $superAdmin = $this->getSuperAdmin();

        $response = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => self::SUPER_ADMIN_PASSWORD,
            ])->assertOk()
            ->assertJsonStructure(['status', 'token']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame(40, strlen($token));

        $sessionData = session(SuperAdminPasswordConfirmation::SESSION_KEY);
        $this->assertIsArray($sessionData);
        $this->assertSame($token, $sessionData['token']);
        $this->assertSame($superAdmin->id, $sessionData['user_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Super Admin: Add / Create User
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_create_user_fails_without_password_confirmation(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $payload = $this->validCreatePayload('unconfirmed@example.com');

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('admin.users.store'), $payload)
            ->assertSessionHasErrors(['current_password']);

        $this->assertDatabaseMissing('users', ['email' => 'unconfirmed@example.com']);
    }

    public function test_super_admin_create_user_fails_with_incorrect_password(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $payload = $this->validCreatePayload('wrongpass@example.com');
        $payload['current_password'] = 'WrongPassword999!';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('admin.users.store'), $payload)
            ->assertSessionHasErrors(['current_password']);

        $this->assertDatabaseMissing('users', ['email' => 'wrongpass@example.com']);
    }

    public function test_super_admin_create_user_succeeds_with_valid_current_password(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $payload = $this->validCreatePayload('confirmed.direct@example.com');
        $payload['current_password'] = self::SUPER_ADMIN_PASSWORD;

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('admin.users.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'confirmed.direct@example.com']);
    }

    public function test_super_admin_create_user_succeeds_with_confirmation_token_and_burns_token(): void
    {
        $superAdmin = $this->getSuperAdmin();

        $tokenResponse = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => self::SUPER_ADMIN_PASSWORD,
            ])->assertOk();

        $token = $tokenResponse->json('token');

        $payload = $this->validCreatePayload('confirmed.token@example.com');
        $payload['super_admin_confirmation_token'] = $token;

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('admin.users.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'confirmed.token@example.com']);

        // Token cannot be replayed for another creation
        $replayPayload = $this->validCreatePayload('replay@example.com');
        $replayPayload['super_admin_confirmation_token'] = $token;

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('admin.users.store'), $replayPayload)
            ->assertSessionHasErrors(['current_password']);

        $this->assertDatabaseMissing('users', ['email' => 'replay@example.com']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Super Admin: Edit Account Details
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_update_user_fails_without_password_confirmation(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['first_name' => 'Original']);
        $payload = $this->validUpdatePayload($target);
        $payload['first_name'] = 'Tampered';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->put(route('admin.users.update', $target), $payload)
            ->assertSessionHasErrors(['current_password']);

        $this->assertSame('Original', $target->fresh()->first_name);
    }

    public function test_super_admin_update_user_fails_with_incorrect_password(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['first_name' => 'Original']);
        $payload = $this->validUpdatePayload($target);
        $payload['first_name'] = 'Tampered';
        $payload['current_password'] = 'IncorrectPass123!';

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->put(route('admin.users.update', $target), $payload)
            ->assertSessionHasErrors(['current_password']);

        $this->assertSame('Original', $target->fresh()->first_name);
    }

    public function test_super_admin_update_user_succeeds_with_valid_current_password(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['first_name' => 'Original']);
        $payload = $this->validUpdatePayload($target);
        $payload['first_name'] = 'UpdatedBySuperAdmin';
        $payload['current_password'] = self::SUPER_ADMIN_PASSWORD;

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->put(route('admin.users.update', $target), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('UpdatedBySuperAdmin', $target->fresh()->first_name);
    }

    public function test_super_admin_update_user_succeeds_with_token_and_burns_it(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['first_name' => 'Original']);

        $tokenResponse = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => self::SUPER_ADMIN_PASSWORD,
            ])->assertOk();

        $token = $tokenResponse->json('token');

        $payload = $this->validUpdatePayload($target);
        $payload['first_name'] = 'UpdatedWithToken';
        $payload['super_admin_confirmation_token'] = $token;

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->put(route('admin.users.update', $target), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('UpdatedWithToken', $target->fresh()->first_name);

        // Cannot reuse token for another update
        $target2 = User::factory()->viewer()->create(['first_name' => 'OriginalTwo']);
        $payload2 = $this->validUpdatePayload($target2);
        $payload2['first_name'] = 'ReplayAttempt';
        $payload2['super_admin_confirmation_token'] = $token;

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->put(route('admin.users.update', $target2), $payload2)
            ->assertSessionHasErrors(['current_password']);

        $this->assertSame('OriginalTwo', $target2->fresh()->first_name);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Super Admin: Deactivate User Account
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_deactivate_user_fails_without_password_confirmation(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target))
            ->assertSessionHasErrors(['current_password']);

        $this->assertTrue($target->fresh()->isActive());
    }

    public function test_super_admin_deactivate_user_fails_with_incorrect_password(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target), [
                'current_password' => 'WrongPassword123!',
            ])->assertSessionHasErrors(['current_password']);

        $this->assertTrue($target->fresh()->isActive());
    }

    public function test_super_admin_deactivate_user_succeeds_with_valid_password(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target), [
                'current_password' => self::SUPER_ADMIN_PASSWORD,
            ])->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $target->fresh()->status);
    }

    public function test_super_admin_deactivate_user_succeeds_with_token_and_burns_it(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $tokenResponse = $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->postJson(route('admin.users.confirm-password'), [
                'current_password' => self::SUPER_ADMIN_PASSWORD,
            ])->assertOk();

        $token = $tokenResponse->json('token');

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target), [
                'super_admin_confirmation_token' => $token,
            ])->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $target->fresh()->status);

        // Token cannot be reused
        $target2 = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target2), [
                'super_admin_confirmation_token' => $token,
            ])->assertSessionHasErrors(['current_password']);

        $this->assertTrue($target2->fresh()->isActive());
    }

    public function test_super_admin_reactivate_user_does_not_require_password_confirmation(): void
    {
        $superAdmin = $this->getSuperAdmin();
        $target = User::factory()->viewer()->inactive()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target))
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->isActive());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Ordinary Administrator (Non-Super-Admin) Workflows Preserved
    // ─────────────────────────────────────────────────────────────────────────

    public function test_ordinary_admin_creates_user_without_super_admin_password(): void
    {
        $admin = User::factory()->administrator()->create();
        $payload = $this->validCreatePayload('admin.created@example.com');

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->post(route('admin.users.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'admin.created@example.com']);
    }

    public function test_ordinary_admin_updates_user_without_super_admin_password(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->viewer()->create(['first_name' => 'BeforeUpdate']);
        $payload = $this->validUpdatePayload($target);
        $payload['first_name'] = 'AfterUpdate';

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->put(route('admin.users.update', $target), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('AfterUpdate', $target->fresh()->first_name);
    }

    public function test_ordinary_admin_deactivates_user_without_super_admin_password(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->viewer()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->patch(route('admin.users.toggle-status', $target))
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $target->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. UI & View Structure Checks
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_sees_password_modal_in_app_layout(): void
    {
        $superAdmin = $this->getSuperAdmin();
        User::factory()->viewer()->create();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-super-admin-password-modal', false)
            ->assertSee('Confirm Your Password')
            ->assertSee('This action requires Super Admin confirmation')
            ->assertSee('data-super-admin-password-input', false)
            ->assertSee('data-super-admin-deactivate="true"', false);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('data-super-admin-password="create"', false);

        $target = User::factory()->viewer()->create();
        $this->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('data-super-admin-password="edit"', false);

        $this->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('data-super-admin-deactivate="true"', false);
    }

    public function test_regular_admin_does_not_see_password_modal(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('data-super-admin-password-modal', false)
            ->assertDontSee('data-super-admin-deactivate="true"', false);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('data-super-admin-password="create"', false)
            ->assertSee('data-confirm-title="Create staff user"', false);

        $target = User::factory()->viewer()->create();
        $this->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertDontSee('data-super-admin-password="edit"', false)
            ->assertSee('data-confirm-title="Confirm account changes"', false);
    }
}
