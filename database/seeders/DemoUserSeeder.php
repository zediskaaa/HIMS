<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\PasswordHistoryService;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Create one reusable demonstration account for every non-Super-Admin role.
     *
     * The protected Super Administrator remains owned by SuperAdminSeeder. An
     * existing demo account is never reset, reactivated, or reassigned here, so
     * re-running sample data cannot undo a password or account change.
     */
    public function run(): void
    {
        $passwords = app(PasswordHistoryService::class);

        foreach ($this->accounts() as $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $this->command?->line("Preserved existing demo account: {$account['email']}");

                continue;
            }

            User::withoutEvents(function () use ($account, $passwords): void {
                $passwords->usePassword(
                    $account['password'],
                    function (string $passwordHash) use ($account): User {
                        $user = new User;
                        $user->forceFill([
                            'name' => $account['name'],
                            'email' => $account['email'],
                            'email_verified_at' => now(),
                            'password' => $passwordHash,
                            'password_changed_at' => now(),
                            'role' => $account['role'],
                            'status' => UserStatus::Active,
                            'employee_id' => $this->availableEmployeeId($account['employee_id']),
                            'department' => $account['department'],
                            'phone' => $account['phone'],
                            'mfa_enabled' => false,
                        ])->save();

                        return $user;
                    },
                );
            });
        }

        $this->command?->info('Demo user accounts are available for every non-Super-Admin role.');
    }

    /**
     * @return array<int, array{role: UserRole, name: string, email: string, employee_id: string, department: string, phone: string, password: string}>
     */
    private function accounts(): array
    {
        return [
            [
                'role' => UserRole::Administrator,
                'name' => 'Adrian Mendoza',
                'email' => 'test@example.com',
                'employee_id' => 'EMP-0001',
                'department' => 'Information Technology',
                'phone' => '09170000001',
                'password' => 'DemoAdmin1!',
            ],
            [
                'role' => UserRole::InventoryManager,
                'name' => 'Ana Reyes',
                'email' => 'ana.reyes@djnrmhs.test',
                'employee_id' => 'EMP-0002',
                'department' => 'Central Supply',
                'phone' => '09170000002',
                'password' => 'DemoInventory1!',
            ],
            [
                'role' => UserRole::WarehouseStaff,
                'name' => 'Ben Santos',
                'email' => 'ben.santos@djnrmhs.test',
                'employee_id' => 'EMP-0003',
                'department' => 'Warehouse',
                'phone' => '09170000003',
                'password' => 'DemoWarehouse1!',
            ],
            [
                'role' => UserRole::PharmacyStaff,
                'name' => 'Cely Dizon',
                'email' => 'cely.dizon@djnrmhs.test',
                'employee_id' => 'EMP-0004',
                'department' => 'Pharmacy',
                'phone' => '09170000004',
                'password' => 'DemoPharmacy1!',
            ],
            [
                'role' => UserRole::Auditor,
                'name' => 'Dino Cruz',
                'email' => 'dino.cruz@djnrmhs.test',
                'employee_id' => 'EMP-0005',
                'department' => 'Internal Audit',
                'phone' => '09170000005',
                'password' => 'DemoAuditor1!',
            ],
            [
                'role' => UserRole::Viewer,
                'name' => 'Ella Flores',
                'email' => 'ella.flores@djnrmhs.test',
                'employee_id' => 'EMP-0006',
                'department' => 'Quality Office',
                'phone' => '09170000006',
                'password' => 'DemoViewer1!',
            ],
        ];
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
