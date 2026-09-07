<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Requests\Auth\SuperAdminLoginRequest;
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
        return view('super-admin.auth.login', [
            'loginRestriction' => $lockouts->sessionRestriction($request, AuthenticationContext::SUPER_ADMIN_GUARD),
        ]);
    }

    public function store(
        SuperAdminLoginRequest $request,
        LoginMfaService $mfa,
        PasswordExpirationService $expiration,
    ): RedirectResponse {
        $user = $request->validateCredentials();

        if ($user->authenticatorMfaEnabled()) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::SUPER_ADMIN_GUARD);

            if ($pendingUser?->is($user)
                && $mfa->challengeMethod($request, AuthenticationContext::SUPER_ADMIN_GUARD) === LoginMfaService::METHOD_AUTHENTICATOR) {
                return redirect()->route('super-admin.login.mfa');
            }

            $request->session()->regenerate();
            $mfa->issueAuthenticator(
                $request,
                $user,
                AuthenticationContext::SUPER_ADMIN_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            return redirect()->route('super-admin.login.mfa');
        }

        if ($user->mfa_enabled) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::SUPER_ADMIN_GUARD);

            if ($pendingUser?->is($user) && $mfa->resendAvailableIn($request, AuthenticationContext::SUPER_ADMIN_GUARD) > 0) {
                return redirect()->route('super-admin.login.mfa')
                    ->with('status', 'A verification code was recently sent.');
            }

            $request->session()->regenerate();
            $otp = $mfa->issue(
                $request,
                $user,
                AuthenticationContext::SUPER_ADMIN_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            try {
                $user->notify(new LoginMfaOtp($otp, $mfa->expiresInMinutes()));
            } catch (Throwable $exception) {
                $mfa->clear($request);
                Log::error('Login MFA email could not be sent.', [
                    'user_id' => $user->getKey(),
                    'guard' => AuthenticationContext::SUPER_ADMIN_GUARD,
                    'exception' => $exception::class,
                ]);

                return back()->withErrors([
                    'email' => 'We could not send a verification code. Please try again.',
                ])->onlyInput('email');
            }

            return redirect()->route('super-admin.login.mfa');
        }

        if ($user->passwordHasExpired()) {
            $request->session()->regenerate();
            $expiration->begin(
                $request,
                $user,
                AuthenticationContext::SUPER_ADMIN_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            return redirect()->route(AuthenticationPanel::SuperAdmin->expiredPasswordRoute());
        }

        $request->login($user);
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
