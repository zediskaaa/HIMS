<?php

namespace App\Http\Controllers;

use App\Enums\NotificationDestination;
use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->notificationFor($request, $notification)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $this->user($request);
        $stored = $this->notificationFor($request, $notification);
        $stored->markAsRead();

        $destination = NotificationDestination::tryFrom((string) ($stored->data['destination'] ?? ''));

        $parameters = $stored->data['route_parameters'] ?? [];
        $parameters = is_array($parameters) ? $parameters : [];

        if ($destination === null
            || ! $destination->isAuthorizedFor($user)
            || ! $destination->isAvailable($parameters)) {
            return redirect()
                ->route(AuthenticationPanel::forRole($user->role)->dashboardRoute())
                ->with('info', 'This notification is no longer available for your current access level.');
        }

        return redirect()->to($destination->url($user, $parameters));
    }

    private function notificationFor(Request $request, string $id): DatabaseNotification
    {
        return $this->user($request)->notifications()->findOrFail($id);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
