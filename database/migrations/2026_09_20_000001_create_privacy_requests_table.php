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
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requestor_name', 120);
            $table->string('requestor_email', 150);
            $table->string('request_type', 40); // access, rectification, erasure_review, inquiry, objection
            $table->text('details');
            $table->string('status', 40)->default('pending'); // pending, under_review, approved, fulfilled, rejected_with_legal_ground, closed
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('export_payload')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('request_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
