<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\PasswordStandard;
use App\Services\PasswordHistoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = config('account_provisioning.demo_accounts', []);

        if (! is_array($accounts) || $accounts === []) {
            $this->command?->line('Demo account provisioning skipped because HIMS_DEMO_ACCOUNTS_JSON is not configured.');

            return;
        }

        $passwords = app(PasswordHistoryService::class);

        foreach ($accounts as $account) {
            $account = $this->validateAccount($account);
            $role = UserRole::from($account['role']);

            if (User::query()->where('email', $account['email'])->exists()) {
                $this->command?->line("Preserved existing configured demo account: {$account['email']}");

                continue;
            }

            User::withoutEvents(function () use ($account, $passwords, $role): void {
                $passwords->usePassword(
                    $account['password'],
                    function (string $passwordHash) use ($account, $role): User {
                        $user = new User;
                        $user->forceFill([
                            'name' => $account['name'],
                            'email' => $account['email'],
                            'email_verified_at' => now(),
                            'password' => $passwordHash,
                            'password_changed_at' => now(),
                            'role' => $role,
                            'status' => UserStatus::Active,
                            'employee_id' => $this->availableEmployeeId($account['employee_id']),
                            'department' => $account['department'],
                            'phone' => $account['phone'] ?? null,
                            'mfa_enabled' => false,
                        ])->save();

                        return $user;
                    },
                );
            });
        }
    }

    /** @return array{name: string, email: string, employee_id: string, department: string, phone: ?string, password: string, role: string} */
    private function validateAccount(mixed $account): array
    {
        return Validator::make(is_array($account) ? $account : [], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'employee_id' => ['required', 'string', 'max:50'],
            'department' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', new PasswordStandard],
            'role' => [
                'required',
                Rule::in(collect(UserRole::cases())
                    ->reject(fn (UserRole $role) => $role === UserRole::SuperAdministrator)
                    ->map->value
                    ->all()),
            ],
        ])->validate();
    }

    private function availableEmployeeId(string $preferred): string
    {
        $employeeId = $preferred;
        $suffix = 2;

        while (User::query()->where('employee_id', $employeeId)->exists()) {
            $employeeId = $preferred.'-'.$suffix++;
        }

        return $employeeId;
    }
}
