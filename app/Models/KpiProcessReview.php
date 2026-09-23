<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiProcessReview extends Model
{
    use HasFactory;

    protected $table = 'kpi_process_reviews';

    protected $fillable = [
        'review_number',
        'title',
        'period_start',
        'period_end',
        'evaluator_id',
        'status',
        'approved_by_id',
        'approved_at',
        'rejection_reason',
        'qualitative_context',
        'executive_summary',
        'metrics_summary',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'metrics_summary' => 'array',
    ];

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function supplierScorecards(): HasMany
    {
        return $this->hasMany(SupplierScorecard::class, 'kpi_process_review_id');
    }

    public function procurementSavingsLogs(): HasMany
    {
        return $this->hasMany(ProcurementSavingsLog::class, 'kpi_process_review_id');
    }

    public function inventoryShrinkageReports(): HasMany
    {
        return $this->hasMany(InventoryShrinkageReport::class, 'kpi_process_review_id');
    }

    public function processRecommendations(): HasMany
    {
        return $this->hasMany(ProcessRecommendation::class, 'kpi_process_review_id');
    }

    public function getNetSavingsAmountAttribute(): float
    {
        if (isset($this->metrics_summary['net_savings_amount'])) {
            return (float) $this->metrics_summary['net_savings_amount'];
        }

        if ($this->relationLoaded('procurementSavingsLogs')) {
            return (float) $this->procurementSavingsLogs->sum('variance_amount');
        }

        return (float) ($this->procurementSavingsLogs()->sum('variance_amount') ?: 0.0);
    }

    public function getAggregateSavingsPctAttribute(): float
    {
        if (isset($this->metrics_summary['aggregate_savings_pct'])) {
            return (float) $this->metrics_summary['aggregate_savings_pct'];
        }

        $totalSpend = (float) ($this->metrics_summary['total_spend'] ?? ($this->metrics_summary['total_spend_evaluated'] ?? 0));
        if ($totalSpend > 0) {
            return round(($this->net_savings_amount / $totalSpend) * 100, 2);
        }

        return 0.0;
    }

    public function getAvgSupplierScoreAttribute(): ?float
    {
        if (isset($this->metrics_summary['avg_supplier_score'])) {
            return (float) $this->metrics_summary['avg_supplier_score'];
        }

        if ($this->relationLoaded('supplierScorecards')) {
            return $this->supplierScorecards->isNotEmpty()
                ? round((float) $this->supplierScorecards->avg('total_score'), 1)
                : null;
        }

        $avg = $this->supplierScorecards()->avg('total_score');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function getBottleneckStagesAttribute(): array
    {
        if (! empty($this->metrics_summary['bottleneck_stages'])) {
            return $this->metrics_summary['bottleneck_stages'];
        }

        try {
            $analyzer = app(\App\Services\Analytics\BottleneckAnalysisService::class);
            $evaluated = $analyzer->evaluate($this->period_start, $this->period_end);

            return $evaluated['stages'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getCriticalBottleneckAttribute(): ?array
    {
        if (isset($this->metrics_summary['critical_bottleneck'])) {
            return $this->metrics_summary['critical_bottleneck'];
        }

        try {
            $analyzer = app(\App\Services\Analytics\BottleneckAnalysisService::class);
            $evaluated = $analyzer->evaluate($this->period_start, $this->period_end);

            return $evaluated['critical_bottleneck'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isImplemented(): bool
    {
        return $this->status === 'implemented';
    }

    public function canBeApprovedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Segregation of duties: Creator/Evaluator cannot approve their own review
        if ($this->evaluator_id === $user->id) {
            return false;
        }

        return $user->hasPermission(\App\Enums\Permission::ApproveProcessReview);
    }
}
