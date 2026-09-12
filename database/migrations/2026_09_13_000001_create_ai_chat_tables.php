<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $table->string('role', 20); // user, assistant
            $table->longText('content');
            $table->string('attachment_name')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_type', 50)->nullable(); // spreadsheet, document, image, text
            $table->string('attachment_extension', 20)->nullable();
            $table->string('attachment_size', 50)->nullable();
            $table->boolean('is_error')->default(false);
            $table->string('source', 50)->nullable(); // ai, grounded_fallback, system
            $table->string('status_hint')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_conversations');
    }
};
