<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ChainOfCustodyLog extends Model
{
    use HasFactory;

    protected $table = 'chain_of_custody_logs';

    protected $fillable = [
        'custody_number',
        'trackable_type',
        'trackable_id',
        'event_type',
        'releasing_user_id',
        'releasing_party_name',
        'receiving_user_id',
        'receiving_party_name',
        'transferred_at',
        'origin_location',
        'destination_location',
        'package_condition',
        'verification_method',
        'notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function releasingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'releasing_user_id');
    }

    public function receivingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiving_user_id');
    }

    /**
     * Enforce immutable append-only ledger mechanics.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Chain of custody log records are immutable and append-only.'));
        static::deleting(fn () => throw new LogicException('Chain of custody log records are immutable and cannot be deleted.'));
    }
}
