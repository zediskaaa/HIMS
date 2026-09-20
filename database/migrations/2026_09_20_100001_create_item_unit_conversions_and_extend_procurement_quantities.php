<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('purchase_unit', 50);
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['item_id', 'purchase_unit'], 'item_unit_conv_unique');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('purchase_unit', 50)->nullable()->after('quantity');
            $table->decimal('conversion_factor', 12, 4)->default(1)->after('purchase_unit');
        });

        Schema::table('po_line_items', function (Blueprint $table) {
            $table->string('purchase_unit', 50)->nullable()->after('line_number');
            $table->decimal('conversion_factor', 12, 4)->default(1)->after('purchase_unit');
        });

        Schema::table('grn_line_items', function (Blueprint $table) {
            $table->string('purchase_unit', 50)->nullable()->after('item_batch_id');
            $table->decimal('conversion_factor', 12, 4)->default(1)->after('purchase_unit');
            $table->integer('received_base_quantity')->default(0)->after('received_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('grn_line_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'conversion_factor', 'received_base_quantity']);
        });

        Schema::table('po_line_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'conversion_factor']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'conversion_factor']);
        });

        Schema::dropIfExists('item_unit_conversions');
    }
};
