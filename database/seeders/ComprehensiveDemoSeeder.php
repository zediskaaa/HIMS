<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class ComprehensiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Comprehensive demonstration data is disabled in production.');
        }

        $this->call([
            SuperAdminSeeder::class,
            DemoUserSeeder::class,
            InventoryDemoSeeder::class,
            SupplierManagementDemoSeeder::class,
            ProcurementDemoSeeder::class,
            SmartWarehousingDemoSeeder::class,
            LogisticsDemoSeeder::class,
            ProcessReviewDemoSeeder::class,
            OperationalMetricsDemoSeeder::class,
            ErrorRecoveryDemoSeeder::class,
        ]);

        $this->command?->info('Comprehensive HIMS sample data is ready. See docs/demo-data.md for the account and module guide.');
    }
}
