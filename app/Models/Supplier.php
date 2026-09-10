<?php

namespace App\Models;

use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierDocumentStatus;
use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'trade_name',
        'business_structure',
        'provides_regulated_health_products',
        'contact_person',
        'email',
        'phone',
        'address',
        'billing_address',
        'delivery_address',
        'tax_number',
        'identity_key',
        'status',
        'accreditation_status',
        'accreditation_expires_at',
        'standard_lead_time_days',
        'payment_terms',
        'notes',
        'created_by',
        'reviewed_by',
        'approved_by',
        'last_reviewed_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'provides_regulated_health_products' => 'boolean',
            'status' => SupplierStatus::class,
            'accreditation_status' => SupplierAccreditationStatus::class,
            'accreditation_expires_at' => 'date',
            'standard_lead_time_days' => 'integer',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function accreditations(): HasMany
    {
        return $this->hasMany(SupplierAccreditation::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    public function complianceAlerts(): HasMany
    {
        return $this->hasMany(SupplierComplianceAlert::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function effectiveAccreditationStatus(): SupplierAccreditationStatus
    {
        if ($this->accreditation_status === SupplierAccreditationStatus::Approved
            && $this->accreditation_expires_at?->lt(today())) {
            return SupplierAccreditationStatus::Expired;
        }

        return $this->accreditation_status;
    }

    public function hasBlockingComplianceIssue(): bool
    {
        return $this->documents()
            ->where('is_current', true)
            ->where('blocks_procurement_when_invalid', true)
            ->where(function (Builder $query): void {
                $query->where('verification_status', '!=', SupplierDocumentStatus::Verified->value)
                    ->orWhere(fn (Builder $expiry) => $expiry
                        ->whereNotNull('expires_at')
                        ->whereDate('expires_at', '<', today()));
            })
            ->exists();
    }

    public function isProcurementEligible(): bool
    {
        return $this->status === SupplierStatus::Active
            && $this->effectiveAccreditationStatus() === SupplierAccreditationStatus::Approved
            && ! $this->hasBlockingComplianceIssue();
    }

    /**
     * @return array<int, string>
     */
    public function accreditationReviewReadinessIssues(): array
    {
        $issues = [];
        if (blank($this->business_structure)) {
            $issues[] = 'business structure';
        }
        if (! collect([$this->address, $this->billing_address, $this->delivery_address])->contains(fn ($value) => filled($value))) {
            $issues[] = 'an address';
        }

        $hasContact = filled($this->email) || filled($this->phone)
            || $this->contacts()->where('is_active', true)->where(fn ($query) => $query
                ->where(fn ($email) => $email->whereNotNull('email')->where('email', '!=', ''))
                ->orWhere(fn ($phone) => $phone->whereNotNull('phone')->where('phone', '!=', ''))
                ->orWhere(fn ($mobile) => $mobile->whereNotNull('mobile')->where('mobile', '!=', '')))->exists();
        if (! $hasContact) {
            $issues[] = 'an active email or phone contact';
        }

        return $issues;
    }

    public function isReadyForAccreditationReview(): bool
    {
        return $this->accreditationReviewReadinessIssues() === [];
    }

    public function complianceState(): string
    {
        if ($this->effectiveAccreditationStatus() === SupplierAccreditationStatus::Expired
            || $this->hasBlockingComplianceIssue()) {
            return 'action_required';
        }

        $warningDate = today()->addDays(30);
        $hasExpiring = ($this->accreditation_expires_at?->between(today(), $warningDate) ?? false)
            || $this->documents()->where('is_current', true)->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', today())
                ->whereDate('expires_at', '<=', $warningDate)
                ->exists();

        return $hasExpiring ? 'expiring_soon' : 'current';
    }

    public function scopeProcurementEligible(Builder $query): Builder
    {
        return $query
            ->where('status', SupplierStatus::Active->value)
            ->where('accreditation_status', SupplierAccreditationStatus::Approved->value)
            ->where(fn (Builder $dates) => $dates
                ->whereNull('accreditation_expires_at')
                ->orWhereDate('accreditation_expires_at', '>=', today()))
            ->whereDoesntHave('documents', fn (Builder $documents) => $documents
                ->where('is_current', true)
                ->where('blocks_procurement_when_invalid', true)
                ->where(function (Builder $invalid): void {
                    $invalid->where('verification_status', '!=', SupplierDocumentStatus::Verified->value)
                        ->orWhere(fn (Builder $expiry) => $expiry
                            ->whereNotNull('expires_at')
                            ->whereDate('expires_at', '<', today()));
                }));
    }
}
