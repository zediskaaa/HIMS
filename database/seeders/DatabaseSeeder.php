<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoCredentials = [
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
        ];

        // System-owned and idempotent. It is seeded separately from demo data
        // so production setup can run only SuperAdminSeeder when appropriate.
        $this->call(SuperAdminSeeder::class);

        // The demo account is the administrator: without it nobody can reach
        // the user-management screens to create anyone else.
        User::factory()->administrator()->create([
            ...$demoCredentials,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'employee_id' => 'EMP-0001',
            'department' => 'Administration',
        ]);

        // One account per remaining role, so the permission differences are
        // demonstrable by signing in rather than only described. All share the
        // factory's 'password'.
        $staff = [
            [UserRole::InventoryManager, 'Ana Reyes', 'ana.reyes@djnrmhs.test', 'EMP-0002', 'Central Supply'],
            [UserRole::WarehouseStaff, 'Ben Santos', 'ben.santos@djnrmhs.test', 'EMP-0003', 'Warehouse'],
            [UserRole::PharmacyStaff, 'Cely Dizon', 'cely.dizon@djnrmhs.test', 'EMP-0004', 'Pharmacy'],
            [UserRole::Viewer, 'Dino Cruz', 'dino.cruz@djnrmhs.test', 'EMP-0005', 'Internal Audit'],
        ];

        foreach ($staff as [$role, $name, $email, $employeeId, $department]) {
            User::factory()->role($role)->create([
                ...$demoCredentials,
                'name' => $name,
                'email' => $email,
                'employee_id' => $employeeId,
                'department' => $department,
            ]);
        }

        $this->call(InventoryDemoSeeder::class);
    }
}
