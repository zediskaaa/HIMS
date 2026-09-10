<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierContract extends Model
{
    protected $fillable = ['supplier_id', 'contract_number', 'contract_type', 'starts_at', 'ends_at', 'status', 'payment_terms', 'delivery_terms', 'responsible_user_id', 'notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SupplierPrice::class);
    }

    public function effectiveStatus(): string
    {
        if ($this->status !== 'active') {
            return $this->status;
        }

        if ($this->starts_at->gt(today())) {
            return 'scheduled';
        }

        return $this->ends_at?->lt(today()) ? 'expired' : 'active';
    }
}
