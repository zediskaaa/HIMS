<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number')->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('carrier_name')->nullable();
            $table->string('waybill_number')->nullable();
            $table->string('packing_slip_number')->nullable();
            $table->foreignId('received_by_id')->constrained('users');
            $table->string('receipt_status', 30)->default('draft');
            $table->dateTime('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'receipt_status']);
            $table->index('received_at');
        });

        Schema::create('grn_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->foreignId('po_line_id')->nullable()->constrained('po_line_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->integer('ordered_quantity')->default(0);
            $table->integer('shipped_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->integer('accepted_quantity')->default(0);
            $table->integer('rejected_quantity')->default(0);
            $table->integer('quarantined_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->foreignId('destination_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('manufactured_date')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status', 30)->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });

        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_line_item_id')->nullable()->constrained('grn_line_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('inspected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspection_status', 30)->default('pending_sample');
            $table->integer('sample_quantity')->default(0);
            $table->integer('accepted_quantity')->default(0);
            $table->integer('rejected_quantity')->default(0);
            $table->dateTime('inspection_date');
            $table->text('findings')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'inspection_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('grn_line_items');
        Schema::dropIfExists('goods_receipt_notes');
    }
};
