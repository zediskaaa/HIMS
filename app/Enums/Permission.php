<?php

namespace App\Enums;

/**
 * The individual abilities a role can grant.
 *
 * Screens and routes check a permission, never a role — so re-shuffling who
 * can do what is a change to UserRole::permissions() alone, and no controller
 * has to be touched.
 *
 * The list is deliberately finer-grained than "can use the inventory module".
 * Pharmacy staff dispense stock to wards but have no business creating item
 * records or correcting balances, so dispensing (issue_stock) is a separate
 * ability from full movement authority (record_movements), which is in turn
 * separate from balance corrections (adjust_stock). Granting a department one
 * of the three no longer hands it the other two.
 */
enum Permission: string
{
    // Read-only.
    case ViewInventory = 'view_inventory';
    case ViewReports = 'view_reports';

    // Day-to-day stock operations.
    case IssueStock = 'issue_stock';
    case RecordMovements = 'record_movements';
    case AcknowledgeAlerts = 'acknowledge_alerts';

    // Custodial — reshapes the records rather than moving the stock.
    case AdjustStock = 'adjust_stock';
    case ManageItems = 'manage_items';
    case ManageLocations = 'manage_locations';
    case ManageSuppliers = 'manage_suppliers';
    case ManageProcurement = 'manage_procurement';
    case GenerateForecasts = 'generate_forecasts';

    // Administration.
    case ManageUsers = 'manage_users';
    case ViewAuditTrail = 'view_audit_trail';

    public function label(): string
    {
        return match ($this) {
            self::ViewInventory => 'View stock levels',
            self::ViewReports => 'View reports',
            self::IssueStock => 'Issue and dispense stock',
            self::RecordMovements => 'Record stock movements',
            self::AcknowledgeAlerts => 'Acknowledge stock alerts',
            self::AdjustStock => 'Adjust stock balances',
            self::ManageItems => 'Manage inventory items',
            self::ManageLocations => 'Manage storage locations',
            self::ManageSuppliers => 'Manage suppliers',
            self::ManageProcurement => 'Manage procurement',
            self::GenerateForecasts => 'Generate demand forecasts',
            self::ManageUsers => 'Manage users',
            self::ViewAuditTrail => 'View audit trail',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ViewInventory => 'Read item records, stock levels and open alerts.',
            self::ViewReports => 'Read dashboards, stock levels and reports.',
            self::IssueStock => 'Issue and dispense stock to wards and departments.',
            self::RecordMovements => 'Receive, transfer, dispose and return stock.',
            self::AcknowledgeAlerts => 'Clear low-stock and expiry alerts.',
            self::AdjustStock => 'Correct a recorded balance after a cycle count.',
            self::ManageItems => 'Add and edit item records and categories.',
            self::ManageLocations => 'Add and edit warehouse zones, racks and bins.',
            self::ManageSuppliers => 'Maintain the supplier directory.',
            self::ManageProcurement => 'Raise and approve requisitions and purchase orders.',
            self::GenerateForecasts => 'Run demand forecasts and save plans.',
            self::ManageUsers => 'Create staff accounts, change roles, deactivate access.',
            self::ViewAuditTrail => 'Review append-only user and authentication activity.',
        };
    }

    /**
     * The screen group this ability unlocks.
     *
     * Used to render the permission matrix as module rows, which is how access
     * rules actually get reviewed — nobody verifies a flat list of twelve
     * abilities, but "who can reach Procurement" is a question with an answer.
     */
    public function module(): string
    {
        return match ($this) {
            self::ViewInventory, self::ManageItems, self::AdjustStock => 'Inventory',
            self::IssueStock, self::RecordMovements => 'Stock Movements',
            self::ManageLocations, self::AcknowledgeAlerts => 'Warehousing',
            self::ManageSuppliers, self::ManageProcurement => 'Procurement',
            self::ViewReports, self::GenerateForecasts => 'Records & Analysis',
            self::ManageUsers, self::ViewAuditTrail => 'Administration',
        };
    }

    /**
     * Every module in matrix order, each with the abilities it covers.
     *
     * @return array<string, array<int, self>>
     */
    public static function byModule(): array
    {
        $grouped = [];

        foreach (self::cases() as $permission) {
            $grouped[$permission->module()][] = $permission;
        }

        return $grouped;
    }
}
