<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use App\Services\PasswordHistoryService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GlobalPasswordHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const USED_PASSWORD = 'GloballyUsed1!';

    private const CURRENT_PASSWORD = 'CurrentSecure1!';

    public static function authenticationPanels(): array
    {
        return [
            'staff' => ['staff', AuthenticationContext::WEB_GUARD],
            'admin' => ['admin', AuthenticationContext::ADMIN_GUARD],
            'super admin' => ['super-admin', AuthenticationContext::SUPER_ADMIN_GUARD],
        ];
    }

    #[DataProvider('authenticationPanels')]
    public function test_every_role_receives_the_same_generic_rejection_for_a_globally_used_password(
        string $panel,
        string $guard,
    ): void {
        $owner = User::factory()->create(['password' => self::USED_PASSWORD]);
        $this->recordCurrentPassword($owner, now()->subYears(3));

        $user = $this->userForPanel($panel);
        $user->forceFill(['password' => self::CURRENT_PASSWORD])->save();
        $originalHash = $user->password;

        $this->actingAs($user, $guard)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => self::USED_PASSWORD,
                'password_confirmation' => self::USED_PASSWORD,
            ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('updatePassword', [
                'password' => PasswordHistoryService::REJECTION_MESSAGE,
            ]);

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_profile_changes_are_recorded_and_an_old_password_stays_blocked(): void
    {
        $user = User::factory()->warehouseStaff()->create(['password' => self::CURRENT_PASSWORD]);
        $this->recordCurrentPassword($user);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => 'CompletelyNew2!',
                'password_confirmation' => 'CompletelyNew2!',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('CompletelyNew2!', $user->fresh()->password));
        $this->assertSame(2, PasswordHistory::query()->count());

        $this->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => 'CompletelyNew2!',
            'password' => self::CURRENT_PASSWORD,
            'password_confirmation' => self::CURRENT_PASSWORD,
        ])->assertSessionHasErrorsIn('updatePassword', [
            'password' => PasswordHistoryService::REJECTION_MESSAGE,
        ]);
    }

    public function test_registration_and_user_management_cannot_reuse_an_inactive_accounts_password(): void
    {
        $formerUser = User::factory()->create([
            'password' => self::USED_PASSWORD,
            'status' => UserStatus::Inactive,
        ]);
        $this->recordCurrentPassword($formerUser, now()->subYears(5));

        $this->from(route('register'))->post(route('register'), [
            'name' => 'New Registrant',
            'email' => 'registrant@example.test',
            'password' => self::USED_PASSWORD,
            'password_confirmation' => self::USED_PASSWORD,
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'password' => PasswordHistoryService::REJECTION_MESSAGE,
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'registrant@example.test']);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), $this->userPayload(self::USED_PASSWORD))
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors([
                'password' => PasswordHistoryService::REJECTION_MESSAGE,
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'managed@example.test']);
    }

    public function test_password_reset_cannot_reuse_another_users_password(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['password' => self::USED_PASSWORD]);
        $this->recordCurrentPassword($owner);
        $account = User::factory()->warehouseStaff()->create(['password' => self::CURRENT_PASSWORD]);
        $originalHash = $account->password;

        $this->post(route('password.email'), ['email' => $account->email]);
        $otp = Notification::sent($account, PasswordResetOtp::class)->firstOrFail()->otp;
        $verification = $this->post(route('password.otp.verify'), [
            'email' => $account->email,
            'otp' => $otp,
        ]);
        $token = basename((string) parse_url($verification->headers->get('Location'), PHP_URL_PATH));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $account->email,
            'password' => self::USED_PASSWORD,
            'password_confirmation' => self::USED_PASSWORD,
        ])->assertSessionHasErrors([
            'password' => PasswordHistoryService::REJECTION_MESSAGE,
        ]);

        $this->assertSame($originalHash, $account->fresh()->password);
    }

    public function test_expired_password_replacement_cannot_use_global_history(): void
    {
        $owner = User::factory()->create(['password' => self::USED_PASSWORD]);
        $this->recordCurrentPassword($owner);
        $expired = User::factory()->warehouseStaff()->create([
            'password' => self::CURRENT_PASSWORD,
            'password_changed_at' => now()->subDays(90),
        ]);

        $this->post(route('login'), [
            'email' => $expired->email,
            'password' => self::CURRENT_PASSWORD,
        ])->assertRedirect(route('password.expired'));

        $this->from(route('password.expired'))->put(route('password.expired.update'), [
            'password' => self::USED_PASSWORD,
            'password_confirmation' => self::USED_PASSWORD,
        ])->assertRedirect(route('password.expired'))
            ->assertSessionHasErrors([
                'password' => PasswordHistoryService::REJECTION_MESSAGE,
            ]);

        $this->assertGuest();
        $this->assertTrue($expired->fresh()->passwordHasExpired());
    }

    public function test_new_password_and_history_record_roll_back_together_on_failure(): void
    {
        $user = User::factory()->create(['password' => self::CURRENT_PASSWORD]);
        $originalHash = $user->password;

        try {
            app(PasswordHistoryService::class)->usePassword(
                'RollbackCandidate3!',
                function (string $passwordHash) use ($user): User {
                    $user->forceFill(['password' => $passwordHash])->save();

                    throw new RuntimeException('Simulated failure after the password write.');
                },
            );

            $this->fail('The simulated failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after the password write.', $exception->getMessage());
        }

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertDatabaseCount('password_histories', 0);
    }

    public function test_history_serialization_and_rejection_do_not_disclose_security_details(): void
    {
        $owner = User::factory()->create(['password' => self::USED_PASSWORD]);
        $history = $this->recordCurrentPassword($owner);

        $this->assertArrayNotHasKey('password_hash', $history->toArray());
        $this->assertArrayNotHasKey('password_fingerprint', $history->toArray());

        $candidate = User::factory()->warehouseStaff()->create(['password' => self::CURRENT_PASSWORD]);
        $response = $this->actingAs($candidate)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => self::USED_PASSWORD,
                'password_confirmation' => self::USED_PASSWORD,
            ]);

        $response->assertSessionHasErrorsIn('updatePassword', [
            'password' => PasswordHistoryService::REJECTION_MESSAGE,
        ]);
        $response->assertSessionHas('_old_input', fn (array $input): bool => ! array_key_exists('password', $input));
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(PasswordHistoryService::REJECTION_MESSAGE)
            ->assertDontSee($owner->email)
            ->assertDontSee($history->password_hash);
        $this->assertStringNotContainsString($owner->email, (string) $response->getContent());
        $this->assertStringNotContainsString($history->password_hash, (string) $response->getContent());
    }

    private function recordCurrentPassword(User $user, mixed $usedAt = null): PasswordHistory
    {
        return PasswordHistory::query()->create([
            'user_id' => $user->getKey(),
            'password_hash' => $user->password,
            'used_at' => $usedAt ?? now(),
        ]);
    }

    private function userForPanel(string $panel): User
    {
        return match ($panel) {
            'admin' => User::factory()->administrator()->create(),
            'super-admin' => User::factory()->superAdministrator()->create(),
            default => User::factory()->warehouseStaff()->create(),
        };
    }

    /** @return array<string, string> */
    private function userPayload(string $password): array
    {
        return [
            'surname' => 'Managed',
            'first_name' => 'Account',
            'email' => 'managed@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => 'viewer',
            'department' => 'Administration',
            'phone' => '09171234567',
        ];
    }
}
