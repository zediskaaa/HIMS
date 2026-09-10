<?php

namespace App\Enums;

enum ApprovalChainType: string
{
    case PurchaseRequest = 'purchase_request';
    case SourcingAward = 'sourcing_award';
    case PurchaseOrder = 'purchase_order';
    case ChangeOrder = 'change_order';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseRequest => 'Purchase Request (PR)',
            self::SourcingAward => 'Sourcing Award Recommendation',
            self::PurchaseOrder => 'Purchase Order (PO)',
            self::ChangeOrder => 'Change Order / PO Revision',
        };
    }
}
