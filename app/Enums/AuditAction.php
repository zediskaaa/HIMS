<?php

namespace App\Enums;

enum AuditAction: string
{
    case CreatedUser = 'created_user';
    case UpdatedUser = 'updated_user';
    case DeletedUser = 'deleted_user';
    case LoggedIn = 'logged_in';
    case FailedLogin = 'failed_login';
    case LoggedOut = 'logged_out';
    case ChangedPassword = 'changed_password';
    case ChangedMfa = 'changed_mfa';
    case SmsVerification = 'sms_verification';
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
    case ArchivedSupplier = 'archived_supplier';
    case UnarchivedSupplier = 'unarchived_supplier';
    case CreatedInventoryItem = 'created_inventory_item';
    case UpdatedInventoryItem = 'updated_inventory_item';
    case DeletedInventoryItem = 'deleted_inventory_item';
    case ArchivedInventoryItem = 'archived_inventory_item';
    case UnarchivedInventoryItem = 'unarchived_inventory_item';
    case ArchivedUser = 'archived_user';
    case UnarchivedUser = 'unarchived_user';
    case RecordedStockMovement = 'recorded_stock_movement';
    case GeneratedDemandForecast = 'generated_demand_forecast';
    case RefreshedDemandForecast = 'refreshed_demand_forecast';
    case FailedDemandForecast = 'failed_demand_forecast';
    case UploadedSupplierDocument = 'uploaded_supplier_document';
    case DownloadedSupplierDocument = 'downloaded_supplier_document';
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
    case RejectedPurchaseOrder = 'rejected_purchase_order';
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
    case ExportedNarcoticsReport = 'exported_narcotics_report';
    case UploadedLogisticsDocument = 'uploaded_logistics_document';
    case DownloadedLogisticsDocument = 'downloaded_logistics_document';
    case VerifiedLogisticsDocument = 'verified_logistics_document';
    case RevisedLogisticsDocument = 'revised_logistics_document';
    case ArchivedLogisticsDocument = 'archived_logistics_document';
    case DeletedLogisticsDocument = 'deleted_logistics_document';
    case CreatedInspectionAcceptanceReport = 'created_inspection_acceptance_report';
    case CompletedTechnicalInspection = 'completed_technical_inspection';
    case ApprovedIarAcceptance = 'approved_iar_acceptance';
    case RejectedIar = 'rejected_iar';
    case TransmittedIarToCoa = 'transmitted_iar_to_coa';
    case RecordedCustodyTransfer = 'recorded_custody_transfer';
    case UpdatedShipmentStatus = 'updated_shipment_status';
    case ShipmentDispatched = 'shipment_dispatched';
    case ShipmentArrivedDock = 'shipment_arrived_dock';
    case CreatedProcessReview = 'created_process_review';
    case UpdatedProcessReview = 'updated_process_review';
    case SubmittedProcessReview = 'submitted_process_review';
    case ApprovedProcessReview = 'approved_process_review';
    case RejectedProcessReview = 'rejected_process_review';
    case ImplementedProcessRecommendation = 'implemented_process_recommendation';
    case TriggeredRecoveryAction = 'triggered_recovery_action';
    case ResolvedRecoveryIncident = 'resolved_recovery_incident';
    case SystemOperationFailed = 'system_operation_failed';
    case SystemOperationRecovered = 'system_operation_recovered';
    case SystemHealthMaintenance = 'system_health_maintenance';
    case AnalyzedAiChatAttachment = 'analyzed_ai_chat_attachment';
    case FailedAiChatAttachment = 'failed_ai_chat_attachment';
    case SubmittedPrivacyRequest = 'submitted_privacy_request';
    case ApprovedPrivacyRequest = 'approved_privacy_request';
    case FulfilledPrivacyRequest = 'fulfilled_privacy_request';
    case DownloadedPrivacyPackage = 'downloaded_privacy_package';
    case ResolvedPrivacyRequest = 'resolved_privacy_request';
    case RecordedSecurityIncident = 'recorded_security_incident';
    case UpdatedSecurityIncident = 'updated_security_incident';
    case ExecutedDataRetention = 'executed_data_retention';
    case ExportedSystemReport = 'exported_system_report';

    public function label(): string
    {
        return match ($this) {
            self::CreatedUser => 'Created User',
            self::UpdatedUser => 'Updated User',
            self::DeletedUser => 'Deleted User',
            self::LoggedIn => 'Logged In',
            self::FailedLogin => 'Failed Login',
            self::LoggedOut => 'Logged Out',
            self::ChangedPassword => 'Changed Password',
            self::ChangedMfa => 'Changed MFA',
            self::SmsVerification => 'SMS Verification',
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
            self::ArchivedSupplier => 'Archived Supplier',
            self::UnarchivedSupplier => 'Unarchived Supplier',
            self::CreatedInventoryItem => 'Created Inventory Item',
            self::UpdatedInventoryItem => 'Updated Inventory Item',
            self::DeletedInventoryItem => 'Deleted Inventory Item',
            self::ArchivedInventoryItem => 'Archived Inventory Item',
            self::UnarchivedInventoryItem => 'Unarchived Inventory Item',
            self::ArchivedUser => 'Archived User',
            self::UnarchivedUser => 'Unarchived User',
            self::RecordedStockMovement => 'Recorded Stock Movement',
            self::GeneratedDemandForecast => 'Generated Demand Forecast',
            self::RefreshedDemandForecast => 'Refreshed Demand Forecast',
            self::FailedDemandForecast => 'Failed Demand Forecast',
            self::UploadedSupplierDocument => 'Uploaded Supplier Document',
            self::DownloadedSupplierDocument => 'Downloaded Supplier Document',
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
            self::RejectedPurchaseOrder => 'Rejected Purchase Order',
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
            self::ExportedNarcoticsReport => 'Exported Narcotics Report',
            self::UploadedLogisticsDocument => 'Uploaded Logistics Document',
            self::DownloadedLogisticsDocument => 'Downloaded Logistics Document',
            self::VerifiedLogisticsDocument => 'Verified Logistics Document',
            self::RevisedLogisticsDocument => 'Revised Logistics Document',
            self::ArchivedLogisticsDocument => 'Archived Logistics Document',
            self::DeletedLogisticsDocument => 'Deleted Logistics Document',
            self::CreatedInspectionAcceptanceReport => 'Created Inspection & Acceptance Report',
            self::CompletedTechnicalInspection => 'Completed Technical Inspection (IAR)',
            self::ApprovedIarAcceptance => 'Approved IAR Custodial Acceptance',
            self::RejectedIar => 'Rejected IAR (Defective/Non-Compliant)',
            self::TransmittedIarToCoa => 'Transmitted IAR to Resident COA Auditor',
            self::RecordedCustodyTransfer => 'Recorded Chain of Custody Transfer',
            self::UpdatedShipmentStatus => 'Updated Inbound Shipment Status',
            self::ShipmentDispatched => 'Shipment Dispatched',
            self::ShipmentArrivedDock => 'Shipment Arrived at Dock',
            self::CreatedProcessReview => 'Created Process Review',
            self::UpdatedProcessReview => 'Updated Process Review',
            self::SubmittedProcessReview => 'Submitted Process Review',
            self::ApprovedProcessReview => 'Approved Process Review',
            self::RejectedProcessReview => 'Rejected Process Review',
            self::ImplementedProcessRecommendation => 'Implemented Process Recommendation',
            self::TriggeredRecoveryAction => 'Triggered Recovery Action',
            self::ResolvedRecoveryIncident => 'Resolved Recovery Incident',
            self::SystemOperationFailed => 'System Operation Failed',
            self::SystemOperationRecovered => 'System Operation Recovered',
            self::SystemHealthMaintenance => 'System Health Maintenance',
            self::AnalyzedAiChatAttachment => 'Analyzed AI Chat Attachment',
            self::FailedAiChatAttachment => 'Failed AI Chat Attachment',
            self::SubmittedPrivacyRequest => 'Submitted Privacy Request',
            self::ApprovedPrivacyRequest => 'Approved Privacy Request',
            self::FulfilledPrivacyRequest => 'Fulfilled Privacy Request',
            self::DownloadedPrivacyPackage => 'Downloaded Privacy Export Package',
            self::ResolvedPrivacyRequest => 'Resolved Privacy Request',
            self::RecordedSecurityIncident => 'Recorded Security Incident',
            self::UpdatedSecurityIncident => 'Updated Security Incident',
            self::ExecutedDataRetention => 'Executed Data Retention',
            self::ExportedSystemReport => 'Exported System Report',
        };
    }

    public function module(): string
    {
        return match (true) {
            in_array($this, [
                self::LoggedIn,
                self::FailedLogin,
                self::LoggedOut,
                self::ChangedPassword,
                self::ChangedMfa,
                self::SmsVerification,
                self::TemporarilyLockedUser,
                self::UnlockedUser,
            ], true) => 'Authentication',
            in_array($this, [self::CreatedUser, self::UpdatedUser, self::DeletedUser, self::ArchivedUser, self::UnarchivedUser], true) => 'User Administration',
            str_contains($this->value, 'privacy_request')
                || str_contains($this->value, 'privacy_package')
                || str_contains($this->value, 'security_incident')
                || $this === self::ExecutedDataRetention => 'Privacy & Security Governance',
            $this === self::ExportedSystemReport => 'Reports & Analytics',
            str_contains($this->value, 'ai_chat') => 'AI Assistant',
            str_contains($this->value, 'supplier') && ! in_array($this, [self::SubmittedSupplierQuote], true) => 'Supplier Management',
            str_contains($this->value, 'purchase_request')
                || str_contains($this->value, 'sourcing_rfq')
                || str_contains($this->value, 'purchase_order')
                || $this === self::SubmittedSupplierQuote => 'Procurement',
            str_contains($this->value, 'material_requisition') || $this === self::AcknowledgedMaterialIssuance => 'Store Requisitions',
            str_contains($this->value, 'inventory_item') => 'Item Master',
            $this === self::RecordedStockMovement => 'Inventory Movements',
            str_contains($this->value, 'demand_forecast') => 'Demand Forecasting',
            str_contains($this->value, 'cycle_count') => 'Cycle Counts',
            str_contains($this->value, 'inventory_adjustment') => 'Inventory Adjustments',
            str_contains($this->value, 'stock_transfer') => 'Stock Transfers',
            str_contains($this->value, 'goods_receipt')
                || str_contains($this->value, 'quality_inspection')
                || str_contains($this->value, 'quarantine_stock') => 'Receiving & Quality',
            str_contains($this->value, 'storage_location') => 'Storage Locations',
            str_contains($this->value, 'warehouse')
                || str_contains($this->value, 'dangerous_drug')
                || str_contains($this->value, 'narcotics')
                || str_contains($this->value, 'consignment') => 'Warehousing',
            str_contains($this->value, 'logistics_document')
                || str_contains($this->value, 'iar')
                || str_contains($this->value, 'custody_transfer')
                || str_contains($this->value, 'shipment')
                || $this === self::CompletedTechnicalInspection => 'Logistics',
            str_contains($this->value, 'process_review')
                || $this === self::ImplementedProcessRecommendation => 'Process Reviews',
            str_contains($this->value, 'recovery')
                || str_contains($this->value, 'system_operation')
                || $this === self::SystemHealthMaintenance => 'System Recovery',
            default => 'System',
        };
    }

    public function category(): string
    {
        return match ($this->module()) {
            'Authentication', 'Privacy & Security Governance' => 'Security',
            'User Administration' => 'Administration',
            'Supplier Management', 'Procurement' => 'Supplier & Procurement',
            'Logistics' => 'Logistics',
            'Process Reviews' => 'Governance',
            'System', 'System Recovery' => 'System',
            default => 'Inventory & Warehousing',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function modules(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action) => [$action->module() => $action->module()])
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action) => [$action->category() => $action->category()])
            ->sortKeys()
            ->all();
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
