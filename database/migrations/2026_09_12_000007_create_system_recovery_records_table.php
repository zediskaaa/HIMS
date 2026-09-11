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
        Schema::create('system_recovery_records', function (Blueprint $table) {
            $table->id();
            $table->string('error_id', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_snapshot', 255)->nullable();
            $table->string('module', 64)->index();
            $table->string('operation', 100)->index();
            $table->string('error_summary', 500);
            $table->string('exception_class', 255)->nullable();
            $table->json('technical_details')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('strategy_applied', 64)->nullable();
            $table->boolean('is_retryable')->default(false)->index();
            $table->string('retry_handler', 64)->nullable();
            $table->json('retry_payload')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_retried_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['module', 'status']);
            $table->index(['operation', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_recovery_records');
    }
};
