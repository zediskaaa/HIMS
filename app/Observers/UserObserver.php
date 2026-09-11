<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HimsNotificationService;
use Illuminate\Support\Str;

class UserObserver
{
    /** @var list<string> */
    private const AUDITABLE_FIELDS = [
        'surname',
        'first_name',
        'middle_name',
        'email',
        'employee_id',
        'department',
        'phone',
        'role',
        'status',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HimsNotificationService $notifications,
    ) {}

    public function created(User $user): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        $this->audit->log(
            AuditAction::CreatedUser,
            $actor,
            "Created a new user account for {$user->name}.",
            $user,
            $user->name,
            newValues: $this->snapshot($user),
        );
    }

    public function updated(User $user): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        $changes = $user->getChanges();
        $passwordChanged = array_key_exists('password', $changes);
        $mfaChanged = array_key_exists('mfa_enabled', $changes)
            || array_key_exists('authenticator_enabled_at', $changes);
        $changedFields = array_values(array_intersect(self::AUDITABLE_FIELDS, array_keys($changes)));

        if ($changedFields !== []) {
            $oldValues = [];
            $newValues = [];

            foreach ($changedFields as $field) {
                $oldValues[$field] = $user->getRawOriginal($field);
                $newValues[$field] = $user->getAttributes()[$field] ?? null;
            }

            $labels = collect($changedFields)
                ->map(fn (string $field) => Str::headline($field))
                ->implode(', ');

            $auditLog = $this->audit->log(
                AuditAction::UpdatedUser,
                $actor,
                "Updated user information for {$user->name}. Changed: {$labels}.",
                $user,
                $user->name,
                $oldValues,
                $newValues,
            );

            if (array_intersect($changedFields, ['email', 'role', 'status']) !== []) {
                $this->notifications->sendToUser(
                    $user,
                    "account-security:{$auditLog->event_id}",
                    'Account settings changed',
                    'Your HIMS email, role, or account status was changed. Contact an administrator if this was unexpected.',
                    NotificationPriority::Warning,
                    NotificationDestination::Profile,
                );
            }
        }

        if ($passwordChanged) {
            $description = $actor->is($user)
                ? 'Changed their account password.'
                : "Changed the password for {$user->name}.";

            $auditLog = $this->audit->log(
                AuditAction::ChangedPassword,
                $actor,
                $description,
                $user,
                $user->name,
            );

            $this->notifications->sendToUser(
                $user,
                "password-changed:{$auditLog->event_id}",
                'Password changed',
                'Your HIMS password was changed. Contact an administrator immediately if this was not you.',
                NotificationPriority::Warning,
                NotificationDestination::Profile,
            );
        }

        if ($mfaChanged) {
            $factor = array_key_exists('authenticator_enabled_at', $changes)
                ? 'Authenticator app MFA'
                : 'Email MFA';
            $wasEnabled = array_key_exists('authenticator_enabled_at', $changes)
                ? filled($user->getRawOriginal('authenticator_enabled_at'))
                : (bool) $user->getRawOriginal('mfa_enabled');
            $enabled = array_key_exists('authenticator_enabled_at', $changes)
                ? $user->authenticator_enabled_at !== null
                : (bool) $user->mfa_enabled;
            $state = $enabled ? 'enabled' : 'disabled';

            $auditLog = $this->audit->log(
                AuditAction::ChangedMfa,
                $actor,
                "{$state} {$factor} for {$user->name}.",
                $user,
                $user->name,
                oldValues: ['factor' => $factor, 'enabled' => $wasEnabled],
                newValues: ['factor' => $factor, 'enabled' => $enabled],
            );

            $this->notifications->sendToUser(
                $user,
                "mfa-changed:{$auditLog->event_id}",
                "{$factor} {$state}",
                "{$factor} was {$state} for your HIMS account. Contact an administrator if this was unexpected.",
                NotificationPriority::Warning,
                NotificationDestination::Profile,
            );
        }
    }

    public function deleting(User $user): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        $this->audit->log(
            AuditAction::DeletedUser,
            $actor,
            "Deleted the user account for {$user->name}.",
            $user,
            $user->name,
            oldValues: $this->snapshot($user),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return collect(self::AUDITABLE_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $user->getAttributes()[$field] ?? null])
            ->all();
    }
}
