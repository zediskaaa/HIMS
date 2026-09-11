<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DemandPlanController;
use App\Http\Controllers\Api\Inventory\AdjustmentController;
use App\Http\Controllers\Api\Inventory\CycleCountController;
use App\Http\Controllers\Api\Inventory\GoodsReceiptController;
use App\Http\Controllers\Api\Inventory\QualityControlController;
use App\Http\Controllers\Api\Inventory\ReplenishmentController;
use App\Http\Controllers\Api\Inventory\TelemetryApiController;
use App\Http\Controllers\Api\Inventory\WarehouseTaskController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\Procurement\AwardController;
use App\Http\Controllers\Api\Procurement\EvaluationController;
use App\Http\Controllers\Api\Procurement\OrderGenerationController;
use App\Http\Controllers\Api\Procurement\QuoteController;
use App\Http\Controllers\Api\Procurement\RequisitionController;
use App\Http\Controllers\Api\Procurement\RfqController;
use App\Http\Controllers\Api\ProcurementRequestController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StorageLocationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierQuoteController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/token', [AuthController::class, 'token']);
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Protected API
    Route::middleware('auth:sanctum')->group(function () {
        // Item masters are retired by status. Permanent deletion would orphan
        // stock, receipt, count, and audit history, so no DELETE route exists.
        Route::apiResource('inventory-items', InventoryItemController::class)->only(['index', 'show', 'store', 'update']);
        // Suppliers retain procurement history and are deactivated/suspended;
        // permanent deletion is intentionally not exposed.
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show', 'store', 'update']);
        // Inventory movements are an append-only operational ledger. Corrections
        // are recorded as new compensating movements, never by rewriting history.
        Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'show', 'store']);
        // Locations are deactivated or blocked; deletion is not exposed because
        // stock and task history must continue to resolve their identifiers.
        Route::apiResource('storage-locations', StorageLocationController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('procurement-requests', ProcurementRequestController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('supplier-quotes', SupplierQuoteController::class)->only(['index', 'show', 'store', 'update']);
        // Forecast plans are historical evidence. They may be revised but not
        // erased through the API.
        Route::apiResource('demand-plans', DemandPlanController::class)->only(['index', 'show', 'store', 'update']);
        Route::get('dashboard-summary', [DashboardController::class, 'summary']);

        // Enterprise Source-to-Pay (S2P) & Procure-to-Pay (P2P) Endpoints
        Route::prefix('procurement')->middleware(EnsureIdempotency::class)->group(function () {
            Route::get('requisitions', [RequisitionController::class, 'index']);
            Route::get('requisitions/{requisition}', [RequisitionController::class, 'show']);
            Route::post('requisitions', [RequisitionController::class, 'store']);

            Route::get('rfqs', [RfqController::class, 'index']);
            Route::get('rfqs/{rfq}', [RfqController::class, 'show']);
            Route::post('rfqs', [RfqController::class, 'store']);
            Route::post('rfqs/{rfqId}/quotes', [QuoteController::class, 'store']);
            Route::post('rfqs/{rfqId}/evaluate', [EvaluationController::class, 'evaluate']);
            Route::post('rfqs/{rfqId}/award', [AwardController::class, 'award']);

            Route::post('orders/generate', [OrderGenerationController::class, 'generate']);
        });

        // Enterprise Materials Management & Inventory Endpoints
        Route::prefix('inventory')->middleware(EnsureIdempotency::class)->group(function () {
            Route::get('receipts', [GoodsReceiptController::class, 'index']);
            Route::get('receipts/{goodsReceiptNote}', [GoodsReceiptController::class, 'show']);
            Route::post('receipts', [GoodsReceiptController::class, 'store']);

            Route::post('qc/{batchId}/release', [QualityControlController::class, 'releaseByBatch']);
            Route::post('qc/inspections/{inspection}/release', [QualityControlController::class, 'releaseInspection']);
            Route::post('qc/inspections/{inspection}/reject', [QualityControlController::class, 'rejectInspection']);

            Route::get('requisitions', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'index']);
            Route::get('requisitions/{requisition}', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'show']);
            Route::post('requisitions', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'store']);
            Route::post('requisitions/{reqId}/approve', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'approve']);
            Route::post('requisitions/{reqId}/reject', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'reject']);
            Route::get('requisitions/{reqId}/picklist', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'picklist']);
            Route::post('requisitions/{reqId}/issue', [App\Http\Controllers\Api\Inventory\RequisitionController::class, 'issue']);

            Route::get('cycle-counts', [CycleCountController::class, 'index']);
            Route::get('cycle-counts/{cycleCountDoc}', [CycleCountController::class, 'show']);
            Route::post('cycle-counts/schedule', [CycleCountController::class, 'schedule']);
            Route::post('cycle-counts/{countId}/submit', [CycleCountController::class, 'submitCounts']);
            Route::post('cycle-counts/{countId}/approve', [CycleCountController::class, 'approve']);

            Route::get('adjustments', [AdjustmentController::class, 'index']);
            Route::get('adjustments/{inventoryAdjustment}', [AdjustmentController::class, 'show']);
            Route::post('adjustments', [AdjustmentController::class, 'store']);
            Route::post('adjustments/{adjustmentId}/authorize', [AdjustmentController::class, 'authorizeAdjustment']);

            Route::get('items/{itemId}/replenishment-status', [ReplenishmentController::class, 'status']);
            Route::post('items/{itemId}/replenish', [ReplenishmentController::class, 'evaluate']);

            Route::get('warehouse-tasks', [WarehouseTaskController::class, 'index']);
            Route::get('warehouse-tasks/{warehouseTask}', [WarehouseTaskController::class, 'show']);
            Route::post('warehouse-tasks', [WarehouseTaskController::class, 'store']);
            Route::post('warehouse-tasks/{warehouseTask}/assign', [WarehouseTaskController::class, 'assign']);
            Route::post('warehouse-tasks/{warehouseTask}/start', [WarehouseTaskController::class, 'start']);
            Route::post('warehouse-tasks/{warehouseTask}/scans', [WarehouseTaskController::class, 'scan']);
            Route::post('warehouse-tasks/{warehouseTask}/complete', [WarehouseTaskController::class, 'complete']);
            Route::post('warehouse-tasks/{warehouseTask}/cancel', [WarehouseTaskController::class, 'cancel']);

            Route::post('telemetry/ingest', [TelemetryApiController::class, 'ingest']);
        });
    });
});
