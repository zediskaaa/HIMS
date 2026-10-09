<?php

namespace App\Enums;

enum SupplierCompanyProfileStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::ChangesRequested => 'Changes Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
