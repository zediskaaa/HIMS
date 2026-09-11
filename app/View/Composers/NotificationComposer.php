<?php

namespace App\View\Composers;

use App\Models\User;
use Illuminate\View\View;

class NotificationComposer
{
    public function compose(View $view): void
    {
        $user = request()->user();

        if (! $user instanceof User) {
            $view->with(['topbarNotifications' => collect(), 'topbarUnreadCount' => 0]);

            return;
        }

        $view->with([
            'topbarNotifications' => $user->notifications()->latest()->limit(8)->get(),
            'topbarUnreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
