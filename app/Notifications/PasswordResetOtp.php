<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtp extends Notification
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
            ->subject('HIMS Password Reset Code')
            ->view('emails.auth.password-reset-otp', $data)
            ->text('emails.auth.password-reset-otp-text', $data);
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
