<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Dispatched = 'dispatched';
    case Acknowledged = 'acknowledged';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Amended = 'amended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Dispatched => 'Dispatched',
            self::Acknowledged => 'Acknowledged by Vendor',
            self::PartiallyFulfilled => 'Partially Fulfilled',
            self::Fulfilled => 'Fulfilled / Closed',
            self::Amended => 'Amended (Revised)',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses this status is allowed to move to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Submitted, self::Cancelled],
            self::Submitted => [self::PendingApproval, self::Approved, self::Draft, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Draft, self::Cancelled],
            self::Approved => [self::Dispatched, self::Acknowledged, self::PartiallyFulfilled, self::Fulfilled, self::Amended, self::Cancelled],
            self::Dispatched => [self::Acknowledged, self::PartiallyFulfilled, self::Fulfilled, self::Amended, self::Cancelled],
            self::Acknowledged => [self::PartiallyFulfilled, self::Fulfilled, self::Amended, self::Cancelled],
            self::PartiallyFulfilled => [self::PartiallyFulfilled, self::Fulfilled, self::Amended, self::Cancelled],
            self::Fulfilled, self::Amended, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * A purchase order may receive goods once approved, dispatched, or acknowledged.
     */
    public function canReceiveStock(): bool
    {
        return in_array($this, [self::Approved, self::Dispatched, self::Acknowledged, self::PartiallyFulfilled], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Fulfilled, self::Amended, self::Cancelled], true);
    }
}
