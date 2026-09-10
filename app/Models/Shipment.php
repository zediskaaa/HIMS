<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shipment extends Model
{
    use HasFactory;

    protected $table = 'shipments';

    protected $fillable = [
        'shipment_number',
        'purchase_order_id',
        'supplier_id',
        'carrier_name',
        'tracking_number',
        'waybill_number',
        'vehicle_plate_number',
        'driver_name',
        'driver_contact',
        'sscc',
        'origin_address',
        'destination_facility',
        'dispatch_date',
        'estimated_delivery_date',
        'actual_delivery_date',
        'status',
        'is_cold_chain',
        'temp_min',
        'temp_max',
        'temp_logger_serial',
        'temp_excursion',
        'notes',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'is_cold_chain' => 'boolean',
        'temp_excursion' => 'boolean',
        'temp_min' => 'decimal:2',
        'temp_max' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LogisticsDocument::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function custodyLogs(): MorphMany
    {
        return $this->morphMany(ChainOfCustodyLog::class, 'trackable');
    }

    public function isDelivered(): bool
    {
        return in_array($this->status, ['arrived_at_dock', 'received'], true);
    }

    public function isDelayed(): bool
    {
        if ($this->isDelivered()) {
            return ! empty($this->actual_delivery_date) && ! empty($this->estimated_delivery_date)
                && Carbon::parse($this->actual_delivery_date)->greaterThan($this->estimated_delivery_date);
        }

        return ! empty($this->estimated_delivery_date) && now()->startOfDay()->greaterThan($this->estimated_delivery_date);
    }

    public function calculateDaysDelayed(): int
    {
        if (empty($this->estimated_delivery_date)) {
            return 0;
        }

        $endDate = $this->actual_delivery_date ? Carbon::parse($this->actual_delivery_date)->startOfDay() : now()->startOfDay();
        $expected = Carbon::parse($this->estimated_delivery_date)->startOfDay();

        return max(0, $expected->diffInDays($endDate, false));
    }
}
