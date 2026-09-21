<?php

namespace App\Enums;

/**
 * Whether an account may be used.
 *
 * Accounts are deactivated rather than deleted: a user row is referenced by
 * every stock movement that person recorded, so removing it would either
 * break that history or orphan it. Deactivating keeps the audit trail whole.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Archived => 'Archived',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect([self::Active, self::Inactive])
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
