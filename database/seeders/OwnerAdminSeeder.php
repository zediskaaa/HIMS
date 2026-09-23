<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\PasswordStandard;
use App\Services\PasswordHistoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

class OwnerAdminSeeder extends Seeder
{
    public function run(): void
    {
        $account = $this->configuredAccount();
        if ($account === null) {
            $this->command?->line('Owner Administrator provisioning skipped because HIMS_OWNER_ADMIN_* values are not configured.');

            return;
        }

        if (User::query()->where('email', $account['email'])->exists()) {
            $this->command?->line('Preserved the configured Owner Administrator account.');

            return;
        }

        $passwords = app(PasswordHistoryService::class);

        User::withoutEvents(function () use ($account, $passwords): void {
            $passwords->usePassword(
                $account['password'],
                function (string $passwordHash) use ($account): User {
                    $user = new User;
                    $user->forceFill([
                        'name' => $account['name'],
                        'email' => $account['email'],
                        'phone' => $account['phone'],
                        'email_verified_at' => now(),
                        'password' => $passwordHash,
                        'password_changed_at' => now(),
                        'role' => UserRole::Administrator,
                        'status' => UserStatus::Active,
                        'employee_id' => $this->availableEmployeeId(),
                        'department' => $account['department'],
                        'mfa_enabled' => false,
                    ])->save();

                    return $user;
                },
            );
        });
    }

    /** @return array{name: string, email: string, phone: ?string, department: string, password: string}|null */
    private function configuredAccount(): ?array
    {
        $account = config('account_provisioning.owner_admin', []);

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

    private function availableEmployeeId(): string
    {
        $number = 1;

        do {
            $employeeId = 'ADM-'.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (User::query()->where('employee_id', $employeeId)->exists());

        return $employeeId;
    }
}
