<?php

namespace App\Services;

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\StockAlert;
use Illuminate\Support\Facades\DB;

/**
 * SWS: raises and clears the alerts shown on the warehousing dashboard.
 *
 * The sweep is idempotent — running it twice does not produce duplicate
 * alerts, and an alert whose condition has cleared is resolved rather than
 * deleted so the history stays auditable.
 */
class StockAlertService
{
    public function __construct(private readonly HimsNotificationService $notifications) {}

    /**
     * Re-evaluate a single item's stock alerts.
     *
     * The daily sweep used to be the only thing that wrote to `stock_alerts`,
     * so an item that dropped below its reorder level raised nothing until
     * 01:00 the next morning, and a replenished item kept its alert open just
     * as long. This runs on every stock change instead, which is what keeps
     * the dashboard honest between sweeps.
     *
     * @return array{raised: int, resolved: int}
     */
    public function syncForItem(InventoryItem $item): array
    {
        $raised = $this->evaluateStockLevel($item);
        $resolved = $this->resolveClearedStockAlerts($item);

        return ['raised' => $raised, 'resolved' => $resolved];
    }

    /**
     * Run every check and return how many alerts were raised and resolved.
     *
     * @return array{raised: int, resolved: int}
     */
    public function sweep(): array
    {
        return DB::transaction(function (): array {
            $raised = 0;
            $resolved = 0;

            foreach (InventoryItem::with('batches')->get() as $item) {
                $raised += $this->evaluateStockLevel($item);
                $resolved += $this->resolveClearedStockAlerts($item);
            }

            foreach (ItemBatch::with('item')->active()->whereNotNull('expiry_date')->get() as $batch) {
                $raised += $this->evaluateBatchExpiry($batch);
            }

            $resolved += $this->resolveClearedExpiryAlerts();

            return ['raised' => $raised, 'resolved' => $resolved];
        });
    }

    /**
     * Raise a low-stock or out-of-stock alert if one is not already live.
     */
    public function evaluateStockLevel(InventoryItem $item): int
    {
        $type = match (true) {
            $item->isOutOfStock() => AlertType::OutOfStock,
            $item->isLowStock() => AlertType::LowStock,
            default => null,
        };

        if ($type === null) {
            return 0;
        }

        $message = $type === AlertType::OutOfStock
            ? sprintf('%s (%s) is out of stock.', $item->name, $item->sku)
            : sprintf(
                '%s (%s) is at %d, at or below its reorder level of %d.',
                $item->name,
                $item->sku,
                $item->quantity_on_hand,
                $item->reorder_level
            );

        return $this->raise($type, [
            'item_id' => $item->id,
            'threshold_value' => $item->reorder_level,
            'current_value' => $item->quantity_on_hand,
            'message' => $message,
        ]);
    }

    /**
     * Raise an expired or expiring-soon alert for a batch.
     */
    public function evaluateBatchExpiry(ItemBatch $batch): int
    {
        if ($batch->quantityOnHand() <= 0) {
            return 0;
        }

        $type = match (true) {
            $batch->isExpired() => AlertType::Expired,
            $batch->isExpiringSoon() => AlertType::ExpiringSoon,
            default => null,
        };

        if ($type === null) {
            return 0;
        }

        $itemName = $batch->item?->name ?? 'Item';
        $message = $type === AlertType::Expired
            ? sprintf('Batch %s of %s expired on %s.', $batch->batch_number, $itemName, $batch->expiry_date->toFormattedDateString())
            : sprintf(
                'Batch %s of %s expires on %s (%d days).',
                $batch->batch_number,
                $itemName,
                $batch->expiry_date->toFormattedDateString(),
                $batch->daysUntilExpiry()
            );

        return $this->raise($type, [
            'item_id' => $batch->item_id,
            'item_batch_id' => $batch->id,
            'current_value' => $batch->quantityOnHand(),
            'message' => $message,
        ]);
    }

    /**
     * Acknowledge an alert on behalf of a user.
     */
    public function acknowledge(StockAlert $alert, ?int $userId = null): StockAlert
    {
        $alert->acknowledge($userId);

        return $alert;
    }

    /**
     * Create the alert unless an identical one is already live.
     *
     * When one is already live its stored numbers are refreshed rather than
     * left alone: `current_value` is a snapshot taken when the alert was
     * raised, and the dashboard reads it, so an un-refreshed row keeps
     * reporting the quantity from whenever the condition first tripped.
     *
     * @param  array<string, mixed>  $attributes
     * @return int 1 when an alert was created, 0 when one already existed
     */
    private function raise(AlertType $type, array $attributes): int
    {
        $existing = StockAlert::query()
            ->active()
            ->ofType($type)
            ->where('item_id', $attributes['item_id'])
            ->where('item_batch_id', $attributes['item_batch_id'] ?? null)
            ->first();

        if ($existing !== null) {
            $existing->fill(array_intersect_key($attributes, array_flip([
                'current_value',
                'threshold_value',
                'message',
            ])))->save();

            if ($existing->status === AlertStatus::Open) {
                $this->notifyAlert($existing);
            }

            return 0;
        }

        $alert = StockAlert::create(array_merge($attributes, [
            'type' => $type,
            'severity' => $type->defaultSeverity(),
            'status' => AlertStatus::Open,
        ]));

        $this->notifyAlert($alert);

        return 1;
    }

    private function notifyAlert(StockAlert $alert): void
    {
        $this->notifications->sendToPermission(
            Permission::AcknowledgeAlerts,
            "stock-alert:{$alert->id}",
            $alert->type->label(),
            (string) $alert->message,
            match ($alert->type) {
                AlertType::OutOfStock, AlertType::Expired => NotificationPriority::Critical,
                default => NotificationPriority::Warning,
            },
            NotificationDestination::InventoryAlerts,
        );
    }

    /**
     * Close low/out-of-stock alerts for an item that has recovered.
     */
    private function resolveClearedStockAlerts(InventoryItem $item): int
    {
        $stillLow = $item->isLowStock() || $item->isOutOfStock();

        if ($stillLow) {
            // An item that recovered from out-of-stock to merely low should
            // not keep its out-of-stock alert open.
            $obsolete = $item->isOutOfStock() ? AlertType::LowStock : AlertType::OutOfStock;

            return $this->resolveWhere(
                StockAlert::query()->active()->ofType($obsolete)->where('item_id', $item->id)
            );
        }

        return $this->resolveWhere(
            StockAlert::query()
                ->active()
                ->where('item_id', $item->id)
                ->whereIn('type', [AlertType::LowStock->value, AlertType::OutOfStock->value])
        );
    }

    /**
     * Close expiry alerts whose batch has been depleted or disposed of.
     */
    private function resolveClearedExpiryAlerts(): int
    {
        $alerts = StockAlert::query()
            ->active()
            ->whereIn('type', [AlertType::ExpiringSoon->value, AlertType::Expired->value])
            ->whereNotNull('item_batch_id')
            ->with('batch')
            ->get();

        $resolved = 0;

        foreach ($alerts as $alert) {
            $batch = $alert->batch;

            if ($batch === null || $batch->quantityOnHand() <= 0) {
                $alert->resolve();
                $resolved++;

                continue;
            }

            // A batch that has tipped from "expiring soon" to "expired"
            // gets a fresh critical alert instead of a stale warning.
            if ($alert->type === AlertType::ExpiringSoon && $batch->isExpired()) {
                $alert->resolve();
                $resolved++;
            }
        }

        return $resolved;
    }

    private function resolveWhere($query): int
    {
        $count = 0;

        foreach ($query->get() as $alert) {
            $alert->resolve();
            $count++;
        }

        return $count;
    }
}
