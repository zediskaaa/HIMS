<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Services\LoginMfaService;
use App\Services\Sms\IprogSmsGateway;
use App\Services\Sms\SmsOtpDelivery;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SmsMfaTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
    }

    public function test_sms_only_login_remains_a_guest_until_the_single_use_code_is_verified(): void
    {
        $user = $this->smsUser();

        $this->post(route('login'), $this->credentials($user))->assertRedirect(route('login.mfa'));
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $code = $this->sms->latestCode();
        $state = session(LoginMfaService::SESSION_KEY);
        $this->assertSame(LoginMfaService::METHOD_SMS, $state['method']);
        $this->assertTrue(Hash::check($code, $state['otp_hash']));

        $this->get(route('login.mfa'))->assertOk()
            ->assertSee('Verification Code')
            ->assertSee('Hospital supply operations, on one secure record.')
            ->assertSee('09******567')
            ->assertDontSee($user->phone)
            ->assertDontSee($code);

        $this->post(route('login.mfa.verify'), ['otp' => $code === '999999' ? '000000' : '999999'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);

        $this->post(route('login.mfa.verify'), ['otp' => $code])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
        $this->assertNull(session(LoginMfaService::SESSION_KEY));

        $this->app['auth']->guard(AuthenticationContext::WEB_GUARD)->logout();
        $this->app['auth']->forgetGuards();
        $this->post(route('login.mfa.verify'), ['otp' => $code])->assertRedirect(route('login'));
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::SmsVerification)->where('description', 'SMS verification succeeded.')->count());
    }

    public function test_sms_resend_replaces_the_code_and_keeps_failed_attempts(): void
    {
        config()->set('auth.login_mfa.resend_cooldown', 1);
        $user = $this->smsUser();
        $this->post(route('login'), $this->credentials($user));
        $first = $this->sms->latestCode();

        $this->post(route('login.mfa.resend'))->assertSessionHasErrors('otp');
        $this->post(route('login.mfa.verify'), ['otp' => $first === '000000' ? '999999' : '000000']);
        $attempts = session(LoginMfaService::SESSION_KEY)['attempts_remaining'];

        $this->travel(2)->seconds();
        $this->post(route('login.mfa.resend'))->assertSessionHas('status');
        $second = $this->sms->latestCode();
        $this->assertNotSame($first, $second);
        $this->assertSame($attempts, session(LoginMfaService::SESSION_KEY)['attempts_remaining']);

        $this->post(route('login.mfa.verify'), ['otp' => $first])->assertSessionHasErrors('otp');
        $this->post(route('login.mfa.verify'), ['otp' => $second])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_expired_and_exhausted_codes_never_complete_login(): void
    {
        $user = $this->smsUser();
        $this->post(route('login'), $this->credentials($user));
        $code = $this->sms->latestCode();
        $wrong = $code === '000000' ? '999999' : '000000';

        foreach (range(1, (int) config('auth.login_mfa.max_attempts')) as $_) {
            $this->post(route('login.mfa.verify'), ['otp' => $wrong])->assertSessionHasErrors('otp');
        }
        $this->post(route('login.mfa.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);

        $this->post(route('login'), $this->credentials($user));
        $this->travel(6)->minutes();
        $this->post(route('login.mfa.verify'), ['otp' => $this->sms->latestCode()])->assertSessionHasErrors('otp');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_delivery_failure_and_api_token_login_cannot_bypass_sms(): void
    {
        $user = $this->smsUser();
        $this->sms->succeed = false;

        $this->post(route('login'), $this->credentials($user))->assertSessionHasErrors('email');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
        $this->get(route('login.mfa'))->assertRedirect(route('login'));

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(428)->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_profile_toggle_requires_password_and_a_valid_registered_phone(): void
    {
        $user = User::factory()->warehouseStaff()->create(['phone' => null]);
        $this->actingAs($user);

        $this->patch(route('profile.sms-mfa.update'), [
            'sms_mfa_enabled' => '1', 'current_password' => 'password',
        ])->assertSessionHasErrors('sms_mfa_enabled', errorBag: 'smsMfa');
        $this->assertFalse($user->fresh()->sms_mfa_enabled);

        $user->forceFill(['phone' => '09171234567'])->save();
        $this->patch(route('profile.sms-mfa.update'), [
            'sms_mfa_enabled' => '1', 'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password', errorBag: 'smsMfa');

        $this->patch(route('profile.sms-mfa.update'), [
            'sms_mfa_enabled' => '1', 'current_password' => 'password',
        ])->assertRedirect(route('profile.edit'));
        $this->assertTrue($user->fresh()->sms_mfa_enabled);
        $this->assertSame('09171234567', $user->sms_mfa_phone);

        $this->patch(route('profile.sms-mfa.update'), [
            'sms_mfa_enabled' => '0', 'current_password' => 'password',
        ])->assertRedirect(route('profile.edit'));
        $this->assertFalse($user->fresh()->sms_mfa_enabled);
        $this->assertNull($user->sms_mfa_phone);
        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::ChangedMfa)->count());
    }

    public function test_sms_cannot_be_enabled_before_a_provider_is_configured(): void
    {
        $this->app->forgetInstance(SmsGateway::class);
        config()->set('services.sms.api_token', null);
        $user = User::factory()->warehouseStaff()->create(['phone' => '09171234567']);

        $this->actingAs($user)->patch(route('profile.sms-mfa.update'), [
            'sms_mfa_enabled' => '1', 'current_password' => 'password',
        ])->assertSessionHasErrors('sms_mfa_enabled', errorBag: 'smsMfa');

        $this->assertFalse($user->fresh()->sms_mfa_enabled);
    }

    public function test_sms_generation_is_bounded_across_sessions_for_one_account(): void
    {
        $user = $this->smsUser();
        $delivery = $this->app->make(SmsOtpDelivery::class);

        foreach (range(1, 5) as $_) {
            $this->assertSame(SmsOtpDelivery::SENT, $delivery->send($user, '123456', 5));
        }

        $this->assertSame(SmsOtpDelivery::RATE_LIMITED, $delivery->send($user, '123456', 5));
        $this->assertCount(5, $this->sms->messages);
    }

    public function test_completed_login_cannot_reopen_the_code_screen_with_browser_back(): void
    {
        $user = $this->smsUser();
        $this->post(route('login'), $this->credentials($user));
        $this->post(route('login.mfa.verify'), ['otp' => $this->sms->latestCode()]);

        $this->get(route('login.mfa'))->assertRedirect();
        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
    }

    public function test_sms_audit_and_page_never_include_the_code_or_provider_credential(): void
    {
        config()->set('services.sms.api_token', 'synthetic-secret-for-test');
        $user = $this->smsUser();
        $this->post(route('login'), $this->credentials($user));
        $code = $this->sms->latestCode();
        $page = $this->get(route('login.mfa'))->assertOk()->getContent();

        $this->assertStringNotContainsString($code, $page);
        $this->assertStringNotContainsString('synthetic-secret-for-test', $page);
        $this->assertStringNotContainsString($code, AuditLog::query()->where('action', AuditAction::SmsVerification)->get()->toJson());
        $this->assertStringNotContainsString('synthetic-secret-for-test', AuditLog::query()->where('action', AuditAction::SmsVerification)->get()->toJson());
    }

    public function test_registered_phone_change_fails_closed_and_sms_delivery_never_uses_the_new_number(): void
    {
        $user = $this->smsUser();
        $user->forceFill(['phone' => '09987654321'])->save();

        $this->post(route('login'), $this->credentials($user))->assertSessionHasErrors('email');
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
        $this->assertSame([], $this->sms->messages);
    }

    public function test_email_and_sms_are_both_required_for_an_administrator(): void
    {
        Notification::fake();
        $user = User::factory()->administrator()->create([
            'password' => 'password',
            'phone' => '09171234567',
            'mfa_enabled' => true,
            'sms_mfa_enabled' => true,
            'sms_mfa_phone' => '09171234567',
        ]);

        $this->post(route('admin.login.store'), $this->credentials($user))
            ->assertRedirect(route('admin.login.mfa'));
        $this->assertSame(LoginMfaService::METHOD_EMAIL, session(LoginMfaService::SESSION_KEY)['method']);
        $emailCode = Notification::sent($user, LoginMfaOtp::class)->first()->otp;

        $this->post(route('admin.login.mfa.verify'), ['otp' => $emailCode])
            ->assertRedirect(route('admin.login.mfa'));
        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
        $this->assertSame(LoginMfaService::METHOD_SMS, session(LoginMfaService::SESSION_KEY)['method']);

        $this->post(route('admin.login.mfa.verify'), ['otp' => $this->sms->latestCode()])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, AuthenticationContext::ADMIN_GUARD);
    }

    public function test_expired_password_flow_begins_only_after_sms_verification(): void
    {
        $user = $this->smsUser();
        $user->forceFill(['password_changed_at' => now()->subDays(91)])->save();

        $this->post(route('login'), $this->credentials($user))->assertRedirect(route('login.mfa'));
        $this->assertGuest(AuthenticationContext::WEB_GUARD);

        $this->post(route('login.mfa.verify'), ['otp' => $this->sms->latestCode()])
            ->assertRedirect(route('password.expired'));
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_authenticator_and_sms_are_both_required_for_a_super_administrator(): void
    {
        $authenticator = new Google2FA;
        $secret = $authenticator->generateSecretKey();
        $user = User::factory()->superAdministrator()->create([
            'password' => 'password',
            'phone' => '09171234567',
            'sms_mfa_enabled' => true,
            'sms_mfa_phone' => '09171234567',
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
        ]);

        $this->post(route('super-admin.login.store'), $this->credentials($user))
            ->assertRedirect(route('super-admin.login.mfa'));
        $this->assertSame(LoginMfaService::METHOD_AUTHENTICATOR, session(LoginMfaService::SESSION_KEY)['method']);
        $this->assertSame([], $this->sms->messages);

        $this->post(route('super-admin.login.mfa.verify'), ['otp' => $authenticator->getCurrentOtp($secret)])
            ->assertRedirect(route('super-admin.login.mfa'));
        $this->assertGuest(AuthenticationContext::SUPER_ADMIN_GUARD);

        $this->post(route('super-admin.login.mfa.verify'), ['otp' => $this->sms->latestCode()])
            ->assertRedirect(route('super-admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, AuthenticationContext::SUPER_ADMIN_GUARD);
    }

    public function test_iprog_provider_uses_a_post_body_and_requires_an_accepted_message_id(): void
    {
        config()->set('services.sms.api_token', 'test-only-token');
        Http::fake(['www.iprogsms.com/*' => Http::sequence()
            ->push(['status' => 500, 'message' => 'Rejected'], 200)
            ->push(['status' => 200, 'message' => 'Queued'], 200)
            ->push(['status' => 200, 'message' => 'Queued', 'message_id' => 'iSms-test'], 200)]);

        $gateway = new IprogSmsGateway;
        $this->assertFalse($gateway->send('09171234567', 'test-only-message'));
        $this->assertFalse($gateway->send('09171234567', 'test-only-message'));
        $this->assertTrue($gateway->send('09171234567', 'test-only-message'));
        Http::assertSent(fn ($request) => $request->url() === 'https://www.iprogsms.com/api/v1/sms_messages'
            && $request['api_token'] === 'test-only-token'
            && $request['phone_number'] === '639171234567'
            && $request['message'] === 'test-only-message'
            && ! str_contains($request->url(), 'test-only-token'));
    }

    private function smsUser(): User
    {
        return User::factory()->warehouseStaff()->create([
            'password' => 'password',
            'phone' => '09171234567',
            'sms_mfa_enabled' => true,
            'sms_mfa_phone' => '09171234567',
        ]);
    }

    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }
}

class FakeSmsGateway implements SmsGateway
{
    public bool $succeed = true;

    /** @var list<array{number: string, message: string}> */
    public array $messages = [];

    public function available(): bool
    {
        return true;
    }

    public function send(string $mobileNumber, string $message): bool
    {
        $this->messages[] = ['number' => $mobileNumber, 'message' => $message];

        return $this->succeed;
    }

    public function latestCode(): string
    {
        preg_match('/HIMS verification code: ([0-9]{6})/', $this->messages[array_key_last($this->messages)]['message'], $matches);

        return $matches[1];
    }
}
