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
    });
});
