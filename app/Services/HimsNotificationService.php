<?php

namespace App\Services;

use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\HimsNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

class HimsNotificationService
{
    /**
     * @param  array<string, scalar|null>  $routeParameters
     */
    public function sendToUser(
        User $recipient,
        string $dedupeKey,
        string $title,
        string $message,
        NotificationPriority $priority,
        NotificationDestination $destination,
        array $routeParameters = [],
    ): bool {
        if (! $destination->isAuthorizedFor($recipient)) {
            return false;
        }

        $id = Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            implode(':', ['hims-notification', $recipient->getMorphClass(), $recipient->getKey(), $dedupeKey])
        )->toString();

        $notification = new HimsNotification(
            $title,
            $message,
            $priority,
            $destination,
            $routeParameters,
        );
        $notification->id = $id;

        try {
            if ($recipient->notifications()->whereKey($id)->exists()) {
                return false;
            }

            $recipient->notify($notification);
        } catch (UniqueConstraintViolationException) {
            return false;
        } catch (Throwable $exception) {
            Log::error('Failed to persist a HIMS notification.', [
                'notification_id' => $id,
                'recipient_id' => $recipient->getKey(),
                'exception_class' => $exception::class,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, scalar|null>  $routeParameters
     */
    public function sendToPermission(
        Permission $permission,
        string $dedupeKey,
        string $title,
        string $message,
        NotificationPriority $priority,
        NotificationDestination $destination,
        array $routeParameters = [],
        ?User $except = null,
    ): int {
        $roles = collect(UserRole::cases())
            ->filter(fn (UserRole $role) => $role->grants($permission))
            ->map->value
            ->all();

        $sent = 0;

        User::query()
            ->where('status', UserStatus::Active->value)
            ->whereIn('role', $roles)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->eachById(function (User $recipient) use (
                &$sent,
                $dedupeKey,
                $title,
                $message,
                $priority,
                $destination,
                $routeParameters,
            ): void {
                $sent += (int) $this->sendToUser(
                    $recipient,
                    $dedupeKey,
                    $title,
                    $message,
                    $priority,
                    $destination,
                    $routeParameters,
                );
            });

        return $sent;
    }

    /**
     * Notify the holders of a workflow role, with Super Administrators included
     * as the established override role.
     *
     * @param  list<UserRole>  $roles
     * @param  array<string, scalar|null>  $routeParameters
     */
    public function sendToRoles(
        array $roles,
        string $dedupeKey,
        string $title,
        string $message,
        NotificationPriority $priority,
        NotificationDestination $destination,
        array $routeParameters = [],
        ?User $except = null,
    ): int {
        $roleValues = collect([...$roles, UserRole::SuperAdministrator])
            ->unique(fn (UserRole $role) => $role->value)
            ->map->value
            ->all();
        $sent = 0;

        User::query()
            ->where('status', UserStatus::Active->value)
            ->whereIn('role', $roleValues)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->eachById(function (User $recipient) use (
                &$sent,
                $dedupeKey,
                $title,
                $message,
                $priority,
                $destination,
                $routeParameters,
            ): void {
                $sent += (int) $this->sendToUser(
                    $recipient,
                    $dedupeKey,
                    $title,
                    $message,
                    $priority,
                    $destination,
                    $routeParameters,
                );
            });

        return $sent;
    }
}
