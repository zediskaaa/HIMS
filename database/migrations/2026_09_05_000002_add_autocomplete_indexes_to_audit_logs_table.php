<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('actor_name');
            $table->index('actor_employee_id');
            $table->index('target_name');
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['actor_name']);
            $table->dropIndex(['actor_employee_id']);
            $table->dropIndex(['target_name']);
            $table->dropIndex(['ip_address']);
        });
    }
};
