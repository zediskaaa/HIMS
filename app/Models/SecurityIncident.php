<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncident extends Model
{
    use HasFactory;

    public const CATEGORY_UNAUTHORIZED_ACCESS = 'unauthorized_access_attempt';
    public const CATEGORY_BRUTE_FORCE_SPIKE = 'brute_force_spike';
    public const CATEGORY_SUSPICIOUS_EXPORT = 'suspicious_export';
    public const CATEGORY_CREDENTIAL_ANOMALY = 'credential_anomaly';
    public const CATEGORY_DATA_LEAKAGE_RISK = 'data_leakage_risk';
    public const CATEGORY_SYSTEM_TAMPERING = 'system_tampering';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_DETECTED = 'detected';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_CONTAINED = 'contained';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_FALSE_POSITIVE = 'false_positive';

    protected $table = 'security_incidents';

    protected $fillable = [
        'incident_number',
        'title',
        'category',
        'severity',
        'status',
        'description',
        'affected_system_or_data',
        'is_suspected_breach',
        'breach_assessment',
        'containment_actions',
        'remediation_notes',
        'detected_at',
        'resolved_at',
        'reported_by_user_id',
        'assigned_to_user_id',
        'resolved_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_suspected_breach' => 'boolean',
            'metadata' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SecurityIncident $model) {
            if (empty($model->incident_number)) {
                $model->incident_number = static::generateIncidentNumber();
            }
            if (empty($model->category)) {
                $model->category = 'unauthorized_access_attempt';
            }
            if (empty($model->affected_system_or_data)) {
                $model->affected_system_or_data = 'HIMS Core Platform';
            }
            if (empty($model->detected_at)) {
                $model->detected_at = now();
            }
        });
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function getIncidentTypeAttribute(): string
    {
        return $this->category;
    }

    public function setIncidentTypeAttribute(string $value): void
    {
        $this->attributes['category'] = $value;
    }

    public function getIsReportableBreachAttribute(): bool
    {
        return (bool) ($this->is_suspected_breach || ($this->metadata['is_reportable_breach'] ?? false));
    }

    public function setIsReportableBreachAttribute(bool|int $value): void
    {
        $this->attributes['is_suspected_breach'] = (bool) $value;
    }

    public function getAffectedSubjectsCountAttribute(): ?int
    {
        return isset($this->metadata['affected_subjects_count']) ? (int) $this->metadata['affected_subjects_count'] : null;
    }

    public function setAffectedSubjectsCountAttribute(?int $value): void
    {
        $meta = $this->metadata ?? [];
        $meta['affected_subjects_count'] = $value;
        $this->metadata = $meta;
    }

    public function getNpcNotifiedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        $val = $this->metadata['npc_notified_at'] ?? null;
        return $val ? \Illuminate\Support\Carbon::parse($val) : null;
    }

    public function setNpcNotifiedAtAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['npc_notified_at'] = $value ? \Illuminate\Support\Carbon::parse($value)->toIso8601String() : null;
        $this->metadata = $meta;
    }

    public function getSeverityLabelAttribute(): string
    {
        return $this->severityBadge()['label'];
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusBadge()['label'];
    }

    public function getReportedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->detected_at ?? $this->created_at;
    }

    public static function generateIncidentNumber(): string
    {
        return 'INC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function severityBadge(): array
    {
        return match ($this->severity) {
            self::SEVERITY_LOW => ['tone' => 'neutral', 'label' => 'Low Risk'],
            self::SEVERITY_MEDIUM => ['tone' => 'warning', 'label' => 'Medium Risk'],
            self::SEVERITY_HIGH => ['tone' => 'danger', 'label' => 'High Risk'],
            self::SEVERITY_CRITICAL => ['tone' => 'danger', 'label' => 'Critical Priority'],
            default => ['tone' => 'neutral', 'label' => ucfirst($this->severity)],
        };
    }

    public function statusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_DETECTED => ['tone' => 'warning', 'label' => 'Detected'],
            self::STATUS_INVESTIGATING => ['tone' => 'primary', 'label' => 'Investigating'],
            self::STATUS_CONTAINED => ['tone' => 'primary', 'label' => 'Contained'],
            self::STATUS_RESOLVED => ['tone' => 'success', 'label' => 'Resolved'],
            self::STATUS_FALSE_POSITIVE => ['tone' => 'neutral', 'label' => 'False Positive'],
            default => ['tone' => 'neutral', 'label' => ucfirst($this->status)],
        };
    }
}
