<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_count_docs', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('count_type', 30)->default('ABC'); // ABC, random, annual, spot
            $table->date('scheduled_date');
            $table->foreignId('assigned_counter_id')->constrained('users');
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('status', 30)->default('generated'); // generated, active_count, recount_pending, approved, posted, cancelled
            $table->dateTime('snapshot_timestamp');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_date']);
        });

        Schema::create('cycle_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_count_doc_id')->constrained('cycle_count_docs')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('storage_location_id')->constrained('storage_locations');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->integer('book_quantity_snapshot');
            $table->integer('counted_quantity_blind')->nullable();
            $table->integer('variance_quantity')->default(0);
            $table->decimal('variance_value', 12, 2)->default(0);
            $table->boolean('recount_required')->default(false);
            $table->string('status', 30)->default('pending'); // pending, counted, recount_requested, resolved
            $table->foreignId('inventory_adjustment_id')->nullable()->constrained('inventory_adjustments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_count_lines');
        Schema::dropIfExists('cycle_count_docs');
    }
};
