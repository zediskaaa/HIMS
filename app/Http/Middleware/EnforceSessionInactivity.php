<?php

namespace App\Http\Middleware;

use App\Support\AuthenticationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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

    public const LAST_ACTIVITY_RESPONSE_HEADER = 'X-Session-Activity-At';

    public const CONTEXT_COOKIE = 'hims_inactivity';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $guardName = AuthenticationContext::authenticatedGuard();
        $manualLogout = $request->isMethod('POST')
            && $request->routeIs('logout', 'admin.logout', 'super-admin.logout');
        $notice = $request->session()->get('session_timeout_context');

        if ($manualLogout || ($notice && $notice['expires_at'] <= now()->getTimestamp())) {
            $request->session()->forget(['session_timeout', 'session_timeout_context']);
        }

        if ($manualLogout) {
            Cookie::queue(Cookie::forget(self::CONTEXT_COOKIE));

            return $next($request);
        }

        if ($guardName === null) {
            // EncryptCookies authenticates this evidence independently of the
            // short-lived session. It grants no authentication or module access.
            $context = json_decode($request->cookie(self::CONTEXT_COOKIE, ''), true);
            if (is_array($context)
                && in_array($context['guard'] ?? null, AuthenticationContext::sessionGuards(), true)
                && is_int($context['deadline'] ?? null)
                && $context['deadline'] <= now()->getTimestamp()) {
                return $this->timeout($request, $context['guard']);
            }

            return $this->finish($request, $next($request));
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

        return $this->finish($request, $next($request));
    }

    private function finish(Request $request, Response $response): Response
    {
        if ($request->routeIs('session.activity', 'admin.session.activity', 'super-admin.session.activity')) {
            $request->session()->reflash();
        }

        $guard = AuthenticationContext::authenticatedGuard();

        if ($guard !== null) {
            $request->session()->forget(['session_timeout', 'session_timeout_context']);
            $lastActivity = $request->session()->get(self::lastActivityKey($guard));
            if (is_numeric($lastActivity)) {
                $response->headers->set(self::LAST_ACTIVITY_RESPONSE_HEADER, (string) $lastActivity);

                // A browser-session cookie survives storage/cookie expiry at
                // the inactivity deadline, but is cleared on logout/consumption.
                Cookie::queue(Cookie::make(self::CONTEXT_COOKIE, json_encode([
                    'guard' => $guard,
                    'deadline' => (int) $lastActivity + max(1, (int) config('session.lifetime')) * 60,
                ]), 0, config('session.path'), config('session.domain'), config('session.secure'), true,
                    false, config('session.same_site')));
            }
        } else {
            Cookie::queue(Cookie::forget(self::CONTEXT_COOKIE));
        }

        return $response;
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
        Cookie::queue(Cookie::forget(self::CONTEXT_COOKIE));

        // Keep the notice across API responses and the browser's intermediate
        // /session/expired redirect; the matching login view consumes it.
        $request->session()->put([
            'session_timeout' => true,
            'session_timeout_context' => [
                'guard' => $guardName,
                'expires_at' => now()->getTimestamp() + max(1, (int) config('session.lifetime')) * 60,
            ],
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()
                ->json([
                    'message' => 'Your session has expired due to inactivity. Please log in again.',
                    'code' => 'SESSION_TIMEOUT',
                ], 401)
                ->header('X-Session-Expired', 'true');
        }

        return redirect()
            ->route(AuthenticationContext::loginRoute($guardName));
    }
}
