<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Branding only: displayed in the directory and on the supplier profile.
            // It is deliberately not a compliance document, so it carries no
            // verification status and never blocks procurement eligibility.
            $table->string('logo_path')->nullable()->after('provides_regulated_health_products');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
