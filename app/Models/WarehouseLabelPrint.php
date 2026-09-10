<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseLabelPrint extends Model
{
    protected $fillable = ['label_number', 'target_type', 'target_id', 'template', 'payload', 'copies', 'printed_by_id', 'printed_at'];
    protected $casts = ['payload' => 'array', 'copies' => 'integer', 'printed_at' => 'datetime'];
    public function printedBy(): BelongsTo { return $this->belongsTo(User::class, 'printed_by_id'); }
}
