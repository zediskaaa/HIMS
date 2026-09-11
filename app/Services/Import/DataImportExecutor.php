<?php

namespace App\Services\Import;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

class DataImportExecutor
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Execute transactional import of validated records.
     *
     * @param  string  $target  'items' | 'locations' | 'suppliers'
     * @param  array<int, array<string, mixed>>  $records
     * @return array{created: int, updated: int, total: int}
     */
    public function execute(string $target, array $records, User $user): array
    {
        return DB::transaction(function () use ($target, $records, $user) {
            return match ($target) {
                'items' => $this->executeItems($records, $user),
                'locations' => $this->executeLocations($records, $user),
                'suppliers' => $this->executeSuppliers($records, $user),
                default => throw new \InvalidArgumentException("Unsupported import target [{$target}]."),
            };
        });
    }

    /**
     * Import Inventory Items.
     */
    protected function executeItems(array $records, User $user): array
    {
        $created = 0;
        $updated = 0;

        foreach ($records as $data) {
            $mode = $data['_mode'] ?? 'create';
            $existingId = $data['_existing_id'] ?? null;
            unset($data['_mode'], $data['_existing_id']);

            if ($mode === 'update' && $existingId) {
                $item = InventoryItem::findOrFail($existingId);
                $oldValues = $item->only(array_keys($data));
                $item->update($data);
                $updated++;

                $this->auditLogger->log(
                    action: AuditAction::UpdatedInventoryItem,
                    actor: $user,
                    description: "Updated item {$item->sku} ({$item->name}) via data import",
                    target: $item,
                    targetName: $item->name,
                    oldValues: $oldValues,
                    newValues: $data,
                    module: 'Inventory'
                );
            } else {
                // Ensure starting quantity is 0; actual stock balances are managed by ItemStockLevel rows
                $data['quantity_on_hand'] = 0;
                $item = InventoryItem::create($data);
                $created++;

                $this->auditLogger->log(
                    action: AuditAction::CreatedInventoryItem,
                    actor: $user,
                    description: "Imported new inventory item {$item->sku} ({$item->name})",
                    target: $item,
                    targetName: $item->name,
                    newValues: $data,
                    module: 'Inventory'
                );
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }

    /**
     * Import Storage Locations.
     */
    protected function executeLocations(array $records, User $user): array
    {
        $created = 0;
        $updated = 0;

        foreach ($records as $data) {
            $mode = $data['_mode'] ?? 'create';
            $existingId = $data['_existing_id'] ?? null;
            unset($data['_mode'], $data['_existing_id']);

            if ($mode === 'update' && $existingId) {
                $location = StorageLocation::findOrFail($existingId);
                $oldValues = $location->only(array_keys($data));
                $location->update($data);
                $updated++;

                $this->auditLogger->log(
                    action: AuditAction::UpdatedStorageLocationStatus,
                    actor: $user,
                    description: "Updated storage location {$location->code} ({$location->name}) via data import",
                    target: $location,
                    targetName: $location->name,
                    oldValues: $oldValues,
                    newValues: $data,
                    module: 'Warehousing'
                );
            } else {
                $location = StorageLocation::create($data);
                $created++;

                $this->auditLogger->log(
                    action: AuditAction::CreatedStorageLocation,
                    actor: $user,
                    description: "Imported new storage location {$location->code} ({$location->name})",
                    target: $location,
                    targetName: $location->name,
                    newValues: $data,
                    module: 'Warehousing'
                );
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }

    /**
     * Import Suppliers.
     */
    protected function executeSuppliers(array $records, User $user): array
    {
        $created = 0;
        $updated = 0;

        foreach ($records as $data) {
            $mode = $data['_mode'] ?? 'create';
            $existingId = $data['_existing_id'] ?? null;
            unset($data['_mode'], $data['_existing_id']);

            if ($mode === 'update' && $existingId) {
                $supplier = Supplier::findOrFail($existingId);
                $oldValues = $supplier->only(array_keys($data));
                $supplier->update($data);
                $updated++;

                $this->auditLogger->log(
                    action: AuditAction::UpdatedSupplier,
                    actor: $user,
                    description: "Updated supplier {$supplier->name} via data import",
                    target: $supplier,
                    targetName: $supplier->name,
                    oldValues: $oldValues,
                    newValues: $data,
                    module: 'Procurement'
                );
            } else {
                $supplier = Supplier::create($data);
                $created++;

                $this->auditLogger->log(
                    action: AuditAction::CreatedSupplier,
                    actor: $user,
                    description: "Imported new supplier {$supplier->name}",
                    target: $supplier,
                    targetName: $supplier->name,
                    newValues: $data,
                    module: 'Procurement'
                );
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }
}
