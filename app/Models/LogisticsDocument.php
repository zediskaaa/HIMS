<?php

namespace App\Models;

use App\Enums\DocumentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LogisticsDocument extends Model
{
    use HasFactory;

    protected $table = 'logistics_documents';

    protected $fillable = [
        'tracking_number',
        'document_type',
        'title',
        'reference_number',
        'supplier_id',
        'purchase_order_id',
        'goods_receipt_note_id',
        'inspection_acceptance_report_id',
        'material_requisition_id',
        'status',
        'file_path',
        'file_name',
        'original_name',
        'mime_type',
        'file_size_bytes',
        'disk',
        'sha256_checksum',
        'issued_at',
        'received_at',
        'verified_at',
        'retention_class',
        'retention_until',
        'uploaded_by_id',
        'verified_by_id',
        'version_number',
        'replaces_document_id',
        'superseded_by_id',
        'revision_reason',
        'verification_notes',
        'notes',
    ];

    protected $casts = [
        'document_type' => DocumentType::class,
        'file_size_bytes' => 'integer',
        'version_number' => 'integer',
        'issued_at' => 'date',
        'received_at' => 'date',
        'verified_at' => 'datetime',
        'retention_until' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function inspectionAcceptanceReport(): BelongsTo
    {
        return $this->belongsTo(InspectionAcceptanceReport::class, 'inspection_acceptance_report_id');
    }

    public function materialRequisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function replacesDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_document_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_document_id');
    }

    public function custodyLogs(): MorphMany
    {
        return $this->morphMany(ChainOfCustodyLog::class, 'trackable');
    }

    public function isVerified(): bool
    {
        return in_array($this->status, ['verified', 'accepted'], true);
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function hasFile(): bool
    {
        return ! empty($this->file_path);
    }

    /**
     * Determine default retention expiration based on National Archives of the Philippines (NAP) GRDS.
     */
    public static function defaultRetentionDate(string $retentionClass): Carbon
    {
        return match ($retentionClass) {
            'operational_2yr' => now()->addYears(2),
            'tax_invoice_5yr' => now()->addYears(5),
            'financial_10yr' => now()->addYears(10),
            default => now()->addYears(25), // Permanent archive default
        };
    }
}
