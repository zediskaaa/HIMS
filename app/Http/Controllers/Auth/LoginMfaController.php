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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    ): View|Response|RedirectResponse {
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

        $isAuthenticator = $mfa->challengeUsesAuthenticator($request, $panel->guard());
        $statePayload = $mfa->statePayload($request, $panel->guard());
        $isExpired = $mfa->isExpired($request, $panel->guard());
        $expiresAt = $statePayload['expires_at'] ?? now()->getTimestamp();
        $serverNow = now()->getTimestamp();
        $remainingSeconds = max(0, $expiresAt - $serverNow);

        return response()
            ->view('auth.login-mfa', [
                'panel' => $panel,
                'method' => $method,
                'isAuthenticator' => $isAuthenticator,
                'authenticatorSetup' => $recoverySetup,
                'maskedEmail' => $this->maskEmail($user->email),
                'maskedPhone' => $method === LoginMfaService::METHOD_SMS
                    ? substr((string) $user->phone, 0, 2).'******'.substr((string) $user->phone, -3)
                    : null,
                'expiresInMinutes' => $mfa->expiresInMinutes(),
                'expired' => $isExpired,
                'exhausted' => $mfa->isExhausted($request, $panel->guard()),
                'resendAvailableIn' => $mfa->resendAvailableIn($request, $panel->guard()),
                'expiresAt' => $expiresAt,
                'serverNow' => $serverNow,
                'remainingSeconds' => $remainingSeconds,
                'warningSeconds' => $mfa->warningThresholdSeconds(),
                'totalDurationSeconds' => $mfa->authenticatorTimeoutSeconds(),
                'continueUrl' => route($panel->loginMfaContinueRoute()),
                'cancelUrl' => route($panel->loginMfaCancelRoute()),
                'loginUrl' => route($panel->loginRoute()),
            ])
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function verify(
        Request $request,
        LoginMfaService $mfa,
        LoginLockoutService $lockouts,
        PasswordExpirationService $expiration,
        SmsMfaChallengeService $sms,
        AuditLogger $audit,
    ): JsonResponse|RedirectResponse {
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

            return $this->verificationFailure(
                $request,
                $panel,
                'email',
                'Too many verification attempts. Please wait before signing in again.',
                429,
                true,
            );
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
            return $this->verificationFailure(
                $request,
                $panel,
                'email',
                'Your verification session is no longer valid. Please sign in again.',
                401,
                true,
            );
        }

        if ($result['status'] === LoginMfaService::EXPIRED) {
            $message = ! in_array($result['method'] ?? null, [LoginMfaService::METHOD_EMAIL, LoginMfaService::METHOD_SMS], true)
                ? 'Your authenticator verification session has expired. Please sign in again.'
                : 'This verification code has expired. Request a new code.';

            return $this->verificationFailure($request, $panel, 'otp', $message, 410);
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

            return $this->verificationFailure($request, $panel, 'otp', $message);
        }

        $throttleKey = $result['login_throttle_key']
            ?? $lockouts->throttleKey($panel->value, $result['user']->email, $request->ip());

        if ($restriction = $lockouts->activeRestriction($result['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $result['user']->email, $restriction);

            return $this->verificationFailure(
                $request,
                $panel,
                'email',
                $lockouts->message($restriction),
                423,
                true,
            );
        }

        if (($result['method'] ?? null) !== LoginMfaService::METHOD_SMS && $result['user']->sms_mfa_enabled) {
            $status = $sms->begin($request, $result['user'], $panel->guard(), $result['remember'], $throttleKey);

            if ($status === SmsOtpDelivery::SENT) {
                return $this->verificationSuccess(
                    $request,
                    redirect()->route($panel->loginMfaRoute()),
                );
            }

            return $this->verificationFailure(
                $request,
                $panel,
                'email',
                $status === SmsOtpDelivery::RATE_LIMITED
                    ? 'Too many SMS code requests. Please wait before trying again.'
                    : 'We could not send a verification code. Please try signing in again.',
                $status === SmsOtpDelivery::RATE_LIMITED ? 429 : 503,
                true,
            );
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

            return $this->verificationSuccess(
                $request,
                redirect()->route($panel->expiredPasswordRoute()),
            );
        }

        if ($restriction = $lockouts->completeSuccessfulLogin($result['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $result['user']->email, $restriction);

            return $this->verificationFailure(
                $request,
                $panel,
                'email',
                $lockouts->message($restriction),
                423,
                true,
            );
        }

        $lockouts->clearRestriction($request);
        Auth::guard($panel->guard())->login($result['user'], $result['remember']);
        $request->session()->regenerate();
        MfaSession::mark($request, $result['user'], $panel->guard());
        $request->session()->put(
            EnforceSessionInactivity::lastActivityKey($panel->guard()),
            now()->getTimestamp(),
        );

        return $this->verificationSuccess(
            $request,
            redirect()->intended(route($panel->dashboardRoute(), absolute: false)),
        );
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

    public function continueSession(Request $request, LoginMfaService $mfa): JsonResponse|RedirectResponse
    {
        $panel = $this->panel($request);
        $result = $mfa->extendAuthenticatorSession($request, $panel->guard());

        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            $statusCode = match ($result['status']) {
                LoginMfaService::SUCCESS => 200,
                LoginMfaService::MISSING => 401,
                LoginMfaService::EXPIRED => 410,
                'cooldown' => 429,
                default => 422,
            };

            return response()->json([
                'success' => $result['status'] === LoginMfaService::SUCCESS,
                'status' => $result['status'],
                'message' => $result['message'] ?? null,
                'expires_at' => $result['expires_at'] ?? null,
                'remaining_seconds' => $result['remaining_seconds'] ?? null,
                'warning_seconds' => $result['warning_seconds'] ?? null,
                'extensions_remaining' => $result['extensions_remaining'] ?? null,
                'retry_after' => $result['retry_after'] ?? null,
                'redirect_url' => in_array($result['status'], [LoginMfaService::MISSING, LoginMfaService::EXPIRED], true)
                    ? route($panel->loginRoute())
                    : null,
            ], $statusCode);
        }

        if ($result['status'] === LoginMfaService::MISSING || $result['status'] === LoginMfaService::EXPIRED) {
            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $result['message']]);
        }

        if ($result['status'] !== LoginMfaService::SUCCESS) {
            return back()->withErrors(['otp' => $result['message']]);
        }

        return back()->with('status', 'Verification session has been extended.');
    }

    public function cancel(Request $request, LoginMfaService $mfa): JsonResponse|RedirectResponse
    {
        $panel = $this->panel($request);
        $mfa->clear($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect_url' => route($panel->loginRoute()),
            ]);
        }

        return redirect()->route($panel->loginRoute());
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from((string) $request->route('auth_panel'));
    }

    private function verificationFailure(
        Request $request,
        AuthenticationPanel $panel,
        string $field,
        string $message,
        int $status = 422,
        bool $redirectToLogin = false,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [$field => [$message]],
                'redirect_url' => $redirectToLogin ? route($panel->loginRoute()) : null,
            ], $status);
        }

        if ($redirectToLogin) {
            return redirect()->route($panel->loginRoute())->withErrors([$field => $message]);
        }

        return back()->withErrors([$field => $message]);
    }

    private function verificationSuccess(Request $request, RedirectResponse $redirect): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $redirect->getTargetUrl(),
            ]);
        }

        return $redirect;
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
