<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_otp_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/reset-password-otp?email='.urlencode($user->email));

        $response
            ->assertOk()
            ->assertSee('Set new password')
            ->assertSee($user->email);
    }

    public function test_legacy_otp_reset_url_redirects_to_the_otp_form(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/reset-password?email='.urlencode($user->email));

        $response->assertRedirect(route('password.reset.otp', [
            'email' => $user->email,
        ]));
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_current_password_cannot_be_reused_with_a_valid_reset_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $originalPasswordHash = $user->password;

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $originalPasswordHash) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasErrors('password');

            $this->assertSame($originalPasswordHash, $user->refresh()->password);
            $this->assertTrue(Hash::check('password', $user->password));

            return true;
        });
    }

    public function test_current_password_cannot_be_reused_in_the_otp_reset_flow(): void
    {
        $user = User::factory()->create();
        $originalPasswordHash = $user->password;

        $response = $this->post('/reset-password-otp', [
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');

        $this->assertSame($originalPasswordHash, $user->refresh()->password);
    }
}
