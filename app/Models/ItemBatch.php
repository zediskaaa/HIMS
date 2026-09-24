<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ItemBatch extends Model
{
    use HasFactory;

    public const EXPIRING_SOON_DAYS = 90;

    public const CRITICAL_EXPIRY_DAYS = 30;

    public const EXPIRY_NORMAL = 'normal';

    public const EXPIRY_SOON = 'expiring_soon';

    public const EXPIRY_CRITICAL = 'critical';

    public const EXPIRY_EXPIRED = 'expired';

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
        return $this->expiryClassification() === self::EXPIRY_EXPIRED;
    }

    public function isExpiringSoon(): bool
    {
        return in_array($this->expiryClassification(), [self::EXPIRY_SOON, self::EXPIRY_CRITICAL], true);
    }

    public function isCriticalExpiry(): bool
    {
        return $this->expiryClassification() === self::EXPIRY_CRITICAL;
    }

    public function daysUntilExpiry(): ?int
    {
        return static::daysUntil($this->expiry_date);
    }

    public function expiryClassification(): ?string
    {
        return static::classifyExpiryDate($this->expiry_date);
    }

    public function expiryStatusLabel(): string
    {
        return match ($this->expiryClassification()) {
            self::EXPIRY_NORMAL => 'Normal',
            self::EXPIRY_SOON => 'Expiring Soon',
            self::EXPIRY_CRITICAL => 'Critical / Near Expiry',
            self::EXPIRY_EXPIRED => 'Expired',
            default => 'No Expiration Date',
        };
    }

    public static function classifyExpiryDate(CarbonInterface|string|null $expiryDate): ?string
    {
        $days = static::daysUntil($expiryDate);

        if ($days === null) {
            return null;
        }

        return match (true) {
            $days <= 0 => self::EXPIRY_EXPIRED,
            $days <= self::CRITICAL_EXPIRY_DAYS => self::EXPIRY_CRITICAL,
            $days <= self::EXPIRING_SOON_DAYS => self::EXPIRY_SOON,
            default => self::EXPIRY_NORMAL,
        };
    }

    public static function daysUntil(CarbonInterface|string|null $expiryDate): ?int
    {
        if ($expiryDate === null || $expiryDate === '') {
            return null;
        }

        return (int) today()->diffInDays(Carbon::parse($expiryDate)->startOfDay(), false);
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

    public function scopeExpiringSoon($query, int $withinDays = self::EXPIRING_SOON_DAYS)
    {
        $withinDays = max(1, min(self::EXPIRING_SOON_DAYS, $withinDays));

        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', today())
            ->whereDate('expiry_date', '<=', today()->addDays($withinDays));
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today());
    }
}
