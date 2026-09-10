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
