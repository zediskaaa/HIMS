<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetOtpService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetOtpController extends Controller
{
    public function show(Request $request, PasswordResetOtpService $otpService): View|RedirectResponse
    {
        $panel = $this->panel($request);
        $account = $this->eligibleAccount($request);

        if ($account === null || ! $panel->accepts($account->role)) {
            return redirect()
                ->route($panel->passwordRequestRoute())
                ->withErrors(['email' => 'Please request a new password reset code.']);
        }

        return view('auth.reset-password-otp', [
            'email' => $account->email,
            'expiresInMinutes' => $otpService->expiresInMinutes(),
            'panel' => $panel,
        ]);
    }

    public function verify(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.digits' => 'Enter the complete 6-digit verification code.',
        ]);

        $panel = $this->panel($request);
        $account = $this->eligibleAccount($request);

        if ($account === null) {
            return back()->withInput($request->only('email'))->withErrors([
                'otp' => 'This verification code is invalid or has expired.',
            ]);
        }

        if (! $panel->accepts($account->role)) {
            $correctPanel = AuthenticationPanel::forRole($account->role);

            return redirect()
                ->route($correctPanel->passwordRequestRoute())
                ->with('wrong_panel', $panel->wrongPanelAlert($correctPanel));
        }

        $resetToken = $otpService->exchangeForResetToken($account, $validated['otp']);

        if ($resetToken === null) {
            return back()->withInput($request->only('email'))->withErrors([
                'otp' => 'This verification code is invalid or has expired.',
            ]);
        }

        return redirect()->route($panel->passwordResetRoute(), [
            'token' => $resetToken,
            'email' => $account->email,
        ]);
    }

    private function eligibleAccount(Request $request): ?User
    {
        return User::query()
            ->where('email', $request->string('email'))
            ->where('status', UserStatus::Active->value)
            ->first();
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from(
            (string) $request->route('auth_panel', AuthenticationPanel::Staff->value)
        );
    }
}
