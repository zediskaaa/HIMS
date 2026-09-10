<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginLockoutService
{
    public const SESSION_KEY = 'auth.login_restriction';

    public const SUCCESS = 'success';

    public const INVALID = 'invalid';

    public const WRONG_PANEL = 'wrong_panel';

    public const WAITING = 'waiting';

    public const LOCKED = 'locked';

    private const ATTEMPT_WINDOW_SECONDS = 900;

    private const CYCLE_WINDOW_SECONDS = 31536000;

    private const PRE_LOCK_FAILURES = 5;

    /** @var array<int, int> */
    private const WAIT_MINUTES = [
        5 => 20,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Validate credentials while serializing updates to a known account.
     *
     * @param  array<int, string>  $allowedRoles
     * @return array{status: string, user?: User, seconds?: int}
     */
    public function attempt(
        string $email,
        #[\SensitiveParameter] string $password,
        array $allowedRoles,
        bool $detectWrongPanel = false,
    ): array {
        $shadowKey = $this->identifierThrottleKey($email);

        $result = DB::transaction(function () use ($email, $password, $allowedRoles, $detectWrongPanel): array {
            $user = User::query()
                ->where('email', $email)
                ->where('status', UserStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return ['status' => self::INVALID];
            }

            if ($detectWrongPanel && ! in_array($user->role->value, $allowedRoles, true)) {
                return ['status' => self::WRONG_PANEL, 'user' => $user];
            }

            if ($user->isSuperAdministrator()) {
                $credentialsValid = Hash::check($password, $user->password);

                return [
                    'status' => self::INVALID,
                    'other_panel_credentials_valid' => $credentialsValid,
                    ...($credentialsValid ? ['user' => $user] : []),
                ];
            }

            if (! in_array($user->role->value, $allowedRoles, true)) {
                $credentialsValid = Hash::check($password, $user->password);

                if ($credentialsValid && ($restriction = $this->storedRestriction($user))) {
                    return [...$restriction, 'known' => true];
                }

                return [
                    'status' => self::INVALID,
                    'other_panel_credentials_valid' => $credentialsValid,
                    ...($credentialsValid ? ['user' => $user] : []),
                ];
            }

            if ($restriction = $this->accountRestriction($user)) {
                return [...$restriction, 'known' => true];
            }

            if (Hash::check($password, $user->password)) {
                return ['status' => self::SUCCESS, 'user' => $user, 'known' => true];
            }

            return $this->recordAccountFailure($user);
        }, 3);

        if (in_array($result['status'], [self::SUCCESS, self::WRONG_PANEL], true)) {
            return $result;
        }

        if ($result['known'] ?? false) {
            if (isset($result['attempt'])) {
                $this->setShadowCounter(
                    $this->attemptKey($shadowKey),
                    (int) $result['attempt'],
                    self::ATTEMPT_WINDOW_SECONDS,
                );
            }

            if (isset($result['lockout_count'])) {
                $this->setShadowCounter(
                    $this->cycleKey($shadowKey),
                    (int) $result['lockout_count'],
                    self::CYCLE_WINDOW_SECONDS,
                );
            }

            if (in_array($result['status'], [self::WAITING, self::LOCKED], true)) {
                $this->setShadowRestriction(
                    $shadowKey,
                    (int) $result['seconds'],
                    $result['status'] === self::LOCKED,
                );
            }

            return $result;
        }

        if (array_key_exists('other_panel_credentials_valid', $result)) {
            if ($result['other_panel_credentials_valid']) {
                return $result;
            }

            if ($restriction = $this->shadowRestriction($shadowKey)) {
                return $restriction;
            }

            return $this->recordShadowFailure($shadowKey);
        }

        if ($restriction = $this->shadowRestriction($shadowKey)) {
            return $restriction;
        }

        // Match the password-hash work performed for a real account so an
        // invalid identifier does not create a useful timing side channel.
        Hash::make($password);

        return $this->recordShadowFailure($shadowKey);
    }

    /**
     * Re-check at the final authentication boundary, then reset only the
     * current cycle. The lifetime lockout count remains for progressive cycles.
     *
     * @return array{status: string, seconds: int}|null
     */
    public function completeSuccessfulLogin(User $user, string $throttleKey): ?array
    {
        if ($user->isSuperAdministrator()) {
            return null;
        }

        $restriction = DB::transaction(function () use ($user): ?array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($restriction = $this->accountRestriction($lockedUser)) {
                return $restriction;
            }

            $lockedUser->forceFill([
                'failed_login_attempts' => 0,
                'last_failed_login_at' => null,
                'login_retry_at' => null,
                'login_locked_until' => null,
            ])->saveQuietly();

            return null;
        }, 3);

        if ($restriction !== null) {
            $this->setShadowRestriction(
                $throttleKey,
                $restriction['seconds'],
                $restriction['status'] === self::LOCKED,
            );

            return $restriction;
        }

        $this->clearCurrentThrottle($throttleKey);

        return null;
    }

    /** @return array{status: string, seconds: int}|null */
    public function activeRestriction(User $user, string $throttleKey): ?array
    {
        if ($user->isSuperAdministrator()) {
            return null;
        }

        $restriction = DB::transaction(function () use ($user): ?array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            return $this->accountRestriction($lockedUser);
        }, 3);

        if ($restriction !== null) {
            $this->setShadowRestriction(
                $throttleKey,
                $restriction['seconds'],
                $restriction['status'] === self::LOCKED,
            );
        }

        return $restriction;
    }

    public function clearCurrentThrottle(string $throttleKey): void
    {
        RateLimiter::clear($this->attemptKey($throttleKey));
        RateLimiter::clear($this->waitKey($throttleKey));
        RateLimiter::clear($this->lockKey($throttleKey));
    }

    /** @param array{status: string, seconds: int, expires_at?: int, attempt?: int} $restriction */
    public function rememberRestriction(
        Request $request,
        string $guard,
        string $email,
        array $restriction,
    ): void {
        $request->session()->put(self::SESSION_KEY, [
            'guard' => $guard,
            'email' => Str::lower(trim($email)),
            'status' => $restriction['status'],
            'expires_at' => $restriction['expires_at'] ?? now()->addSeconds($restriction['seconds'])->getTimestamp(),
            'attempts_remaining' => $this->attemptsRemaining($restriction),
        ]);
    }

    public function clearRestriction(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Return a session's server-validated restriction for a login page.
     *
     * @return array{guard: string, email: string, status: string, expires_at: int, attempts_remaining: ?int}|null
     */
    public function sessionRestriction(Request $request, string $guard): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state) || ($state['guard'] ?? null) !== $guard) {
            return null;
        }

        if (! is_string($state['email'] ?? null)
            || ! is_string($state['status'] ?? null)
            || ! in_array($state['status'], [self::WAITING, self::LOCKED], true)
            || ! is_numeric($state['expires_at'] ?? null)
            || (int) $state['expires_at'] <= now()->getTimestamp()) {
            $this->clearRestriction($request);

            return null;
        }

        $user = User::query()
            ->active()
            ->where('email', $state['email'])
            ->first();

        if ($user !== null && ! $user->isSuperAdministrator()) {
            $restriction = $this->storedRestriction($user);

            if ($restriction === null) {
                $this->clearRestriction($request);

                return null;
            }

            $state['status'] = $restriction['status'];
            $state['expires_at'] = $restriction['expires_at'];
            $state['attempts_remaining'] = $this->attemptsRemaining($restriction);
            $request->session()->put(self::SESSION_KEY, $state);
        }

        return [
            'guard' => $guard,
            'email' => $state['email'],
            'status' => $state['status'],
            'expires_at' => (int) $state['expires_at'],
            'attempts_remaining' => is_numeric($state['attempts_remaining'] ?? null)
                ? max(0, (int) $state['attempts_remaining'])
                : null,
        ];
    }

    public function throttleKey(string $prefix, string $email, ?string $ipAddress): string
    {
        return $prefix.'|'.Str::transliterate(Str::lower($email).'|'.($ipAddress ?? 'unknown'));
    }

    /** @param array{status: string, seconds?: int, attempt?: int} $result */
    public function message(array $result): string
    {
        if ($result['status'] === self::LOCKED) {
            $minutes = max(1, (int) ceil($result['seconds'] / 60));

            return "Your account is temporarily locked. Please try again in {$minutes} ".str('minute')->plural($minutes).'.';
        }

        $message = trans('auth.failed');
        $remaining = $this->attemptsRemaining($result);

        if ($remaining !== null) {
            $message .= " You have {$remaining} ".str('attempt')->plural($remaining).' remaining.';
        }

        if ($result['status'] === self::WAITING) {
            $minutes = max(1, (int) ceil($result['seconds'] / 60));
            $message .= " Please try again after {$minutes} ".str('minute')->plural($minutes).'.';
        }

        return $message;
    }

    /** @param array{attempt?: int} $result */
    public function attemptsRemaining(array $result): ?int
    {
        return isset($result['attempt'])
            ? max(0, self::PRE_LOCK_FAILURES - (int) $result['attempt'])
            : null;
    }

    /** @return array{status: string, seconds: int}|null */
    private function accountRestriction(User $user): ?array
    {
        $now = now();
        $changed = false;
        $decayStartsAt = $user->last_failed_login_at;

        if ($user->login_retry_at !== null
            && ($decayStartsAt === null || $user->login_retry_at->isAfter($decayStartsAt))) {
            // A mandatory wait cannot count as voluntary inactivity; otherwise
            // the 20-minute fifth-attempt wait would make a sixth-attempt lock
            // impossible. Decay starts once another attempt is permitted.
            $decayStartsAt = $user->login_retry_at;
        }

        if ($user->login_locked_until !== null && $user->login_locked_until->lessThanOrEqualTo($now)) {
            $user->login_locked_until = null;
            $changed = true;
        }

        if ($user->login_retry_at !== null && $user->login_retry_at->lessThanOrEqualTo($now)) {
            $user->login_retry_at = null;
            $changed = true;
        }

        if ((int) $user->failed_login_attempts > 0
            && ($decayStartsAt === null
                || $decayStartsAt->lessThanOrEqualTo($now->copy()->subSeconds(self::ATTEMPT_WINDOW_SECONDS)))) {
            $user->failed_login_attempts = 0;
            $user->last_failed_login_at = null;
            $changed = true;
        }

        if ($changed) {
            $user->saveQuietly();
        }

        return $this->storedRestriction($user);
    }

    /** @return array{status: string, seconds: int}|null */
    private function storedRestriction(User $user): ?array
    {
        $now = now();

        if ($user->login_locked_until?->isFuture()) {
            return $this->restriction(self::LOCKED, $user->login_locked_until->getTimestamp() - $now->getTimestamp());
        }

        if ($user->login_retry_at?->isFuture()) {
            return [
                ...$this->restriction(self::WAITING, $user->login_retry_at->getTimestamp() - $now->getTimestamp()),
                'attempt' => (int) $user->failed_login_attempts,
            ];
        }

        return null;
    }

    /** @return array{status: string, seconds?: int, known: true, attempt: int, lockout_count?: int} */
    private function recordAccountFailure(User $user): array
    {
        $attempt = (int) $user->failed_login_attempts + 1;

        if ($attempt >= 6) {
            $lockoutCount = (int) $user->login_lockout_count + 1;
            $minutes = $this->lockMinutes($lockoutCount);
            $lockedUntil = now()->addMinutes($minutes);

            $user->forceFill([
                'failed_login_attempts' => 0,
                'last_failed_login_at' => null,
                'login_retry_at' => null,
                'login_locked_until' => $lockedUntil,
                'login_lockout_count' => $lockoutCount,
            ])->saveQuietly();

            $this->audit->log(
                AuditAction::TemporarilyLockedUser,
                null,
                "Temporarily locked the user account for {$user->name} after repeated failed login attempts.",
                $user,
                $user->name,
                [
                    'failed_login_attempts' => 5,
                    'login_locked_until' => null,
                    'login_lockout_count' => $lockoutCount - 1,
                ],
                [
                    'failed_login_attempts' => 0,
                    'login_locked_until' => $lockedUntil->toIso8601String(),
                    'login_lockout_count' => $lockoutCount,
                    'lock_duration_minutes' => $minutes,
                ],
            );

            return [
                ...$this->restriction(self::LOCKED, $minutes * 60),
                'known' => true,
                'attempt' => 0,
                'lockout_count' => $lockoutCount,
            ];
        }

        $waitMinutes = self::WAIT_MINUTES[$attempt] ?? null;
        $user->forceFill([
            'failed_login_attempts' => $attempt,
            'last_failed_login_at' => now(),
            'login_retry_at' => $waitMinutes === null ? null : now()->addMinutes($waitMinutes),
        ])->saveQuietly();

        $this->audit->log(
            AuditAction::FailedLogin,
            null,
            'A failed sign-in attempt was recorded for an existing account.',
            $user,
            'Account',
            newValues: ['failed_login_attempt' => $attempt],
            source: 'user',
        );

        return [
            ...($waitMinutes === null
                ? ['status' => self::INVALID]
                : $this->restriction(self::WAITING, $waitMinutes * 60)),
            'known' => true,
            'attempt' => $attempt,
        ];
    }

    /** @return array{status: string, seconds?: int} */
    private function recordShadowFailure(string $throttleKey): array
    {
        $attemptKey = $this->attemptKey($throttleKey);
        $attempt = RateLimiter::attempts($attemptKey) + 1;
        $this->setShadowCounter($attemptKey, $attempt, self::ATTEMPT_WINDOW_SECONDS);

        if ($attempt >= 6) {
            $lockoutCount = RateLimiter::hit($this->cycleKey($throttleKey), self::CYCLE_WINDOW_SECONDS);
            $seconds = $this->lockMinutes($lockoutCount) * 60;

            RateLimiter::clear($attemptKey);
            $this->setShadowRestriction($throttleKey, $seconds, true);

            return $this->restriction(self::LOCKED, $seconds);
        }

        $waitMinutes = self::WAIT_MINUTES[$attempt] ?? null;
        if ($waitMinutes === null) {
            return ['status' => self::INVALID, 'attempt' => $attempt];
        }

        $seconds = $waitMinutes * 60;
        $this->setShadowRestriction($throttleKey, $seconds, false);

        return [...$this->restriction(self::WAITING, $seconds), 'attempt' => $attempt];
    }

    /** @return array{status: string, seconds: int}|null */
    private function shadowRestriction(string $throttleKey): ?array
    {
        if (! RateLimiter::tooManyAttempts($this->waitKey($throttleKey), 1)) {
            return null;
        }

        $locked = RateLimiter::tooManyAttempts($this->lockKey($throttleKey), 1);

        $restriction = $this->restriction(
            $locked ? self::LOCKED : self::WAITING,
            RateLimiter::availableIn($this->waitKey($throttleKey)),
        );

        if (! $locked) {
            $restriction['attempt'] = RateLimiter::attempts($this->attemptKey($throttleKey));
        }

        return $restriction;
    }

    private function setShadowRestriction(string $throttleKey, int $seconds, bool $locked): void
    {
        RateLimiter::clear($this->waitKey($throttleKey));
        RateLimiter::clear($this->lockKey($throttleKey));
        RateLimiter::hit($this->waitKey($throttleKey), max(1, $seconds));

        if ($locked) {
            RateLimiter::hit($this->lockKey($throttleKey), max(1, $seconds));
        }
    }

    private function setShadowCounter(string $key, int $count, int $decaySeconds): void
    {
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < $count; $attempt++) {
            RateLimiter::hit($key, $decaySeconds);
        }
    }

    /** @return array{status: string, seconds: int, expires_at: int} */
    private function restriction(string $status, int $seconds): array
    {
        $seconds = max(1, $seconds);

        return [
            'status' => $status,
            'seconds' => $seconds,
            'expires_at' => now()->addSeconds($seconds)->getTimestamp(),
        ];
    }

    private function lockMinutes(int $lockoutCount): int
    {
        return match ($lockoutCount) {
            1 => 30,
            2 => 60,
            3 => 120,
            default => 240,
        };
    }

    private function attemptKey(string $throttleKey): string
    {
        return "progressive-login:{$throttleKey}:attempts";
    }

    private function waitKey(string $throttleKey): string
    {
        return "progressive-login:{$throttleKey}:wait";
    }

    private function lockKey(string $throttleKey): string
    {
        return "progressive-login:{$throttleKey}:lock";
    }

    private function cycleKey(string $throttleKey): string
    {
        return "progressive-login:{$throttleKey}:cycles";
    }

    private function identifierThrottleKey(string $email): string
    {
        return 'identifier|'.Str::transliterate(Str::lower(trim($email)));
    }
}
