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

    public function guard(): string
    {
        return match ($this) {
            self::Staff => AuthenticationContext::WEB_GUARD,
            self::Admin => AuthenticationContext::ADMIN_GUARD,
            self::SuperAdmin => AuthenticationContext::SUPER_ADMIN_GUARD,
        };
    }

    public function dashboardRoute(): string
    {
        return $this === self::SuperAdmin ? 'super-admin.dashboard' : 'dashboard';
    }

    public function loginMfaRoute(): string
    {
        return match ($this) {
            self::Staff => 'login.mfa',
            self::Admin => 'admin.login.mfa',
            self::SuperAdmin => 'super-admin.login.mfa',
        };
    }

    public function loginMfaVerifyRoute(): string
    {
        return match ($this) {
            self::Staff => 'login.mfa.verify',
            self::Admin => 'admin.login.mfa.verify',
            self::SuperAdmin => 'super-admin.login.mfa.verify',
        };
    }

    public function loginMfaResendRoute(): string
    {
        return match ($this) {
            self::Staff => 'login.mfa.resend',
            self::Admin => 'admin.login.mfa.resend',
            self::SuperAdmin => 'super-admin.login.mfa.resend',
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

    public function passwordOtpRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.otp',
            self::Admin => 'admin.password.otp',
            self::SuperAdmin => 'super-admin.password.otp',
        };
    }

    public function passwordOtpVerifyRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.otp.verify',
            self::Admin => 'admin.password.otp.verify',
            self::SuperAdmin => 'super-admin.password.otp.verify',
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

    public function expiredPasswordRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.expired',
            self::Admin => 'admin.password.expired',
            self::SuperAdmin => 'super-admin.password.expired',
        };
    }

    public function expiredPasswordUpdateRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.expired.update',
            self::Admin => 'admin.password.expired.update',
            self::SuperAdmin => 'super-admin.password.expired.update',
        };
    }

    public function expiredPasswordCancelRoute(): string
    {
        return match ($this) {
            self::Staff => 'password.expired.cancel',
            self::Admin => 'admin.password.expired.cancel',
            self::SuperAdmin => 'super-admin.password.expired.cancel',
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
