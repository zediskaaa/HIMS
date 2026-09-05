<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Last Name')
            ->assertSee('First Name')
            ->assertSee('Middle Name')
            ->assertSee('name="surname"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="middle_name"', false)
            ->assertDontSee('name="name"', false);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'surname' => 'Dela Cruz',
                'first_name' => 'Juan',
                'middle_name' => 'Santos',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('profile_success', 'Profile updated successfully.')
            ->assertSessionMissing('success')
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Dela Cruz', $user->surname);
        $this->assertSame('Juan', $user->first_name);
        $this->assertSame('Santos', $user->middle_name);
        $this->assertSame('Juan Santos Dela Cruz', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        $profilePage = $this
            ->actingAs($user)
            ->get('/profile');

        $profilePage
            ->assertOk()
            ->assertSee('value="Dela Cruz"', false)
            ->assertSee('value="Juan"', false)
            ->assertSee('value="Santos"', false)
            ->assertSee('Juan Santos Dela Cruz')
            ->assertSee('Profile updated successfully.');

        $this->assertSame(1, substr_count($profilePage->getContent(), 'Profile updated successfully.'));

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Profile updated successfully.');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_middle_name_is_optional_and_full_name_omits_it_cleanly(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'surname' => 'Reyes',
                'first_name' => 'Ana',
                'middle_name' => '',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Reyes', $user->surname);
        $this->assertSame('Ana', $user->first_name);
        $this->assertNull($user->middle_name);
        $this->assertSame('Ana Reyes', $user->name);
    }

    public function test_required_name_parts_and_field_lengths_are_validated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => '',
                'first_name' => '',
                'middle_name' => str_repeat('a', 81),
                'email' => $user->email,
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors(['surname', 'first_name', 'middle_name'])
            ->assertSessionMissing('profile_success');

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Profile updated successfully.');
    }

    public function test_failed_profile_persistence_does_not_flash_success(): void
    {
        $user = User::factory()->create([
            'name' => 'Original User',
            'email' => 'original@example.com',
        ]);

        User::saving(function (): void {
            throw new \RuntimeException('Simulated database failure.');
        });

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'surname' => 'Changed',
                'first_name' => 'Profile',
                'middle_name' => null,
                'email' => 'changed@example.com',
            ]);

        $response
            ->assertServerError()
            ->assertSessionMissing('profile_success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Original User',
            'email' => 'original@example.com',
        ]);
    }

    public function test_legacy_full_name_is_loaded_into_separate_profile_fields(): void
    {
        $user = User::factory()->create(['name' => 'Maria Clara Santos']);

        DB::table('users')->where('id', $user->id)->update([
            'surname' => null,
            'first_name' => null,
            'middle_name' => null,
        ]);

        $this
            ->actingAs($user->refresh())
            ->get('/profile')
            ->assertOk()
            ->assertSee('value="Santos"', false)
            ->assertSee('value="Maria"', false)
            ->assertSee('value="Clara"', false);
    }

    public function test_all_authentication_panels_can_update_structured_names(): void
    {
        $panels = [
            [
                'guard' => 'web',
                'role' => UserRole::WarehouseStaff,
                'login' => 'login',
                'activity' => 'session.activity',
                'logout' => 'logout',
            ],
            [
                'guard' => 'admin',
                'role' => UserRole::Administrator,
                'login' => 'admin.login.store',
                'activity' => 'admin.session.activity',
                'logout' => 'admin.logout',
            ],
            [
                'guard' => 'super_admin',
                'role' => UserRole::SuperAdministrator,
                'login' => 'super-admin.login.store',
                'activity' => 'super-admin.session.activity',
                'logout' => 'super-admin.logout',
            ],
        ];

        foreach ($panels as $index => $panel) {
            $user = User::factory()->role($panel['role'])->create([
                'password' => 'password',
            ]);

            $this->post(route($panel['login']), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect();

            $this->app['auth']->forgetGuards();
            $this->assertAuthenticatedAs($user, $panel['guard']);

            $response = $this
                ->patch('/profile', [
                    'surname' => 'Role '.$index,
                    'first_name' => 'Profile',
                    'middle_name' => null,
                    'email' => $user->email,
                ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertSessionHas('profile_success', 'Profile updated successfully.')
                ->assertRedirect('/profile');

            $this->assertSame('Profile Role '.$index, $user->refresh()->name);

            // A background activity request must not consume the profile-only
            // notice before the redirected page has an opportunity to show it.
            $this->postJson(route($panel['activity']))->assertNoContent();

            $this->app['auth']->forgetGuards();

            $this->get('/profile')
                ->assertOk()
                ->assertSee('Profile updated successfully.')
                ->assertSessionMissing('profile_success');

            $this->get('/profile')
                ->assertOk()
                ->assertDontSee('Profile updated successfully.');

            $this->post(route($panel['logout']))->assertRedirect();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
