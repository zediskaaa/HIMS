<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status column defaulted to 'pending', which is not a PurchaseOrderStatus
 * case. No guard recognises it: receive() turns it away as unapproved and no
 * closed-status query counts it, so an insert that omitted a status used to
 * produce a purchase order that could never leave that state.
 *
 * The default becomes 'draft', where the enum's own lifecycle begins. Rows that
 * already hold 'pending' are normalised by the following migration
 * (2026_09_15_000002), which is a state-preserving rename rather than a schema
 * change: 'pending' meant "not yet started", which is what 'draft' says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
