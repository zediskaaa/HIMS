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
        // Production receives system-owned account provisioning only.
        $this->call(SuperAdminSeeder::class);
        $this->call(OwnerAdminSeeder::class);

        if (app()->environment('production')) {
            return;
        }

        // Keep one canonical local/demo entry point so a normal db:seed cannot
        // leave later modules missing while earlier demo modules appear ready.
        $this->call(ComprehensiveDemoSeeder::class);
    }
}
