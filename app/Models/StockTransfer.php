<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'source_location_id',
        'destination_location_id',
        'in_transit_location_id',
        'status',
        'dispatched_by_id',
        'dispatched_at',
        'received_by_id',
        'received_at',
        'notes',
        'discrepancy_reason',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'destination_location_id');
    }

    public function inTransitLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'in_transit_location_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }
}
