<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PasswordExpirationService;
use App\Support\AuthenticationContext;
use App\Support\AuthenticationPanel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsCurrent
{
    public function __construct(private readonly PasswordExpirationService $expiration) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guardName = AuthenticationContext::authenticatedGuard();

        if ($guardName === null) {
            $user = $request->user();

            if (! $user instanceof User || ! $user->passwordHasExpired()) {
                return $next($request);
            }

            $panel = AuthenticationPanel::forRole($user->role);
            $location = route($panel->loginRoute());

            return response()->json([
                'message' => 'Your password has expired. Sign in through the HIMS login page to create a new password.',
                'code' => 'PASSWORD_EXPIRED',
                'redirect' => $location,
            ], 428)->header('X-HIMS-Password-Expired', $location);
        }

        $guard = Auth::guard($guardName);
        $user = $guard->user();

        if (! $user instanceof User || ! $user->passwordHasExpired()) {
            return $next($request);
        }

        $panel = AuthenticationPanel::forGuard($guardName);
        $location = route($panel->expiredPasswordRoute());

        if (! $request->hasSession()) {
            $guard->logoutCurrentDevice();

            return response()->json([
                'message' => 'Your password has expired. Please create a new password to continue.',
                'code' => 'PASSWORD_EXPIRED',
                'redirect' => $location,
            ], 428)->header('X-HIMS-Password-Expired', $location);
        }

        $this->expiration->begin($request, $user, $guardName, $guard->viaRemember());
        $guard->logoutCurrentDevice();
        $request->session()->regenerate();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Your password has expired. Please create a new password to continue.',
                'code' => 'PASSWORD_EXPIRED',
                'redirect' => $location,
            ], 428)->header('X-HIMS-Password-Expired', $location);
        }

        return redirect()->route($panel->expiredPasswordRoute());
    }
}
