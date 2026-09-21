<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\PdeaDangerousDrugsRegister;
use App\Models\StorageLocation;
use App\Models\SurgicalConsignmentBillOnly;
use App\Models\User;
use App\Models\WarehouseException;
use App\Models\WarehouseScanEvent;
use App\Models\WarehouseTask;
use App\Services\AuditLogger;
use App\Services\Warehouse\BarcodeService;
use App\Services\Warehouse\WarehouseTaskService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SmartWarehousingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly WarehouseTaskService $tasks,
        private readonly BarcodeService $barcodeService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['dashboard', 'locations', 'locationItems']),
            new Middleware('can:'.Permission::ExecuteWarehouseTasks->value, only: ['scanStation', 'lookupBarcode']),
            new Middleware('can:'.Permission::ManageWarehouseTopology->value, only: ['storeLocation']),
        ];
    }

    public function dashboard(): View
    {
        $metrics = [
            'open_tasks' => WarehouseTask::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'overdue_tasks' => WarehouseTask::whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
            'open_exceptions' => WarehouseException::where('status', 'open')->count(),
            'narcotics_items' => InventoryItem::where('regulatory_category', 'DANGEROUS_DRUG')->count(),
            'pending_bill_onlys' => SurgicalConsignmentBillOnly::where('status', 'pending_po')->count(),
            'total_locations' => StorageLocation::count(),
            'active_locations' => StorageLocation::where('status', 'active')->count(),
        ];

        $recentTasks = WarehouseTask::with(['item', 'sourceLocation', 'destinationLocation', 'assignedTo'])
            ->latest()
            ->take(10)
            ->get();

        $recentScans = WarehouseScanEvent::with(['warehouseTask', 'scannedBy'])
            ->latest('id')
            ->take(8)
            ->get();

        return view('inventory.warehousing.index', compact('metrics', 'recentTasks', 'recentScans'));
    }

    public function locations(Request $request): View
    {
        $query = StorageLocation::with(['parent', 'children'])
            ->withCount(['stockLevels as active_items_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum(['stockLevels as total_stock_quantity' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->orderBy('code');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('thermal')) {
            $query->where('temperature_classification', $request->thermal);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode_value', 'like', "%{$search}%");
            });
        }

        $locations = $query->paginate(25)->withQueryString();
        $locations->getCollection()->transform(function ($loc) {
            $loc->pending_inbound_count = $loc->pendingInboundCount();

            return $loc;
        });

        $parentLocations = StorageLocation::whereIn('type', ['warehouse', 'zone', 'aisle', 'rack'])->orderBy('name')->get();

        return view('inventory.warehousing.locations', compact('locations', 'parentLocations'));
    }

    public function locationItems(Request $request, StorageLocation $storageLocation): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can(Permission::ViewWarehouseTasks->value) || $user->can(Permission::ViewInventory->value)),
            403,
            'Unauthorized to view storage location items.'
        );

        $query = ItemStockLevel::where('storage_location_id', $storageLocation->id)
            ->where('quantity', '>', 0)
            ->with(['item.category', 'batch']);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($iq) use ($search) {
                    $iq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode_value', 'like', "%{$search}%");
                })->orWhereHas('batch', function ($bq) use ($search) {
                    $bq->where('batch_number', 'like', "%{$search}%")
                        ->orWhere('lot_number', 'like', "%{$search}%");
                });
            });
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 15)));
        $paginated = $query->paginate($perPage);

        $items = $paginated->getCollection()->map(function (ItemStockLevel $stockLevel) {
            $item = $stockLevel->item;
            $batch = $stockLevel->batch;

            $status = $item ? $item->stockStatus() : 'in_stock';
            $statusLabel = match ($status) {
                'out_of_stock' => 'Out of Stock',
                'low_stock' => 'Low Stock',
                default => 'In Stock',
            };

            return [
                'id' => $stockLevel->id,
                'item_id' => $item?->id,
                'name' => $item?->name ?? 'Unknown Item',
                'sku' => $item?->sku ?? '—',
                'barcode' => $item?->barcode_value,
                'category' => $item?->category?->name ?? 'General',
                'quantity' => (int) $stockLevel->quantity,
                'reserved_quantity' => (int) $stockLevel->reserved_quantity,
                'available_quantity' => (int) $stockLevel->availableQuantity(),
                'unit' => $item?->unit ?? 'units',
                'batch_number' => $batch?->batch_number,
                'lot_number' => $batch?->lot_number,
                'expiry_date' => $batch?->expiry_date?->toDateString(),
                'expiry_formatted' => $batch?->expiry_date?->format('M Y'),
                'days_until_expiry' => $batch?->daysUntilExpiry(),
                'is_expired' => $batch ? $batch->isExpired() : false,
                'is_expiring_soon' => $batch ? $batch->isExpiringSoon() : false,
                'stock_status' => $status,
                'status_label' => $statusLabel,
            ];
        });

        return response()->json([
            'location' => [
                'id' => $storageLocation->id,
                'code' => $storageLocation->code,
                'name' => $storageLocation->name,
                'type' => str_replace('_', ' ', $storageLocation->type),
                'full_path' => $storageLocation->fullPath(),
                'total_quantity' => $storageLocation->totalQuantity(),
                'active_items_count' => (int) $storageLocation->stockLevels()->where('quantity', '>', 0)->count(),
            ],
            'items' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:storage_locations,code'],
            'barcode_value' => ['nullable', 'string', 'max:100', 'unique:storage_locations,barcode_value'],
            'parent_id' => ['nullable', 'exists:storage_locations,id'],
            'type' => ['required', 'string', 'in:warehouse,zone,aisle,rack,shelf,bin,virtual,cold_room,vault'],
            'description' => ['nullable', 'string', 'max:500'],
            'zone' => ['nullable', 'string', 'max:50'],
            'aisle' => ['nullable', 'string', 'max:30'],
            'rack' => ['nullable', 'string', 'max:30'],
            'shelf' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'storage_classification' => ['nullable', 'string', 'max:40'],
            'temperature_classification' => ['nullable', 'string', 'in:ambient,refrigerated,frozen,ultra_cold'],
            'is_receiving_staging' => ['boolean'],
            'is_quarantine' => ['boolean'],
            'is_pick_face' => ['boolean'],
            'is_reserve' => ['boolean'],
            'is_dispatch_staging' => ['boolean'],
            'is_narcotics_vault' => ['boolean'],
            'is_hazardous_containment' => ['boolean'],
        ]);

        $validated['barcode_value'] = $validated['barcode_value'] ?? $validated['code'];
        $validated['status'] = 'active';

        $location = StorageLocation::create($validated);

        $this->auditLogger->record(
            AuditAction::CreatedStorageLocation,
            actor: $request->user(),
            target: $location,
            description: "Created storage location {$location->code}",
            targetName: $location->code,
            newValues: ['code' => $location->code, 'type' => $location->type, 'status' => $location->status],
        );

        return back()->with('success', "Location {$validated['code']} created successfully.");
    }

    public function scanStation(): View
    {
        $activeTasks = WarehouseTask::with(['item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo', 'scans'])
            ->whereIn('status', ['ready', 'assigned', 'in_progress', 'partially_completed'])
            ->orderBy('priority')
            ->latest()
            ->take(15)
            ->get();

        $recentTasks = WarehouseTask::with(['item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo'])
            ->latest()
            ->take(6)
            ->get();

        $recentScans = WarehouseScanEvent::with(['warehouseTask', 'scannedBy'])
            ->latest('id')
            ->take(10)
            ->get();

        $workstationMetrics = [
            'active_tasks' => $activeTasks->count(),
            'completed_today' => WarehouseTask::where('status', 'completed')->whereDate('completed_at', today())->count(),
            'total_scans_today' => WarehouseScanEvent::whereDate('created_at', today())->count(),
            'open_exceptions' => WarehouseException::where('status', 'open')->count(),
        ];

        return view('inventory.warehousing.scan_station', compact('activeTasks', 'recentTasks', 'recentScans', 'workstationMetrics'));
    }

    /**
     * Resolve scanned barcode, QR code, or manually entered identifier (Item, Location, Task, Batch)
     * using the central BarcodeService, strictly enforcing authorization and business rules
     * (e.g. inactive storage locations cannot accept new stock).
     */
    public function lookupBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'operation' => ['nullable', 'string', 'in:lookup,receive,inbound,dispatch,pick,transfer'],
        ]);

        $raw = trim($validated['barcode']);
        $operation = $validated['operation'] ?? 'lookup';

        $resolved = $this->barcodeService->parseAndResolve($raw);

        if ($resolved['resolved_type'] === null) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'raw' => $raw,
                'message' => "No matching item, storage location, or warehouse task found for identifier [{$raw}].",
            ], 404);
        }

        if ($resolved['resolved_type'] === 'location') {
            $location = StorageLocation::find($resolved['resolved_id']);
            if (! $location) {
                return response()->json([
                    'success' => false,
                    'status' => 'not_found',
                    'raw' => $raw,
                    'message' => "Location with ID {$resolved['resolved_id']} was not found.",
                ], 404);
            }

            $isInactive = $location->status === 'inactive';

            // Respect warehouse business rules:
            // Inactive locations cannot receive new stock or inbound assignments
            if ($isInactive && in_array($operation, ['receive', 'inbound'], true)) {
                return response()->json([
                    'success' => false,
                    'status' => 'inactive_location_blocked',
                    'type' => 'location',
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                    'location_status' => 'inactive',
                    'message' => "Storage location [{$location->code}] is INACTIVE. Inbound receiving and new stock assignments into inactive storage are prohibited.",
                ], 422);
            }

            return response()->json([
                'success' => true,
                'status' => $isInactive ? 'location_inactive' : 'location_active',
                'type' => 'location',
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'location_status' => $location->status,
                'is_active' => ! $isInactive,
                'warning' => $isInactive ? 'This storage location is INACTIVE. Inbound receiving and new stock assignments are prohibited. Existing inventory may be transferred or released.' : null,
                'redirect_url' => route('inventory.warehousing.locations.items', $location),
                'message' => "Storage location [{$location->code}] ({$location->name}) identified." . ($isInactive ? ' NOTE: Location is inactive.' : ''),
            ]);
        }

        if ($resolved['resolved_type'] === 'item') {
            $item = InventoryItem::find($resolved['resolved_id']);
            if (! $item) {
                return response()->json([
                    'success' => false,
                    'status' => 'not_found',
                    'raw' => $raw,
                    'message' => "Item with ID {$resolved['resolved_id']} was not found.",
                ], 404);
            }

            $isArchived = $item->status === 'archived' || $item->archived_at !== null;

            return response()->json([
                'success' => true,
                'status' => $isArchived ? 'item_archived' : 'item_active',
                'type' => 'item',
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'item_status' => $item->status,
                'is_active' => ! $isArchived,
                'warning' => $isArchived ? 'This item is ARCHIVED. It is retained for historical records but cannot receive new transactions.' : null,
                'redirect_url' => route('inventory.items', ['search' => $item->sku]),
                'message' => "Item [{$item->sku}] ({$item->name}) identified." . ($isArchived ? ' NOTE: Item is archived.' : ''),
            ]);
        }

        if ($resolved['resolved_type'] === 'task') {
            $task = WarehouseTask::find($resolved['resolved_id']);
            if (! $task) {
                return response()->json([
                    'success' => false,
                    'status' => 'not_found',
                    'raw' => $raw,
                    'message' => "Warehouse task with ID {$resolved['resolved_id']} was not found.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 'task_found',
                'type' => 'task',
                'id' => $task->id,
                'task_number' => $task->task_number,
                'task_status' => $task->status->value,
                'redirect_url' => route('inventory.warehouse-tasks.show', $task),
                'message' => "Warehouse task [{$task->task_number}] identified.",
            ]);
        }

        if ($resolved['resolved_type'] === 'batch') {
            $batch = ItemBatch::with('item')->find($resolved['resolved_id']);
            if (! $batch) {
                return response()->json([
                    'success' => false,
                    'status' => 'not_found',
                    'raw' => $raw,
                    'message' => "Batch with ID {$resolved['resolved_id']} was not found.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 'batch_found',
                'type' => 'batch',
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'item_sku' => $batch->item?->sku,
                'redirect_url' => $batch->item ? route('inventory.items', ['search' => $batch->item->sku]) : null,
                'message' => "Item batch [{$batch->batch_number}] identified.",
            ]);
        }

        return response()->json([
            'success' => false,
            'status' => 'unsupported_type',
            'raw' => $raw,
            'message' => "Identifier [{$raw}] resolved to an unsupported type [{$resolved['resolved_type']}].",
        ], 422);
    }
}
