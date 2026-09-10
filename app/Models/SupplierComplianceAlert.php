<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierComplianceAlert extends Model
{
    protected $fillable = [
        'supplier_id',
        'supplier_document_id',
        'supplier_contract_id',
        'source_key',
        'type',
        'severity',
        'status',
        'due_date',
        'message',
        'first_detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'due_date' => 'date',
            'first_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SupplierDocument::class, 'supplier_document_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SupplierContract::class, 'supplier_contract_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value]);
    }
}
