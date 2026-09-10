<?php

namespace App\Services\Warehouse;

use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use DomainException;

class LocationCompatibilityService
{
    public function assertCompatible(StorageLocation $location, InventoryItem $item, int $quantity): void
    {
        if (! $location->isOperational()) {
            throw new DomainException("Location {$location->code} is not active.");
        }

        if ($location->is_quarantine || $location->is_damaged_stock || $location->is_dispatch_staging) {
            throw new DomainException("Location {$location->code} is not an eligible storage destination.");
        }

        if ($item->storage_classification && $location->storage_classification
            && $item->storage_classification !== $location->storage_classification) {
            throw new DomainException("Location {$location->code} has an incompatible storage classification.");
        }

        if ($item->temperature_classification && $location->temperature_classification
            && $item->temperature_classification !== $location->temperature_classification) {
            throw new DomainException("Location {$location->code} has an incompatible temperature classification.");
        }

        if ($location->categoryRules()->exists()
            && ! $location->categoryRules()->where('item_category_id', $item->category_id)->exists()) {
            throw new DomainException("Location {$location->code} does not allow this item category.");
        }

        if ($location->excursion_hold) {
            throw new DomainException("Location {$location->code} is under an active temperature excursion hold.");
        }

        if ($item->isDangerousDrug() && ! $location->is_narcotics_vault) {
            throw new DomainException("Dangerous Drug {$item->name} must be stored in a secured narcotics vault.");
        }

        if (! $item->isDangerousDrug() && $location->is_narcotics_vault) {
            throw new DomainException("Non-controlled item {$item->name} cannot be placed in the narcotics vault.");
        }

        if ($item->lasa_group_code) {
            $this->assertLasaIsolation($location, $item);
        }

        if ($location->capacity !== null && ($location->totalQuantity() + $quantity) > $location->capacity) {
            throw new DomainException("Location {$location->code} does not have enough available capacity.");
        }
    }

    public function assertLasaIsolation(StorageLocation $location, InventoryItem $item): void
    {
        // 1. Check if location itself holds a different item with the same LASA group
        $hasSameLocationConflict = ItemStockLevel::query()
            ->where('storage_location_id', $location->id)
            ->where('item_id', '!=', $item->id)
            ->where('quantity', '>', 0)
            ->whereHas('item', fn ($q) => $q->where('lasa_group_code', $item->lasa_group_code))
            ->with('item')
            ->first();

        if ($hasSameLocationConflict) {
            throw new DomainException("LASA Proximity Conflict: Location {$location->code} already holds {$hasSameLocationConflict->item->name} (Group: {$item->lasa_group_code}). Patient safety rules prohibit co-location.");
        }

        // 2. Check if adjacent locations in the same rack or parent zone hold a different item with the same LASA group
        if ($location->rack !== null && $location->parent_id !== null) {
            $hasRackConflict = ItemStockLevel::query()
                ->where('quantity', '>', 0)
                ->where('item_id', '!=', $item->id)
                ->whereHas('location', fn ($q) => $q->where('parent_id', $location->parent_id)->where('rack', $location->rack)->where('id', '!=', $location->id))
                ->whereHas('item', fn ($q) => $q->where('lasa_group_code', $item->lasa_group_code))
                ->with(['item', 'location'])
                ->first();

            if ($hasRackConflict) {
                throw new DomainException("LASA Proximity Conflict: Adjacent rack bay {$hasRackConflict->location->code} holds {$hasRackConflict->item->name} (Group: {$item->lasa_group_code}). Bins in the same rack bay cannot co-locate LASA medications.");
            }
        }
    }

    public function recommend(InventoryItem $item, int $quantity, ?int $excludeLocationId = null): StorageLocation
    {
        $candidates = StorageLocation::query()
            ->active()
            ->whereNotIn('type', ['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'level', 'department'])
            ->when($excludeLocationId, fn ($query) => $query->whereKeyNot($excludeLocationId))
            ->orderByDesc('is_pick_face')
            ->orderByDesc('is_reserve')
            ->orderBy('sort_sequence')
            ->get();

        foreach ($candidates as $candidate) {
            try {
                $this->assertCompatible($candidate, $item, $quantity);
                return $candidate;
            } catch (DomainException) {
                continue;
            }
        }

        throw new DomainException("No active compatible destination has capacity for {$quantity} {$item->unit} of {$item->name}.");
    }
}
