<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Procurement\ApprovalRoutingEngine;
use App\Services\Procurement\BudgetEncumbranceService;
use App\Services\Procurement\POConversionService;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Throwable;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    /**
     * Procurement and dock receiving use separate permissions. The legacy
     * receive URL leads to the GRN workflow and never posts inventory.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewProcurement->value, only: ['index']),
            new Middleware('can:'.Permission::IssuePurchaseOrder->value, only: ['store']),
            new Middleware('can:'.Permission::ApprovePurchaseOrder->value, only: ['revise', 'approve', 'reject']),
            new Middleware('can:'.Permission::ReceivePurchaseOrder->value, only: ['receive']),
        ];
    }

    public function __construct(
        private readonly BudgetEncumbranceService $budgetService,
        private readonly POConversionService $poConversionService,
        private readonly ProcurementAuditService $auditService,
        private readonly ApprovalRoutingEngine $approvalEngine
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

    /** Route the legacy PO action through dock receiving without posting stock. */
    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return redirect()->route('inventory.receiving.index', ['purchase_order_id' => $purchaseOrder->id]);
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

    /**
     * Approve a purchase order directly from the Purchase Order Pipeline.
     * Authorized for both Super Administrator and Inventory Manager.
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();

        // Segregation of duties: creators cannot approve their own order unless Super Administrator
        if ($purchaseOrder->created_by_user_id === $user->id && ! $user->isSuperAdministrator()) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['approval' => "Segregation of Duties Violation: Issuer cannot approve their own Purchase Order #{$purchaseOrder->po_number}."]);
        }

        $chain = $purchaseOrder->approvalChain;

        try {
            if ($chain && $chain->status === 'pending') {
                if ($user->isSuperAdministrator()) {
                    // Super Administrator possesses ultimate executive DOA authority to clear pending approval steps
                    while ($chain->currentPendingStep()) {
                        $this->approvalEngine->approveStep($chain, $user, 'Executive approval authorized by Super Administrator.');
                    }
                } else {
                    $this->approvalEngine->approveStep($chain, $user, 'Approved by Inventory Manager.');
                }
            } else {
                // Direct or legacy order without approval chain
                $oldStatus = $purchaseOrder->status;
                $purchaseOrder->status = PurchaseOrderStatus::Approved->value;
                $purchaseOrder->save();

                $this->auditService->record(
                    $user,
                    'PurchaseOrder',
                    $purchaseOrder->id,
                    'approved_purchase_order',
                    ['status' => $oldStatus],
                    ['status' => PurchaseOrderStatus::Approved->value]
                );
            }

            return redirect()->route('inventory.purchases')
                ->with('success', "Purchase Order {$purchaseOrder->po_number} approved successfully.");
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['approval' => $e->getMessage()]);
        }
    }

    /**
     * Reject a purchase order directly from the Purchase Order Pipeline.
     */
    public function reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $reason = $validated['rejection_reason'] ?: 'Rejected during pipeline review.';
        $chain = $purchaseOrder->approvalChain;

        try {
            if ($chain && $chain->status === 'pending') {
                $this->approvalEngine->rejectStep($chain, $user, $reason);
            } else {
                $oldStatus = $purchaseOrder->status;
                $purchaseOrder->status = PurchaseOrderStatus::Cancelled->value;
                $purchaseOrder->notes = trim(implode("\n", array_filter([
                    $purchaseOrder->notes,
                    "Approval rejected: {$reason}",
                ])));
                $purchaseOrder->save();

                $this->budgetService->releaseHardEncumbrance($purchaseOrder);

                $this->auditService->record(
                    $user,
                    'PurchaseOrder',
                    $purchaseOrder->id,
                    'rejected_purchase_order',
                    ['status' => $oldStatus],
                    ['status' => PurchaseOrderStatus::Cancelled->value, 'reason' => $reason]
                );
            }

            return redirect()->route('inventory.purchases')
                ->with('info', "Purchase Order {$purchaseOrder->po_number} has been rejected and funds released.");
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['approval' => $e->getMessage()]);
        }
    }
}
