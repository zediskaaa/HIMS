<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number')->unique();
            $table->foreignId('requesting_user_id')->constrained('users');
            $table->string('department');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->date('required_date')->nullable();
            $table->string('status', 30)->default('draft'); // draft, pending_approval, approved, picking, issued, cancelled, rejected
            $table->string('urgency', 20)->default('routine'); // routine, urgent, stat_emergency
            $table->text('justification')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['department', 'status']);
            $table->index('required_date');
        });

        Schema::create('material_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_requisition_id')->constrained('material_requisitions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->integer('requested_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('issued_quantity')->default(0);
            $table->string('allocation_strategy', 20)->default('FEFO'); // FEFO, FIFO, MANUAL
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('line_status', 30)->default('pending'); // pending, reserved, partially_issued, issued, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'line_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_requisition_lines');
        Schema::dropIfExists('material_requisitions');
    }
};
