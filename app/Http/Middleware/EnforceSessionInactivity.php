<?php

namespace App\Http\Middleware;

use App\Support\AuthenticationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce the authenticated web session's configured inactivity limit.
 *
 * Laravel's session handler already expires idle storage after
 * config('session.lifetime') minutes. This explicit timestamp also prevents
 * passive background requests (such as dashboard polling) from extending an
 * unattended login and gives the application a deterministic timeout response.
 */
class EnforceSessionInactivity
{
    public const LAST_ACTIVITY_AT = 'auth.last_activity_at';

    public const PASSIVE_ACTIVITY_HEADER = 'X-Session-Activity';

    public function handle(Request $request, Closure $next): Response
    {
        $guardName = AuthenticationContext::authenticatedGuard();

        if (! $request->hasSession() || $guardName === null) {
            return $next($request);
        }

        $guard = Auth::guard($guardName);
        $lastActivityKey = self::lastActivityKey($guardName);
        $lastActivityAt = $request->session()->get($lastActivityKey);
        $now = now()->getTimestamp();

        // A remember-me cookie must never silently recreate a session after
        // the inactivity window. A normal form login seeds the timestamp.
        $restoredByRememberCookie = $lastActivityAt === null && $guard->viaRemember();
        $inactivityLimitReached = self::hasExceededInactivityLimit($request, $guardName, $now);

        if ($restoredByRememberCookie || $inactivityLimitReached) {
            return $this->timeout($request, $guardName);
        }

        // Passive requests remain protected and can trigger expiration, but
        // they do not count as user activity and therefore cannot extend it.
        if ($request->header(self::PASSIVE_ACTIVITY_HEADER) !== 'passive') {
            $request->session()->put($lastActivityKey, $now);
        }

        return $next($request);
    }

    public static function lastActivityKey(string $guard): string
    {
        return $guard === AuthenticationContext::WEB_GUARD
            ? self::LAST_ACTIVITY_AT
            : "auth.{$guard}.last_activity_at";
    }

    public static function hasExceededInactivityLimit(
        Request $request,
        string $guard,
        ?int $now = null,
    ): bool {
        $lastActivityAt = $request->session()->get(self::lastActivityKey($guard));
        $lifetimeInSeconds = max(1, (int) config('session.lifetime')) * 60;

        return is_numeric($lastActivityAt)
            && (($now ?? now()->getTimestamp()) - (int) $lastActivityAt) >= $lifetimeInSeconds;
    }

    private function timeout(Request $request, string $guardName): JsonResponse|RedirectResponse
    {
        Auth::guard($guardName)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()
                ->json([
                    'message' => 'Your session has expired due to inactivity. Please log in again.',
                    'code' => 'SESSION_TIMEOUT',
                ], 401)
                ->header('X-Session-Expired', 'true');
        }

        return redirect()
            ->route(AuthenticationContext::loginRoute($guardName))
            ->with('session_timeout', true);
    }
}
