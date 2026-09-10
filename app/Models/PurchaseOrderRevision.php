<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderRevision extends Model
{
    use HasFactory;

    protected $table = 'po_revisions';

    protected $fillable = [
        'purchase_order_id',
        'revision_number',
        'change_order_code',
        'justification',
        'delta_amount',
        'variance_percentage',
        'original_snapshot',
        'proposed_snapshot',
        'requires_doa_reapproval',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'revision_number' => 'integer',
        'delta_amount' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'original_snapshot' => 'array',
        'proposed_snapshot' => 'array',
        'requires_doa_reapproval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
