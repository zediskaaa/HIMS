<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\WarehouseTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTaskWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_modal_trigger_and_modal_dialog_markup(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)->get(route('inventory.warehouse-tasks.index'));

        $response->assertOk();
        $response->assertSee('Create Task');
        $response->assertSee('create-operational-task');
        $response->assertSee('Create Operational Task');
    }

    public function test_operator_without_manage_permission_does_not_see_create_task_modal_or_trigger(): void
    {
        $operator = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($operator)->get(route('inventory.warehouse-tasks.index'));

        $response->assertOk();
        $response->assertDontSee('create-operational-task');
        $response->assertDontSee('Create Operational Task');
    }

    public function test_web_store_creates_task_and_redirects_to_show(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $source = StorageLocation::create([
            'name' => 'Source Bin A',
            'code' => 'SRC-A',
            'barcode_value' => 'SRC-A',
            'type' => 'bin',
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'capacity' => 100,
            'status' => 'active',
        ]);
        $destination = StorageLocation::create([
            'name' => 'Destination Bin B',
            'code' => 'DST-B',
            'barcode_value' => 'DST-B',
            'type' => 'bin',
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'capacity' => 100,
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Surgical Mask Box',
            'sku' => 'MSK-SURG-50',
            'unit' => 'box',
            'quantity_on_hand' => 100,
            'reorder_level' => 10,
            'unit_cost' => 50,
            'total_value' => 5000,
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'status' => 'active',
        ]);

        $payload = [
            'task_type' => 'move',
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'requested_quantity' => 5,
            'notes' => 'Urgent replenishment transfer',
        ];

        $response = $this->actingAs($manager)->post(route('inventory.warehouse-tasks.store'), $payload);

        $task = WarehouseTask::latest('id')->first();
        $this->assertNotNull($task);
        $this->assertSame(5, $task->requested_quantity);
        $this->assertSame('move', $task->task_type->value);

        $response->assertRedirect(route('inventory.warehouse-tasks.show', $task));
        $response->assertSessionHas('success');
    }

    public function test_web_store_validation_failure_redirects_back_with_errors(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)->post(route('inventory.warehouse-tasks.store'), [
            'task_type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors(['task_type', 'source_location_id', 'destination_location_id', 'item_id', 'requested_quantity']);
    }
}
