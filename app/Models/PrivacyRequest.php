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
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY_FOR_RELEASE = 'ready_for_release';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

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
        'approved_at',
        'approved_by_user_id',
        'target_completion_date',
        'processing_started_at',
        'fulfilled_at',
        'resolution_notes',
        'export_payload',
        'package_filename',
        'package_path',
        'package_hash',
        'package_size_bytes',
        'package_manifest',
        'package_expires_at',
        'download_count',
        'last_downloaded_at',
        'exclusions_summary',
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
            if (empty($model->target_completion_date)) {
                // 15 working days standard SLA under institutional procedures / DPA guidelines
                $model->target_completion_date = now()->addDays(21);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'export_payload' => 'array',
            'package_manifest' => 'array',
            'exclusions_summary' => 'array',
            'handled_at' => 'datetime',
            'approved_at' => 'datetime',
            'target_completion_date' => 'datetime',
            'processing_started_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'package_expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'package_size_bytes' => 'integer',
            'download_count' => 'integer',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
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
            self::TYPE_ACCESS => 'Personal Data Access & Portability',
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
            self::STATUS_PENDING, self::STATUS_SUBMITTED => ['tone' => 'warning', 'label' => 'Submitted'],
            self::STATUS_UNDER_REVIEW => ['tone' => 'primary', 'label' => 'Under Review'],
            self::STATUS_APPROVED => ['tone' => 'primary', 'label' => 'Approved'],
            self::STATUS_PROCESSING => ['tone' => 'primary', 'label' => 'Processing'],
            self::STATUS_READY_FOR_RELEASE => ['tone' => 'success', 'label' => 'Ready for Release'],
            self::STATUS_FULFILLED, self::STATUS_RELEASED => ['tone' => 'success', 'label' => 'Released'],
            self::STATUS_REJECTED => ['tone' => 'danger', 'label' => 'Refused (DPA Sec. 16)'],
            self::STATUS_CLOSED => ['tone' => 'neutral', 'label' => 'Closed'],
            self::STATUS_CANCELLED => ['tone' => 'neutral', 'label' => 'Cancelled'],
            self::STATUS_EXPIRED => ['tone' => 'neutral', 'label' => 'Expired'],
            default => ['tone' => 'neutral', 'label' => ucfirst($this->status)],
        };
    }

    public function isDownloadable(): bool
    {
        if (! in_array($this->status, [self::STATUS_FULFILLED, self::STATUS_RELEASED, self::STATUS_READY_FOR_RELEASE], true)) {
            return false;
        }

        if (empty($this->package_path)) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->package_expires_at !== null && $this->package_expires_at->isPast();
    }

    public function daysRemaining(): ?int
    {
        if ($this->target_completion_date === null) {
            return null;
        }

        return (int) now()->diffInDays($this->target_completion_date, false);
    }

    public function isOverdue(): bool
    {
        if (in_array($this->status, [self::STATUS_FULFILLED, self::STATUS_RELEASED, self::STATUS_REJECTED, self::STATUS_CLOSED], true)) {
            return false;
        }

        return $this->target_completion_date !== null && $this->target_completion_date->isPast();
    }

    public function formattedPackageSize(): ?string
    {
        if ($this->package_size_bytes === null) {
            return null;
        }

        $bytes = $this->package_size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
