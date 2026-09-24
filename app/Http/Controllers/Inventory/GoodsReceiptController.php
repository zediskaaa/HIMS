<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\PurchaseOrder;
use App\Models\QualityInspection;
use App\Models\StorageLocation;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\QualityControlService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class GoodsReceiptController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ReceivePurchaseOrder->value, only: ['storeReceipt']),
            new Middleware('can:'.Permission::RecordMovements->value, only: ['returnRejected']),
            new Middleware('can:'.Permission::InspectStock->value, only: ['qcQueue', 'releaseQc', 'rejectQc']),
        ];
    }

    public function __construct(
        private readonly GoodsReceiptService $receiptService,
        private readonly QualityControlService $qcService
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission(Permission::ViewInventory)
            || auth()->user()->hasPermission(Permission::ReceivePurchaseOrder), 403);
        $goodsReceipts = GoodsReceiptNote::with(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item'])
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        // Open purchase orders available for dock receiving
        $openPurchaseOrders = PurchaseOrder::with(['supplier', 'lines.item'])
            ->whereIn('status', [
                PurchaseOrderStatus::Approved->value,
                PurchaseOrderStatus::Dispatched->value,
                PurchaseOrderStatus::Acknowledged->value,
                PurchaseOrderStatus::UnderInspection->value,
                PurchaseOrderStatus::RejectedDelivery->value,
                PurchaseOrderStatus::PartiallyFulfilled->value,
                'partially_received',
                'issued',
            ])
            ->whereHas('lines', fn ($query) => $query->whereRaw('ordered_quantity - received_quantity + rejected_quantity > 0'))
            ->latest()
            ->get();

        $storageLocations = StorageLocation::where('status', 'active')
            ->where('is_quarantine', false)
            ->where('is_damaged_stock', false)
            ->where('is_receiving_staging', false)
            ->where('is_dispatch_staging', false)
            ->whereNotIn('type', ['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'level', 'department'])
            ->orderBy('name')
            ->get();

        $suppliers = \App\Models\Supplier::where('status', 'active')->orderBy('name')->get();

        return view('inventory.receiving.index', compact('goodsReceipts', 'openPurchaseOrders', 'storageLocations', 'suppliers'));
    }

    public function show(GoodsReceiptNote $goodsReceiptNote): View
    {
        abort_unless(auth()->user()->hasPermission(Permission::ViewInventory)
            || auth()->user()->hasPermission(Permission::ReceivePurchaseOrder), 403);
        $goodsReceiptNote->load([
            'purchaseOrder.lines.item',
            'supplier',
            'receivedBy',
            'lines.item',
            'lines.batch',
            'lines.destinationLocation',
            'lines.purchaseOrderLine',
            'lines.inspections.warehouseTasks.destinationLocation',
            'lines.inspections.warehouseTasks.sourceLocation',
            'lines.inspections.inspectedBy',
            'inspectionAcceptanceReport.inspectedBy',
            'inspectionAcceptanceReport.acceptedBy',
            'inspectionAcceptanceReport.documents.uploadedBy',
            'documents.uploadedBy',
        ]);

        $storageLocations = StorageLocation::where('status', 'active')
            ->where('is_quarantine', false)
            ->where('is_damaged_stock', false)
            ->where('is_receiving_staging', false)
            ->where('is_dispatch_staging', false)
            ->whereNotIn('type', ['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'level', 'department'])
            ->orderBy('name')
            ->get();

        return view('inventory.receiving.show', compact('goodsReceiptNote', 'storageLocations'));
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'receipt_key' => ['nullable', 'string', 'max:100'],
            'actual_supplier_id' => ['required', 'exists:suppliers,id'],
            'carrier_name' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'packing_slip_number' => ['nullable', 'string', 'max:100'],
            'received_at' => ['nullable', 'date'],
            'destination_location_id' => ['nullable', 'exists:storage_locations,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'exists:po_line_items,id'],
            'lines.*.actual_item_id' => ['nullable', 'exists:inventory_items,id'],
            'lines.*.actual_sku' => ['required', 'string', 'max:100'],
            'lines.*.actual_purchase_unit' => ['required', 'string', 'max:50'],
            'lines.*.received_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.destination_location_id' => ['nullable', 'exists:storage_locations,id'],
            'lines.*.item_condition' => ['nullable', 'string', 'in:good,damaged,compromised,wrong_item,expired'],
            'lines.*.discrepancy_type' => ['nullable', 'string', 'in:shortage,overage,damage,wrong_item,expired,near_expiry,missing_lot,other'],
            'lines.*.discrepancy_action' => ['nullable', 'string', 'in:quarantine,accept,reject,return_to_supplier,hold'],
            'lines.*.discrepancy_notes' => ['nullable', 'string', 'max:500'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:50'],
            'lines.*.lot_number' => ['nullable', 'string', 'max:50'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.manufactured_date' => ['nullable', 'date'],
            'lines.*.serial_number' => ['nullable', 'string', 'max:100'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        try {
            $grn = $this->receiptService->receiveOrder($po, $validated, $request->user());

            return redirect()->route('inventory.receiving.index')
                ->with('success', "Goods Receipt Note {$grn->grn_number} created successfully. Inbound stock routed to Quarantine.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['receive' => $e->getMessage()])->withInput();
        }
    }

    public function returnRejected(Request $request, GoodsReceiptNote $goodsReceiptNote, GoodsReceiptNoteLine $line): RedirectResponse
    {
        abort_unless((int) $line->goods_receipt_note_id === (int) $goodsReceiptNote->id, 404);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'return_key' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $this->qcService->returnRejected($line, (int) $validated['quantity'], $request->user(), $validated['return_key'] ?? null);

            return redirect()->route('inventory.receiving.show', $goodsReceiptNote)
                ->with('success', 'Rejected stock return recorded.');
        } catch (DomainException $exception) {
            return redirect()->back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function qcQueue(): View
    {
        $inspections = QualityInspection::with(['grnLine.goodsReceiptNote.supplier', 'item', 'batch'])
            ->whereIn('inspection_status', ['pending_sample', 'partially_disposed'])
            ->latest('inspection_date')
            ->paginate(15)
            ->withQueryString();

        $storageLocations = StorageLocation::active()
            ->where('is_quarantine', false)
            ->where('is_damaged_stock', false)
            ->where('is_receiving_staging', false)
            ->where('is_dispatch_staging', false)
            ->whereNotIn('type', ['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'level', 'department'])
            ->orderBy('name')
            ->get();

        return view('inventory.receiving.qc', compact('inspections', 'storageLocations'));
    }

    public function releaseQc(Request $request, QualityInspection $inspection): RedirectResponse
    {
        $validated = $request->validate([
            'accepted_quantity' => ['required', 'integer', 'min:1'],
            'decision_key' => ['nullable', 'string', 'max:100'],
            'target_location_id' => ['required', 'exists:storage_locations,id'],
            'findings' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->qcService->releaseLot(
                $inspection,
                (int) $validated['accepted_quantity'],
                (int) $validated['target_location_id'],
                $request->user(),
                $validated['findings'] ?? null,
                $validated['decision_key'] ?? null
            );

            return redirect()->route('inventory.qc.index')
                ->with('success', 'Stock released from quarantine. If receiving staging is configured, a scan-validated put-away task now controls the final move.');
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['qc' => $e->getMessage()]);
        }
    }

    public function rejectQc(Request $request, QualityInspection $inspection): RedirectResponse
    {
        $validated = $request->validate([
            'rejected_quantity' => ['required', 'integer', 'min:1'],
            'decision_key' => ['nullable', 'string', 'max:100'],
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->qcService->rejectLot(
                $inspection,
                (int) $validated['rejected_quantity'],
                $validated['rejection_reason'],
                $request->user(),
                $validated['decision_key'] ?? null
            );

            return redirect()->route('inventory.qc.index')
                ->with('success', 'Stock rejected and isolated in blocked inventory.');
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['qc' => $e->getMessage()]);
        }
    }
}
