<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(30),
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('NewPassword1!', $user->refresh()->password));
        $this->assertTrue($user->password_changed_at->isToday());
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_current_password_cannot_be_reused_as_the_new_password(): void
    {
        $user = User::factory()->create();
        $originalPasswordHash = $user->password;

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'password')
            ->assertRedirect('/profile');

        $this->assertSame($originalPasswordHash, $user->refresh()->password);
    }

    public function test_super_admin_receives_current_password_validation_feedback(): void
    {
        $user = User::factory()->superAdministrator()->create([
            'email' => 'super.admin@example.com',
            'password' => 'password',
        ]);
        $originalPasswordHash = $user->password;

        $this->post(route('super-admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->assertAuthenticatedAs($user, 'super_admin');

        $this->from('/profile')->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('updatePassword', [
                'current_password' => 'Current password is incorrect.',
            ])
            ->assertSessionMissing('password_success');

        $this->assertSame($originalPasswordHash, $user->refresh()->password);

        $this->postJson(route('super-admin.session.activity'))->assertNoContent();

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Current password is incorrect.')
            ->assertDontSee('Password updated successfully.');

        $this->from('/profile')->put('/password', [
            'current_password' => '',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('updatePassword', [
                'current_password' => 'Current password is required.',
            ])
            ->assertSessionMissing('password_success');

        $this->assertSame($originalPasswordHash, $user->refresh()->password);
    }

    public function test_super_admin_can_update_password_with_their_current_password(): void
    {
        $user = User::factory()->superAdministrator()->create([
            'email' => 'super.admin@example.com',
            'password' => 'password',
        ]);

        $this->post(route('super-admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->assertAuthenticatedAs($user, 'super_admin');

        $this->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('password_success', 'Password updated successfully.')
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('NewPassword1!', $user->refresh()->password));

        $this->postJson(route('super-admin.session.activity'))->assertNoContent();

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Password updated successfully.');

        $this->get('/profile')
            ->assertOk()
            ->assertDontSee('Password updated successfully.');
    }
}
