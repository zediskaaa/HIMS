<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Analytics\ProcessReviewController;
use App\Http\Controllers\AuthenticatorController;
use App\Http\Controllers\Inventory\ConsignmentController;
use App\Http\Controllers\Inventory\CycleCountController;
use App\Http\Controllers\Inventory\DemandForecastController;
use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\LogisticsController;
use App\Http\Controllers\Inventory\MaterialRequisitionController;
use App\Http\Controllers\Inventory\NarcoticsVaultController;
use App\Http\Controllers\Inventory\ProcurementController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\SmartWarehousingController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Inventory\StorageLocationController;
use App\Http\Controllers\Inventory\TelemetryController;
use App\Http\Controllers\Inventory\WarehouseTaskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::view('/privacy-notice', 'legal.privacy-notice')->name('privacy.notice');
Route::redirect('/privacy-policy', '/privacy-notice');
Route::redirect('/privacy', '/privacy-notice');

Route::view('/terms-of-use', 'legal.terms-of-use')->name('terms');
Route::redirect('/terms-and-conditions', '/terms-of-use');
Route::redirect('/terms', '/terms-of-use');

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
    Route::get('/inventory/suppliers/{supplier}', [SupplierController::class, 'show'])->name('inventory.suppliers.show');
    Route::patch('/inventory/suppliers/{supplier}', [SupplierController::class, 'update'])->name('inventory.suppliers.update');
    Route::post('/inventory/suppliers/{supplier}/contacts', [SupplierController::class, 'addContact'])->name('inventory.suppliers.contacts.store');
    Route::post('/inventory/suppliers/{supplier}/documents', [SupplierController::class, 'uploadDocument'])->name('inventory.suppliers.documents.store');
    Route::get('/inventory/suppliers/{supplier}/documents/{document}', [SupplierController::class, 'downloadDocument'])->name('inventory.suppliers.documents.download');
    Route::patch('/inventory/suppliers/{supplier}/documents/{document}/verification', [SupplierController::class, 'verifyDocument'])->name('inventory.suppliers.documents.verify');
    Route::post('/inventory/suppliers/{supplier}/submit', [SupplierController::class, 'submitForReview'])->name('inventory.suppliers.submit');
    Route::post('/inventory/suppliers/{supplier}/approve', [SupplierController::class, 'approve'])->name('inventory.suppliers.approve');
    Route::post('/inventory/suppliers/{supplier}/reject', [SupplierController::class, 'reject'])->name('inventory.suppliers.reject');
    Route::post('/inventory/suppliers/{supplier}/suspend', [SupplierController::class, 'suspend'])->name('inventory.suppliers.suspend');
    Route::post('/inventory/suppliers/{supplier}/inactivate', [SupplierController::class, 'inactivate'])->name('inventory.suppliers.inactivate');
    Route::post('/inventory/suppliers/{supplier}/reactivate', [SupplierController::class, 'reactivate'])->name('inventory.suppliers.reactivate');
    Route::post('/inventory/suppliers/{supplier}/products', [SupplierController::class, 'addProduct'])->name('inventory.suppliers.products.store');
    Route::patch('/inventory/suppliers/{supplier}/products/{supplierProduct}/deactivate', [SupplierController::class, 'deactivateProduct'])->name('inventory.suppliers.products.deactivate');
    Route::patch('/inventory/suppliers/{supplier}/products/{supplierProduct}/reactivate', [SupplierController::class, 'reactivateProduct'])->name('inventory.suppliers.products.reactivate');
    Route::post('/inventory/suppliers/{supplier}/prices', [SupplierController::class, 'addPrice'])->name('inventory.suppliers.prices.store');
    Route::post('/inventory/suppliers/{supplier}/contracts', [SupplierController::class, 'addContract'])->name('inventory.suppliers.contracts.store');
    Route::patch('/inventory/suppliers/{supplier}/contracts/{contract}', [SupplierController::class, 'updateContract'])->name('inventory.suppliers.contracts.update');
    Route::get('/inventory/items', [InventoryItemController::class, 'index'])->name('inventory.items');
    Route::post('/inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
    Route::get('/inventory/storage-locations', [StorageLocationController::class, 'index'])->name('inventory.storage-locations');
    Route::post('/inventory/storage-locations', [StorageLocationController::class, 'store'])->name('inventory.storage-locations.store');
    Route::patch('/inventory/storage-locations/{storageLocation}/status', [StorageLocationController::class, 'updateStatus'])->name('inventory.storage-locations.status');
    Route::post('/inventory/storage-locations/{storageLocation}/label', [StorageLocationController::class, 'printLabel'])->name('inventory.storage-locations.label');
    Route::get('/inventory/warehouse-tasks', [WarehouseTaskController::class, 'index'])->name('inventory.warehouse-tasks.index');
    Route::post('/inventory/warehouse-tasks', [WarehouseTaskController::class, 'store'])->name('inventory.warehouse-tasks.store');
    Route::get('/inventory/warehouse-tasks/{warehouseTask}', [WarehouseTaskController::class, 'show'])->name('inventory.warehouse-tasks.show');
    Route::post('/inventory/warehouse-tasks/{warehouseTask}/assign', [WarehouseTaskController::class, 'assign'])->name('inventory.warehouse-tasks.assign');
    Route::post('/inventory/warehouse-tasks/{warehouseTask}/start', [WarehouseTaskController::class, 'start'])->name('inventory.warehouse-tasks.start');
    Route::post('/inventory/warehouse-tasks/{warehouseTask}/scan', [WarehouseTaskController::class, 'scan'])->name('inventory.warehouse-tasks.scan');
    Route::post('/inventory/warehouse-tasks/{warehouseTask}/complete', [WarehouseTaskController::class, 'complete'])->name('inventory.warehouse-tasks.complete');
    Route::post('/inventory/warehouse-tasks/{warehouseTask}/cancel', [WarehouseTaskController::class, 'cancel'])->name('inventory.warehouse-tasks.cancel');
    Route::post('/inventory/warehouse-tasks/{warehouseTask}/label', [WarehouseTaskController::class, 'printLabel'])->name('inventory.warehouse-tasks.label');
    Route::post('/inventory/warehouse-exceptions/{warehouseException}/resolve', [WarehouseTaskController::class, 'resolveException'])->name('inventory.warehouse-exceptions.resolve');

    // Smart Warehousing Suite
    Route::get('/inventory/warehousing', [SmartWarehousingController::class, 'dashboard'])->name('inventory.warehousing.dashboard');
    Route::get('/inventory/warehousing/locations', [SmartWarehousingController::class, 'locations'])->name('inventory.warehousing.locations');
    Route::post('/inventory/warehousing/locations', [SmartWarehousingController::class, 'storeLocation'])->name('inventory.warehousing.locations.store');
    Route::get('/inventory/warehousing/scan-station', [SmartWarehousingController::class, 'scanStation'])->name('inventory.warehousing.scan-station');

    // IoT Cold-Chain Telemetry & MKT
    Route::get('/inventory/warehousing/telemetry', [TelemetryController::class, 'index'])->name('inventory.warehousing.telemetry');
    Route::post('/inventory/warehousing/telemetry', [TelemetryController::class, 'store'])->name('inventory.warehousing.telemetry.store');
    Route::post('/inventory/warehousing/telemetry/{location}/release', [TelemetryController::class, 'release'])->name('inventory.warehousing.telemetry.release');

    // Dangerous Drugs & PDEA Narcotics Vault
    Route::get('/inventory/warehousing/narcotics', [NarcoticsVaultController::class, 'index'])->name('inventory.warehousing.narcotics');
    Route::post('/inventory/warehousing/narcotics', [NarcoticsVaultController::class, 'store'])->name('inventory.warehousing.narcotics.store');
    Route::get('/inventory/warehousing/narcotics/export', [NarcoticsVaultController::class, 'exportReport'])->name('inventory.warehousing.narcotics.export');

    // Surgical Consignment & Bill-Only Implants
    Route::get('/inventory/warehousing/consignment', [ConsignmentController::class, 'index'])->name('inventory.warehousing.consignment');
    Route::post('/inventory/warehousing/consignment/consume', [ConsignmentController::class, 'consume'])->name('inventory.warehousing.consignment.consume');

    Route::get('/inventory/stock-movements', [StockMovementController::class, 'index'])->name('inventory.stock-movements');
    Route::post('/inventory/stock-movements', [StockMovementController::class, 'store'])->name('inventory.stock-movements.store');
    Route::get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])->name('inventory.adjustments');
    Route::post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])->name('inventory.adjustments.store');
    Route::post('/inventory/adjustments/{inventoryAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('inventory.adjustments.approve');

    // Inbound Goods Receiving & QC Inspection
    Route::get('/inventory/receiving', [GoodsReceiptController::class, 'index'])->name('inventory.receiving.index');
    Route::post('/inventory/receiving', [GoodsReceiptController::class, 'storeReceipt'])->name('inventory.receiving.store');
    Route::get('/inventory/receiving/{goodsReceiptNote}', [GoodsReceiptController::class, 'show'])->name('inventory.receiving.show');
    Route::get('/inventory/qc', [GoodsReceiptController::class, 'qcQueue'])->name('inventory.qc.index');
    Route::post('/inventory/qc/{inspection}/release', [GoodsReceiptController::class, 'releaseQc'])->name('inventory.qc.release');
    Route::post('/inventory/qc/{inspection}/reject', [GoodsReceiptController::class, 'rejectQc'])->name('inventory.qc.reject');

    // Material Store Requisitions & Picking
    Route::get('/inventory/requisitions', [MaterialRequisitionController::class, 'index'])->name('inventory.requisitions.index');
    Route::post('/inventory/requisitions', [MaterialRequisitionController::class, 'store'])->name('inventory.requisitions.store');
    Route::get('/inventory/requisitions/{requisition}', [MaterialRequisitionController::class, 'show'])->name('inventory.requisitions.show');
    Route::post('/inventory/requisitions/{requisition}/approve', [MaterialRequisitionController::class, 'approve'])->name('inventory.requisitions.approve');
    Route::post('/inventory/requisitions/{requisition}/reject', [MaterialRequisitionController::class, 'reject'])->name('inventory.requisitions.reject');
    Route::post('/inventory/requisitions/{requisition}/cancel', [MaterialRequisitionController::class, 'cancel'])->name('inventory.requisitions.cancel');
    Route::post('/inventory/requisitions/{requisition}/issue', [MaterialRequisitionController::class, 'issue'])->name('inventory.requisitions.issue');
    Route::post('/inventory/requisitions/{requisition}/acknowledge', [MaterialRequisitionController::class, 'acknowledge'])->name('inventory.requisitions.acknowledge');

    // Internal Transfers
    Route::get('/inventory/transfers', [StockTransferController::class, 'index'])->name('inventory.transfers.index');
    Route::post('/inventory/transfers', [StockTransferController::class, 'store'])->name('inventory.transfers.store');
    Route::get('/inventory/transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('inventory.transfers.show');
    Route::post('/inventory/transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('inventory.transfers.receive');

    // Cycle Counting
    Route::get('/inventory/cycle-counts', [CycleCountController::class, 'index'])->name('inventory.cycle-counts.index');
    Route::post('/inventory/cycle-counts', [CycleCountController::class, 'schedule'])->name('inventory.cycle-counts.schedule');
    Route::get('/inventory/cycle-counts/{cycleCountDoc}', [CycleCountController::class, 'show'])->name('inventory.cycle-counts.show');
    Route::post('/inventory/cycle-counts/{cycleCountDoc}/counts', [CycleCountController::class, 'submitCounts'])->name('inventory.cycle-counts.submit');
    Route::post('/inventory/cycle-counts/{cycleCountDoc}/approve', [CycleCountController::class, 'approve'])->name('inventory.cycle-counts.approve');
    Route::post('/inventory/cycle-counts/calculate-abc', [CycleCountController::class, 'calculateAbc'])->name('inventory.cycle-counts.abc');

    // Document Tracking & Logistics Records System (DTRS)
    Route::get('/inventory/logistics', [LogisticsController::class, 'dashboard'])->name('inventory.logistics');
    Route::get('/inventory/logistics/documents', [LogisticsController::class, 'documents'])->name('inventory.logistics.documents');
    Route::post('/inventory/logistics/documents', [LogisticsController::class, 'uploadDocument'])->name('inventory.logistics.documents.upload');
    Route::post('/inventory/logistics/documents/{document}/verify', [LogisticsController::class, 'verifyDocument'])->name('inventory.logistics.documents.verify');
    Route::post('/inventory/logistics/documents/{document}/supersede', [LogisticsController::class, 'supersedeDocument'])->name('inventory.logistics.documents.supersede');
    Route::get('/inventory/logistics/documents/{document}/download', [LogisticsController::class, 'downloadDocument'])->name('inventory.logistics.documents.download');

    Route::get('/inventory/logistics/shipments', [LogisticsController::class, 'shipments'])->name('inventory.logistics.shipments');
    Route::post('/inventory/logistics/shipments', [LogisticsController::class, 'storeShipment'])->name('inventory.logistics.shipments.store');
    Route::post('/inventory/logistics/shipments/{shipment}/dock-arrival', [LogisticsController::class, 'recordDockArrival'])->name('inventory.logistics.shipments.dock-arrival');

    Route::get('/inventory/logistics/iar', [LogisticsController::class, 'iarIndex'])->name('inventory.logistics.iar.index');
    Route::post('/inventory/logistics/receipts/{goodsReceiptNote}/iar', [LogisticsController::class, 'generateIarFromReceipt'])->name('inventory.logistics.iar.generate');
    Route::get('/inventory/logistics/iar/{iar}', [LogisticsController::class, 'iarShow'])->name('inventory.logistics.iar.show');
    Route::post('/inventory/logistics/iar/{iar}/technical-inspection', [LogisticsController::class, 'performTechnicalInspection'])->name('inventory.logistics.iar.technical-inspection');
    Route::post('/inventory/logistics/iar/{iar}/custodial-acceptance', [LogisticsController::class, 'approveCustodialAcceptance'])->name('inventory.logistics.iar.custodial-acceptance');
    Route::post('/inventory/logistics/iar/{iar}/transmit-coa', [LogisticsController::class, 'transmitToCoa'])->name('inventory.logistics.iar.transmit-coa');

    Route::get('/inventory/logistics/chain-of-custody', [LogisticsController::class, 'chainOfCustody'])->name('inventory.logistics.chain-of-custody');
    Route::get('/inventory/logistics/ris/{requisition}', [LogisticsController::class, 'risShow'])->name('inventory.logistics.ris.show');
    Route::get('/inventory/purchases', [ProcurementController::class, 'index'])->name('inventory.purchases');
    Route::post('/inventory/purchases/requests', [ProcurementController::class, 'storeRequest'])->name('inventory.purchases.requests.store');
    Route::post('/inventory/purchases/quotes', [ProcurementController::class, 'storeQuote'])->name('inventory.purchases.quotes.store');
    Route::post('/inventory/purchases/requests/{procurementRequest}/approve', [ProcurementController::class, 'approve'])->name('inventory.purchases.requests.approve');
    Route::post('/inventory/purchases/orders', [PurchaseOrderController::class, 'store'])->name('inventory.purchases.orders.store');
    Route::post('/inventory/purchases/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('inventory.purchases.receive');
    Route::post('/inventory/purchases/enterprise-requests', [ProcurementController::class, 'storeEnterpriseRequest'])->name('inventory.purchases.enterprise-requests.store');
    Route::post('/inventory/purchases/rfqs', [ProcurementController::class, 'createEnterpriseRfq'])->name('inventory.purchases.rfqs.store');
    Route::post('/inventory/purchases/rfqs/{rfq}/evaluate', [ProcurementController::class, 'evaluateRfqWeb'])->name('inventory.purchases.rfqs.evaluate');
    Route::post('/inventory/purchases/rfqs/{rfq}/award', [ProcurementController::class, 'awardRfqWeb'])->name('inventory.purchases.rfqs.award');
    Route::post('/inventory/purchases/approval-chains/{chain}/approve', [ProcurementController::class, 'approveStepWeb'])->name('inventory.purchases.approval-chains.approve');
    Route::post('/inventory/purchases/approval-chains/{chain}/reject', [ProcurementController::class, 'rejectStepWeb'])->name('inventory.purchases.approval-chains.reject');
    Route::post('/inventory/purchases/orders/from-award', [ProcurementController::class, 'generatePoFromAwardWeb'])->name('inventory.purchases.orders.from-award');
    Route::post('/inventory/purchases/orders/{purchaseOrder}/revise', [PurchaseOrderController::class, 'revise'])->name('inventory.purchases.orders.revise');
    Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts'])->name('inventory.alerts');
    Route::get('/inventory/reports', [ReportController::class, 'index'])->name('inventory.reports');

    // Demand Forecasting. Reading the forecast needs view_reports; saving a
    // plan needs generate_forecasts. Both are declared on the controller.
    Route::get('/inventory/demand-forecast', [DemandForecastController::class, 'index'])->name('inventory.demand-forecast');
    Route::post('/inventory/demand-forecast', [DemandForecastController::class, 'store'])->name('inventory.demand-forecast.store');

    // Evidence-Based Process Review & DPRI Reference Pricing
    Route::get('/reviews', [ProcessReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/create', [ProcessReviewController::class, 'create'])->name('reviews.create');
    Route::post('/reviews', [ProcessReviewController::class, 'store'])->name('reviews.store');
    Route::post('/reviews/check-availability', [ProcessReviewController::class, 'checkAvailability'])->name('reviews.check-availability');
    Route::get('/reviews/dpri', [ProcessReviewController::class, 'dpriIndex'])->name('reviews.dpri');
    Route::post('/reviews/dpri', [ProcessReviewController::class, 'storeDpri'])->name('reviews.dpri.store');
    Route::get('/reviews/{review}', [ProcessReviewController::class, 'show'])->name('reviews.show');
    Route::put('/reviews/{review}', [ProcessReviewController::class, 'update'])->name('reviews.update');
    Route::post('/reviews/{review}/submit', [ProcessReviewController::class, 'submit'])->name('reviews.submit');
    Route::post('/reviews/{review}/approve', [ProcessReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/reviews/{review}/reject', [ProcessReviewController::class, 'reject'])->name('reviews.reject');
    Route::post('/reviews/recommendations/{recommendation}/implement', [ProcessReviewController::class, 'implementRecommendation'])->name('reviews.recommendations.implement');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/mfa', [ProfileController::class, 'updateMfa'])->name('profile.mfa.update');
    Route::post('/profile/authenticator/setup', [AuthenticatorController::class, 'setup'])
        ->middleware('throttle:5,1')
        ->name('profile.authenticator.setup');
    Route::post('/profile/authenticator/setup-json', [AuthenticatorController::class, 'setupJson'])
        ->middleware('throttle:5,1')
        ->name('profile.authenticator.setup.json');
    // A stale error-page URL may be revisited as GET. Return to the setup UI;
    // enabling the authenticator itself remains POST-only and CSRF-protected.
    Route::get('/profile/authenticator/enable', fn () => redirect()->route('profile.edit'));
    Route::post('/profile/authenticator/enable', [AuthenticatorController::class, 'enable'])
        ->middleware('throttle:6,1')
        ->name('profile.authenticator.enable');
    Route::post('/profile/authenticator/enable-json', [AuthenticatorController::class, 'enableJson'])
        ->middleware('throttle:6,1')
        ->name('profile.authenticator.enable.json');
    Route::post('/profile/authenticator/cancel', [AuthenticatorController::class, 'cancel'])
        ->name('profile.authenticator.cancel');
    Route::delete('/profile/authenticator', [AuthenticatorController::class, 'disable'])
        ->middleware('throttle:6,1')
        ->name('profile.authenticator.disable');
});

/*
 * User Management. Administrator-only — the guard is declared on the controller
 * itself (HasMiddleware) rather than here, so a method added later cannot slip
 * out from behind it.
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/audit-trail/suggestions', [AuditLogController::class, 'suggestions'])->name('audit-logs.suggestions');
    Route::get('/audit-trail', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-trail/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::patch('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');

    // The role-versus-module matrix, generated from the same enum the gates are
    // registered from, so it cannot drift from what is actually enforced.
    Route::get('/permissions', [PermissionMatrixController::class, 'index'])->name('permissions');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin_auth.php';
require __DIR__.'/super_admin.php';
