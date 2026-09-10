<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_stock_levels', function (Blueprint $table) {
            $table->integer('quarantined_quantity')->default(0)->after('reserved_quantity');
            $table->integer('blocked_quantity')->default(0)->after('quarantined_quantity');
            $table->integer('in_transit_quantity')->default(0)->after('blocked_quantity');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('is_serial_tracked')->default(false)->after('is_batch_tracked');
            $table->string('abc_class', 1)->default('B')->after('is_serial_tracked');
            $table->string('costing_method', 20)->default('moving_average')->after('abc_class');
            $table->unsignedInteger('safety_stock')->default(0)->after('reorder_level');
            $table->unsignedInteger('reorder_point')->default(0)->after('safety_stock');
            $table->unsignedInteger('economic_order_quantity')->default(0)->after('reorder_point');
            $table->unsignedInteger('lead_time_days')->default(7)->after('economic_order_quantity');
            $table->unsignedInteger('annual_demand')->default(0)->after('lead_time_days');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_serial_tracked',
                'abc_class',
                'costing_method',
                'safety_stock',
                'reorder_point',
                'economic_order_quantity',
                'lead_time_days',
                'annual_demand',
            ]);
        });

        Schema::table('item_stock_levels', function (Blueprint $table) {
            $table->dropColumn([
                'quarantined_quantity',
                'blocked_quantity',
                'in_transit_quantity',
            ]);
        });
    }
};
