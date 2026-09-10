<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE_KEY_PATTERN = '/(?:password|passphrase|token|secret|otp|totp|authorization|cookie|session|api[_-]?key|private[_-]?key|file[_-]?(?:content|contents)|document[_-]?content)/i';

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
        ?string $module = null,
        ?string $category = null,
        string $outcome = 'success',
        ?string $source = null,
        ?string $businessReason = null,
        ?string $correlationId = null,
        ?string $targetType = null,
        string|int|null $targetId = null,
        ?string $targetReference = null,
    ): AuditLog {
        $request = app()->bound('request') ? app(Request::class) : null;
        $eventId = (string) Str::uuid();
        $displayTimezone = (string) config('app.timezone', 'UTC');

        return AuditLog::create([
            'event_id' => $eventId,
            // A just-deleted user can still trigger Laravel's Logout event.
            // Preserve their snapshot, but do not write a dangling foreign key.
            'user_id' => $actor?->exists === true ? $actor->getKey() : null,
            'actor_name' => $actor?->name ?? 'System',
            'actor_employee_id' => $actor?->employee_id,
            'actor_role' => $actor?->role?->value,
            'action' => $action,
            'event_category' => $category ?? $action->category(),
            'module' => $module ?? $action->module(),
            'target_type' => $target?->getMorphClass() ?? $targetType,
            'target_id' => $target?->getKey() === null
                ? ($targetId === null ? null : (string) $targetId)
                : (string) $target->getKey(),
            'target_name' => $targetName,
            'target_reference' => $targetReference ?? $targetName,
            'description' => Str::limit($description, 4000, '...'),
            'business_reason' => $businessReason === null ? null : Str::limit($businessReason, 4000, '...'),
            'outcome' => in_array($outcome, ['success', 'failure'], true) ? $outcome : 'success',
            'source' => $source ?? ($actor === null ? 'system' : 'user'),
            'correlation_id' => $this->correlationId($request, $correlationId, $eventId),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at_utc' => now('UTC')->format('Y-m-d H:i:s.u'),
            'display_timezone' => $displayTimezone,
        ]);
    }

    /**
     * Named-argument friendly alias for log().
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        AuditAction $action,
        ?User $actor = null,
        ?Model $target = null,
        string $description = '',
        ?string $targetName = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $module = null,
        ?string $category = null,
        string $outcome = 'success',
        ?string $source = null,
        ?string $businessReason = null,
        ?string $correlationId = null,
        ?string $targetType = null,
        string|int|null $targetId = null,
        ?string $targetReference = null,
    ): AuditLog {
        return $this->log(
            action: $action,
            actor: $actor,
            description: $description,
            target: $target,
            targetName: $targetName,
            oldValues: $oldValues,
            newValues: $newValues,
            module: $module,
            category: $category,
            outcome: $outcome,
            source: $source,
            businessReason: $businessReason,
            correlationId: $correlationId,
            targetType: $targetType,
            targetId: $targetId,
            targetReference: $targetReference,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(array $values, int $depth = 0): ?array
    {
        if ($values === [] || $depth > 4) {
            return null;
        }

        $safe = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitize($value, $depth + 1);
                if ($nested !== null) {
                    $safe[$key] = $nested;
                }

                continue;
            }

            if (is_string($value)) {
                $safe[$key] = Str::limit($value, 2000, '...');
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe === [] ? null : $safe;
    }

    private function correlationId(?Request $request, ?string $provided, string $fallback): string
    {
        $candidate = trim((string) ($provided ?: $request?->headers->get('X-Request-ID')));

        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $candidate) === 1) {
            return $candidate;
        }

        if ($request !== null) {
            $existing = $request->attributes->get('audit_correlation_id');
            if (is_string($existing)) {
                return $existing;
            }

            $request->attributes->set('audit_correlation_id', $fallback);
        }

        return $fallback;
    }
}
