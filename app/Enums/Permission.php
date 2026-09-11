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
    case ViewSuppliers = 'view_suppliers';
    case ViewSupplierSensitiveData = 'view_supplier_sensitive_data';
    case ViewProcurement = 'view_procurement';
    case ViewProcurementSensitiveData = 'view_procurement_sensitive_data';

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
    case ViewWarehouseTasks = 'view_warehouse_tasks';
    case ManageWarehouseTasks = 'manage_warehouse_tasks';
    case ExecuteWarehouseTasks = 'execute_warehouse_tasks';
    case ResolveWarehouseExceptions = 'resolve_warehouse_exceptions';
    case PrintWarehouseLabels = 'print_warehouse_labels';
    case ManageWarehouseTopology = 'manage_warehouse_topology';
    case ManageTelemetryExcursions = 'manage_telemetry_excursions';
    case AccessNarcoticsVault = 'access_narcotics_vault';
    case RecordConsignments = 'record_consignments';
    case ManageProcurementPolicy = 'manage_procurement_policy';
    case GenerateForecasts = 'generate_forecasts';
    case ViewProcessReviews = 'view_process_reviews';
    case CreateProcessReview = 'create_process_review';
    case ApproveProcessReview = 'approve_process_review';
    case ImplementProcessReview = 'implement_process_review';

    // Logistics, Document Tracking & Chain of Custody (COA GAM / EOPT / GDP)
    case ViewLogisticsRecords = 'view_logistics_records';
    case ViewLogisticsSensitiveData = 'view_logistics_sensitive_data';
    case ManageLogisticsRecords = 'manage_logistics_records';
    case VerifyLogisticsDocuments = 'verify_logistics_documents';
    case PerformTechnicalInspection = 'perform_technical_inspection';
    case ApproveIarAcceptance = 'approve_iar_acceptance';
    case ManageChainOfCustody = 'manage_chain_of_custody';

    // Administration.
    case ManageUsers = 'manage_users';
    case ViewAuditTrail = 'view_audit_trail';
    case ManageSystemRecovery = 'manage_system_recovery';

    public function label(): string
    {
        return match ($this) {
            self::ViewInventory => 'View stock levels',
            self::ViewReports => 'View reports',
            self::ViewSuppliers => 'View supplier profiles',
            self::ViewSupplierSensitiveData => 'View sensitive supplier evidence and commercial data',
            self::ViewProcurement => 'View procurement and purchase orders',
            self::ViewProcurementSensitiveData => 'View sensitive procurement financials and evaluations',
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
            self::ViewWarehouseTasks => 'View warehouse tasks and scans',
            self::ManageWarehouseTasks => 'Create, assign, and cancel warehouse tasks',
            self::ExecuteWarehouseTasks => 'Execute assigned warehouse tasks',
            self::ResolveWarehouseExceptions => 'Resolve warehouse exceptions',
            self::PrintWarehouseLabels => 'Print internal warehouse labels',
            self::ManageWarehouseTopology => 'Configure warehouse zones and spatial hierarchy',
            self::ManageTelemetryExcursions => 'Monitor telemetry and release excursion holds',
            self::AccessNarcoticsVault => 'Access and execute narcotics vault operations',
            self::RecordConsignments => 'Record surgical consignment implant consumption',
            self::ManageProcurementPolicy => 'Manage procurement categories & policy',
            self::GenerateForecasts => 'Generate demand forecasts',
            self::ViewLogisticsRecords => 'View logistics and document tracking records',
            self::ViewLogisticsSensitiveData => 'View sensitive logistics evidence and custody details',
            self::ManageLogisticsRecords => 'Register shipments, DRs, and logistics records',
            self::VerifyLogisticsDocuments => 'Verify, approve, and review logistics document records',
            self::PerformTechnicalInspection => 'Conduct technical inspection and sign IAR inspection portion',
            self::ApproveIarAcceptance => 'Accept deliveries and post IAR to inventory ledger',
            self::ManageChainOfCustody => 'Record and sign chain-of-custody transfer events',
            self::ManageUsers => 'Manage users',
            self::ViewAuditTrail => 'View audit trail',
            self::ManageSystemRecovery => 'Manage system recovery and diagnostics',
            self::ViewProcessReviews => 'View evidence-based process reviews',
            self::CreateProcessReview => 'Draft evidence-based process reviews',
            self::ApproveProcessReview => 'Approve or reject process reviews (Maker-Checker)',
            self::ImplementProcessReview => 'Execute review corrective action recommendations',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ViewInventory => 'Read item records, stock levels and open alerts.',
            self::ViewReports => 'Read dashboards, stock levels and reports.',
            self::ViewSuppliers => 'Read the supplier directory, qualification status, and operational summary.',
            self::ViewSupplierSensitiveData => 'Read supplier contacts, tax identifiers, addresses, compliance files, pricing, contracts, and internal history.',
            self::ViewProcurement => 'Read procurement status, quantities, suppliers, and purchase-order fulfillment records.',
            self::ViewProcurementSensitiveData => 'Read budgets, unit costs, total commitments, quotations, commercial terms, and internal bid evaluations.',
            self::IssueStock => 'Issue and dispense stock to wards and departments.',
            self::RecordMovements => 'Receive, transfer, dispose and return stock.',
            self::AcknowledgeAlerts => 'Acknowledge stock alerts.',
            self::AdjustStock => 'Correct counted balances on the item record.',
            self::ApproveAdjustment => 'Authorize stock adjustments exceeding threshold.',
            self::InspectStock => 'Conduct QA/QC assay and release lots.',
            self::PerformCycleCount => 'Conduct physical inventory counts.',
            self::TransferStock => 'Move stock between warehouse locations.',
            self::ManageItems => 'Create, edit and categorize items in the master catalogue.',
            self::ManageLocations => 'Create, rename and organize storage locations.',
            self::ManageSuppliers => 'Create and maintain vendor records and catalogues.',
            self::ReviewSupplierCompliance => 'Audit licenses, certifications, and compliance status.',
            self::ApproveSuppliers => 'Sign off on new vendor registration, suspension, or blacklisting.',
            self::ManageProcurement => 'Oversee end-to-end procurement contracts, vendors, and purchasing orders.',
            self::CreateRequisition => 'Submit purchase requests on behalf of departments.',
            self::ApproveRequisition => 'Sign off and release department purchase requests.',
            self::ManageSourcing => 'Draft and configure Request for Quotations (RFQs).',
            self::EvaluateBids => 'Evaluate submitted supplier pricing, specifications, and proposals.',
            self::AwardProcurement => 'Approve abstract of quotation and award contracts.',
            self::IssuePurchaseOrder => 'Generate and transmit formal purchase orders.',
            self::ApprovePurchaseOrder => 'Authorize high-value purchase orders and revisions.',
            self::ReceivePurchaseOrder => 'Record warehouse dock receipts against purchase orders.',
            self::ViewWarehouseTasks => 'Read warehouse put-away, pick, and staging tasks.',
            self::ManageWarehouseTasks => 'Dispatch and reassign warehouse staff tasks.',
            self::ExecuteWarehouseTasks => 'Perform scanning and task confirmations on mobile workstation.',
            self::ResolveWarehouseExceptions => 'Override discrepant counts and barcode scan exceptions.',
            self::PrintWarehouseLabels => 'Generate bin, pallet, and GS1-128 compliance labels.',
            self::ManageWarehouseTopology => 'Create zones, aisles, racks, and volumetric constraints.',
            self::ManageTelemetryExcursions => 'Acknowledge temperature excursions and release quarantine hold.',
            self::AccessNarcoticsVault => 'Perform double-signoff witness transactions in high-security vaults.',
            self::RecordConsignments => 'Log implant utilization and prepare bill-only PO conversions.',
            self::ManageProcurementPolicy => 'Configure spend thresholds, approval hierarchies, and procurement routes.',
            self::GenerateForecasts => 'Run demand forecasting and reorder level algorithms.',
            self::ViewLogisticsRecords => 'Read logistics tracking, bill of lading, DRs, and inspection reports.',
            self::ViewLogisticsSensitiveData => 'Read commercial logistics invoices, freight costs, and custody certificates.',
            self::ManageLogisticsRecords => 'Register inbound shipments, courier tracking, and inspection documents.',
            self::VerifyLogisticsDocuments => 'Verify document conformity and sign off logistics QA checkpoints.',
            self::PerformTechnicalInspection => 'Conduct technical spec verification and sign IAR inspection portion.',
            self::ApproveIarAcceptance => 'Accept deliveries and post IAR to inventory ledger.',
            self::ManageChainOfCustody => 'Record and sign chain-of-custody transfer events.',
            self::ManageUsers => 'Create, deactivate and provision user accounts.',
            self::ViewAuditTrail => 'Read the append-only organization-wide Audit Trail.',
            self::ManageSystemRecovery => 'Access Super Admin Recovery Center, retry failed operations, and inspect system health.',
            self::ViewProcessReviews => 'Read evidence-based process review records, findings, and metrics.',
            self::CreateProcessReview => 'Create and submit operational process reviews for supervisory audit.',
            self::ApproveProcessReview => 'Review, approve, or reject operational process review recommendations.',
            self::ImplementProcessReview => 'Record and post execution of corrective action recommendations.',
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
            self::ManageLocations, self::AcknowledgeAlerts, self::ReceivePurchaseOrder, self::InspectStock,
            self::PerformCycleCount, self::ViewWarehouseTasks, self::ManageWarehouseTasks,
            self::ExecuteWarehouseTasks, self::ResolveWarehouseExceptions, self::PrintWarehouseLabels,
            self::ManageWarehouseTopology, self::ManageTelemetryExcursions, self::AccessNarcoticsVault,
            self::RecordConsignments => 'Warehousing',
            self::ViewSuppliers, self::ViewSupplierSensitiveData, self::ViewProcurement, self::ViewProcurementSensitiveData, self::ManageSuppliers, self::ReviewSupplierCompliance, self::ApproveSuppliers, self::ManageProcurement,
            self::CreateRequisition, self::ApproveRequisition, self::ManageSourcing, self::EvaluateBids,
            self::AwardProcurement, self::IssuePurchaseOrder, self::ApprovePurchaseOrder, self::ManageProcurementPolicy => 'Procurement',
            self::ViewLogisticsRecords, self::ViewLogisticsSensitiveData, self::ManageLogisticsRecords, self::VerifyLogisticsDocuments,
            self::PerformTechnicalInspection, self::ApproveIarAcceptance, self::ManageChainOfCustody => 'Logistics & Records',
            self::ViewReports, self::GenerateForecasts, self::ViewProcessReviews,
            self::CreateProcessReview, self::ApproveProcessReview, self::ImplementProcessReview => 'Records & Analysis',
            self::ManageUsers, self::ViewAuditTrail, self::ManageSystemRecovery => 'Administration',
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
