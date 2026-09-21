<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 500)->nullable()->after('archived_by');

            $table->index(['status', 'archived_at'], 'inventory_items_status_archived_at_index');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 500)->nullable()->after('archived_by');

            $table->index(['status', 'archived_at'], 'suppliers_status_archived_at_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 500)->nullable()->after('archived_by');

            $table->index(['status', 'archived_at'], 'users_status_archived_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropIndex('inventory_items_status_archived_at_index');
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('suppliers_status_archived_at_index');
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_status_archived_at_index');
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
        });
    }
};
