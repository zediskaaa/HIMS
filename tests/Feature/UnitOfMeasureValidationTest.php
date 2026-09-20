<?php

namespace Tests\Feature;

use App\Enums\UnitOfMeasure;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ItemUnitConversion;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitOfMeasureValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private StorageLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->inventoryManager()->create();
        $this->location = StorageLocation::create([
            'name' => 'Main Warehouse Shelf A',
            'code' => 'MWS-A',
            'status' => 'active',
            'type' => 'shelf',
        ]);
    }

    public function test_enum_contains_all_standard_hospital_medical_units(): void
    {
        $expectedUnits = [
            'piece', 'box', 'pack', 'bottle', 'vial', 'ampoule', 'tube',
            'sachet', 'strip', 'tablet', 'capsule', 'canister', 'roll',
            'pair', 'set', 'kit', 'bag', 'pouch', 'jar', 'container',
            'cartridge', 'syringe', 'applicator', 'meter', 'centimeter',
            'liter', 'milliliter', 'gram', 'kilogram', 'unit',
        ];

        $actualValues = UnitOfMeasure::values();

        foreach ($expectedUnits as $expected) {
            $this->assertContains($expected, $actualValues, "UnitOfMeasure missing expected unit: {$expected}");
        }

        // Verify label formatting
        $this->assertSame('Piece (pc)', UnitOfMeasure::Piece->label());
        $this->assertSame('Box (box)', UnitOfMeasure::Box->label());
        $this->assertSame('Vial (vial)', UnitOfMeasure::Vial->label());
        $this->assertSame('Liter (L)', UnitOfMeasure::Liter->label());
        $this->assertSame('Milliliter (mL)', UnitOfMeasure::Milliliter->label());
        $this->assertSame('Gram (g)', UnitOfMeasure::Gram->label());
        $this->assertSame('Kilogram (kg)', UnitOfMeasure::Kilogram->label());
    }

    public function test_user_can_create_item_with_valid_predefined_unit(): void
    {
        $payload = [
            'name' => 'Paracetamol 500mg Tablet',
            'sku' => 'MED-PARA-500',
            'unit' => 'tablet',
            'unit_cost' => '1.50',
            'reorder_level' => 100,
        ];

        $response = $this->actingAs($this->manager)
            ->post(route('inventory.items.store'), $payload);

        $response->assertRedirect(route('inventory.items'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'MED-PARA-500',
            'unit' => 'tablet',
        ]);

        $item = InventoryItem::where('sku', 'MED-PARA-500')->first();
        $this->assertNotNull($item);
        $this->assertSame(UnitOfMeasure::Tablet, $item->unitOfMeasure());
        $this->assertSame('Tablet (tablet)', $item->unitLabel());
        $this->assertSame('tablet', $item->unitAbbreviation());
    }

    public function test_empty_unit_of_measure_is_rejected_on_item_creation(): void
    {
        $payload = [
            'name' => 'Test Item Missing Unit',
            'sku' => 'TEST-NO-UNIT',
            'unit' => '',
        ];

        $response = $this->actingAs($this->manager)
            ->post(route('inventory.items.store'), $payload);

        $response->assertSessionHasErrors(['unit']);
        $this->assertDatabaseMissing('inventory_items', [
            'sku' => 'TEST-NO-UNIT',
        ]);
    }

    public function test_arbitrary_free_text_unit_is_rejected_by_backend_validation(): void
    {
        $payload = [
            'name' => 'Test Item Arbitrary Unit',
            'sku' => 'TEST-ARB-UNIT',
            'unit' => 'arbitrary_nonexistent_unit_xyz',
        ];

        $response = $this->actingAs($this->manager)
            ->post(route('inventory.items.store'), $payload);

        $response->assertSessionHasErrors(['unit']);
        $this->assertDatabaseMissing('inventory_items', [
            'sku' => 'TEST-ARB-UNIT',
        ]);
    }

    public function test_existing_legacy_units_are_preserved_and_remain_valid(): void
    {
        // Seed an item with a legacy/custom unit
        $legacyItem = InventoryItem::create([
            'name' => 'Legacy Historical Device',
            'sku' => 'LEG-DEV-001',
            'unit' => 'custom_carton',
            'quantity_on_hand' => 10,
            'reorder_level' => 2,
            'unit_cost' => 50.00,
            'total_value' => 500.00,
        ]);

        // Verify options with legacy includes the legacy unit
        $options = UnitOfMeasure::optionsWithLegacy();
        $this->assertArrayHasKey('custom_carton', $options);
        $this->assertSame('Custom_carton (Legacy)', $options['custom_carton']);

        // Verify index page renders the legacy item with its unit without error
        $response = $this->actingAs($this->manager)->get(route('inventory.items'));
        $response->assertOk();
        $response->assertSee('Legacy Historical Device');
        $response->assertSee('custom_carton');

        // Verify that the legacy unit is permitted in validation so historical item workflows are not blocked
        $this->assertContains('custom_carton', UnitOfMeasure::allowedValuesWithLegacy());
    }

    public function test_pack_and_piece_relationship_calculations_are_accurate(): void
    {
        // Base unit is piece
        $gauze = InventoryItem::create([
            'name' => 'Sterile Gauze Sponge 4x4',
            'sku' => 'GAUZE-SGS-4X4',
            'unit' => 'piece',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'unit_cost' => 1.50,
            'total_value' => 150.00,
        ]);

        // Explicit unit conversion: 1 pack = 100 pieces
        ItemUnitConversion::create([
            'item_id' => $gauze->id,
            'purchase_unit' => 'pack',
            'conversion_factor' => 100,
            'is_default' => true,
        ]);

        // Pack and piece must not be interchangeable
        $packFactor = $gauze->conversionFactorFor('pack');
        $pieceFactor = $gauze->conversionFactorFor('piece');

        $this->assertSame(100.0, (float) $packFactor);
        $this->assertSame(1.0, (float) $pieceFactor);

        // 4 packs * 100 pieces/pack = 400 pieces
        $orderedPacks = 4;
        $receivedPieces = $orderedPacks * $packFactor;
        $this->assertSame(400.0, (float) $receivedPieces);

        // If item base unit is pack, pack should resolve to 1.0
        $packItem = InventoryItem::create([
            'name' => 'Exam Gloves (Pack of 50)',
            'sku' => 'GLOVE-PK-50',
            'unit' => 'pack',
            'quantity_on_hand' => 10,
            'reorder_level' => 5,
            'unit_cost' => 25.00,
            'total_value' => 250.00,
        ]);
        $this->assertSame(1.0, (float) $packItem->conversionFactorFor('pack'));
    }

    public function test_api_rejects_invalid_units_and_accepts_valid_units(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();
        Sanctum::actingAs($user, ['*']);

        // Invalid unit rejected
        $badPayload = [
            'sku' => 'API-BAD-UNIT',
            'name' => 'API Invalid Unit Item',
            'unit' => 'invalid_random_unit_123',
        ];
        $badResp = $this->postJson('/api/v1/inventory-items', $badPayload);
        $badResp->assertStatus(422)->assertJsonValidationErrors(['unit']);

        // Valid unit accepted
        $goodPayload = [
            'sku' => 'API-GOOD-UNIT',
            'name' => 'API Valid Unit Item',
            'unit' => 'vial',
            'unit_cost' => 12.50,
        ];
        $goodResp = $this->postJson('/api/v1/inventory-items', $goodPayload);
        $goodResp->assertStatus(201)->assertJsonFragment(['sku' => 'API-GOOD-UNIT']);

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'API-GOOD-UNIT',
            'unit' => 'vial',
        ]);
    }

    public function test_supplier_product_link_form_rejects_invalid_units(): void
    {
        $supplier = Supplier::create([
            'name' => 'Pharma Supplies Inc',
            'email' => 'contact@pharmasupplies.example.com',
            'status' => 'active',
            'accreditation_status' => 'approved',
        ]);

        $item = InventoryItem::create([
            'name' => 'Ceftriaxone 1g',
            'sku' => 'CEF-1G',
            'unit' => 'vial',
            'quantity_on_hand' => 50,
            'reorder_level' => 10,
            'unit_cost' => 45.00,
            'total_value' => 2250.00,
        ]);

        $response = $this->actingAs($this->manager)->post(route('inventory.suppliers.products.store', $supplier), [
            'item_id' => $item->id,
            'pack_size' => '10 vials/box',
            'unit' => 'invalid_supplier_unit_foo',
        ]);

        $response->assertSessionHasErrors(['unit']);
    }

    public function test_dpri_price_benchmark_form_rejects_invalid_units(): void
    {
        $response = $this->actingAs($this->manager)->post(route('reviews.dpri.store'), [
            'pndf_code' => 'PNDF-TEST-001',
            'drug_name' => 'Test DPRI Drug',
            'unit_of_measure' => 'invalid_dpri_unit_bar',
            'ceiling_price' => 10.50,
            'edition_year' => 2026,
        ]);

        $response->assertSessionHasErrors(['unit_of_measure']);
    }
}
