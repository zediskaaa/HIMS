<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemsCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The catalogue used to render server-side and then be replaced by a single
     * paginated API page, so the row count collapsed once loading finished.
     */
    public function test_catalog_renders_every_item_without_a_paginated_refetch(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // More rows than the API's default page size so a truncated refetch
        // would be visible.
        foreach (range(1, 20) as $index) {
            InventoryItem::create([
                'name' => "Catalog Item {$index}",
                'sku' => "CAT-{$index}",
                'quantity_on_hand' => 50,
                'reorder_level' => 10,
                'unit_cost' => 1,
                'total_value' => 50,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('inventory.items'));

        $response->assertOk();

        foreach (range(1, 20) as $index) {
            $response->assertSee("Catalog Item {$index}");
        }

        // The catalog must come from the one authorized response rather than a
        // foreground fetch that replaces it with a page of itself.
        $response->assertDontSee('/api/v1/inventory-items');
    }

    public function test_catalog_labels_low_stock_and_out_of_stock_separately(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // The stock state each row shows is derived from its quantity against
        // its reorder level, so these quantities are what put the three rows in
        // the three states.
        foreach ([
            ['name' => 'Healthy Item', 'sku' => 'CAT-HEALTHY', 'quantity_on_hand' => 500],
            ['name' => 'Reorder Soon Item', 'sku' => 'CAT-LOW', 'quantity_on_hand' => 10],
            ['name' => 'Depleted Item', 'sku' => 'CAT-OUT', 'quantity_on_hand' => 0],
        ] as $definition) {
            InventoryItem::create($definition + [
                'reorder_level' => 50,
                'unit_cost' => 1,
                'total_value' => 500,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('inventory.items'));

        $response->assertOk();

        // The stock filter's own option labels also read "Low Stock" and
        // "Out Of Stock", so scope the assertion to the rendered rows: the
        // catalogue has to distinguish the two states itself rather than
        // inherit the wording from a control above it. The boundary is
        // asserted first, because splitting on a marker that has gone missing
        // would silently widen the scope back to the whole page.
        $this->assertStringContainsString('id="inventory-items-table-body"', $response->getContent());

        $rows = str($response->getContent())
            ->after('id="inventory-items-table-body"')
            ->before('</tbody>')
            ->toString();

        // Falling to the reorder level and being fully depleted are different
        // operational states, and neither may be conveyed by colour alone.
        $this->assertStringContainsString('Low Stock', $rows);
        $this->assertStringContainsString('Out Of Stock', $rows);
    }

    public function test_catalog_shows_the_packaging_unit_with_the_quantity(): void
    {
        // The create form is hidden from a stock viewer, so a unit string can
        // only reach this response through the catalogue table itself.
        $viewer = User::factory()->pharmacyStaff()->create();

        InventoryItem::create([
            'name' => 'Isopropyl Alcohol 70% 500 mL',
            'sku' => 'CAT-ALCOHOL',
            'unit' => 'bottle',
            'quantity_on_hand' => 8,
            'reorder_level' => 42,
            'unit_cost' => 74,
            'total_value' => 592,
        ]);

        $response = $this->actingAs($viewer)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertSee('bottle');
    }

    public function test_catalog_shows_price_per_piece_only_to_financially_authorized_users(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $pharmacyStaff = User::factory()->pharmacyStaff()->create();

        InventoryItem::create([
            'name' => 'Sterile Examination Gloves',
            'sku' => 'CAT-PRICE-01',
            'unit' => 'piece',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 12.50,
            'total_value' => 1250,
        ]);

        $this->actingAs($manager)
            ->get(route('inventory.items'))
            ->assertOk()
            ->assertSee('Price / Piece')
            ->assertSee('&#8369;12.50', false);

        $this->actingAs($pharmacyStaff)
            ->get(route('inventory.items'))
            ->assertOk()
            ->assertDontSee('Price / Piece')
            ->assertDontSee('&#8369;12.50', false);
    }

    /**
     * The catalog is the lookup surface for every department, so a term that
     * matches nothing has to say so rather than look like an empty inventory.
     */
    public function test_catalog_search_narrows_on_name_sku_and_barcode(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Losartan 50 mg',
            'sku' => 'CAT-LOSA',
            'barcode_value' => '4801234567890',
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 40,
        ]);
        InventoryItem::create([
            'name' => 'Cetirizine 10 mg',
            'sku' => 'CAT-CETI',
            'barcode_value' => '4809876543210',
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 40,
        ]);

        // Each case asserts on the identity the search box does not echo back:
        // the term itself is rendered into the input's value, so a looser
        // assertion would pass even if no row matched.
        $cases = [
            ['Losartan', 'CAT-LOSA', 'CAT-CETI'],
            ['CAT-CETI', 'Cetirizine 10 mg', 'Losartan 50 mg'],
            ['4801234567890', 'CAT-LOSA', 'CAT-CETI'],
        ];

        foreach ($cases as [$term, $expected, $absent]) {
            $response = $this->actingAs($manager)->get(route('inventory.items', ['search' => $term]));

            $response->assertOk();
            $response->assertSee($expected);
            $response->assertDontSee($absent);
        }

        $this->actingAs($manager)
            ->get(route('inventory.items', ['search' => 'Nothing Matches This']))
            ->assertOk()
            ->assertSee('No items match these filters.')
            ->assertDontSee('No inventory items yet.');
    }

    public function test_catalog_filters_rows_by_stock_state(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // The stock state each row shows is derived from its quantity against
        // its reorder level, so these quantities are what put the three rows in
        // the three states.
        foreach ([
            ['name' => 'Healthy Item', 'sku' => 'CAT-HEALTHY', 'quantity_on_hand' => 500],
            ['name' => 'Reorder Soon Item', 'sku' => 'CAT-LOW', 'quantity_on_hand' => 10],
            ['name' => 'Depleted Item', 'sku' => 'CAT-OUT', 'quantity_on_hand' => 0],
        ] as $definition) {
            InventoryItem::create($definition + [
                'reorder_level' => 50,
                'unit_cost' => 1,
                'total_value' => 500,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('inventory.items', ['status' => 'low_stock']));

        $response->assertOk();
        $response->assertSee('CAT-LOW');
        $response->assertDontSee('CAT-HEALTHY');
        $response->assertDontSee('CAT-OUT');
    }

    /**
     * Category options used to be built only for a viewer who may create
     * items, which left everyone else with a filter that had nothing in it.
     */
    public function test_catalog_offers_category_filtering_to_a_viewer_who_cannot_manage_items(): void
    {
        $viewer = User::factory()->pharmacyStaff()->create();

        $medicines = ItemCategory::create(['name' => 'Medicines', 'code' => 'MED']);
        $consumables = ItemCategory::create(['name' => 'Consumables', 'code' => 'CONS']);

        InventoryItem::create([
            'name' => 'Paracetamol 500 mg',
            'sku' => 'CAT-PARA',
            'category_id' => $medicines->id,
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 40,
        ]);
        InventoryItem::create([
            'name' => 'Cotton Balls',
            'sku' => 'CAT-COTTON',
            'category_id' => $consumables->id,
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 40,
        ]);

        $response = $this->actingAs($viewer)->get(route('inventory.items', ['category_id' => $medicines->id]));

        $response->assertOk();
        $response->assertSee('Consumables');
        $response->assertSee('CAT-PARA');
        $response->assertDontSee('CAT-COTTON');
    }

    public function test_catalog_paginates_records_at_twenty_items_per_page(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // Create 35 items so there are 2 full pages (20 + 15)
        for ($i = 1; $i <= 35; $i++) {
            InventoryItem::create([
                'name' => sprintf('Batch Item %02d', $i),
                'sku' => sprintf('BATCH-%02d', $i),
                'quantity_on_hand' => 50,
                'reorder_level' => 10,
                'unit_cost' => 10,
                'total_value' => 500,
            ]);
        }

        // Page 1
        $page1Response = $this->actingAs($manager)->get(route('inventory.items'));
        $page1Response->assertOk();
        $page1Response->assertSee('Showing');
        $page1Response->assertSee('1');
        $page1Response->assertSee('20');
        $page1Response->assertSee('35');
        $page1Response->assertSee('items');

        // Check latest 20 items are on page 1 (Batch Item 35 down to 16)
        $page1Response->assertSee('Batch Item 35');
        $page1Response->assertSee('Batch Item 16');
        $page1Response->assertDontSee('Batch Item 15');
        $page1Response->assertDontSee('Batch Item 01');

        // Page 2
        $page2Response = $this->actingAs($manager)->get(route('inventory.items', ['page' => 2]));
        $page2Response->assertOk();
        $page2Response->assertSee('Showing');
        $page2Response->assertSee('21');
        $page2Response->assertSee('35');
        $page2Response->assertSee('Batch Item 15');
        $page2Response->assertSee('Batch Item 01');
        $page2Response->assertDontSee('Batch Item 35');
    }

    public function test_catalog_pagination_preserves_query_string_filters(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // Create 25 matching items and 5 non-matching items
        for ($i = 1; $i <= 25; $i++) {
            InventoryItem::create([
                'name' => sprintf('Antibiotic Injection %02d', $i),
                'sku' => sprintf('ANTI-%02d', $i),
                'quantity_on_hand' => 100,
                'reorder_level' => 20,
                'unit_cost' => 15,
                'total_value' => 1500,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            InventoryItem::create([
                'name' => sprintf('Bandage Roll %02d', $i),
                'sku' => sprintf('BAND-%02d', $i),
                'quantity_on_hand' => 100,
                'reorder_level' => 20,
                'unit_cost' => 5,
                'total_value' => 500,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('inventory.items', ['search' => 'Antibiotic']));
        $response->assertOk();

        // Should show 25 items matching filter
        $response->assertSee('25 matching items');
        $response->assertSee('Showing');
        $response->assertSee('1');
        $response->assertSee('20');
        $response->assertSee('25');

        // Next page link should contain search query string
        $content = $response->getContent();
        $this->assertStringContainsString('search=Antibiotic', $content);
        $this->assertStringContainsString('page=2', $content);

        // Accessing page 2 with search
        $page2 = $this->actingAs($manager)->get(route('inventory.items', ['search' => 'Antibiotic', 'page' => 2]));
        $page2->assertOk();
        $page2->assertSee('Showing');
        $page2->assertSee('21');
        $page2->assertSee('25');
        $page2->assertDontSee('Bandage Roll');
    }

    public function test_catalog_renders_full_width_layout_and_balanced_columns(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Meropenem Trihydrate 1g Powder for Injection',
            'sku' => 'ANT-MER-1G00',
            'unit' => 'vial',
            'quantity_on_hand' => 800,
            'reorder_level' => 200,
            'unit_cost' => 450,
            'total_value' => 360000,
        ]);

        $response = $this->actingAs($manager)->get(route('inventory.items'));
        $response->assertOk();

        $content = $response->getContent();

        // 1. Verify full-width layout container is applied (max-w-none)
        $this->assertStringContainsString('max-w-none', $content);

        // 2. Verify balanced table columns with appropriate min-width and wrapping control
        $this->assertStringContainsString('min-w-[280px]', $content);
        $this->assertStringContainsString('font-mono', $content);
        $this->assertStringContainsString('whitespace-nowrap', $content);

        // 3. Verify pagination controls are omitted when items fit on one page
        $this->assertStringNotContainsString('Pagination Navigation', $content);

        // 4. Verify dark mode styling classes
        $this->assertStringContainsString('dark:bg-neutral-900', $content);
        $this->assertStringContainsString('dark:border-neutral-800', $content);
    }
}
