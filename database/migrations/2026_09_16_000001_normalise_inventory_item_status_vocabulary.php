<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise `inventory_items.status` onto its lifecycle vocabulary.
 *
 * That column is the item's lifecycle: `active` is the column default and the
 * only value the create form, the update request, the importer and the seeders
 * write, and `inactive` is the one other value the API accepts.
 *
 * Until this change, InventoryAutomationService stamped the item's *stock
 * condition* — in_stock / low_stock / out_of_stock — over the same column on
 * every movement. The original lifecycle of a row that path overwrote cannot be
 * recovered: the write was not audited, so a clobbered row is indistinguishable
 * from one that was never touched. Those rows are normalised to `active`
 * because that is how every reader already treated them. The guards that
 * exclude an item from a picker tested for `discontinued`, a value nothing ever
 * wrote, so a stock-condition value behaved as "usable" everywhere — including
 * in the demand forecast and the supplier item picker, which test against
 * `inactive`. Normalising to `active` therefore preserves observable behaviour
 * for every existing row and only changes rows legitimately marked `inactive`,
 * which stay excluded.
 *
 * This is a vocabulary normalisation, not a policy change, and it stores no
 * stock condition: that is derived from the quantities through
 * InventoryItem::stockStatusFor().
 *
 * One statement, so the change is all-or-nothing rather than partially applied,
 * and safe to re-run: a row written concurrently by the application is already
 * `active` or `inactive`, so the predicate no longer matches it. The write set
 * is bounded by the catalogue size and the column keeps its type and default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_items')
            ->whereNotIn('status', ['active', 'inactive'])
            ->update(['status' => 'active']);
    }

    /**
     * Deliberately not reversible.
     *
     * The previous values are not recoverable and restoring them would write
     * stale stock conditions back into a column that means lifecycle, which is
     * the inconsistency this migration removes. Every normalised row is valid
     * for every reader, so leaving them is safe.
     */
    public function down(): void
    {
        //
    }
};
