<?php

use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Analytics\ProcessReviewController;
use App\Http\Controllers\AuthenticatorController;
use App\Http\Controllers\DashboardAiAssistantController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Inventory\ConsignmentController;
use App\Http\Controllers\Inventory\CycleCountController;
use App\Http\Controllers\Inventory\DemandForecastController;
use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\ImportController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\LogisticsController;
use App\Http\Controllers\Inventory\MaterialRequisitionController;
use App\Http\Controllers\Inventory\NarcoticsVaultController;
use App\Http\Controllers\Inventory\ProcurementController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\ScheduledReportController;
use App\Http\Controllers\Inventory\SmartWarehousingController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Inventory\StorageLocationController;
use App\Http\Controllers\Inventory\WarehouseTaskController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Privacy\ConsentController;
use App\Http\Controllers\Privacy\DsarDownloadController;
use App\Http\Controllers\PrivacyRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
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

Route::get('/dashboard', [InventoryController::class, 'index'])->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])->name('dashboard');

// Polled by the dashboard's alert panel every 30s. Sits on the web routes so
// it authenticates with the session cookie the page already has.
Route::get('/dashboard/live', [InventoryController::class, 'live'])->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])->name('dashboard.live');

// Conversational HIMS AI Assistant.
Route::post('/dashboard/ai-assistant', [DashboardAiAssistantController::class, 'chat'])
    ->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user', 'throttle:30,1'])
    ->name('dashboard.ai-assistant');
Route::get('/dashboard/ai-assistant/conversations', [DashboardAiAssistantController::class, 'conversations'])
    ->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])
    ->name('dashboard.ai-assistant.conversations');
Route::get('/dashboard/ai-assistant/conversations/active', [DashboardAiAssistantController::class, 'activeConversation'])
    ->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])
    ->name('dashboard.ai-assistant.active');
Route::get('/dashboard/ai-assistant/conversations/{id}', [DashboardAiAssistantController::class, 'showConversation'])
    ->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])
    ->name('dashboard.ai-assistant.conversation');
Route::get('/dashboard/ai-assistant/attachment/{message}', [DashboardAiAssistantController::class, 'attachment'])
    ->middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])
    ->name('dashboard.ai-assistant.attachment');

/*
 * The inventory surface. `auth` here only establishes that somebody is signed
 * in — the permission each route requires is declared on its controller via
 * HasMiddleware, so a method added later cannot slip out from behind the guard
 * by being forgotten in this file. See App\Enums\UserRole::permissions() for
 * who holds what, and /admin/permissions for the matrix that renders it.
 */
Route::middleware(['auth:web,admin,super_admin', 'verified', 'internal-user'])->group(function () {
    Route::get('/global-search', [GlobalSearchController::class, 'search'])->name('global-search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')
        ->name('notifications.read');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])
        ->whereUuid('notification')
        ->name('notifications.open');

    Route::get('/inventory', function () {
        return redirect()->route('dashboard');
    })->name('inventory');
    Route::get('/inventory/suppliers', [SupplierController::class, 'index'])->name('inventory.suppliers');
    Route::post('/inventory/suppliers', [SupplierController::class, 'store'])->name('inventory.suppliers.store');
    Route::get('/inventory/suppliers/{supplier}', [SupplierController::class, 'show'])->name('inventory.suppliers.show');
    Route::patch('/inventory/suppliers/{supplier}', [SupplierController::class, 'update'])->name('inventory.suppliers.update');
    Route::post('/inventory/suppliers/{supplier}/logo', [SupplierController::class, 'updateLogo'])
        ->middleware('throttle:10,1')
        ->name('inventory.suppliers.logo.update');
    Route::delete('/inventory/suppliers/{supplier}/logo', [SupplierController::class, 'destroyLogo'])->name('inventory.suppliers.logo.destroy');
    Route::get('/inventory/suppliers/{supplier}/logo', [SupplierController::class, 'showLogo'])->name('inventory.suppliers.logo');
    Route::post('/inventory/suppliers/{supplier}/contacts', [SupplierController::class, 'addContact'])->name('inventory.suppliers.contacts.store');
    Route::post('/inventory/suppliers/{supplier}/documents', [SupplierController::class, 'uploadDocument'])->name('inventory.suppliers.documents.store');
    Route::get('/inventory/suppliers/{supplier}/documents/{document}', [SupplierController::class, 'downloadDocument'])->name('inventory.suppliers.documents.download');
    Route::patch('/inventory/suppliers/{supplier}/documents/{document}/verification', [SupplierController::class, 'verifyDocument'])->name('inventory.suppliers.documents.verify');
    Route::post('/inventory/suppliers/{supplier}/submit', [SupplierController::class, 'submitForReview'])->name('inventory.suppliers.submit');
    Route::post('/inventory/suppliers/{supplier}/approve', [SupplierController::class, 'approve'])->name('inventory.suppliers.approve');
    Route::post('/inventory/suppliers/{supplier}/portal-users', [SupplierController::class, 'inviteUser'])->name('inventory.suppliers.portal-users.store');
    Route::post('/inventory/suppliers/{supplier}/invitations/{invitation}/resend', [SupplierController::class, 'resendInvitation'])->name('inventory.suppliers.invitations.resend');
    Route::post('/inventory/suppliers/{supplier}/invitations/{invitation}/revoke', [SupplierController::class, 'revokeInvitation'])->name('inventory.suppliers.invitations.revoke');
    Route::patch('/inventory/suppliers/{supplier}/portal-users/{portalUser}', [SupplierController::class, 'updatePortalUser'])->name('inventory.suppliers.portal-users.update');
    Route::post('/inventory/suppliers/{supplier}/reject', [SupplierController::class, 'reject'])->name('inventory.suppliers.reject');
    Route::post('/inventory/suppliers/{supplier}/request-profile-changes', [SupplierController::class, 'requestProfileChanges'])->name('inventory.suppliers.request-profile-changes');
    Route::post('/inventory/suppliers/{supplier}/suspend', [SupplierController::class, 'suspend'])->name('inventory.suppliers.suspend');
    Route::post('/inventory/suppliers/{supplier}/archive', [ArchiveController::class, 'archiveSupplier'])->name('inventory.suppliers.archive');
    Route::post('/inventory/suppliers/{supplier}/unarchive', [ArchiveController::class, 'unarchiveSupplier'])->name('inventory.suppliers.unarchive');
    Route::post('/inventory/suppliers/{supplier}/inactivate', [SupplierController::class, 'inactivate'])->name('inventory.suppliers.inactivate');
    Route::post('/inventory/suppliers/{supplier}/reactivate', [SupplierController::class, 'reactivate'])->name('inventory.suppliers.reactivate');
    Route::post('/inventory/suppliers/{supplier}/products', [SupplierController::class, 'addProduct'])->name('inventory.suppliers.products.store');
    Route::patch('/inventory/suppliers/{supplier}/products/{supplierProduct}/deactivate', [SupplierController::class, 'deactivateProduct'])->name('inventory.suppliers.products.deactivate');
    Route::patch('/inventory/suppliers/{supplier}/products/{supplierProduct}/reactivate', [SupplierController::class, 'reactivateProduct'])->name('inventory.suppliers.products.reactivate');
    Route::patch('/inventory/suppliers/{supplier}/products/{supplierProduct}/approve', [SupplierController::class, 'approveProduct'])->name('inventory.suppliers.products.approve');
    Route::patch('/inventory/suppliers/{supplier}/discrepancies/{discrepancy}/resolve', [SupplierController::class, 'resolveDiscrepancy'])->name('inventory.suppliers.discrepancies.resolve');
    Route::post('/inventory/suppliers/{supplier}/prices', [SupplierController::class, 'addPrice'])->name('inventory.suppliers.prices.store');
    Route::post('/inventory/suppliers/{supplier}/contracts', [SupplierController::class, 'addContract'])->name('inventory.suppliers.contracts.store');
    Route::patch('/inventory/suppliers/{supplier}/contracts/{contract}', [SupplierController::class, 'updateContract'])->name('inventory.suppliers.contracts.update');
    Route::get('/inventory/items', [InventoryItemController::class, 'index'])->name('inventory.items');
    Route::post('/inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
    Route::post('/inventory/items/{item}/archive', [ArchiveController::class, 'archiveItem'])->name('inventory.items.archive');
    Route::post('/inventory/items/{item}/unarchive', [ArchiveController::class, 'unarchiveItem'])->name('inventory.items.unarchive');
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
    Route::get('/inventory/warehousing/locations/{storageLocation}/items', [SmartWarehousingController::class, 'locationItems'])->name('inventory.warehousing.locations.items');
    Route::post('/inventory/warehousing/locations', [SmartWarehousingController::class, 'storeLocation'])->name('inventory.warehousing.locations.store');
    Route::get('/inventory/warehousing/scan-station', [SmartWarehousingController::class, 'scanStation'])->name('inventory.warehousing.scan-station');
    Route::post('/inventory/warehousing/lookup-barcode', [SmartWarehousingController::class, 'lookupBarcode'])->name('inventory.warehousing.lookup-barcode');

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
    Route::post('/inventory/receiving/{goodsReceiptNote}/lines/{line}/return', [GoodsReceiptController::class, 'returnRejected'])->name('inventory.receiving.return');
    Route::get('/inventory/qc', [GoodsReceiptController::class, 'qcQueue'])->name('inventory.qc.index');
    Route::post('/inventory/qc/{inspection}/release', [GoodsReceiptController::class, 'releaseQc'])->name('inventory.qc.release');
    Route::post('/inventory/qc/{inspection}/reject', [GoodsReceiptController::class, 'rejectQc'])->name('inventory.qc.reject');

    // Material Store Requisitions & Picking
    Route::get('/inventory/requisitions', [MaterialRequisitionController::class, 'index'])->name('inventory.requisitions.index');
    Route::get('/inventory/requisitions/ai-recommendation/{item}', [MaterialRequisitionController::class, 'itemAiRecommendation'])->name('inventory.requisitions.item-ai-recommendation');
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
    Route::post('/inventory/logistics/shipments/{shipment}/dock-arrival', [LogisticsController::class, 'recordDockArrival'])->name('inventory.logistics.shipments.dock-arrival');

    Route::get('/inventory/logistics/iar', [LogisticsController::class, 'iarIndex'])->name('inventory.logistics.iar.index');
    Route::post('/inventory/logistics/receipts/{goodsReceiptNote}/iar', [LogisticsController::class, 'generateIarFromReceipt'])->name('inventory.logistics.iar.generate');
    Route::get('/inventory/logistics/iar/{iar}', [LogisticsController::class, 'iarShow'])->name('inventory.logistics.iar.show');
    Route::get('/inventory/logistics/iar/{iar}/print', [LogisticsController::class, 'iarPrint'])->name('inventory.logistics.iar.print');
    Route::get('/inventory/logistics/iar/{iar}/download', [LogisticsController::class, 'iarDownload'])->name('inventory.logistics.iar.download');
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
    Route::post('/inventory/purchases/orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('inventory.purchases.orders.approve');
    Route::post('/inventory/purchases/orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('inventory.purchases.orders.reject');
    Route::post('/inventory/purchases/orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('inventory.purchases.orders.cancel');
    Route::post('/inventory/purchases/orders/{purchaseOrder}/revise', [PurchaseOrderController::class, 'revise'])->name('inventory.purchases.orders.revise');
    Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts'])->name('inventory.alerts');
    Route::get('/inventory/reports', [ReportController::class, 'index'])->name('inventory.reports');
    Route::get('/inventory/reports/generate', [ReportController::class, 'generate'])->name('inventory.reports.generate');
    Route::get('/inventory/reports/schedules', [ScheduledReportController::class, 'index'])->name('inventory.reports.schedules');
    Route::post('/inventory/reports/schedules', [ScheduledReportController::class, 'store'])->name('inventory.reports.schedules.store');
    Route::patch('/inventory/reports/schedules/{scheduledReport}', [ScheduledReportController::class, 'update'])->name('inventory.reports.schedules.update');
    Route::patch('/inventory/reports/schedules/{scheduledReport}/toggle', [ScheduledReportController::class, 'toggle'])->name('inventory.reports.schedules.toggle');
    Route::delete('/inventory/reports/schedules/{scheduledReport}', [ScheduledReportController::class, 'destroy'])->name('inventory.reports.schedules.destroy');

    // Data Import System (CSV, Excel, JSON)
    Route::get('/inventory/import', [ImportController::class, 'index'])->name('inventory.import.index');
    Route::get('/inventory/import/template', [ImportController::class, 'downloadTemplate'])->name('inventory.import.template');
    Route::post('/inventory/import/preview', [ImportController::class, 'preview'])->name('inventory.import.preview');
    Route::post('/inventory/import/commit', [ImportController::class, 'commit'])->name('inventory.import.commit');
    Route::get('/inventory/import/status/{token}', [ImportController::class, 'status'])->name('inventory.import.status');

    // Demand Forecasting. Reading the forecast needs view_reports; saving a
    // plan needs generate_forecasts. Both are declared on the controller.
    Route::get('/inventory/demand-forecast', [DemandForecastController::class, 'index'])->name('inventory.demand-forecast');
    Route::post('/inventory/demand-forecast', [DemandForecastController::class, 'store'])->name('inventory.demand-forecast.store');
    Route::post('/inventory/demand-forecast/refresh', [DemandForecastController::class, 'refresh'])->name('inventory.demand-forecast.refresh');

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
});

Route::prefix('supplier')->name('supplier.')->group(function () {
    Route::middleware(['guest:web', 'guest:admin', 'guest:super_admin'])->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
        Route::get('/login/mfa', [\App\Http\Controllers\Auth\LoginMfaController::class, 'show'])->defaults('auth_panel', \App\Support\AuthenticationPanel::Supplier->value)->name('login.mfa');
        Route::post('/login/mfa', [\App\Http\Controllers\Auth\LoginMfaController::class, 'verify'])->defaults('auth_panel', \App\Support\AuthenticationPanel::Supplier->value)->middleware('throttle:6,1')->name('login.mfa.verify');
        Route::post('/login/mfa/resend', [\App\Http\Controllers\Auth\LoginMfaController::class, 'resend'])->defaults('auth_panel', \App\Support\AuthenticationPanel::Supplier->value)->middleware('throttle:3,1')->name('login.mfa.resend');
        Route::post('/login/mfa/email', [\App\Http\Controllers\Auth\LoginMfaController::class, 'sendViaEmail'])->defaults('auth_panel', \App\Support\AuthenticationPanel::Supplier->value)->middleware('throttle:3,1')->name('login.mfa.email');
        Route::post('/login/mfa/continue', [\App\Http\Controllers\Auth\LoginMfaController::class, 'continueSession'])->defaults('auth_panel', \App\Support\AuthenticationPanel::Supplier->value)->middleware('throttle:10,1')->name('login.mfa.continue');
        Route::post('/login/mfa/cancel', [\App\Http\Controllers\Auth\LoginMfaController::class, 'cancel'])->defaults('auth_panel', \App\Support\AuthenticationPanel::Supplier->value)->name('login.mfa.cancel');
    });

    Route::middleware(['auth:web', 'verified', 'supplier-user'])->group(function () {
        Route::get('/dashboard', [SupplierPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/company-profile', [SupplierPortalController::class, 'companyProfile'])->name('company-profile.edit');
        Route::patch('/company-profile', [SupplierPortalController::class, 'saveCompanyProfile'])->name('company-profile.update');
        Route::patch('/company-profile/submit', [SupplierPortalController::class, 'submitCompanyProfile'])->name('company-profile.submit');
        Route::delete('/company-profile/documents/{document}', [SupplierPortalController::class, 'removeCompanyProfileDocument'])->name('company-profile.documents.destroy');
        Route::get('/purchase-orders', [SupplierPortalController::class, 'orders'])->name('orders.index');
        Route::get('/purchase-orders/{purchaseOrder}', [SupplierPortalController::class, 'order'])->name('orders.show');
        Route::post('/purchase-orders/{purchaseOrder}/acknowledgements', [SupplierPortalController::class, 'acknowledge'])->name('orders.acknowledge');
        Route::post('/purchase-orders/{purchaseOrder}/asns', [SupplierPortalController::class, 'storeAsn'])->name('orders.asns.store');
        Route::get('/discrepancies', [SupplierPortalController::class, 'discrepancies'])->name('discrepancies.index');
        Route::post('/discrepancies/{discrepancy}/response', [SupplierPortalController::class, 'respondToDiscrepancy'])->name('discrepancies.respond');
        Route::get('/rfqs', [SupplierPortalController::class, 'rfqs'])->name('rfqs.index');
        Route::post('/rfqs/{invitation}/bid', [SupplierPortalController::class, 'submitBid'])->name('rfqs.bid');
        Route::get('/catalog', [SupplierPortalController::class, 'catalog'])->name('catalog.index');
        Route::post('/catalog', [SupplierPortalController::class, 'submitCatalogProduct'])->name('catalog.store');
        Route::get('/compliance', [SupplierPortalController::class, 'compliance'])->name('compliance.index');
        Route::post('/compliance', [SupplierPortalController::class, 'uploadComplianceDocument'])->name('compliance.store');
        Route::get('/compliance/{document}', [SupplierPortalController::class, 'downloadComplianceDocument'])->name('compliance.download');
        Route::get('/invoices', [SupplierPortalController::class, 'invoices'])->name('invoices.index');
        Route::post('/invoices', [SupplierPortalController::class, 'submitInvoice'])->name('invoices.store');
        Route::get('/performance', [SupplierPortalController::class, 'performance'])->name('performance');
    });
});

Route::middleware('auth:web,admin,super_admin')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:10,1')
        ->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])
        ->name('profile.avatar.destroy');
    Route::get('/users/{user}/avatar', [ProfileController::class, 'showAvatar'])
        ->name('users.avatar');
    Route::patch('/profile/session-timeout-reminder', [ProfileController::class, 'updateSessionTimeoutReminder'])->name('profile.session-timeout-reminder.update');
    Route::post('/profile/audit-location', [ProfileController::class, 'storeAuditLocation'])
        ->middleware('throttle:10,1')
        ->name('profile.audit-location.store');
    Route::patch('/profile/mfa', [ProfileController::class, 'updateMfa'])->name('profile.mfa.update');
    Route::patch('/profile/sms-mfa', [ProfileController::class, 'updateSmsMfa'])
        ->middleware('throttle:6,1')
        ->name('profile.sms-mfa.update');
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
    Route::post('/profile/trusted-devices/{trustedDevice}/revoke', [ProfileController::class, 'destroyTrustedDevice'])
        ->name('profile.trusted-devices.destroy');

    // Data Subject Requests under RA 10173
    Route::post('/privacy/requests', [PrivacyRequestController::class, 'store'])
        ->name('privacy.requests.store');
    Route::post('/privacy/requests/{privacyRequest}/cancel', [PrivacyRequestController::class, 'cancel'])
        ->name('privacy.requests.cancel');
    Route::get('/privacy/requests/{privacyRequest}/download', [DsarDownloadController::class, 'download'])
        ->name('privacy.requests.download');

    // Consent Management
    Route::get('/consent/privacy-policy', [ConsentController::class, 'showPrivacyPolicyConsent'])
        ->name('consent.privacy-policy');
    Route::post('/consent/privacy-policy', [ConsentController::class, 'storePrivacyPolicyConsent'])
        ->name('consent.privacy-policy.store');
    Route::match(['post', 'patch'], '/profile/consent/optional', [ConsentController::class, 'updateOptionalConsent'])
        ->name('profile.consent.optional');
});

/*
 * Administrative modules for the Admin panel.
 * These definitions are also loaded by the Super Admin panel behind its own
 * guard, URL prefix, and route-name prefix.
 */
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:web,admin', 'verified'])
    ->group(base_path('routes/administration.php'));

require __DIR__.'/auth.php';
require __DIR__.'/admin_auth.php';
require __DIR__.'/super_admin.php';
