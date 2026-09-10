<?php

namespace App\Enums;

enum ProcurementMethod: string
{
    case CompetitiveBidding = 'competitive_bidding';
    case RequestForQuotation = 'request_for_quotation';
    case DirectContracting = 'direct_contracting';
    case EmergencyProcurement = 'emergency_procurement';
    case RepeatOrder = 'repeat_order';
    case FrameworkAgreement = 'framework_agreement';

    public function label(): string
    {
        return match ($this) {
            self::CompetitiveBidding => 'Competitive Bidding / Tender',
            self::RequestForQuotation => 'Request for Quotation (RFQ)',
            self::DirectContracting => 'Direct Contracting (Single Source)',
            self::EmergencyProcurement => 'Emergency Procurement',
            self::RepeatOrder => 'Repeat Order',
            self::FrameworkAgreement => 'Framework Agreement Call-Off',
        };
    }

    public function minimumSuppliers(): int
    {
        return match ($this) {
            self::CompetitiveBidding => 3,
            self::RequestForQuotation => 3,
            self::DirectContracting => 1,
            self::EmergencyProcurement => 1,
            self::RepeatOrder => 1,
            self::FrameworkAgreement => 1,
        };
    }

    public function isSealedBidPreferred(): bool
    {
        return in_array($this, [self::CompetitiveBidding, self::RequestForQuotation], true);
    }
}
