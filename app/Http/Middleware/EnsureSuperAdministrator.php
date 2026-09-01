<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence in depth for the dedicated Super Admin surface.
 *
 * The super_admin guard proves a separate authentication flow was used; this
 * role check proves the authenticated account is actually a Super Admin.
 */
class EnsureSuperAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdministrator(), 403);

        return $next($request);
    }
}
