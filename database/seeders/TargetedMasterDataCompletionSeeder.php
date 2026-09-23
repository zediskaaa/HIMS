<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\SupplierStatus;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Explicitly repair known, existing master-data relationships only.
 *
 * This seeder is intentionally absent from DatabaseSeeder. It does not create
 * suppliers, prices, accreditation decisions, stock, or transactions.
 */
class TargetedMasterDataCompletionSeeder extends Seeder
{
    private const SUPPLIER_ITEM_SKUS = [
        'PPE-MASK-N95',
        'PHARMA-PARA-500',
        'PPE-GLOVE-L',
        'VAC-RAB-VER05',
        'ANT-MER-1G00',
        'PPE-GOWN-XL',
    ];

    private const ITEM_CATEGORY_CODES = [
        'MED-VENT-01' => 'MED-CONS',
        'MED-GAUZE-ST' => 'MED-CONS',
        'MED-TITAN-PL' => 'MED',
        'MED-INF-SET' => 'MED-CONS',
        'VAC-RAB-VER05' => 'PHARMA',
        'ANT-MER-1G00' => 'PHARMA',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $skus = array_values(array_unique([
                ...self::SUPPLIER_ITEM_SKUS,
                ...array_keys(self::ITEM_CATEGORY_CODES),
            ]));
            $items = InventoryItem::query()
                ->whereIn('sku', $skus)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('sku');

            foreach ($skus as $sku) {
                $item = $items->get($sku);
                if (! $item || $item->status !== 'active') {
                    throw new RuntimeException("Expected active inventory item {$sku} was not found; no data was changed.");
                }
            }

            $categories = ItemCategory::query()
                ->whereIn('code', array_unique(array_values(self::ITEM_CATEGORY_CODES)))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            foreach (self::ITEM_CATEGORY_CODES as $sku => $code) {
                $category = $categories->get($code);
                $item = $items->get($sku);

                if (! $category || ! $category->is_active) {
                    throw new RuntimeException("Expected active item category {$code} was not found; no data was changed.");
                }
                if ($item->category_id !== null && (int) $item->category_id !== $category->id) {
                    throw new RuntimeException("Inventory item {$sku} has a different category; no data was changed.");
                }

                if ($item->category_id === null) {
                    $locationIds = $item->stockLevels()
                        ->pluck('storage_location_id')
                        ->push($item->default_location_id)
                        ->filter()
                        ->unique()
                        ->all();

                    $hasIncompatibleLocation = StorageLocation::query()
                        ->whereKey($locationIds)
                        ->whereHas('categoryRules')
                        ->whereDoesntHave('categoryRules', fn ($rules) => $rules->where('item_category_id', $category->id))
                        ->exists();

                    if ($hasIncompatibleLocation) {
                        throw new RuntimeException("Inventory item {$sku} has a location that does not allow category {$code}; no data was changed.");
                    }
                }
            }

            $supplierIds = [];
            foreach (self::SUPPLIER_ITEM_SKUS as $sku) {
                $supplierId = $items->get($sku)->supplier_id;
                if ($supplierId === null) {
                    throw new RuntimeException("Inventory item {$sku} has no recorded supplier; no data was changed.");
                }
                $supplierIds[] = (int) $supplierId;
            }

            $suppliers = Supplier::query()
                ->whereKey(array_unique($supplierIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach (self::SUPPLIER_ITEM_SKUS as $sku) {
                $supplier = $suppliers->get((int) $items->get($sku)->supplier_id);
                if (! $supplier || $supplier->status !== SupplierStatus::Active) {
                    throw new RuntimeException("The recorded supplier for {$sku} is missing or inactive; no data was changed.");
                }
            }

            $audit = app(AuditLogger::class);

            foreach (self::SUPPLIER_ITEM_SKUS as $sku) {
                $item = $items->get($sku);
                $supplier = $suppliers->get((int) $item->supplier_id);
                $product = $supplier->supplierProducts()->firstOrCreate(['item_id' => $item->id]);

                if ($product->wasRecentlyCreated) {
                    $audit->record(
                        AuditAction::AddedSupplierProduct,
                        target: $supplier,
                        targetName: $supplier->name,
                        description: 'Linked an existing inventory item to its recorded supplier.',
                        newValues: [
                            'supplier_product_id' => $product->id,
                            'supplier_id' => $supplier->id,
                            'item_id' => $item->id,
                            'item_sku' => $item->sku,
                        ],
                    );
                }
            }

            foreach (self::ITEM_CATEGORY_CODES as $sku => $code) {
                $item = $items->get($sku);
                if ($item->category_id !== null) {
                    continue;
                }

                $category = $categories->get($code);
                $item->category_id = $category->id;
                $item->save();

                $audit->record(
                    AuditAction::UpdatedInventoryItem,
                    target: $item,
                    targetName: $item->name,
                    description: 'Assigned an existing category to an inventory item.',
                    oldValues: ['category_id' => null],
                    newValues: [
                        'category_id' => $category->id,
                        'category_code' => $category->code,
                    ],
                );
            }
        });
    }
}
