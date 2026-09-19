<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyRequest extends Model
{
    use HasFactory;

    public const TYPE_ACCESS = 'access';
    public const TYPE_RECTIFICATION = 'rectification';
    public const TYPE_ERASURE_REVIEW = 'erasure_review';
    public const TYPE_INQUIRY = 'inquiry';
    public const TYPE_OBJECTION = 'objection';

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'privacy_requests';

    protected $fillable = [
        'ticket_number',
        'user_id',
        'requestor_name',
        'requestor_email',
        'request_type',
        'details',
        'status',
        'handled_by_user_id',
        'handled_at',
        'resolution_notes',
        'export_payload',
    ];

    protected static function booted(): void
    {
        static::creating(function (PrivacyRequest $model) {
            if (empty($model->ticket_number)) {
                $model->ticket_number = static::generateTicketNumber();
            }
            if (empty($model->requestor_name) && $model->user_id) {
                $user = $model->user ?? User::find($model->user_id);
                $model->requestor_name = $user?->name ?? 'System User';
                $model->requestor_email = $user?->email ?? '';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'export_payload' => 'array',
            'handled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->typeLabel();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusBadge()['label'];
    }

    public function getResolvedByAttribute(): ?int
    {
        return $this->handled_by_user_id;
    }

    public function getResolvedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->handled_at;
    }

    public static function generateTicketNumber(): string
    {
        return 'DSR-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function typeLabel(): string
    {
        return match ($this->request_type) {
            self::TYPE_ACCESS => 'Personal Data Access / Export',
            self::TYPE_RECTIFICATION => 'Correction of Inaccurate Data',
            self::TYPE_ERASURE_REVIEW => 'Erasure / Disposal Review',
            self::TYPE_INQUIRY => 'Privacy Policy Inquiry',
            self::TYPE_OBJECTION => 'Objection to Processing',
            default => ucfirst(str_replace('_', ' ', $this->request_type)),
        };
    }

    public function statusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING => ['tone' => 'warning', 'label' => 'Pending Review'],
            self::STATUS_UNDER_REVIEW => ['tone' => 'primary', 'label' => 'Under Investigation'],
            self::STATUS_APPROVED => ['tone' => 'primary', 'label' => 'Approved'],
            self::STATUS_FULFILLED => ['tone' => 'success', 'label' => 'Fulfilled'],
            self::STATUS_REJECTED => ['tone' => 'danger', 'label' => 'Legally Denied'],
            self::STATUS_CLOSED => ['tone' => 'neutral', 'label' => 'Closed'],
            default => ['tone' => 'neutral', 'label' => ucfirst($this->status)],
        };
    }
}
