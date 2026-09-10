<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Rules\ProcurementEligibleSupplier;
use App\Services\InventoryAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryItemController extends Controller implements HasMiddleware
{
    public function __construct(private readonly InventoryAutomationService $automationService) {}

    /**
     * Reading the item list and editing the item master are different jobs.
     * Every department needs to look up an item; only the inventory manager
     * may create or change one.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index']),
            new Middleware('can:'.Permission::ManageItems->value, only: ['store']),
        ];
    }

    public function index(): View
    {
        $items = InventoryItem::with(['supplier', 'category', 'defaultLocation'])->latest()->get();
        $eligibleSuppliers = Supplier::procurementEligible()->orderBy('name')->get();
        $unavailableSuppliers = Supplier::query()
            ->whereNotIn('id', $eligibleSuppliers->modelKeys())
            ->orderBy('name')
            ->get()
            ->each(function (Supplier $supplier): void {
                $supplier->setAttribute('eligibility_reason', match (true) {
                    $supplier->status->value !== 'active' => $supplier->status->label(),
                    $supplier->effectiveAccreditationStatus()->value !== 'approved' => $supplier->effectiveAccreditationStatus()->label().' accreditation',
                    default => 'Compliance action required',
                });
            });
        $categories = ItemCategory::active()
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ItemCategory $category) => [$category->id => $category->fullPath()]);
        $locations = StorageLocation::active()
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (StorageLocation $location) => [$location->id => $location->fullPath()]);
        $unitOptions = InventoryItem::query()
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->distinct()
            ->orderBy('unit')
            ->pluck('unit');

        return view('inventory.items.index', compact(
            'categories',
            'eligibleSuppliers',
            'items',
            'locations',
            'unavailableSuppliers',
            'unitOptions',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:inventory_items'],
            'barcode_value' => ['nullable', 'string', 'max:100', 'unique:inventory_items,barcode_value'],
            'gtin' => ['nullable', 'digits_between:8,14', 'unique:inventory_items,gtin'],
            'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('is_active', true)],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_batch_tracked' => ['sometimes', 'boolean'],
            'is_serial_tracked' => ['sometimes', 'boolean'],
            'is_expiry_tracked' => ['sometimes', 'boolean'],
            'storage_classification' => ['nullable', Rule::in(['general', 'medical_supply', 'pharmaceutical', 'sterile', 'cold_chain', 'hazardous', 'flammable', 'controlled'])],
            'temperature_classification' => ['nullable', Rule::in(['ambient', 'controlled_room', 'refrigerated', 'frozen', 'deep_frozen'])],
            'pick_face_minimum' => ['nullable', 'integer', 'min:0'],
            'pick_face_maximum' => ['nullable', 'integer', 'min:0', 'gte:pick_face_minimum'],
            'initial_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'expiry_alert_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'supplier_id' => ['nullable', new ProcurementEligibleSupplier],
            'default_location_id' => [
                Rule::requiredIf(fn () => (int) $request->input('initial_quantity', 0) > 0),
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where('status', 'active'),
            ],
            'batch_number' => [
                Rule::requiredIf(fn () => (int) $request->input('initial_quantity', 0) > 0 && $request->boolean('is_batch_tracked')),
                'nullable',
                'string',
                'max:255',
            ],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if (! $request->boolean('is_batch_tracked') && ($request->boolean('is_expiry_tracked') || filled($validated['expiry_date'] ?? null))) {
            throw ValidationException::withMessages([
                'expiry_date' => 'An expiry date requires batch or lot tracking.',
            ]);
        }
        if ($request->boolean('is_serial_tracked') && (int) ($validated['initial_quantity'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'initial_quantity' => 'Create serialized stock through receiving so each unit receives a verified serial number.',
            ]);
        }
        if ($request->boolean('is_expiry_tracked') && (int) ($validated['initial_quantity'] ?? 0) > 0 && blank($validated['expiry_date'] ?? null)) {
            throw ValidationException::withMessages([
                'expiry_date' => 'An expiry date is required for opening stock of an expiry-tracked item.',
            ]);
        }

        DB::transaction(function () use ($request, $validated): void {
            $initialQuantity = (int) ($validated['initial_quantity'] ?? 0);
            $item = InventoryItem::create([
                ...Arr::except($validated, ['initial_quantity', 'batch_number', 'expiry_date']),
                'is_batch_tracked' => $request->boolean('is_batch_tracked'),
                'is_serial_tracked' => $request->boolean('is_serial_tracked'),
                'is_expiry_tracked' => $request->boolean('is_expiry_tracked'),
                'quantity_on_hand' => 0,
                'reserved_quantity' => 0,
                'total_value' => 0,
            ]);

            if ($initialQuantity === 0) {
                $this->automationService->syncItemTotals($item);

                return;
            }

            $batch = $item->is_batch_tracked
                ? ItemBatch::create([
                    'item_id' => $item->id,
                    'batch_number' => $validated['batch_number'],
                    'expiry_date' => $validated['expiry_date'] ?? null,
                    'received_at' => today(),
                    'unit_cost' => $validated['unit_cost'] ?? 0,
                    'initial_quantity' => $initialQuantity,
                    'status' => 'active',
                ])
                : null;

            $this->automationService->recordMovement([
                'item_id' => $item->id,
                'movement_type' => MovementType::StockIn,
                'quantity' => $initialQuantity,
                'to_location_id' => $validated['default_location_id'],
                'item_batch_id' => $batch?->id,
                'unit_cost' => $validated['unit_cost'] ?? 0,
                'remarks' => 'Opening balance recorded during item creation.',
            ], $request->user()->id);
        });

        return redirect()->route('inventory.items')->with('success', 'Inventory item created successfully.');
    }
}
