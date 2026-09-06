<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Services\LoginMfaService;
use App\Support\AuthenticationContext;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class LoginMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_is_disabled_by_default_and_admin_and_super_admin_can_persist_the_setting(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->assertFalse($admin->mfa_enabled);

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->patch(route('profile.mfa.update'), ['mfa_enabled' => true])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('mfa_success');

        $this->assertTrue($admin->fresh()->mfa_enabled);
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Multi-Factor Authentication')
            ->assertSee('ON');

        $this->patch(route('profile.mfa.update'), ['mfa_enabled' => false])
            ->assertRedirect(route('profile.edit'));
        $this->assertFalse($admin->fresh()->mfa_enabled);

        $superAdmin = User::factory()->superAdministrator()->create();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->patch(route('profile.mfa.update'), ['mfa_enabled' => true])
            ->assertRedirect(route('profile.edit'));
        $this->assertTrue($superAdmin->fresh()->mfa_enabled);
    }

    public function test_staff_cannot_see_or_change_the_mfa_setting(): void
    {
        $staff = User::factory()->warehouseStaff()->create();

        $this->actingAs($staff, AuthenticationContext::WEB_GUARD)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Multi-Factor Authentication');

        $this->patch(route('profile.mfa.update'), ['mfa_enabled' => true])
            ->assertForbidden();
        $this->assertFalse($staff->fresh()->mfa_enabled);
    }

    public function test_admin_with_mfa_off_logs_in_without_an_otp(): void
    {
        Notification::fake();
        $admin = $this->admin(mfa: false);

        $this->post(route('admin.login.store'), $this->credentials($admin))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin, AuthenticationContext::ADMIN_GUARD);
        Notification::assertNothingSent();
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
    }

    public function test_super_admin_with_mfa_off_logs_in_without_an_otp(): void
    {
        Notification::fake();
        $superAdmin = $this->superAdmin(mfa: false);

        $this->post(route('super-admin.login.store'), $this->credentials($superAdmin))
            ->assertRedirect(route('super-admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);
        Notification::assertNothingSent();
    }

    public function test_admin_with_mfa_on_is_a_guest_until_the_correct_otp_is_verified(): void
    {
        $admin = $this->admin();
        $notification = $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');
        $state = session(LoginMfaService::SESSION_KEY);

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertNotSame($notification->otp, $state['otp_hash']);
        $this->assertTrue(Hash::check($notification->otp, $state['otp_hash']));
        $this->assertNull($admin->fresh()->last_login_at);

        $this->get(route('admin.login.mfa'))
            ->assertOk()
            ->assertSee('Verify your sign-in')
            ->assertDontSee($notification->otp);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);

        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin, AuthenticationContext::ADMIN_GUARD);
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_super_admin_with_mfa_on_uses_only_the_super_admin_guard(): void
    {
        $superAdmin = $this->superAdmin();
        $notification = $this->beginMfa(
            $superAdmin,
            'super-admin.login.store',
            'super-admin.login.mfa',
        );

        $this->get(route('super-admin.dashboard'))
            ->assertRedirect(route('super-admin.login'));
        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);

        $this->post(route('super-admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('super-admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_wrong_password_never_creates_or_sends_a_challenge(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
        Notification::assertNothingSent();
    }

    public function test_incorrect_and_expired_otps_are_rejected(): void
    {
        $admin = $this->admin();
        $notification = $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');
        $wrongOtp = $notification->otp === '000000' ? '999999' : '000000';

        $this->post(route('admin.login.mfa.verify'), ['otp' => $wrongOtp])
            ->assertSessionHasErrors(['otp' => 'This verification code is invalid.']);
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);

        $this->travel(config('auth.login_mfa.expire') + 1)->minutes();
        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertSessionHasErrors(['otp' => 'This verification code has expired. Request a new code.']);
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_a_successful_otp_is_one_time_and_cannot_be_reused(): void
    {
        $admin = $this->admin();
        $notification = $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');

        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp]);
        $this->app['auth']->guard(AuthenticationContext::ADMIN_GUARD)->logout();
        $this->app['auth']->forgetGuards();

        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_resend_has_a_cooldown_and_replaces_the_previous_code(): void
    {
        config()->set('auth.login_mfa.resend_cooldown', 1);
        $admin = $this->admin();
        $first = $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');

        $this->post(route('admin.login.mfa.resend'))
            ->assertSessionHasErrors('otp');

        $this->travel(2)->seconds();
        $this->post(route('admin.login.mfa.resend'))
            ->assertSessionHas('status', 'A new verification code has been sent.');
        $second = Notification::sent($admin, LoginMfaOtp::class)->last();

        $this->assertNotSame($first->otp, $second->otp);
        $this->post(route('admin.login.mfa.verify'), ['otp' => $first->otp])
            ->assertSessionHasErrors('otp');
        $this->post(route('admin.login.mfa.verify'), ['otp' => $second->otp])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin, AuthenticationContext::ADMIN_GUARD);
    }

    public function test_reposting_valid_credentials_during_the_cooldown_does_not_send_more_email(): void
    {
        $admin = $this->admin();
        $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');

        $this->post(route('admin.login.store'), $this->credentials($admin))
            ->assertRedirect(route('admin.login.mfa'))
            ->assertSessionHas('status', 'A verification code was recently sent.');

        Notification::assertSentToTimes($admin, LoginMfaOtp::class, 1);
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_too_many_incorrect_attempts_blocks_even_the_correct_code_until_resend(): void
    {
        $admin = $this->admin();
        $notification = $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');
        $wrongOtp = $notification->otp === '000000' ? '999999' : '000000';

        for ($attempt = 1; $attempt <= config('auth.login_mfa.max_attempts'); $attempt++) {
            $this->post(route('admin.login.mfa.verify'), ['otp' => $wrongOtp]);
        }

        $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp])
            ->assertSessionHasErrors(['otp' => 'Too many incorrect attempts. Request a new code.']);
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_verification_endpoints_require_a_valid_matching_pending_attempt(): void
    {
        $this->get(route('admin.login.mfa'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->post(route('super-admin.login.mfa.verify'), ['otp' => '123456'])
            ->assertRedirect(route('super-admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_remember_me_cookie_is_created_only_after_mfa_verification(): void
    {
        $admin = $this->admin();
        Notification::fake();

        $passwordResponse = $this->post(route('admin.login.store'), [
            ...$this->credentials($admin),
            'remember' => true,
        ]);
        $notification = Notification::sent($admin, LoginMfaOtp::class)->first();
        $recaller = $this->app['auth']->guard(AuthenticationContext::ADMIN_GUARD)->getRecallerName();

        $this->assertNull($passwordResponse->getCookie($recaller));
        $otpResponse = $this->post(route('admin.login.mfa.verify'), ['otp' => $notification->otp]);
        $this->assertNotNull($otpResponse->getCookie($recaller));
    }

    public function test_login_otp_email_contains_security_and_expiration_information(): void
    {
        $admin = $this->admin();
        $notification = $this->beginMfa($admin, 'admin.login.store', 'admin.login.mfa');
        $mail = $notification->toMail($admin);
        $html = $mail->render();

        $this->assertStringContainsString($notification->otp, $html);
        $this->assertStringContainsString('Login verification', $html);
        $this->assertStringContainsString('expires in', $html);
        $this->assertStringContainsString('Do not share this code', $html);
    }

    public function test_mail_failure_aborts_the_pending_login_without_authenticating(): void
    {
        $admin = $this->admin();
        Log::spy();

        $dispatcher = $this->mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP authentication failed.'));

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), $this->credentials($admin))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
        Log::shouldHaveReceived('error')->once();
    }

    private function admin(bool $mfa = true): User
    {
        return User::factory()->administrator()->create([
            'password' => bcrypt('password'),
            'mfa_enabled' => $mfa,
        ]);
    }

    private function superAdmin(bool $mfa = true): User
    {
        return User::factory()->superAdministrator()->create([
            'password' => bcrypt('password'),
            'mfa_enabled' => $mfa,
        ]);
    }

    /** @return array{email: string, password: string} */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }

    private function beginMfa(User $user, string $loginRoute, string $mfaRoute): LoginMfaOtp
    {
        Notification::fake();

        $this->post(route($loginRoute), $this->credentials($user))
            ->assertRedirect(route($mfaRoute));

        $notification = Notification::sent($user, LoginMfaOtp::class)->last();
        $this->assertInstanceOf(LoginMfaOtp::class, $notification);

        return $notification;
    }
}
