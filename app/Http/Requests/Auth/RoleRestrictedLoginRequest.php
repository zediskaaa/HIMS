<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\LoginLockoutService;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

abstract class RoleRestrictedLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Validate the password without creating an authenticated session. Admin
     * panels use this first stage when an MFA challenge still has to pass.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): User
    {
        if ($this->usesProgressiveLockout()) {
            return $this->validateProgressiveCredentials();
        }

        $user = User::query()
            ->where('email', $this->string('email')->toString())
            ->where('status', UserStatus::Active->value)
            ->first();

        if ($user !== null && ! in_array($user->role->value, $this->allowedRoles(), true)) {
            $this->rejectWrongPanel($user);
        }

        $credentialsValid = $user !== null
            && Hash::check($this->string('password')->toString(), $user->password);

        $this->ensureIsNotRateLimited();

        if (! $credentialsValid) {
            if ($user === null) {
                Hash::make($this->string('password')->toString());
            }

            $attempt = RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => app(LoginLockoutService::class)->message([
                    'status' => LoginLockoutService::INVALID,
                    'attempt' => $attempt,
                ]),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function login(User $user): void
    {
        if ($this->usesProgressiveLockout()) {
            $restriction = app(LoginLockoutService::class)->completeSuccessfulLogin(
                $user,
                $this->progressiveThrottleKey(),
            );

            if ($restriction !== null) {
                $this->throwRestriction($restriction);
            }
        }

        app(LoginLockoutService::class)->clearRestriction($this);
        Auth::guard($this->guard())->login($user, $this->boolean('remember'));
    }

    public function progressiveThrottleKey(): string
    {
        return $this->throttleKey();
    }

    private function rejectWrongPanel(User $account): never
    {
        $currentPanel = AuthenticationPanel::forGuard($this->guard());
        $correctPanel = AuthenticationPanel::forRole($account->role);
        app(LoginLockoutService::class)->clearRestriction($this);
        $this->session()->flash('wrong_panel', $currentPanel->wrongPanelAlert($correctPanel));

        throw new HttpResponseException(
            redirect()
                ->route($currentPanel->loginRoute())
                ->withInput($this->only('email')),
        );
    }

    private function validateProgressiveCredentials(): User
    {
        $lockouts = app(LoginLockoutService::class);
        $result = $lockouts->attempt(
            $this->string('email')->toString(),
            $this->string('password')->toString(),
            $this->allowedRoles(),
            detectWrongPanel: true,
        );

        if ($result['status'] === LoginLockoutService::SUCCESS) {
            return $result['user'];
        }

        if ($result['status'] === LoginLockoutService::WRONG_PANEL
            && ($result['user'] ?? null) instanceof User) {
            $this->rejectWrongPanel($result['user']);
        }

        if ($result['status'] === LoginLockoutService::INVALID) {
            $lockouts->clearRestriction($this);

            throw ValidationException::withMessages([
                'email' => $lockouts->message($result),
            ]);
        }

        $this->throwRestriction($result);
    }

    /** @param array{status: string, seconds: int} $restriction */
    private function throwRestriction(array $restriction): never
    {
        event(new Lockout($this));
        app(LoginLockoutService::class)->rememberRestriction(
            $this,
            $this->guard(),
            $this->string('email')->toString(),
            $restriction,
        );

        throw ValidationException::withMessages([
            'email' => app(LoginLockoutService::class)->message($restriction),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $restriction = [
            'status' => LoginLockoutService::WAITING,
            'seconds' => $seconds,
            'expires_at' => now()->addSeconds($seconds)->getTimestamp(),
            'attempt' => RateLimiter::attempts($this->throttleKey()),
        ];
        app(LoginLockoutService::class)->rememberRestriction(
            $this,
            $this->guard(),
            $this->string('email')->toString(),
            $restriction,
        );

        throw ValidationException::withMessages([
            'email' => app(LoginLockoutService::class)->message($restriction),
        ]);
    }

    private function throttleKey(): string
    {
        return app(LoginLockoutService::class)->throttleKey(
            $this->throttlePrefix(),
            $this->string('email')->toString(),
            $this->ip(),
        );
    }

    protected function usesProgressiveLockout(): bool
    {
        return true;
    }

    abstract protected function guard(): string;

    /**
     * @return array<int, string>
     */
    abstract protected function allowedRoles(): array;

    abstract protected function throttlePrefix(): string;
}
