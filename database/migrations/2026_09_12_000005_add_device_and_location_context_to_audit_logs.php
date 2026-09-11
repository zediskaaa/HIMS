<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('device_type', 64)->nullable()->after('user_agent');
            $table->string('device_name')->nullable()->after('device_type');
            $table->string('operating_system', 128)->nullable()->after('device_name');
            $table->string('browser', 128)->nullable()->after('operating_system');
            $table->string('location_city', 128)->nullable()->after('browser');
            $table->string('location_region', 128)->nullable()->after('location_city');
            $table->string('location_country', 128)->nullable()->after('location_region');
            $table->char('location_country_code', 2)->nullable()->after('location_country');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'device_name',
                'operating_system',
                'browser',
                'location_city',
                'location_region',
                'location_country',
                'location_country_code',
            ]);
        });
    }
};
