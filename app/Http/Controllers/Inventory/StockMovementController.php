<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryAutomationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller implements HasMiddleware
{
    /**
     * One route records every kind of movement, and the kinds are not equally
     * privileged — dispensing to a ward is pharmacy's job, receiving a delivery
     * is the warehouse's. A single `can:` on the route could only check the
     * widest of them, so store() authorises the submitted type itself and the
     * middleware here just establishes that the user records movements at all.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index']),
        ];
    }

    public function __construct(private readonly InventoryAutomationService $automationService) {}

    public function index(): View
    {
        // A transfer writes its source and destination rows inside one
        // transaction, so moved_at ties are common; id breaks the tie and keeps
        // the newest movement at the top.
        $movements = StockMovement::with(['item', 'batch', 'fromLocation', 'toLocation', 'user', 'reference'])
            ->latest('moved_at')
            ->latest('id')
            ->get();

        $items = InventoryItem::orderBy('name')->get();
        $locations = StorageLocation::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        // Stock lives per item/location/batch, and the service rejects an
        // outbound movement from a location that holds none. Surfacing the
        // balances here is what makes the From Location choice informed.
        $availability = ItemStockLevel::with(['item', 'location'])
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('item_id');

        // Only the types this user may actually record. Pharmacy staff see
        // Issuance and Stock Out; the warehouse also sees receiving and returns.
        // Inter-location transfers use the dedicated Initiate Stock Transfer workflow.
        $movementTypes = self::permittedTypes();

        $transferLocations = StorageLocation::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('zone')
                    ->orWhere('zone', '!=', 'In-Transit');
            })
            ->orderBy('name')
            ->get();

        $transferStockLevels = ItemStockLevel::query()
            ->where('quantity', '>', 0)
            ->select('item_id', 'storage_location_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as available_qty'))
            ->groupBy('item_id', 'storage_location_id')
            ->get();

        $locationStockMap = [];
        foreach ($transferStockLevels as $sl) {
            $locationStockMap[$sl->storage_location_id][$sl->item_id] = (int) $sl->available_qty;
        }

        $itemsData = $items->mapWithKeys(function ($item) use ($availability) {
            $levels = $availability->get($item->id, collect());
            $locStocks = [];
            foreach ($levels as $lvl) {
                $locStocks[$lvl->storage_location_id] = (int) $lvl->quantity;
            }
            return [$item->id => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit ?: 'unit',
                'quantity_on_hand' => (int) $item->quantity_on_hand,
                'location_stocks' => $locStocks,
            ]];
        });

        $availableUnits = $items->pluck('unit')
            ->filter()
            ->map(fn ($u) => trim($u))
            ->filter(fn ($u) => $u !== '')
            ->unique(fn ($u) => strtolower($u))
            ->values();

        $availableTransferItems = InventoryItem::where('quantity_on_hand', '>', 0)->orderBy('name')->get();

        return view('inventory.stock_movements.index', compact(
            'movements', 'items', 'locations', 'suppliers', 'availability', 'movementTypes',
            'transferLocations', 'locationStockMap', 'availableTransferItems', 'itemsData', 'availableUnits'
        ));
    }

    /**
     * The movement types this screen records.
     *
     * Inter-location transfers have their dedicated multi-line workflow.
     * Adjustments have their own screen in StockAdjustmentController.
     *
     * @return array<int, MovementType>
     */
    public static function recordableTypes(): array
    {
        return [
            MovementType::StockIn,
            MovementType::StockOut,
            MovementType::Issuance,
            MovementType::ReturnToSupplier,
        ];
    }

    /**
     * The subset of those the signed-in user holds the permission for.
     *
     * @return array<int, MovementType>
     */
    public static function permittedTypes(): array
    {
        $user = auth()->user();

        return array_values(array_filter(
            self::recordableTypes(),
            fn (MovementType $type) => $user !== null && $user->hasPermission($type->requiredPermission())
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'movement_type' => ['required', Rule::in(array_merge(
                array_map(fn (MovementType $type) => $type->value, self::recordableTypes()),
                [MovementType::Transfer->value]
            ))],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'from_location_id' => ['nullable', 'exists:storage_locations,id'],
            'to_location_id' => ['nullable', 'exists:storage_locations,id'],
            // Issuance and returns record their counterparty through the
            // movement's polymorphic reference, so no new columns are needed.
            'issued_to_location_id' => ['required_if:movement_type,issuance', 'nullable', 'exists:storage_locations,id'],
            'return_supplier_id' => ['required_if:movement_type,return_to_supplier', 'nullable', 'exists:suppliers,id'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'quantity.min' => 'Quantity must be at least 1 unit.',
            'quantity.max' => 'Quantity cannot exceed 999,999 units per transaction.',
            'issued_to_location_id.required_if' => 'Select the department or ward the stock was issued to.',
            'return_supplier_id.required_if' => 'Select the supplier the stock is being returned to.',
        ]);

        // Authorise the specific type, not the screen. Validation has already
        // confirmed it is a real recordable type, so this can only fail on
        // permission — a 403, which is what a forged form post deserves.
        Gate::authorize(
            MovementType::from($validated['movement_type'])->requiredPermission()->value
        );

        // recordMovement throws ValidationException for a missing location or
        // insufficient stock at the source. Laravel turns that into a redirect
        // back with the errors and the old input, which the view renders.
        $movements = $this->automationService->recordMovement(
            $validated,
            auth()->id(),
            $this->resolveReference($validated)
        );

        $first = $movements->first();
        $message = $movements->count() > 1
            ? sprintf('Recorded %s across %d batches (earliest expiry first).', $first->movement_type->label(), $movements->count())
            : sprintf('%s of %d recorded.', $first->movement_type->label(), $first->quantity);

        return redirect()->route('inventory.stock-movements')->with('success', $message);
    }

    /**
     * The counterparty a movement points at, if its type has one.
     *
     * An issuance names the ward or department that received the stock; a
     * return names the supplier it went back to. Both ride the existing
     * `reference_type`/`reference_id` morph rather than dedicated columns.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveReference(array $validated): ?Model
    {
        return match ($validated['movement_type']) {
            'issuance' => StorageLocation::find($validated['issued_to_location_id'] ?? null),
            'return_to_supplier' => Supplier::find($validated['return_supplier_id'] ?? null),
            default => null,
        };
    }
}
