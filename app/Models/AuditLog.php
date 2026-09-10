<?php

namespace App\Models;

use App\Enums\AuditAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'actor_name',
        'actor_employee_id',
        'actor_role',
        'action',
        'event_category',
        'module',
        'target_type',
        'target_id',
        'target_name',
        'target_reference',
        'description',
        'business_reason',
        'outcome',
        'source',
        'correlation_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'occurred_at_utc',
        'display_timezone',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function authoritativeTimestamp(): CarbonImmutable
    {
        if ($this->occurred_at_utc) {
            return CarbonImmutable::parse($this->occurred_at_utc, 'UTC');
        }

        return CarbonImmutable::instance($this->created_at)
            ->setTimezone(config('app.timezone'))
            ->utc();
    }

    public function displayTimestamp(): CarbonImmutable
    {
        return $this->authoritativeTimestamp()->setTimezone(
            $this->display_timezone ?: config('app.timezone'),
        );
    }

    public function displayTimezoneLabel(): string
    {
        $timezone = $this->display_timezone ?: config('app.timezone');

        return $timezone === 'Asia/Manila'
            ? 'PHT (UTC+8)'
            : $this->displayTimestamp()->format('T (P)');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    /**
     * The actor relation may become null after that user is deleted; snapshot
     * columns keep the identity readable permanently.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
