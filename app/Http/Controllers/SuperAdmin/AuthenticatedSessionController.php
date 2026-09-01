<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Requests\Auth\SuperAdminLoginRequest;
use App\Support\AuthenticationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('super-admin.auth.login');
    }

    public function store(SuperAdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->put(
            EnforceSessionInactivity::lastActivityKey(AuthenticationContext::SUPER_ADMIN_GUARD),
            now()->getTimestamp(),
        );

        return redirect()->intended(route('super-admin.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard(AuthenticationContext::SUPER_ADMIN_GUARD)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super-admin.login');
    }

    public function expired(Request $request): RedirectResponse
    {
        if (! Auth::guard(AuthenticationContext::SUPER_ADMIN_GUARD)->check()
            || ! EnforceSessionInactivity::hasExceededInactivityLimit(
                $request,
                AuthenticationContext::SUPER_ADMIN_GUARD,
            )) {
            return redirect()->route('super-admin.login');
        }

        Auth::guard(AuthenticationContext::SUPER_ADMIN_GUARD)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('super-admin.login')
            ->with('session_timeout', true);
    }
}
