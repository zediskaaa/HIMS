<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Rules\ProcurementEligibleSupplier;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', new ProcurementEligibleSupplier],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'incoterms' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = (int) $validated['quantity'];
        $unitCost = (float) $validated['unit_cost'];
        $totalAmount = round($quantity * $unitCost, 2);

        $po = PurchaseOrder::create([
            ...$validated,
            'po_number' => 'PO-'.now()->format('YmdHis'),
            'total_amount' => $totalAmount,
            'total_encumbered_amount' => $totalAmount,
            'currency' => 'PHP',
            'exchange_rate' => 1.0,
            'payment_terms' => $validated['payment_terms'] ?? 'Net 30',
            'incoterms' => $validated['incoterms'] ?? 'DDP',
            'version' => 'PO-REV1',
            'revision_number' => 1,
            'status' => $validated['status'] ?? 'pending',
        ]);

        // Automatically create primary PO Line Item
        $po->lines()->create([
            'item_id' => $po->item_id,
            'line_number' => 1,
            'ordered_quantity' => $quantity,
            'received_quantity' => 0,
            'invoiced_quantity' => 0,
            'unit_price' => $unitCost,
            'total_line_amount' => $totalAmount,
            'line_status' => 'open',
        ]);

        $po->cxml_payload = app(POConversionService::class)->generateCxmlPayload($po);
        $po->save();

        $this->budgetService->convertSoftToHardEncumbrance($po);

        $this->auditService->record(
            auth()->user(),
            'PurchaseOrder',
            $po->id,
            'issued_purchase_order',
            null,
            ['po_number' => $po->po_number, 'amount' => $totalAmount]
        );

        return redirect()->route('inventory.purchases')->with('success', 'Purchase order created successfully.');
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
