<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
     * Authenticate only an active account belonging to this login panel.
     * Every failure deliberately uses the same message so the endpoint does
     * not reveal whether an email exists, is inactive, or has another role.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::guard($this->guard())->attempt([
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'status' => UserStatus::Active->value,
            fn (Builder $query) => $query->whereIn('role', $this->allowedRoles()),
        ], $this->boolean('remember'));

        if (! $authenticated) {
            $this->flashWrongPanelAlertForValidCredentials();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Guide a valid account to its own panel without changing the generic
     * authentication error. Verifying the password first avoids disclosing
     * another account's role to somebody who only knows its email address.
     */
    private function flashWrongPanelAlertForValidCredentials(): void
    {
        $account = User::query()
            ->active()
            ->where('email', $this->string('email')->toString())
            ->first();

        if ($account === null || ! Hash::check($this->string('password')->toString(), $account->password)) {
            return;
        }

        $currentPanel = AuthenticationPanel::forGuard($this->guard());

        if ($currentPanel->accepts($account->role)) {
            return;
        }

        $correctPanel = AuthenticationPanel::forRole($account->role);
        $this->session()->flash('wrong_panel', $currentPanel->wrongPanelAlert($correctPanel));
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

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return $this->throttlePrefix().'|'.Str::transliterate(
            Str::lower($this->string('email')).'|'.$this->ip()
        );
    }

    abstract protected function guard(): string;

    /**
     * @return array<int, string>
     */
    abstract protected function allowedRoles(): array;

    abstract protected function throttlePrefix(): string;
}
