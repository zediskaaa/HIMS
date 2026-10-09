<?php

namespace App\Notifications;

use App\Models\SupplierInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SupplierInvitation $invitation,
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'appName' => config('app.name'),
            'name' => $notifiable->name,
            'supplierName' => $this->invitation->supplier->name,
            'invitationUrl' => route('supplier-invitations.accept', $this->token),
            'expiresIn' => $this->invitation->expires_at->diffForHumans(),
        ];

        return (new MailMessage)
            ->subject('Invitation to complete your HIMS supplier registration')
            ->view('emails.supplier-invitation', $data)
            ->text('emails.supplier-invitation-text', $data);
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
