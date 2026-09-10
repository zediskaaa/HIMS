<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_request_id',
        'sourcing_rfq_id',
        'supplier_id',
        'quote_number',
        'quoted_price',
        'total_bid_amount',
        'currency',
        'exchange_rate',
        'incoterms',
        'payment_terms',
        'validity_end_date',
        'status',
        'is_sealed',
        'unsealed_at',
        'is_awarded',
        'notes',
    ];

    protected $casts = [
        'quoted_price' => 'decimal:2',
        'total_bid_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'validity_end_date' => 'date',
        'is_sealed' => 'boolean',
        'unsealed_at' => 'datetime',
        'is_awarded' => 'boolean',
    ];

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SourcingRfq::class, 'sourcing_rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SourcingEvaluation::class);
    }

    public function isMasked(): bool
    {
        if (! $this->is_sealed) {
            return false;
        }

        if ($this->unsealed_at !== null) {
            return false;
        }

        // If linked to an RFQ, check if RFQ deadline has not elapsed and RFQ is still sealed
        if ($this->rfq !== null) {
            return $this->rfq->isSealed();
        }

        return $this->is_sealed;
    }

    public function totalLandedCost(): float
    {
        if ($this->lines()->exists()) {
            return (float) $this->lines()->sum('landed_cost');
        }

        return (float) ($this->total_bid_amount > 0 ? $this->total_bid_amount : $this->quoted_price);
    }
}
