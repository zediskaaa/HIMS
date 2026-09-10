<?php

namespace App\Services\Procurement;

use App\Enums\AuditAction;
use App\Models\ProcurementAuditLog;
use App\Models\User;
use App\Services\AuditLogger;

class ProcurementAuditService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function record(
        ?User $actor,
        string $entityName,
        int $entityId,
        string $actionType,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ProcurementAuditLog {
        $log = ProcurementAuditLog::create([
            'user_id' => $actor?->id,
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'action_type' => $actionType,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        // Keep the legacy procurement ledger while ensuring the organization-wide
        // append-only trail receives the same semantic business event.
        $matchingAuditAction = AuditAction::tryFrom($actionType);
        if ($matchingAuditAction) {
            $this->auditLogger->log(
                $matchingAuditAction,
                $actor,
                ($actor?->name ?? 'System')." performed {$actionType} on {$entityName} #{$entityId}.",
                targetName: "{$entityName} #{$entityId}",
                targetType: $entityName,
                targetId: $entityId,
                targetReference: "{$entityName} #{$entityId}",
                oldValues: $oldValues ?? [],
                newValues: $newValues ?? [],
                source: $actor === null ? 'system' : 'user',
            );
        }

        return $log;
    }
}
