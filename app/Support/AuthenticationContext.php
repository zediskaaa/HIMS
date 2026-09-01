<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Resolve which first-party session guard owns the current request.
 *
 * Shared HIMS screens accept either guard. Keeping the context in one place
 * prevents layouts, timeout handling, and account-status enforcement from
 * drifting as the Super Admin panel evolves independently.
 */
final class AuthenticationContext
{
    public const WEB_GUARD = 'web';

    public const ADMIN_GUARD = 'admin';

    public const SUPER_ADMIN_GUARD = 'super_admin';

    /**
     * @return array<int, string>
     */
    public static function sessionGuards(): array
    {
        return [self::WEB_GUARD, self::ADMIN_GUARD, self::SUPER_ADMIN_GUARD];
    }

    public static function authenticatedGuard(): ?string
    {
        $default = Auth::getDefaultDriver();
        $guards = in_array($default, self::sessionGuards(), true)
            ? [$default, ...self::sessionGuards()]
            : self::sessionGuards();

        foreach (array_unique($guards) as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    public static function isSuperAdmin(): bool
    {
        return self::authenticatedGuard() === self::SUPER_ADMIN_GUARD;
    }

    public static function isAdmin(): bool
    {
        return self::authenticatedGuard() === self::ADMIN_GUARD;
    }

    public static function dashboardRoute(): string
    {
        return self::isSuperAdmin() ? 'super-admin.dashboard' : 'dashboard';
    }

    public static function logoutRoute(): string
    {
        return match (self::authenticatedGuard()) {
            self::SUPER_ADMIN_GUARD => 'super-admin.logout',
            self::ADMIN_GUARD => 'admin.logout',
            default => 'logout',
        };
    }

    public static function loginRoute(string $guard): string
    {
        return match ($guard) {
            self::SUPER_ADMIN_GUARD => 'super-admin.login',
            self::ADMIN_GUARD => 'admin.login',
            default => 'login',
        };
    }

    public static function activityRoute(): string
    {
        return match (self::authenticatedGuard()) {
            self::SUPER_ADMIN_GUARD => 'super-admin.session.activity',
            self::ADMIN_GUARD => 'admin.session.activity',
            default => 'session.activity',
        };
    }

    public static function expiredRoute(): string
    {
        return match (self::authenticatedGuard()) {
            self::SUPER_ADMIN_GUARD => 'super-admin.session.expired',
            self::ADMIN_GUARD => 'admin.session.expired',
            default => 'session.expired',
        };
    }
}
