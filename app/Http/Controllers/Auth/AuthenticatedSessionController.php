<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\PasswordExpirationService;
use App\Support\AuthenticationContext;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, PasswordExpirationService $expiration): RedirectResponse
    {
        $user = $request->validateCredentials();

        if ($user->passwordHasExpired()) {
            $request->session()->regenerate();
            $expiration->begin(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
            );

            return redirect()->route(AuthenticationPanel::Staff->expiredPasswordRoute());
        }

        $request->login($user);

        $request->session()->regenerate();
        $request->session()->put(
            EnforceSessionInactivity::LAST_ACTIVITY_AT,
            now()->getTimestamp(),
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;

        Auth::guard($guard)->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * End a verified idle browser session and carry a one-time login notice.
     *
     * The route is signed because it is reached by the browser's inactivity
     * timer with a top-level navigation rather than a form submission. A stale
     * timer after manual logout cannot create a timeout notice.
     */
    public function expired(Request $request): RedirectResponse
    {
        if (! Auth::guard(AuthenticationContext::WEB_GUARD)->check()
            || ! EnforceSessionInactivity::hasExceededInactivityLimit(
                $request,
                AuthenticationContext::WEB_GUARD,
            )) {
            return redirect()->route('login');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('session_timeout', true);
    }
}
