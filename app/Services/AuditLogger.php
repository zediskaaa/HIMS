<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        AuditAction $action,
        ?User $actor,
        string $description,
        ?Model $target = null,
        ?string $targetName = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        $request = app()->bound('request') ? app(Request::class) : null;

        return AuditLog::create([
            // A just-deleted user can still trigger Laravel's Logout event.
            // Preserve their snapshot, but do not write a dangling foreign key.
            'user_id' => $actor?->exists === true ? $actor->getKey() : null,
            'actor_name' => $actor?->name ?? 'System',
            'actor_employee_id' => $actor?->employee_id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey() === null ? null : (string) $target->getKey(),
            'target_name' => $targetName,
            'description' => $description,
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
