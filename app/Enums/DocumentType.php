<?php

namespace App\Enums;

enum DocumentType: string
{
    case PurchaseOrder = 'purchase_order';
    case DeliveryReceipt = 'delivery_receipt';
    case Invoice = 'invoice';
    case SalesInvoice = 'sales_invoice';
    case PurchaseOrderAttachment = 'po_attachment';
    case Quotation = 'quotation';
    case Contract = 'contract';
    case InspectionReport = 'inspection_report';
    case IarReport = 'iar_report';
    case RisSlip = 'ris_slip';
    case Waybill = 'waybill';
    case BillOfLading = 'bill_of_lading';
    case PackingList = 'packing_list';
    case CertificateOfAnalysis = 'certificate_of_analysis';
    case ChainOfCustodyRecord = 'chain_of_custody_record';
    case TemperatureLog = 'temperature_log';
    case NonConformanceReport = 'non_conformance_report';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseOrder => 'Purchase Order (PO)',
            self::DeliveryReceipt => 'Delivery Receipt (DR)',
            self::Invoice, self::SalesInvoice => 'Sales Invoice (SI)',
            self::PurchaseOrderAttachment => 'PO Supporting Attachment',
            self::Quotation => 'Supplier Quotation / Bid',
            self::Contract => 'Procurement Contract / Agreement',
            self::InspectionReport => 'Quality Inspection Report',
            self::IarReport => 'Inspection & Acceptance Report (IAR)',
            self::RisSlip => 'Requisition & Issue Slip (RIS)',
            self::Waybill => 'Shipping Waybill / Consignment Note',
            self::BillOfLading => 'Bill of Lading (B/L)',
            self::PackingList => 'Packing Slip / Packing List',
            self::CertificateOfAnalysis => 'Certificate of Analysis (COA) / CPR',
            self::ChainOfCustodyRecord => 'Chain of Custody Handover Record',
            self::TemperatureLog => 'Cold-Chain Temperature Data Logger Log',
            self::NonConformanceReport => 'Non-Conformance / Discrepancy Report',
            self::Other => 'Other Supporting Document',
        };
    }

    /**
     * Short prefix used when generating document numbers.
     */
    public function numberPrefix(): string
    {
        return match ($this) {
            self::PurchaseOrder => 'PO',
            self::DeliveryReceipt => 'DR',
            self::Invoice, self::SalesInvoice => 'SI',
            self::PurchaseOrderAttachment => 'POA',
            self::Quotation => 'QTN',
            self::Contract => 'CTR',
            self::InspectionReport => 'IR',
            self::IarReport => 'IAR',
            self::RisSlip => 'RIS',
            self::Waybill => 'WB',
            self::BillOfLading => 'BL',
            self::PackingList => 'PL',
            self::CertificateOfAnalysis => 'COA',
            self::ChainOfCustodyRecord => 'COC',
            self::TemperatureLog => 'TLOG',
            self::NonConformanceReport => 'NCR',
            self::Other => 'DOC',
        };
    }

    public function abbreviation(): string
    {
        return $this->numberPrefix();
    }

    public function napRetentionYears(): int
    {
        return match ($this) {
            self::DeliveryReceipt, self::PackingList, self::Waybill, self::BillOfLading, self::TemperatureLog, self::Other => 2,
            self::Invoice, self::SalesInvoice, self::Quotation => 5,
            default => 10,
        };
    }
}
