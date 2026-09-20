<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\IoTTelemetryLog;
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
use App\Services\Warehouse\TelemetryService;
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
        private readonly TelemetryService $telemetry,
        private readonly AuditLogger $auditLogger,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['dashboard', 'locations', 'locationItems']),
            new Middleware('can:'.Permission::ExecuteWarehouseTasks->value, only: ['scanStation']),
            new Middleware('can:'.Permission::ManageWarehouseTopology->value, only: ['storeLocation']),
        ];
    }

    public function dashboard(): View
    {
        $metrics = [
            'open_tasks' => WarehouseTask::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'overdue_tasks' => WarehouseTask::whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
            'active_excursions' => StorageLocation::where('excursion_hold', true)->count(),
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

        $criticalSensors = StorageLocation::whereNotNull('temperature_classification')
            ->with(['telemetryLogs' => fn ($q) => $q->latest('id')->take(1)])
            ->take(6)
            ->get()
            ->map(function (StorageLocation $location) {
                $latest = $location->telemetryLogs->first();
                $mkt = $this->telemetry->calculateMkt($location, null, 24);

                return [
                    'location' => $location,
                    'latest_temp' => $latest?->temperature_celsius,
                    'latest_humidity' => $latest?->relative_humidity_pct,
                    'mkt' => $mkt,
                    'status' => $latest?->excursion_status ?? ($location->excursion_hold ? 'excursion' : 'normal'),
                    'hold' => $location->excursion_hold,
                ];
            });

        return view('inventory.warehousing.index', compact('metrics', 'recentTasks', 'recentScans', 'criticalSensors'));
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

        $recentScans = WarehouseScanEvent::with(['warehouseTask', 'scannedBy'])
            ->latest('id')
            ->take(10)
            ->get();

        return view('inventory.warehousing.scan_station', compact('activeTasks', 'recentScans'));
    }
}
