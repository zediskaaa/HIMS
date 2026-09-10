<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->unsignedBigInteger('procurement_request_id')->nullable()->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->unsignedBigInteger('procurement_request_id')->nullable(false)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
        });
    }
};
