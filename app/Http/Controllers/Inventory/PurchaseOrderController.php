<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    /**
     * Raising a purchase order and receiving the delivery are separate jobs and
     * separate permissions: procurement commits the money, the warehouse counts
     * what arrives on the dock. Splitting them keeps a warehouse hand able to
     * book in a delivery without also being able to order stock.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ManageProcurement->value, only: ['index', 'store']),
            new Middleware('can:'.Permission::RecordMovements->value, only: ['receive']),
        ];
    }

    public function __construct(private readonly InventoryAutomationService $automationService) {}

    public function index(): View
    {
        $purchaseOrders = PurchaseOrder::with(['supplier', 'item'])->latest('requested_at')->get();
        $suppliers = Supplier::where('status', 'active')->get();
        $items = InventoryItem::all();

        return view('inventory.purchases.index', compact('purchaseOrders', 'suppliers', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = (int) $validated['quantity'];
        $unitCost = (float) $validated['unit_cost'];

        PurchaseOrder::create([
            ...$validated,
            'po_number' => 'PO-'.now()->format('YmdHis'),
            'total_amount' => round($quantity * $unitCost, 2),
        ]);

        return redirect()->route('inventory.purchases')->with('success', 'Purchase order created successfully.');
    }

    /**
     * Post a received purchase order into stock.
     *
     * This used to add the quantity straight onto `quantity_on_hand` and
     * record nothing else. That left `item_stock_levels` — which is what the
     * quantity is actually recomputed from — untouched, so the next stock
     * movement silently threw the receipt away. Routing through the service
     * writes the level row, the movement and the rollup in one transaction,
     * and re-evaluates the item's alerts on the way out.
     */
    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'received') {
            return redirect()->route('inventory.purchases')->with('info', 'This purchase order has already been received.');
        }

        $item = $purchaseOrder->item;

        // Goods have to land somewhere. Fall back to the item's default
        // location, then to any location at all, so a seeded demo PO can
        // still be received without the operator picking a bin.
        $locationId = $item->default_location_id ?? StorageLocation::query()->orderBy('id')->value('id');

        if ($locationId === null) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['receive' => 'No storage location exists to receive this order into.']);
        }

        DB::transaction(function () use ($purchaseOrder, $item, $locationId): void {
            $this->automationService->recordMovement([
                'item_id' => $item->id,
                'movement_type' => MovementType::StockIn,
                'quantity' => (int) $purchaseOrder->quantity,
                'to_location_id' => $locationId,
                'unit_cost' => $purchaseOrder->unit_cost ?? $item->unit_cost,
                'remarks' => 'Received against '.$purchaseOrder->po_number,
            ], auth()->id(), $purchaseOrder);

            $purchaseOrder->status = 'received';
            $purchaseOrder->received_at = now();
            $purchaseOrder->save();
        });

        return redirect()->route('inventory.purchases')->with('success', 'Goods received and inventory updated.');
    }
}
