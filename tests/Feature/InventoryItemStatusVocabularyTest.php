<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `inventory_items.status` means the item's lifecycle — `active` / `inactive`.
 *
 * It used to receive the item's stock condition (in_stock / low_stock /
 * out_of_stock) on every movement as well, so the same column carried two
 * vocabularies. These two checks cover the halves of settling it: the rows
 * already holding a stock condition, and the readers that must now offer only
 * the items still in use. The stock condition itself is derived from the
 * quantities and is covered by the catalogue and report suites.
 */
class InventoryItemStatusVocabularyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_backfill_normalises_a_stock_condition_written_into_the_lifecycle_column(): void
    {
        $legacy = InventoryItem::create([
            'name' => 'Sodium Chloride 0.9% 1 L',
            'sku' => 'STAT-LEGACY',
            'quantity_on_hand' => 5,
            'reorder_level' => 50,
            'unit_cost' => 1,
            'total_value' => 5,
        ]);

        $withdrawn = InventoryItem::create([
            'name' => 'Amoxicillin 500 mg Capsule',
            'sku' => 'STAT-WITHDRAWN',
            'status' => 'inactive',
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 0,
        ]);

        // Written behind the model, because the writer that produced this
        // spelling was the stock automation path and nothing else writes it.
        DB::table('inventory_items')->where('id', $legacy->id)->update(['status' => 'low_stock']);

        $migration = require database_path('migrations/2026_09_16_000001_normalise_inventory_item_status_vocabulary.php');
        $migration->up();

        // The lifecycle the movement path overwrote is not recoverable, so the
        // row lands on the value every reader already treated it as: the pickers
        // tested for `discontinued`, and the forecast tested for `inactive`.
        $this->assertSame('active', $legacy->fresh()->status);

        // A row deliberately withdrawn is withdrawn, and stays that way.
        $this->assertSame('inactive', $withdrawn->fresh()->status);

        // The migration stores no stock condition. Both rows still report the
        // state their quantities put them in, which is the point of removing
        // the second vocabulary rather than translating it.
        $this->assertSame('low_stock', $legacy->fresh()->stockStatus());
        $this->assertSame('out_of_stock', $withdrawn->fresh()->stockStatus());
    }

    /**
     * A withdrawn item used to be offered by the pickers. They excluded
     * `discontinued`, a value nothing ever wrote, so `inactive` passed straight
     * through — an item taken out of use could still be selected for a stock
     * adjustment or a warehouse task.
     */
    public function test_an_item_withdrawn_from_use_is_not_offered_in_operational_pickers(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $offered = InventoryItem::create([
            'name' => 'Mannitol 20% 250 mL',
            'sku' => 'STAT-IN-USE',
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 40,
        ]);

        $withdrawn = InventoryItem::create([
            'name' => 'Metoclopramide 10 mg Ampoule',
            'sku' => 'STAT-WITHDRAWN-PICKER',
            'status' => 'inactive',
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 40,
        ]);

        foreach ([route('inventory.adjustments'), route('inventory.warehouse-tasks.index')] as $picker) {
            $response = $this->actingAs($manager)->get($picker);

            // Both quantity and reorder level match the offered row, so nothing
            // but the lifecycle can account for the difference between them.
            $response->assertOk();
            $response->assertSee($offered->sku);
            $response->assertDontSee($withdrawn->sku);
        }

        // The excluded item is still a row the picker could have returned, so
        // the exclusion is the lifecycle filter and not a missing record.
        $this->assertDatabaseHas('inventory_items', [
            'id' => $withdrawn->id,
            'status' => 'inactive',
        ]);

        // And it is still on the master list, which is where a withdrawn item
        // remains visible instead of disappearing from the system.
        $this->actingAs($manager)
            ->get(route('inventory.items'))
            ->assertOk()
            ->assertSee($offered->sku)
            ->assertSee($withdrawn->sku);
    }
}
