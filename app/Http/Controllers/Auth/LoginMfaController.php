<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Notifications\LoginMfaOtp;
use App\Services\AuditLogger;
use App\Services\AuthenticatorSetupService;
use App\Services\LoginLockoutService;
use App\Services\LoginMfaService;
use App\Services\PasswordExpirationService;
use App\Services\Sms\SmsMfaChallengeService;
use App\Services\Sms\SmsOtpDelivery;
use App\Support\AuthenticationPanel;
use App\Support\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class LoginMfaController extends Controller
{
    public function show(
        Request $request,
        LoginMfaService $mfa,
        LoginLockoutService $lockouts,
        AuthenticatorSetupService $authenticatorSetup,
    ): View|RedirectResponse {
        $panel = $this->panel($request);
        $user = $mfa->pendingUser($request, $panel->guard());

        if ($user === null) {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Your verification session is no longer valid. Please sign in again.']);
        }

        $method = $mfa->challengeMethod($request, $panel->guard());
        $recoverySetup = $method === LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY
            ? $authenticatorSetup->details($request, $user)
            : null;

        if ($method === LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY && $recoverySetup === null) {
            $mfa->clear($request);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Your authenticator recovery session has expired. Please sign in again.']);
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
            'method' => $method,
            'authenticatorSetup' => $recoverySetup,
            'maskedEmail' => $this->maskEmail($user->email),
            'maskedPhone' => $method === LoginMfaService::METHOD_SMS
                ? substr((string) $user->phone, 0, 2).'******'.substr((string) $user->phone, -3)
                : null,
            'expiresInMinutes' => $mfa->expiresInMinutes(),
            'expired' => $mfa->isExpired($request, $panel->guard()),
            'exhausted' => $mfa->isExhausted($request, $panel->guard()),
            'resendAvailableIn' => $mfa->resendAvailableIn($request, $panel->guard()),
        ]);
    }

    public function verify(
        Request $request,
        LoginMfaService $mfa,
        LoginLockoutService $lockouts,
        PasswordExpirationService $expiration,
        SmsMfaChallengeService $sms,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.digits' => 'Enter the complete 6-digit verification code.',
        ]);
        $panel = $this->panel($request);
        $pendingUser = $mfa->pendingUser($request, $panel->guard());
        $pendingMethod = $mfa->challengeMethod($request, $panel->guard());
        $smsAttemptKey = $pendingUser !== null && $pendingMethod === LoginMfaService::METHOD_SMS
            ? 'sms-mfa:verify:'.$pendingUser->getKey()
            : null;

        if ($smsAttemptKey !== null && RateLimiter::tooManyAttempts($smsAttemptKey, 10)) {
            $mfa->clear($request);
            $audit->log(AuditAction::SmsVerification, null, 'SMS verification attempt limit reached.', $pendingUser, 'Account', outcome: 'failure', source: 'user');

            return redirect()->route($panel->loginRoute())->withErrors(['email' => 'Too many verification attempts. Please wait before signing in again.']);
        }

        $result = $mfa->verify($request, $panel->guard(), $validated['otp']);

        if ($smsAttemptKey !== null && $result['status'] === LoginMfaService::INVALID) {
            RateLimiter::hit($smsAttemptKey, 900);
        }

        if ($pendingUser !== null && $pendingMethod === LoginMfaService::METHOD_SMS) {
            $description = match ($result['status']) {
                LoginMfaService::SUCCESS => 'SMS verification succeeded.',
                LoginMfaService::EXPIRED => 'SMS verification code expired.',
                default => ($result['attempts_remaining'] ?? 1) < 1
                    ? 'SMS verification attempt limit reached.'
                    : 'SMS verification failed.',
            };
            $audit->log(AuditAction::SmsVerification, null, $description, $pendingUser, 'Account',
                outcome: $result['status'] === LoginMfaService::SUCCESS ? 'success' : 'failure', source: 'user');
        }

        if ($result['status'] === LoginMfaService::MISSING) {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Your verification session is no longer valid. Please sign in again.']);
        }

        if ($result['status'] === LoginMfaService::EXPIRED) {
            $message = ! in_array($result['method'] ?? null, [LoginMfaService::METHOD_EMAIL, LoginMfaService::METHOD_SMS], true)
                ? 'Your authenticator verification session has expired. Please sign in again.'
                : 'This verification code has expired. Request a new code.';

            return back()->withErrors(['otp' => $message]);
        }

        if ($result['status'] === LoginMfaService::INVALID) {
            $authenticator = in_array($result['method'] ?? null, [
                LoginMfaService::METHOD_AUTHENTICATOR,
                LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY,
            ], true);
            $message = ($result['attempts_remaining'] ?? 0) > 0
                ? ($authenticator
                    ? 'Invalid authenticator code. Please try again.'
                    : 'This verification code is invalid.')
                : ($authenticator || ($result['method'] ?? null) === LoginMfaService::METHOD_SMS
                    ? 'Too many incorrect attempts. Please sign in again.'
                    : 'Too many incorrect attempts. Request a new code.');

            return back()->withErrors(['otp' => $message]);
        }

        $throttleKey = $result['login_throttle_key']
            ?? $lockouts->throttleKey($panel->value, $result['user']->email, $request->ip());

        if ($restriction = $lockouts->activeRestriction($result['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $result['user']->email, $restriction);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        if (($result['method'] ?? null) !== LoginMfaService::METHOD_SMS && $result['user']->sms_mfa_enabled) {
            $status = $sms->begin($request, $result['user'], $panel->guard(), $result['remember'], $throttleKey);

            return $status === SmsOtpDelivery::SENT
                ? redirect()->route($panel->loginMfaRoute())
                : redirect()->route($panel->loginRoute())->withErrors(['email' => $status === SmsOtpDelivery::RATE_LIMITED
                    ? 'Too many SMS code requests. Please wait before trying again.'
                    : 'We could not send a verification code. Please try signing in again.']);
        }

        if ($result['user']->passwordHasExpired()) {
            $request->session()->regenerate();
            MfaSession::mark($request, $result['user'], $panel->guard());
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
        MfaSession::mark($request, $result['user'], $panel->guard());
        $request->session()->put(
            EnforceSessionInactivity::lastActivityKey($panel->guard()),
            now()->getTimestamp(),
        );

        return redirect()->intended(route($panel->dashboardRoute(), absolute: false));
    }

    public function resend(Request $request, LoginMfaService $mfa, SmsMfaChallengeService $sms): RedirectResponse
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

        if ($result['status'] === 'unsupported') {
            return back()->withErrors([
                'otp' => 'Authenticator codes are generated by your app and cannot be resent.',
            ]);
        }

        if ($result['status'] === 'exhausted') {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => 'Too many incorrect attempts. Please sign in again.']);
        }

        if (($result['method'] ?? null) === LoginMfaService::METHOD_SMS) {
            $status = $sms->deliver($request, $result['user'], $panel->guard(), $result['otp'], true);

            return $status === SmsOtpDelivery::SENT
                ? back()->with('status', 'A new verification code has been sent.')
                : redirect()->route($panel->loginRoute())->withErrors(['email' => $status === SmsOtpDelivery::RATE_LIMITED
                    ? 'Too many SMS code requests. Please wait before trying again.'
                    : 'We could not send a verification code. Please try signing in again.']);
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
