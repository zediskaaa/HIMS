<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementSavingsLog extends Model
{
    use HasFactory;

    protected $table = 'procurement_savings_logs';

    protected $fillable = [
        'kpi_process_review_id',
        'purchase_order_id',
        'purchase_order_line_id',
        'inventory_item_id',
        'pndf_code',
        'item_name',
        'uom',
        'quantity_procured',
        'actual_unit_price',
        'dpri_ceiling_price',
        'variance_amount',
        'savings_percentage',
        'is_above_ceiling',
        'justification',
    ];

    protected $casts = [
        'quantity_procured' => 'decimal:2',
        'actual_unit_price' => 'decimal:4',
        'dpri_ceiling_price' => 'decimal:4',
        'variance_amount' => 'decimal:4',
        'savings_percentage' => 'decimal:2',
        'is_above_ceiling' => 'boolean',
    ];

    public function processReview(): BelongsTo
    {
        return $this->belongsTo(KpiProcessReview::class, 'kpi_process_review_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
