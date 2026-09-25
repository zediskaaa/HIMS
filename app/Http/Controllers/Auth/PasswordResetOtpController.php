<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetOtpService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\JsonResponse;
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

    public function verify(Request $request, PasswordResetOtpService $otpService): JsonResponse|RedirectResponse
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
            return $this->verificationFailure($request, 'This verification code is invalid or has expired.');
        }

        if (! $panel->accepts($account->role)) {
            $correctPanel = AuthenticationPanel::forRole($account->role);

            if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $panel->wrongPanelAlert($correctPanel)['message'],
                    'redirect_url' => route($correctPanel->passwordRequestRoute()),
                ], 403);
            }

            return redirect()
                ->route($correctPanel->passwordRequestRoute())
                ->with('wrong_panel', $panel->wrongPanelAlert($correctPanel));
        }

        $resetToken = $otpService->exchangeForResetToken($account, $validated['otp']);

        if ($resetToken === null) {
            return $this->verificationFailure($request, 'This verification code is invalid or has expired.');
        }

        $redirect = redirect()->route($panel->passwordResetRoute(), [
            'token' => $resetToken,
            'email' => $account->email,
        ]);

        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $redirect->getTargetUrl(),
            ]);
        }

        return $redirect;
    }

    private function eligibleAccount(Request $request): ?User
    {
        return User::query()
            ->where('email', $request->string('email'))
            ->where('status', UserStatus::Active->value)
            ->first();
    }

    private function verificationFailure(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['otp' => [$message]],
            ], 422);
        }

        return back()->withInput($request->only('email'))->withErrors(['otp' => $message]);
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from(
            (string) $request->route('auth_panel', AuthenticationPanel::Staff->value)
        );
    }
}
