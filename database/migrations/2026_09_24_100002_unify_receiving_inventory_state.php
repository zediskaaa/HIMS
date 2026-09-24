<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->foreignId('quarantine_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('receipt_key', 100)->nullable()->unique();
            // Legacy GRNs can contain duplicate references. New receipts use scoped keys.
            $table->string('packing_slip_key', 191)->nullable()->unique();
            $table->string('waybill_key', 191)->nullable()->unique();
        });

        Schema::table('grn_line_items', function (Blueprint $table) {
            $table->foreignId('staging_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->integer('pending_put_away_quantity')->default(0);
            $table->integer('returned_quantity')->default(0);
        });

        Schema::table('po_line_items', function (Blueprint $table) {
            $table->integer('accepted_quantity')->default(0);
            $table->integer('rejected_quantity')->default(0);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->string('purchase_unit', 50)->nullable();
            $table->integer('purchase_quantity')->nullable();
            $table->string('base_unit', 50)->nullable();
            $table->string('serial_number', 100)->nullable();
        });

        DB::table('purchase_orders')->whereNotNull('item_id')->where('quantity', '>', 0)
            ->orderBy('id')->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    if (DB::table('po_line_items')->where('purchase_order_id', $order->id)->exists()) {
                        continue;
                    }
                    $delivered = (int) DB::table('grn_line_items')
                        ->join('goods_receipt_notes', 'goods_receipt_notes.id', '=', 'grn_line_items.goods_receipt_note_id')
                        ->where('goods_receipt_notes.purchase_order_id', $order->id)
                        ->where('grn_line_items.item_id', $order->item_id)
                        ->sum('grn_line_items.received_quantity');
                    $completed = in_array($order->status, ['received', 'fulfilled'], true);
                    if ($order->status === 'partially_fulfilled' && $delivered === 0) {
                        continue; // Historical partial deliveries need manual reconciliation.
                    }
                    $quantity = (int) $order->quantity;
                    DB::table('po_line_items')->insert([
                        'purchase_order_id' => $order->id,
                        'item_id' => $order->item_id,
                        'line_number' => 1,
                        'ordered_quantity' => $quantity,
                        'received_quantity' => $completed ? $quantity : min($quantity, $delivered),
                        'accepted_quantity' => $completed ? $quantity : 0,
                        'rejected_quantity' => 0,
                        'unit_price' => (float) ($order->unit_cost ?? ($order->total_amount / $quantity)),
                        'total_line_amount' => (float) $order->total_amount,
                        'purchase_unit' => $order->purchase_unit,
                        'conversion_factor' => $order->conversion_factor ?: 1,
                        'line_status' => $completed ? 'accepted' : 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('po_line_items')->orderBy('id')->chunkById(200, function ($lines): void {
            foreach ($lines as $line) {
                $disposition = DB::table('grn_line_items')->where('po_line_id', $line->id)
                    ->selectRaw('coalesce(sum(accepted_quantity), 0) as accepted, coalesce(sum(rejected_quantity), 0) as rejected')
                    ->first();
                $legacyAccepted = (int) $disposition->accepted + (int) $disposition->rejected > 0 ? 0
                    : (DB::table('purchase_orders')->where('id', $line->purchase_order_id)
                        ->whereIn('status', ['received', 'fulfilled'])->exists() ? (int) $line->received_quantity : 0);
                DB::table('po_line_items')->where('id', $line->id)->update([
                    'accepted_quantity' => max((int) $disposition->accepted, $legacyAccepted),
                    'rejected_quantity' => (int) $disposition->rejected,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropConstrainedForeignId('goods_receipt_note_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'purchase_unit', 'purchase_quantity', 'base_unit', 'serial_number']);
        });
        Schema::table('po_line_items', fn (Blueprint $table) => $table->dropColumn(['accepted_quantity', 'rejected_quantity']));
        Schema::table('grn_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staging_location_id');
            $table->dropColumn(['pending_put_away_quantity', 'returned_quantity']);
        });
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropUnique(['packing_slip_key']);
            $table->dropUnique(['waybill_key']);
            $table->dropColumn(['packing_slip_key', 'waybill_key']);
            $table->dropUnique(['receipt_key']);
            $table->dropConstrainedForeignId('quarantine_location_id');
        });
    }
};
