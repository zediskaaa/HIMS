<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
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
            new Middleware('can:' . Permission::ReceivePurchaseOrder->value, only: ['storeReceipt']),
            new Middleware('can:' . Permission::InspectStock->value, only: ['qcQueue', 'releaseQc', 'rejectQc']),
            new Middleware('can:' . Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(
        private readonly GoodsReceiptService $receiptService,
        private readonly QualityControlService $qcService
    ) {}

    public function index(): View
    {
        $goodsReceipts = GoodsReceiptNote::with(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item'])
            ->latest('received_at')
            ->paginate(15);

        // Open purchase orders available for dock receiving
        $openPurchaseOrders = PurchaseOrder::with(['supplier', 'lines.item'])
            ->whereNotIn('status', ['received', 'cancelled', 'rejected', PurchaseOrderStatus::Fulfilled->value])
            ->latest()
            ->get();

        $storageLocations = StorageLocation::where('status', 'active')->orderBy('name')->get();

        return view('inventory.receiving.index', compact('goodsReceipts', 'openPurchaseOrders', 'storageLocations'));
    }

    public function show(GoodsReceiptNote $goodsReceiptNote): View
    {
        $goodsReceiptNote->load(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item', 'lines.batch', 'lines.inspections']);

        return view('inventory.receiving.show', compact('goodsReceiptNote'));
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'carrier_name' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'packing_slip_number' => ['nullable', 'string', 'max:100'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'exists:po_line_items,id'],
            'lines.*.received_quantity' => ['required', 'integer', 'min:1'],
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

    public function qcQueue(): View
    {
        $inspections = QualityInspection::with(['grnLine.goodsReceiptNote.supplier', 'item', 'batch'])
            ->where('inspection_status', 'pending_sample')
            ->latest('inspection_date')
            ->paginate(15);

        $storageLocations = StorageLocation::where('status', 'active')
            ->where('zone', '!=', 'Quarantine')
            ->orderBy('name')
            ->get();

        return view('inventory.receiving.qc', compact('inspections', 'storageLocations'));
    }

    public function releaseQc(Request $request, QualityInspection $inspection): RedirectResponse
    {
        $validated = $request->validate([
            'accepted_quantity' => ['required', 'integer', 'min:1'],
            'target_location_id' => ['required', 'exists:storage_locations,id'],
            'findings' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->qcService->releaseLot(
                $inspection,
                (int) $validated['accepted_quantity'],
                (int) $validated['target_location_id'],
                $request->user(),
                $validated['findings'] ?? null
            );

            return redirect()->route('inventory.qc.index')
                ->with('success', "Stock released from quarantine to unrestricted inventory.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['qc' => $e->getMessage()]);
        }
    }

    public function rejectQc(Request $request, QualityInspection $inspection): RedirectResponse
    {
        $validated = $request->validate([
            'rejected_quantity' => ['required', 'integer', 'min:1'],
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->qcService->rejectLot(
                $inspection,
                (int) $validated['rejected_quantity'],
                $validated['rejection_reason'],
                $request->user()
            );

            return redirect()->route('inventory.qc.index')
                ->with('success', "Stock rejected and isolated in blocked inventory.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['qc' => $e->getMessage()]);
        }
    }
}
