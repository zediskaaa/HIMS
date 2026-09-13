<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\PasswordHistoryService;
use Illuminate\Database\Seeder;

/**
 * The account holder's own day-to-day Administrator login.
 *
 * Kept apart from DemoUserSeeder on purpose. That roster is throwaway sample
 * staff any environment may recreate; this is a real person's account, so it
 * stands on its own and is never reset once it exists — re-running sample data
 * cannot undo a password this account changes later.
 */
class OwnerAdminSeeder extends Seeder
{
    public const EMAIL = 'zedrickdemonteverde1@gmail.com';

    public const NAME = 'Zedrick De Monteverde';

    /**
     * Initial setup secret only. Authentication always uses Laravel's hashed
     * password verifier; this value is never written to logs or responses.
     */
    private const INITIAL_PASSWORD = 'ZedrickAdmin2026!';

    public function run(): void
    {
        if (User::query()->where('email', self::EMAIL)->exists()) {
            $this->command?->line('Preserved existing administrator account: '.self::EMAIL);

            return;
        }

        $passwords = app(PasswordHistoryService::class);

        User::withoutEvents(function () use ($passwords): void {
            $passwords->usePassword(
                self::INITIAL_PASSWORD,
                function (string $passwordHash): User {
                    $user = new User;
                    $user->forceFill([
                        'name' => self::NAME,
                        'email' => self::EMAIL,
                        'email_verified_at' => now(),
                        'password' => $passwordHash,
                        'password_changed_at' => now(),
                        'role' => UserRole::Administrator,
                        'status' => UserStatus::Active,
                        'employee_id' => $this->availableEmployeeId(),
                        'department' => 'Information Technology',
                        'mfa_enabled' => false,
                    ])->save();

                    return $user;
                },
            );
        });

        $this->command?->info('Administrator account ready: '.self::EMAIL);
    }

    /**
     * `users.employee_id` is unique, so keep it clear of the demo roster and
     * step past anything already holding the number.
     */
    private function availableEmployeeId(): string
    {
        $employeeId = 'EMP-0100';
        $suffix = 2;

        while (User::query()->where('employee_id', $employeeId)->exists()) {
            $employeeId = 'EMP-0100-'.$suffix++;
        }

        return $employeeId;
    }
}
