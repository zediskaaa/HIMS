<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'requisition_number',
        'requesting_user_id',
        'department',
        'cost_center_id',
        'required_date',
        'status',
        'urgency',
        'justification',
        'approved_by_id',
        'approved_at',
        'issued_by_id',
        'issued_at',
        'acknowledged_by_id',
        'acknowledged_at',
        'rejection_reason',
    ];

    protected $casts = [
        'required_date' => 'date',
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function requestingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requesting_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialRequisitionLine::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }
}
