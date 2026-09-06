<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Rules\NotCurrentPassword;
use App\Rules\PasswordStandard;
use App\Services\PasswordExpirationService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpiredPasswordController extends Controller
{
    public function show(Request $request, PasswordExpirationService $expiration): View|RedirectResponse
    {
        $panel = $this->panel($request);

        if ($expiration->pendingAttempt($request, $panel->guard()) === null) {
            return $this->invalidAttempt($panel);
        }

        return view('auth.password-expired', ['panel' => $panel]);
    }

    public function update(Request $request, PasswordExpirationService $expiration): RedirectResponse
    {
        $panel = $this->panel($request);
        $attempt = $expiration->pendingAttempt($request, $panel->guard());

        if ($attempt === null) {
            return $this->invalidAttempt($panel);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordStandard,
                new NotCurrentPassword($attempt['user']),
            ],
        ], [
            'password.confirmed' => PasswordStandard::CONFIRMATION_MESSAGE,
        ]);

        $attempt['user']->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $expiration->clear($request);
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

    private function invalidAttempt(AuthenticationPanel $panel): RedirectResponse
    {
        return redirect()->route($panel->loginRoute())->withErrors([
            'email' => 'Your password update session is no longer valid. Please sign in again.',
        ]);
    }
}
