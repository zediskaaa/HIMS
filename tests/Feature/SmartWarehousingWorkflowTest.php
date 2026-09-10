<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\WarehouseTaskEvent;
use App\Services\Warehouse\BarcodeService;
use App\Services\Warehouse\WarehouseTaskService;
use Database\Seeders\SmartWarehousingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class SmartWarehousingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_validated_move_updates_location_balances_and_history(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination] = $this->locations();
        [$item, $batch] = $this->itemAndBatch();
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $source->id,
            'item_batch_id' => $batch->id,
            'quantity' => 10,
        ]);

        $service = app(WarehouseTaskService::class);
        $task = $service->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 4,
            'assigned_to_id' => $operator->id,
            'idempotency_key' => 'test-move-1',
        ], $manager);

        $service->start($task, $operator);
        $wrong = $service->scan($task, 'UNKNOWN-CODE', $operator, 'scan-wrong');
        $this->assertSame('rejected', $wrong->outcome);
        $this->assertDatabaseHas('warehouse_exceptions', ['warehouse_task_id' => $task->id, 'exception_type' => 'unknown_barcode']);

        $this->assertSame('accepted', $service->scan($task, $source->barcode_value, $operator, 'scan-source')->outcome);
        $this->assertSame('accepted', $service->scan($task, '(01)04801234567897(10)LOT-A(17)280131', $operator, 'scan-item')->outcome);
        $this->assertSame('accepted', $service->scan($task, $destination->barcode_value, $operator, 'scan-destination')->outcome);
        $service->complete($task, 4, $operator);

        $this->assertSame(WarehouseTaskStatus::Completed, $task->refresh()->status);
        $this->assertDatabaseHas('item_stock_levels', [
            'item_id' => $item->id,
            'storage_location_id' => $source->id,
            'item_batch_id' => $batch->id,
            'quantity' => 6,
        ]);
        $this->assertDatabaseHas('item_stock_levels', [
            'item_id' => $item->id,
            'storage_location_id' => $destination->id,
            'item_batch_id' => $batch->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => $task->getMorphClass(),
            'reference_id' => $task->id,
            'quantity' => 4,
        ]);
    }

    public function test_task_and_scan_idempotency_do_not_duplicate_work(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination] = $this->locations();
        [$item] = $this->itemAndBatch();
        $service = app(WarehouseTaskService::class);
        $data = [
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'requested_quantity' => 1,
            'assigned_to_id' => $operator->id,
            'idempotency_key' => 'same-task',
        ];

        $first = $service->create($data, $manager);
        $second = $service->create($data, $manager);
        $this->assertTrue($first->is($second));

        $service->start($first, $operator);
        $firstScan = $service->scan($first, $source->code, $operator, 'same-scan');
        $replay = $service->scan($first, $source->code, $operator, 'same-scan');
        $this->assertTrue($firstScan->is($replay));
        $this->assertSame(1, $first->scans()->count());
    }

    public function test_role_separation_blocks_system_admin_from_managing_or_executing_tasks(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->inventoryManager()->create();
        $operator = User::factory()->warehouseStaff()->create();

        $this->assertTrue($manager->hasPermission(Permission::ManageWarehouseTasks));
        $this->assertTrue($operator->hasPermission(Permission::ExecuteWarehouseTasks));
        $this->assertFalse($operator->hasPermission(Permission::ManageWarehouseTasks));
        $this->assertFalse($admin->hasPermission(Permission::ManageWarehouseTasks));
        $this->assertFalse($admin->hasPermission(Permission::ExecuteWarehouseTasks));
    }

    public function test_api_requires_the_specific_task_permission(): void
    {
        $viewer = User::factory()->viewer()->create();
        $manager = User::factory()->inventoryManager()->create();
        [$source, $destination] = $this->locations();
        [$item] = $this->itemAndBatch();
        $payload = [
            'task_type' => 'move',
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'requested_quantity' => 1,
        ];

        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/inventory/warehouse-tasks', $payload)->assertForbidden();

        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/inventory/warehouse-tasks', $payload, ['Idempotency-Key' => 'api-create-task'])
            ->assertCreated()
            ->assertJsonPath('data.task_type', 'move');
    }

    public function test_event_records_are_append_only(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        [$source, $destination] = $this->locations();
        [$item] = $this->itemAndBatch();
        $task = app(WarehouseTaskService::class)->create([
            'task_type' => 'move',
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'requested_quantity' => 1,
        ], $manager);

        $event = $task->events()->firstOrFail();
        $this->expectException(LogicException::class);
        $event->update(['event_type' => 'tampered']);
    }

    public function test_demo_seeder_is_idempotent_and_preserves_completed_work(): void
    {
        User::factory()->inventoryManager()->create();
        $this->seed(SmartWarehousingDemoSeeder::class);
        $task = \App\Models\WarehouseTask::where('idempotency_key', 'smart-warehousing-demo-replenishment-v1')->firstOrFail();
        $task->update(['status' => WarehouseTaskStatus::Completed, 'completed_quantity' => 24, 'completed_at' => now()]);

        $this->seed(SmartWarehousingDemoSeeder::class);

        $this->assertSame(1, StorageLocation::where('code', 'SWS-DEMO-WH')->count());
        $this->assertSame(1, InventoryItem::where('sku', 'SWS-DEMO-SYRINGE-5ML')->count());
        $this->assertSame(1, \App\Models\WarehouseTask::where('idempotency_key', 'smart-warehousing-demo-replenishment-v1')->count());
        $this->assertSame(WarehouseTaskStatus::Completed, $task->refresh()->status);
    }

    /** @return array{StorageLocation, StorageLocation} */
    private function locations(): array
    {
        $source = StorageLocation::create([
            'name' => 'Reserve A', 'code' => 'RSV-A', 'barcode_value' => 'RSV-A', 'type' => 'bin',
            'storage_classification' => 'medical_supply', 'temperature_classification' => 'ambient',
            'capacity' => 100, 'is_reserve' => true, 'status' => 'active',
        ]);
        $destination = StorageLocation::create([
            'name' => 'Pick Face A', 'code' => 'PICK-A', 'barcode_value' => 'PICK-A', 'type' => 'bin',
            'storage_classification' => 'medical_supply', 'temperature_classification' => 'ambient',
            'capacity' => 20, 'is_pick_face' => true, 'status' => 'active',
        ]);

        return [$source, $destination];
    }

    /** @return array{InventoryItem, ItemBatch} */
    private function itemAndBatch(): array
    {
        $item = InventoryItem::create([
            'name' => 'Sterile Syringe 5 mL',
            'sku' => 'SYR-5ML',
            'gtin' => '04801234567897',
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
            'storage_classification' => 'medical_supply',
            'temperature_classification' => 'ambient',
            'unit' => 'box',
            'status' => 'active',
        ]);
        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-A',
            'lot_number' => 'LOT-A',
            'expiry_date' => '2028-01-31',
            'unit_cost' => 100,
            'initial_quantity' => 10,
            'status' => 'active',
        ]);

        return [$item, $batch];
    }
}
