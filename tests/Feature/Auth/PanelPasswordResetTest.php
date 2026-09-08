<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Notifications\PasswordResetOtp;
use App\Services\LoginLockoutService;
use App\Services\LoginMfaService;
use App\Services\PasswordExpirationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PanelPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_login_panel_links_only_to_its_own_password_reset_request(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.route('password.request').'"', escape: false)
            ->assertDontSee('Go to Admin Login')
            ->assertDontSee('Go to Super Admin Login')
            ->assertDontSee('href="'.route('admin.login').'"', escape: false)
            ->assertDontSee('href="'.route('super-admin.login').'"', escape: false);
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('href="'.route('admin.password.request').'"', escape: false);
        $this->get(route('super-admin.login'))
            ->assertOk()
            ->assertSee('href="'.route('super-admin.password.request').'"', escape: false);
    }

    public function test_legacy_email_enumeration_endpoint_is_removed(): void
    {
        $this->get('/check-email?email=someone@example.test')->assertNotFound();
    }

    public function test_admin_is_rejected_by_staff_login_with_a_message_only_notice(): void
    {
        $admin = User::factory()->administrator()->create(['password' => 'password']);

        foreach (['password', 'incorrect-password'] as $password) {
            $this->from(route('login'))->post(route('login'), [
                'email' => $admin->email,
                'password' => $password,
            ])->assertRedirect(route('login'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('wrong_panel.message', "You're using the Staff Login Panel. Please use the Admin Login Panel.")
                ->assertSessionMissing('wrong_panel.url')
                ->assertSessionMissing('wrong_panel.label')
                ->assertSessionMissing(LoginLockoutService::SESSION_KEY);
        }

        $this->assertGuest('web');
        $this->assertSame(0, $admin->refresh()->failed_login_attempts);
        $this->assertNull($admin->last_failed_login_at);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Wrong login panel')
            ->assertSee("You're using the Staff Login Panel. Please use the Admin Login Panel.")
            ->assertDontSee('Go to Admin Login')
            ->assertDontSee('Go to Super Admin Login')
            ->assertDontSee('href="'.route('admin.login').'"', escape: false);
    }

    public function test_super_admin_is_rejected_by_staff_login_with_a_message_only_notice(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create(['password' => 'password']);

        foreach (['password', 'incorrect-password'] as $password) {
            $this->from(route('login'))->post(route('login'), [
                'email' => $superAdmin->email,
                'password' => $password,
            ])->assertRedirect(route('login'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('wrong_panel.message', "You're using the Staff Login Panel. Please use the Super Admin Login Panel.")
                ->assertSessionMissing('wrong_panel.url')
                ->assertSessionMissing('wrong_panel.label')
                ->assertSessionMissing(LoginLockoutService::SESSION_KEY);
        }

        $this->assertGuest('web');
        $superAdmin->refresh();
        $this->assertSame(0, $superAdmin->failed_login_attempts);
        $this->assertNull($superAdmin->last_failed_login_at);
        $this->assertNull($superAdmin->login_locked_until);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee("You're using the Staff Login Panel. Please use the Super Admin Login Panel.")
            ->assertDontSee('Go to Super Admin Login')
            ->assertDontSee('href="'.route('super-admin.login').'"', escape: false);
    }

    public function test_every_other_wrong_login_panel_stays_on_the_originating_panel_without_an_action_link(): void
    {
        $attempts = [
            [User::factory()->warehouseStaff()->create(['password' => 'password']), 'admin.login', 'admin.login.store', 'Admin', 'Staff'],
            [User::factory()->superAdministrator()->create(['password' => 'password']), 'admin.login', 'admin.login.store', 'Admin', 'Super Admin'],
            [User::factory()->administrator()->create(['password' => 'password']), 'super-admin.login', 'super-admin.login.store', 'Super Admin', 'Admin'],
            [User::factory()->viewer()->create(['password' => 'password']), 'super-admin.login', 'super-admin.login.store', 'Super Admin', 'Staff'],
        ];

        foreach ($attempts as [$account, $loginRoute, $storeRoute, $currentPanel, $correctPanel]) {
            foreach (['password', 'incorrect-password'] as $password) {
                $this->from(route($loginRoute))->post(route($storeRoute), [
                    'email' => $account->email,
                    'password' => $password,
                ])->assertRedirect(route($loginRoute))
                    ->assertSessionHasNoErrors()
                    ->assertSessionHas('wrong_panel.message', "You're using the {$currentPanel} Login Panel. Please use the {$correctPanel} Login Panel.")
                    ->assertSessionMissing('wrong_panel.url')
                    ->assertSessionMissing('wrong_panel.label')
                    ->assertSessionMissing(LoginLockoutService::SESSION_KEY);
            }

            $account->refresh();
            $this->assertSame(0, $account->failed_login_attempts);
            $this->assertNull($account->last_failed_login_at);
            $this->assertNull($account->login_retry_at);
            $this->assertNull($account->login_locked_until);
        }
    }

    public function test_repeated_wrong_panel_submissions_never_throttle_or_lock_the_account(): void
    {
        $staff = User::factory()->warehouseStaff()->create(['password' => 'password']);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->from(route('super-admin.login'))->post(route('super-admin.login.store'), [
                'email' => $staff->email,
                'password' => "incorrect-password-{$attempt}",
            ])->assertRedirect(route('super-admin.login'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('wrong_panel.message')
                ->assertSessionMissing(LoginLockoutService::SESSION_KEY);
        }

        $staff->refresh();
        $this->assertSame(0, $staff->failed_login_attempts);
        $this->assertNull($staff->last_failed_login_at);
        $this->assertNull($staff->login_retry_at);
        $this->assertNull($staff->login_locked_until);
        $this->assertGuest('super_admin');

        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 4 attempts remaining.',
        ])->assertSessionMissing('wrong_panel');

        $this->assertSame(1, $staff->refresh()->failed_login_attempts);
    }

    public function test_wrong_panel_attempt_does_not_start_mfa_or_password_expiration(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create([
            'password' => 'password',
            'mfa_enabled' => true,
            'password_changed_at' => now()->subDays(91),
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $admin->email,
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('wrong_panel.message', "You're using the Staff Login Panel. Please use the Admin Login Panel.")
            ->assertSessionMissing(LoginMfaService::SESSION_KEY)
            ->assertSessionMissing(PasswordExpirationService::SESSION_KEY)
            ->assertSessionMissing(LoginLockoutService::SESSION_KEY);

        Notification::assertNotSentTo($admin, LoginMfaOtp::class);
        $this->assertGuest('web');
        $this->assertGuest('admin');
        $this->assertSame(0, $admin->refresh()->failed_login_attempts);
    }

    public function test_staff_forgot_password_rejects_admin_and_super_admin_accounts(): void
    {
        Notification::fake();

        $accounts = [
            User::factory()->administrator()->create(),
            User::factory()->superAdministrator()->create(),
        ];

        foreach ($accounts as $account) {
            $correctPanel = $account->isSuperAdministrator() ? 'Super Admin' : 'Admin';

            $this->from(route('password.request'))->post(route('password.email'), [
                'email' => $account->email,
            ])->assertRedirect(route('password.request'))
                ->assertSessionHas('wrong_panel.message', "You're using the Staff Login Panel. Please use the {$correctPanel} Login Panel.")
                ->assertSessionMissing('wrong_panel.url')
                ->assertSessionMissing('wrong_panel.label');

            Notification::assertNotSentTo($account, PasswordResetOtp::class);
        }
    }

    public function test_staff_account_cannot_use_an_admin_password_reset_panel(): void
    {
        Notification::fake();
        $staff = User::factory()->warehouseStaff()->create();

        $this->from(route('admin.password.request'))->post(route('admin.password.email'), [
            'email' => $staff->email,
        ])->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('wrong_panel.message', "You're using the Admin Login Panel. Please use the Staff Login Panel.")
            ->assertSessionMissing('wrong_panel.url')
            ->assertSessionMissing('wrong_panel.label');

        Notification::assertNotSentTo($staff, PasswordResetOtp::class);
    }

    public function test_admin_can_complete_only_the_admin_password_reset_flow(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();

        $this->post(route('admin.password.email'), ['email' => $admin->email])
            ->assertSessionHasNoErrors();

        $notification = Notification::sent($admin, PasswordResetOtp::class)->first();
        $this->assertInstanceOf(PasswordResetOtp::class, $notification);

        $verification = $this->post(route('admin.password.otp.verify'), [
            'email' => $admin->email,
            'otp' => $notification->otp,
        ]);
        $resetUrl = $verification->headers->get('Location');
        $this->assertStringContainsString('/admin/reset-password/', $resetUrl);
        $this->get($resetUrl)->assertOk()->assertSee('Admin password recovery');

        $this->post(route('admin.password.store'), [
            'token' => $this->tokenFromRedirect($verification),
            'email' => $admin->email,
            'password' => 'NewAdminPassword1!',
            'password_confirmation' => 'NewAdminPassword1!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('NewAdminPassword1!', $admin->refresh()->password));
    }

    public function test_super_admin_can_complete_only_the_super_admin_password_reset_flow(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->post(route('super-admin.password.email'), ['email' => $superAdmin->email])
            ->assertSessionHasNoErrors();

        $notification = Notification::sent($superAdmin, PasswordResetOtp::class)->first();
        $this->assertInstanceOf(PasswordResetOtp::class, $notification);

        $verification = $this->post(route('super-admin.password.otp.verify'), [
            'email' => $superAdmin->email,
            'otp' => $notification->otp,
        ]);
        $resetUrl = $verification->headers->get('Location');
        $this->assertStringContainsString('/super-admin/reset-password/', $resetUrl);
        $this->get($resetUrl)->assertOk()->assertSee('Super Admin password recovery');

        $this->post(route('super-admin.password.store'), [
            'token' => $this->tokenFromRedirect($verification),
            'email' => $superAdmin->email,
            'password' => 'NewSuperPassword1!',
            'password_confirmation' => 'NewSuperPassword1!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('super-admin.login'));

        $this->assertTrue(Hash::check('NewSuperPassword1!', $superAdmin->refresh()->password));
    }

    public function test_admin_cannot_redeem_a_valid_token_through_staff_reset_post(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        $originalPassword = $admin->password;

        $this->post(route('admin.password.email'), ['email' => $admin->email]);

        $notification = Notification::sent($admin, PasswordResetOtp::class)->first();
        $verification = $this->post(route('admin.password.otp.verify'), [
            'email' => $admin->email,
            'otp' => $notification->otp,
        ]);

        $this->post(route('password.store'), [
            'token' => $this->tokenFromRedirect($verification),
            'email' => $admin->email,
            'password' => 'BypassPassword1!',
            'password_confirmation' => 'BypassPassword1!',
        ])->assertRedirect(route('admin.password.request'))
            ->assertSessionMissing('wrong_panel.url')
            ->assertSessionMissing('wrong_panel.label');

        $this->assertSame($originalPassword, $admin->refresh()->password);
    }

    public function test_super_admin_cannot_open_or_submit_staff_reset_routes(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdministrator()->create();
        $originalPassword = $superAdmin->password;

        $this->post(route('super-admin.password.email'), ['email' => $superAdmin->email]);

        $notification = Notification::sent($superAdmin, PasswordResetOtp::class)->first();

        $this->get(route('password.otp', [
            'email' => $superAdmin->email,
        ]))->assertRedirect(route('password.request'));

        $this->post(route('password.otp.verify'), [
            'email' => $superAdmin->email,
            'otp' => $notification->otp,
        ])->assertRedirect(route('super-admin.password.request'));

        $verification = $this->post(route('super-admin.password.otp.verify'), [
            'email' => $superAdmin->email,
            'otp' => $notification->otp,
        ]);
        $token = $this->tokenFromRedirect($verification);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $superAdmin->email,
        ]))->assertRedirect(route('super-admin.password.request'));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $superAdmin->email,
            'password' => 'BypassPassword1!',
            'password_confirmation' => 'BypassPassword1!',
        ])->assertRedirect(route('super-admin.password.request'));

        $this->assertSame($originalPassword, $superAdmin->refresh()->password);
    }

    public function test_unknown_email_receives_a_generic_response_without_a_notification(): void
    {
        Notification::fake();

        $this->post(route('password.email'), [
            'email' => 'unknown@example.test',
        ])->assertSessionHas('status', 'If an eligible account matches that email, a password reset code has been sent.')
            ->assertSessionMissing('wrong_panel');

        Notification::assertNothingSent();
    }

    private function tokenFromRedirect(TestResponse $response): string
    {
        $response->assertRedirect();

        return basename((string) parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }
}
