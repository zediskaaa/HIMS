<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('event_id')->nullable()->unique()->after('id');
            $table->string('event_category', 64)->nullable()->index()->after('action');
            $table->string('module', 64)->nullable()->index()->after('event_category');
            $table->string('actor_role', 64)->nullable()->index()->after('actor_employee_id');
            $table->string('outcome', 16)->nullable()->index()->after('description');
            $table->string('source', 24)->nullable()->index()->after('outcome');
            $table->string('correlation_id', 100)->nullable()->index()->after('source');
            $table->string('target_reference')->nullable()->index()->after('target_name');
            $table->text('business_reason')->nullable()->after('description');
            $table->dateTime('occurred_at_utc', 6)->nullable()->index()->after('user_agent');
            $table->string('display_timezone', 64)->nullable()->after('occurred_at_utc');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropUnique(['event_id']);
            $table->dropIndex(['event_category']);
            $table->dropIndex(['module']);
            $table->dropIndex(['actor_role']);
            $table->dropIndex(['outcome']);
            $table->dropIndex(['source']);
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['target_reference']);
            $table->dropIndex(['occurred_at_utc']);
            $table->dropColumn([
                'event_id',
                'event_category',
                'module',
                'actor_role',
                'outcome',
                'source',
                'correlation_id',
                'target_reference',
                'business_reason',
                'occurred_at_utc',
                'display_timezone',
            ]);
        });
    }
};
