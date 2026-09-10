<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryShrinkageReport extends Model
{
    use HasFactory;

    protected $table = 'inventory_shrinkage_reports';

    protected $fillable = [
        'kpi_process_review_id',
        'cycle_count_doc_id',
        'storage_location_id',
        'inventory_item_id',
        'ledger_book_quantity',
        'physical_counted_quantity',
        'shrinkage_quantity',
        'shrinkage_rate_pct',
        'unit_cost',
        'total_loss_value',
        'shrinkage_reason',
        'requires_admin_escalation',
        'notes',
    ];

    protected $casts = [
        'ledger_book_quantity' => 'decimal:2',
        'physical_counted_quantity' => 'decimal:2',
        'shrinkage_quantity' => 'decimal:2',
        'shrinkage_rate_pct' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_loss_value' => 'decimal:4',
        'requires_admin_escalation' => 'boolean',
    ];

    public function processReview(): BelongsTo
    {
        return $this->belongsTo(KpiProcessReview::class, 'kpi_process_review_id');
    }

    public function cycleCountDoc(): BelongsTo
    {
        return $this->belongsTo(CycleCountDoc::class, 'cycle_count_doc_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
