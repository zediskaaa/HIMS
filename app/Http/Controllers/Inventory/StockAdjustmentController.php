<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Services\InventoryAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    /**
     * An adjustment is the one operation that creates or destroys stock with no
     * counterparty — no supplier delivered it, no ward received it. It is how a
     * miscount gets papered over, so it stays with the inventory manager rather
     * than the staff who did the counting.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::AdjustStock->value),
        ];
    }

    public function __construct(private readonly InventoryAutomationService $automationService) {}

    public function index(): View
    {
        $items = InventoryItem::latest()->get();
        $locations = StorageLocation::orderBy('name')->get();

        return view('inventory.adjustments.index', compact('items', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'adjustment_type' => ['required', 'in:increase,decrease,correction'],
            'quantity' => ['required', 'integer', 'min:0'],
            'location_id' => ['required', 'exists:storage_locations,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = (int) $validated['quantity'];
        $locationId = (int) $validated['location_id'];

        // An adjustment is recorded as a signed movement, so the three form
        // options all collapse to a delta. A correction states the count the
        // shelf should read, so its delta is the gap between that and what
        // the location currently holds.
        $delta = match ($validated['adjustment_type']) {
            'increase' => $quantity,
            'decrease' => -$quantity,
            'correction' => $quantity - $this->automationService->availableAt(
                (int) $validated['item_id'],
                $locationId
            ),
        };

        if ($delta === 0) {
            return redirect()->route('inventory.adjustments')
                ->with('info', 'No adjustment applied — the recorded count already matches.');
        }

        $this->automationService->recordMovement([
            'item_id' => $validated['item_id'],
            'movement_type' => MovementType::Adjustment,
            'quantity' => $delta,
            'to_location_id' => $locationId,
            'remarks' => $validated['reason'] ?? null,
        ], auth()->id());

        return redirect()->route('inventory.adjustments')->with('success', 'Stock adjustment applied successfully.');
    }
}
