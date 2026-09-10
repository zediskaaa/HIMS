<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_line_item_id',
        'item_id',
        'item_batch_id',
        'inspected_by_id',
        'inspection_status',
        'sample_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'inspection_date',
        'findings',
        'rejection_reason',
    ];

    protected $casts = [
        'sample_quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'inspection_date' => 'datetime',
    ];

    public function grnLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteLine::class, 'grn_line_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_id');
    }

    public function isApproved(): bool
    {
        return $this->inspection_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->inspection_status === 'rejected';
    }

    public function getSampleSizeAttribute(): int
    {
        return (int) ($this->sample_quantity ?? 0);
    }

    public function setSampleSizeAttribute($value): void
    {
        $this->attributes['sample_quantity'] = $value;
    }
}
