<?php

namespace App\Notifications;

use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class HimsNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $routeParameters
     */
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly NotificationPriority $priority,
        private readonly NotificationDestination $destination,
        private readonly array $routeParameters = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'hims';
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => Str::limit($this->title, 160, '...'),
            'message' => Str::limit($this->message, 500, '...'),
            'priority' => $this->priority->value,
            'destination' => $this->destination->value,
            'route_parameters' => collect($this->routeParameters)
                ->filter(fn (mixed $value, mixed $key) => is_string($key) && (is_scalar($value) || $value === null))
                ->all(),
        ];
    }
}
