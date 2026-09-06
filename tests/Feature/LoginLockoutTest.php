<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Services\LoginLockoutService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_attempts_remain_available_before_the_cooldown_and_first_lock(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        $this->failedLogin($user)->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 4 attempts remaining.',
        ]);
        $this->assertSame(1, $user->refresh()->failed_login_attempts);
        $this->assertNull($user->login_retry_at);

        $this->failedLogin($user)->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 3 attempts remaining.',
        ]);
        $this->assertSame(2, $user->refresh()->failed_login_attempts);

        $this->failedLogin($user)->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 2 attempts remaining.',
        ])->assertSessionMissing(LoginLockoutService::SESSION_KEY);
        $this->assertSame(3, $user->refresh()->failed_login_attempts);
        $this->assertNull($user->login_retry_at);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-login-cooldown', false);

        $this->failedLogin($user)->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 1 attempt remaining.',
        ]);
        $this->assertSame(4, $user->refresh()->failed_login_attempts);
        $this->assertNull($user->login_retry_at);

        $this->failedLogin($user)
            ->assertSessionHasErrors([
                'email' => 'Incorrect email or password. You have 0 attempts remaining. Please try again after 20 minutes.',
            ])
            ->assertSessionHas(LoginLockoutService::SESSION_KEY, function (array $restriction) use ($user): bool {
                return $restriction['guard'] === AuthenticationContext::WEB_GUARD
                    && $restriction['email'] === $user->email
                    && $restriction['status'] === LoginLockoutService::WAITING
                    && $restriction['attempts_remaining'] === 0
                    && $restriction['expires_at'] === $user->refresh()->login_retry_at->getTimestamp();
            });
        $this->assertSame(5, $user->refresh()->failed_login_attempts);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-login-cooldown', false)
            ->assertSee('data-login-cooldown-value', false)
            ->assertSee('You have 0 attempts remaining.')
            ->assertSee('Try again in')
            ->assertSee('data-login-cooldown-expires-at="'.$user->login_retry_at->getTimestamp().'"', false);

        $this->post(route('login'), $this->credentials($user))
            ->assertSessionHasErrors([
                'email' => 'Incorrect email or password. You have 0 attempts remaining. Please try again after 20 minutes.',
            ]);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertSame(5, $user->refresh()->failed_login_attempts);

        $this->travelPast($user->login_retry_at);
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-login-cooldown', false);
        $this->failedLogin($user)->assertSessionHasErrors([
            'email' => 'Your account is temporarily locked. Please try again in 30 minutes.',
        ]);

        $user->refresh();
        $this->assertTrue($user->isTemporarilyLocked());
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(1, $user->login_lockout_count);

        $lockAudit = AuditLog::query()
            ->where('action', AuditAction::TemporarilyLockedUser->value)
            ->sole();
        $this->assertNull($lockAudit->user_id);
        $this->assertSame('System', $lockAudit->actor_name);
        $this->assertSame($user->getMorphClass(), $lockAudit->target_type);
        $this->assertSame((string) $user->getKey(), $lockAudit->target_id);
        $this->assertSame(30, $lockAudit->new_values['lock_duration_minutes']);
        $auditPayload = strtolower((string) json_encode([
            $lockAudit->description,
            $lockAudit->old_values,
            $lockAudit->new_values,
        ]));
        $this->assertStringNotContainsString('password', $auditPayload);
        $this->assertStringNotContainsString('otp', $auditPayload);
        $this->assertStringNotContainsString('hash', $auditPayload);
        $this->assertStringNotContainsString('secret', $auditPayload);

        $this->post(route('login'), $this->credentials($user))
            ->assertSessionHasErrors('email');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertSame(1, AuditLog::query()
            ->where('action', AuditAction::TemporarilyLockedUser->value)
            ->count());

        $this->travelPast($user->login_locked_until);
        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->login_retry_at);
        $this->assertNull($user->login_locked_until);
        $this->assertSame(1, $user->login_lockout_count);
    }

    public function test_repeated_lockout_cycles_progress_to_one_two_and_four_hours(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $durations = [30, 60, 120, 240, 240];

        foreach ($durations as $cycle => $minutes) {
            $this->runFailedCycle($user);
            $user->refresh();

            $this->assertSame($cycle + 1, $user->login_lockout_count);
            $this->assertEqualsWithDelta(
                $minutes * 60,
                now()->diffInSeconds($user->login_locked_until, false),
                1,
            );

            $this->travelPast($user->login_locked_until);
        }
    }

    public function test_successful_login_resets_only_the_current_failed_attempt_cycle(): void
    {
        $user = User::factory()->warehouseStaff()->create([
            'login_lockout_count' => 2,
        ]);

        $this->failedLogin($user);
        $this->failedLogin($user);
        $this->assertSame(2, $user->refresh()->failed_login_attempts);

        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->login_retry_at);
        $this->assertSame(2, $user->login_lockout_count);
    }

    public function test_mfa_password_stage_does_not_reset_attempts_until_otp_succeeds(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create(['mfa_enabled' => true]);

        $this->failedLogin($admin, 'admin.login.store');
        $this->failedLogin($admin, 'admin.login.store');

        $this->post(route('admin.login.store'), $this->credentials($admin))
            ->assertRedirect(route('admin.login.mfa'));
        $this->assertSame(2, $admin->refresh()->failed_login_attempts);
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);

        $notification = Notification::sent($admin, LoginMfaOtp::class)->sole();
        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin, AuthenticationContext::ADMIN_GUARD);
        $this->assertSame(0, $admin->refresh()->failed_login_attempts);
    }

    public function test_a_lock_created_while_mfa_is_pending_prevents_final_authentication(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create(['mfa_enabled' => true]);

        $this->post(route('admin.login.store'), $this->credentials($admin))
            ->assertRedirect(route('admin.login.mfa'));
        $notification = Notification::sent($admin, LoginMfaOtp::class)->sole();

        $admin->forceFill([
            'login_locked_until' => now()->addMinutes(30),
            'login_lockout_count' => 1,
        ])->saveQuietly();

        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertTrue($admin->refresh()->isTemporarilyLocked());
    }

    public function test_password_expiration_does_not_reset_attempts_until_password_renewal_finishes(): void
    {
        $user = User::factory()->warehouseStaff()->create([
            'password_changed_at' => now()->subDays(91),
        ]);

        $this->failedLogin($user);
        $this->failedLogin($user);
        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('password.expired'));

        $this->assertSame(2, $user->refresh()->failed_login_attempts);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);

        $this->put(route('password.expired.update'), [
            'password' => 'RenewedPassword1!',
            'password_confirmation' => 'RenewedPassword1!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
        $this->assertSame(0, $user->refresh()->failed_login_attempts);
    }

    public function test_super_admin_keeps_existing_throttle_without_persisted_lockout_state(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();

        for ($attempt = 0; $attempt < 7; $attempt++) {
            $this->failedLogin($superAdmin, 'super-admin.login.store');
        }

        $superAdmin->refresh();
        $this->assertSame(0, $superAdmin->failed_login_attempts);
        $this->assertSame(0, $superAdmin->login_lockout_count);
        $this->assertNull($superAdmin->login_retry_at);
        $this->assertNull($superAdmin->login_locked_until);
    }

    public function test_super_admin_can_confirm_and_auditably_unlock_a_locked_staff_account(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();
        $staff = User::factory()->warehouseStaff()->create();
        $this->runFailedCycle($staff);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('admin.users.index', ['search' => $staff->email]))
            ->assertOk()
            ->assertSee('Temporarily Locked')
            ->assertSee('Are you sure you want to unlock this account?')
            ->assertSee('data-loading-text="Unlocking account..."', false);

        $this->patch(route('admin.users.unlock', $staff))
            ->assertRedirect()
            ->assertSessionHas('success', "{$staff->name} can now attempt to sign in again.");

        $staff->refresh();
        $this->assertFalse($staff->isTemporarilyLocked());
        $this->assertSame(0, $staff->failed_login_attempts);
        $this->assertNull($staff->login_retry_at);
        $this->assertSame(1, $staff->login_lockout_count);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->getKey(),
            'action' => AuditAction::UnlockedUser->value,
            'target_type' => $staff->getMorphClass(),
            'target_id' => (string) $staff->getKey(),
        ]);
        $this->patch(route('admin.users.unlock', $staff))
            ->assertSessionHasErrors('account');
        $this->assertSame(1, AuditLog::query()
            ->where('action', AuditAction::UnlockedUser->value)
            ->count());

        $this->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Temporarily Locked User')
            ->assertSee('Unlocked User');
        $this->get(route('admin.users.index', ['search' => $staff->email]))
            ->assertOk()
            ->assertDontSee('Are you sure you want to unlock this account?');

        $this->app['auth']->guard(AuthenticationContext::SUPER_ADMIN_GUARD)->logout();
        $this->app['auth']->forgetGuards();
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-login-cooldown', false);
        $this->post(route('login'), $this->credentials($staff))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($staff, AuthenticationContext::WEB_GUARD);
    }

    public function test_super_admin_can_unlock_an_admin_but_admin_staff_and_self_unlocks_are_forbidden(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();
        $admin = User::factory()->administrator()->create([
            'login_locked_until' => now()->addHour(),
            'login_lockout_count' => 2,
        ]);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('admin.users.unlock', $admin))
            ->assertRedirect();
        $this->assertFalse($admin->refresh()->isTemporarilyLocked());

        $staff = $this->lockedUser();
        $regularAdmin = User::factory()->administrator()->create();
        $this->app['auth']->forgetGuards();
        $this->actingAs($regularAdmin, AuthenticationContext::ADMIN_GUARD)
            ->get(route('admin.users.index', ['search' => $staff->email]))
            ->assertOk()
            ->assertSee('Temporarily Locked')
            ->assertDontSee('Are you sure you want to unlock this account?');
        $this->patch(route('admin.users.unlock', $staff))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($staff, AuthenticationContext::WEB_GUARD)
            ->patch(route('admin.users.unlock', $staff))
            ->assertForbidden();

        $selfLockedAdmin = User::factory()->administrator()->create([
            'login_locked_until' => now()->addMinutes(30),
        ]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($selfLockedAdmin, AuthenticationContext::ADMIN_GUARD)
            ->patch(route('admin.users.unlock', $selfLockedAdmin))
            ->assertForbidden();
    }

    public function test_locked_account_cannot_bypass_the_policy_through_the_token_api(): void
    {
        $user = $this->lockedUser();

        $this->postJson('/api/v1/auth/token', [
            ...$this->credentials($user),
            'device_name' => 'security-test',
        ])->assertStatus(429)
            ->assertJsonPath('code', 'LOGIN_LOCKED')
            ->assertJsonPath('retry_after', 1800)
            ->assertHeader('Retry-After', '1800');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_api_requires_mfa_without_resetting_the_password_failure_cycle(): void
    {
        $admin = User::factory()->administrator()->create(['mfa_enabled' => true]);
        $this->failedLogin($admin, 'admin.login.store');
        $this->failedLogin($admin, 'admin.login.store');

        $this->postJson('/api/v1/auth/token', [
            ...$this->credentials($admin),
            'device_name' => 'security-test',
        ])->assertStatus(428)
            ->assertJsonPath('code', 'MFA_REQUIRED');

        $this->assertSame(2, $admin->refresh()->failed_login_attempts);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_accounts_remain_generic_and_do_not_gain_lockout_state(): void
    {
        $user = User::factory()->warehouseStaff()->inactive()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->failedLogin($user);
        }

        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->login_retry_at);
        $this->assertNull($user->login_locked_until);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_unknown_identifiers_receive_the_same_progressive_feedback_without_account_enumeration(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $unknown = 'not-an-account@example.com';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->failedLogin($user)->assertSessionHasErrors([
                'email' => 'Incorrect email or password. You have '.(5 - $attempt).' attempts remaining.',
            ]);
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$attempt}"])
                ->post(route('login'), [
                    'email' => $unknown,
                    'password' => 'WrongPassword1!',
                ])->assertSessionHasErrors([
                    'email' => 'Incorrect email or password. You have '.(5 - $attempt).' attempts remaining.',
                ]);
        }

        for ($attempt = 3; $attempt <= 4; $attempt++) {
            $message = 'Incorrect email or password. You have '.(5 - $attempt).' attempts remaining.';
            $this->failedLogin($user)->assertSessionHasErrors(['email' => $message]);
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$attempt}"])
                ->post(route('login'), [
                    'email' => $unknown,
                    'password' => 'WrongPassword1!',
                ])->assertSessionHasErrors(['email' => $message]);
        }

        $message = 'Incorrect email or password. You have 0 attempts remaining. Please try again after 20 minutes.';
        $this->failedLogin($user)->assertSessionHasErrors(['email' => $message]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post(route('login'), [
                'email' => $unknown,
                'password' => 'WrongPassword1!',
            ])->assertSessionHasErrors(['email' => $message]);
    }

    public function test_only_wrong_passwords_on_the_accounts_correct_panel_increment_its_counter(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->failedLogin($admin)->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 4 attempts remaining.',
        ]);
        $this->failedLogin($admin)->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 3 attempts remaining.',
        ]);
        $this->assertSame(0, $admin->refresh()->failed_login_attempts);
        $this->assertNull($admin->last_failed_login_at);

        $this->failedLogin($admin, 'admin.login.store')
            ->assertSessionHasErrors([
                'email' => 'Incorrect email or password. You have 4 attempts remaining.',
            ]);
        $this->assertSame(1, $admin->refresh()->failed_login_attempts);
        $this->assertNotNull($admin->last_failed_login_at);
    }

    public function test_failed_attempts_decay_after_fifteen_minutes_and_wrong_panel_attempts_do_not_refresh_them(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->failedLogin($admin, 'admin.login.store');
        $this->failedLogin($admin, 'admin.login.store');
        $lastFailure = $admin->refresh()->last_failed_login_at;

        $this->travel(16)->minutes();
        $this->post(route('login'), $this->credentials($admin))
            ->assertSessionHas('wrong_panel.message');

        $admin->refresh();
        $this->assertSame(2, $admin->failed_login_attempts);
        $this->assertTrue($admin->last_failed_login_at->equalTo($lastFailure));

        $this->failedLogin($admin, 'admin.login.store')
            ->assertSessionHasErrors([
                'email' => 'Incorrect email or password. You have 4 attempts remaining.',
            ]);

        $admin->refresh();
        $this->assertSame(1, $admin->failed_login_attempts);
        $this->assertTrue($admin->last_failed_login_at->isAfter($lastFailure));
    }

    public function test_valid_credentials_on_the_wrong_panel_do_not_bypass_an_active_lock(): void
    {
        $admin = User::factory()->administrator()->create([
            'login_locked_until' => now()->addMinutes(30),
            'login_lockout_count' => 1,
        ]);
        $lockedUntil = $admin->login_locked_until;

        $this->post(route('login'), $this->credentials($admin))
            ->assertSessionHasErrors([
                'email' => 'Your account is temporarily locked. Please try again in 30 minutes.',
            ])
            ->assertSessionMissing('wrong_panel');

        $admin->refresh();
        $this->assertTrue($admin->login_locked_until->equalTo($lockedUntil));
        $this->assertSame(1, $admin->login_lockout_count);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_login_countdown_uses_the_server_deadline_and_revalidates_on_submit(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('const startLoginCooldown = () => {', $script);
        $this->assertStringContainsString('dataset.loginCooldownExpiresAt', $script);
        $this->assertStringContainsString('dataset.loginCooldownServerNow', $script);
        $this->assertStringContainsString('performance.now()', $script);
        $this->assertStringContainsString('submit.disabled = active;', $script);
        $this->assertStringContainsString("form.addEventListener('submit'", $script);
        $this->assertStringContainsString('The next submission is always revalidated by Laravel.', $script);
        $this->assertStringContainsString('startLoginCooldown();', $script);
    }

    private function runFailedCycle(User $user): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $user->refresh();

            if ($user->login_retry_at?->isFuture()) {
                $this->travelPast($user->login_retry_at);
            }

            $this->failedLogin($user);
        }
    }

    private function failedLogin(User $user, string $route = 'login')
    {
        return $this->post(route($route), [
            'email' => $user->email,
            'password' => 'WrongPassword1!',
        ]);
    }

    /** @return array{email: string, password: string} */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }

    private function lockedUser(): User
    {
        return User::factory()->warehouseStaff()->create([
            'failed_login_attempts' => 5,
            'login_retry_at' => now()->subSecond(),
            'login_locked_until' => now()->addMinutes(30),
            'login_lockout_count' => 2,
        ]);
    }

    private function travelPast($timestamp): void
    {
        $this->travelTo($timestamp->copy()->addSecond());
    }
}
