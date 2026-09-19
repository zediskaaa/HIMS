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
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number', 32)->unique();
            $table->string('title', 150);
            $table->string('category', 50); // unauthorized_access_attempt, brute_force_spike, suspicious_export, credential_anomaly, data_leakage_risk, system_tampering
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->string('status', 30)->default('detected'); // detected, investigating, contained, resolved, false_positive
            $table->text('description');
            $table->string('affected_system_or_data', 150);
            $table->boolean('is_suspected_breach')->default(false);
            $table->text('breach_assessment')->nullable();
            $table->text('containment_actions')->nullable();
            $table->text('remediation_notes')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('severity');
            $table->index('is_suspected_breach');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
