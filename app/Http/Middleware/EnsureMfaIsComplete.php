<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $guardName = AuthenticationContext::authenticatedGuard();

        if ($guardName === null) {
            return $next($request);
        }

        $guard = Auth::guard($guardName);
        $user = $guard->user();
        $requiresMfa = $user instanceof User
            && ($user->authenticatorMfaEnabled() || $user->mfa_enabled);

        if (! $requiresMfa) {
            return $next($request);
        }

        if (! $request->hasSession()) {
            $guard->forgetUser();

            return response()->json([
                'message' => 'Multi-factor verification is required. Please sign in again.',
                'code' => 'MFA_REQUIRED',
                'redirect' => route(AuthenticationContext::loginRoute($guardName)),
            ], 401);
        }

        if (MfaSession::completed($request, $user, $guardName)) {
            return $next($request);
        }

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $location = route(AuthenticationContext::loginRoute($guardName));

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Multi-factor verification is required. Please sign in again.',
                'code' => 'MFA_REQUIRED',
                'redirect' => $location,
            ], 401);
        }

        return redirect()->route(AuthenticationContext::loginRoute($guardName))
            ->withErrors(['email' => 'Multi-factor verification is required. Please sign in again.']);
    }
}
