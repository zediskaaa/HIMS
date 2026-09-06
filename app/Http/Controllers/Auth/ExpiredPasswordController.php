<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use App\Rules\PasswordStandard;
use App\Services\LoginLockoutService;
use App\Services\PasswordExpirationService;
use App\Services\PasswordHistoryService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpiredPasswordController extends Controller
{
    public function show(
        Request $request,
        PasswordExpirationService $expiration,
        LoginLockoutService $lockouts,
    ): View|RedirectResponse {
        $panel = $this->panel($request);

        $attempt = $expiration->pendingAttempt($request, $panel->guard());

        if ($attempt === null) {
            return $this->invalidAttempt($panel);
        }

        $throttleKey = $this->throttleKey($request, $panel, $attempt, $lockouts);
        if ($restriction = $lockouts->activeRestriction($attempt['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $attempt['user']->email, $restriction);
            $expiration->clear($request);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        return view('auth.password-expired', ['panel' => $panel]);
    }

    public function update(
        Request $request,
        PasswordExpirationService $expiration,
        PasswordHistoryService $passwords,
        LoginLockoutService $lockouts,
    ): RedirectResponse {
        $panel = $this->panel($request);
        $attempt = $expiration->pendingAttempt($request, $panel->guard());

        if ($attempt === null) {
            return $this->invalidAttempt($panel);
        }

        $throttleKey = $this->throttleKey($request, $panel, $attempt, $lockouts);
        if ($restriction = $lockouts->activeRestriction($attempt['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $attempt['user']->email, $restriction);
            $expiration->clear($request);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordStandard,
            ],
        ], [
            'password.confirmed' => PasswordStandard::CONFIRMATION_MESSAGE,
        ]);

        $passwords->usePassword(
            $validated['password'],
            function (string $passwordHash) use ($attempt): User {
                $attempt['user']->forceFill([
                    'password' => $passwordHash,
                    'remember_token' => Str::random(60),
                ])->save();

                return $attempt['user'];
            },
        );

        $expiration->clear($request);

        if ($restriction = $lockouts->completeSuccessfulLogin($attempt['user'], $throttleKey)) {
            $lockouts->rememberRestriction($request, $panel->guard(), $attempt['user']->email, $restriction);

            return redirect()->route($panel->loginRoute())
                ->withErrors(['email' => $lockouts->message($restriction)]);
        }

        $lockouts->clearRestriction($request);
        Auth::guard($panel->guard())->login($attempt['user'], $attempt['remember']);
        $request->session()->regenerate();
        $request->session()->put(
            EnforceSessionInactivity::lastActivityKey($panel->guard()),
            now()->getTimestamp(),
        );

        return redirect()
            ->intended(route($panel->dashboardRoute(), absolute: false))
            ->with('status', 'Password updated successfully. You may now continue.');
    }

    public function cancel(Request $request, PasswordExpirationService $expiration): RedirectResponse
    {
        $panel = $this->panel($request);
        $expiration->clear($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($panel->loginRoute());
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from((string) $request->route('auth_panel'));
    }

    /**
     * @param  array{user: User, remember: bool, login_throttle_key: ?string}  $attempt
     */
    private function throttleKey(
        Request $request,
        AuthenticationPanel $panel,
        array $attempt,
        LoginLockoutService $lockouts,
    ): string {
        return $attempt['login_throttle_key']
            ?? $lockouts->throttleKey($panel->value, $attempt['user']->email, $request->ip());
    }

    private function invalidAttempt(AuthenticationPanel $panel): RedirectResponse
    {
        return redirect()->route($panel->loginRoute())->withErrors([
            'email' => 'Your password update session is no longer valid. Please sign in again.',
        ]);
    }
}
