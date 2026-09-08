<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuthenticationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticationPanelRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = AuthenticationContext::authenticatedGuard();

        if ($guard === null) {
            return $next($request);
        }

        $user = Auth::guard($guard)->user();

        if (! $user instanceof User || $this->roleMatchesGuard($user, $guard)) {
            return $next($request);
        }

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Your account access has changed. Please sign in through the appropriate panel.',
            ], 401);
        }

        return redirect()
            ->route(AuthenticationContext::loginRoute($this->guardForRole($user->role)))
            ->withErrors([
                'email' => 'Your account access has changed. Please sign in through the appropriate panel.',
            ]);
    }

    private function roleMatchesGuard(User $user, string $guard): bool
    {
        return match ($guard) {
            AuthenticationContext::SUPER_ADMIN_GUARD => $user->role === UserRole::SuperAdministrator,
            AuthenticationContext::ADMIN_GUARD => $user->role === UserRole::Administrator,
            AuthenticationContext::WEB_GUARD => ! $user->role->isAdministrator(),
            default => false,
        };
    }

    private function guardForRole(UserRole $role): string
    {
        return match ($role) {
            UserRole::SuperAdministrator => AuthenticationContext::SUPER_ADMIN_GUARD,
            UserRole::Administrator => AuthenticationContext::ADMIN_GUARD,
            default => AuthenticationContext::WEB_GUARD,
        };
    }
}
