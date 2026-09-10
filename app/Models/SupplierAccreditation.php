<?php

namespace App\Models;

use App\Enums\SupplierAccreditationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAccreditation extends Model
{
    protected $fillable = ['supplier_id', 'cycle_number', 'status', 'submitted_by', 'submitted_at', 'decided_by', 'decided_at', 'valid_from', 'expires_at', 'decision_notes'];

    protected function casts(): array
    {
        return ['status' => SupplierAccreditationStatus::class, 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'valid_from' => 'date', 'expires_at' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
