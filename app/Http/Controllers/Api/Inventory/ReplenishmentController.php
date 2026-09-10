<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\PurchaseOrderLine;
use App\Services\Inventory\ReplenishmentDaemon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReplenishmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:' . Permission::ViewInventory->value, only: ['status']),
            new Middleware('can:' . Permission::CreateRequisition->value, only: ['evaluate']),
        ];
    }

    public function __construct(private readonly ReplenishmentDaemon $replenishmentDaemon) {}

    public function status(int $itemId): JsonResponse
    {
        $item = InventoryItem::findOrFail($itemId);

        $ss = $item->safety_stock > 0 ? $item->safety_stock : $this->replenishmentDaemon->calculateSafetyStock($item);
        $rop = $item->reorder_point > 0 ? $item->reorder_point : $this->replenishmentDaemon->calculateReorderPoint($item);
        $eoq = $item->economic_order_quantity > 0 ? $item->economic_order_quantity : $this->replenishmentDaemon->calculateEconomicOrderQuantity($item);

        $atp = $item->availableToPromise();
        $soh = $item->physicalQuantityOnHand();
        $quarantined = $item->quarantinedQuantity();
        $blocked = $item->blockedQuantity();

        $onOrder = (int) PurchaseOrderLine::query()
            ->where('item_id', $item->id)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['received', 'cancelled', 'rejected']))
            ->selectRaw('coalesce(sum(ordered_quantity - received_quantity), 0) as open_qty')
            ->value('open_qty');

        $effectiveStock = $atp + $onOrder;
        $isBreached = $effectiveStock <= $rop;

        return response()->json([
            'item_id' => $item->id,
            'item_name' => $item->name,
            'sku' => $item->sku,
            'abc_class' => $item->abc_class,
            'physical_on_hand' => $soh,
            'unrestricted_on_hand' => (int) $item->quantity_on_hand,
            'reserved_quantity' => (int) $item->reserved_quantity,
            'quarantined_quantity' => $quarantined,
            'blocked_quantity' => $blocked,
            'available_to_promise' => $atp,
            'on_order_quantity' => $onOrder,
            'effective_stock' => $effectiveStock,
            'safety_stock' => $ss,
            'reorder_point' => $rop,
            'economic_order_quantity' => $eoq,
            'reorder_breached' => $isBreached,
            'recommendation' => $isBreached
                ? "Reorder condition triggered. Target order quantity: {$eoq} units."
                : 'Stock level optimal. Above reorder threshold.',
        ]);
    }

    public function evaluate(Request $request, int $itemId): JsonResponse
    {
        $item = InventoryItem::findOrFail($itemId);

        $pr = $this->replenishmentDaemon->evaluateAndReplenish($item, $request->user());

        if ($pr) {
            return response()->json([
                'message' => "Automated draft Purchase Request {$pr->pr_number} created in Procurement.",
                'purchase_request' => $pr->load('lines'),
            ], 201);
        }

        return response()->json([
            'message' => 'No purchase request generated: stock is above reorder point or an open draft PR already exists.',
        ]);
    }
}
