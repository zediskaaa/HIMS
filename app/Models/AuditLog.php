<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_employee_id',
        'action',
        'target_type',
        'target_id',
        'target_name',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
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
