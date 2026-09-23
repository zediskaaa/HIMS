<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\PasswordStandard;
use App\Services\PasswordHistoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('is_protected', true)->where('role', UserRole::SuperAdministrator->value)->exists()) {
            $this->command?->line('Preserved the existing protected Super Administrator account.');

            return;
        }

        $account = $this->configuredAccount();
        if ($account === null) {
            $this->command?->warn('Super Administrator provisioning skipped. Use hims:create-super-admin or configure HIMS_SUPER_ADMIN_* values.');

            return;
        }

        $passwords = app(PasswordHistoryService::class);

        User::withoutEvents(function () use ($account, $passwords): void {
            $user = User::query()->firstOrNew(['email' => $account['email']]);
            $employeeId = $user->employee_id ?: $this->availableEmployeeId($user);

            $passwords->usePassword(
                $account['password'],
                function (string $passwordHash) use ($user, $account, $employeeId): User {
                    $user->forceFill([
                        'name' => $account['name'],
                        'email' => $account['email'],
                        'phone' => $account['phone'],
                        'email_verified_at' => now(),
                        'password' => $passwordHash,
                        'password_changed_at' => now(),
                        'role' => UserRole::SuperAdministrator,
                        'status' => UserStatus::Active,
                        'is_protected' => true,
                        'employee_id' => $employeeId,
                        'department' => $account['department'],
                    ])->save();

                    return $user;
                },
            );
        });
    }

    /** @return array{name: string, email: string, phone: ?string, department: string, password: string}|null */
    private function configuredAccount(): ?array
    {
        $account = config('account_provisioning.super_admin', []);

        if (! is_array($account) || blank($account['name'] ?? null) || blank($account['email'] ?? null) || blank($account['password'] ?? null)) {
            return null;
        }

        return Validator::make($account, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', new PasswordStandard],
        ])->validate();
    }

    private function availableEmployeeId(User $user): string
    {
        $number = 1;

        do {
            $employeeId = 'SA-'.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (User::query()
            ->where('employee_id', $employeeId)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists());

        return $employeeId;
    }
}
