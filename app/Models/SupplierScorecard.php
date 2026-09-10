<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierScorecard extends Model
{
    use HasFactory;

    protected $table = 'supplier_scorecards';

    protected $fillable = [
        'kpi_process_review_id',
        'supplier_id',
        'delivery_score',
        'quality_score',
        'fill_rate_score',
        'total_score',
        'total_pos_count',
        'completed_pos_count',
        'late_deliveries_count',
        'avg_lead_time_days',
        'promised_lead_time_days',
        'non_conformance_count',
        'temperature_excursions_count',
        'has_valid_lto',
        'has_valid_cpr',
        'recommendation',
        'notes',
    ];

    protected $casts = [
        'delivery_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'fill_rate_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'avg_lead_time_days' => 'decimal:2',
        'promised_lead_time_days' => 'decimal:2',
        'total_pos_count' => 'integer',
        'completed_pos_count' => 'integer',
        'late_deliveries_count' => 'integer',
        'non_conformance_count' => 'integer',
        'temperature_excursions_count' => 'integer',
        'has_valid_lto' => 'boolean',
        'has_valid_cpr' => 'boolean',
    ];

    public function processReview(): BelongsTo
    {
        return $this->belongsTo(KpiProcessReview::class, 'kpi_process_review_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function getRecommendationBadgeClassAttribute(): string
    {
        return match ($this->recommendation) {
            'preferred' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            'retain' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            'conditional_capa' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
            'suspend' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
            default => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        };
    }
}
