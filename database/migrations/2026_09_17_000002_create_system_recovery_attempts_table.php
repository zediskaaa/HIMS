<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of recovery attempts.
 *
 * Each retry writes a new row so the original failure is never overwritten and
 * every attempt stays distinguishable from the incident it belongs to.
 *
 * The indexes carry explicit names. Laravel's generated name for the composite
 * unique is 73 characters, and MySQL/TiDB reject identifiers over 64 — a limit
 * SQLite does not enforce, so the test suite cannot catch this class of defect.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A partially-applied deployment of this migration exists: the table was
        // created before the index names were shortened, and the resulting
        // over-long unique name aborted both index statements. The guard lets
        // this migration finish that deployment rather than requiring a
        // destructive drop of a table that is already live. It is inert on a
        // fresh database, which takes the same path from the create onwards.
        if (! Schema::hasTable('system_recovery_attempts')) {
            Schema::create('system_recovery_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('system_recovery_record_id')
                    ->constrained('system_recovery_records')
                    ->cascadeOnDelete();
                $table->unsignedInteger('attempt_number');
                $table->string('outcome', 16);
                $table->string('handler', 64)->nullable();
                $table->string('message', 500)->nullable();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_snapshot', 255)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('system_recovery_attempts', function (Blueprint $table) {
            // One attempt number per incident: the ledger cannot silently
            // overwrite an earlier attempt.
            $table->unique(
                ['system_recovery_record_id', 'attempt_number'],
                'recovery_attempt_incident_number_unique'
            );

            // Serves the incident detail page, which reads the history in order.
            $table->index(
                ['system_recovery_record_id', 'created_at'],
                'recovery_attempt_incident_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_recovery_attempts');
    }
};
