<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every change to stock quantities.
 *
 * Balances live in `item_stock_levels`, keyed by item + location + batch.
 * `inventory_items.quantity_on_hand` / `total_value` / `status` are caches
 * recomputed from those rows after each movement, so nothing else in the
 * app should write to them directly.
 */
class InventoryAutomationService
{
    public function __construct(private readonly StockAlertService $alerts) {}

    /**
     * Record a stock movement and apply it to the affected balances.
     *
     * A single logical movement can span several batches (a FEFO issue that
     * drains one batch and continues into the next), so this returns every
     * StockMovement row it wrote.
     *
     * @param  array<string, mixed>  $validated
     * @return Collection<int, StockMovement>
     */
    public function recordMovement(array $validated, ?int $userId = null, ?Model $reference = null): Collection
    {
        return DB::transaction(function () use ($validated, $userId, $reference): Collection {
            $item = InventoryItem::lockForUpdate()->findOrFail($validated['item_id']);
            $type = $validated['movement_type'] instanceof MovementType
                ? $validated['movement_type']
                : MovementType::from($validated['movement_type']);

            $quantity = (int) $validated['quantity'];
            $fromLocationId = $validated['from_location_id'] ?? null;
            $toLocationId = $validated['to_location_id'] ?? null;
            $batchId = $validated['item_batch_id'] ?? null;

            $this->assertLocationsPresent($type, $fromLocationId, $toLocationId);

            if ($type->isAdjustment()) {
                $movements = $this->applyAdjustment($item, $validated, $userId, $reference);
            } elseif ($type->decrementsSource()) {
                $movements = $this->applyOutbound($item, $type, $quantity, (int) $fromLocationId, $toLocationId, $batchId, $validated, $userId, $reference);
            } else {
                $movements = $this->applyInbound($item, $type, $quantity, (int) $toLocationId, $batchId, $validated, $userId, $reference);
            }

            $this->syncItemTotals($item);

            return new Collection($movements);
        });
    }

    /**
     * Recompute the cached rollups on an item from its stock levels.
     *
     * Alerts are re-evaluated here rather than in recordMovement() so that
     * every writer of stock — movements, adjustments, goods receipts, the
     * nightly sweep — gets correct alerts without having to remember to ask
     * for them. Both run inside the caller's transaction, so the balance and
     * the alert that describes it can never be committed out of step.
     */
    public function syncItemTotals(InventoryItem $item): InventoryItem
    {
        $totals = $item->stockLevels()
            ->selectRaw('coalesce(sum(quantity), 0) as qty, coalesce(sum(reserved_quantity), 0) as reserved')
            ->first();

        $item->quantity_on_hand = (int) ($totals->qty ?? 0);
        $item->reserved_quantity = (int) ($totals->reserved ?? 0);
        $item->total_value = round($item->quantity_on_hand * (float) ($item->unit_cost ?? 0), 2);
        $item->status = $this->resolveStatus($item);
        $item->save();

        $this->alerts->syncForItem($item);

        return $item;
    }

    /**
     * Find or create the batch a receipt should land in.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateBatch(InventoryItem $item, string $batchNumber, array $attributes = []): ItemBatch
    {
        return ItemBatch::firstOrCreate(
            ['item_id' => $item->id, 'batch_number' => $batchNumber],
            array_merge([
                'received_at' => now()->toDateString(),
                'unit_cost' => $item->unit_cost,
                'status' => 'active',
            ], $attributes)
        );
    }

    /**
     * Quantity of an item available at a location, optionally for one batch.
     */
    public function availableAt(int $itemId, int $locationId, ?int $batchId = null): int
    {
        return (int) ItemStockLevel::query()
            ->forItem($itemId)
            ->atLocation($locationId)
            ->when($batchId !== null, fn ($q) => $q->where('item_batch_id', $batchId))
            ->sum('quantity');
    }

    /**
     * Split a requested quantity across batches at a location, earliest
     * expiry first. Batches without an expiry date are consumed last.
     *
     * @return array<int, array{batch_id: int|null, quantity: int}>
     */
    public function allocateFefo(int $itemId, int $locationId, int $quantity, ?int $batchId = null): array
    {
        $levels = ItemStockLevel::query()
            ->where('item_stock_levels.item_id', $itemId)
            ->where('item_stock_levels.storage_location_id', $locationId)
            ->where('item_stock_levels.quantity', '>', 0)
            ->when($batchId !== null, fn ($q) => $q->where('item_stock_levels.item_batch_id', $batchId))
            ->leftJoin('item_batches', 'item_stock_levels.item_batch_id', '=', 'item_batches.id')
            ->orderByRaw('item_batches.expiry_date is null')
            ->orderBy('item_batches.expiry_date')
            ->orderBy('item_stock_levels.id')
            ->select('item_stock_levels.*')
            ->get();

        $allocation = [];
        $remaining = $quantity;

        foreach ($levels as $level) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $level->quantity);
            $allocation[] = ['batch_id' => $level->item_batch_id, 'quantity' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient stock at the selected location. Short by '.$remaining.'.'],
            ]);
        }

        return $allocation;
    }

    /**
     * Apply a signed delta to one balance row, creating it when needed.
     */
    public function adjustStockLevel(int $itemId, int $locationId, ?int $batchId, int $delta): ItemStockLevel
    {
        $level = ItemStockLevel::firstOrCreate(
            [
                'item_id' => $itemId,
                'storage_location_id' => $locationId,
                'item_batch_id' => $batchId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );

        $level->quantity = max(0, (int) $level->quantity + $delta);
        $level->save();

        return $level;
    }

    public function adjustQuarantinedStock(int $itemId, int $locationId, ?int $batchId, int $delta): ItemStockLevel
    {
        $level = ItemStockLevel::firstOrCreate(
            [
                'item_id' => $itemId,
                'storage_location_id' => $locationId,
                'item_batch_id' => $batchId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0, 'quarantined_quantity' => 0, 'blocked_quantity' => 0, 'in_transit_quantity' => 0]
        );

        $level->quarantined_quantity = max(0, (int) $level->quarantined_quantity + $delta);
        $level->save();

        return $level;
    }

    public function adjustBlockedStock(int $itemId, int $locationId, ?int $batchId, int $delta): ItemStockLevel
    {
        $level = ItemStockLevel::firstOrCreate(
            [
                'item_id' => $itemId,
                'storage_location_id' => $locationId,
                'item_batch_id' => $batchId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0, 'quarantined_quantity' => 0, 'blocked_quantity' => 0, 'in_transit_quantity' => 0]
        );

        $level->blocked_quantity = max(0, (int) $level->blocked_quantity + $delta);
        $level->save();

        return $level;
    }

    public function adjustInTransitStock(int $itemId, int $locationId, ?int $batchId, int $delta): ItemStockLevel
    {
        $level = ItemStockLevel::firstOrCreate(
            [
                'item_id' => $itemId,
                'storage_location_id' => $locationId,
                'item_batch_id' => $batchId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0, 'quarantined_quantity' => 0, 'blocked_quantity' => 0, 'in_transit_quantity' => 0]
        );

        $level->in_transit_quantity = max(0, (int) $level->in_transit_quantity + $delta);
        $level->save();

        return $level;
    }

    public function reserveStock(int $itemId, int $locationId, ?int $batchId, int $quantity): ItemStockLevel
    {
        $level = ItemStockLevel::firstOrCreate(
            [
                'item_id' => $itemId,
                'storage_location_id' => $locationId,
                'item_batch_id' => $batchId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );

        $available = $batchId !== null
            ? $level->availableQuantity()
            : (int) ItemStockLevel::where('item_id', $itemId)
                ->selectRaw('coalesce(sum(quantity - reserved_quantity), 0) as avail')
                ->value('avail');

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient available stock to place reservation. Available: '.$available.', Requested: '.$quantity],
            ]);
        }

        $level->reserved_quantity = (int) $level->reserved_quantity + $quantity;
        $level->save();

        $item = InventoryItem::lockForUpdate()->findOrFail($itemId);
        $this->syncItemTotals($item);

        return $level;
    }

    public function releaseReservation(int $itemId, int $locationId, ?int $batchId, int $quantity): ItemStockLevel
    {
        $level = ItemStockLevel::firstOrCreate(
            [
                'item_id' => $itemId,
                'storage_location_id' => $locationId,
                'item_batch_id' => $batchId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );

        $level->reserved_quantity = max(0, (int) $level->reserved_quantity - $quantity);
        $level->save();

        $item = InventoryItem::lockForUpdate()->findOrFail($itemId);
        $this->syncItemTotals($item);

        return $level;
    }

    private function resolveStatus(InventoryItem $item): string
    {
        if ((int) $item->quantity_on_hand <= 0) {
            return 'out_of_stock';
        }

        if ((int) $item->reorder_level > 0 && (int) $item->quantity_on_hand <= (int) $item->reorder_level) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    private function assertLocationsPresent(MovementType $type, ?int $from, ?int $to): void
    {
        if ($type->requiresSourceLocation() && $from === null) {
            throw ValidationException::withMessages([
                'from_location_id' => ['A source location is required for a '.$type->label().'.'],
            ]);
        }

        if ($type->requiresDestinationLocation() && $to === null) {
            throw ValidationException::withMessages([
                'to_location_id' => ['A destination location is required for a '.$type->label().'.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, StockMovement>
     */
    private function applyInbound(
        InventoryItem $item,
        MovementType $type,
        int $quantity,
        int $toLocationId,
        ?int $batchId,
        array $validated,
        ?int $userId,
        ?Model $reference
    ): array {
        $this->adjustStockLevel($item->id, $toLocationId, $batchId, $quantity);

        return [$this->writeMovement($item, $type, $quantity, $batchId, null, $toLocationId, $validated, $userId, $reference)];
    }

    /**
     * Outbound covers stock_out, disposal and the source half of a transfer.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, StockMovement>
     */
    private function applyOutbound(
        InventoryItem $item,
        MovementType $type,
        int $quantity,
        int $fromLocationId,
        ?int $toLocationId,
        ?int $batchId,
        array $validated,
        ?int $userId,
        ?Model $reference
    ): array {
        $allocation = $this->allocateFefo($item->id, $fromLocationId, $quantity, $batchId);
        $movements = [];

        foreach ($allocation as $slice) {
            $this->adjustStockLevel($item->id, $fromLocationId, $slice['batch_id'], -$slice['quantity']);

            // A transfer puts the same batch down again at the destination.
            if ($type->incrementsDestination() && $toLocationId !== null) {
                $this->adjustStockLevel($item->id, (int) $toLocationId, $slice['batch_id'], $slice['quantity']);
            }

            $movements[] = $this->writeMovement(
                $item,
                $type,
                $slice['quantity'],
                $slice['batch_id'],
                $fromLocationId,
                $toLocationId,
                $validated,
                $userId,
                $reference
            );
        }

        return $movements;
    }

    /**
     * Adjustments carry a signed quantity and hit one location directly,
     * bypassing FEFO — they exist to correct counts, not to move goods.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, StockMovement>
     */
    private function applyAdjustment(InventoryItem $item, array $validated, ?int $userId, ?Model $reference): array
    {
        $delta = (int) $validated['quantity'];
        $locationId = $validated['to_location_id'] ?? $validated['from_location_id'] ?? $item->default_location_id;

        if ($locationId === null) {
            throw ValidationException::withMessages([
                'to_location_id' => ['An adjustment needs a storage location.'],
            ]);
        }

        $batchId = $validated['item_batch_id'] ?? null;

        if ($delta < 0 && $this->availableAt($item->id, (int) $locationId, $batchId) < abs($delta)) {
            throw ValidationException::withMessages([
                'quantity' => ['Cannot reduce below zero at the selected location.'],
            ]);
        }

        $this->adjustStockLevel($item->id, (int) $locationId, $batchId, $delta);

        return [$this->writeMovement(
            $item,
            MovementType::Adjustment,
            $delta,
            $batchId,
            $delta < 0 ? (int) $locationId : null,
            $delta > 0 ? (int) $locationId : null,
            $validated,
            $userId,
            $reference
        )];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function writeMovement(
        InventoryItem $item,
        MovementType $type,
        int $quantity,
        ?int $batchId,
        ?int $fromLocationId,
        ?int $toLocationId,
        array $validated,
        ?int $userId,
        ?Model $reference
    ): StockMovement {
        return StockMovement::create([
            'item_id' => $item->id,
            'item_batch_id' => $batchId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $validated['unit_cost'] ?? $item->unit_cost,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'remarks' => $validated['remarks'] ?? null,
            'moved_at' => now(),
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
