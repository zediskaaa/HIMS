<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PasswordHistoryService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $passwords = app(PasswordHistoryService::class);

        // System-owned and idempotent. It is seeded separately from demo data
        // so production setup can run only SuperAdminSeeder when appropriate.
        $this->call(SuperAdminSeeder::class);

        // The demo account is the administrator: without it nobody can reach
        // the user-management screens to create anyone else.
        $passwords->usePassword(
            'DemoAdmin1!',
            fn (string $passwordHash): User => User::factory()->administrator()->create([
                'password' => $passwordHash,
                'password_changed_at' => now(),
                'name' => 'Test User',
                'email' => 'test@example.com',
                'employee_id' => 'EMP-0001',
                'department' => 'Administration',
            ]),
        );

        // One account per remaining role, so the permission differences are
        // demonstrable by signing in rather than only described. Passwords are
        // deliberately unique because global history permits each only once.
        $staff = [
            [UserRole::InventoryManager, 'Ana Reyes', 'ana.reyes@djnrmhs.test', 'EMP-0002', 'Central Supply', 'DemoInventory1!'],
            [UserRole::WarehouseStaff, 'Ben Santos', 'ben.santos@djnrmhs.test', 'EMP-0003', 'Warehouse', 'DemoWarehouse1!'],
            [UserRole::PharmacyStaff, 'Cely Dizon', 'cely.dizon@djnrmhs.test', 'EMP-0004', 'Pharmacy', 'DemoPharmacy1!'],
            [UserRole::Viewer, 'Dino Cruz', 'dino.cruz@djnrmhs.test', 'EMP-0005', 'Internal Audit', 'DemoViewer1!'],
        ];

        foreach ($staff as [$role, $name, $email, $employeeId, $department, $password]) {
            $passwords->usePassword(
                $password,
                fn (string $passwordHash): User => User::factory()->role($role)->create([
                    'password' => $passwordHash,
                    'password_changed_at' => now(),
                    'name' => $name,
                    'email' => $email,
                    'employee_id' => $employeeId,
                    'department' => $department,
                ]),
            );
        }

        $this->call(InventoryDemoSeeder::class);
        $this->call(SupplierManagementDemoSeeder::class);
    }
}
