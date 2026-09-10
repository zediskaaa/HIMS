<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseCheck extends Command
{
    protected $signature = 'db:check';

    protected $description = 'Verify database connection and show basic info';

    public function handle(): int
    {
        $this->info('Testing database connection...');

        try {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            DB::connection()->getPdo();
            $this->info("[OK] Connected to {$connection}");

            $driver = DB::connection()->getDriverName();
            $this->line("  Driver: {$driver}");
            $this->line("  Database: {$database}");

            if ($driver === 'mysql') {
                $version = DB::selectOne('SELECT VERSION() as version');
                $this->line("  Version: {$version->version}");
            }

            $this->newLine();
            $this->info('Checking key tables...');

            $tables = [
                'users',
                'inventory_items',
                'item_stock_levels',
                'stock_movements',
                'suppliers',
                'storage_locations',
            ];

            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $this->line("  {$table}: {$count} rows");
                } catch (\Exception $e) {
                    $this->error("  {$table}: missing or inaccessible");
                }
            }

            $this->newLine();
            $this->info('Connection verified [OK]');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
