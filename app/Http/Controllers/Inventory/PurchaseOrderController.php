<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryAutomationService;
use App\Services\Procurement\BudgetEncumbranceService;
use App\Services\Procurement\POConversionService;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

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
            new Middleware('can:'.Permission::ViewProcurement->value, only: ['index']),
            new Middleware('can:'.Permission::IssuePurchaseOrder->value, only: ['store']),
            new Middleware('can:'.Permission::ApprovePurchaseOrder->value, only: ['revise']),
            new Middleware('can:'.Permission::ReceivePurchaseOrder->value, only: ['receive']),
        ];
    }

    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly BudgetEncumbranceService $budgetService,
        private readonly POConversionService $poConversionService,
        private readonly ProcurementAuditService $auditService
    ) {}

    public function index(): View
    {
        $purchaseOrders = PurchaseOrder::with(['supplier', 'item', 'lines.item', 'revisions'])
            ->latest('requested_at')
            ->get();
        $suppliers = Supplier::procurementEligible()->orderBy('name')->get();
        $items = InventoryItem::all();

        return view('inventory.purchases.index', compact('purchaseOrders', 'suppliers', 'items'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        try {
            $po = $this->poConversionService->createDirectPurchaseOrder(
                $request->validated(),
                $request->user(),
            );

            return redirect()->route('inventory.purchases')
                ->with('success', "Purchase order {$po->po_number} created from trusted catalog pricing.")
                ->with('new_po_id', $po->id);
        } catch (DomainException $exception) {
            return redirect()->route('inventory.purchases')
                ->withInput()
                ->withErrors(['purchase_order' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('inventory.purchases')
                ->withInput()
                ->withErrors(['purchase_order' => 'The purchase order could not be created. No records were saved.']);
        }
    }

    /**
     * Post a received purchase order into stock.
     * Supports both multi-line enterprise orders and legacy orders.
     */
    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'received' || $purchaseOrder->status === PurchaseOrderStatus::Fulfilled->value) {
            return redirect()->route('inventory.purchases')->with('info', 'This purchase order has already been received.');
        }

        $status = PurchaseOrderStatus::tryFrom((string) $purchaseOrder->status);

        if (! ($status?->canReceiveStock() ?? in_array($purchaseOrder->status, ['approved', 'dispatched', 'acknowledged', 'partially_fulfilled', 'partially_received', 'issued'], true))) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['receive' => 'This purchase order must be approved before stock can be received.']);
        }

        $fallbackLocationId = StorageLocation::query()->orderBy('id')->value('id');

        if ($fallbackLocationId === null) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['receive' => 'No storage location exists to receive this order into.']);
        }

        DB::transaction(function () use ($purchaseOrder, $fallbackLocationId): void {
            if ($purchaseOrder->lines()->exists()) {
                foreach ($purchaseOrder->lines as $line) {
                    $item = $line->item;
                    $locId = $item->default_location_id ?? $fallbackLocationId;
                    $qtyToReceive = $line->remainingQuantity() > 0 ? $line->remainingQuantity() : $line->ordered_quantity;

                    $this->automationService->recordMovement([
                        'item_id' => $item->id,
                        'movement_type' => MovementType::StockIn,
                        'quantity' => $qtyToReceive,
                        'to_location_id' => $locId,
                        'unit_cost' => $line->unit_price ?? $item->unit_cost,
                        'remarks' => "Received against {$purchaseOrder->po_number} Line #{$line->line_number}",
                    ], auth()->id(), $purchaseOrder);

                    $line->received_quantity += $qtyToReceive;
                    $line->line_status = 'received';
                    $line->save();
                }
            } else {
                // Legacy single-item fallback
                $item = $purchaseOrder->item;
                $locId = $item->default_location_id ?? $fallbackLocationId;

                $this->automationService->recordMovement([
                    'item_id' => $item->id,
                    'movement_type' => MovementType::StockIn,
                    'quantity' => (int) $purchaseOrder->quantity,
                    'to_location_id' => $locId,
                    'unit_cost' => $purchaseOrder->unit_cost ?? $item->unit_cost,
                    'remarks' => 'Received against '.$purchaseOrder->po_number,
                ], auth()->id(), $purchaseOrder);
            }

            $purchaseOrder->status = 'received';
            $purchaseOrder->received_at = now();
            $purchaseOrder->save();

            // Record fulfillment in budget
            $this->budgetService->recordFulfillmentSpent($purchaseOrder, (float) $purchaseOrder->total_amount);

            $this->auditService->record(
                auth()->user(),
                'PurchaseOrder',
                $purchaseOrder->id,
                'received_purchase_order',
                null,
                ['po_number' => $purchaseOrder->po_number, 'received_at' => now()->toIso8601String()]
            );
        });

        return redirect()->route('inventory.purchases')->with('success', 'Goods received and inventory updated.');
    }

    /**
     * Submit a Purchase Order Revision / Change Order.
     */
    public function revise(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'justification' => ['required', 'string', 'max:500'],
        ]);

        $newLines = [
            [
                'item_id' => $purchaseOrder->item_id ?? $purchaseOrder->lines()->first()?->item_id,
                'ordered_quantity' => (int) $validated['quantity'],
                'unit_price' => (float) $validated['unit_cost'],
            ],
        ];

        try {
            $revision = $this->poConversionService->submitPoRevision(
                $purchaseOrder,
                $newLines,
                $validated['justification'],
                auth()->user()
            );

            $this->auditService->record(
                auth()->user(),
                'PurchaseOrder',
                $purchaseOrder->id,
                'amended_purchase_order',
                null,
                [
                    'revision_code' => $revision->change_order_code,
                    'variance_percentage' => $revision->variance_percentage,
                    'requires_doa' => $revision->requires_doa_reapproval,
                ]
            );

            $msg = $revision->requires_doa_reapproval
                ? "PO Change Order {$revision->change_order_code} submitted. Financial variance ({$revision->variance_percentage}%) exceeds 5% DOA policy threshold and requires re-approval."
                : "PO Change Order {$revision->change_order_code} applied successfully.";

            return redirect()->route('inventory.purchases')->with('success', $msg);
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['revise' => $e->getMessage()]);
        }
    }
}
