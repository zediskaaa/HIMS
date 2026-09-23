<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['item_id', 'moved_at'], 'stock_movements_item_date_idx');
            $table->index(['moved_at', 'id'], 'stock_movements_date_id_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('requested_at', 'purchase_orders_requested_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('purchase_orders_requested_at_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_date_id_idx');
            $table->dropIndex('stock_movements_item_date_idx');
        });
    }
};
