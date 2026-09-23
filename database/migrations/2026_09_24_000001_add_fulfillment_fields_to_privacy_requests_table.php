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
        Schema::table('privacy_requests', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('handled_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('target_completion_date')->nullable()->after('approved_by_user_id');
            $table->timestamp('processing_started_at')->nullable()->after('target_completion_date');
            $table->timestamp('fulfilled_at')->nullable()->after('processing_started_at');
            $table->string('package_filename', 255)->nullable()->after('export_payload');
            $table->string('package_path', 255)->nullable()->after('package_filename');
            $table->string('package_hash', 64)->nullable()->after('package_path');
            $table->unsignedBigInteger('package_size_bytes')->nullable()->after('package_hash');
            $table->json('package_manifest')->nullable()->after('package_size_bytes');
            $table->timestamp('package_expires_at')->nullable()->after('package_manifest');
            $table->unsignedInteger('download_count')->default(0)->after('package_expires_at');
            $table->timestamp('last_downloaded_at')->nullable()->after('download_count');
            $table->json('exclusions_summary')->nullable()->after('last_downloaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'approved_at',
                'approved_by_user_id',
                'target_completion_date',
                'processing_started_at',
                'fulfilled_at',
                'package_filename',
                'package_path',
                'package_hash',
                'package_size_bytes',
                'package_manifest',
                'package_expires_at',
                'download_count',
                'last_downloaded_at',
                'exclusions_summary',
            ]);
        });
    }
};
