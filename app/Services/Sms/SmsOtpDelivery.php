<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SmsOtpDelivery
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const RATE_LIMITED = 'rate_limited';

    public function __construct(private readonly SmsGateway $gateway) {}

    public function available(): bool
    {
        return $this->gateway->available();
    }

    public function send(User $user, #[\SensitiveParameter] string $otp, int $expiresInMinutes): string
    {
        $phone = (string) $user->phone;
        if (! $user->sms_mfa_enabled
            || ! $this->available()
            || preg_match('/^09[0-9]{9}$/D', $phone) !== 1
            || ! hash_equals((string) $user->sms_mfa_phone, $phone)) {
            return self::FAILED;
        }

        $key = 'sms-mfa:send:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return self::RATE_LIMITED;
        }

        RateLimiter::hit($key, 900);

        $message = "HIMS verification code: {$otp}. Expires in {$expiresInMinutes} minutes. Never share this code.";

        try {
            return $this->gateway->send($phone, $message) ? self::SENT : self::FAILED;
        } catch (Throwable) {
            return self::FAILED;
        }
    }
}
