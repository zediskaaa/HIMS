<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\IoTTelemetryLog;
use App\Models\StorageLocation;
use App\Services\Warehouse\TelemetryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class TelemetryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly TelemetryService $telemetry,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['index']),
            new Middleware('can:'.Permission::ManageTelemetryExcursions->value, only: ['store', 'release']),
        ];
    }

    public function index(Request $request): View
    {
        $locations = StorageLocation::whereNotNull('temperature_classification')
            ->orderBy('code')
            ->get();

        $activeHolds = StorageLocation::where('excursion_hold', true)
            ->with(['stockLevels.item', 'telemetryLogs' => fn ($q) => $q->latest('id')->take(5)])
            ->get();

        $query = IoTTelemetryLog::with('location')->latest('id');

        if ($request->filled('location_id')) {
            $query->where('storage_location_id', $request->location_id);
        }
        if ($request->filled('status')) {
            $query->where('excursion_status', $request->status);
        }

        $logs = $query->paginate(25)->withQueryString();

        $sensorStats = $locations->map(function (StorageLocation $loc) {
            $latest = $loc->telemetryLogs()->latest('id')->first();
            $mkt = $this->telemetry->calculateMkt($loc, null, 24);

            return [
                'location' => $loc,
                'latest' => $latest,
                'mkt' => $mkt,
            ];
        });

        return view('inventory.warehousing.telemetry', compact('locations', 'activeHolds', 'logs', 'sensorStats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sensor_id' => ['required', 'string', 'max:60'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'temperature_celsius' => ['required', 'numeric', 'between:-100,60'],
            'relative_humidity_pct' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        try {
            $log = $this->telemetry->ingest($validated, $request->user());
            $msg = $log->excursion_status === 'excursion'
                ? "Telemetry recorded: TEMPERATURE EXCURSION DETECTED at {$log->temperature_celsius}°C! Location placed on EXCURSION_HOLD."
                : "Telemetry reading {$log->temperature_celsius}°C recorded successfully.";

            return back()->with($log->excursion_status === 'excursion' ? 'error' : 'success', $msg);
        } catch (DomainException $e) {
            return back()->withErrors(['telemetry' => $e->getMessage()]);
        }
    }

    public function release(Request $request, StorageLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $this->telemetry->releaseExcursionHold($location, $validated['justification'], $request->user());

            return back()->with('success', "Temperature excursion hold on {$location->code} has been successfully released.");
        } catch (DomainException $e) {
            return back()->withErrors(['release' => $e->getMessage()]);
        }
    }
}
