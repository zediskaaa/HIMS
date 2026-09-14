<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemandForecastDemoSeeder extends Seeder
{
    private const DEMO_SKU_PREFIX = 'FCAST-';

    private const EXPECTED_ITEMS = 12;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demand forecasting demonstration data is disabled in production.');
        }

        if (InventoryItem::query()->where('sku', self::DEMO_SKU_PREFIX.'MASK-3PLY')->exists()) {
            $count = InventoryItem::query()->where('sku', 'like', self::DEMO_SKU_PREFIX.'%')->count();
            $this->command?->line("Preserved {$count} existing demand forecasting demonstration items.");

            return;
        }

        DB::transaction(function (): void {
            $categories = $this->categories();
            [$warehouse, $department] = $this->locations();
            $userId = User::query()->where('status', 'active')->value('id') ?? User::query()->value('id');

            foreach ($this->items() as $definition) {
                $stock = $definition['stock'];
                $reorderLevel = $definition['reorder_level'];
                $unitCost = $definition['unit_cost'];

                $item = InventoryItem::create([
                    'name' => $definition['name'],
                    'sku' => self::DEMO_SKU_PREFIX.$definition['sku'],
                    'category_id' => $categories[$definition['category']]->id,
                    'unit' => $definition['unit'],
                    'is_batch_tracked' => false,
                    'quantity_on_hand' => $stock,
                    'reserved_quantity' => $definition['reserved'],
                    'reorder_level' => $reorderLevel,
                    'safety_stock' => $definition['safety_stock'],
                    'lead_time_days' => $definition['lead_time_days'],
                    'unit_cost' => $unitCost,
                    'total_value' => $stock * $unitCost,
                    'default_location_id' => $warehouse->id,
                    'status' => $stock <= $reorderLevel ? 'low_stock' : 'in_stock',
                ]);

                ItemStockLevel::create([
                    'item_id' => $item->id,
                    'storage_location_id' => $warehouse->id,
                    'item_batch_id' => null,
                    'quantity' => $stock,
                    'reserved_quantity' => $definition['reserved'],
                ]);

                $this->seedConsumptionHistory(
                    $item,
                    $warehouse,
                    $department,
                    $definition['base_demand'],
                    $definition['events'],
                    $definition['pattern'],
                    $unitCost,
                    $userId,
                );
            }
        });

        // Kept in step with AiDemandForecastService::cacheKey(). A stale version
        // here means reseeding writes new history the dashboard never reads.
        Cache::forget('demand-forecast:v3:90:30');

        $this->command?->info(
            self::EXPECTED_ITEMS.' forecasting demo items and '.(self::EXPECTED_ITEMS * 24)
            .' dated consumption movements are ready.'
        );
    }

    /** @return array<string, ItemCategory> */
    private function categories(): array
    {
        return [
            'ppe' => ItemCategory::firstOrCreate(
                ['code' => 'PPE'],
                ['name' => 'PPE', 'description' => 'Personal protective equipment.', 'is_active' => true],
            ),
            'consumables' => ItemCategory::firstOrCreate(
                ['code' => 'MED-CONS'],
                ['name' => 'Medical Consumables', 'description' => 'Frequently consumed ward and procedure supplies.', 'is_active' => true],
            ),
            'pharmaceuticals' => ItemCategory::firstOrCreate(
                ['code' => 'PHARMA'],
                ['name' => 'Pharmaceuticals', 'description' => 'Medicines and pharmaceutical preparations.', 'is_active' => true],
            ),
            'diagnostics' => ItemCategory::firstOrCreate(
                ['code' => 'DIAG-SUP'],
                ['name' => 'Diagnostic Supplies', 'description' => 'Supplies used for diagnostic monitoring and testing.', 'is_active' => true],
            ),
        ];
    }

    /** @return array{0: StorageLocation, 1: StorageLocation} */
    private function locations(): array
    {
        $warehouse = StorageLocation::query()
            ->where('type', 'warehouse')
            ->where('status', 'active')
            ->first()
            ?? StorageLocation::firstOrCreate(
                ['code' => 'FCAST-WH'],
                ['name' => 'Forecast Demo Warehouse', 'type' => 'warehouse', 'status' => 'active', 'capacity' => 20000],
            );

        $department = StorageLocation::query()
            ->where('type', 'department')
            ->where('status', 'active')
            ->first()
            ?? StorageLocation::firstOrCreate(
                ['code' => 'FCAST-WARD'],
                ['name' => 'Forecast Demo Clinical Ward', 'type' => 'department', 'status' => 'active'],
            );

        return [$warehouse, $department];
    }

    /**
     * The demo hospital's catalogue. The rising items carry the largest base
     * demand on purpose: with no item selected the dashboard draws every item as
     * one aggregate line, so a growth story only reads if the growing items
     * dominate the volume being summed.
     *
     * @return array<int, array{
     *     name: string, sku: string, category: string, unit: string,
     *     stock: int, reserved: int, reorder_level: int, safety_stock: int,
     *     lead_time_days: int, unit_cost: float, base_demand: int,
     *     events: int, pattern: string
     * }>
     */
    private function items(): array
    {
        return [
            ['name' => '3-Ply Surgical Face Mask', 'sku' => 'MASK-3PLY', 'category' => 'ppe', 'unit' => 'box', 'stock' => 18, 'reserved' => 2, 'reorder_level' => 80, 'safety_stock' => 35, 'lead_time_days' => 10, 'unit_cost' => 115.00, 'base_demand' => 18, 'events' => 24, 'pattern' => 'rising'],
            ['name' => 'Examination Gloves (Medium)', 'sku' => 'GLOVE-M', 'category' => 'ppe', 'unit' => 'box', 'stock' => 165, 'reserved' => 15, 'reorder_level' => 110, 'safety_stock' => 45, 'lead_time_days' => 8, 'unit_cost' => 285.00, 'base_demand' => 9, 'events' => 24, 'pattern' => 'steady'],
            ['name' => 'Disposable Syringe 5 mL', 'sku' => 'SYRINGE-5ML', 'category' => 'consumables', 'unit' => 'box', 'stock' => 24, 'reserved' => 3, 'reorder_level' => 95, 'safety_stock' => 30, 'lead_time_days' => 12, 'unit_cost' => 195.00, 'base_demand' => 7, 'events' => 24, 'pattern' => 'surge'],
            ['name' => 'IV Cannula 22G', 'sku' => 'IVC-22G', 'category' => 'consumables', 'unit' => 'box', 'stock' => 14, 'reserved' => 2, 'reorder_level' => 65, 'safety_stock' => 24, 'lead_time_days' => 14, 'unit_cost' => 420.00, 'base_demand' => 14, 'events' => 24, 'pattern' => 'rising'],
            ['name' => 'Sterile Gauze Pads 4x4', 'sku' => 'GAUZE-4X4', 'category' => 'consumables', 'unit' => 'pack', 'stock' => 40, 'reserved' => 4, 'reorder_level' => 75, 'safety_stock' => 28, 'lead_time_days' => 7, 'unit_cost' => 85.00, 'base_demand' => 6, 'events' => 24, 'pattern' => 'falling'],
            ['name' => 'Normal Saline 0.9% 1 L', 'sku' => 'SALINE-1L', 'category' => 'pharmaceuticals', 'unit' => 'bag', 'stock' => 18, 'reserved' => 2, 'reorder_level' => 60, 'safety_stock' => 22, 'lead_time_days' => 9, 'unit_cost' => 68.00, 'base_demand' => 5, 'events' => 24, 'pattern' => 'steady'],
            ['name' => 'Ceftriaxone 1 g Vial', 'sku' => 'CEFTRI-1G', 'category' => 'pharmaceuticals', 'unit' => 'vial', 'stock' => 12, 'reserved' => 2, 'reorder_level' => 48, 'safety_stock' => 18, 'lead_time_days' => 15, 'unit_cost' => 92.00, 'base_demand' => 10, 'events' => 24, 'pattern' => 'rising'],
            ['name' => 'Isopropyl Alcohol 70% 500 mL', 'sku' => 'ALCOHOL-500', 'category' => 'pharmaceuticals', 'unit' => 'bottle', 'stock' => 8, 'reserved' => 1, 'reorder_level' => 42, 'safety_stock' => 16, 'lead_time_days' => 6, 'unit_cost' => 74.00, 'base_demand' => 3, 'events' => 24, 'pattern' => 'surge'],
            ['name' => 'ECG Monitoring Electrodes', 'sku' => 'ECG-ELECTRODE', 'category' => 'diagnostics', 'unit' => 'pack', 'stock' => 16, 'reserved' => 2, 'reorder_level' => 55, 'safety_stock' => 20, 'lead_time_days' => 11, 'unit_cost' => 310.00, 'base_demand' => 4, 'events' => 24, 'pattern' => 'intermittent'],
            ['name' => 'Blood Glucose Test Strips', 'sku' => 'GLUCOSE-STRIP', 'category' => 'diagnostics', 'unit' => 'box', 'stock' => 50, 'reserved' => 5, 'reorder_level' => 70, 'safety_stock' => 26, 'lead_time_days' => 10, 'unit_cost' => 620.00, 'base_demand' => 7, 'events' => 24, 'pattern' => 'steady'],
            ['name' => 'Urinary Catheter 16Fr', 'sku' => 'CATHETER-16FR', 'category' => 'consumables', 'unit' => 'piece', 'stock' => 35, 'reserved' => 3, 'reorder_level' => 38, 'safety_stock' => 14, 'lead_time_days' => 13, 'unit_cost' => 48.00, 'base_demand' => 3, 'events' => 24, 'pattern' => 'falling'],
            ['name' => 'Absorbable Suture 3-0', 'sku' => 'SUTURE-3-0', 'category' => 'consumables', 'unit' => 'box', 'stock' => 10, 'reserved' => 1, 'reorder_level' => 36, 'safety_stock' => 15, 'lead_time_days' => 16, 'unit_cost' => 780.00, 'base_demand' => 8, 'events' => 24, 'pattern' => 'rising'],
        ];
    }

    private function seedConsumptionHistory(
        InventoryItem $item,
        StorageLocation $warehouse,
        StorageLocation $department,
        int $baseDemand,
        int $events,
        string $pattern,
        float $unitCost,
        mixed $userId,
    ): void {
        $oldestDaysAgo = 88;
        $newestDaysAgo = 2;
        $span = $oldestDaysAgo - $newestDaysAgo;

        for ($index = 0; $index < $events; $index++) {
            $progress = $events > 1 ? $index / ($events - 1) : 1.0;
            $daysAgo = (int) round($oldestDaysAgo - ($progress * $span));
            $movementType = $index % 3 === 0 ? MovementType::StockOut : MovementType::Issuance;

            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => $movementType,
                'quantity' => $this->quantityForPattern($baseDemand, $progress, $pattern, $index),
                'unit_cost' => $unitCost,
                'from_location_id' => $warehouse->id,
                'to_location_id' => $movementType === MovementType::Issuance ? $department->id : null,
                'remarks' => sprintf('[Forecast demo] %s demand event %02d', $pattern, $index + 1),
                'moved_at' => now()->subDays($daysAgo)->setTime(8 + ($index % 10), ($index * 11) % 60),
                'user_id' => $userId,
            ]);
        }
    }

    private function quantityForPattern(int $base, float $progress, string $pattern, int $index): int
    {
        $quantity = match ($pattern) {
            // Accelerating demand: about a third of the base at the start of the
            // window, three times it by the end. The shape matters as much as the
            // size — the forecast projects the horizon at the rate over the later
            // half, so a growth story only reads as growth when that half is
            // genuinely hotter than the window average behind it.
            'rising' => $base * (0.35 + (($progress ** 2) * 2.65)),
            'falling' => $base * (1.9 - ($progress * 1.35)),
            'surge' => $base * ($progress >= 0.70 ? 2.6 : 0.8 + ($progress * 0.5)),
            'intermittent' => $base * ($index % 4 === 0 ? 2.2 : 0.45),
            default => $base + (($index % 3) - 1),
        };

        return max(1, (int) round($quantity));
    }
}
