<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DemandPlanController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\ProcurementRequestController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StorageLocationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierQuoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/token', [AuthController::class, 'token']);
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Protected API
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('inventory-items', InventoryItemController::class);
        // Suppliers retain procurement history and are deactivated/suspended;
        // permanent deletion is intentionally not exposed.
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('stock-movements', StockMovementController::class);
        Route::apiResource('storage-locations', StorageLocationController::class);
        Route::apiResource('procurement-requests', ProcurementRequestController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('supplier-quotes', SupplierQuoteController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('demand-plans', DemandPlanController::class);
        Route::get('dashboard-summary', [DashboardController::class, 'summary']);

        // Enterprise Source-to-Pay (S2P) & Procure-to-Pay (P2P) Endpoints
        Route::prefix('procurement')->middleware(\App\Http\Middleware\EnsureIdempotency::class)->group(function () {
            Route::get('requisitions', [\App\Http\Controllers\Api\Procurement\RequisitionController::class, 'index']);
            Route::get('requisitions/{requisition}', [\App\Http\Controllers\Api\Procurement\RequisitionController::class, 'show']);
            Route::post('requisitions', [\App\Http\Controllers\Api\Procurement\RequisitionController::class, 'store']);

            Route::get('rfqs', [\App\Http\Controllers\Api\Procurement\RfqController::class, 'index']);
            Route::get('rfqs/{rfq}', [\App\Http\Controllers\Api\Procurement\RfqController::class, 'show']);
            Route::post('rfqs', [\App\Http\Controllers\Api\Procurement\RfqController::class, 'store']);
            Route::post('rfqs/{rfqId}/quotes', [\App\Http\Controllers\Api\Procurement\QuoteController::class, 'store']);
            Route::post('rfqs/{rfqId}/evaluate', [\App\Http\Controllers\Api\Procurement\EvaluationController::class, 'evaluate']);
            Route::post('rfqs/{rfqId}/award', [\App\Http\Controllers\Api\Procurement\AwardController::class, 'award']);

            Route::post('orders/generate', [\App\Http\Controllers\Api\Procurement\OrderGenerationController::class, 'generate']);
        });

        // Enterprise Materials Management & Inventory Endpoints
        Route::prefix('inventory')->middleware(\App\Http\Middleware\EnsureIdempotency::class)->group(function () {
            Route::get('receipts', [\App\Http\Controllers\Api\Inventory\GoodsReceiptController::class, 'index']);
            Route::get('receipts/{goodsReceiptNote}', [\App\Http\Controllers\Api\Inventory\GoodsReceiptController::class, 'show']);
            Route::post('receipts', [\App\Http\Controllers\Api\Inventory\GoodsReceiptController::class, 'store']);

            Route::post('qc/{batchId}/release', [\App\Http\Controllers\Api\Inventory\QualityControlController::class, 'releaseByBatch']);
            Route::post('qc/inspections/{inspection}/release', [\App\Http\Controllers\Api\Inventory\QualityControlController::class, 'releaseInspection']);
            Route::post('qc/inspections/{inspection}/reject', [\App\Http\Controllers\Api\Inventory\QualityControlController::class, 'rejectInspection']);

            Route::get('requisitions', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'index']);
            Route::get('requisitions/{requisition}', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'show']);
            Route::post('requisitions', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'store']);
            Route::post('requisitions/{reqId}/approve', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'approve']);
            Route::post('requisitions/{reqId}/reject', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'reject']);
            Route::get('requisitions/{reqId}/picklist', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'picklist']);
            Route::post('requisitions/{reqId}/issue', [\App\Http\Controllers\Api\Inventory\RequisitionController::class, 'issue']);

            Route::get('cycle-counts', [\App\Http\Controllers\Api\Inventory\CycleCountController::class, 'index']);
            Route::get('cycle-counts/{cycleCountDoc}', [\App\Http\Controllers\Api\Inventory\CycleCountController::class, 'show']);
            Route::post('cycle-counts/schedule', [\App\Http\Controllers\Api\Inventory\CycleCountController::class, 'schedule']);
            Route::post('cycle-counts/{countId}/submit', [\App\Http\Controllers\Api\Inventory\CycleCountController::class, 'submitCounts']);
            Route::post('cycle-counts/{countId}/approve', [\App\Http\Controllers\Api\Inventory\CycleCountController::class, 'approve']);

            Route::get('adjustments', [\App\Http\Controllers\Api\Inventory\AdjustmentController::class, 'index']);
            Route::get('adjustments/{inventoryAdjustment}', [\App\Http\Controllers\Api\Inventory\AdjustmentController::class, 'show']);
            Route::post('adjustments', [\App\Http\Controllers\Api\Inventory\AdjustmentController::class, 'store']);
            Route::post('adjustments/{adjustmentId}/authorize', [\App\Http\Controllers\Api\Inventory\AdjustmentController::class, 'authorizeAdjustment']);

            Route::get('items/{itemId}/replenishment-status', [\App\Http\Controllers\Api\Inventory\ReplenishmentController::class, 'status']);
            Route::post('items/{itemId}/replenish', [\App\Http\Controllers\Api\Inventory\ReplenishmentController::class, 'evaluate']);
        });
    });
});
