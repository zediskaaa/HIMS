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
    case ApproveAdjustment = 'approve_adjustment';
    case InspectStock = 'inspect_stock';
    case PerformCycleCount = 'perform_cycle_count';
    case TransferStock = 'transfer_stock';
    case ManageItems = 'manage_items';
    case ManageLocations = 'manage_locations';
    case ManageSuppliers = 'manage_suppliers';
    case ReviewSupplierCompliance = 'review_supplier_compliance';
    case ApproveSuppliers = 'approve_suppliers';
    case ManageProcurement = 'manage_procurement';
    case CreateRequisition = 'create_requisition';
    case ApproveRequisition = 'approve_requisition';
    case ManageSourcing = 'manage_sourcing';
    case EvaluateBids = 'evaluate_bids';
    case AwardProcurement = 'award_procurement';
    case IssuePurchaseOrder = 'issue_purchase_order';
    case ApprovePurchaseOrder = 'approve_purchase_order';
    case ReceivePurchaseOrder = 'receive_purchase_order';
    case ManageProcurementPolicy = 'manage_procurement_policy';
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
            self::ApproveAdjustment => 'Approve stock adjustments',
            self::InspectStock => 'Inspect and release quarantine stock',
            self::PerformCycleCount => 'Perform cycle counts',
            self::TransferStock => 'Transfer stock between locations',
            self::ManageItems => 'Manage inventory items',
            self::ManageLocations => 'Manage storage locations',
            self::ManageSuppliers => 'Manage suppliers',
            self::ReviewSupplierCompliance => 'Review supplier compliance',
            self::ApproveSuppliers => 'Approve and suspend suppliers',
            self::ManageProcurement => 'Manage procurement',
            self::CreateRequisition => 'Create department requisitions',
            self::ApproveRequisition => 'Approve department requisitions',
            self::ManageSourcing => 'Manage sourcing and RFQs',
            self::EvaluateBids => 'Evaluate supplier quotations and bids',
            self::AwardProcurement => 'Recommend and award sourcing events',
            self::IssuePurchaseOrder => 'Create and dispatch purchase orders',
            self::ApprovePurchaseOrder => 'Approve purchase orders & revisions',
            self::ReceivePurchaseOrder => 'Receive purchase order deliveries',
            self::ManageProcurementPolicy => 'Manage procurement categories & policy',
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
            self::AdjustStock => 'Request and submit stock adjustments.',
            self::ApproveAdjustment => 'Authorize inventory count variances and adjustments within DOA limits.',
            self::InspectStock => 'Perform quality inspection and release or reject quarantine stock.',
            self::PerformCycleCount => 'Schedule and record blind cycle counts.',
            self::TransferStock => 'Dispatch and receive internal stock transfers.',
            self::ManageItems => 'Add and edit item records and categories.',
            self::ManageLocations => 'Add and edit warehouse zones, racks and bins.',
            self::ManageSuppliers => 'Maintain the supplier directory.',
            self::ReviewSupplierCompliance => 'Verify supplier evidence and submit accreditation reviews.',
            self::ApproveSuppliers => 'Decide accreditation and control supplier availability for procurement.',
            self::ManageProcurement => 'Comprehensive procurement management.',
            self::CreateRequisition => 'Draft and submit department stock or purchase requisitions.',
            self::ApproveRequisition => 'Independently approve department requisitions within authority limits.',
            self::ManageSourcing => 'Package requirements into RFQs and invite accredited suppliers.',
            self::EvaluateBids => 'Execute comparative evaluation and landed cost scoring.',
            self::AwardProcurement => 'Recommend supplier award and initiate DOA workflow.',
            self::IssuePurchaseOrder => 'Convert sourcing awards to purchase orders and dispatch.',
            self::ApprovePurchaseOrder => 'Authorize purchase orders and revisions in DOA chain.',
            self::ReceivePurchaseOrder => 'Receive and inspect incoming purchase order deliveries.',
            self::ManageProcurementPolicy => 'Configure spend categories, cost centers, and DOA policies.',
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
            self::ViewInventory, self::ManageItems, self::AdjustStock, self::ApproveAdjustment => 'Inventory',
            self::IssueStock, self::RecordMovements, self::TransferStock => 'Stock Movements',
            self::ManageLocations, self::AcknowledgeAlerts, self::ReceivePurchaseOrder, self::InspectStock, self::PerformCycleCount => 'Warehousing',
            self::ManageSuppliers, self::ReviewSupplierCompliance, self::ApproveSuppliers, self::ManageProcurement,
            self::CreateRequisition, self::ApproveRequisition, self::ManageSourcing, self::EvaluateBids,
            self::AwardProcurement, self::IssuePurchaseOrder, self::ApprovePurchaseOrder, self::ManageProcurementPolicy => 'Procurement',
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
