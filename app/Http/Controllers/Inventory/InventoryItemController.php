<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\UnitOfMeasure;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Rules\ProcurementEligibleSupplier;
use App\Services\AuditLogger;
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
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $audit,
    ) {}

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

    public function index(Request $request): View
    {
        $user = $request->user();
        $canManageItems = $user->can(Permission::ManageItems->value);
        $canViewSuppliers = $user->can(Permission::ViewSuppliers->value);

        // The stock condition is derived from the quantities, not read off
        // `status`, which carries the item's lifecycle. Keeping the accepted
        // states next to the query lets the filter take only a state the
        // catalogue can render; anything else is ignored rather than silently
        // emptying the table or erroring on a crafted query string.
        $stockStatuses = [
            'in_stock' => 'In Stock',
            'low_stock' => 'Low Stock',
            'out_of_stock' => 'Out Of Stock',
        ];

        $items = InventoryItem::query()
            ->where('status', '!=', 'archived')
            ->with(array_filter([
                $canViewSuppliers ? 'supplier' : null,
                'category',
                'defaultLocation',
            ]))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->trim().'%';

                $query->where(fn ($search) => $search
                    ->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('barcode_value', 'like', $term));
            })
            // Matches the derived condition the table badges render from, so a
            // filtered row always carries the state the filter promised.
            ->when(
                in_array($request->query('status'), array_keys($stockStatuses), true),
                fn ($query) => $query->stockStatus($request->query('status')),
            )
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('location_id'), function ($query) use ($request): void {
                $locationId = $request->integer('location_id');
                $query->where(function ($sub) use ($locationId): void {
                    $sub->where('default_location_id', $locationId)
                        ->orWhereHas('stockLevels', fn ($sl) => $sl->where('storage_location_id', $locationId)->where('quantity', '>', 0));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $eligibleSuppliers = $canManageItems
            ? Supplier::procurementEligible()->orderBy('name')->get()
            : collect();
        $unavailableSuppliers = $canManageItems
            ? Supplier::query()
                ->whereNotIn('id', $eligibleSuppliers->modelKeys())
                ->orderBy('name')
                ->get()
                ->each(function (Supplier $supplier): void {
                    $supplier->setAttribute('eligibility_reason', match (true) {
                        $supplier->status->value !== 'active' => $supplier->status->label(),
                        $supplier->effectiveAccreditationStatus()->value !== 'approved' => $supplier->effectiveAccreditationStatus()->label().' accreditation',
                        default => 'Compliance action required',
                    });
                })
            : collect();
        // Categories double as the catalogue's filter options, so every viewer
        // needs them; the create form they also feed stays behind ManageItems.
        $categories = ItemCategory::active()
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ItemCategory $category) => [$category->id => $category->fullPath()]);
        $filterLocations = StorageLocation::active()
            ->with('parent')
            ->orderBy('name')
            ->get();
        $locationOptions = $canManageItems
            ? StorageLocation::with('parent')
                ->orderBy('name')
                ->get()
                ->map(fn (StorageLocation $location) => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'type' => ucfirst(str_replace('_', ' ', (string) ($location->type ?? 'location'))),
                    'parent_name' => $location->parent?->name ?? '',
                    'full_path' => $location->fullPath(),
                    'is_active' => $location->status === 'active',
                    'status' => $location->status,
                    'display_label' => $location->displayOptionLabel(),
                ])
            : collect();
        $locations = $canManageItems
            ? StorageLocation::with('parent')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (StorageLocation $location) => [$location->id => $location->displayOptionLabel()])
            : collect();
        $unitOptions = $canManageItems
            ? UnitOfMeasure::optionsWithLegacy()
            : [];

        return view('inventory.items.index', [
            'categories' => $categories,
            'eligibleSuppliers' => $eligibleSuppliers,
            'filterLocations' => $filterLocations,
            'filters' => $request->only(['search', 'status', 'category_id', 'location_id']),
            'items' => $items,
            'locationOptions' => $locationOptions,
            'locations' => $locations,
            'stockStatuses' => $stockStatuses,
            'unavailableSuppliers' => $unavailableSuppliers,
            'unitOptions' => $unitOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:inventory_items'],
            'barcode_value' => ['nullable', 'string', 'max:100', 'unique:inventory_items,barcode_value'],
            'gtin' => ['nullable', 'digits_between:8,14', 'unique:inventory_items,gtin'],
            'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('is_active', true)],
            'unit' => ['required', 'string', Rule::in(UnitOfMeasure::allowedValuesWithLegacy())],
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
        ], [
            'unit.required' => 'The unit of measure field is required.',
            'unit.in' => 'The selected unit of measure is invalid. Please choose from the allowed units.',
            'default_location_id.required' => 'A storage location is required when recording opening stock.',
            'default_location_id.exists' => 'The selected storage location is invalid or inactive. Only active locations can be assigned.',
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
            } else {
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
            }

            $this->audit->log(
                AuditAction::CreatedInventoryItem,
                $request->user(),
                'Created an inventory item.',
                $item,
                $item->sku,
                newValues: Arr::only($item->getAttributes(), [
                    'sku', 'barcode_value', 'gtin', 'name', 'category_id', 'unit',
                    'is_batch_tracked', 'is_serial_tracked', 'is_expiry_tracked',
                    'unit_cost', 'reorder_level', 'status', 'supplier_id',
                    'default_location_id', 'quantity_on_hand',
                ]),
            );
        });

        return redirect()->route('inventory.items')->with('success', 'Inventory item created successfully.');
    }
}
