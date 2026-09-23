<?php

namespace App\Services\Sms;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginMfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsMfaChallengeService
{
    public function __construct(
        private readonly LoginMfaService $mfa,
        private readonly SmsOtpDelivery $delivery,
        private readonly AuditLogger $audit,
    ) {}

    public function begin(Request $request, User $user, string $guard, bool $remember, ?string $throttleKey): string
    {
        $otp = $this->mfa->issueSms($request, $user, $guard, $remember, $throttleKey);

        return $this->deliver($request, $user, $guard, $otp, false);
    }

    public function deliver(Request $request, User $user, string $guard, #[\SensitiveParameter] string $otp, bool $resend): string
    {
        $status = $this->delivery->send($user, $otp, $this->mfa->expiresInMinutes());
        $description = $status === SmsOtpDelivery::SENT
            ? ($resend ? 'SMS verification code resent.' : 'SMS verification code requested.')
            : ($status === SmsOtpDelivery::RATE_LIMITED
                ? 'SMS verification request limit reached.'
                : 'SMS verification delivery failed.');

        $this->audit->log(
            AuditAction::SmsVerification,
            null,
            $description,
            $user,
            'Account',
            outcome: $status === SmsOtpDelivery::SENT ? 'success' : 'failure',
            source: 'user',
        );

        if ($status !== SmsOtpDelivery::SENT) {
            $this->mfa->clear($request);
            Log::warning('SMS verification delivery did not complete.', [
                'user_id' => $user->getKey(),
                'guard' => $guard,
                'reason' => $status,
            ]);
        }

        return $status;
    }
}
