<?php

namespace App\Enums;

enum RequisitionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Sourcing = 'sourcing';
    case PoConverted = 'po_converted';
    case Converted = 'converted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval, self::Submitted => 'Pending Approval',
            self::Approved => 'Approved',
            self::Sourcing => 'In Sourcing / RFQ',
            self::PoConverted, self::Converted => 'PO Converted',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Submitted, self::Cancelled],
            self::PendingApproval, self::Submitted => [self::Approved, self::Rejected, self::Draft, self::Cancelled],
            self::Approved => [self::Sourcing, self::PoConverted, self::Converted, self::Cancelled],
            self::Sourcing => [self::PoConverted, self::Converted, self::Cancelled],
            self::Rejected => [self::Draft, self::Cancelled],
            self::PoConverted, self::Converted, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Only an approved or sourcing requisition may be turned into a purchase order.
     */
    public function canConvertToPurchaseOrder(): bool
    {
        return in_array($this, [self::Approved, self::Sourcing], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
