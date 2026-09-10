<?php

namespace App\Models;

use App\Enums\ProcurementMethod;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourcingRfq extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfq_number',
        'title',
        'description',
        'purchase_request_id',
        'created_by_user_id',
        'procurement_method',
        'bidding_type',
        'submission_deadline',
        'status',
        'terms_conditions',
        'currency',
        'weight_price',
        'weight_technical',
        'weight_quality',
        'weight_lead_time',
        'published_at',
        'unsealed_at',
        'unsealed_by_user_id',
    ];

    protected $casts = [
        'procurement_method' => ProcurementMethod::class,
        'bidding_type' => RfqBiddingType::class,
        'status' => RfqStatus::class,
        'submission_deadline' => 'datetime',
        'published_at' => 'datetime',
        'unsealed_at' => 'datetime',
        'weight_price' => 'decimal:2',
        'weight_technical' => 'decimal:2',
        'weight_quality' => 'decimal:2',
        'weight_lead_time' => 'decimal:2',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function unsealedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unsealed_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RfqLineItem::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqSupplierInvitation::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SourcingEvaluation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function isDeadlineElapsed(): bool
    {
        return $this->submission_deadline !== null && now()->greaterThanOrEqualTo($this->submission_deadline);
    }

    public function isSealed(): bool
    {
        return $this->bidding_type === RfqBiddingType::Sealed && $this->unsealed_at === null;
    }

    public function isBiddingClosed(): bool
    {
        return $this->status === RfqStatus::BiddingClosed
            || ($this->status === RfqStatus::Published && $this->isDeadlineElapsed());
    }

    public function canBeEvaluated(): bool
    {
        if ($this->status === RfqStatus::Draft
            || $this->status === RfqStatus::Cancelled
            || $this->status === RfqStatus::Awarded) {
            return false;
        }

        if (! $this->quotes()->exists()) {
            return false;
        }

        if ($this->isSealed() && ! $this->isDeadlineElapsed()) {
            return false;
        }

        return true;
    }

    public function closeBiddingIfExpired(): bool
    {
        if ($this->status === RfqStatus::Published && $this->isDeadlineElapsed()) {
            $this->status = RfqStatus::BiddingClosed;
            $this->save();

            return true;
        }

        return false;
    }

    public function effectiveStatus(): RfqStatus
    {
        if ($this->status === RfqStatus::Published && $this->isDeadlineElapsed()) {
            return RfqStatus::BiddingClosed;
        }

        return $this->status;
    }
}
