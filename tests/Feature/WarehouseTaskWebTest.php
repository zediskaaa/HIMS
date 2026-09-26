<?php

namespace Tests\Feature;

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

    public function test_manager_sees_compact_task_workspace_and_secondary_action_modals(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $task = $this->createInProgressTask($manager);

        $response = $this->actingAs($manager)->get(route('inventory.warehouse-tasks.show', $task));

        $response->assertOk()
            ->assertSee('Task summary')
            ->assertSee('Scan verification')
            ->assertSee('Complete all required scans before posting stock.')
            ->assertSee('Task activity')
            ->assertSee('xl:grid-cols-2', false)
            ->assertSee('Scan History (0)')
            ->assertSee('Exceptions (0)')
            ->assertSee('change-task-assignment')
            ->assertSee('generate-task-label')
            ->assertSee('target-task-code')
            ->assertSee('cancel-warehouse-task')
            ->assertDontSee('Testing shortcut')
            ->assertDontSee('Scan expected identifier')
            ->assertSee('x-bind:disabled="reason.trim().length === 0"', false);
    }

    public function test_operator_can_use_scan_workspace_without_manage_task_actions(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $operator = User::factory()->warehouseStaff()->create();
        $task = $this->createInProgressTask($manager, $operator);

        $response = $this->actingAs($operator)->get(route('inventory.warehouse-tasks.show', $task));

        $response->assertOk()
            ->assertSee('Scan verification')
            ->assertSee('Scan with Camera')
            ->assertDontSee('change-task-assignment')
            ->assertDontSee('cancel-warehouse-task');
    }

    private function createInProgressTask(User $creator, ?User $assignee = null): WarehouseTask
    {
        $source = StorageLocation::create([
            'name' => 'Main Warehouse Bin 01',
            'code' => 'WH-01',
            'barcode_value' => 'WH-01',
            'type' => 'bin',
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'capacity' => 100,
            'status' => 'active',
        ]);
        $destination = StorageLocation::create([
            'name' => 'Operating Theater Cleanroom',
            'code' => 'OT-CR-03',
            'barcode_value' => 'OT-CR-03',
            'type' => 'room',
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'capacity' => 100,
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Examination Gloves (Medium)',
            'sku' => 'FCST-GLOVE-M',
            'barcode_value' => '480000000056',
            'unit' => 'box',
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
            'unit_cost' => 150,
            'total_value' => 4500,
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'status' => 'active',
        ]);

        return WarehouseTask::create([
            'task_number' => 'TSK-COMPACT-WORKSPACE-001',
            'task_type' => 'move',
            'status' => 'in_progress',
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'requested_quantity' => 30,
            'completed_quantity' => 0,
            'assigned_to_id' => ($assignee ?? $creator)->id,
            'created_by_id' => $creator->id,
            'started_at' => now(),
            'recommendation_reason' => 'Available stock selected by warehouse pick sequence.',
        ]);
    }
}
