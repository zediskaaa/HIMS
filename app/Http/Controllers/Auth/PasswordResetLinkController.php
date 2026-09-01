<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use App\Services\PasswordResetOtpService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(Request $request): View
    {
        return view('auth.forgot-password', [
            'panel' => $this->panel($request),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $panel = $this->panel($request);
        $account = User::query()
            ->active()
            ->where('email', $request->string('email'))
            ->first();

        if ($account !== null && ! $panel->accepts($account->role)) {
            $correctPanel = AuthenticationPanel::forRole($account->role);

            return back()
                ->withInput($request->only('email'))
                ->with('wrong_panel', $panel->wrongPanelAlert($correctPanel));
        }

        // Unknown and inactive accounts deliberately receive the same response
        // as a valid request. Only the owner of a matching inbox can continue.
        if ($account === null) {
            return back()->with('status', 'If an eligible account matches that email, a password reset code has been sent.');
        }

        $otp = $otpService->issue($account);

        try {
            $account->notify(new PasswordResetOtp($otp, $otpService->expiresInMinutes()));
        } catch (Throwable $exception) {
            // Do not leave a usable code behind when its delivery failed. The
            // conditional delete cannot erase a newer code from another request.
            $otpService->deleteIfCurrent($account, $otp);

            Log::error('Password reset OTP email could not be sent.', [
                'user_id' => $account->getKey(),
                'panel' => $panel->value,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'smtp_response_code' => $this->smtpResponseCode($exception),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Unable to send the password reset code. Please try again later.']);
        }

        return redirect()->route($panel->passwordOtpRoute(), [
            'email' => $account->email,
        ])->with('status', 'A password reset code has been sent to your email address.');
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from(
            (string) $request->route('auth_panel', AuthenticationPanel::Staff->value)
        );
    }

    private function smtpResponseCode(Throwable $exception): ?string
    {
        preg_match_all('/\b([45]\d{2})\b/', $exception->getMessage(), $matches);

        return $matches[1] === [] ? null : end($matches[1]);
    }
}
