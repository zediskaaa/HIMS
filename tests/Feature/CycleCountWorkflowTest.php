<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CycleCountDoc;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleCountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function createInventoryManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function createWarehouseStaff(): User
    {
        return User::factory()->warehouseStaff()->create();
    }

    private function createPharmacyStaff(): User
    {
        return User::factory()->pharmacyStaff()->create();
    }

    private function createItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name' => 'Propofol 10mg/mL 20mL Vial',
            'sku' => 'MED-PROP-20ML',
            'description' => 'Intravenous anesthetic emulsion',
            'unit_of_measure' => 'vial',
            'unit_cost' => 250.00,
            'quantity_on_hand' => 50,
            'reorder_point' => 10,
            'safety_stock' => 5,
            'economic_order_quantity' => 25,
            'lead_time_days' => 3,
            'annual_demand' => 300,
            'abc_class' => 'A',
            'costing_method' => 'FIFO',
            'status' => 'active',
        ], $overrides));
    }

    private function createLocation(string $code = 'LOC-MAIN-01', string $name = 'Central Pharmacy Shelves'): StorageLocation
    {
        return StorageLocation::create([
            'code' => $code,
            'name' => $name,
            'zone' => 'General',
            'status' => 'active',
        ]);
    }

    public function test_cycle_counts_index_loads_successfully_without_is_active_error(): void
    {
        $manager = $this->createInventoryManager();
        $warehouse = $this->createWarehouseStaff();
        $pharmacy = $this->createPharmacyStaff();

        // Create an inactive warehouse user
        $inactiveWarehouse = User::factory()->warehouseStaff()->create([
            'status' => UserStatus::Inactive,
        ]);

        $response = $this->actingAs($manager)->get(route('inventory.cycle-counts.index'));

        $response->assertOk();
        $response->assertViewIs('inventory.cycle-counts.index');
        $response->assertSeeText('Cycle Counts & Physical Audits');
        $response->assertSeeText('Schedule Cycle Count');

        // Check that counters passed to view contains active authorized users only
        $response->assertViewHas('counters', function ($counters) use ($warehouse, $manager, $pharmacy, $inactiveWarehouse) {
            // Must contain active warehouse staff and inventory manager
            $ids = $counters->pluck('id')->all();
            $this->assertContains($warehouse->id, $ids);
            $this->assertContains($manager->id, $ids);

            // Must NOT contain pharmacy staff (does not have PerformCycleCount)
            $this->assertNotContains($pharmacy->id, $ids);

            // Must NOT contain inactive warehouse staff
            $this->assertNotContains($inactiveWarehouse->id, $ids);

            return true;
        });
    }

    public function test_scheduling_cycle_count_with_active_authorized_counter_succeeds(): void
    {
        $manager = $this->createInventoryManager();
        $counter = $this->createWarehouseStaff();
        $item = $this->createItem();
        $location = $this->createLocation();

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($manager)->post(route('inventory.cycle-counts.schedule'), [
            'count_type' => 'ABC',
            'storage_location_id' => $location->id,
            'assigned_counter_id' => $counter->id,
        ]);

        $doc = CycleCountDoc::first();
        $this->assertNotNull($doc);
        $this->assertEquals($counter->id, $doc->assigned_counter_id);
        $this->assertEquals('generated', $doc->status);
        $this->assertCount(1, $doc->lines);
        $this->assertEquals(50, $doc->lines->first()->book_quantity_snapshot);

        $response->assertRedirect(route('inventory.cycle-counts.show', $doc));

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::ScheduledCycleCount->value,
            'target_id' => $doc->id,
        ]);
    }

    public function test_scheduling_cycle_count_rejects_inactive_or_unauthorized_counter(): void
    {
        $manager = $this->createInventoryManager();
        $pharmacy = $this->createPharmacyStaff(); // Unauthorized role
        $inactiveStaff = User::factory()->warehouseStaff()->create(['status' => UserStatus::Inactive]);

        // Attempt scheduling with unauthorized pharmacy staff
        $response1 = $this->actingAs($manager)->post(route('inventory.cycle-counts.schedule'), [
            'count_type' => 'ABC',
            'assigned_counter_id' => $pharmacy->id,
        ]);
        $response1->assertSessionHasErrors(['assigned_counter_id']);

        // Attempt scheduling with inactive staff
        $response2 = $this->actingAs($manager)->post(route('inventory.cycle-counts.schedule'), [
            'count_type' => 'ABC',
            'assigned_counter_id' => $inactiveStaff->id,
        ]);
        $response2->assertSessionHasErrors(['assigned_counter_id']);
    }

    public function test_submitting_blind_counts_records_physical_count_and_detects_variance(): void
    {
        $manager = $this->createInventoryManager();
        $counter = $this->createWarehouseStaff();
        $item = $this->createItem(['quantity_on_hand' => 50, 'unit_cost' => 200.00]);
        $location = $this->createLocation();

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($manager)->post(route('inventory.cycle-counts.schedule'), [
            'count_type' => 'ABC',
            'storage_location_id' => $location->id,
            'assigned_counter_id' => $counter->id,
        ]);

        $doc = CycleCountDoc::first();
        $line = $doc->lines->first();

        // Blind counter submits physical count of 45 (variance -5 units = -₱1,000)
        $response = $this->actingAs($counter)->post(route('inventory.cycle-counts.submit', $doc), [
            'counts' => [
                $line->id => 45,
            ],
        ]);

        $response->assertRedirect(route('inventory.cycle-counts.show', $doc));

        $doc->refresh();
        $line->refresh();

        $this->assertEquals(45, $line->counted_quantity_blind);
        $this->assertEquals(-5, $line->variance_quantity);
        $this->assertEquals(-1000.00, $line->variance_value);
    }

    public function test_segregation_of_duties_prevents_counter_from_approving_own_cycle_count(): void
    {
        $counter = $this->createInventoryManager(); // Manager acting as counter
        $supervisor = $this->createAdmin();
        $item = $this->createItem();
        $location = $this->createLocation();

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($counter)->post(route('inventory.cycle-counts.schedule'), [
            'count_type' => 'ABC',
            'storage_location_id' => $location->id,
            'assigned_counter_id' => $counter->id,
        ]);

        $doc = CycleCountDoc::first();
        $line = $doc->lines->first();

        $this->actingAs($counter)->post(route('inventory.cycle-counts.submit', $doc), [
            'counts' => [$line->id => 50],
        ]);

        // Counter tries to approve their own count
        $response = $this->actingAs($counter)->post(route('inventory.cycle-counts.approve', $doc));
        $response->assertSessionHasErrors(['approve']);

        $doc->refresh();
        $this->assertNotEquals('approved', $doc->status);

        // Independent supervisor approves
        $response2 = $this->actingAs($supervisor)->post(route('inventory.cycle-counts.approve', $doc));
        $response2->assertRedirect(route('inventory.cycle-counts.show', $doc));

        $doc->refresh();
        $this->assertEquals('posted', $doc->status); // Status is 'posted' after variance resolution
    }
}
