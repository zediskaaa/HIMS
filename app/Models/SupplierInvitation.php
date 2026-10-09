<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REVOKED = 'revoked';

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_FAILED = 'failed';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'delivery_attempts' => 'integer',
            'invited_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
            'opened_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function displayStatus(): string
    {
        if ($this->status !== self::STATUS_PENDING) {
            return $this->status;
        }

        if ($this->delivery_status === self::DELIVERY_FAILED) {
            return self::DELIVERY_FAILED;
        }

        return $this->expires_at->isPast() ? 'expired' : self::STATUS_PENDING;
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            self::STATUS_PENDING => 'Invitation Pending',
            self::STATUS_ACCEPTED => 'Invitation Accepted',
            self::STATUS_REVOKED => 'Invitation Revoked',
            self::DELIVERY_FAILED => 'Delivery Failed',
            'expired' => 'Invitation Expired',
            default => 'Invitation Status Unknown',
        };
    }

    public function canResend(): bool
    {
        return ($this->status === self::STATUS_PENDING && $this->user?->isPendingActivation())
            || ($this->status === self::STATUS_REVOKED && $this->user?->isCancelled());
    }

    public function canRevoke(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->user?->isPendingActivation();
    }
}
