<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_line_items', function (Blueprint $table) {
            $table->string('item_condition', 50)->default('good')->after('status');
            $table->string('discrepancy_type', 50)->nullable()->after('item_condition');
            $table->string('discrepancy_action', 50)->nullable()->after('discrepancy_type');
            $table->text('discrepancy_notes')->nullable()->after('discrepancy_action');
        });
    }

    public function down(): void
    {
        Schema::table('grn_line_items', function (Blueprint $table) {
            $table->dropColumn([
                'item_condition',
                'discrepancy_type',
                'discrepancy_action',
                'discrepancy_notes',
            ]);
        });
    }
};
