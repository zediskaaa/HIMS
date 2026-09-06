<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Notifications\LoginMfaOtp;
use App\Services\LoginLockoutService;
use App\Services\LoginMfaService;
use App\Services\PasswordExpirationService;
use App\Support\AuthenticationContext;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, LoginLockoutService $lockouts): View
    {
        return view('admin.auth.login', [
            'loginRestriction' => $lockouts->sessionRestriction($request, AuthenticationContext::ADMIN_GUARD),
        ]);
    }

    public function store(
        AdminLoginRequest $request,
        LoginMfaService $mfa,
        PasswordExpirationService $expiration,
    ): RedirectResponse {
        $user = $request->validateCredentials();

        if ($user->mfa_enabled) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::ADMIN_GUARD);

            if ($pendingUser?->is($user) && $mfa->resendAvailableIn($request, AuthenticationContext::ADMIN_GUARD) > 0) {
                return redirect()->route('admin.login.mfa')
                    ->with('status', 'A verification code was recently sent.');
            }

            $request->session()->regenerate();
            $otp = $mfa->issue(
                $request,
                $user,
                AuthenticationContext::ADMIN_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            try {
                $user->notify(new LoginMfaOtp($otp, $mfa->expiresInMinutes()));
            } catch (Throwable $exception) {
                $mfa->clear($request);
                Log::error('Login MFA email could not be sent.', [
                    'user_id' => $user->getKey(),
                    'guard' => AuthenticationContext::ADMIN_GUARD,
                    'exception' => $exception::class,
                ]);

                return back()->withErrors([
                    'email' => 'We could not send a verification code. Please try again.',
                ])->onlyInput('email');
            }

            return redirect()->route('admin.login.mfa');
        }

        if ($user->passwordHasExpired()) {
            $request->session()->regenerate();
            $expiration->begin(
                $request,
                $user,
                AuthenticationContext::ADMIN_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            return redirect()->route(AuthenticationPanel::Admin->expiredPasswordRoute());
        }

        $request->login($user);
        $request->session()->regenerate();
        $request->session()->put(
            EnforceSessionInactivity::lastActivityKey(AuthenticationContext::ADMIN_GUARD),
            now()->getTimestamp(),
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard(AuthenticationContext::ADMIN_GUARD)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function expired(Request $request): RedirectResponse
    {
        if (! Auth::guard(AuthenticationContext::ADMIN_GUARD)->check()
            || ! EnforceSessionInactivity::hasExceededInactivityLimit(
                $request,
                AuthenticationContext::ADMIN_GUARD,
            )) {
            return redirect()->route('admin.login');
        }

        Auth::guard(AuthenticationContext::ADMIN_GUARD)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with('session_timeout', true);
    }
}
