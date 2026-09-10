<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InspectionAcceptanceReport extends Model
{
    use HasFactory;

    protected $table = 'inspection_acceptance_reports';

    protected $fillable = [
        'iar_number',
        'goods_receipt_note_id',
        'purchase_order_id',
        'supplier_id',
        'invoice_number',
        'iar_date',
        'inspection_date',
        'inspected_by_id',
        'inspection_status',
        'inspection_findings',
        'acceptance_date',
        'accepted_by_id',
        'delivery_status',
        'status',
        'days_delayed',
        'liquidated_damages_amount',
        'coa_transmittal_deadline_at',
        'coa_transmitted_at',
        'coa_received_by',
        'notes',
    ];

    protected $casts = [
        'iar_date' => 'date',
        'inspection_date' => 'date',
        'acceptance_date' => 'date',
        'coa_transmittal_deadline_at' => 'date',
        'coa_transmitted_at' => 'date',
        'days_delayed' => 'integer',
        'liquidated_damages_amount' => 'decimal:2',
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LogisticsDocument::class, 'inspection_acceptance_report_id');
    }

    public function custodyLogs(): MorphMany
    {
        return $this->morphMany(ChainOfCustodyLog::class, 'trackable');
    }

    public function isInspected(): bool
    {
        return in_array($this->status, ['inspected_passed', 'pending_acceptance', 'accepted', 'posted_to_inventory'], true);
    }

    public function isAccepted(): bool
    {
        return in_array($this->status, ['accepted', 'posted_to_inventory'], true);
    }

    public function isRejected(): bool
    {
        return in_array($this->status, ['inspected_failed', 'rejected'], true);
    }

    public function isCoaTransmitted(): bool
    {
        return ! empty($this->coa_transmitted_at);
    }

    /**
     * Determine if COA 5-day transmittal window is expiring soon or overdue.
     */
    public function isCoaDeadlineUrgent(): bool
    {
        if ($this->isCoaTransmitted() || empty($this->coa_transmittal_deadline_at)) {
            return false;
        }

        return Carbon::parse($this->coa_transmittal_deadline_at)->diffInDays(now(), false) >= -2;
    }
}
