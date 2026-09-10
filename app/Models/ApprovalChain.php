<?php

namespace App\Models;

use App\Enums\ApprovalChainType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalChain extends Model
{
    use HasFactory;

    protected $fillable = [
        'chain_type',
        'target_id',
        'total_commitment_amount',
        'status',
    ];

    protected $casts = [
        'chain_type' => ApprovalChainType::class,
        'total_commitment_amount' => 'decimal:2',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_number');
    }

    public function currentPendingStep(): ?ApprovalStep
    {
        return $this->steps()->where('status', 'pending')->first();
    }

    public function isFullyApproved(): bool
    {
        return ! $this->steps()->where('status', '!=', 'approved')->where('status', '!=', 'skipped')->exists();
    }
}
