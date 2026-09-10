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
    });
});
