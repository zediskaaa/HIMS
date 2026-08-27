<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
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

    public function __construct(private readonly AuditLogger $audit) {}

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

            $this->audit->log(
                AuditAction::UpdatedUser,
                $actor,
                "Updated user information for {$user->name}. Changed: {$labels}.",
                $user,
                $user->name,
                $oldValues,
                $newValues,
            );
        }

        if ($passwordChanged) {
            $description = $actor->is($user)
                ? 'Changed their account password.'
                : "Changed the password for {$user->name}.";

            $this->audit->log(
                AuditAction::ChangedPassword,
                $actor,
                $description,
                $user,
                $user->name,
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
