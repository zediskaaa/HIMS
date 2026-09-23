<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\Warehouse\BarcodeService;
use App\Services\Warehouse\WarehouseTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CameraScanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: StorageLocation, 1: StorageLocation, 2: InventoryItem, 3: ItemBatch}
     */
    private function seedTaskPrerequisites(): array
    {
        $source = StorageLocation::create([
            'name' => 'Receiving Bay 01',
            'code' => 'BAY-01',
            'barcode_value' => 'LOC-BAY-01',
            'status' => 'active',
        ]);

        $destination = StorageLocation::create([
            'name' => 'Aisle 02 Shelf B',
            'code' => 'A2-B',
            'barcode_value' => 'LOC-A2-B',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Sterile Scalpel No. 10',
            'sku' => 'SCALP-10',
            'barcode_value' => 'ITEM-SCALP-10',
            'gtin' => '04801234567897',
            'quantity_on_hand' => 50,
            'status' => 'active',
        ]);

        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-2026-X1',
            'expiry_date' => now()->addYear(),
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $source->id,
            'item_batch_id' => $batch->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        return [$source, $destination, $item, $batch];
    }

    public function test_unauthenticated_user_redirected_from_scan_station(): void
    {
        $this->get(route('inventory.warehousing.scan-station'))
            ->assertRedirect(route('login'));
    }

    public function test_auditor_and_viewer_cannot_access_scan_station(): void
    {
        $auditor = User::factory()->role(UserRole::Auditor)->create();
        $viewer = User::factory()->role(UserRole::Viewer)->create();

        $this->actingAs($auditor)->get(route('inventory.warehousing.scan-station'))
            ->assertForbidden();

        $this->actingAs($viewer)->get(route('inventory.warehousing.scan-station'))
            ->assertForbidden();
    }

    public function test_warehouse_staff_can_view_scan_station_with_camera_controls(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $task = app(WarehouseTaskService::class)->create([
            'task_type' => WarehouseTaskType::PutAway,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 10,
            'assigned_to_id' => $operator->id,
        ], $operator);

        $response = $this->actingAs($operator)->get(route('inventory.warehousing.scan-station'));

        $response->assertOk()
            ->assertSee('Warehouse Scan Workstation')
            ->assertSee('Open Camera')
            ->assertSee('himsCameraScanner')
            ->assertSee('Ready for Barcode / QR Scanner Input')
            ->assertSee($task->task_number);
    }

    public function test_camera_scan_submission_advances_warehouse_task_workflow(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $service = app(WarehouseTaskService::class);
        $task = $service->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 5,
            'assigned_to_id' => $operator->id,
        ], $operator);

        $service->start($task, $operator);

        // Step 1: Scan Source Location
        $response1 = $this->actingAs($operator)->post(route('inventory.warehouse-tasks.scan', $task), [
            'scan_value' => $source->barcode_value,
        ]);
        $response1->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('warehouse_scan_events', [
            'warehouse_task_id' => $task->id,
            'outcome' => 'accepted',
            'sequence_number' => 1,
        ]);

        // Step 2: Scan Item GS1 DataMatrix / SKU
        $response2 = $this->actingAs($operator)->post(route('inventory.warehouse-tasks.scan', $task), [
            'scan_value' => $item->sku,
        ]);
        $response2->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('warehouse_scan_events', [
            'warehouse_task_id' => $task->id,
            'outcome' => 'accepted',
            'sequence_number' => 2,
        ]);

        // Step 3: Scan Destination Location
        $response3 = $this->actingAs($operator)->post(route('inventory.warehouse-tasks.scan', $task), [
            'scan_value' => $destination->barcode_value,
        ]);
        $response3->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('warehouse_scan_events', [
            'warehouse_task_id' => $task->id,
            'outcome' => 'accepted',
            'sequence_number' => 3,
        ]);

        // Finalize: Complete task
        $completeResponse = $this->actingAs($operator)->post(route('inventory.warehouse-tasks.complete', $task), [
            'quantity' => 5,
        ]);
        $completeResponse->assertRedirect()->assertSessionHas('success');

        $this->assertSame(WarehouseTaskStatus::Completed, $task->fresh()->status);
    }

    public function test_scanning_the_task_label_identifies_the_job_without_raising_an_exception(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $service = app(WarehouseTaskService::class);
        $task = $service->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 5,
            'assigned_to_id' => $operator->id,
        ], $operator);

        $service->start($task, $operator);

        $identification = $service->scan($task, $task->task_number, $operator, 'scan-task-label');
        $this->assertSame('identified', $identification->outcome);
        $this->assertStringContainsString($task->task_number, (string) $identification->message);
        $this->assertStringContainsString($source->code, (string) $identification->message);
        $this->assertDatabaseMissing('warehouse_exceptions', ['warehouse_task_id' => $task->id]);

        // The identification scan must not consume a step: the source still comes first
        // and is accepted under sequence number 1.
        $this->assertSame('accepted', $service->scan($task, $source->barcode_value, $operator, 'scan-source')->outcome);
        $this->assertDatabaseHas('warehouse_scan_events', [
            'warehouse_task_id' => $task->id,
            'raw_value' => $source->barcode_value,
            'outcome' => 'accepted',
            'sequence_number' => 1,
        ]);
    }

    public function test_invalid_barcode_scan_records_exception_and_error_feedback(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $service = app(WarehouseTaskService::class);
        $task = $service->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 5,
            'assigned_to_id' => $operator->id,
        ], $operator);

        $service->start($task, $operator);

        $response = $this->actingAs($operator)->post(route('inventory.warehouse-tasks.scan', $task), [
            'scan_value' => 'INVALID-BARCODE-999',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('warehouse_scan_events', [
            'warehouse_task_id' => $task->id,
            'outcome' => 'rejected',
        ]);
        $this->assertDatabaseHas('warehouse_exceptions', [
            'warehouse_task_id' => $task->id,
            'status' => 'open',
        ]);
    }

    public function test_direct_scan_forbidden_without_execute_permission(): void
    {
        $auditor = User::factory()->role(UserRole::Auditor)->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $service = app(WarehouseTaskService::class);
        $task = $service->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 5,
        ], $auditor);

        $this->actingAs($auditor)->post(route('inventory.warehouse-tasks.scan', $task), [
            'scan_value' => $source->barcode_value,
        ])->assertForbidden();
    }

    public function test_consignment_screen_renders_camera_scanner_for_authorized_staff(): void
    {
        $staff = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($staff)->get(route('inventory.warehousing.consignment'));

        $response->assertOk()
            ->assertSee('camera-scanner-consignment')
            ->assertSee('Scan Surgical Implant Serial / DataMatrix');
    }

    public function test_consignment_balance_does_not_claim_fda_registration_without_a_reference(): void
    {
        $staff = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create([
            'name' => 'Operating Suite Storage',
            'code' => 'OR-STORAGE',
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Consignment Orthopedic Implant',
            'sku' => 'IMPLANT-ORTHO-01',
            'is_consignment' => true,
            'status' => 'active',
        ]);
        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 2,
        ]);

        $this->actingAs($staff)->get(route('inventory.warehousing.consignment'))
            ->assertOk()
            ->assertSee('Consignment Orthopedic Implant')
            ->assertSee('Not recorded')
            ->assertDontSee('MDR-Registered');
    }

    public function test_cycle_count_show_renders_shelf_scanner(): void
    {
        $staff = User::factory()->warehouseStaff()->create();
        [$source, , $item, $batch] = $this->seedTaskPrerequisites();

        $doc = \App\Models\CycleCountDoc::create([
            'document_number' => 'CC-TEST-001',
            'count_type' => 'blind',
            'status' => 'scheduled',
            'storage_location_id' => $source->id,
            'assigned_counter_id' => $staff->id,
            'scheduled_date' => today(),
            'snapshot_timestamp' => now(),
        ]);

        \App\Models\CycleCountLine::create([
            'cycle_count_doc_id' => $doc->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'storage_location_id' => $source->id,
            'book_quantity_snapshot' => 50,
        ]);

        $response = $this->actingAs($staff)->get(route('inventory.cycle-counts.show', $doc));

        $response->assertOk()
            ->assertSee('camera-scanner-cycle-count')
            ->assertSee('Scan Shelf Item');
    }

    public function test_barcode_service_resolves_all_supported_symbologies(): void
    {
        [$source, , $item, $batch] = $this->seedTaskPrerequisites();
        $barcodeService = app(BarcodeService::class);

        // Location code
        $locResult = $barcodeService->parseAndResolve($source->code);
        $this->assertSame('location', $locResult['resolved_type']);
        $this->assertSame($source->id, $locResult['resolved_id']);

        // Item SKU
        $skuResult = $barcodeService->parseAndResolve($item->sku);
        $this->assertSame('item', $skuResult['resolved_type']);
        $this->assertSame($item->id, $skuResult['resolved_id']);

        // GS1 HRI format with (01) GTIN and (10) Batch
        $gs1Result = $barcodeService->parseAndResolve('(01)04801234567897(10)LOT-2026-X1');
        $this->assertSame('item', $gs1Result['resolved_type']);
        $this->assertSame($item->id, $gs1Result['resolved_id']);
        $this->assertSame('04801234567897', $gs1Result['gtin']);
        $this->assertSame('LOT-2026-X1', $gs1Result['batch']);

        // GS1 DataMatrix format with ]d2 prefix
        $dmResult = $barcodeService->parseAndResolve(']d2010480123456789710LOT-2026-X1');
        $this->assertSame('item', $dmResult['resolved_type']);
        $this->assertSame($item->id, $dmResult['resolved_id']);
    }

    public function test_smart_warehousing_dashboard_renders_with_scan_events(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $service = app(WarehouseTaskService::class);
        $task = $service->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 5,
            'assigned_to_id' => $operator->id,
        ], $operator);

        $service->start($task, $operator);
        $service->scan($task, $source->barcode_value, $operator);

        $response = $this->actingAs($operator)->get(route('inventory.warehousing.dashboard'));
        $response->assertOk()
            ->assertSee('Smart Warehousing System (SWS)')
            ->assertSee($source->barcode_value)
            ->assertDontSee('View All (', false);
    }

    public function test_smart_warehousing_dashboard_hides_view_all_when_tasks_are_zero_or_few(): void
    {
        $operator = User::factory()->warehouseStaff()->create();

        // 0 tasks: should not display "View All (0)"
        $response = $this->actingAs($operator)->get(route('inventory.warehousing.dashboard'));
        $response->assertOk();
        $response->assertDontSee('View All (', false);
    }

    public function test_unauthorized_user_cannot_perform_barcode_lookup(): void
    {
        $viewer = User::factory()->role(UserRole::Viewer)->create();
        [$source] = $this->seedTaskPrerequisites();

        $this->actingAs($viewer)
            ->postJson(route('inventory.warehousing.lookup-barcode'), ['barcode' => $source->code])
            ->assertForbidden();
    }

    public function test_operator_can_lookup_item_barcode_and_get_details(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [, , $item] = $this->seedTaskPrerequisites();

        $response = $this->actingAs($operator)
            ->postJson(route('inventory.warehousing.lookup-barcode'), ['barcode' => $item->sku]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'item',
                'id' => $item->id,
                'sku' => $item->sku,
                'is_active' => true,
            ]);
    }

    public function test_operator_can_lookup_storage_location_barcode(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source] = $this->seedTaskPrerequisites();

        $response = $this->actingAs($operator)
            ->postJson(route('inventory.warehousing.lookup-barcode'), ['barcode' => $source->code]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'location',
                'id' => $source->id,
                'code' => $source->code,
                'is_active' => true,
            ]);
    }

    public function test_operator_lookup_flags_inactive_storage_location(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        $inactiveLocation = StorageLocation::create([
            'name' => 'Decommissioned Bin 99',
            'code' => 'BIN-99-INACT',
            'barcode_value' => 'LOC-BIN-99-INACT',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($operator)
            ->postJson(route('inventory.warehousing.lookup-barcode'), ['barcode' => $inactiveLocation->code]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'location',
                'location_status' => 'inactive',
                'is_active' => false,
            ]);
        $this->assertNotNull($response->json('warning'));
    }

    public function test_operator_cannot_perform_inbound_into_inactive_location(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        $inactiveLocation = StorageLocation::create([
            'name' => 'Decommissioned Bin 99',
            'code' => 'BIN-99-INACT',
            'barcode_value' => 'LOC-BIN-99-INACT',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($operator)
            ->postJson(route('inventory.warehousing.lookup-barcode'), [
                'barcode' => $inactiveLocation->code,
                'operation' => 'inbound',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status' => 'inactive_location_blocked',
            ]);
    }

    public function test_operator_can_lookup_warehouse_task_number(): void
    {
        $operator = User::factory()->warehouseStaff()->create();
        [$source, $destination, $item, $batch] = $this->seedTaskPrerequisites();

        $task = app(WarehouseTaskService::class)->create([
            'task_type' => WarehouseTaskType::Move,
            'priority' => 'normal',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'requested_quantity' => 5,
            'assigned_to_id' => $operator->id,
        ], $operator);

        $response = $this->actingAs($operator)
            ->postJson(route('inventory.warehousing.lookup-barcode'), ['barcode' => $task->task_number]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'task',
                'id' => $task->id,
                'task_number' => $task->task_number,
            ]);
    }

    public function test_lookup_returns_404_for_unknown_barcode(): void
    {
        $operator = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($operator)
            ->postJson(route('inventory.warehousing.lookup-barcode'), ['barcode' => 'UNKNOWN-NONEXISTENT-999']);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'status' => 'not_found',
            ]);
    }

    public function test_scan_station_supports_direct_camera_launch_and_nav_link(): void
    {
        $operator = User::factory()->warehouseStaff()->create();

        // 1. Dashboard displays Launch Scanner action with direct camera query param
        $dashboardResponse = $this->actingAs($operator)->get(route('inventory.warehousing.dashboard'));
        $dashboardResponse->assertOk()
            ->assertSee(route('inventory.warehousing.scan-station', ['camera' => 1]))
            ->assertSee('Launch Scanner');

        // 2. Scan station handles direct camera parameter and includes auto-lookup on scan
        $scanResponse = $this->actingAs($operator)->get(route('inventory.warehousing.scan-station', ['camera' => 1]));
        $scanResponse->assertOk()
            ->assertSee('camera-scanner-standby')
            ->assertSee('@hims-code-scanned.window', false)
            ->assertSee('lookupBarcode', false);
    }
}
