<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'batch_number',
        'lot_number',
        'manufactured_date',
        'expiry_date',
        'received_at',
        'unit_cost',
        'initial_quantity',
        'status',
        'notes',
    ];

    protected $casts = [
        'manufactured_date' => 'date',
        'expiry_date' => 'date',
        'received_at' => 'date',
        'unit_cost' => 'decimal:2',
        'initial_quantity' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(ItemStockLevel::class, 'item_batch_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_batch_id');
    }

    /**
     * Quantity of this batch still on hand across every location.
     */
    public function quantityOnHand(): int
    {
        return (int) $this->stockLevels()->sum('quantity');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /**
     * Whether the batch falls inside its item's expiry warning window.
     */
    public function isExpiringSoon(?int $withinDays = null): bool
    {
        if ($this->expiry_date === null || $this->isExpired()) {
            return false;
        }

        $withinDays ??= $this->item?->expiry_alert_days ?? 30;

        return $this->expiry_date->lessThanOrEqualTo(now()->addDays($withinDays));
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * First-Expired-First-Out ordering. Batches with earliest expiry sort first;
     * ties and non-dated stock fall back to chronological FIFO order.
     */
    public function scopeFefo($query)
    {
        return $query->orderByRaw('expiry_date IS NULL ASC')
            ->orderBy('expiry_date', 'asc')
            ->orderByRaw('COALESCE(received_at, created_at) ASC')
            ->orderBy('id', 'asc');
    }

    /**
     * First-In-First-Out ordering. Oldest received stock is consumed first.
     */
    public function scopeFifo($query)
    {
        return $query->orderByRaw('COALESCE(received_at, created_at) ASC')->orderBy('id', 'asc');
    }

    public function scopeExpiringBefore($query, $date)
    {
        return $query->whereNotNull('expiry_date')->where('expiry_date', '<=', $date);
    }
}
