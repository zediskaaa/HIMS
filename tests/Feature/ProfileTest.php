<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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
            ->assertSee('name="current_password"', false)
            ->assertSee('Required only when changing your email address.')
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
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('profile_success', 'Email updated successfully.')
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
            ->assertSee('Email updated')
            ->assertSee('Email updated successfully.');

        $this->assertSame(1, substr_count($profilePage->getContent(), 'Email updated successfully.'));

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Email updated successfully.');
    }

    public function test_current_password_is_required_to_change_email(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => 'changed@example.com',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors([
                'current_password' => 'Current password is required to change your email address.',
            ])
            ->assertSessionMissing('profile_success');

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Current password is required to change your email address.')
            ->assertSee('!border-danger-500', false);

        $this->assertSame('original@example.com', $user->refresh()->email);
    }

    public function test_current_password_must_be_correct_to_change_email(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => 'changed@example.com',
                'current_password' => 'wrong-password',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors([
                'current_password' => 'Current password is incorrect.',
            ])
            ->assertSessionMissing('profile_success');

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Current password is incorrect.')
            ->assertSee('!border-danger-500', false);

        $this->assertSame('original@example.com', $user->refresh()->email);
    }

    public function test_an_incorrect_supplied_password_is_never_accepted_as_a_profile_update(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => $user->email,
                'current_password' => 'wrong-password',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors([
                'current_password' => 'Current password is incorrect.',
            ])
            ->assertSessionMissing('profile_success');

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Current password is incorrect.')
            ->assertDontSee('Profile updated successfully.');
    }

    public function test_duplicate_email_is_rejected_even_with_the_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => 'taken@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('profile_success');

        $this->assertSame('original@example.com', $user->refresh()->email);
    }

    public function test_invalid_email_is_rejected_even_with_the_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => 'not-an-email',
                'current_password' => 'password',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('profile_success');

        $this->assertSame('original@example.com', $user->refresh()->email);
    }

    #[DataProvider('authenticationPanels')]
    public function test_email_change_validation_and_feedback_work_for_every_authentication_panel(
        string $guard,
        UserRole $role,
        string $loginRoute,
        string $activityRoute,
    ): void {
        $user = User::factory()->role($role)->create([
            'email' => $guard.'.original@example.com',
            'password' => 'password',
        ]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route($loginRoute), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->assertAuthenticatedAs($user, $guard);

        $profile = [
            'surname' => $user->surname,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
        ];

        $this->from('/profile')->patch('/profile', [
            ...$profile,
            'email' => $guard.'.wrong-password@example.com',
            'current_password' => 'wrong-password',
        ])->assertRedirect('/profile')
            ->assertSessionHasErrors([
                'current_password' => 'Current password is incorrect.',
            ])
            ->assertSessionMissing('profile_success');

        $this->assertSame($guard.'.original@example.com', $user->refresh()->email);

        // A heartbeat can race the redirect in a real browser. It must not
        // consume the flashed validation error before Profile Settings renders.
        $this->postJson(route($activityRoute))->assertNoContent();

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Current password is incorrect.')
            ->assertDontSee('Email updated successfully.');

        $this->get('/profile')
            ->assertOk()
            ->assertDontSee('Current password is incorrect.');

        $this->from('/profile')->patch('/profile', [
            ...$profile,
            'email' => $guard.'.missing-password@example.com',
        ])->assertRedirect('/profile')
            ->assertSessionHasErrors([
                'current_password' => 'Current password is required to change your email address.',
            ])
            ->assertSessionMissing('profile_success');

        $this->assertSame($guard.'.original@example.com', $user->refresh()->email);

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Current password is required to change your email address.');

        $this->from('/profile')->patch('/profile', [
            ...$profile,
            'email' => 'not-an-email',
            'current_password' => 'password',
        ])->assertRedirect('/profile')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('profile_success');

        $this->assertSame($guard.'.original@example.com', $user->refresh()->email);
        $this->get('/profile')->assertOk();

        $this->from('/profile')->patch('/profile', [
            ...$profile,
            'email' => 'taken@example.com',
            'current_password' => 'password',
        ])->assertRedirect('/profile')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('profile_success');

        $this->assertSame($guard.'.original@example.com', $user->refresh()->email);
        $this->get('/profile')->assertOk();

        $newEmail = $guard.'.updated@example.com';

        $this->patch('/profile', [
            ...$profile,
            'email' => $newEmail,
            'current_password' => 'password',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('profile_success', 'Email updated successfully.')
            ->assertRedirect('/profile');

        $this->assertSame($newEmail, $user->refresh()->email);

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Email updated successfully.')
            ->assertSee('value="'.$newEmail.'"', false);

        $this->get('/profile')
            ->assertOk()
            ->assertDontSee('Email updated successfully.')
            ->assertSee('value="'.$newEmail.'"', false);
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
                'current_password' => 'password',
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

    public function test_profile_explains_account_retention_and_has_no_delete_control(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Account Retention')
            ->assertSee('Your account cannot be permanently deleted.')
            ->assertDontSee('Delete Account');
    }

    public function test_direct_self_deletion_request_is_rejected_and_account_is_retained(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ])->assertStatus(405);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    /**
     * @return array<string, array{string, UserRole, string, string}>
     */
    public static function authenticationPanels(): array
    {
        return [
            'staff' => ['web', UserRole::WarehouseStaff, 'login', 'session.activity'],
            'admin' => ['admin', UserRole::Administrator, 'admin.login.store', 'admin.session.activity'],
            'super admin' => [
                'super_admin',
                UserRole::SuperAdministrator,
                'super-admin.login.store',
                'super-admin.session.activity',
            ],
        ];
    }
}
