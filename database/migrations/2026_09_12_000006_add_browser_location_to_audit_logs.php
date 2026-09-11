<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('location_source', 24)->nullable()->after('location_country_code');
            $table->decimal('location_latitude', 8, 4)->nullable()->after('location_source');
            $table->decimal('location_longitude', 9, 4)->nullable()->after('location_latitude');
            $table->unsignedInteger('location_accuracy_meters')->nullable()->after('location_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn([
                'location_source',
                'location_latitude',
                'location_longitude',
                'location_accuracy_meters',
            ]);
        });
    }
};
