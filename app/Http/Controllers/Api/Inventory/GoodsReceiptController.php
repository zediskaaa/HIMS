<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Services\Inventory\GoodsReceiptService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GoodsReceiptController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:' . Permission::ReceivePurchaseOrder->value, only: ['store']),
            new Middleware('can:' . Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(private readonly GoodsReceiptService $receiptService) {}

    public function index(): JsonResponse
    {
        $receipts = GoodsReceiptNote::with(['supplier', 'purchaseOrder', 'receivedBy', 'lines.item'])
            ->latest('received_at')
            ->paginate(25);

        return response()->json($receipts);
    }

    public function show(GoodsReceiptNote $goodsReceiptNote): JsonResponse
    {
        $goodsReceiptNote->load(['supplier', 'purchaseOrder', 'receivedBy', 'lines.item', 'lines.inspections']);

        return response()->json($goodsReceiptNote);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'carrier_name' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'packing_slip_number' => ['nullable', 'string', 'max:100'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['nullable', 'array'],
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

            return response()->json([
                'message' => "Goods receipt {$grn->grn_number} created and placed into Quarantine.",
                'data' => $grn->load('lines.item'),
            ], 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
