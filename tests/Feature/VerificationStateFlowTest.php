<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FakeVerificationSmsGateway implements SmsGateway
{
    public bool $succeed = true;
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

class VerificationStateFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeVerificationSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeVerificationSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
    }

    public function test_email_verification_renders_unified_state_model_without_redundant_messages(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create([
            'password' => bcrypt('password'),
            'mfa_enabled' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.mfa'));

        $response = $this->get(route('admin.login.mfa'));
        $response->assertOk();

        // 1. Verify idle inputs are active
        $response->assertSee('himsOtpVerification', false);
        $response->assertSee('data-otp-digit', false);

        // 2. Verify mutually exclusive button states and disabled until complete
        $response->assertSee("x-bind:disabled=\"state !== 'ready'", false);
        $response->assertSee("x-show=\"state === 'verifying' || validating\"", false);
        $response->assertSee("x-show=\"state === 'idle' || state === 'ready' || state === 'error'\"", false);
        $response->assertSee("x-show=\"state === 'verified' || state === 'success'\"", false);

        // 3. Verify error-only feedback paragraph (no redundant verifying/success text below blocks)
        $response->assertSee("x-text=\"state === 'error' ? message : ''\"", false);
    }

    public function test_sms_verification_renders_unified_state_model_without_redundant_messages(): void
    {
        $user = User::factory()->warehouseStaff()->create([
            'password' => 'password',
            'phone' => '09171234567',
            'sms_mfa_enabled' => true,
            'sms_mfa_phone' => '09171234567',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login.mfa'));

        $response = $this->get(route('login.mfa'));
        $response->assertOk();

        // 1. Method heading and masked phone
        $response->assertSee('Verification Code');
        $response->assertSee('09******567');

        // 2. Verify mutually exclusive button states and disabled until complete
        $response->assertSee("x-bind:disabled=\"state !== 'ready'", false);
        $response->assertSee("x-show=\"state === 'verifying' || validating\"", false);
        $response->assertSee("x-show=\"state === 'idle' || state === 'ready' || state === 'error'\"", false);
        $response->assertSee("x-show=\"state === 'verified' || state === 'success'\"", false);

        // 3. Verify single feedback paragraph
        $response->assertSee("x-text=\"state === 'error' ? message : ''\"", false);
    }

    public function test_authenticator_verification_renders_unified_state_model_without_redundant_messages(): void
    {
        $user = User::factory()->administrator()->create([
            'password' => 'password',
            'authenticator_secret' => 'JBSWY3DPEHPK3PXP',
            'authenticator_enabled_at' => now(),
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.mfa'));

        $response = $this->get(route('admin.login.mfa'));
        $response->assertOk();

        // 1. Authenticator heading
        $response->assertSee('Authenticator Verification');

        // 2. Verify mutually exclusive button states and disabled until complete
        $response->assertSee("x-bind:disabled=\"state !== 'ready'", false);
        $response->assertSee("x-show=\"state === 'verifying' || validating\"", false);
        $response->assertSee("x-show=\"state === 'idle' || state === 'ready' || state === 'error'\"", false);
        $response->assertSee("x-show=\"state === 'verified' || state === 'success'\"", false);

        // 3. Verify single feedback paragraph
        $response->assertSee("x-text=\"state === 'error' ? message : ''\"", false);
    }

    public function test_password_reset_otp_renders_unified_state_model_without_redundant_messages(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        $response = $this->get(route('password.otp', ['email' => $user->email]));
        $response->assertOk();

        $response->assertSee("x-bind:disabled=\"state !== 'ready'\"", false);
        $response->assertSee("x-show=\"state === 'verifying' || validating\"", false);
        $response->assertSee("x-show=\"state === 'idle' || state === 'ready' || state === 'error'\"", false);
        $response->assertSee("x-show=\"state === 'verified' || state === 'success'\"", false);
        $response->assertSee("x-text=\"state === 'error' ? message : ''\"", false);
    }

    public function test_client_verification_script_implements_clean_state_model_and_navigation(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($appJs);

        // State machine checks
        $this->assertStringContainsString("this.state === 'verifying' || this.state === 'verified'", $appJs);
        $this->assertStringContainsString("this.state = 'verifying';", $appJs);
        $this->assertStringContainsString("this.state = 'verified';", $appJs);
        $this->assertStringContainsString("this.state = 'error';", $appJs);
        $this->assertStringContainsString("this.state = 'idle';", $appJs);
        $this->assertStringContainsString("this.state = 'ready';", $appJs);

        // Sequential green boxes animation checks
        $this->assertStringContainsString("index < this.successCount", $appJs);
        $this->assertStringContainsString("this.successCount = index;", $appJs);
        $this->assertStringContainsString("prefers-reduced-motion", $appJs);

        // Real-time button disabled condition
        $this->assertStringContainsString("this.isComplete", $appJs);

        // Suppress loading overlay modal on verification redirect
        $this->assertStringContainsString("window.himsNavigate(redirectUrl, { showOverlay: false });", $appJs);
        $this->assertStringContainsString("showOverlay: shouldShowOverlay = true", $appJs);
    }
}
