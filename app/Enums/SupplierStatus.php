<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    // Retained for rows created by the original supplier CRUD. Accreditation
    // review now lives in SupplierAccreditationStatus instead.
    case UnderReview = 'under_review';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::UnderReview => 'Under Review',
        };
    }

    /**
     * Whether this supplier may be selected on new requisitions and POs.
     */
    public function canBeSelectedForNewOrders(): bool
    {
        return $this === self::Active;
    }

    public static function options(): array
    {
        return collect([self::Active, self::Suspended, self::Inactive])
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
