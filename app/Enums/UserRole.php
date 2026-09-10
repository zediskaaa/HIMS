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
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Super Administrator',
            self::Administrator => 'Administrator',
            self::InventoryManager => 'Inventory Manager',
            self::WarehouseStaff => 'Warehouse Staff',
            self::PharmacyStaff => 'Pharmacy Staff',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Full administrative access through the dedicated Super Admin panel.',
            self::Administrator => 'Full access, including user accounts.',
            self::InventoryManager => 'Runs the storeroom: items, procurement, forecasts.',
            self::WarehouseStaff => 'Receives and moves stock; clears alerts.',
            self::PharmacyStaff => 'Issues and dispenses stock to wards.',
            self::Viewer => 'Read-only access for auditors and observers.',
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
            self::Administrator => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission) => ! in_array($permission, [
                    Permission::ViewAuditTrail,
                    Permission::ManageWarehouseTasks,
                    Permission::ExecuteWarehouseTasks,
                    Permission::ResolveWarehouseExceptions,
                    Permission::AccessNarcoticsVault,
                ], true),
            )),

            // Owns the storeroom records: the item master, supplier directory,
            // procurement, forecasting, and the balance corrections that follow
            // a cycle count. The only role that may reshape inventory data.
            self::InventoryManager => [
                Permission::ViewInventory,
                Permission::ViewReports,
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
                Permission::IssuePurchaseOrder,
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
            ],

            // Physically handles stock: receives deliveries, transfers between
            // zones, issues to wards, and clears the alerts that result.
            self::WarehouseStaff => [
                Permission::ViewInventory,
                Permission::ViewReports,
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
            ],

            // Dispenses to wards and raises departmental requisitions.
            self::PharmacyStaff => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::IssueStock,
                Permission::CreateRequisition,
                Permission::InspectStock,
                Permission::ViewWarehouseTasks,
                Permission::ManageTelemetryExcursions,
                Permission::AccessNarcoticsVault,
                Permission::RecordConsignments,
            ],

            // Auditors and observers. Reads everything, writes nothing.
            self::Viewer => [
                Permission::ViewInventory,
                Permission::ViewReports,
                Permission::ViewWarehouseTasks,
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
