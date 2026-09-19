<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SecurityIncidentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Record a new security incident or suspected breach.
     *
     * @param  array{
     *     title: string,
     *     category: string,
     *     severity: string,
     *     description: string,
     *     affected_system_or_data: string,
     *     is_suspected_breach?: bool,
     *     breach_assessment?: string,
     *     metadata?: array<string, mixed>
     * }  $data
     */
    public function record(?User $reporter, array $data): SecurityIncident
    {
        return DB::transaction(function () use ($reporter, $data) {
            $incidentNumber = SecurityIncident::generateIncidentNumber();

            $incident = SecurityIncident::create([
                'incident_number' => $incidentNumber,
                'title' => $data['title'],
                'category' => $data['category'],
                'severity' => $data['severity'] ?? SecurityIncident::SEVERITY_MEDIUM,
                'status' => SecurityIncident::STATUS_DETECTED,
                'description' => $data['description'],
                'affected_system_or_data' => $data['affected_system_or_data'],
                'is_suspected_breach' => (bool) ($data['is_suspected_breach'] ?? false),
                'breach_assessment' => $data['breach_assessment'] ?? null,
                'detected_at' => now(),
                'reported_by_user_id' => $reporter?->id,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->auditLogger->record(
                action: AuditAction::RecordedSecurityIncident,
                actor: $reporter,
                target: $incident,
                targetName: "Security Incident {$incidentNumber}",
                description: "Recorded security incident {$incidentNumber}: [{$incident->title}] (Severity: {$incident->severity}).",
                newValues: [
                    'incident_number' => $incidentNumber,
                    'severity' => $incident->severity,
                    'is_suspected_breach' => $incident->is_suspected_breach,
                ]
            );

            return $incident;
        });
    }

    public function recordIncident(
        string $title,
        string $description,
        string $severity,
        string $incidentType,
        ?User $reportedBy = null,
        ?string $containmentActions = null,
        bool $isReportableBreach = false,
        ?string $affectedSystem = null,
        array $metadata = []
    ): SecurityIncident {
        $incident = $this->record($reportedBy, [
            'title' => $title,
            'category' => $incidentType,
            'severity' => $severity,
            'description' => $description,
            'affected_system_or_data' => $affectedSystem ?? 'HIMS Core Platform',
            'is_suspected_breach' => $isReportableBreach,
            'metadata' => $metadata,
        ]);

        if ($containmentActions) {
            $incident->containment_actions = $containmentActions;
            $incident->save();
        }

        return $incident;
    }

    public function updateIncidentStatus(
        SecurityIncident $incident,
        string $status,
        User $actor,
        ?string $remediationNotes = null,
        ?string $containmentActions = null,
        ?string $severity = null
    ): SecurityIncident {
        $updates = ['status' => $status];
        if ($severity !== null) {
            $updates['severity'] = $severity;
        }
        if ($containmentActions !== null) {
            $updates['containment_actions'] = $containmentActions;
        }
        if ($remediationNotes !== null) {
            $updates['remediation_notes'] = $remediationNotes;
        }

        return $this->update($incident, $actor, $updates);
    }

    public function assessBreachNotification(
        SecurityIncident $incident,
        bool $isReportableBreach,
        ?int $affectedCount = null,
        ?\Carbon\Carbon $notifiedAt = null,
        ?User $actor = null
    ): SecurityIncident {
        $incident->is_suspected_breach = $isReportableBreach;
        $meta = $incident->metadata ?? [];
        if ($affectedCount !== null) {
            $meta['affected_subjects_count'] = $affectedCount;
        }
        if ($notifiedAt !== null) {
            $meta['npc_notified_at'] = $notifiedAt->toIso8601String();
        }
        $incident->metadata = $meta;
        $incident->save();

        return $incident;
    }

    public function recordSuspiciousActivity(
        string $type,
        string $description,
        string $severity = SecurityIncident::SEVERITY_MEDIUM,
        ?User $actor = null,
        array $metadata = []
    ): SecurityIncident {
        return $this->record($actor, [
            'title' => 'Automated Anomaly: ' . ucwords(str_replace('_', ' ', $type)),
            'category' => $type,
            'severity' => $severity,
            'description' => $description,
            'affected_system_or_data' => 'Authentication & Access Security',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Update incident progress, containment actions, or resolution.
     *
     * @param  array{
     *     status?: string,
     *     severity?: string,
     *     containment_actions?: string,
     *     remediation_notes?: string,
     *     breach_assessment?: string,
     *     is_suspected_breach?: bool,
     *     assigned_to_user_id?: int
     * }  $updates
     */
    public function update(SecurityIncident $incident, User $actor, array $updates): SecurityIncident
    {
        return DB::transaction(function () use ($incident, $actor, $updates) {
            $oldStatus = $incident->status;

            if (isset($updates['status'])) {
                $incident->status = $updates['status'];
                if (in_array($updates['status'], [SecurityIncident::STATUS_RESOLVED, SecurityIncident::STATUS_FALSE_POSITIVE], true)) {
                    $incident->resolved_at = now();
                    $incident->resolved_by_user_id = $actor->id;
                }
            }

            if (isset($updates['severity'])) {
                $incident->severity = $updates['severity'];
            }

            if (isset($updates['containment_actions'])) {
                $incident->containment_actions = $updates['containment_actions'];
            }

            if (isset($updates['remediation_notes'])) {
                $incident->remediation_notes = $updates['remediation_notes'];
            }

            if (isset($updates['breach_assessment'])) {
                $incident->breach_assessment = $updates['breach_assessment'];
            }

            if (isset($updates['is_suspected_breach'])) {
                $incident->is_suspected_breach = (bool) $updates['is_suspected_breach'];
            }

            if (isset($updates['assigned_to_user_id'])) {
                $incident->assigned_to_user_id = $updates['assigned_to_user_id'];
            }

            $incident->save();

            $this->auditLogger->record(
                action: AuditAction::UpdatedSecurityIncident,
                actor: $actor,
                target: $incident,
                targetName: "Security Incident {$incident->incident_number}",
                description: "Updated security incident {$incident->incident_number} status to [{$incident->status}].",
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $incident->status,
                    'severity' => $incident->severity,
                    'is_suspected_breach' => $incident->is_suspected_breach,
                ]
            );

            return $incident;
        });
    }

    /**
     * Automated anomaly recorder (e.g. for lockout spikes or repeated unauthorized attempts).
     * Suppresses duplicate incidents within a 1-hour window for the same category and title.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function detectAnomaly(
        string $category,
        string $title,
        string $description,
        string $affectedSystem,
        string $severity = SecurityIncident::SEVERITY_MEDIUM,
        array $metadata = []
    ): ?SecurityIncident {
        $recentDuplicate = SecurityIncident::query()
            ->where('category', $category)
            ->where('title', $title)
            ->where('status', '!=', SecurityIncident::STATUS_RESOLVED)
            ->where('detected_at', '>=', now()->subHour())
            ->first();

        if ($recentDuplicate) {
            // Append metadata or update count
            $currentMeta = $recentDuplicate->metadata ?? [];
            $currentMeta['occurrences'] = ($currentMeta['occurrences'] ?? 1) + 1;
            $recentDuplicate->metadata = $currentMeta;
            $recentDuplicate->save();

            return $recentDuplicate;
        }

        return $this->record(null, [
            'title' => $title,
            'category' => $category,
            'severity' => $severity,
            'description' => $description,
            'affected_system_or_data' => $affectedSystem,
            'metadata' => array_merge($metadata, ['occurrences' => 1, 'automated_detection' => true]),
        ]);
    }

    /**
     * Get factual incident metrics for the security overview.
     *
     * @return array<string, int>
     */
    public function getMetrics(): array
    {
        return [
            'total' => SecurityIncident::count(),
            'open' => SecurityIncident::whereIn('status', [SecurityIncident::STATUS_DETECTED, SecurityIncident::STATUS_INVESTIGATING, SecurityIncident::STATUS_CONTAINED])->count(),
            'critical' => SecurityIncident::where('severity', SecurityIncident::SEVERITY_CRITICAL)->where('status', '!=', SecurityIncident::STATUS_RESOLVED)->count(),
            'suspected_breaches' => SecurityIncident::where('is_suspected_breach', true)->count(),
            'resolved_last_30d' => SecurityIncident::where('status', SecurityIncident::STATUS_RESOLVED)->where('resolved_at', '>=', now()->subDays(30))->count(),
        ];
    }
}
