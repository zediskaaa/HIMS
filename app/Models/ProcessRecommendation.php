<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessRecommendation extends Model
{
    use HasFactory;

    protected $table = 'process_recommendations';

    protected $fillable = [
        'kpi_process_review_id',
        'category',
        'target_type',
        'target_id',
        'target_name',
        'problem_detected',
        'evidence_metrics',
        'root_cause_analysis',
        'recommended_action',
        'expected_operational_benefit',
        'priority',
        'status',
        'implemented_by_id',
        'implemented_at',
        'implementation_notes',
    ];

    protected $casts = [
        'evidence_metrics' => 'array',
        'implemented_at' => 'datetime',
    ];

    public function processReview(): BelongsTo
    {
        return $this->belongsTo(KpiProcessReview::class, 'kpi_process_review_id');
    }

    public function implementedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implemented_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isImplemented(): bool
    {
        return $this->status === 'implemented';
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority) {
            'critical' => 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
            'high' => 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
            'medium' => 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
            default => 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        };
    }
}
