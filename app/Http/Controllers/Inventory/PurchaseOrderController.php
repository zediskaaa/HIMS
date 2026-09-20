<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Carbon\Carbon;
use App\Services\InventoryAutomationService;
use App\Services\Procurement\ApprovalRoutingEngine;
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
            new Middleware('can:'.Permission::ApprovePurchaseOrder->value, only: ['revise', 'approve', 'reject']),
            new Middleware('can:'.Permission::ReceivePurchaseOrder->value, only: ['receive']),
        ];
    }

    public function __construct(
        private readonly InventoryAutomationService $automationService,
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

        $requestedLocId = request('location_id') ?? request('storage_location_id');
        $fallbackLocationId = StorageLocation::active()->orderBy('id')->value('id');

        // Check if explicit location was passed
        if ($requestedLocId) {
            $explicitLoc = StorageLocation::find($requestedLocId);
            if (! $explicitLoc || $explicitLoc->status !== 'active') {
                if (request()->expectsJson()) {
                    return response()->json([
                        'message' => 'This location is inactive and cannot receive new inventory. Select an active location.',
                        'errors' => ['receive' => ['This location is inactive and cannot receive new inventory. Select an active location.']],
                    ], 422);
                }

                return redirect()->route('inventory.purchases')
                    ->withErrors(['receive' => 'This location is inactive and cannot receive new inventory. Select an active location.']);
            }
        }

        // Validate destinations for line items or single item
        $checkLocations = collect();
        if ($purchaseOrder->lines()->exists()) {
            foreach ($purchaseOrder->lines as $line) {
                $targetId = $requestedLocId ?? $line->item?->default_location_id ?? $line->item?->stockLevels()->value('storage_location_id') ?? $fallbackLocationId;
                if ($targetId) {
                    $checkLocations->push($targetId);
                }
            }
        } else {
            $targetId = $requestedLocId ?? $purchaseOrder->item?->default_location_id ?? $purchaseOrder->item?->stockLevels()->value('storage_location_id') ?? $fallbackLocationId;
            if ($targetId) {
                $checkLocations->push($targetId);
            }
        }

        foreach ($checkLocations->unique() as $targetLocId) {
            $loc = StorageLocation::find($targetLocId);
            if (! $loc || $loc->status !== 'active') {
                if (request()->expectsJson()) {
                    return response()->json([
                        'message' => 'This location is inactive and cannot receive new inventory. Select an active location.',
                        'errors' => ['receive' => ['This location is inactive and cannot receive new inventory. Select an active location.']],
                    ], 422);
                }

                return redirect()->route('inventory.purchases')
                    ->withErrors(['receive' => 'This location is inactive and cannot receive new inventory. Select an active location.']);
            }
        }

        if ($fallbackLocationId === null && $checkLocations->isEmpty()) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['receive' => 'No storage location exists to receive this order into.']);
        }

        DB::transaction(function () use ($purchaseOrder, $fallbackLocationId, $requestedLocId): void {
            $po = PurchaseOrder::lockForUpdate()->with(['lines.item', 'item'])->findOrFail($purchaseOrder->id);

            if ($po->isFullyReceived() || $po->status === 'received' || $po->status === PurchaseOrderStatus::Fulfilled->value) {
                return;
            }

            if ($po->lines()->exists()) {
                foreach ($po->lines as $line) {
                    $item = $line->item;
                    $locId = $requestedLocId
                        ?? $item->default_location_id
                        ?? $item->stockLevels()->value('storage_location_id')
                        ?? $fallbackLocationId;
                    $item->ensureStockLevelExists($locId);

                    $qtyToReceive = $line->remainingQuantity() > 0 ? $line->remainingQuantity() : $line->ordered_quantity;
                    if ($qtyToReceive <= 0) {
                        continue;
                    }

                    $conversionFactor = $line->conversionFactor();
                    if ($conversionFactor <= 1.0 && filled($line->purchase_unit)) {
                        $conversionFactor = $item->conversionFactorFor($line->purchase_unit);
                    }
                    $baseQtyToReceive = (int) round($qtyToReceive * $conversionFactor);

                    $pUnit = $line->purchase_unit ?: $item->unit ?: 'unit';
                    $bUnit = $item->unit ?: 'unit';
                    $unitDisplay = $conversionFactor > 1.0
                        ? "{$qtyToReceive} {$pUnit} ({$baseQtyToReceive} {$bUnit})"
                        : "{$baseQtyToReceive} {$bUnit}";

                    $batch = null;
                    $batchNumber = request('batch_number');
                    if (empty($batchNumber) && ($item->is_batch_tracked || request()->has('batch_number'))) {
                        $batchNumber = 'LOT-'.$po->po_number.($po->lines()->count() > 1 ? '-L'.$line->line_number : '');
                    }

                    if ($batchNumber) {
                        $receivedDate = request('received_at') ? Carbon::parse(request('received_at'))->toDateString() : now()->toDateString();
                        $batch = ItemBatch::firstOrCreate(
                            ['item_id' => $item->id, 'batch_number' => $batchNumber],
                            [
                                'received_at' => $receivedDate,
                                'expiry_date' => request('expiry_date') ? Carbon::parse(request('expiry_date')) : null,
                                'unit_cost' => ($line->unit_price && $conversionFactor > 0) ? round($line->unit_price / $conversionFactor, 4) : $item->unit_cost,
                                'initial_quantity' => $baseQtyToReceive,
                                'status' => 'active',
                            ]
                        );
                    }

                    $this->automationService->recordMovement([
                        'item_id' => $item->id,
                        'movement_type' => MovementType::StockIn,
                        'quantity' => $baseQtyToReceive,
                        'to_location_id' => $locId,
                        'item_batch_id' => $batch?->id,
                        'unit_cost' => ($line->unit_price && $conversionFactor > 0) ? round($line->unit_price / $conversionFactor, 4) : $item->unit_cost,
                        'remarks' => "Received {$unitDisplay} against {$po->po_number} Line #{$line->line_number}",
                    ], auth()->id(), $po);

                    $line->received_quantity += $qtyToReceive;
                    $line->line_status = $line->remainingQuantity() === 0 ? 'received' : 'partially_received';
                    $line->save();
                }
            } else {
                // Legacy single-item fallback
                $item = $po->item;
                $locId = $requestedLocId
                    ?? $item->default_location_id
                    ?? $item->stockLevels()->value('storage_location_id')
                    ?? $fallbackLocationId;
                $item->ensureStockLevelExists($locId);

                $qtyToReceive = (int) $po->quantity;
                $conversionFactor = $po->conversionFactor();
                if ($conversionFactor <= 1.0 && filled($po->purchase_unit)) {
                    $conversionFactor = $item->conversionFactorFor($po->purchase_unit);
                }
                $baseQtyToReceive = (int) round($qtyToReceive * $conversionFactor);

                $pUnit = $po->purchase_unit ?: $item->unit ?: 'unit';
                $bUnit = $item->unit ?: 'unit';
                $unitDisplay = $conversionFactor > 1.0
                    ? "{$qtyToReceive} {$pUnit} ({$baseQtyToReceive} {$bUnit})"
                    : "{$baseQtyToReceive} {$bUnit}";

                $batch = null;
                if (request()->filled('batch_number')) {
                    $batchNumber = (string) request('batch_number');
                    $receivedDate = request('received_at') ? Carbon::parse(request('received_at'))->toDateString() : now()->toDateString();
                    $batch = ItemBatch::firstOrCreate(
                        ['item_id' => $item->id, 'batch_number' => $batchNumber],
                        [
                            'received_at' => $receivedDate,
                            'expiry_date' => request('expiry_date') ? Carbon::parse(request('expiry_date')) : null,
                            'unit_cost' => ($po->unit_cost && $conversionFactor > 0) ? round($po->unit_cost / $conversionFactor, 4) : $item->unit_cost,
                            'initial_quantity' => $baseQtyToReceive,
                            'status' => 'active',
                        ]
                    );
                }

                $this->automationService->recordMovement([
                    'item_id' => $item->id,
                    'movement_type' => MovementType::StockIn,
                    'quantity' => $baseQtyToReceive,
                    'to_location_id' => $locId,
                    'item_batch_id' => $batch?->id,
                    'unit_cost' => ($po->unit_cost && $conversionFactor > 0) ? round($po->unit_cost / $conversionFactor, 4) : $item->unit_cost,
                    'remarks' => "Received {$unitDisplay} against {$po->po_number}",
                ], auth()->id(), $po);
            }

            $po->status = PurchaseOrderStatus::Received->value;
            $po->received_at = now();
            $po->save();

            // Record fulfillment in budget
            $this->budgetService->recordFulfillmentSpent($po, (float) $po->total_amount);

            $this->auditService->record(
                auth()->user(),
                'PurchaseOrder',
                $po->id,
                'received_purchase_order',
                null,
                ['po_number' => $po->po_number, 'received_at' => now()->toIso8601String()]
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
