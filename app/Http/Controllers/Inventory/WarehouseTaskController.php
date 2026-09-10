<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\WarehouseException;
use App\Models\WarehouseTask;
use App\Services\Warehouse\WarehouseLabelService;
use App\Services\Warehouse\WarehouseTaskService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class WarehouseTaskController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly WarehouseTaskService $tasks,
        private readonly WarehouseLabelService $labels,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageWarehouseTasks->value, only: ['store', 'assign', 'cancel']),
            new Middleware('can:'.Permission::ExecuteWarehouseTasks->value, only: ['start', 'scan', 'complete']),
            new Middleware('can:'.Permission::ResolveWarehouseExceptions->value, only: ['resolveException']),
            new Middleware('can:'.Permission::PrintWarehouseLabels->value, only: ['printLabel']),
        ];
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(WarehouseTaskStatus::cases(), 'value'))],
            'type' => ['nullable', 'in:'.implode(',', array_column(WarehouseTaskType::cases(), 'value'))],
        ]);

        $tasks = WarehouseTask::query()
            ->with(['item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('task_type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $locations = StorageLocation::active()->orderBy('code')->get();
        $items = InventoryItem::query()->where('status', '!=', 'discontinued')->orderBy('name')->get();
        $operators = User::active()->get()->filter(fn (User $user) => $user->hasPermission(Permission::ExecuteWarehouseTasks))->sortBy('name')->values();
        $metrics = [
            'open' => WarehouseTask::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'overdue' => WarehouseTask::whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
            'exceptions' => WarehouseException::where('status', 'open')->count(),
            'completed_today' => WarehouseTask::where('status', 'completed')->whereDate('completed_at', today())->count(),
        ];

        return view('inventory.warehouse_tasks.index', compact('tasks', 'locations', 'items', 'operators', 'metrics'));
    }

    public function show(WarehouseTask $warehouseTask): View
    {
        $warehouseTask->load(['item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo', 'createdBy', 'events.actor', 'scans.scannedBy', 'exceptions.raisedBy']);
        $operators = User::active()->get()->filter(fn (User $user) => $user->hasPermission(Permission::ExecuteWarehouseTasks))->sortBy('name')->values();

        return view('inventory.warehouse_tasks.show', compact('warehouseTask', 'operators'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_type' => ['required', 'in:put_away,replenishment,move'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'source_location_id' => ['required', 'different:destination_location_id', 'exists:storage_locations,id'],
            'destination_location_id' => ['required', 'exists:storage_locations,id'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'item_batch_id' => ['nullable', 'exists:item_batches,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $task = $this->tasks->create($validated, $request->user());
            return redirect()->route('inventory.warehouse-tasks.show', $task)->with('success', "Task {$task->task_number} created.");
        } catch (DomainException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()])->withInput();
        }
    }

    public function assign(Request $request, WarehouseTask $warehouseTask): RedirectResponse
    {
        $validated = $request->validate(['assigned_to_id' => ['required', 'exists:users,id']]);
        try {
            $this->tasks->assign($warehouseTask, User::findOrFail($validated['assigned_to_id']), $request->user());
            return back()->with('success', 'Task assignment updated.');
        } catch (DomainException $exception) {
            return back()->withErrors(['assignment' => $exception->getMessage()]);
        }
    }

    public function start(Request $request, WarehouseTask $warehouseTask): RedirectResponse
    {
        try {
            $this->tasks->start($warehouseTask, $request->user());
            return back()->with('success', 'Task started. Scan the prompted source, item, and destination in order.');
        } catch (DomainException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    public function scan(Request $request, WarehouseTask $warehouseTask): RedirectResponse
    {
        $validated = $request->validate([
            'scan_value' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $scan = $this->tasks->scan($warehouseTask, $validated['scan_value'], $request->user(), $validated['idempotency_key'] ?? null);
            return back()->with($scan->outcome === 'accepted' ? 'success' : 'error', $scan->message);
        } catch (DomainException $exception) {
            return back()->withErrors(['scan' => $exception->getMessage()]);
        }
    }

    public function complete(Request $request, WarehouseTask $warehouseTask): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $this->tasks->complete($warehouseTask, (int) $validated['quantity'], $request->user(), $validated['override_reason'] ?? null);
            return back()->with('success', 'Task quantity completed and the authoritative inventory ledger was updated where applicable.');
        } catch (DomainException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    public function cancel(Request $request, WarehouseTask $warehouseTask): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $this->tasks->cancel($warehouseTask, $validated['reason'], $request->user());
            return back()->with('success', 'Task cancelled with reason recorded.');
        } catch (DomainException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    public function resolveException(Request $request, WarehouseException $warehouseException): RedirectResponse
    {
        $validated = $request->validate(['resolution' => ['required', 'string', 'max:2000']]);
        try {
            $this->tasks->resolveException($warehouseException, $validated['resolution'], $request->user());
            return back()->with('success', 'Warehouse exception resolved.');
        } catch (DomainException $exception) {
            return back()->withErrors(['exception' => $exception->getMessage()]);
        }
    }

    public function printLabel(Request $request, WarehouseTask $warehouseTask): View
    {
        $validated = $request->validate(['copies' => ['required', 'integer', 'min:1', 'max:20']]);
        $label = $this->labels->create($warehouseTask, 'task_qr', (int) $validated['copies'], $request->user());
        $qrCode = $this->labels->qrDataUri($label->payload['code']);

        return view('inventory.warehouse_tasks.label', compact('label', 'qrCode'));
    }
}
