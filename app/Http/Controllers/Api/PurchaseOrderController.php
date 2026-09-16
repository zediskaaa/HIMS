<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Procurement\POConversionService;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Throwable;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly POConversionService $poConversionService,
        private readonly ProcurementAuditService $auditService
    ) {}

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
        try {
            $po = $this->poConversionService->createDirectPurchaseOrder(
                $request->validated(),
                $request->user(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'purchase_order' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'purchase_order' => 'The purchase order could not be created. No records were saved.',
            ]);
        }

        return (new PurchaseOrderResource($po))->response()->setStatusCode(201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $old = $purchase_order->only(array_keys($request->validated()));
        $purchase_order->update($request->validated());
        $new = $purchase_order->only(array_keys($request->validated()));

        $this->auditService->record(
            $request->user(),
            'PurchaseOrder',
            $purchase_order->id,
            'amended_purchase_order',
            $old,
            $new,
        );

        return new PurchaseOrderResource($purchase_order);
    }
}
