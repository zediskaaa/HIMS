<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('source_location_id')->constrained('storage_locations');
            $table->foreignId('destination_location_id')->constrained('storage_locations');
            $table->foreignId('in_transit_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('status', 30)->default('draft'); // draft, dispatched, in_transit, received, cancelled, discrepancy
            $table->foreignId('dispatched_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispatched_at')->nullable();
            $table->foreignId('received_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('discrepancy_reason')->nullable();
            $table->timestamps();

            $table->index(['source_location_id', 'status']);
            $table->index(['destination_location_id', 'status']);
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->integer('dispatched_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->integer('lost_quantity')->default(0);
            $table->string('line_status', 30)->default('pending'); // pending, in_transit, received, discrepancy
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'line_status']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('storage_location_id')->constrained('storage_locations');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->integer('current_quantity');
            $table->integer('adjustment_quantity'); // signed delta
            $table->integer('resulting_quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_variance_value', 12, 2)->default(0);
            $table->string('adjustment_type', 30); // increase, decrease, correction, count_variance, damage, loss, breakage, expiry, disposal
            $table->string('reason_code', 50); // count_variance, damage, breakage, loss, expiry, data_correction, other
            $table->text('explanation');
            $table->string('status', 30)->default('pending_approval'); // pending_approval, approved, rejected, posted
            $table->foreignId('requested_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('second_approved_by_id')->nullable()->constrained('users')->nullOnDelete(); // for > ₱25,000 threshold
            $table->dateTime('posted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
