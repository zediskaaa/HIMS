<?php

namespace App\Support;

use App\Enums\UserRole;

enum AuthenticationPanel: string
{
    case Staff = 'staff';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public static function forRole(UserRole $role): self
    {
        return match ($role) {
            UserRole::SuperAdministrator => self::SuperAdmin,
            UserRole::Administrator => self::Admin,
            default => self::Staff,
        };
    }

    public static function forGuard(string $guard): self
    {
        return match ($guard) {
            AuthenticationContext::SUPER_ADMIN_GUARD => self::SuperAdmin,
            AuthenticationContext::ADMIN_GUARD => self::Admin,
            default => self::Staff,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    /**
     * @return array<int, string>
     */
    public function roleValues(): array
    {
        return match ($this) {
            self::Staff => collect(UserRole::cases())
                ->reject(fn (UserRole $role) => $role->isAdministrator())
                ->map->value
                ->all(),
            self::Admin => [UserRole::Administrator->value],
            self::SuperAdmin => [UserRole::SuperAdministrator->value],
        };
    }

    public function accepts(UserRole $role): bool
    {
        return in_array($role->value, $this->roleValues(), true);
    }

    public function loginRoute(): string
    {
        return match ($this) {
            self::Staff => 'login',
            self::Admin => 'admin.login',
            self::SuperAdmin => 'super-admin.login',
        };
    }

    public function passwordRequestRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.request',
            self::Admin => 'admin.password.request',
            self::SuperAdmin => 'super-admin.password.request',
        };
    }

    public function passwordEmailRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.email',
            self::Admin => 'admin.password.email',
            self::SuperAdmin => 'super-admin.password.email',
        };
    }

    public function passwordResetRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.reset',
            self::Admin => 'admin.password.reset',
            self::SuperAdmin => 'super-admin.password.reset',
        };
    }

    public function passwordStoreRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.store',
            self::Admin => 'admin.password.store',
            self::SuperAdmin => 'super-admin.password.store',
        };
    }

    /** @return array{message: string} */
    public function wrongPanelAlert(self $correctPanel): array
    {
        return [
            'message' => "You're using the {$this->label()} Login Panel. Please use the {$correctPanel->label()} Login Panel.",
        ];
    }
}
