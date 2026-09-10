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
        'purchase_order_id',
        'supplier_id',
        'carrier_name',
        'waybill_number',
        'packing_slip_number',
        'received_by_id',
        'receipt_status',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
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
