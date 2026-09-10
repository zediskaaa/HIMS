<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_number',
        'title',
        'description',
        'requester_id',
        'cost_center_id',
        'procurement_category_id',
        'total_estimated_amount',
        'currency',
        'priority',
        'status',
        'is_emergency',
        'submitted_at',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'total_estimated_amount' => 'decimal:2',
        'is_emergency' => 'boolean',
        'status' => RequisitionStatus::class,
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcurementCategory::class, 'procurement_category_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function sourcingRfq(): HasOne
    {
        return $this->hasOne(SourcingRfq::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function approvalChain(): HasOne
    {
        return $this->hasOne(ApprovalChain::class, 'target_id')
            ->where('chain_type', 'purchase_request');
    }

    public function recalculateTotal(): void
    {
        $this->total_estimated_amount = $this->lines()->sum('estimated_total_price');
        $this->save();
    }
}
