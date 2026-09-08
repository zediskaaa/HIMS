<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginMfaOtp extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] public readonly string $otp,
        public readonly int $expiresInMinutes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'otp' => $this->otp,
            'expiresInMinutes' => $this->expiresInMinutes,
        ];

        return (new MailMessage)
            ->subject('HIMS Login Verification Code')
            ->view('emails.auth.login-mfa-otp', $data)
            ->text('emails.auth.login-mfa-otp-text', $data);
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
