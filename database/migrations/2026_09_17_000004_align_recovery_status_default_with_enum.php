<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status column defaulted to 'pending', which is not a RecoveryStatus case.
 * The 2026_09_17_000003 migration mapped every stored row onto the new
 * vocabulary, but the column default still names the old one, so any insert
 * that omits a status writes a value the enum cast cannot read: reading the row
 * back throws a ValueError and takes the Recovery Center index down with it.
 *
 * The default becomes 'failed', which is where a recorded failure begins. It is
 * deliberately not 'not_recoverable': that status is outside the open() scope,
 * so an incident landing there would be absent from the Recovery Center's
 * working set — a failure hidden rather than surfaced. A 'failed' row with no
 * retry handler offers no retry, so the default cannot manufacture a misleading
 * Retry control either.
 *
 * Every path that records a failure already sets the status explicitly
 * (SafeExecutionService::recordFailure assigns Failed or NotRecoverable), so
 * this changes no stored row. It makes the schema stop contradicting the enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_recovery_records', function (Blueprint $table) {
            $table->string('status', 32)->default('failed')->change();
        });
    }

    public function down(): void
    {
        Schema::table('system_recovery_records', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->change();
        });
    }
};
