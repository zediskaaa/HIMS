<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCenterBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'cost_center_id',
        'fiscal_year',
        'allocated_budget',
        'soft_encumbered',
        'hard_encumbered',
        'spent_amount',
        'currency',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'allocated_budget' => 'decimal:2',
        'soft_encumbered' => 'decimal:2',
        'hard_encumbered' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function availableBudget(): float
    {
        return (float) max(0, $this->allocated_budget - ($this->soft_encumbered + $this->hard_encumbered + $this->spent_amount));
    }

    public function totalCommitted(): float
    {
        return (float) ($this->soft_encumbered + $this->hard_encumbered + $this->spent_amount);
    }

    public function utilizationPercentage(): float
    {
        if ($this->allocated_budget <= 0) {
            return 0.0;
        }

        return round(($this->totalCommitted() / $this->allocated_budget) * 100, 2);
    }
}
