<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqSupplierInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'sourcing_rfq_id',
        'supplier_id',
        'portal_token',
        'status',
        'invited_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SourcingRfq::class, 'sourcing_rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
