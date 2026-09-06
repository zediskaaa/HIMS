<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Services\PasswordExpirationService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordExpirationTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'CurrentPassword1!';

    private const NEW_PASSWORD = 'FreshPassword2!';

    public function test_password_expiration_uses_the_exact_ninety_day_boundary(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(90)->addSecond(),
        ]);

        $this->assertFalse($user->passwordHasExpired());

        $user->forceFill(['password_changed_at' => now()->subDays(90)])->save();
        $this->assertTrue($user->fresh()->passwordHasExpired());

        $user->forceFill(['password_changed_at' => null])->save();
        $this->assertFalse($user->fresh()->passwordHasExpired());
    }

    public function test_a_non_expired_staff_password_logs_in_normally(): void
    {
        $user = User::factory()->warehouseStaff()->create([
            'password' => self::CURRENT_PASSWORD,
            'password_changed_at' => now()->subDays(90)->addSecond(),
        ]);

        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
    }

    public function test_expired_staff_must_change_password_before_authentication(): void
    {
        $user = $this->expiredUser('staff');

        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('password.expired'))
            ->assertSessionHas(PasswordExpirationService::SESSION_KEY);

        $this->assertGuest(AuthenticationContext::WEB_GUARD);

        $this->get(route('password.expired'))
            ->assertOk()
            ->assertSee('Password Expired')
            ->assertSee('Your password has expired. Please create a new password to continue.')
            ->assertSee('Password must be at least 8 characters long')
            ->assertSee('data-loading-text="Updating password..."', false);

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->put(route('password.expired.update'), [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('status', 'Password updated successfully. You may now continue.');

        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertTrue($user->fresh()->password_changed_at->isToday());
        $this->assertFalse($user->fresh()->passwordHasExpired());
        $this->assertNull(session(PasswordExpirationService::SESSION_KEY));
        $this->assertNotNull(session(EnforceSessionInactivity::LAST_ACTIVITY_AT));
    }

    public function test_expired_admin_without_mfa_uses_the_admin_flow(): void
    {
        $user = $this->expiredUser('admin');

        $this->post(route('admin.login.store'), $this->credentials($user))
            ->assertRedirect(route('admin.password.expired'));

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->get(route('admin.password.expired'))->assertOk()->assertSee('Password Expired');

        $this->put(route('admin.password.expired.update'), [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user, AuthenticationContext::ADMIN_GUARD);
    }

    public function test_expired_super_admin_without_mfa_uses_the_super_admin_flow(): void
    {
        $user = $this->expiredUser('super-admin');

        $this->post(route('super-admin.login.store'), $this->credentials($user))
            ->assertRedirect(route('super-admin.password.expired'));

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);

        $this->put(route('super-admin.password.expired.update'), [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('super-admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user, AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_mfa_is_completed_before_an_expired_admin_can_change_the_password(): void
    {
        Notification::fake();
        $user = $this->expiredUser('admin', mfa: true);

        $this->post(route('admin.login.store'), $this->credentials($user))
            ->assertRedirect(route('admin.login.mfa'));

        $this->get(route('admin.password.expired'))->assertRedirect(route('admin.login'));

        $notification = Notification::sent($user, LoginMfaOtp::class)->sole();

        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('admin.password.expired'));

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->get(route('admin.password.expired'))->assertOk();
    }

    public function test_mfa_is_completed_before_an_expired_super_admin_can_change_the_password(): void
    {
        Notification::fake();
        $user = $this->expiredUser('super-admin', mfa: true);

        $this->post(route('super-admin.login.store'), $this->credentials($user))
            ->assertRedirect(route('super-admin.login.mfa'));

        $notification = Notification::sent($user, LoginMfaOtp::class)->sole();

        $this->post(route('super-admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('super-admin.password.expired'));

        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_invalid_or_reused_passwords_do_not_complete_the_expired_flow(): void
    {
        $user = $this->expiredUser('staff');
        $this->post(route('login'), $this->credentials($user));

        $this->from(route('password.expired'))->put(route('password.expired.update'), [
            'password' => 'Password1',
            'password_confirmation' => 'DifferentPassword2!',
        ])->assertRedirect(route('password.expired'))
            ->assertSessionHasErrors([
                'password' => 'Passwords do not match.',
            ]);

        $this->from(route('password.expired'))->put(route('password.expired.update'), [
            'password' => self::CURRENT_PASSWORD,
            'password_confirmation' => self::CURRENT_PASSWORD,
        ])->assertRedirect(route('password.expired'))
            ->assertSessionHasErrors('password');

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    public function test_an_expired_authenticated_session_cannot_bypass_the_change_screen(): void
    {
        $user = $this->expiredUser('staff');

        $this->actingAs($user, AuthenticationContext::WEB_GUARD)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.expired'));

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->get(route('password.expired'))->assertOk();
    }

    public function test_an_expired_stateful_api_session_is_rejected_with_a_change_location(): void
    {
        $user = $this->expiredUser('staff');

        $this->actingAs($user, AuthenticationContext::WEB_GUARD)
            ->getJson('/api/v1/inventory-items')
            ->assertStatus(428)
            ->assertHeader('X-HIMS-Password-Expired', route('password.expired'))
            ->assertJsonPath('code', 'PASSWORD_EXPIRED');

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_expired_passwords_cannot_mint_or_use_api_tokens(): void
    {
        $user = $this->expiredUser('staff');

        $this->postJson('/api/v1/auth/token', [
            ...$this->credentials($user),
            'device_name' => 'test-client',
        ])->assertStatus(428)
            ->assertJsonMissingPath('token')
            ->assertJsonPath('code', 'PASSWORD_EXPIRED');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/inventory-items')
            ->assertStatus(428)
            ->assertHeader('X-HIMS-Password-Expired', route('login'))
            ->assertJsonPath('code', 'PASSWORD_EXPIRED');
    }

    public function test_the_change_session_can_be_cancelled_or_expire(): void
    {
        $user = $this->expiredUser('staff');
        $this->post(route('login'), $this->credentials($user));

        $this->post(route('password.expired.cancel'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing(PasswordExpirationService::SESSION_KEY);

        $this->post(route('login'), $this->credentials($user));
        $this->travel(16)->minutes();

        $this->get(route('password.expired'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    private function expiredUser(string $panel, bool $mfa = false): User
    {
        $factory = match ($panel) {
            'admin' => User::factory()->administrator(),
            'super-admin' => User::factory()->superAdministrator(),
            default => User::factory()->warehouseStaff(),
        };

        return $factory->create([
            'password' => self::CURRENT_PASSWORD,
            'password_changed_at' => now()->subDays(90),
            'mfa_enabled' => $mfa,
        ]);
    }

    /** @return array{email: string, password: string} */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => self::CURRENT_PASSWORD];
    }
}
