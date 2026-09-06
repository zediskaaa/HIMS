<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginMfaService
{
    public const SESSION_KEY = 'auth.login_mfa';

    public const SUCCESS = 'success';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const MISSING = 'missing';

    public function issue(Request $request, User $user, string $guard, bool $remember): string
    {
        $previousState = $this->state($request, $guard);

        do {
            $otp = $this->generateOtp();
        } while ($previousState !== null && Hash::check($otp, $previousState['otp_hash']));

        $now = now()->getTimestamp();

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'guard' => $guard,
            'otp_hash' => Hash::make($otp),
            'expires_at' => $now + ($this->expiresInMinutes() * 60),
            'attempts_remaining' => $this->maxAttempts(),
            'resend_available_at' => $now + $this->resendCooldownSeconds(),
            'remember' => $remember,
        ]);

        return $otp;
    }

    public function pendingUser(Request $request, string $guard): ?User
    {
        $state = $this->state($request, $guard);

        if ($state === null) {
            return null;
        }

        $user = User::query()->find($state['user_id']);
        $panel = AuthenticationPanel::forGuard($guard);

        if ($user === null || ! $user->isActive() || ! $user->mfa_enabled || ! $panel->accepts($user->role)) {
            $this->clear($request);

            return null;
        }

        return $user;
    }

    /**
     * @return array{status: string, user?: User, remember?: bool, attempts_remaining?: int}
     */
    public function verify(Request $request, string $guard, #[\SensitiveParameter] string $otp): array
    {
        $state = $this->state($request, $guard);
        $user = $this->pendingUser($request, $guard);

        if ($state === null || $user === null) {
            return ['status' => self::MISSING];
        }

        if ($state['expires_at'] <= now()->getTimestamp()) {
            return ['status' => self::EXPIRED];
        }

        if ($state['attempts_remaining'] < 1 || ! Hash::check($otp, $state['otp_hash'])) {
            $state['attempts_remaining'] = max(0, $state['attempts_remaining'] - 1);
            $request->session()->put(self::SESSION_KEY, $state);

            return [
                'status' => self::INVALID,
                'attempts_remaining' => $state['attempts_remaining'],
            ];
        }

        $this->clear($request);

        return [
            'status' => self::SUCCESS,
            'user' => $user,
            'remember' => $state['remember'],
        ];
    }

    /**
     * @return array{status: string, otp?: string, user?: User, retry_after?: int}
     */
    public function resend(Request $request, string $guard): array
    {
        $state = $this->state($request, $guard);
        $user = $this->pendingUser($request, $guard);

        if ($state === null || $user === null) {
            return ['status' => self::MISSING];
        }

        $retryAfter = max(0, $state['resend_available_at'] - now()->getTimestamp());

        if ($retryAfter > 0) {
            return ['status' => 'cooldown', 'retry_after' => $retryAfter];
        }

        return [
            'status' => self::SUCCESS,
            'otp' => $this->issue($request, $user, $guard, $state['remember']),
            'user' => $user,
        ];
    }

    public function isExpired(Request $request, string $guard): bool
    {
        $state = $this->state($request, $guard);

        return $state !== null && $state['expires_at'] <= now()->getTimestamp();
    }

    public function resendAvailableIn(Request $request, string $guard): int
    {
        $state = $this->state($request, $guard);

        return $state === null
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

    /**
     * @return array{user_id: int, guard: string, otp_hash: string, expires_at: int, attempts_remaining: int, resend_available_at: int, remember: bool}|null
     */
    private function state(Request $request, string $guard): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state)
            || ($state['guard'] ?? null) !== $guard
            || ! is_numeric($state['user_id'] ?? null)
            || ! is_string($state['otp_hash'] ?? null)
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
            'otp_hash' => $state['otp_hash'],
            'expires_at' => (int) $state['expires_at'],
            'attempts_remaining' => (int) $state['attempts_remaining'],
            'resend_available_at' => (int) $state['resend_available_at'],
            'remember' => $state['remember'],
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
