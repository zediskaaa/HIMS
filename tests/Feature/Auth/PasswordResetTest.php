<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_request_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Email password reset code');
    }

    public function test_request_generates_stores_and_emails_a_six_digit_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.otp', ['email' => $user->email]));

        Notification::assertSentTo($user, PasswordResetOtp::class, function (PasswordResetOtp $notification) use ($user): bool {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $notification->otp);
            $this->assertSame(5, $notification->expiresInMinutes);
            $this->assertNotInstanceOf(ShouldQueue::class, $notification);

            $storedHash = DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->value('token');

            $this->assertNotSame($notification->otp, $storedHash);
            $this->assertTrue(Hash::check($notification->otp, $storedHash));

            $mail = $notification->toMail($user);
            $html = view($mail->view['html'], $mail->viewData)->render();
            $text = view($mail->view['text'], $mail->viewData)->render();

            $this->assertSame('HIMS Password Reset Code', $mail->subject);
            $this->assertStringContainsString($notification->otp, $html);
            $this->assertStringContainsString($notification->otp, $text);
            $this->assertStringContainsString('expires in 5 minutes', $html);

            return true;
        });
    }

    public function test_mail_channel_builds_and_addresses_the_real_message(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect();

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());

        $message = $transport->messages()[0]->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertSame('HIMS Password Reset Code', $message->getSubject());
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $message->getHtmlBody());
        $this->assertStringContainsString('expires in 5 minutes', $message->getHtmlBody());
    }

    public function test_otp_entry_screen_can_be_rendered_for_an_active_staff_account(): void
    {
        $user = User::factory()->create();

        $this->get(route('password.otp', ['email' => $user->email]))
            ->assertOk()
            ->assertSee('Enter verification code')
            ->assertSee($user->email);
    }

    public function test_incorrect_otp_is_rejected(): void
    {
        $user = User::factory()->create();
        $notification = $this->requestOtp($user);
        $originalPasswordHash = $user->password;
        $incorrectOtp = $notification->otp === '000000' ? '999999' : '000000';

        $this->from(route('password.otp', ['email' => $user->email]))
            ->post(route('password.otp.verify'), [
                'email' => $user->email,
                'otp' => $incorrectOtp,
            ])->assertRedirect(route('password.otp', ['email' => $user->email]))
            ->assertSessionHasErrors('otp');

        $this->assertSame($originalPasswordHash, $user->refresh()->password);
    }

    public function test_expired_otp_is_rejected_and_removed(): void
    {
        $user = User::factory()->create();
        $notification = $this->requestOtp($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(6)]);

        $this->post(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => $notification->otp,
        ])->assertSessionHasErrors('otp');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_valid_otp_is_one_time_and_opens_the_secure_reset_form(): void
    {
        $user = User::factory()->create();
        $notification = $this->requestOtp($user);

        $verification = $this->verifyOtp($user, $notification);
        $verification->assertRedirect();
        $resetUrl = $verification->headers->get('Location');

        $this->get($resetUrl)
            ->assertOk()
            ->assertSee('Choose a new password');

        $this->post(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => $notification->otp,
        ])->assertSessionHasErrors('otp');
    }

    public function test_password_can_be_reset_after_valid_otp_verification(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(30),
        ]);
        $notification = $this->requestOtp($user);
        $verification = $this->verifyOtp($user, $notification);
        $token = $this->tokenFromRedirect($verification);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassword1!', $user->refresh()->password));
        $this->assertTrue($user->password_changed_at->isToday());
        $this->assertSame(1, PasswordHistory::query()->whereBelongsTo($user)->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_current_password_cannot_be_reused_after_valid_otp_verification(): void
    {
        $user = User::factory()->create();
        $originalPasswordHash = $user->password;
        $notification = $this->requestOtp($user);
        $token = $this->tokenFromRedirect($this->verifyOtp($user, $notification));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertSame($originalPasswordHash, $user->refresh()->password);
    }

    public function test_resending_replaces_the_old_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $first = Notification::sent($user, PasswordResetOtp::class)->first();

        $this->post(route('password.email'), ['email' => $user->email]);
        $second = Notification::sent($user, PasswordResetOtp::class)->last();

        $this->assertNotSame($first->otp, $second->otp);
        $storedHash = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertFalse(Hash::check($first->otp, $storedHash));
        $this->assertTrue(Hash::check($second->otp, $storedHash));

        $this->post(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => $first->otp,
        ])->assertSessionHasErrors('otp');
    }

    public function test_mail_transport_failure_is_handled_and_removes_the_unsent_otp(): void
    {
        $user = User::factory()->create();
        Log::spy();

        $dispatcher = $this->mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Expected response code 235 but received 535.'));

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'Unable to send the password reset code. Please try again later.',
            ]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_legacy_tokenless_reset_url_redirects_to_a_new_request(): void
    {
        $user = User::factory()->create();

        $this->get('/reset-password?email='.urlencode($user->email))
            ->assertRedirect(route('password.request'));
    }

    private function requestOtp(User $user): PasswordResetOtp
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $notification = Notification::sent($user, PasswordResetOtp::class)->last();
        $this->assertInstanceOf(PasswordResetOtp::class, $notification);

        return $notification;
    }

    private function verifyOtp(User $user, PasswordResetOtp $notification): TestResponse
    {
        return $this->post(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => $notification->otp,
        ]);
    }

    private function tokenFromRedirect(TestResponse $response): string
    {
        $response->assertRedirect();

        return basename((string) parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }
}
