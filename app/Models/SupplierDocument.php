<?php

namespace App\Models;

use App\Enums\SupplierDocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDocument extends Model
{
    protected $fillable = ['supplier_id', 'document_type', 'document_number', 'issued_at', 'expires_at', 'issuing_authority', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'verification_status', 'required_for_accreditation', 'blocks_procurement_when_invalid', 'uploaded_by', 'verified_by', 'verified_at', 'review_notes', 'notes', 'superseded_by_id', 'is_current'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verification_status' => SupplierDocumentStatus::class,
            'required_for_accreditation' => 'boolean',
            'blocks_procurement_when_invalid' => 'boolean',
            'verified_at' => 'datetime',
            'size_bytes' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->lt(today()) ?? false;
    }
}
