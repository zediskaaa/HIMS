<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\NotificationDestination;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HimsNotificationService;
use App\Support\AuthenticationPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly HimsNotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $feed = $this->notifications->feedFor($this->user($request));
        $unreadCount = (clone $feed)->whereNull('read_at')->count();
        $notifications = $feed
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(HimsNotificationService::FEED_BATCH_SIZE)
            ->withPath(route('notifications.index'));

        return response()->json([
            'html' => view('layouts.partials.notification-items', [
                'notifications' => $notifications->getCollection(),
            ])->render(),
            'loaded_count' => $notifications->count(),
            'unread_count' => $unreadCount,
            'next_url' => $notifications->nextPageUrl(),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $this->user($request);
        $this->markAsRead($user, $this->notificationFor($request, $notification));

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        DB::transaction(function () use ($user): void {
            $count = $this->notifications->feedFor($user)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            if ($count > 0) {
                $this->auditLogger->record(
                    AuditAction::AcknowledgedNotification,
                    $user,
                    $user,
                    "Marked {$count} notifications as read.",
                    $user->name,
                    newValues: ['notifications_marked_read' => $count],
                );
            }
        });

        return back();
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $this->user($request);
        $stored = $this->notificationFor($request, $notification);
        $this->markAsRead($user, $stored);

        $destination = NotificationDestination::tryFrom((string) ($stored->data['destination'] ?? ''));

        $parameters = $stored->data['route_parameters'] ?? [];
        $parameters = is_array($parameters) ? $parameters : [];
        $parameters = $this->resolveLegacyProcurementTarget($destination, $stored, $parameters);
        $parameters = $this->resolveLegacyInventoryAlertTarget($destination, $stored, $parameters);

        if ($destination === null
            || ! $destination->isAuthorizedFor($user, $parameters)
            || ! $destination->isAvailable($parameters)) {
            return redirect()
                ->route(AuthenticationPanel::forRole($user->role)->dashboardRoute())
                ->with('info', 'This notification is no longer available for your current access level.');
        }

        return redirect()->to($destination->url($user, $parameters));
    }

    /** @param array<string, scalar|null> $parameters */
    private function resolveLegacyProcurementTarget(
        ?NotificationDestination $destination,
        DatabaseNotification $notification,
        array $parameters,
    ): array {
        if ($destination !== NotificationDestination::Procurement || ! empty($parameters['purchase_order'])) {
            return $parameters;
        }

        $purchaseOrderId = null;
        $purchaseOrderNumber = trim((string) ($parameters['po_search'] ?? ''));

        if ($purchaseOrderNumber !== '') {
            $purchaseOrderId = PurchaseOrder::query()
                ->where('po_number', $purchaseOrderNumber)
                ->value('id');
        } elseif (preg_match(
            '/Purchase Order(?: \(PO\))? #(\d+)/i',
            (string) ($notification->data['message'] ?? ''),
            $matches,
        ) === 1) {
            $purchaseOrderId = (int) $matches[1];
        }

        if ($purchaseOrderId !== null) {
            $parameters['purchase_order'] = (int) $purchaseOrderId;
        }

        return $parameters;
    }

    /** @param array<string, scalar|null> $parameters */
    private function resolveLegacyInventoryAlertTarget(
        ?NotificationDestination $destination,
        DatabaseNotification $notification,
        array $parameters,
    ): array {
        if ($destination !== NotificationDestination::InventoryAlerts || ! empty($parameters['item'])) {
            return $parameters;
        }

        if (preg_match('/\(([^()]+)\)\s+is\s+/i', (string) ($notification->data['message'] ?? ''), $matches) !== 1) {
            return $parameters;
        }

        $itemId = InventoryItem::query()
            ->where('sku', trim($matches[1]))
            ->value('id');

        if ($itemId !== null) {
            $parameters['item'] = (int) $itemId;
            $parameters['alert_type'] = str_contains(
                strtolower((string) ($notification->data['message'] ?? '')),
                'out of stock'
            ) ? 'out_of_stock' : 'low_stock';
        }

        return $parameters;
    }

    private function notificationFor(Request $request, string $id): DatabaseNotification
    {
        return $this->notifications->feedFor($this->user($request))->findOrFail($id);
    }

    private function markAsRead(User $user, DatabaseNotification $notification): void
    {
        if ($notification->read_at !== null) {
            return;
        }

        DB::transaction(function () use ($user, $notification): void {
            $notification->markAsRead();
            $title = (string) ($notification->data['title'] ?? 'HIMS notification');

            $this->auditLogger->record(
                AuditAction::AcknowledgedNotification,
                $user,
                description: "Marked notification '{$title}' as read.",
                targetName: $title,
                newValues: [
                    'notification_id' => $notification->id,
                    'destination' => $notification->data['destination'] ?? null,
                ],
                targetType: 'Notification',
                targetId: $notification->id,
                targetReference: $title,
            );
        });
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
