<?php

namespace App\Models;

use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_chain_id',
        'step_number',
        'required_role',
        'approver_user_id',
        'status',
        'threshold_min',
        'threshold_max',
        'decision_notes',
        'digital_signature_token',
        'decided_at',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'status' => ApprovalStepStatus::class,
        'threshold_min' => 'decimal:2',
        'threshold_max' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'approval_chain_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
