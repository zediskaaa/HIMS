<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the traceability fields the Recovery Center needs to describe an
 * incident honestly: what kind of operation failed, which resource it touched,
 * the natural reference ID of that operation, and the observed outcome of the
 * last recovery attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_recovery_records', function (Blueprint $table) {
            $table->string('failure_type', 32)->default('application')->after('module')->index();
            $table->string('affected_resource', 255)->nullable()->after('error_summary');
            $table->string('reference_id', 64)->nullable()->after('affected_resource')->index();
            $table->string('last_attempt_outcome', 16)->nullable()->after('last_retried_at');
            $table->string('last_attempt_error', 500)->nullable()->after('last_attempt_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('system_recovery_records', function (Blueprint $table) {
            $table->dropIndex(['reference_id']);
            $table->dropIndex(['failure_type']);
            $table->dropColumn([
                'failure_type',
                'affected_resource',
                'reference_id',
                'last_attempt_outcome',
                'last_attempt_error',
            ]);
        });
    }
};
