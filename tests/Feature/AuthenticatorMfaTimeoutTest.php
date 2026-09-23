<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\LoginMfaService;
use App\Support\AuthenticationContext;
use App\Support\AuthenticationPanel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticatorMfaTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public static function panelProvider(): array
    {
        return [
            'staff' => [
                UserRole::WarehouseStaff,
                AuthenticationContext::WEB_GUARD,
                'login',
                'login.mfa',
                'login.mfa.verify',
                'login.mfa.continue',
                'login.mfa.cancel',
                'dashboard',
            ],
            'admin' => [
                UserRole::Administrator,
                AuthenticationContext::ADMIN_GUARD,
                'admin.login',
                'admin.login.mfa',
                'admin.login.mfa.verify',
                'admin.login.mfa.continue',
                'admin.login.mfa.cancel',
                'admin.dashboard',
            ],
            'super_admin' => [
                UserRole::SuperAdministrator,
                AuthenticationContext::SUPER_ADMIN_GUARD,
                'super-admin.login',
                'super-admin.login.mfa',
                'super-admin.login.mfa.verify',
                'super-admin.login.mfa.continue',
                'super-admin.login.mfa.cancel',
                'super-admin.dashboard',
            ],
        ];
    }

    #[DataProvider('panelProvider')]
    public function test_authenticator_mfa_initiates_120_second_timeout_with_security_headers(
        UserRole $role,
        string $guard,
        string $loginRoute,
        string $mfaRoute,
        string $verifyRoute,
        string $continueRoute,
        string $cancelRoute,
        string $dashboardRoute,
    ): void {
        $now = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($now);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser($role, $secret);

        $response = $this->post(route($loginRoute), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route($mfaRoute));
        $this->assertGuest($guard);

        $state = session(LoginMfaService::SESSION_KEY);
        $this->assertNotNull($state);
        $this->assertSame(LoginMfaService::METHOD_AUTHENTICATOR, $state['method']);
        $this->assertSame($now->getTimestamp(), $state['started_at']);
        $this->assertSame($now->getTimestamp() + 120, $state['expires_at']);
        $this->assertSame(0, $state['extensions_count']);

        $page = $this->get(route($mfaRoute));
        $page->assertOk();
        $cacheControl = (string) $page->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $page->assertHeader('Pragma', 'no-cache');
        $this->assertContains($page->headers->get('Expires'), ['0', 'Fri, 01 Jan 1990 00:00:00 GMT']);

        $page->assertSee('Authenticator Verification');
        $page->assertSee('2-minute verification window');
        $page->assertSee('Continue Session');
        $page->assertSee('End Session');
    }

    public function test_authenticator_mfa_page_refresh_preserves_deadline_and_calculates_remaining_time(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login.mfa'));

        $initialExpiry = session(LoginMfaService::SESSION_KEY)['expires_at'];
        $this->assertSame($startTime->getTimestamp() + 120, $initialExpiry);

        // Advance 45 seconds
        Carbon::setTestNow($startTime->copy()->addSeconds(45));

        $page = $this->get(route('login.mfa'));
        $page->assertOk();

        // Ensure session expires_at was NOT reset or moved forward
        $currentExpiry = session(LoginMfaService::SESSION_KEY)['expires_at'];
        $this->assertSame($initialExpiry, $currentExpiry);

        // Verify remaining seconds passed to the view is 75 (120 - 45)
        $this->assertSame(75, $page->viewData('remainingSeconds'));
        $this->assertFalse($page->viewData('expired'));
    }

    public function test_authenticator_mfa_expires_after_120_seconds_and_rejects_code_submission(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Advance time to 121 seconds (1 second past the 2-minute deadline)
        Carbon::setTestNow($startTime->copy()->addSeconds(121));

        $validOtp = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->post(route('login.mfa.verify'), [
            'otp' => $validOtp,
        ]);

        $response->assertSessionHasErrors([
            'otp' => 'Your authenticator verification session has expired. Please sign in again.',
        ]);

        // Session state must be cleared
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    public function test_expired_authenticator_session_allows_starting_a_fresh_login_attempt(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Fast forward past expiration
        Carbon::setTestNow($startTime->copy()->addSeconds(130));

        // Attempting to visit MFA shows expired state or redirects
        $mfaPage = $this->get(route('login.mfa'));
        $mfaPage->assertOk();
        $this->assertTrue($mfaPage->viewData('expired'));

        // User posts credentials again to start fresh
        $freshTime = Carbon::parse('2026-09-23 12:05:00');
        Carbon::setTestNow($freshTime);

        $freshLogin = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $freshLogin->assertRedirect(route('login.mfa'));

        $state = session(LoginMfaService::SESSION_KEY);
        $this->assertSame($freshTime->getTimestamp() + 120, $state['expires_at']);
        $this->assertSame(0, $state['extensions_count']);
    }

    public function test_continue_session_extends_timeout_during_warning_window(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Advance to 100 seconds elapsed (20 seconds remaining, within the 30-second warning window)
        $warningTime = $startTime->copy()->addSeconds(100);
        Carbon::setTestNow($warningTime);

        $continueResponse = $this->postJson(route('login.mfa.continue'));
        $continueResponse->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'success',
                'remaining_seconds' => 120,
                'extensions_remaining' => 2,
            ]);

        $newState = session(LoginMfaService::SESSION_KEY);
        $this->assertSame($warningTime->getTimestamp() + 120, $newState['expires_at']);
        $this->assertSame(1, $newState['extensions_count']);
        $this->assertSame($warningTime->getTimestamp(), $newState['last_extended_at']);

        // Now advance to 180 seconds from start (which would be expired under original 120s deadline)
        Carbon::setTestNow($startTime->copy()->addSeconds(180));

        $validOtp = (new Google2FA)->getCurrentOtp($secret);
        $verifyResponse = $this->post(route('login.mfa.verify'), ['otp' => $validOtp]);

        $verifyResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user, AuthenticationContext::WEB_GUARD);
    }

    public function test_continue_session_rejected_outside_warning_window(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // 10 seconds elapsed (110 seconds remaining > 30-second warning threshold)
        Carbon::setTestNow($startTime->copy()->addSeconds(10));

        $response = $this->postJson(route('login.mfa.continue'));
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status' => 'not_in_warning_window',
                'message' => 'Session can only be extended when 30 seconds or less remain.',
            ]);

        $state = session(LoginMfaService::SESSION_KEY);
        $this->assertSame($startTime->getTimestamp() + 120, $state['expires_at']);
        $this->assertSame(0, $state['extensions_count']);
    }

    public function test_continue_session_rejected_when_expired(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // 125 seconds elapsed (expired)
        Carbon::setTestNow($startTime->copy()->addSeconds(125));

        $response = $this->postJson(route('login.mfa.continue'));
        $response->assertStatus(410)
            ->assertJson([
                'success' => false,
                'status' => 'expired',
                'redirect_url' => route('login'),
            ]);

        $this->assertNull(session(LoginMfaService::SESSION_KEY));
    }

    public function test_continue_session_rate_limited_cooldown(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // At 95s elapsed (25s remaining in warning window)
        $t1 = $startTime->copy()->addSeconds(95);
        Carbon::setTestNow($t1);

        $first = $this->postJson(route('login.mfa.continue'));
        $first->assertOk();

        // 2 seconds later (within 5-second cooldown)
        Carbon::setTestNow($t1->copy()->addSeconds(2));

        $second = $this->postJson(route('login.mfa.continue'));
        $second->assertStatus(429)
            ->assertJson([
                'success' => false,
                'status' => 'cooldown',
                'retry_after' => 3,
            ]);
    }

    public function test_continue_session_capped_at_three_extensions(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // 1st Extension: at 95s elapsed (deadline extends to 95 + 120 = 215s)
        $t1 = $startTime->copy()->addSeconds(95);
        Carbon::setTestNow($t1);
        $this->postJson(route('login.mfa.continue'))
            ->assertOk()
            ->assertJson(['extensions_remaining' => 2]);

        // 2nd Extension: at 190s elapsed (25s before 215s; deadline extends to 190 + 120 = 310s)
        $t2 = $startTime->copy()->addSeconds(190);
        Carbon::setTestNow($t2);
        $this->postJson(route('login.mfa.continue'))
            ->assertOk()
            ->assertJson(['extensions_remaining' => 1]);

        // 3rd Extension: at 285s elapsed (25s before 310s; deadline extends to 285 + 120 = 405s)
        $t3 = $startTime->copy()->addSeconds(285);
        Carbon::setTestNow($t3);
        $this->postJson(route('login.mfa.continue'))
            ->assertOk()
            ->assertJson(['extensions_remaining' => 0]);

        // 4th Attempt: at 380s elapsed (25s before 405s; limit reached!)
        $t4 = $startTime->copy()->addSeconds(380);
        Carbon::setTestNow($t4);
        $this->postJson(route('login.mfa.continue'))
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status' => 'max_extensions',
                'message' => 'Maximum session extensions reached (3/3). Please complete verification.',
            ]);
    }

    public function test_end_session_invalidates_mfa_and_redirects_to_login(): void
    {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser(UserRole::WarehouseStaff, $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotNull(session(LoginMfaService::SESSION_KEY));

        // Click End Session (JSON)
        $response = $this->postJson(route('login.mfa.cancel'));
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'redirect_url' => route('login'),
            ]);

        $this->assertNull(session(LoginMfaService::SESSION_KEY));

        // Visiting MFA screen now redirects back to login
        $this->get(route('login.mfa'))
            ->assertRedirect(route('login'));
    }

    public function test_direct_url_access_without_pending_mfa_redirects_to_login(): void
    {
        $this->get(route('login.mfa'))
            ->assertRedirect(route('login'));

        $this->postJson(route('login.mfa.continue'))
            ->assertStatus(401)
            ->assertJson(['status' => 'missing']);

        $this->postJson(route('login.mfa.cancel'))
            ->assertOk()
            ->assertJson(['redirect_url' => route('login')]);
    }

    #[DataProvider('panelProvider')]
    public function test_expired_mfa_screen_shows_only_return_to_login_and_hides_redundant_panel_link(
        UserRole $role,
        string $guard,
        string $loginRoute,
        string $mfaRoute,
        string $verifyRoute,
        string $continueRoute,
        string $cancelRoute,
        string $dashboardRoute,
    ): void {
        $startTime = Carbon::parse('2026-09-23 12:00:00');
        Carbon::setTestNow($startTime);

        $secret = (new Google2FA)->generateSecretKey();
        $user = $this->createAuthenticatorUser($role, $secret);

        $this->post(route($loginRoute), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route($mfaRoute));

        // 1. While ACTIVE: "Return to [Panel] login" is visible
        $activePage = $this->get(route($mfaRoute));
        $activePage->assertOk();
        $panelLabel = AuthenticationPanel::forGuard($guard)->label();
        $activePage->assertSee('Return to '.$panelLabel.' login');

        // 2. Fast forward to EXPIRED:
        Carbon::setTestNow($startTime->copy()->addSeconds(125));

        $expiredPage = $this->get(route($mfaRoute));
        $expiredPage->assertOk();
        $expiredPage->assertSee('Verification session expired');
        $expiredPage->assertSee('Return to login and sign in again');

        // The separate return link is hidden via display: none
        $content = $expiredPage->getContent();
        $this->assertStringContainsString('display: none', $content);

        // 3. Visiting login screen resets/clears the expired verification state
        $this->get(route($loginRoute))->assertOk();
        $this->assertNull(session(LoginMfaService::SESSION_KEY));
    }

    private function createAuthenticatorUser(UserRole $role, string $secret): User
    {
        return User::factory()->role($role)->create([
            'password' => 'password',
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
        ]);
    }
}
