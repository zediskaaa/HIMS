<?php

namespace App\Enums;

/**
 * Departments that own or consume inventory in this hospital system.
 *
 * The value is stored in the existing users.department column. A separate
 * lookup table would add no relationship data today, so the enum provides one
 * validated source of truth without duplicating the current architecture.
 */
enum UserDepartment: string
{
    case Administration = 'Administration';
    case CentralSupply = 'Central Supply';
    case Warehouse = 'Warehouse';
    case Pharmacy = 'Pharmacy';
    case Laboratory = 'Laboratory';
    case Nursing = 'Nursing';
    case Procurement = 'Procurement';
    case Finance = 'Finance';
    case HumanResources = 'Human Resources';
    case InformationTechnology = 'Information Technology';
    case InternalAudit = 'Internal Audit';
    case RecordsManagement = 'Records Management';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $department) => [$department->value => $department->value])
            ->all();
    }

    /**
     * Include a legacy value only on its existing user's edit form. This keeps
     * old records editable without offering arbitrary departments to new users.
     *
     * @return array<string, string>
     */
    public static function optionsIncluding(?string $existing): array
    {
        $options = self::options();

        if (filled($existing) && ! array_key_exists($existing, $options)) {
            $options[$existing] = $existing.' (Existing department)';
        }

        return $options;
    }
}
