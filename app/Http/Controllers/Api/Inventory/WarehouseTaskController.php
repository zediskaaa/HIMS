<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WarehouseTask;
use App\Services\Warehouse\WarehouseTaskService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class WarehouseTaskController extends Controller implements HasMiddleware
{
    public function __construct(private readonly WarehouseTaskService $tasks) {}

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageWarehouseTasks->value, only: ['store', 'assign', 'cancel']),
            new Middleware('can:'.Permission::ExecuteWarehouseTasks->value, only: ['start', 'scan', 'complete']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(WarehouseTaskStatus::class)],
            'task_type' => ['nullable', Rule::enum(WarehouseTaskType::class)],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tasks = WarehouseTask::with(['item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo'])
            ->when($validated['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($validated['task_type'] ?? null, fn ($query, $value) => $query->where('task_type', $value))
            ->when($validated['assigned_to_id'] ?? null, fn ($query, $value) => $query->where('assigned_to_id', $value))
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 25));

        return response()->json($tasks);
    }

    public function show(WarehouseTask $warehouseTask): JsonResponse
    {
        return response()->json($warehouseTask->load([
            'item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo',
            'events.actor', 'scans.scannedBy', 'exceptions',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_type' => ['required', Rule::enum(WarehouseTaskType::class)],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'source_location_id' => ['nullable', 'different:destination_location_id', 'exists:storage_locations,id'],
            'destination_location_id' => ['nullable', 'exists:storage_locations,id'],
            'item_id' => ['nullable', 'exists:inventory_items,id'],
            'item_batch_id' => ['nullable', 'exists:item_batches,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $validated['idempotency_key'] = $request->header('Idempotency-Key');

        return $this->respond(fn () => $this->tasks->create($validated, $request->user()), 201);
    }

    public function assign(Request $request, WarehouseTask $warehouseTask): JsonResponse
    {
        $validated = $request->validate(['assigned_to_id' => ['required', 'exists:users,id']]);

        return $this->respond(fn () => $this->tasks->assign($warehouseTask, User::findOrFail($validated['assigned_to_id']), $request->user()));
    }

    public function start(Request $request, WarehouseTask $warehouseTask): JsonResponse
    {
        return $this->respond(fn () => $this->tasks->start($warehouseTask, $request->user()));
    }

    public function scan(Request $request, WarehouseTask $warehouseTask): JsonResponse
    {
        $validated = $request->validate(['scan_value' => ['required', 'string', 'max:1000']]);

        return $this->respond(fn () => $this->tasks->scan(
            $warehouseTask,
            $validated['scan_value'],
            $request->user(),
            $request->header('Idempotency-Key'),
        ));
    }

    public function complete(Request $request, WarehouseTask $warehouseTask): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->respond(fn () => $this->tasks->complete(
            $warehouseTask,
            (int) $validated['quantity'],
            $request->user(),
            $validated['override_reason'] ?? null,
        ));
    }

    public function cancel(Request $request, WarehouseTask $warehouseTask): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->respond(fn () => $this->tasks->cancel($warehouseTask, $validated['reason'], $request->user()));
    }

    private function respond(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
