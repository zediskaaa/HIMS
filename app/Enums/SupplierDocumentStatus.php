<?php

namespace App\Enums;

enum SupplierDocumentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }
}
