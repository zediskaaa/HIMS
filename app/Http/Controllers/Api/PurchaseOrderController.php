<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewProcurement->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::IssuePurchaseOrder->value, only: ['store']),
            new Middleware('can:'.Permission::ApprovePurchaseOrder->value, only: ['update']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = PurchaseOrder::with(['supplier', 'item'])->paginate($perPage);

        return PurchaseOrderResource::collection($items);
    }

    public function show(PurchaseOrder $purchase_order)
    {
        return new PurchaseOrderResource($purchase_order);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $data = $request->validated();
        $po = PurchaseOrder::create($data);

        return (new PurchaseOrderResource($po))->response()->setStatusCode(201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $purchase_order->update($request->validated());

        return new PurchaseOrderResource($purchase_order);
    }
}
