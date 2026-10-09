<?php

namespace App\Enums;

use App\Models\InventoryItem;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\RfqSupplierInvitation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseTask;
use App\Support\AuthenticationPanel;

/**
 * Allowlisted notification destinations. Notification data never contains an
 * arbitrary URL, and access is checked again when a user follows the link.
 */
enum NotificationDestination: string
{
    case Dashboard = 'dashboard';
    case InventoryAlerts = 'inventory_alerts';
    case MaterialRequisition = 'material_requisition';
    case InventoryAdjustments = 'inventory_adjustments';
    case Procurement = 'procurement';
    case Import = 'import';
    case Profile = 'profile';
    case QualityControl = 'quality_control';
    case GoodsReceipt = 'goods_receipt';
    case WarehouseTask = 'warehouse_task';
    case SupplierManagement = 'supplier_management';
    case SupplierCompanyProfile = 'supplier_company_profile';
    case SupplierCompliance = 'supplier_compliance';
    case SupplierRfqs = 'supplier_rfqs';
    case SupplierOrder = 'supplier_order';
    case LogisticsDocuments = 'logistics_documents';

    /** @param array<string, scalar|null> $parameters */
    public function isAuthorizedFor(User $user, array $parameters = []): bool
    {
        return match ($this) {
            self::Dashboard => $user->isActive(),
            self::Profile => true,
            self::InventoryAlerts => $user->hasPermission(Permission::AcknowledgeAlerts),
            self::MaterialRequisition => $user->hasPermission(Permission::ApproveRequisition)
                || $user->hasPermission(Permission::ViewInventory)
                || $user->hasPermission(Permission::CreateRequisition),
            self::InventoryAdjustments => $user->hasPermission(Permission::ApproveAdjustment),
            self::Procurement => $user->hasPermission(Permission::ViewProcurement),
            self::Import => $user->hasPermission(Permission::ManageItems)
                || $user->hasPermission(Permission::ManageLocations)
                || $user->hasPermission(Permission::ManageSuppliers),
            self::QualityControl => $user->hasPermission(Permission::InspectStock),
            self::GoodsReceipt => $user->hasPermission(Permission::ViewInventory)
                || $user->hasPermission(Permission::ReceivePurchaseOrder),
            self::WarehouseTask => $user->hasPermission(Permission::ViewWarehouseTasks),
            self::SupplierManagement => $user->hasPermission(Permission::ViewSuppliers),
            self::SupplierCompanyProfile => $user->hasPermission(Permission::SupplierManageProfile)
                && $this->belongsToSupplier($user, $parameters),
            self::SupplierCompliance => $user->hasPermission(Permission::SupplierManageProfile)
                && $this->belongsToSupplier($user, $parameters),
            self::SupplierRfqs => $user->hasPermission(Permission::SupplierSubmitBids)
                && $this->belongsToSupplier($user, $parameters),
            self::SupplierOrder => $user->hasPermission(Permission::SupplierFulfillOrders)
                && PurchaseOrder::query()
                    ->whereKey((int) ($parameters['purchase_order'] ?? 0))
                    ->where('supplier_id', $user->supplier_id)
                    ->exists(),
            self::LogisticsDocuments => $user->hasPermission(Permission::ViewLogisticsRecords),
        };
    }

    /** @param array<string, scalar|null> $parameters */
    public function isAvailable(array $parameters = []): bool
    {
        return match ($this) {
            self::InventoryAlerts => empty($parameters['item'])
                || InventoryItem::query()
                    ->whereKey((int) $parameters['item'])
                    ->when(! empty($parameters['batch']), fn ($query) => $query->whereHas(
                        'batches',
                        fn ($batches) => $batches->whereKey((int) $parameters['batch'])
                    ))
                    ->exists(),
            self::MaterialRequisition => MaterialRequisition::query()
                ->whereKey((int) ($parameters['requisition'] ?? 0))
                ->exists(),
            self::WarehouseTask => WarehouseTask::query()
                ->whereKey((int) ($parameters['task'] ?? 0))->exists(),
            self::Procurement => empty($parameters['purchase_order'])
                || PurchaseOrder::query()
                    ->visibleInPipeline()
                    ->whereKey((int) $parameters['purchase_order'])
                    ->exists(),
            self::SupplierManagement, self::SupplierCompanyProfile, self::SupplierCompliance => Supplier::query()
                ->whereKey((int) ($parameters['supplier'] ?? 0))
                ->exists(),
            self::SupplierRfqs => empty($parameters['invitation'])
                || RfqSupplierInvitation::query()
                    ->whereKey((int) $parameters['invitation'])
                    ->where('supplier_id', (int) ($parameters['supplier'] ?? 0))
                    ->exists(),
            self::SupplierOrder => PurchaseOrder::query()
                ->whereKey((int) ($parameters['purchase_order'] ?? 0))
                ->exists(),
            default => true,
        };
    }

    /** @param array<string, scalar|null> $parameters */
    public function url(User $user, array $parameters = []): string
    {
        return match ($this) {
            self::Dashboard => route(AuthenticationPanel::forRole($user->role)->dashboardRoute()),
            self::InventoryAlerts => route('inventory.alerts', array_filter([
                'manage_item' => $parameters['item'] ?? null,
                'manage_batch' => $parameters['batch'] ?? null,
                'alert_type' => $parameters['alert_type'] ?? null,
            ], fn ($value): bool => $value !== null)),
            self::MaterialRequisition => route('inventory.requisitions.show', [
                'requisition' => (int) ($parameters['requisition'] ?? 0),
            ]),
            self::InventoryAdjustments => route('inventory.adjustments'),
            self::Procurement => $this->procurementUrl($parameters),
            self::Import => route('inventory.import.index'),
            self::Profile => route('profile.edit'),
            self::QualityControl => route('inventory.qc.index'),
            self::GoodsReceipt => ! empty($parameters['grn'])
                ? route('inventory.receiving.show', ['goodsReceiptNote' => (int) $parameters['grn']])
                : route('inventory.receiving.index'),
            self::WarehouseTask => route('inventory.warehouse-tasks.show', [
                'warehouseTask' => (int) ($parameters['task'] ?? 0),
            ]),
            self::SupplierManagement => route('inventory.suppliers.show', [
                'supplier' => (int) ($parameters['supplier'] ?? 0),
            ]),
            self::SupplierCompanyProfile => route('supplier.company-profile.edit'),
            self::SupplierCompliance => route('supplier.compliance.index'),
            self::SupplierRfqs => route('supplier.rfqs.index'),
            self::SupplierOrder => route('supplier.orders.show', [
                'purchaseOrder' => (int) ($parameters['purchase_order'] ?? 0),
            ]),
            self::LogisticsDocuments => route('inventory.logistics.documents'),
        };
    }

    /** @param array<string, scalar|null> $parameters */
    private function belongsToSupplier(User $user, array $parameters): bool
    {
        return $user->supplier_id !== null
            && (int) ($parameters['supplier'] ?? 0) === (int) $user->supplier_id;
    }

    /** @param array<string, scalar|null> $parameters */
    private function procurementUrl(array $parameters): string
    {
        $purchaseOrderId = (int) ($parameters['purchase_order'] ?? 0);
        $tab = $purchaseOrderId > 0 ? 'orders_revisions' : ($parameters['tab'] ?? null);
        $url = route('inventory.purchases', array_filter([
            'tab' => $tab,
            'po_id' => $purchaseOrderId > 0 ? $purchaseOrderId : null,
            'open_po' => $purchaseOrderId > 0 ? $purchaseOrderId : null,
            'po_search' => $purchaseOrderId > 0 ? null : ($parameters['po_search'] ?? null),
            'approval_search' => $parameters['approval_search'] ?? null,
        ]));

        return $purchaseOrderId > 0 ? $url.'#purchase-orders' : $url;
    }
}
