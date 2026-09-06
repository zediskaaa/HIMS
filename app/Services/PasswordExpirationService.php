<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Http\Request;

class PasswordExpirationService
{
    public const SESSION_KEY = 'auth.password_expiration';

    private const CHANGE_WINDOW_MINUTES = 15;

    public function begin(Request $request, User $user, string $guard, bool $remember): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'guard' => $guard,
            'remember' => $remember,
            'expires_at' => now()->addMinutes(self::CHANGE_WINDOW_MINUTES)->getTimestamp(),
        ]);
    }

    /**
     * @return array{user: User, remember: bool}|null
     */
    public function pendingAttempt(Request $request, string $guard): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state)
            || ($state['guard'] ?? null) !== $guard
            || ! is_numeric($state['user_id'] ?? null)
            || ! is_bool($state['remember'] ?? null)
            || ! is_numeric($state['expires_at'] ?? null)
            || (int) $state['expires_at'] <= now()->getTimestamp()) {
            $this->clear($request);

            return null;
        }

        $user = User::query()->find((int) $state['user_id']);
        $panel = AuthenticationPanel::forGuard($guard);

        if ($user === null
            || ! $user->isActive()
            || ! $panel->accepts($user->role)
            || ! $user->passwordHasExpired()) {
            $this->clear($request);

            return null;
        }

        return ['user' => $user, 'remember' => $state['remember']];
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
