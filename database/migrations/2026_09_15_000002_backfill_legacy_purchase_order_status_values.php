<?php

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise the two purchase order statuses that were written outside the
 * PurchaseOrderStatus vocabulary.
 *
 * 'pending' was the column default before the enum aligned with it, so it
 * survives in rows that were inserted without an explicit status. It maps to
 * the new default, 'draft', which is where the enum's lifecycle begins.
 *
 * 'partially_received' was written by the IA acceptance path while the goods
 * receipt path wrote 'partially_fulfilled' for the identical condition. It is
 * not an enum case, so tryFrom() returned null: the order read as Draft on
 * screen and, worse, dropped out of every enum-driven guard.
 *
 * Both are state-preserving renames, not policy changes: either value moves to
 * the enum case that already means the same thing. Nothing here decides whether
 * an order should have been approved or cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')
            ->where('status', 'pending')
            ->update(['status' => PurchaseOrderStatus::Draft->value]);

        DB::table('purchase_orders')
            ->where('status', 'partially_received')
            ->update(['status' => PurchaseOrderStatus::PartiallyFulfilled->value]);
    }

    /**
     * Deliberately not reversible.
     *
     * Rows that were normalised here are indistinguishable from rows the
     * application legitimately writes as 'draft' or 'partially_fulfilled', so a
     * rollback would rewrite real orders into two values no guard recognises.
     * The statuses are valid enum cases throughout, so leaving them is safe.
     */
    public function down(): void
    {
        //
    }
};
