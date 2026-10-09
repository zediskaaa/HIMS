<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SupplierInvitation;
use App\Notifications\AccountActivationOtp;
use App\Rules\PasswordStandard;
use App\Services\AccountActivationService;
use App\Services\AuditLogger;
use App\Services\Sms\SmsOtpDelivery;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class AccountActivationController extends Controller
{
    private const IDENTITY = 'account_activation.identity';

    private const VERIFIED_USER = 'account_activation.verified_user_id';

    public function start(Request $request): View
    {
        $request->session()->forget(['account_activation']);

        return view('auth.account-activation.start');
    }

    public function identify(Request $request, AccountActivationService $activation, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['required', 'string', 'digits:11', 'regex:/^09[0-9]{9}$/'],
        ], [
            'phone.digits' => 'Enter the registered 11-digit mobile number.',
            'phone.regex' => 'The mobile number must begin with 09.',
        ]);

        $user = $activation->eligibleAccount($validated['email'], $validated['phone']);
        $request->session()->put(self::IDENTITY, [
            'user_id' => $user?->getKey(),
            'email_mask' => AccountActivationService::maskEmail($validated['email']),
            'phone_mask' => AccountActivationService::maskPhone($validated['phone']),
        ]);

        if ($user !== null) {
            $audit->record(
                AuditAction::AccountActivationInitiated,
                target: $user,
                description: 'Account activation was initiated using the registered contact details.',
                targetName: $user->name,
                source: 'user',
            );
        }

        return redirect()->route('activation.method');
    }

    public function method(Request $request): View|RedirectResponse
    {
        $identity = $request->session()->get(self::IDENTITY);

        return is_array($identity)
            ? view('auth.account-activation.method', compact('identity'))
            : redirect()->route('activation.start');
    }

    public function send(
        Request $request,
        AccountActivationService $activation,
        SmsOtpDelivery $sms,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate(['channel' => ['required', 'in:email,sms']]);
        $identity = $request->session()->get(self::IDENTITY);

        if (! is_array($identity)) {
            return redirect()->route('activation.start');
        }

        $request->session()->put(self::IDENTITY.'.channel', $validated['channel']);
        $user = $this->activationUser($identity);

        // Keep the public response indistinguishable when the supplied account
        // details are unknown, inactive, archived, or already activated.
        if ($user === null) {
            return redirect()->route('activation.verify')
                ->with('status', 'If the account is eligible, a verification code has been sent.');
        }

        $rateKey = "account-activation:send:{$user->getKey()}:{$validated['channel']}";
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $audit->record(
                AuditAction::AccountActivationRateLimited,
                target: $user,
                description: 'An account activation code request was rate limited.',
                targetName: $user->name,
                outcome: 'failure',
                source: 'user',
            );

            return back()->withErrors(['channel' => 'Too many code requests. Please wait before trying again.']);
        }

        $result = $activation->issue($user, $validated['channel']);
        if ($result['status'] === AccountActivationService::COOLDOWN) {
            $audit->record(
                AuditAction::AccountActivationRateLimited,
                target: $user,
                description: 'An account activation resend was requested during the cooldown.',
                targetName: $user->name,
                outcome: 'failure',
                source: 'user',
            );

            return back()->withErrors([
                'channel' => 'Please wait '.$result['retry_after'].' seconds before requesting another code.',
            ]);
        }

        if ($result['status'] !== AccountActivationService::SENT) {
            return redirect()->route('activation.start')
                ->withErrors(['email' => 'This account cannot be activated. Contact a HIMS administrator.']);
        }

        RateLimiter::hit($rateKey, 900);
        $delivered = $this->deliver($user, $validated['channel'], $result['otp'], $activation, $sms);

        if (! $delivered) {
            $activation->invalidateIfCurrent($user, $result['otp']);
            $audit->record(
                AuditAction::AccountActivationOtpRequested,
                target: $user,
                description: 'An account activation code could not be delivered.',
                targetName: $user->name,
                newValues: ['channel' => $validated['channel']],
                outcome: 'failure',
                source: 'system',
            );

            return back()->withErrors([
                'channel' => 'We could not send the code through that method. Try the other method or contact an administrator.',
            ]);
        }

        $audit->record(
            AuditAction::AccountActivationOtpRequested,
            target: $user,
            description: ($result['resent'] ? 'Resent' : 'Sent').' an account activation code through the selected channel.',
            targetName: $user->name,
            newValues: ['channel' => $validated['channel']],
            source: 'user',
        );

        return redirect()->route('activation.verify')
            ->with('status', 'Verification code sent. It expires in '.$activation->otpExpiresInMinutes().' minutes.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $identity = $request->session()->get(self::IDENTITY);

        return is_array($identity) && isset($identity['channel'])
            ? view('auth.account-activation.verify', compact('identity'))
            : redirect()->route('activation.start');
    }

    public function verify(Request $request, AccountActivationService $activation, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Enter the 6-digit verification code.',
            'otp.digits' => 'Enter the complete 6-digit code.',
        ]);
        $identity = $request->session()->get(self::IDENTITY);
        $user = is_array($identity) ? $this->activationUser($identity) : null;

        if ($user === null || ! isset($identity['channel'])) {
            if (is_array($identity) && $this->cancelledActivationUser($identity) !== null) {
                return redirect()->route('activation.start')->withErrors(['email' => AccountActivationService::CANCELLED_MESSAGE]);
            }

            return back()->withErrors(['otp' => 'The code is incorrect, expired, or no longer valid.']);
        }

        $result = $activation->verify($user, $identity['channel'], $validated['otp']);

        if ($result !== AccountActivationService::VERIFIED) {
            $audit->record(
                $result === AccountActivationService::TOO_MANY_ATTEMPTS
                    ? AuditAction::AccountActivationRateLimited
                    : AuditAction::AccountActivationOtpFailed,
                target: $user,
                description: 'An account activation code verification attempt failed.',
                targetName: $user->name,
                outcome: 'failure',
                source: 'user',
            );

            $message = match ($result) {
                AccountActivationService::EXPIRED => 'This code has expired. Request a new code.',
                AccountActivationService::TOO_MANY_ATTEMPTS => 'Too many incorrect attempts. Request a new code after the cooldown.',
                default => 'The code is incorrect, expired, or no longer valid.',
            };

            return back()->withInput()->withErrors(['otp' => $message]);
        }

        $audit->record(
            AuditAction::AccountActivationOtpVerified,
            target: $user,
            description: 'The registered contact was verified for account activation.',
            targetName: $user->name,
            newValues: ['channel' => $identity['channel']],
            source: 'user',
        );

        $request->session()->regenerate();
        $request->session()->forget(self::IDENTITY);
        $request->session()->put(self::VERIFIED_USER, $user->getKey());

        return redirect()->route('activation.password');
    }

    public function showPassword(Request $request, AccountActivationService $activation): View|RedirectResponse
    {
        $user = $this->verifiedUser($request);

        if ($user?->isCancelled()) {
            return redirect()->route('activation.start')->withErrors(['email' => AccountActivationService::CANCELLED_MESSAGE]);
        }

        return $user !== null && $activation->canSetPassword($user)
            ? view('auth.account-activation.password')
            : redirect()->route('activation.start')
                ->withErrors(['email' => 'Your verified activation session expired. Start again to receive a new code.']);
    }

    public function password(
        Request $request,
        AccountActivationService $activation,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', new PasswordStandard],
        ], [
            'password.confirmed' => PasswordStandard::CONFIRMATION_MESSAGE,
        ]);
        $user = $this->verifiedUser($request);
        $pendingSupplierInvitation = $user?->supplierInvitation()
            ->where('status', SupplierInvitation::STATUS_PENDING)
            ->first();
        $activated = $user === null ? null : $activation->complete($user, $validated['password']);

        if ($activated === null) {
            if ($user?->isCancelled()) {
                return redirect()->route('activation.start')->withErrors(['email' => AccountActivationService::CANCELLED_MESSAGE]);
            }

            return redirect()->route('activation.start')
                ->withErrors(['email' => 'Your verified activation session expired. Start again to receive a new code.']);
        }

        $audit->record(
            AuditAction::AccountActivationCompleted,
            target: $activated,
            description: 'The user created their password and activated the account.',
            targetName: $activated->name,
            source: 'user',
        );

        if ($pendingSupplierInvitation !== null) {
            $pendingSupplierInvitation->refresh()->load('supplier');
            $audit->record(
                AuditAction::AcceptedSupplierInvitation,
                actor: $activated,
                target: $pendingSupplierInvitation,
                description: 'Accepted the supplier registration invitation and activated the Vendor Administrator account.',
                targetName: $pendingSupplierInvitation->supplier->name,
                newValues: ['supplier_id' => $pendingSupplierInvitation->supplier_id, 'status' => SupplierInvitation::STATUS_ACCEPTED],
                source: 'user',
            );
        }

        $request->session()->forget(['account_activation']);
        $request->session()->regenerateToken();

        return redirect()->route(AuthenticationPanel::forRole($activated->role)->loginRoute())
            ->with('status', 'Account activated. You can now sign in with your new password.');
    }

    private function activationUser(array $identity): ?User
    {
        return isset($identity['user_id'])
            ? User::query()->where('status', 'pending_activation')->find($identity['user_id'])
            : null;
    }

    private function cancelledActivationUser(array $identity): ?User
    {
        return isset($identity['user_id'])
            ? User::query()->where('status', UserStatus::Cancelled->value)->find($identity['user_id'])
            : null;
    }

    private function verifiedUser(Request $request): ?User
    {
        $id = $request->session()->get(self::VERIFIED_USER);

        return is_numeric($id) ? User::query()->find($id) : null;
    }

    private function deliver(
        User $user,
        string $channel,
        #[\SensitiveParameter] string $otp,
        AccountActivationService $activation,
        SmsOtpDelivery $sms,
    ): bool {
        if ($channel === 'sms') {
            return $sms->sendActivation($user, $otp, $activation->otpExpiresInMinutes()) === SmsOtpDelivery::SENT;
        }

        try {
            $user->notify(new AccountActivationOtp($otp, $activation->otpExpiresInMinutes()));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
