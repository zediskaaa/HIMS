<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualItemActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createInventoryManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function createItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name' => 'Latex Gloves XL',
            'sku' => 'PPE-GLV-XL',
            'description' => 'Powder-free surgical gloves',
            'unit' => 'box',
            'unit_cost' => 250.00,
            'quantity_on_hand' => 15,
            'reorder_level' => 50,
            'reorder_point' => 50,
            'safety_stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    private function createLocation(string $code = 'LOC-MAIN-01', string $name = 'Central Storage'): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    public function test_material_requisitions_index_with_item_id_preselects_item(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem();

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $response->assertSee('Contextual Item:');
        $response->assertSee('Latex Gloves XL');
        $response->assertSee('PPE-GLV-XL');
    }

    public function test_material_requisitions_index_with_invalid_item_id_handles_gracefully(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => 999999]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertSessionHas('warning', 'The requested inventory item could not be preselected because it does not exist or is inactive.');
    }

    public function test_stock_adjustments_index_with_item_id_preselects_item_and_type(): void
    {
        $user = $this->createInventoryManager();
        $location = $this->createLocation();
        $item = $this->createItem(['default_location_id' => $location->id]);

        $response = $this->actingAs($user)->get(route('inventory.adjustments', [
            'item_id' => $item->id,
            'adjustment_type' => 'correction',
        ]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $response->assertViewHas('preselectedType', 'correction');
        $response->assertSee('Contextual Item:');
        $response->assertSee('Latex Gloves XL');
    }

    public function test_stock_adjustments_index_with_invalid_item_id_flashes_warning(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.adjustments', ['item_id' => 999999]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertSessionHas('warning', 'The requested inventory item could not be preselected because it does not exist or is inactive.');
    }

    public function test_stock_transfers_index_with_item_id_preselects_item_and_stock_location(): void
    {
        $user = $this->createInventoryManager();
        $loc1 = $this->createLocation('LOC-A', 'Storeroom A');
        $loc2 = $this->createLocation('LOC-B', 'Storeroom B');

        $item = $this->createItem(['quantity_on_hand' => 40, 'default_location_id' => $loc1->id]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $loc1->id,
            'quantity' => 40,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.transfers.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $response->assertViewHas('preselectedSourceLocationId', (string) $loc1->id);
        $response->assertSee('Contextual Stock Transfer:');
        $response->assertSee('Latex Gloves XL');
    }

    public function test_stock_transfers_index_without_item_id_defaults_cleanly(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.transfers.index'));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertViewHas('preselectedSourceLocationId', null);
    }

    public function test_stock_transfers_index_with_invalid_item_id_handles_gracefully(): void
    {
        $user = $this->createInventoryManager();

        $response = $this->actingAs($user)->get(route('inventory.transfers.index', ['item_id' => 999999]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', null);
        $response->assertSessionHas('warning', 'The requested inventory item could not be preselected because it does not exist or is inactive.');
    }

    public function test_catalogue_search_filters_by_item_sku(): void
    {
        $user = $this->createInventoryManager();
        $itemA = $this->createItem(['name' => 'Item A', 'sku' => 'SPECIAL-SKU-99']);
        $itemB = $this->createItem(['name' => 'Item B', 'sku' => 'OTHER-SKU-11']);

        $response = $this->actingAs($user)->get(route('inventory.items', ['search' => 'SPECIAL-SKU-99']));

        $response->assertOk();
        $response->assertSee('SPECIAL-SKU-99');
        $response->assertDontSee('OTHER-SKU-11');
    }

    public function test_inventory_items_catalog_renders_contextual_action_links(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem(['name' => 'Gloves XL', 'sku' => 'PPE-GOWN-XL']);

        $response = $this->actingAs($user)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertSee(route('inventory.requisitions.index', ['item_id' => $item->id]));
        $response->assertSee(route('inventory.adjustments', ['item_id' => $item->id]));
    }

    public function test_material_requisitions_preserves_item_context_when_validation_fails(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem(['name' => 'Gloves XL', 'sku' => 'PPE-GOWN-XL']);

        // Submit with invalid requested_quantity (-1) to trigger validation failure
        $response = $this->actingAs($user)
            ->from(route('inventory.requisitions.index', ['item_id' => $item->id]))
            ->post(route('inventory.requisitions.store'), [
                'context_item_id' => $item->id,
                'department' => 'Emergency Department',
                'lines' => [
                    [
                        'item_id' => $item->id,
                        'requested_quantity' => -1, // invalid
                    ],
                ],
            ]);

        $response->assertRedirect(route('inventory.requisitions.index', ['item_id' => $item->id]));
        $response->assertSessionHasErrors(['lines.0.requested_quantity']);

        // Follow redirect
        $followUp = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));
        $followUp->assertOk();
        $followUp->assertViewHas('preselectedItem', function ($preselected) use ($item) {
            return $preselected !== null && $preselected->id === $item->id;
        });
        $followUp->assertSee('Contextual Item:');
        $followUp->assertSee('Gloves XL');
        $followUp->assertSee('PPE-GOWN-XL');
    }

    public function test_material_requisitions_index_calculates_ai_reorder_recommendation_when_item_preselected(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem([
            'name' => 'Blood Glucose Test Strips',
            'sku' => 'MED-GLU-STRIP',
            'unit' => 'box',
            'quantity_on_hand' => 50,
            'reorder_level' => 70,
            'reorder_point' => 70,
            'safety_stock' => 20,
            'lead_time_days' => 7,
        ]);

        // Create 6 stock movements over the past 60 days (e.g., 25 boxes consumed each time = 150 boxes total)
        foreach (range(1, 6) as $i) {
            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => MovementType::StockOut,
                'quantity' => 25,
                'moved_at' => now()->subDays($i * 10),
            ]);
        }

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('preselectedItem', fn ($p) => $p && $p->id === $item->id);
        $response->assertViewHas('aiRecommendation', function ($rec) {
            return $rec !== null
                && ($rec['available'] ?? false) === true
                && isset($rec['suggested_quantity'])
                && $rec['suggested_quantity'] > 0
                && isset($rec['breakdown']['forecast_demand'])
                && isset($rec['breakdown']['current_stock']);
        });

        $response->assertSee('AI-Suggested Reorder:');
        $response->assertSee('Forecast Demand');
        $response->assertSee('Available Stock');
        $response->assertSee('Safety Buffer');
        $response->assertSee('AI Suggested:');
    }

    public function test_material_requisitions_accounts_for_pending_purchase_orders_to_avoid_duplicate_ordering(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem([
            'name' => 'Surgical Mask 3-Ply',
            'sku' => 'PPE-MASK-3P',
            'unit' => 'box',
            'quantity_on_hand' => 20,
            'reorder_level' => 80,
            'safety_stock' => 20,
        ]);

        // Add consumption history
        foreach (range(1, 4) as $i) {
            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => MovementType::StockOut,
                'quantity' => 20,
                'moved_at' => now()->subDays($i * 14),
            ]);
        }

        $supplier = Supplier::create([
            'name' => 'Apex Medical Supplies',
            'contact_person' => 'John Doe',
            'email' => 'sales@apexmed.test',
            'phone' => '09171234567',
            'address' => '123 Health Ave, Manila',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Approved->value,
            'currency' => 'PHP',
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
            'created_by' => $user->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'line_number' => 1,
            'purchase_unit' => 'box',
            'conversion_factor' => 1,
            'ordered_quantity' => 30,
            'received_quantity' => 0,
            'unit_price' => 150.00,
            'total_line_amount' => 4500.00,
            'line_status' => 'ordered',
        ]);

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('aiRecommendation', function ($rec) {
            return $rec !== null
                && $rec['incoming_procurement'] === 30
                && str_contains($rec['breakdown']['incoming_stock'], '30');
        });
        $response->assertSee('30 box');
    }

    public function test_material_requisitions_handles_insufficient_forecast_data_gracefully(): void
    {
        $user = $this->createInventoryManager();
        // Brand new item without any consumption movements
        $item = $this->createItem([
            'name' => 'Novel Diagnostic Reagent',
            'sku' => 'LAB-NEW-001',
            'quantity_on_hand' => 5,
            'reorder_level' => 20,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.requisitions.index', ['item_id' => $item->id]));

        $response->assertOk();
        $response->assertViewHas('aiRecommendation', function ($rec) {
            return $rec !== null
                && $rec['available'] === false
                && $rec['suggested_quantity'] === null;
        });

        $response->assertSee('AI Forecast: Insufficient Data');
        $response->assertSee('Insufficient historical consumption data');
    }

    public function test_material_requisitions_store_records_ai_suggestion_and_user_quantity_in_audit_log(): void
    {
        $user = $this->createInventoryManager();
        $item = $this->createItem([
            'name' => 'Hypodermic Syringe 5ml',
            'sku' => 'SYR-5ML',
            'quantity_on_hand' => 10,
        ]);

        $response = $this->actingAs($user)->post(route('inventory.requisitions.store'), [
            'department' => 'Emergency Department',
            'urgency' => 'routine',
            'justification' => 'Ward replenishments',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => 150, // User edited quantity
                    'ai_suggested_quantity' => 120, // AI suggested quantity
                    'allocation_strategy' => 'FEFO',
                ],
            ],
        ]);

        $response->assertRedirect(route('inventory.requisitions.index'));

        // Check audit log for CreatedMaterialRequisition
        $audit = AuditLog::query()
            ->where('action', AuditAction::CreatedMaterialRequisition->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals(120, $audit->new_values['ai_suggested_quantity'] ?? null);
        $this->assertEquals(150, $audit->new_values['final_requested_quantity'] ?? null);
        $this->assertStringContainsString('AI Suggested: 120', $audit->description);
        $this->assertStringContainsString('Requested: 150', $audit->description);
    }
}

