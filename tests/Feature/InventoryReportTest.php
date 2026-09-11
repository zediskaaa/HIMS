<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /inventory/reports used to be a placeholder that named four sections and
 * rendered none of them — the controller passed no data at all, so the screen
 * returned 200 with nothing behind it.
 *
 * These tests pin the arithmetic rather than the markup, because the figures
 * are what a report is for: a wrong total that renders beautifully is still
 * wrong, and the panel will check them against the operational screens.
 */
class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): User
    {
        // view_reports, held by everyone from the viewer up.
        return User::factory()->inventoryManager()->create();
    }

    private function reports(): InventoryReportService
    {
        return app(InventoryReportService::class);
    }

    private function location(string $name = 'Main Store', string $code = 'MAIN-01', ?int $capacity = null): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'capacity' => $capacity,
            'status' => 'active',
        ]);
    }

    /**
     * An item plus the level row it is derived from, so the report and the
     * stock screens are reading the same numbers.
     */
    private function stockedItem(
        string $name,
        string $sku,
        int $quantity,
        float $unitCost,
        int $reorderLevel = 0,
        ?StorageLocation $location = null,
        ?ItemCategory $category = null,
    ): InventoryItem {
        $item = InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $category?->id,
            'quantity_on_hand' => $quantity,
            'reorder_level' => $reorderLevel,
            'unit_cost' => $unitCost,
            'status' => 'in_stock',
        ]);

        if ($location) {
            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $location->id,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ]);
        }

        return $item;
    }

    // ---------------------------------------------------------- the screen

    public function test_the_screen_renders_all_four_reported_sections(): void
    {
        $this->actingAs($this->reader())
            ->get('/inventory/reports')
            ->assertStatus(200)
            ->assertSee('Reports &amp; Analytics', false)
            // The placeholder promised these four; each is now a real section.
            ->assertSee('Stock valuation')
            ->assertSee('Stock Status')
            ->assertSee('Procurement Expense')
            ->assertSee('Movement History')
            // And the sentence that used to be the whole page is gone.
            ->assertDontSee('This area will contain inventory summaries');
    }

    public function test_the_screen_renders_compact_tabbed_sections_and_preserves_print_compatibility(): void
    {
        $financialReader = User::factory()->inventoryManager()->create();
        $nonFinancialReader = User::factory()->viewer()->create();

        $response = $this->actingAs($financialReader)->get('/inventory/reports');

        $response->assertStatus(200)
            ->assertSee('Valuation &amp; Locations', false)
            ->assertSee('Procurement &amp; Spending', false)
            ->assertSee('Movements &amp; Consumption', false)
            ->assertSee('Expiry Risk Batches', false)
            ->assertSee('print:!block', false);

        // A viewer without ViewProcurementSensitiveData does not see the procurement spending tab
        $this->actingAs($nonFinancialReader)->get('/inventory/reports')
            ->assertStatus(200)
            ->assertDontSee('Procurement &amp; Spending', false);
    }


    /**
     * The gate moved from InventoryController to ReportController when the
     * screen was split out, so assert it followed rather than assuming it did.
     * Every role holds view_reports on purpose — see UserRole::permissions(),
     * "a storeroom nobody can look into is useless" — so the meaningful
     * assertion is that each one reaches the screen.
     */
    public function test_every_signed_in_role_may_read_the_report(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertTrue($role->grants(Permission::ViewReports));

            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/inventory/reports')
                ->assertStatus(200);
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/inventory/reports')->assertRedirect('/login');
    }

    public function test_the_period_selector_drives_the_window(): void
    {
        $this->actingAs($this->reader())
            ->get('/inventory/reports?days=90')
            ->assertStatus(200)
            ->assertSee('Last 90 days')
            ->assertSee('90-day window');
    }

    /**
     * The value arrives from a <select>, so a hand-edited query string should
     * clamp back into range rather than 500 somebody reading a report.
     */
    public function test_a_nonsense_period_is_clamped_not_rejected(): void
    {
        foreach (['days=0', 'days=99999', 'days=-40', 'days=banana'] as $query) {
            $this->actingAs($this->reader())
                ->get('/inventory/reports?'.$query)
                ->assertStatus(200);
        }
    }

    // ------------------------------------------------- inventory summaries

    public function test_the_summary_totals_units_and_value_across_the_catalogue(): void
    {
        $location = $this->location();
        $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 200, 45.00, location: $location);
        $this->stockedItem('Surgical Gloves', 'PPE-GLV', 500, 12.50, location: $location);

        $summary = $this->reports()->summary();

        $this->assertSame(2, $summary['items']);
        $this->assertSame(700, $summary['units_on_hand']);
        // 200 × 45.00 = 9,000 plus 500 × 12.50 = 6,250.
        $this->assertSame(15250.0, $summary['stock_value']);
    }

    /**
     * The buckets are worked out from the quantities, not read off
     * `inventory_items.status`. That column is a cache written when stock moves
     * through the service, so an item created by hand and never moved keeps
     * whatever status it was created with — and the report would then disagree
     * with the item screen sitting next to it.
     */
    public function test_stock_status_is_derived_from_quantities_not_the_cached_column(): void
    {
        // Created as `in_stock` but actually at zero, and as `in_stock` but
        // sitting on its reorder level.
        $this->stockedItem('Out of stock item', 'SKU-OUT', 0, 10.00, reorderLevel: 50);
        $this->stockedItem('Low stock item', 'SKU-LOW', 40, 10.00, reorderLevel: 50);
        $this->stockedItem('Healthy item', 'SKU-OK', 900, 10.00, reorderLevel: 50);

        $status = $this->reports()->stockStatus();

        $this->assertSame(1, $status['out_of_stock']['items']);
        $this->assertSame(1, $status['low_stock']['items']);
        $this->assertSame(1, $status['in_stock']['items']);

        $this->assertSame(40, $status['low_stock']['units']);
        $this->assertSame(400.0, $status['low_stock']['value']);

        // And the headline count is the two that need doing something about.
        $this->assertSame(2, $this->reports()->summary($status)['needs_attention']);
    }

    public function test_valuation_is_broken_down_by_category_with_uncategorised_kept_visible(): void
    {
        $ppe = ItemCategory::create(['name' => 'Personal Protective Equipment', 'code' => 'PPE']);

        $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 200, 45.00, category: $ppe);
        $this->stockedItem('Surgical Gloves', 'PPE-GLV', 100, 12.50, category: $ppe);
        $this->stockedItem('Unfiled Item', 'SKU-NONE', 10, 100.00);

        $rows = $this->reports()->valuationByCategory();

        $this->assertCount(2, $rows);

        // Ordered by value, so PPE (9,000 + 1,250) leads the uncategorised 1,000.
        $this->assertSame('Personal Protective Equipment', $rows[0]->category);
        $this->assertSame(2, (int) $rows[0]->items);
        $this->assertSame(10250.0, (float) $rows[0]->value);

        // An item with no category is still stock the hospital owns — it gets
        // a labelled row rather than being dropped by the join.
        $this->assertSame('Uncategorised', $rows[1]->category);
        $this->assertSame(1000.0, (float) $rows[1]->value);
    }

    public function test_stock_by_location_reports_units_value_and_utilisation(): void
    {
        $main = $this->location('Main Store', 'MAIN-01', capacity: 1000);
        $pharmacy = $this->location('Central Pharmacy', 'PHARM-01');

        $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 800, 45.00, location: $main);
        $this->stockedItem('Paracetamol 500mg', 'PHARMA-PARA', 300, 1.10, location: $pharmacy);

        $rows = $this->reports()->stockByLocation()->keyBy('code');

        $this->assertSame(800, $rows['MAIN-01']['units']);
        $this->assertSame(36000.0, $rows['MAIN-01']['value']);
        $this->assertSame(80.0, $rows['MAIN-01']['utilisation']);

        // No capacity configured is unknown, not an empty shelf — reporting 0%
        // would read as "nothing stored here" when 300 units are.
        $this->assertSame(300, $rows['PHARM-01']['units']);
        $this->assertNull($rows['PHARM-01']['utilisation']);
    }

    // ---------------------------------------------------------- expiry risk

    public function test_expiry_exposure_separates_expired_from_expiring_and_ignores_empty_batches(): void
    {
        $location = $this->location();
        $item = $this->stockedItem('Paracetamol 500mg', 'PHARMA-PARA', 0, 1.10);

        $batches = [
            ['EXPIRED-01', now()->subWeek(), 100],
            ['SOON-01', now()->addDays(10), 250],
            // Already used up: a closed record, not money at risk.
            ['EXPIRED-EMPTY', now()->subMonth(), 0],
            // Far enough out to be nobody's problem yet.
            ['FUTURE-01', now()->addYear(), 500],
        ];

        foreach ($batches as [$number, $expiry, $quantity]) {
            $batch = ItemBatch::create([
                'item_id' => $item->id,
                'batch_number' => $number,
                'expiry_date' => $expiry->toDateString(),
                'received_at' => now()->toDateString(),
                'unit_cost' => 2.00,
                'status' => 'active',
            ]);

            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $location->id,
                'item_batch_id' => $batch->id,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ]);
        }

        $expiry = $this->reports()->expiryExposure();

        $this->assertSame(1, $expiry['expired']['batches']);
        $this->assertSame(100, $expiry['expired']['units']);
        $this->assertSame(200.0, $expiry['expired']['value']);

        $this->assertSame(1, $expiry['expiring_soon']['batches']);
        $this->assertSame(250, $expiry['expiring_soon']['units']);
        $this->assertSame(500.0, $expiry['expiring_soon']['value']);

        $listed = $expiry['rows']->pluck('batch_number')->all();
        $this->assertContains('EXPIRED-01', $listed);
        $this->assertContains('SOON-01', $listed);
        $this->assertNotContains('EXPIRED-EMPTY', $listed);
        $this->assertNotContains('FUTURE-01', $listed);
    }

    // ------------------------------------------------------ expense reports

    public function test_procurement_spend_separates_ordered_received_and_outstanding(): void
    {
        $supplier = Supplier::create(['name' => 'Jeffrey Corporation', 'status' => 'active']);
        $item = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 0, 45.00);

        $order = fn (string $number, string $status, float $total, $requestedAt, $receivedAt = null) => PurchaseOrder::create([
            'po_number' => $number,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 100,
            'unit_cost' => $total / 100,
            'total_amount' => $total,
            'status' => $status,
            'requested_at' => $requestedAt,
            'received_at' => $receivedAt,
        ]);

        $order('PO-A', 'received', 20000, now()->subDays(3), now()->subDay());
        $order('PO-B', 'pending', 30000, now()->subDays(5));
        // Raised well outside a 30-day window, still not delivered.
        $order('PO-C', 'pending', 50000, now()->subDays(200));

        $spend = $this->reports()->procurementSpend(now()->subDays(30));

        // Ordered counts only what was raised inside the window.
        $this->assertSame(2, $spend['ordered']['orders']);
        $this->assertSame(50000.0, $spend['ordered']['value']);
        $this->assertSame(25000.0, $spend['average_order_value']);

        $this->assertSame(1, $spend['received']['orders']);
        $this->assertSame(20000.0, $spend['received']['value']);

        // Outstanding is deliberately not windowed: the 200-day-old order is
        // exactly the one a report is supposed to surface.
        $this->assertSame(2, $spend['outstanding']['orders']);
        $this->assertSame(80000.0, $spend['outstanding']['value']);
    }

    public function test_spend_is_broken_down_by_supplier_with_a_fulfilment_rate(): void
    {
        $item = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 0, 45.00);
        $jeffrey = Supplier::create(['name' => 'Jeffrey Corporation', 'status' => 'active']);
        $metro = Supplier::create(['name' => 'Metro Med Supply', 'status' => 'active']);

        foreach ([
            ['PO-1', $jeffrey, 'received', 40000],
            ['PO-2', $jeffrey, 'pending', 20000],
            ['PO-3', $metro, 'received', 15000],
        ] as [$number, $supplier, $status, $total]) {
            PurchaseOrder::create([
                'po_number' => $number,
                'supplier_id' => $supplier->id,
                'item_id' => $item->id,
                'quantity' => 100,
                'unit_cost' => $total / 100,
                'total_amount' => $total,
                'status' => $status,
                'requested_at' => now()->subDay(),
                'received_at' => $status === 'received' ? now() : null,
            ]);
        }

        $rows = $this->reports()->spendBySupplier(now()->subDays(30))->keyBy('supplier');

        $this->assertSame(60000.0, (float) $rows['Jeffrey Corporation']->value);
        $this->assertSame(2, (int) $rows['Jeffrey Corporation']->orders);
        $this->assertSame(1, (int) $rows['Jeffrey Corporation']->received_orders);

        $this->assertSame(1, (int) $rows['Metro Med Supply']->received_orders);

        // Biggest spend first — that is the row procurement acts on.
        $this->assertSame('Jeffrey Corporation', $this->reports()->spendBySupplier(now()->subDays(30))->first()->supplier);
    }

    // ----------------------------------------------------- movement history

    public function test_movement_activity_lists_every_type_including_the_ones_at_zero(): void
    {
        $item = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 100, 45.00);

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 100,
            'unit_cost' => 45.00,
            'moved_at' => now()->subDay(),
        ]);

        $rows = $this->reports()->movementsByType(now()->subDays(30));

        // A type that disappears reads as "not tracked"; a zero reads as
        // "nothing happened", which is the true statement.
        $this->assertCount(count(MovementType::cases()), $rows);

        $byType = $rows->keyBy(fn (array $row) => $row['type']->value);
        $this->assertSame(1, $byType['stock_in']['movements']);
        $this->assertSame(100, $byType['stock_in']['units']);
        $this->assertSame(4500.0, $byType['stock_in']['value']);
        $this->assertSame(0, $byType['disposal']['movements']);
    }

    /**
     * The distinction the screen has to get right: a transfer relocates stock
     * without the hospital using any, so counting it as consumption would
     * report stock leaving the building when none did.
     */
    public function test_transfers_count_as_neither_received_nor_consumed(): void
    {
        $item = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 100, 45.00);

        foreach ([
            [MovementType::StockIn, 500],
            [MovementType::Transfer, 200],
            [MovementType::StockOut, 60],
            [MovementType::Issuance, 40],
            [MovementType::Disposal, 15],
        ] as [$type, $quantity]) {
            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => $type,
                'quantity' => $quantity,
                'unit_cost' => 45.00,
                'moved_at' => now()->subHours(2),
            ]);
        }

        $totals = $this->reports()->movementTotals($this->reports()->movementsByType(now()->subDays(30)));

        $this->assertSame(5, $totals['movements']);
        $this->assertSame(500, $totals['units_in']);
        $this->assertSame(100, $totals['units_out']);   // 60 out + 40 issued
        $this->assertSame(4500.0, $totals['consumption_value']);
        $this->assertSame(1, $totals['transfers']);
        $this->assertSame(15, $totals['disposals']);
    }

    public function test_the_window_excludes_movements_older_than_the_period(): void
    {
        $item = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 100, 45.00);

        foreach ([now()->subDays(2), now()->subDays(45)] as $movedAt) {
            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => MovementType::StockOut,
                'quantity' => 10,
                'unit_cost' => 45.00,
                'moved_at' => $movedAt,
            ]);
        }

        $this->assertSame(1, $this->reports()->movementTotals(
            $this->reports()->movementsByType(now()->subDays(30))
        )['movements']);

        $this->assertSame(2, $this->reports()->movementTotals(
            $this->reports()->movementsByType(now()->subDays(90))
        )['movements']);

        $this->assertCount(1, $this->reports()->recentMovements(now()->subDays(30)));
    }

    public function test_most_consumed_ranks_by_units_and_ignores_non_consumption(): void
    {
        $gloves = $this->stockedItem('Surgical Gloves', 'PPE-GLV', 1000, 12.50);
        $masks = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 400, 45.00);

        foreach ([
            [$gloves, MovementType::Issuance, 300],
            [$gloves, MovementType::StockOut, 100],
            [$masks, MovementType::Issuance, 150],
            // Neither of these is demand, so neither should reach the table.
            [$masks, MovementType::Transfer, 900],
            [$masks, MovementType::Disposal, 800],
        ] as [$item, $type, $quantity]) {
            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $item->unit_cost,
                'moved_at' => now()->subDay(),
            ]);
        }

        $rows = $this->reports()->topConsumedItems(now()->subDays(30));

        $this->assertCount(2, $rows);
        $this->assertSame('Surgical Gloves', $rows[0]->item);
        $this->assertSame(400, (int) $rows[0]->units);
        $this->assertSame(2, (int) $rows[0]->movements);
        $this->assertSame(5000.0, (float) $rows[0]->value);

        // Masks: the 150 issued, not the 1,700 transferred and disposed of.
        $this->assertSame(150, (int) $rows[1]->units);
    }

    /**
     * The end-to-end statement: receive a purchase order, and the report
     * reflects the delivery in the same numbers the stock screens show.
     */
    public function test_receiving_a_purchase_order_moves_every_figure_on_the_report(): void
    {
        $location = $this->location();
        $item = $this->stockedItem('N95 Respirator Mask', 'PPE-N95', 0, 45.00, location: $location);
        $supplier = Supplier::create(['name' => 'Jeffrey Corporation', 'status' => 'active']);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-20260808120000',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 500,
            'unit_cost' => 41.50,
            'total_amount' => 20750,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->reader())
            ->post("/inventory/purchases/{$purchaseOrder->id}/receive")
            ->assertRedirect('/inventory/purchases');

        $report = $this->reports()->build(30);

        $this->assertSame(500, $report['summary']['units_on_hand']);
        $this->assertSame(500, $report['stockStatus']['in_stock']['units']);
        $this->assertSame(500, $report['movementTotals']['units_in']);
        $this->assertSame(20750.0, $report['spend']['received']['value']);
        // Delivered, so nothing is left owing on it.
        $this->assertSame(0, $report['spend']['outstanding']['orders']);
        $this->assertSame(500, $report['stockByLocation']->first()['units']);
    }
}
