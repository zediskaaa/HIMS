<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('authenticator_secret')->nullable()->after('mfa_enabled');
            $table->timestamp('authenticator_enabled_at')->nullable()->after('authenticator_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['authenticator_secret', 'authenticator_enabled_at']);
        });
    }
};
