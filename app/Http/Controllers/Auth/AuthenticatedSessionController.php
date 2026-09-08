<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Requests\Auth\LoginRequest;
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
    /**
     * Display the login view.
     */
    public function create(Request $request, LoginLockoutService $lockouts): View
    {
        return view('auth.login', [
            'loginRestriction' => $lockouts->sessionRestriction($request, AuthenticationContext::WEB_GUARD),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        LoginMfaService $mfa,
        PasswordExpirationService $expiration,
    ): RedirectResponse {
        $user = $request->validateCredentials();

        if ($user->authenticatorMfaEnabled()) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::WEB_GUARD);

            if ($pendingUser?->is($user)
                && $mfa->challengeUsesAuthenticator($request, AuthenticationContext::WEB_GUARD)) {
                return redirect()->route('login.mfa');
            }

            $request->session()->regenerate();
            $mfa->issueAuthenticator(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            return redirect()->route('login.mfa');
        }

        if ($user->mfa_enabled) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::WEB_GUARD);

            if ($pendingUser?->is($user) && $mfa->resendAvailableIn($request, AuthenticationContext::WEB_GUARD) > 0) {
                return redirect()->route('login.mfa')
                    ->with('status', 'A verification code was recently sent.');
            }

            $request->session()->regenerate();
            $otp = $mfa->issue(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            try {
                $user->notify(new LoginMfaOtp($otp, $mfa->expiresInMinutes()));
            } catch (Throwable $exception) {
                $mfa->clear($request);
                Log::error('Login MFA email could not be sent.', [
                    'user_id' => $user->getKey(),
                    'guard' => AuthenticationContext::WEB_GUARD,
                    'exception' => $exception::class,
                ]);

                return back()->withErrors([
                    'email' => 'We could not send a verification code. Please try again.',
                ])->onlyInput('email');
            }

            return redirect()->route('login.mfa');
        }

        if ($user->passwordHasExpired()) {
            $request->session()->regenerate();
            $expiration->begin(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
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
