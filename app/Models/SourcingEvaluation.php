<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcingEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'sourcing_rfq_id',
        'supplier_quote_id',
        'evaluator_user_id',
        'commercial_score',
        'technical_score',
        'quality_score',
        'lead_time_score',
        'composite_score',
        'normalized_landed_cost',
        'conflict_of_interest_declared',
        'justification_notes',
        'completed_at',
    ];

    protected $casts = [
        'commercial_score' => 'decimal:2',
        'technical_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'lead_time_score' => 'decimal:2',
        'composite_score' => 'decimal:2',
        'normalized_landed_cost' => 'decimal:2',
        'conflict_of_interest_declared' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SourcingRfq::class, 'sourcing_rfq_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class, 'supplier_quote_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }
}
