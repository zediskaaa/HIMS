<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Database\Seeders\TargetedMasterDataCompletionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TargetedMasterDataCompletionSeederTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY_CODES = [
        'MED-VENT-01' => 'MED-CONS',
        'MED-GAUZE-ST' => 'MED-CONS',
        'MED-TITAN-PL' => 'MED',
        'MED-INF-SET' => 'MED-CONS',
        'VAC-RAB-VER05' => 'PHARMA',
        'ANT-MER-1G00' => 'PHARMA',
    ];

    private const SUPPLIER_SKUS = [
        'PPE-MASK-N95',
        'PHARMA-PARA-500',
        'PPE-GLOVE-L',
        'VAC-RAB-VER05',
        'ANT-MER-1G00',
        'PPE-GOWN-XL',
    ];

    private function existingMasterData(): void
    {
        foreach (['MED', 'MED-CONS', 'PHARMA'] as $code) {
            ItemCategory::create(['code' => $code, 'name' => $code, 'is_active' => true]);
        }

        $suppliers = [
            Supplier::create([
                'name' => 'Harborline Clinical Goods Cooperative',
                'status' => SupplierStatus::Active,
                'accreditation_status' => SupplierAccreditationStatus::Draft,
            ]),
            Supplier::create([
                'name' => 'Crestfall Hospital Supply Works',
                'status' => SupplierStatus::Active,
                'accreditation_status' => SupplierAccreditationStatus::Draft,
            ]),
        ];

        $skus = array_values(array_unique([...self::SUPPLIER_SKUS, ...array_keys(self::CATEGORY_CODES)]));
        foreach ($skus as $sku) {
            $supplierIndex = array_search($sku, self::SUPPLIER_SKUS, true);
            InventoryItem::create([
                'sku' => $sku,
                'name' => 'Catalog item '.$sku,
                'unit' => 'piece',
                'status' => 'active',
                'supplier_id' => $supplierIndex === false ? null : $suppliers[$supplierIndex % 2]->id,
            ]);
        }
    }

    public function test_it_repairs_only_existing_relationships_and_is_idempotent(): void
    {
        $this->existingMasterData();
        $item = InventoryItem::where('sku', 'PPE-MASK-N95')->firstOrFail();
        $existingProduct = SupplierProduct::create([
            'supplier_id' => $item->supplier_id,
            'item_id' => $item->id,
            'is_active' => false,
        ]);
        $alreadyCategorized = InventoryItem::where('sku', 'MED-VENT-01')->firstOrFail();
        $alreadyCategorized->update([
            'category_id' => ItemCategory::where('code', 'MED-CONS')->value('id'),
        ]);

        $this->seed(TargetedMasterDataCompletionSeeder::class);

        $this->assertDatabaseCount('supplier_products', 6);
        $this->assertFalse($existingProduct->fresh()->is_active);
        foreach (self::SUPPLIER_SKUS as $sku) {
            $linkedItem = InventoryItem::where('sku', $sku)->firstOrFail();
            $this->assertDatabaseHas('supplier_products', [
                'supplier_id' => $linkedItem->supplier_id,
                'item_id' => $linkedItem->id,
            ]);
        }
        foreach (self::CATEGORY_CODES as $sku => $code) {
            $this->assertSame(
                ItemCategory::where('code', $code)->value('id'),
                InventoryItem::where('sku', $sku)->value('category_id'),
            );
        }

        $this->assertSame(5, AuditLog::where('action', AuditAction::AddedSupplierProduct->value)->count());
        $this->assertSame(5, AuditLog::where('action', AuditAction::UpdatedInventoryItem->value)->count());
        $this->assertSame(10, AuditLog::count());
        $productEvent = AuditLog::where('action', AuditAction::AddedSupplierProduct->value)->firstOrFail();
        $this->assertSame(Supplier::class, $productEvent->target_type);
        $this->assertSame($productEvent->target_id, (string) $productEvent->new_values['supplier_id']);
        $this->assertArrayHasKey('item_id', $productEvent->new_values);
        $categoryEvent = AuditLog::where('action', AuditAction::UpdatedInventoryItem->value)->firstOrFail();
        $this->assertSame(InventoryItem::class, $categoryEvent->target_type);
        $this->assertSame(['category_id' => null], $categoryEvent->old_values);
        $this->assertArrayHasKey('category_code', $categoryEvent->new_values);
        foreach (AuditLog::all() as $event) {
            $this->assertNull($event->user_id);
            $this->assertSame('System', $event->actor_name);
            $this->assertSame('system', $event->source);
        }

        $this->seed(TargetedMasterDataCompletionSeeder::class);

        $this->assertDatabaseCount('supplier_products', 6);
        $this->assertSame(10, AuditLog::count());
        $this->assertDatabaseCount('supplier_prices', 0);
        $this->assertDatabaseCount('supplier_contracts', 0);
        $this->assertDatabaseCount('supplier_accreditations', 0);
        $this->assertSame(0, Supplier::where('accreditation_status', SupplierAccreditationStatus::Approved->value)->count());
    }

    public function test_missing_supplier_aborts_without_partial_writes(): void
    {
        $this->existingMasterData();
        InventoryItem::where('sku', 'PPE-GOWN-XL')->firstOrFail()->update(['supplier_id' => null]);

        try {
            $this->seed(TargetedMasterDataCompletionSeeder::class);
            $this->fail('The seeder should reject a missing recorded supplier.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('PPE-GOWN-XL has no recorded supplier', $exception->getMessage());
        }

        $this->assertDatabaseCount('supplier_products', 0);
        $this->assertSame(0, InventoryItem::whereNotNull('category_id')->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_conflicting_existing_category_is_preserved_without_partial_writes(): void
    {
        $this->existingMasterData();
        $otherCategory = ItemCategory::create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
        InventoryItem::where('sku', 'MED-GAUZE-ST')->firstOrFail()->update(['category_id' => $otherCategory->id]);

        try {
            $this->seed(TargetedMasterDataCompletionSeeder::class);
            $this->fail('The seeder should reject a conflicting existing category.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('MED-GAUZE-ST has a different category', $exception->getMessage());
        }

        $this->assertSame($otherCategory->id, InventoryItem::where('sku', 'MED-GAUZE-ST')->value('category_id'));
        $this->assertDatabaseCount('supplier_products', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_incompatible_default_location_blocks_category_assignment(): void
    {
        $this->assertIncompatibleLocationBlocksCategoryAssignment(true);
    }

    public function test_incompatible_stock_location_blocks_category_assignment(): void
    {
        $this->assertIncompatibleLocationBlocksCategoryAssignment(false);
    }

    private function assertIncompatibleLocationBlocksCategoryAssignment(bool $isDefault): void
    {
        $this->existingMasterData();
        $location = StorageLocation::create([
            'code' => 'LOC-CATEGORY-RULE',
            'name' => 'Medical supply bin',
            'type' => 'bin',
            'status' => 'active',
        ]);
        $location->categoryRules()->create([
            'item_category_id' => ItemCategory::where('code', 'MED')->value('id'),
        ]);
        $item = InventoryItem::where('sku', 'MED-GAUZE-ST')->firstOrFail();

        if ($isDefault) {
            $item->update(['default_location_id' => $location->id]);
        } else {
            ItemStockLevel::create([
                'item_id' => $item->id,
                'storage_location_id' => $location->id,
                'quantity' => 1,
            ]);
            $item->update(['quantity_on_hand' => 1]);
        }

        try {
            $this->seed(TargetedMasterDataCompletionSeeder::class);
            $this->fail('The seeder should reject an incompatible location category rule.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('MED-GAUZE-ST has a location that does not allow category MED-CONS', $exception->getMessage());
        }

        $this->assertNull($item->fresh()->category_id);
        $this->assertDatabaseCount('supplier_products', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
