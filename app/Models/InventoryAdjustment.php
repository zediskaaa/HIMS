<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_number',
        'item_id',
        'storage_location_id',
        'item_batch_id',
        'current_quantity',
        'adjustment_quantity',
        'resulting_quantity',
        'unit_cost',
        'total_variance_value',
        'adjustment_type',
        'reason_code',
        'explanation',
        'status',
        'requested_by_id',
        'approved_by_id',
        'second_approved_by_id',
        'posted_at',
        'rejection_reason',
    ];

    protected $casts = [
        'current_quantity' => 'integer',
        'adjustment_quantity' => 'integer',
        'resulting_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_variance_value' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function secondApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_approved_by_id');
    }

    /**
     * Threshold for dual approval: $500 USD or approx ₱25,000 PHP.
     */
    public function requiresDualApproval(): bool
    {
        return abs((float) $this->total_variance_value) > 25000.00;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}
