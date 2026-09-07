<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginMfaService
{
    public const SESSION_KEY = 'auth.login_mfa';

    public const METHOD_EMAIL = 'email';

    public const METHOD_AUTHENTICATOR = 'authenticator';

    public const SUCCESS = 'success';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const MISSING = 'missing';

    public function __construct(private readonly AuthenticatorService $authenticator) {}

    public function issue(
        Request $request,
        User $user,
        string $guard,
        bool $remember,
        ?string $loginThrottleKey = null,
    ): string {
        $previousState = $this->state($request, $guard);

        do {
            $otp = $this->generateOtp();
        } while ($previousState !== null
            && is_string($previousState['otp_hash'])
            && Hash::check($otp, $previousState['otp_hash']));

        $this->putState(
            $request,
            $user,
            $guard,
            $remember,
            $loginThrottleKey,
            self::METHOD_EMAIL,
            Hash::make($otp),
        );

        return $otp;
    }

    public function issueAuthenticator(
        Request $request,
        User $user,
        string $guard,
        bool $remember,
        ?string $loginThrottleKey = null,
    ): void {
        $this->putState(
            $request,
            $user,
            $guard,
            $remember,
            $loginThrottleKey,
            self::METHOD_AUTHENTICATOR,
        );
    }

    public function pendingUser(Request $request, string $guard): ?User
    {
        $state = $this->state($request, $guard);

        if ($state === null) {
            return null;
        }

        $user = User::query()->find($state['user_id']);
        $panel = AuthenticationPanel::forGuard($guard);
        $methodStillEnabled = $user !== null && match ($state['method']) {
            self::METHOD_AUTHENTICATOR => $user->authenticatorMfaEnabled(),
            self::METHOD_EMAIL => (bool) $user->mfa_enabled,
        };

        if ($user === null || ! $user->isActive() || ! $methodStillEnabled || ! $panel->accepts($user->role)) {
            $this->clear($request);

            return null;
        }

        return $user;
    }

    /** @return array{status: string, user?: User, remember?: bool, login_throttle_key?: ?string, attempts_remaining?: int, method?: string} */
    public function verify(Request $request, string $guard, #[\SensitiveParameter] string $otp): array
    {
        $state = $this->state($request, $guard);
        $user = $this->pendingUser($request, $guard);

        if ($state === null || $user === null) {
            return ['status' => self::MISSING];
        }

        if ($state['expires_at'] <= now()->getTimestamp()) {
            return ['status' => self::EXPIRED, 'method' => $state['method']];
        }

        $valid = match ($state['method']) {
            self::METHOD_AUTHENTICATOR => is_string($user->authenticator_secret)
                && $this->authenticator->verify($user->authenticator_secret, $otp),
            self::METHOD_EMAIL => is_string($state['otp_hash'])
                && Hash::check($otp, $state['otp_hash']),
        };

        if ($state['attempts_remaining'] < 1 || ! $valid) {
            $state['attempts_remaining'] = max(0, $state['attempts_remaining'] - 1);
            $request->session()->put(self::SESSION_KEY, $state);

            return [
                'status' => self::INVALID,
                'attempts_remaining' => $state['attempts_remaining'],
                'method' => $state['method'],
            ];
        }

        $this->clear($request);

        return [
            'status' => self::SUCCESS,
            'user' => $user,
            'remember' => $state['remember'],
            'login_throttle_key' => $state['login_throttle_key'],
            'method' => $state['method'],
        ];
    }

    /** @return array{status: string, otp?: string, user?: User, retry_after?: int} */
    public function resend(Request $request, string $guard): array
    {
        $state = $this->state($request, $guard);
        $user = $this->pendingUser($request, $guard);

        if ($state === null || $user === null) {
            return ['status' => self::MISSING];
        }

        if ($state['method'] !== self::METHOD_EMAIL) {
            return ['status' => 'unsupported'];
        }

        $retryAfter = max(0, $state['resend_available_at'] - now()->getTimestamp());

        if ($retryAfter > 0) {
            return ['status' => 'cooldown', 'retry_after' => $retryAfter];
        }

        return [
            'status' => self::SUCCESS,
            'otp' => $this->issue(
                $request,
                $user,
                $guard,
                $state['remember'],
                $state['login_throttle_key'],
            ),
            'user' => $user,
        ];
    }

    public function challengeMethod(Request $request, string $guard): ?string
    {
        return $this->state($request, $guard)['method'] ?? null;
    }

    public function isExpired(Request $request, string $guard): bool
    {
        $state = $this->state($request, $guard);

        return $state !== null && $state['expires_at'] <= now()->getTimestamp();
    }

    public function resendAvailableIn(Request $request, string $guard): int
    {
        $state = $this->state($request, $guard);

        return $state === null || $state['method'] !== self::METHOD_EMAIL
            ? 0
            : max(0, $state['resend_available_at'] - now()->getTimestamp());
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function expiresInMinutes(): int
    {
        return max(1, min(
            (int) config('auth.login_mfa.expire', 5),
            (int) config('session.lifetime', 120),
        ));
    }

    public function resendCooldownSeconds(): int
    {
        return max(1, (int) config('auth.login_mfa.resend_cooldown', 60));
    }

    private function putState(
        Request $request,
        User $user,
        string $guard,
        bool $remember,
        ?string $loginThrottleKey,
        string $method,
        ?string $otpHash = null,
    ): void {
        $now = now()->getTimestamp();

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'guard' => $guard,
            'method' => $method,
            'otp_hash' => $otpHash,
            'expires_at' => $now + ($this->expiresInMinutes() * 60),
            'attempts_remaining' => $this->maxAttempts(),
            'resend_available_at' => $now + $this->resendCooldownSeconds(),
            'remember' => $remember,
            'login_throttle_key' => $loginThrottleKey,
        ]);
    }

    /** @return array{user_id: int, guard: string, method: string, otp_hash: ?string, expires_at: int, attempts_remaining: int, resend_available_at: int, remember: bool, login_throttle_key: ?string}|null */
    private function state(Request $request, string $guard): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state)
            || ($state['guard'] ?? null) !== $guard
            || ! in_array($state['method'] ?? null, [self::METHOD_EMAIL, self::METHOD_AUTHENTICATOR], true)
            || ! is_numeric($state['user_id'] ?? null)
            || (! is_null($state['otp_hash'] ?? null) && ! is_string($state['otp_hash']))
            || ! is_numeric($state['expires_at'] ?? null)
            || ! is_numeric($state['attempts_remaining'] ?? null)
            || ! is_numeric($state['resend_available_at'] ?? null)
            || ! is_bool($state['remember'] ?? null)) {
            $this->clear($request);

            return null;
        }

        return [
            'user_id' => (int) $state['user_id'],
            'guard' => $state['guard'],
            'method' => $state['method'],
            'otp_hash' => is_string($state['otp_hash'] ?? null) ? $state['otp_hash'] : null,
            'expires_at' => (int) $state['expires_at'],
            'attempts_remaining' => (int) $state['attempts_remaining'],
            'resend_available_at' => (int) $state['resend_available_at'],
            'remember' => $state['remember'],
            'login_throttle_key' => is_string($state['login_throttle_key'] ?? null)
                ? $state['login_throttle_key']
                : null,
        ];
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('auth.login_mfa.max_attempts', 5));
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
