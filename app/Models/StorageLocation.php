<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'barcode_value',
        'parent_id',
        'type',
        'description',
        'zone',
        'capacity',
        'storage_classification',
        'temperature_classification',
        'capacity_unit',
        'is_receiving_staging',
        'is_quarantine',
        'is_pick_face',
        'is_reserve',
        'is_dispatch_staging',
        'is_returns_area',
        'is_damaged_stock',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'is_narcotics_vault',
        'is_hazardous_containment',
        'max_weight_kg',
        'is_frozen_for_count',
        'excursion_hold',
        'sort_sequence',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'max_weight_kg' => 'decimal:2',
        'is_receiving_staging' => 'boolean',
        'is_quarantine' => 'boolean',
        'is_pick_face' => 'boolean',
        'is_reserve' => 'boolean',
        'is_dispatch_staging' => 'boolean',
        'is_returns_area' => 'boolean',
        'is_damaged_stock' => 'boolean',
        'is_narcotics_vault' => 'boolean',
        'is_hazardous_containment' => 'boolean',
        'is_frozen_for_count' => 'boolean',
        'excursion_hold' => 'boolean',
        'sort_sequence' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(ItemStockLevel::class, 'storage_location_id');
    }

    public function categoryRules(): HasMany
    {
        return $this->hasMany(StorageLocationCategoryRule::class);
    }

    public function telemetryLogs(): HasMany
    {
        return $this->hasMany(IoTTelemetryLog::class, 'storage_location_id');
    }

    public function dangerousDrugsEntries(): HasMany
    {
        return $this->hasMany(PdeaDangerousDrugsRegister::class, 'storage_location_id');
    }

    public function isOperational(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Full location path, e.g. "Zone A / Aisle 3 / Rack 2 / Bin 04".
     */
    public function fullPath(): string
    {
        $segments = [$this->name];
        $node = $this->parent;
        $visited = [$this->getKey() => true];

        while ($node !== null) {
            if (isset($visited[$node->getKey()])) {
                $segments[] = '[invalid cycle]';
                break;
            }
            $visited[$node->getKey()] = true;
            array_unshift($segments, $node->name);
            $node = $node->parent;
        }

        return implode(' / ', $segments);
    }

    /**
     * Total units held directly in this location.
     */
    public function totalQuantity(): int
    {
        return (int) $this->stockLevels()->sum('quantity');
    }

    /**
     * Percentage of capacity used, or null when no capacity is configured.
     */
    public function utilisation(): ?float
    {
        if (! $this->capacity) {
            return null;
        }

        return round(($this->totalQuantity() / $this->capacity) * 100, 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}
