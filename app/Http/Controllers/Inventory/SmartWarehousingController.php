<?php

namespace App\Http\Controllers\Inventory;

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
use App\Services\Warehouse\BarcodeService;
use App\Services\Warehouse\TelemetryService;
use App\Services\Warehouse\WarehouseTaskService;
use DomainException;
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
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['dashboard', 'locations']),
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
        $query = StorageLocation::with(['parent', 'children', 'stockLevels.item'])
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

        StorageLocation::create($validated);

        return back()->with('success', "Location {$validated['code']} created successfully.");
    }

    public function scanStation(): View
    {
        $activeTasks = WarehouseTask::with(['item', 'batch', 'sourceLocation', 'destinationLocation', 'assignedTo'])
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
