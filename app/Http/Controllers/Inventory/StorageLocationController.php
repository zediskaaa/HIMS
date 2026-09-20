<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use App\Models\WarehouseTask;
use App\Services\AuditLogger;
use App\Services\Warehouse\WarehouseLabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorageLocationController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly WarehouseLabelService $labels,
    ) {}

    /**
     * Warehouse staff need to see the zones they move stock between; defining
     * them is part of running the storeroom.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index']),
            new Middleware('can:'.Permission::ManageLocations->value, only: ['store']),
            new Middleware('super-admin', only: ['updateStatus']),
            new Middleware('can:'.Permission::PrintWarehouseLabels->value, only: ['printLabel']),
        ];
    }

    public function index(Request $request): View
    {
        $locations = StorageLocation::with('parent')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($q) => $q
                    ->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('zone', 'like', $term));
            })
            ->orderBy('sort_sequence')
            ->orderBy('code')
            ->get();
        $parentLocations = StorageLocation::active()->whereNotIn('type', ['bin', 'department'])->orderBy('code')->get();

        return view('inventory.storage_locations.index', compact('locations', 'parentLocations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:storage_locations'],
            'parent_id' => ['nullable', 'exists:storage_locations,id'],
            'type' => ['required', Rule::in(['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'level', 'bin', 'pharmacy', 'department'])],
            'description' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:100'],
            'storage_classification' => ['nullable', Rule::in(['general', 'medical_supply', 'pharmaceutical', 'sterile', 'cold_chain', 'hazardous', 'flammable', 'controlled'])],
            'temperature_classification' => ['nullable', Rule::in(['ambient', 'controlled_room', 'refrigerated', 'frozen', 'deep_frozen'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'capacity_unit' => ['required_with:capacity', Rule::in(['units', 'boxes', 'pallets'])],
            'status' => ['required', Rule::in(['active', 'blocked', 'inactive'])],
            'is_receiving_staging' => ['nullable', 'boolean'],
            'is_quarantine' => ['nullable', 'boolean'],
            'is_pick_face' => ['nullable', 'boolean'],
            'is_reserve' => ['nullable', 'boolean'],
            'is_dispatch_staging' => ['nullable', 'boolean'],
            'is_returns_area' => ['nullable', 'boolean'],
            'is_damaged_stock' => ['nullable', 'boolean'],
            'sort_sequence' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        if ($validated['type'] === 'warehouse' && ! empty($validated['parent_id'])) {
            throw ValidationException::withMessages(['parent_id' => ['A warehouse must be a root location.']]);
        }
        if ($validated['type'] !== 'warehouse' && empty($validated['parent_id']) && ! in_array($validated['type'], ['department', 'pharmacy'], true)) {
            throw ValidationException::withMessages(['parent_id' => ['Select a parent warehouse or location for this hierarchy level.']]);
        }

        $code = strtoupper($validated['code'] ?: 'LOC-'.substr((string) Str::ulid(), -10));
        $validated['code'] = $code;
        $validated['barcode_value'] = $code;
        foreach (['is_receiving_staging', 'is_quarantine', 'is_pick_face', 'is_reserve', 'is_dispatch_staging', 'is_returns_area', 'is_damaged_stock'] as $flag) {
            $validated[$flag] = (bool) ($validated[$flag] ?? false);
        }

        $location = StorageLocation::create($validated);
        $this->auditLogger->record(
            AuditAction::CreatedStorageLocation,
            actor: $request->user(),
            target: $location,
            description: "Created storage location {$location->code}",
            newValues: ['code' => $location->code, 'type' => $location->type, 'status' => $location->status],
        );

        return redirect()->route('inventory.storage-locations')->with('success', 'Storage location created successfully.');
    }

    public function updateStatus(Request $request, StorageLocation $storageLocation): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdministrator(), 403, 'Only Super Administrators can change storage location status.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'blocked', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $storageLocation->status;
        $newStatus = $validated['status'];
        $reason = $validated['reason'] ?? ($newStatus === 'active' ? 'Reactivated location' : 'Deactivated location');

        \Illuminate\Support\Facades\DB::transaction(function () use ($storageLocation, $newStatus, $oldStatus, $reason, $request) {
            $storageLocation->status = $newStatus;
            $storageLocation->save();

            $this->auditLogger->record(
                AuditAction::UpdatedStorageLocationStatus,
                actor: $request->user(),
                target: $storageLocation,
                description: "Changed {$storageLocation->code} from {$oldStatus} to {$storageLocation->status}: {$reason}",
                targetName: $storageLocation->code,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $storageLocation->status, 'reason' => $reason],
            );
        });

        $message = "Storage location {$storageLocation->code} ({$storageLocation->name}) is now {$storageLocation->status}.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'location' => [
                    'id' => $storageLocation->id,
                    'code' => $storageLocation->code,
                    'name' => $storageLocation->name,
                    'status' => $storageLocation->status,
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function printLabel(Request $request, StorageLocation $storageLocation): View
    {
        $validated = $request->validate(['copies' => ['nullable', 'integer', 'min:1', 'max:20']]);
        $copies = (int) ($validated['copies'] ?? 1);
        $label = $this->labels->create($storageLocation, 'location_qr', $copies, $request->user());
        $qrCode = $this->labels->qrDataUri($label->payload['code']);

        return view('inventory.warehouse_tasks.label', compact('label', 'qrCode'));
    }
}
