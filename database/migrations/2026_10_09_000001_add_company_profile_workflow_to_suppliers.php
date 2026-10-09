<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('company_profile_status', 30)->default('draft')->index()->after('accreditation_status');
            $table->longText('company_profile_draft')->nullable()->after('company_profile_status');
            $table->text('company_profile_feedback')->nullable()->after('company_profile_draft');
            $table->timestamp('company_profile_submitted_at')->nullable()->after('company_profile_feedback');
            $table->timestamp('company_profile_reviewed_at')->nullable()->after('company_profile_submitted_at');
            $table->foreignId('company_profile_reviewed_by')->nullable()->after('company_profile_reviewed_at')->constrained('users')->nullOnDelete();
        });

        DB::table('suppliers')->where('accreditation_status', 'approved')->update(['company_profile_status' => 'approved']);
        DB::table('suppliers')->where('accreditation_status', 'pending_review')->update(['company_profile_status' => 'pending_review']);
        DB::table('suppliers')->where('accreditation_status', 'rejected')->update(['company_profile_status' => 'rejected']);
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex(['company_profile_status']);
            $table->dropConstrainedForeignId('company_profile_reviewed_by');
            $table->dropColumn([
                'company_profile_status',
                'company_profile_draft',
                'company_profile_feedback',
                'company_profile_submitted_at',
                'company_profile_reviewed_at',
            ]);
        });
    }
};
