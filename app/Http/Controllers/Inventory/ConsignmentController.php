<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\SurgicalConsignmentBillOnly;
use App\Services\Warehouse\ConsignmentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class ConsignmentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ConsignmentService $consignment,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewWarehouseTasks->value, only: ['index']),
            new Middleware('can:'.Permission::RecordConsignments->value, only: ['consume']),
        ];
    }

    public function index(Request $request): View
    {
        $consignmentItems = InventoryItem::where('is_consignment', true)->orderBy('name')->get();
        $consignmentLocations = StorageLocation::active()->orderBy('code')->get();

        $balances = ItemStockLevel::whereHas('item', fn ($q) => $q->where('is_consignment', true))
            ->with(['item', 'batch', 'location'])
            ->get();

        $query = SurgicalConsignmentBillOnly::with(['item', 'serial', 'batch', 'location', 'purchaseRequest', 'recordedBy'])
            ->latest('id');

        if ($request->filled('patient_encounter')) {
            $query->where('patient_encounter_id', 'like', "%{$request->patient_encounter}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->paginate(20)->withQueryString();

        return view('inventory.warehousing.consignment', compact('consignmentItems', 'consignmentLocations', 'balances', 'records'));
    }

    public function consume(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'item_batch_id' => ['nullable', 'exists:item_batches,id'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'patient_encounter_id' => ['required', 'string', 'max:100'],
            'operating_suite' => ['required', 'string', 'max:100'],
            'surgeon_name' => ['required', 'string', 'max:255'],
            'implanted_quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $record = $this->consignment->recordImplantUsage($validated, $request->user());

            return back()->with('success', "Consignment surgical implant consumed successfully for Patient Encounter {$record->patient_encounter_id}. Bill-Only Purchase Request generated.");
        } catch (DomainException $e) {
            return back()->withErrors(['consignment' => $e->getMessage()])->withInput();
        }
    }
}
