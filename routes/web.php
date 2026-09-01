<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Inventory\DemandForecastController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\ProcurementController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StorageLocationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/dashboard', [InventoryController::class, 'index'])->middleware('auth:web,admin,super_admin')->name('dashboard');

// Polled by the dashboard's alert panel every 30s. Sits on the web routes so
// it authenticates with the session cookie the page already has.
Route::get('/dashboard/live', [InventoryController::class, 'live'])->middleware('auth:web,admin,super_admin')->name('dashboard.live');

/*
 * The inventory surface. `auth` here only establishes that somebody is signed
 * in — the permission each route requires is declared on its controller via
 * HasMiddleware, so a method added later cannot slip out from behind the guard
 * by being forgotten in this file. See App\Enums\UserRole::permissions() for
 * who holds what, and /admin/permissions for the matrix that renders it.
 */
Route::middleware('auth:web,admin,super_admin')->group(function () {
    Route::get('/inventory', function () {
        return redirect()->route('dashboard');
    })->name('inventory');
    Route::get('/inventory/suppliers', [SupplierController::class, 'index'])->name('inventory.suppliers');
    Route::post('/inventory/suppliers', [SupplierController::class, 'store'])->name('inventory.suppliers.store');
    Route::get('/inventory/items', [InventoryItemController::class, 'index'])->name('inventory.items');
    Route::post('/inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
    Route::get('/inventory/storage-locations', [StorageLocationController::class, 'index'])->name('inventory.storage-locations');
    Route::post('/inventory/storage-locations', [StorageLocationController::class, 'store'])->name('inventory.storage-locations.store');
    Route::get('/inventory/stock-movements', [StockMovementController::class, 'index'])->name('inventory.stock-movements');
    Route::post('/inventory/stock-movements', [StockMovementController::class, 'store'])->name('inventory.stock-movements.store');
    Route::get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])->name('inventory.adjustments');
    Route::post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])->name('inventory.adjustments.store');
    Route::get('/inventory/logistics', [InventoryController::class, 'logistics'])->name('inventory.logistics');
    Route::get('/inventory/purchases', [ProcurementController::class, 'index'])->name('inventory.purchases');
    Route::post('/inventory/purchases/requests', [ProcurementController::class, 'storeRequest'])->name('inventory.purchases.requests.store');
    Route::post('/inventory/purchases/quotes', [ProcurementController::class, 'storeQuote'])->name('inventory.purchases.quotes.store');
    Route::post('/inventory/purchases/requests/{procurementRequest}/approve', [ProcurementController::class, 'approve'])->name('inventory.purchases.requests.approve');
    Route::post('/inventory/purchases/orders', [PurchaseOrderController::class, 'store'])->name('inventory.purchases.orders.store');
    Route::post('/inventory/purchases/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('inventory.purchases.receive');
    Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts'])->name('inventory.alerts');
    Route::get('/inventory/reports', [ReportController::class, 'index'])->name('inventory.reports');

    // Demand Forecasting. Reading the forecast needs view_reports; saving a
    // plan needs generate_forecasts. Both are declared on the controller.
    Route::get('/inventory/demand-forecast', [DemandForecastController::class, 'index'])->name('inventory.demand-forecast');
    Route::post('/inventory/demand-forecast', [DemandForecastController::class, 'store'])->name('inventory.demand-forecast.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
 * User Management. Administrator-only — the guard is declared on the controller
 * itself (HasMiddleware) rather than here, so a method added later cannot slip
 * out from behind it.
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/audit-trail', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // The role-versus-module matrix, generated from the same enum the gates are
    // registered from, so it cannot drift from what is actually enforced.
    Route::get('/permissions', [PermissionMatrixController::class, 'index'])->name('permissions');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin_auth.php';
require __DIR__.'/super_admin.php';
