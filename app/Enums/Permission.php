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
    case ViewProcurement = 'view_procurement';

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
    case ManageLogisticsRecords = 'manage_logistics_records';
    case VerifyLogisticsDocuments = 'verify_logistics_documents';
    case PerformTechnicalInspection = 'perform_technical_inspection';
    case ApproveIarAcceptance = 'approve_iar_acceptance';
    case ManageChainOfCustody = 'manage_chain_of_custody';

    // Administration.
    case ManageUsers = 'manage_users';
    case ViewAuditTrail = 'view_audit_trail';

    public function label(): string
    {
        return match ($this) {
            self::ViewInventory => 'View stock levels',
            self::ViewReports => 'View reports',
            self::ViewSuppliers => 'View supplier profiles',
            self::ViewProcurement => 'View procurement and purchase orders',
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
            self::ManageLogisticsRecords => 'Register shipments, DRs, and logistics records',
            self::VerifyLogisticsDocuments => 'Verify, approve, and review logistics document records',
            self::PerformTechnicalInspection => 'Conduct technical inspection and sign IAR inspection portion',
            self::ApproveIarAcceptance => 'Accept deliveries and post IAR to inventory ledger',
            self::ManageChainOfCustody => 'Record and sign chain-of-custody transfer events',
            self::ManageUsers => 'Manage users',
            self::ViewAuditTrail => 'View audit trail',
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
            self::ViewSuppliers => 'Read vendor profiles, compliance records, and performance scorecards.',
            self::ViewProcurement => 'Read purchase requests, RFQs, comparative evaluations, and purchase orders.',
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
            self::ReviewSupplierCompliance => 'Upload, inspect and verify compliance documents.',
            self::ApproveSuppliers => 'Grant accreditation or suspend active suppliers.',
            self::ManageProcurement => 'Approve or reject departmental purchase requests.',
            self::CreateRequisition => 'Submit purchase requests for departmental stock.',
            self::ApproveRequisition => 'Authorize departmental purchase requests within delegation limits.',
            self::ManageSourcing => 'Create and manage RFQ bidding packages and tenders.',
            self::EvaluateBids => 'Score commercial and technical quotations.',
            self::AwardProcurement => 'Issue procurement awards to winning vendors.',
            self::IssuePurchaseOrder => 'Generate and dispatch binding purchase orders.',
            self::ApprovePurchaseOrder => 'Authorize high-value commitments and PO revisions.',
            self::ReceivePurchaseOrder => 'Log delivery arrivals, check packing slips, route to quarantine.',
            self::ViewWarehouseTasks => 'Read warehouse task assignments, history, and active exceptions.',
            self::ManageWarehouseTasks => 'Plan, schedule, assign, and cancel warehouse work orders.',
            self::ExecuteWarehouseTasks => 'Operate barcode workstations and record scan-assisted task steps.',
            self::ResolveWarehouseExceptions => 'Override routing conflicts and clear quarantine exceptions.',
            self::PrintWarehouseLabels => 'Generate and record internal location, task, and inventory labels.',
            self::ManageWarehouseTopology => 'Manage warehouses, zones, aisles, racks, shelves, and bins.',
            self::ManageTelemetryExcursions => 'Oversee IoT cold-chain temperature telemetry and release holds.',
            self::AccessNarcoticsVault => 'Participate in dual-custody narcotics vault storage and dispensing.',
            self::RecordConsignments => 'Scan and record operating room consignment implant usage.',
            self::ManageProcurementPolicy => 'Configure spend categories, cost centers, and DOA policies.',
            self::GenerateForecasts => 'Run demand forecasts and save plans.',
            self::ViewLogisticsRecords => 'Read logistics dashboards, documents, shipments, and IARs.',
            self::ManageLogisticsRecords => 'Create shipment tracking records and upload logistics documentation.',
            self::VerifyLogisticsDocuments => 'Formally verify authenticity, tax, and regulatory compliance of documents.',
            self::PerformTechnicalInspection => 'Execute physical specification, expiration, and cold-chain compliance checks.',
            self::ApproveIarAcceptance => 'Take custodial responsibility, calculate liquidated damages, and increment ledger.',
            self::ManageChainOfCustody => 'Log custody transitions, condition assessments, and handover signatures.',
            self::ManageUsers => 'Create staff accounts, change roles, deactivate access.',
            self::ViewAuditTrail => 'Review append-only user and authentication activity.',
            self::ViewProcessReviews => 'Inspect KPI reviews, supplier scorecards, DPRI savings, and bottleneck velocity.',
            self::CreateProcessReview => 'Initiate and synthesize operational telemetry and transaction datasets into reviews.',
            self::ApproveProcessReview => 'BAC or Hospital Administrator sign-off for operational review audits.',
            self::ImplementProcessReview => 'Mark corrective interventions and supplier CAPAs as implemented.',
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
            self::ViewSuppliers, self::ViewProcurement, self::ManageSuppliers, self::ReviewSupplierCompliance, self::ApproveSuppliers, self::ManageProcurement,
            self::CreateRequisition, self::ApproveRequisition, self::ManageSourcing, self::EvaluateBids,
            self::AwardProcurement, self::IssuePurchaseOrder, self::ApprovePurchaseOrder, self::ManageProcurementPolicy => 'Procurement',
            self::ViewLogisticsRecords, self::ManageLogisticsRecords, self::VerifyLogisticsDocuments,
            self::PerformTechnicalInspection, self::ApproveIarAcceptance, self::ManageChainOfCustody => 'Logistics & Records',
            self::ViewReports, self::GenerateForecasts, self::ViewProcessReviews,
            self::CreateProcessReview, self::ApproveProcessReview, self::ImplementProcessReview => 'Records & Analysis',
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
