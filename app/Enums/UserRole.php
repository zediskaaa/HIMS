<?php

namespace App\Enums;

/**
 * Who a staff member is, and therefore what they may do.
 *
 * Roles are stored as a single column on `users` rather than in pivot tables.
 * A hospital storeroom has a handful of well-known job functions, not
 * arbitrary permission sets, so the extra tables would carry no information
 * this enum does not already state — and this version is readable at a glance.
 *
 * If per-user overrides are ever needed, the Gate layer in AppServiceProvider
 * is the only thing that has to change; the checks in controllers and views
 * ask about a Permission and stay as they are.
 */
enum UserRole: string
{
    case SuperAdministrator = 'super_administrator';
    case Administrator = 'administrator';
    case InventoryManager = 'inventory_manager';
    case WarehouseStaff = 'warehouse_staff';
    case PharmacyStaff = 'pharmacy_staff';
    case Auditor = 'auditor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Super Administrator',
            self::Administrator => 'Administrator',
            self::InventoryManager => 'Inventory Manager',
            self::WarehouseStaff => 'Warehouse Staff',
            self::PharmacyStaff => 'Pharmacy Staff',
            self::Auditor => 'Auditor',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Full administrative access through the dedicated Super Admin panel.',
            self::Administrator => 'Broad operational and user-account access, excluding reserved audit and high-risk warehouse duties.',
            self::InventoryManager => 'Runs the storeroom: items, procurement, forecasts.',
            self::WarehouseStaff => 'Receives and moves stock; clears alerts.',
            self::PharmacyStaff => 'Issues and dispenses stock to wards.',
            self::Auditor => 'Reviews organization-wide operational reports and the append-only Audit Trail.',
            self::Viewer => 'Read-only operational access for observers.',
        };
    }

    /**
     * The abilities this role holds, mapped to what the job actually involves.
     *
     * Read the list downwards: every role sees stock and reports, because a
     * storeroom nobody can look into is useless. Above that the grants narrow
     * to the work each department is accountable for, and nothing wider.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Kept as its own match arm so its permissions can be narrowed or
            // expanded later without changing the Administrator role.
            self::SuperAdministrator => Permission::cases(),

            // System/IT administrator: user provisioning, module configuration,
            // spatial topology, and read-only operational oversight. Stripped of
            // physical stock mutations, PO creation/approval, and ledger corrections.
            self::Administrator => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewSuppliers,
                Permission::ViewSupplierSensitiveData,
                Permission::ReviewSupplierCompliance,
                Permission::ApproveSuppliers,
                Permission::ViewProcurement,
                Permission::ViewProcurementSensitiveData,
                Permission::ViewLogisticsRecords,
                Permission::ViewLogisticsSensitiveData,
                Permission::ViewProcessReviews,
                Permission::ApproveProcessReview,
                Permission::ApproveRequisition,
                Permission::ApproveAdjustment,
                Permission::ManageUsers,
                Permission::ManageProcurementPolicy,
                Permission::ManageLocations,
                Permission::ViewWarehouseTasks,
                Permission::ManageWarehouseTopology,
                Permission::PrintWarehouseLabels,
                Permission::GenerateForecasts,
            ],

            // Owns the storeroom records: the item master, supplier directory,
            // procurement, forecasting, and the balance corrections that follow
            // a cycle count. Segregation of duties prevents dock receiving,
            // clinical technical inspection, or signing off audit reviews.
            self::InventoryManager => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewSuppliers,
                Permission::ViewSupplierSensitiveData,
                Permission::ViewProcurement,
                Permission::ViewProcurementSensitiveData,
                Permission::IssueStock,
                Permission::RecordMovements,
                Permission::AcknowledgeAlerts,
                Permission::AdjustStock,
                Permission::ApproveAdjustment,
                Permission::InspectStock,
                Permission::PerformCycleCount,
                Permission::TransferStock,
                Permission::ManageItems,
                Permission::ManageLocations,
                Permission::ManageSuppliers,
                Permission::ReviewSupplierCompliance,
                Permission::ManageProcurement,
                Permission::CreateRequisition,
                Permission::ApproveRequisition,
                Permission::ManageSourcing,
                Permission::EvaluateBids,
                Permission::AwardProcurement,
                Permission::IssuePurchaseOrder,
                Permission::ApprovePurchaseOrder,
                Permission::ReceivePurchaseOrder,
                Permission::GenerateForecasts,
                Permission::ViewWarehouseTasks,
                Permission::ManageWarehouseTasks,
                Permission::ExecuteWarehouseTasks,
                Permission::ResolveWarehouseExceptions,
                Permission::PrintWarehouseLabels,
                Permission::ManageWarehouseTopology,
                Permission::ManageTelemetryExcursions,
                Permission::AccessNarcoticsVault,
                Permission::RecordConsignments,
                Permission::ViewLogisticsRecords,
                Permission::ViewLogisticsSensitiveData,
                Permission::ManageLogisticsRecords,
                Permission::VerifyLogisticsDocuments,
                Permission::ApproveIarAcceptance,
                Permission::ManageChainOfCustody,
                Permission::ViewProcessReviews,
                Permission::CreateProcessReview,
                Permission::ImplementProcessReview,
            ],

            // Physically handles stock: receives deliveries at the dock, transfers
            // between zones, issues to wards, and clears alerts. No supplier access,
            // no PO creation, and no ledger adjustment authority.
            self::WarehouseStaff => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewProcurement,
                Permission::IssueStock,
                Permission::RecordMovements,
                Permission::AcknowledgeAlerts,
                Permission::ReceivePurchaseOrder,
                Permission::ViewWarehouseTasks,
                Permission::ExecuteWarehouseTasks,
                Permission::PrintWarehouseLabels,
                Permission::InspectStock,
                Permission::PerformCycleCount,
                Permission::TransferStock,
                Permission::RecordConsignments,
                Permission::ViewLogisticsRecords,
                Permission::ViewLogisticsSensitiveData,
                Permission::ManageLogisticsRecords,
                Permission::ManageChainOfCustody,
            ],

            // Dispenses to wards, raises departmental and purchase requisitions,
            // receives transfers into dispensing units, and signs technical inspections.
            // Blocked from suppliers and smart warehousing execution.
            self::PharmacyStaff => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewProcurement,
                Permission::IssueStock,
                Permission::CreateRequisition,
                Permission::TransferStock,
                Permission::InspectStock,
                Permission::AccessNarcoticsVault,
                Permission::RecordConsignments,
                Permission::ViewLogisticsRecords,
                Permission::ViewLogisticsSensitiveData,
                Permission::PerformTechnicalInspection,
                Permission::VerifyLogisticsDocuments,
            ],

            // Independent regulatory compliance and QA: strictly read-only
            // visibility into operational records and the immutable audit trail.
            // Auditors observe evidence; they never create, verify, approve, or
            // otherwise change the evidence or workflow being audited.
            self::Auditor => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewSuppliers,
                Permission::ViewSupplierSensitiveData,
                Permission::ViewProcurement,
                Permission::ViewProcurementSensitiveData,
                Permission::ViewWarehouseTasks,
                Permission::ViewLogisticsRecords,
                Permission::ViewLogisticsSensitiveData,
                Permission::ViewProcessReviews,
                Permission::ViewAuditTrail,
            ],

            // Passive executive observers: read-only operational summaries and
            // dashboards without transactional or warehouse footprint.
            self::Viewer => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewSuppliers,
                Permission::ViewProcurement,
                Permission::ViewLogisticsRecords,
                Permission::ViewProcessReviews,
            ],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Only an administrator may reach the user-management screens. Kept as a
     * named check so the intent reads clearly at the call site.
     */
    public function isAdministrator(): bool
    {
        return in_array($this, [self::SuperAdministrator, self::Administrator], true);
    }

    public function isSuperAdministrator(): bool
    {
        return $this === self::SuperAdministrator;
    }

    /**
     * Value => label, for populating a <select>.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
