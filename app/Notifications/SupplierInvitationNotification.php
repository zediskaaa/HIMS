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
        return (new MailMessage)
            ->subject('Invitation to complete your HIMS supplier registration')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been invited to represent '.$this->invitation->supplier->name.' in the HIMS Supplier Portal.')
            ->line('Activate your Vendor Administrator account, then complete the company profile and supporting documents for hospital review.')
            ->action('Accept Supplier Invitation', route('supplier-invitations.accept', $this->token))
            ->line('This invitation expires '.$this->invitation->expires_at->diffForHumans().'.')
            ->line('Accepting this invitation does not approve the supplier for procurement. Hospital review is still required.')
            ->line('If you did not expect this invitation, you can ignore this email.');
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
