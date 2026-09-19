<?php

namespace App\Http\Middleware;

use App\Support\AuthenticationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistoryCache
{
    /**
     * Prevent browsers and intermediate proxies from caching authenticated pages,
     * dashboards, and user sessions.
     *
     * In accordance with OWASP security guidelines (WSTG-ATHN-06) and HIMS
     * security requirements, this ensures that clicking browser Back/Forward
     * or restoring history after logout or session timeout cannot expose
     * previously viewed protected records.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        return self::applyHeaders($request, $response);
    }

    public static function applyHeaders(Request $request, Response $response): Response
    {
        // Do not alter binary asset responses like user avatars or document downloads if custom-cached
        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        $isAuthenticated = AuthenticationContext::authenticatedGuard() !== null
            || $request->user() !== null;

        $isProtectedPath = $request->is(
            'super-admin',
            'super-admin/*',
            'admin',
            'admin/*',
            'inventory',
            'inventory/*',
            'profile',
            'profile/*',
            'dashboard',
            'dashboard/*',
            'notifications',
            'notifications/*',
            'analytics',
            'analytics/*',
            'global-search',
            'global-search/*',
            'dtrs',
            'dtrs/*',
            'reports',
            'reports/*'
        );

        $isAuthTransition = $request->routeIs(
            'logout',
            'admin.logout',
            'super-admin.logout',
            'session.expired',
            'admin.session.expired',
            'super-admin.session.expired'
        );

        if ($isAuthenticated || $isProtectedPath || $isAuthTransition) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
        }

        return $response;
    }
}
