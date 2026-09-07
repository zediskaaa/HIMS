<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuthenticatorService;
use App\Services\AuthenticatorSetupService;
use App\Services\LoginMfaService;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticatorMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc_vector_and_provisioning_uri_contains_google_authenticator_parameters(): void
    {
        $totp = new Google2FA;
        $rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $totp->oathTotp($rfcSecret, 1));

        $user = User::factory()->create(['email' => 'clinician@example.com']);
        $uri = app(AuthenticatorService::class)->provisioningUri($user, $rfcSecret);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $parameters);

        $this->assertSame('otpauth', parse_url($uri, PHP_URL_SCHEME));
        $this->assertSame('totp', parse_url($uri, PHP_URL_HOST));
        $this->assertSame('/HIMS:clinician@example.com', rawurldecode((string) parse_url($uri, PHP_URL_PATH)));
        $this->assertSame($rfcSecret, $parameters['secret']);
        $this->assertSame('HIMS', $parameters['issuer']);
        $this->assertSame('SHA1', $parameters['algorithm']);
        $this->assertSame('6', $parameters['digits']);
        $this->assertSame('30', $parameters['period']);
    }

    public function test_setup_generates_a_real_qr_code_but_does_not_enable_authenticator_before_verification(): void
    {
        $user = User::factory()->warehouseStaff()->create(['password' => 'password']);

        $this->actingAs($user)
            ->post(route('profile.authenticator.setup'), ['current_password' => 'password'])
            ->assertRedirect(route('profile.edit'));

        $state = session(AuthenticatorSetupService::SESSION_KEY);
        $secret = Crypt::decryptString($state['secret']);

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertFalse($user->fresh()->authenticatorMfaEnabled());
        $this->assertNull($user->fresh()->authenticator_secret);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Scan this QR code using your authenticator app.')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee($secret)
            ->assertSee('Verify &amp; Enable', false);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($secret);
    }

    public function test_authenticator_is_enabled_only_by_a_valid_totp_and_secret_is_encrypted_at_rest(): void
    {
        $user = User::factory()->warehouseStaff()->create(['password' => 'password']);
        $this->actingAs($user)
            ->post(route('profile.authenticator.setup'), ['current_password' => 'password']);

        $secret = Crypt::decryptString(session(AuthenticatorSetupService::SESSION_KEY)['secret']);

        $this->post(route('profile.authenticator.enable'), ['code' => '000000'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors([
                'code' => 'Invalid authenticator code. Please try again.',
            ], errorBag: 'authenticatorEnable');
        $this->assertFalse($user->fresh()->authenticatorMfaEnabled());

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('profile.authenticator.enable'), ['code' => $code])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('authenticator_success', 'Authenticator app enabled successfully.');

        $user->refresh();
        $this->assertTrue($user->authenticatorMfaEnabled());
        $this->assertSame($secret, $user->authenticator_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('authenticator_secret'));
        $this->assertNull(session(AuthenticatorSetupService::SESSION_KEY));
    }

    public function test_setup_requires_current_password_and_can_be_cancelled_without_enabling(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->post(route('profile.authenticator.setup'), ['current_password' => 'wrong-password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password', errorBag: 'authenticatorSetup');

        $this->assertNull(session(AuthenticatorSetupService::SESSION_KEY));

        $this->post(route('profile.authenticator.setup'), ['current_password' => 'password']);
        $this->assertNotNull(session(AuthenticatorSetupService::SESSION_KEY));

        $this->post(route('profile.authenticator.cancel'))
            ->assertRedirect(route('profile.edit'));
        $this->assertNull(session(AuthenticatorSetupService::SESSION_KEY));
        $this->assertFalse($user->fresh()->authenticatorMfaEnabled());
    }

    public function test_getting_the_enable_url_returns_to_profile_without_enabling_authenticator(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/authenticator/enable')
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($user->fresh()->authenticatorMfaEnabled());
    }

    public function test_enable_endpoint_rejects_requests_without_the_matching_setup_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('profile.authenticator.enable'), ['code' => '123456'])
            ->assertSessionHasErrors('authenticator', errorBag: 'authenticatorEnable');

        $this->assertFalse($user->fresh()->authenticatorMfaEnabled());
    }

    #[DataProvider('authenticationPanels')]
    public function test_authenticator_login_is_required_and_works_for_every_panel(
        UserRole $role,
        string $guard,
        string $loginRoute,
        string $mfaRoute,
        string $verifyRoute,
        string $dashboardRoute,
    ): void {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->role($role)->create([
            'password' => 'password',
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
        ]);

        $this->post(route($loginRoute), $this->credentials($user))
            ->assertRedirect(route($mfaRoute));

        $this->assertGuest($guard);
        $this->assertSame(LoginMfaService::METHOD_AUTHENTICATOR, session(LoginMfaService::SESSION_KEY)['method']);

        $this->get(route($dashboardRoute))->assertRedirect();
        $this->assertGuest($guard);

        $this->get(route($mfaRoute))
            ->assertOk()
            ->assertSee('Authenticator Verification')
            ->assertSee('Enter the 6-digit code from your authenticator app.')
            ->assertDontSee('Send a new code');

        $this->post(route($verifyRoute), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest($guard);

        $this->post(route($verifyRoute), ['otp' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route($dashboardRoute, absolute: false));

        $this->assertAuthenticatedAs($user, $guard);
        foreach (AuthenticationContext::sessionGuards() as $otherGuard) {
            if ($otherGuard !== $guard) {
                $this->assertGuest($otherGuard);
            }
        }
    }

    public function test_an_old_totp_is_rejected_and_login_attempts_are_bounded(): void
    {
        config()->set('auth.login_mfa.max_attempts', 2);
        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->authenticatorUser($secret);
        $totp = new Google2FA;
        $oldCode = $totp->oathTotp($secret, $totp->getTimestamp() - 4);

        $this->post(route('login'), $this->credentials($user));

        $this->post(route('login.mfa.verify'), ['otp' => $oldCode])
            ->assertSessionHasErrors([
                'otp' => 'Invalid authenticator code. Please try again.',
            ]);
        $this->post(route('login.mfa.verify'), ['otp' => '000000'])
            ->assertSessionHasErrors([
                'otp' => 'Too many incorrect attempts. Please sign in again.',
            ]);

        $this->post(route('login.mfa.verify'), ['otp' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertSessionHasErrors('otp');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_empty_non_numeric_and_incomplete_authenticator_codes_are_rejected(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->authenticatorUser($secret);

        foreach (['', 'abcdef', '12345'] as $code) {
            $this->post(route('login'), $this->credentials($user));
            $this->post(route('login.mfa.verify'), ['otp' => $code])
                ->assertSessionHasErrors('otp');
            $this->assertGuest(AuthenticationContext::WEB_GUARD);
        }
    }

    public function test_password_expiration_runs_after_authenticator_verification_without_disabling_it(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->authenticatorUser($secret, [
            'password_changed_at' => now()->subDays(config('auth.password_expiration.days') + 1),
        ]);

        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('login.mfa'));
        $this->post(route('login.mfa.verify'), ['otp' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('password.expired'));

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertTrue($user->fresh()->authenticatorMfaEnabled());
    }

    public function test_logout_and_session_expiration_do_not_disable_authenticator(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->authenticatorUser($secret);

        $this->post(route('login'), $this->credentials($user));
        $this->post(route('login.mfa.verify'), ['otp' => (new Google2FA)->getCurrentOtp($secret)]);
        $this->post(route('logout'))->assertRedirect('/');

        $this->assertTrue($user->fresh()->authenticatorMfaEnabled());
        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('login.mfa'));

        $this->post(route('login.mfa.verify'), ['otp' => (new Google2FA)->getCurrentOtp($secret)]);
        $this->travel(config('session.lifetime') + 1)->minutes();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertTrue($user->fresh()->authenticatorMfaEnabled());
    }

    public function test_disabling_requires_password_and_current_totp_then_clears_the_secret(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->authenticatorUser($secret);
        $this->actingAs($user)->withSession([
            MfaSession::key(AuthenticationContext::WEB_GUARD) => $user->getKey(),
        ]);

        $this->delete(route('profile.authenticator.disable'), [
            'current_password' => 'wrong-password',
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertSessionHasErrors('current_password', errorBag: 'authenticatorDisable');
        $this->assertTrue($user->fresh()->authenticatorMfaEnabled());

        $this->delete(route('profile.authenticator.disable'), [
            'current_password' => 'password',
            'code' => '000000',
        ])->assertSessionHasErrors('code', errorBag: 'authenticatorDisable');
        $this->assertTrue($user->fresh()->authenticatorMfaEnabled());

        $this->delete(route('profile.authenticator.disable'), [
            'current_password' => 'password',
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertSessionHas('authenticator_success', 'Authenticator app disabled successfully.');

        $user->refresh();
        $this->assertFalse($user->authenticatorMfaEnabled());
        $this->assertNull($user->authenticator_secret);
        $this->assertNull($user->authenticator_enabled_at);

        $this->post(route('logout'));
        $this->post(route('login'), $this->credentials($user))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
    }

    public function test_an_authenticated_or_remembered_session_without_mfa_completion_is_rejected(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->authenticatorUser($secret);

        $this->app['auth']->guard(AuthenticationContext::WEB_GUARD)->login($user, true);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_a_stateful_api_request_cannot_use_an_unverified_session(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->warehouseStaff()->create(['password' => 'password']);

        $this->post(route('login'), $this->credentials($user));
        $user->forceFill([
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
        ])->save();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/dashboard-summary')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MFA_REQUIRED');
    }

    /** @return array<string, array{UserRole, string, string, string, string, string}> */
    public static function authenticationPanels(): array
    {
        return [
            'staff' => [
                UserRole::WarehouseStaff,
                AuthenticationContext::WEB_GUARD,
                'login',
                'login.mfa',
                'login.mfa.verify',
                'dashboard',
            ],
            'admin' => [
                UserRole::Administrator,
                AuthenticationContext::ADMIN_GUARD,
                'admin.login.store',
                'admin.login.mfa',
                'admin.login.mfa.verify',
                'dashboard',
            ],
            'super admin' => [
                UserRole::SuperAdministrator,
                AuthenticationContext::SUPER_ADMIN_GUARD,
                'super-admin.login.store',
                'super-admin.login.mfa',
                'super-admin.login.mfa.verify',
                'super-admin.dashboard',
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function authenticatorUser(string $secret, array $attributes = []): User
    {
        return User::factory()->warehouseStaff()->create([
            'password' => 'password',
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
            ...$attributes,
        ]);
    }

    /** @return array{email: string, password: string} */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }
}
