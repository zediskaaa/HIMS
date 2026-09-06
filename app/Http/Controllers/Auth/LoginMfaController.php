<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Services\LoginLockoutService;
use App\Services\LoginMfaService;
use App\Services\PasswordExpirationService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class LoginMfaController extends Controller
{
    public function show(
        Request $request,
        LoginMfaService $mfa,
        LoginLockoutService $lockouts,
    ): View|RedirectResponse {
        $panel = $this->panel($request);
        $user = $mfa->pendingUser($request, $panel->guard());

        if ($user === null) {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Your verification session is no longer valid. Please sign in again.']);
        }

        $throttleKey = $this->throttleKey($request, $panel, $user, $lockouts);
        if ($restriction = $lockouts->activeRestriction($user, $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $user->email, $restriction);
            $mfa->clear($request);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        return view('auth.login-mfa', [
            'panel' => $panel,
            'maskedEmail' => $this->maskEmail($user->email),
            'expiresInMinutes' => $mfa->expiresInMinutes(),
            'expired' => $mfa->isExpired($request, $panel->guard()),
            'resendAvailableIn' => $mfa->resendAvailableIn($request, $panel->guard()),
        ]);
    }

    public function verify(
        Request $request,
        LoginMfaService $mfa,
        LoginLockoutService $lockouts,
        PasswordExpirationService $expiration,
    ): RedirectResponse {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.digits' => 'Enter the complete 6-digit verification code.',
        ]);
        $panel = $this->panel($request);
        $result = $mfa->verify($request, $panel->guard(), $validated['otp']);

        if ($result['status'] === LoginMfaService::MISSING) {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Your verification session is no longer valid. Please sign in again.']);
        }

        if ($result['status'] === LoginMfaService::EXPIRED) {
            return back()->withErrors(['otp' => 'This verification code has expired. Request a new code.']);
        }

        if ($result['status'] === LoginMfaService::INVALID) {
            $message = ($result['attempts_remaining'] ?? 0) > 0
                ? 'This verification code is invalid.'
                : 'Too many incorrect attempts. Request a new code.';

            return back()->withErrors(['otp' => $message]);
        }

        $throttleKey = $result['login_throttle_key']
            ?? $lockouts->throttleKey($panel->value, $result['user']->email, $request->ip());

        if ($restriction = $lockouts->activeRestriction($result['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $result['user']->email, $restriction);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        if ($result['user']->passwordHasExpired()) {
            $request->session()->regenerate();
            $expiration->begin(
                $request,
                $result['user'],
                $panel->guard(),
                $result['remember'],
                $throttleKey,
            );

            return redirect()->route($panel->expiredPasswordRoute());
        }

        if ($restriction = $lockouts->completeSuccessfulLogin($result['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $result['user']->email, $restriction);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        $lockouts->clearRestriction($request);
        Auth::guard($panel->guard())->login($result['user'], $result['remember']);
        $request->session()->regenerate();
        $request->session()->put(
            EnforceSessionInactivity::lastActivityKey($panel->guard()),
            now()->getTimestamp(),
        );

        return redirect()->intended(route($panel->dashboardRoute(), absolute: false));
    }

    public function resend(Request $request, LoginMfaService $mfa): RedirectResponse
    {
        $panel = $this->panel($request);
        $result = $mfa->resend($request, $panel->guard());

        if ($result['status'] === LoginMfaService::MISSING) {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Your verification session is no longer valid. Please sign in again.']);
        }

        if ($result['status'] === 'cooldown') {
            return back()->withErrors([
                'otp' => 'Please wait '.$result['retry_after'].' seconds before requesting another code.',
            ]);
        }

        try {
            $result['user']->notify(new LoginMfaOtp($result['otp'], $mfa->expiresInMinutes()));
        } catch (Throwable $exception) {
            $mfa->clear($request);
            Log::error('Login MFA email could not be sent.', [
                'user_id' => $result['user']->getKey(),
                'guard' => $panel->guard(),
                'exception' => $exception::class,
            ]);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'We could not send a verification code. Please try signing in again.']);
        }

        return back()->with('status', 'A new verification code has been sent.');
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from((string) $request->route('auth_panel'));
    }

    private function throttleKey(
        Request $request,
        AuthenticationPanel $panel,
        User $user,
        LoginLockoutService $lockouts,
    ): string {
        $state = $request->session()->get(LoginMfaService::SESSION_KEY);

        return is_string($state['login_throttle_key'] ?? null)
            ? $state['login_throttle_key']
            : $lockouts->throttleKey($panel->value, $user->email, $request->ip());
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }
}
