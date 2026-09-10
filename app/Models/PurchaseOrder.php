<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'purchase_request_id',
        'sourcing_rfq_id',
        'cost_center_id',
        'item_id',
        'quantity',
        'unit_cost',
        'total_amount',
        'currency',
        'exchange_rate',
        'total_encumbered_amount',
        'payment_terms',
        'incoterms',
        'version',
        'revision_number',
        'status',
        'notes',
        'requested_at',
        'dispatched_at',
        'received_at',
        'cxml_payload',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_encumbered_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'revision_number' => 'integer',
        'requested_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function sourcingRfq(): BelongsTo
    {
        return $this->belongsTo(SourcingRfq::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PurchaseOrderRevision::class)->orderByDesc('revision_number');
    }

    public function approvalChain(): HasOne
    {
        return $this->hasOne(ApprovalChain::class, 'target_id')
            ->where('chain_type', 'purchase_order');
    }

    public function statusEnum(): PurchaseOrderStatus
    {
        return is_string($this->status)
            ? (PurchaseOrderStatus::tryFrom($this->status) ?? PurchaseOrderStatus::Draft)
            : $this->status;
    }

    public function isFullyReceived(): bool
    {
        if ($this->lines()->exists()) {
            return ! $this->lines()->whereRaw('received_quantity < ordered_quantity')->exists();
        }

        return $this->status === 'received' || $this->status === PurchaseOrderStatus::Fulfilled->value;
    }
}
