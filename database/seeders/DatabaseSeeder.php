<?php

namespace Database\Seeders;

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
        // System-owned and idempotent. It is seeded separately from demo data
        // so production setup can run only SuperAdminSeeder when appropriate.
        $this->call(SuperAdminSeeder::class);

        $this->call(DemoUserSeeder::class);

        $this->call(InventoryDemoSeeder::class);
        $this->call(SupplierManagementDemoSeeder::class);
    }
}
