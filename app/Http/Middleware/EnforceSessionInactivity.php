<?php

namespace App\Http\Middleware;

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
        $guard = Auth::guard('web');

        if (! $request->hasSession() || ! $guard->check()) {
            return $next($request);
        }

        $lastActivityAt = $request->session()->get(self::LAST_ACTIVITY_AT);
        $lifetimeInSeconds = max(1, (int) config('session.lifetime')) * 60;
        $now = now()->getTimestamp();

        // A remember-me cookie must never silently recreate a session after
        // the inactivity window. A normal form login seeds the timestamp.
        $restoredByRememberCookie = $lastActivityAt === null && $guard->viaRemember();
        $inactivityLimitReached = is_numeric($lastActivityAt)
            && ($now - (int) $lastActivityAt) >= $lifetimeInSeconds;

        if ($restoredByRememberCookie || $inactivityLimitReached) {
            return $this->timeout($request);
        }

        // Passive requests remain protected and can trigger expiration, but
        // they do not count as user activity and therefore cannot extend it.
        if ($request->header(self::PASSIVE_ACTIVITY_HEADER) !== 'passive') {
            $request->session()->put(self::LAST_ACTIVITY_AT, $now);
        }

        return $next($request);
    }

    private function timeout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->logout();

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
            ->route('login')
            ->with('session_timeout', true);
    }
}
