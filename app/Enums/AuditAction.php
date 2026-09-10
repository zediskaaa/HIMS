<?php

namespace App\Enums;

enum AuditAction: string
{
    case CreatedUser = 'created_user';
    case UpdatedUser = 'updated_user';
    case DeletedUser = 'deleted_user';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case ChangedPassword = 'changed_password';
    case TemporarilyLockedUser = 'temporarily_locked_user';
    case UnlockedUser = 'unlocked_user';
    case CreatedSupplier = 'created_supplier';
    case UpdatedSupplier = 'updated_supplier';
    case SubmittedSupplier = 'submitted_supplier';
    case ApprovedSupplier = 'approved_supplier';
    case RejectedSupplier = 'rejected_supplier';
    case SuspendedSupplier = 'suspended_supplier';
    case InactivatedSupplier = 'inactivated_supplier';
    case ReactivatedSupplier = 'reactivated_supplier';
    case UploadedSupplierDocument = 'uploaded_supplier_document';
    case VerifiedSupplierDocument = 'verified_supplier_document';
    case RejectedSupplierDocument = 'rejected_supplier_document';
    case AddedSupplierProduct = 'added_supplier_product';
    case UpdatedSupplierProduct = 'updated_supplier_product';
    case AddedSupplierPrice = 'added_supplier_price';
    case AddedSupplierContract = 'added_supplier_contract';
    case UpdatedSupplierContract = 'updated_supplier_contract';
    case CreatedPurchaseRequest = 'created_purchase_request';
    case ApprovedPurchaseRequest = 'approved_purchase_request';
    case CreatedSourcingRfq = 'created_sourcing_rfq';
    case PublishedSourcingRfq = 'published_sourcing_rfq';
    case SubmittedSupplierQuote = 'submitted_supplier_quote';
    case EvaluatedSourcingRfq = 'evaluated_sourcing_rfq';
    case AwardedSourcingRfq = 'awarded_sourcing_rfq';
    case IssuedPurchaseOrder = 'issued_purchase_order';
    case ApprovedPurchaseOrder = 'approved_purchase_order';
    case AmendedPurchaseOrder = 'amended_purchase_order';
    case ReceivedPurchaseOrder = 'received_purchase_order';
    case CreatedGoodsReceipt = 'created_goods_receipt';
    case PostedGoodsReceipt = 'posted_goods_receipt';
    case CompletedQualityInspection = 'completed_quality_inspection';
    case ReleasedQuarantineStock = 'released_quarantine_stock';
    case RejectedQuarantineStock = 'rejected_quarantine_stock';
    case CreatedMaterialRequisition = 'created_material_requisition';
    case ApprovedMaterialRequisition = 'approved_material_requisition';
    case RejectedMaterialRequisition = 'rejected_material_requisition';
    case CancelledMaterialRequisition = 'cancelled_material_requisition';
    case IssuedMaterialRequisition = 'issued_material_requisition';
    case AcknowledgedMaterialIssuance = 'acknowledged_material_issuance';
    case DispatchedStockTransfer = 'dispatched_stock_transfer';
    case ReceivedStockTransfer = 'received_stock_transfer';
    case ScheduledCycleCount = 'scheduled_cycle_count';
    case RecordedBlindCount = 'recorded_blind_count';
    case ApprovedInventoryAdjustment = 'approved_inventory_adjustment';
    case PostedInventoryAdjustment = 'posted_inventory_adjustment';
    case CreatedStorageLocation = 'created_storage_location';
    case UpdatedStorageLocationStatus = 'updated_storage_location_status';
    case CreatedWarehouseTask = 'created_warehouse_task';
    case AssignedWarehouseTask = 'assigned_warehouse_task';
    case StartedWarehouseTask = 'started_warehouse_task';
    case CompletedWarehouseTask = 'completed_warehouse_task';
    case CancelledWarehouseTask = 'cancelled_warehouse_task';
    case RaisedWarehouseException = 'raised_warehouse_exception';
    case ResolvedWarehouseException = 'resolved_warehouse_exception';
    case PrintedWarehouseLabel = 'printed_warehouse_label';
    case RecordedDangerousDrugTransaction = 'recorded_dangerous_drug_transaction';
    case RecordedSurgicalConsignmentUsage = 'recorded_surgical_consignment_usage';
    case UploadedLogisticsDocument = 'uploaded_logistics_document';
    case VerifiedLogisticsDocument = 'verified_logistics_document';
    case RevisedLogisticsDocument = 'revised_logistics_document';
    case ArchivedLogisticsDocument = 'archived_logistics_document';
    case CreatedInspectionAcceptanceReport = 'created_inspection_acceptance_report';
    case CompletedTechnicalInspection = 'completed_technical_inspection';
    case ApprovedIarAcceptance = 'approved_iar_acceptance';
    case RejectedIar = 'rejected_iar';
    case TransmittedIarToCoa = 'transmitted_iar_to_coa';
    case RecordedCustodyTransfer = 'recorded_custody_transfer';
    case UpdatedShipmentStatus = 'updated_shipment_status';

    public function label(): string
    {
        return match ($this) {
            self::CreatedUser => 'Created User',
            self::UpdatedUser => 'Updated User',
            self::DeletedUser => 'Deleted User',
            self::LoggedIn => 'Logged In',
            self::LoggedOut => 'Logged Out',
            self::ChangedPassword => 'Changed Password',
            self::TemporarilyLockedUser => 'Temporarily Locked User',
            self::UnlockedUser => 'Unlocked User',
            self::CreatedSupplier => 'Created Supplier',
            self::UpdatedSupplier => 'Updated Supplier',
            self::SubmittedSupplier => 'Submitted Supplier',
            self::ApprovedSupplier => 'Approved Supplier',
            self::RejectedSupplier => 'Rejected Supplier',
            self::SuspendedSupplier => 'Suspended Supplier',
            self::InactivatedSupplier => 'Inactivated Supplier',
            self::ReactivatedSupplier => 'Reactivated Supplier',
            self::UploadedSupplierDocument => 'Uploaded Supplier Document',
            self::VerifiedSupplierDocument => 'Verified Supplier Document',
            self::RejectedSupplierDocument => 'Rejected Supplier Document',
            self::AddedSupplierProduct => 'Added Supplier Product',
            self::UpdatedSupplierProduct => 'Updated Supplier Product',
            self::AddedSupplierPrice => 'Added Supplier Price',
            self::AddedSupplierContract => 'Added Supplier Contract',
            self::UpdatedSupplierContract => 'Updated Supplier Contract',
            self::CreatedPurchaseRequest => 'Created Purchase Request',
            self::ApprovedPurchaseRequest => 'Approved Purchase Request',
            self::CreatedSourcingRfq => 'Created Sourcing RFQ',
            self::PublishedSourcingRfq => 'Published Sourcing RFQ',
            self::SubmittedSupplierQuote => 'Submitted Supplier Quote',
            self::EvaluatedSourcingRfq => 'Evaluated Sourcing RFQ',
            self::AwardedSourcingRfq => 'Awarded Sourcing RFQ',
            self::IssuedPurchaseOrder => 'Issued Purchase Order',
            self::ApprovedPurchaseOrder => 'Approved Purchase Order',
            self::AmendedPurchaseOrder => 'Amended Purchase Order',
            self::ReceivedPurchaseOrder => 'Received Purchase Order',
            self::CreatedGoodsReceipt => 'Created Goods Receipt',
            self::PostedGoodsReceipt => 'Posted Goods Receipt',
            self::CompletedQualityInspection => 'Completed Quality Inspection',
            self::ReleasedQuarantineStock => 'Released Quarantine Stock',
            self::RejectedQuarantineStock => 'Rejected Quarantine Stock',
            self::CreatedMaterialRequisition => 'Created Material Requisition',
            self::ApprovedMaterialRequisition => 'Approved Material Requisition',
            self::RejectedMaterialRequisition => 'Rejected Material Requisition',
            self::CancelledMaterialRequisition => 'Cancelled Material Requisition',
            self::IssuedMaterialRequisition => 'Issued Material Requisition',
            self::AcknowledgedMaterialIssuance => 'Acknowledged Material Issuance',
            self::DispatchedStockTransfer => 'Dispatched Stock Transfer',
            self::ReceivedStockTransfer => 'Received Stock Transfer',
            self::ScheduledCycleCount => 'Scheduled Cycle Count',
            self::RecordedBlindCount => 'Recorded Blind Count',
            self::ApprovedInventoryAdjustment => 'Approved Inventory Adjustment',
            self::PostedInventoryAdjustment => 'Posted Inventory Adjustment',
            self::CreatedStorageLocation => 'Created Storage Location',
            self::UpdatedStorageLocationStatus => 'Updated Storage Location Status',
            self::CreatedWarehouseTask => 'Created Warehouse Task',
            self::AssignedWarehouseTask => 'Assigned Warehouse Task',
            self::StartedWarehouseTask => 'Started Warehouse Task',
            self::CompletedWarehouseTask => 'Completed Warehouse Task',
            self::CancelledWarehouseTask => 'Cancelled Warehouse Task',
            self::RaisedWarehouseException => 'Raised Warehouse Exception',
            self::ResolvedWarehouseException => 'Resolved Warehouse Exception',
            self::PrintedWarehouseLabel => 'Printed Warehouse Label',
            self::RecordedDangerousDrugTransaction => 'Recorded Dangerous Drug Transaction',
            self::RecordedSurgicalConsignmentUsage => 'Recorded Surgical Consignment Usage',
            self::UploadedLogisticsDocument => 'Uploaded Logistics Document',
            self::VerifiedLogisticsDocument => 'Verified Logistics Document',
            self::RevisedLogisticsDocument => 'Revised Logistics Document',
            self::ArchivedLogisticsDocument => 'Archived Logistics Document',
            self::CreatedInspectionAcceptanceReport => 'Created Inspection & Acceptance Report',
            self::CompletedTechnicalInspection => 'Completed Technical Inspection (IAR)',
            self::ApprovedIarAcceptance => 'Approved IAR Custodial Acceptance',
            self::RejectedIar => 'Rejected IAR (Defective/Non-Compliant)',
            self::TransmittedIarToCoa => 'Transmitted IAR to Resident COA Auditor',
            self::RecordedCustodyTransfer => 'Recorded Chain of Custody Transfer',
            self::UpdatedShipmentStatus => 'Updated Inbound Shipment Status',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action) => [$action->value => $action->label()])
            ->all();
    }
}
