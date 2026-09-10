<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_number',
        'dr_number',
        'sales_invoice_number',
        'purchase_order_id',
        'supplier_id',
        'carrier_name',
        'waybill_number',
        'packing_slip_number',
        'sscc',
        'is_cold_chain',
        'temp_logger_id',
        'transit_temp_min',
        'transit_temp_max',
        'temp_excursion',
        'received_by_id',
        'receipt_status',
        'delivery_status',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_cold_chain' => 'boolean',
        'temp_excursion' => 'boolean',
        'transit_temp_min' => 'decimal:2',
        'transit_temp_max' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteLine::class);
    }

    public function inspectionAcceptanceReport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InspectionAcceptanceReport::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LogisticsDocument::class);
    }

    public function custodyLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ChainOfCustodyLog::class, 'trackable');
    }

    public function isDraft(): bool
    {
        return $this->receipt_status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->receipt_status === 'posted';
    }

    public function isQuarantined(): bool
    {
        return in_array($this->receipt_status, ['under_qc', 'quarantined'], true);
    }

    public function getStatusAttribute(): string
    {
        return $this->receipt_status ?? 'draft';
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['receipt_status'] = $value;
    }
}
