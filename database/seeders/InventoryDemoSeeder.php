<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    /**
     * The consumption history spans this many days back. Kept inside the
     * forecast's default 90-day analysis window, with a couple of days of
     * slack at each end so the oldest row cannot fall outside it.
     */
    private const HISTORY_START_DAYS = 88;

    private const HISTORY_END_DAYS = 2;

    public function run(): void
    {
        if (InventoryItem::query()->where('sku', 'PPE-MASK-N95')->exists()) {
            $this->command?->line('Preserved existing inventory demonstration data.');

            return;
        }

        // Categories
        $medical = ItemCategory::create(['name' => 'Medical Supplies', 'code' => 'MED', 'is_active' => true]);
        $ppe = ItemCategory::create(['name' => 'PPE', 'code' => 'PPE', 'parent_id' => $medical->id, 'is_active' => true]);
        $pharma = ItemCategory::create(['name' => 'Pharmaceuticals', 'code' => 'PHARMA', 'is_active' => true]);

        // Storage locations (hierarchical)
        $warehouse = StorageLocation::create(['name' => 'Main Warehouse', 'code' => 'WH-01', 'type' => 'warehouse', 'status' => 'active', 'capacity' => 10000]);
        $zoneA = StorageLocation::create(['name' => 'Zone A', 'code' => 'WH-01-A', 'type' => 'zone', 'parent_id' => $warehouse->id, 'status' => 'active']);
        $pharmacy = StorageLocation::create(['name' => 'Central Pharmacy', 'code' => 'PHARM-01', 'type' => 'pharmacy', 'status' => 'active', 'capacity' => 2000]);

        // Consuming wards. Without these the "Issued To" dropdown on the stock
        // movement screen has nowhere to issue to, and issuance — one of the two
        // movement types the forecast counts as demand — cannot be demonstrated.
        $wardMed = StorageLocation::create(['name' => 'Ward 3 — Medical', 'code' => 'WARD-03', 'type' => 'department', 'status' => 'active']);
        $emergency = StorageLocation::create(['name' => 'Emergency Room', 'code' => 'DEPT-ER', 'type' => 'department', 'status' => 'active']);
        $surgery = StorageLocation::create(['name' => 'Operating Theatre', 'code' => 'DEPT-OR', 'type' => 'department', 'status' => 'active']);

        // Suppliers
        $medsupply = Supplier::create([
            'name' => 'MedSupply Corp',
            'contact_person' => 'Maria Santos',
            'email' => 'maria@medsupply.ph',
            'phone' => '09171234567',
            'address' => 'Quezon City, Metro Manila',
            'status' => 'active',
        ]);

        // N95 Masks - batch tracked, multiple batches at different expiry dates
        $masks = InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'PPE-MASK-N95',
            'category_id' => $ppe->id,
            'unit' => 'box',
            'is_batch_tracked' => true,
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'expiry_alert_days' => 60,
            'supplier_id' => $medsupply->id,
            'default_location_id' => $zoneA->id,
            'status' => 'active',
        ]);

        $batch1 = ItemBatch::create([
            'item_id' => $masks->id,
            'batch_number' => 'N95-2026-001',
            'expiry_date' => now()->addMonths(3),
            'unit_cost' => 45.00,
            'initial_quantity' => 100,
            'status' => 'active',
        ]);

        $batch2 = ItemBatch::create([
            'item_id' => $masks->id,
            'batch_number' => 'N95-2026-002',
            'expiry_date' => now()->addMonths(8),
            'unit_cost' => 47.50,
            'initial_quantity' => 150,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $masks->id,
            'storage_location_id' => $zoneA->id,
            'item_batch_id' => $batch1->id,
            'quantity' => 35,
            'reserved_quantity' => 5,
        ]);

        ItemStockLevel::create([
            'item_id' => $masks->id,
            'storage_location_id' => $zoneA->id,
            'item_batch_id' => $batch2->id,
            'quantity' => 140,
            'reserved_quantity' => 0,
        ]);

        // Paracetamol 500mg - batch tracked, in pharmacy
        $paracetamol = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PHARMA-PARA-500',
            'category_id' => $pharma->id,
            'unit' => 'bottle',
            'is_batch_tracked' => true,
            'quantity_on_hand' => 0,
            'reorder_level' => 20,
            'expiry_alert_days' => 90,
            'supplier_id' => $medsupply->id,
            'default_location_id' => $pharmacy->id,
            'status' => 'active',
        ]);

        $paraBatch = ItemBatch::create([
            'item_id' => $paracetamol->id,
            'batch_number' => 'PARA-2026-045',
            'expiry_date' => now()->addMonths(18),
            'unit_cost' => 8.50,
            'initial_quantity' => 200,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $paracetamol->id,
            'storage_location_id' => $pharmacy->id,
            'item_batch_id' => $paraBatch->id,
            'quantity' => 180,
            'reserved_quantity' => 10,
        ]);

        // Surgical Gloves - not batch tracked
        $gloves = InventoryItem::create([
            'name' => 'Surgical Gloves (Large)',
            'sku' => 'PPE-GLOVE-L',
            'category_id' => $ppe->id,
            'unit' => 'box',
            'is_batch_tracked' => false,
            'quantity_on_hand' => 0,
            'reorder_level' => 30,
            'supplier_id' => $medsupply->id,
            'default_location_id' => $zoneA->id,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $gloves->id,
            'storage_location_id' => $zoneA->id,
            'item_batch_id' => null,
            'quantity' => 25,
            'reserved_quantity' => 0,
        ]);

        $this->seedConsumptionHistory([
            // [item, batch, source location, destination, units per issue, issues, shape]
            [$masks, $batch1, $zoneA, $emergency, 3, 22, 'rising'],
            [$paracetamol, $paraBatch, $pharmacy, $wardMed, 4, 26, 'steady'],
            [$gloves, null, $zoneA, $surgery, 2, 12, 'falling'],
        ]);

        // Movements the forecast has to ignore. Seeded so the exclusion can be
        // shown on screen rather than only asserted: a transfer relocates stock
        // without anyone using it, and an adjustment corrects a miscount.
        $this->seedNonDemandMovements($masks, $batch2, $zoneA, $pharmacy);

        // Sync cached totals
        foreach ([$masks, $paracetamol, $gloves] as $item) {
            $total = ItemStockLevel::where('item_id', $item->id)->sum('quantity');
            $totalCost = ItemStockLevel::query()
                ->where('item_stock_levels.item_id', $item->id)
                ->join('item_batches', 'item_stock_levels.item_batch_id', '=', 'item_batches.id')
                ->selectRaw('SUM(item_stock_levels.quantity * item_batches.unit_cost) as value')
                ->value('value');

            $item->update([
                'quantity_on_hand' => $total,
                'unit_cost' => $total > 0 ? ($totalCost / $total) : 0,
                'total_value' => $totalCost ?? 0,
                'status' => match (true) {
                    $total <= 0 => 'out_of_stock',
                    $total <= $item->reorder_level => 'low_stock',
                    default => 'in_stock',
                },
            ]);
        }

        $this->command?->info('Inventory demo data seeded: 3 categories, 6 locations, 1 supplier, 3 items with batches, stock levels and 90 days of consumption history.');
    }

    /**
     * Write backdated consumption history for the demand forecast.
     *
     * These rows are created directly rather than through
     * InventoryAutomationService::recordMovement(). The stock levels above are
     * already set to what is on the shelf *today* — posting these through the
     * service would deduct the same units a second time and drive the balances
     * negative. The levels say where stock stands now; these movements say what
     * was consumed getting there.
     *
     * @param  array<int, array{0: InventoryItem, 1: ItemBatch|null, 2: StorageLocation, 3: StorageLocation, 4: int, 5: int, 6: string}>  $plans
     */
    private function seedConsumptionHistory(array $plans): void
    {
        // Nullable on stock_movements, so a standalone run of this seeder with
        // no users table populated still works.
        $userId = User::query()->value('id');

        foreach ($plans as [$item, $batch, $from, $to, $baseQuantity, $issues, $shape]) {
            $span = self::HISTORY_START_DAYS - self::HISTORY_END_DAYS;

            for ($i = 0; $i < $issues; $i++) {
                // Spread the issues evenly across the window: the first sits at
                // the oldest end, the last near today.
                $progress = $issues > 1 ? $i / ($issues - 1) : 1.0;
                $daysAgo = (int) round(self::HISTORY_START_DAYS - ($progress * $span));

                // Alternate stock_out and issuance so both movement types the
                // forecast counts as demand appear in the history.
                $type = $i % 2 === 0 ? MovementType::Issuance : MovementType::StockOut;

                StockMovement::create([
                    'item_id' => $item->id,
                    'item_batch_id' => $batch?->id,
                    'movement_type' => $type,
                    'quantity' => $this->shapedQuantity($baseQuantity, $progress, $shape),
                    // Not nullable on the table — it defaults to 0 — so an item
                    // without batch tracking records a zero cost, not a null.
                    'unit_cost' => $batch?->unit_cost ?? 0,
                    'from_location_id' => $from->id,
                    'to_location_id' => $type === MovementType::Issuance ? $to->id : null,
                    'remarks' => $type === MovementType::Issuance
                        ? 'Issued to '.$to->name
                        : 'Consumed at '.$from->name,
                    'moved_at' => now()->subDays($daysAgo)->setTime(9 + ($i % 8), ($i * 7) % 60),
                    'user_id' => $userId,
                ]);
            }
        }
    }

    /**
     * Bend the per-issue quantity so each item tells a different story.
     *
     * The forecast compares the later half of the window against the earlier
     * half and needs a swing of more than 15% to call a trend, so the multipliers
     * here are wide enough to clear that on either side.
     */
    private function shapedQuantity(int $base, float $progress, string $shape): int
    {
        $multiplier = match ($shape) {
            'rising' => 0.6 + ($progress * 1.2),   // demand climbing — reads as Increasing
            'falling' => 1.6 - ($progress * 1.1),  // tapering off — reads as Decreasing
            default => 1.0,                        // flat — reads as Stable
        };

        return max(1, (int) round($base * $multiplier));
    }

    /**
     * Movements that exist in the history but must never reach the forecast.
     */
    private function seedNonDemandMovements(
        InventoryItem $item,
        ItemBatch $batch,
        StorageLocation $from,
        StorageLocation $to
    ): void {
        $userId = User::query()->value('id');

        StockMovement::create([
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'movement_type' => MovementType::Transfer,
            'quantity' => 20,
            'unit_cost' => $batch->unit_cost,
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'remarks' => 'Relocated to pharmacy buffer stock — not consumption.',
            'moved_at' => now()->subDays(14)->setTime(10, 30),
            'user_id' => $userId,
        ]);

        StockMovement::create([
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'movement_type' => MovementType::Adjustment,
            'quantity' => 5,
            'unit_cost' => $batch->unit_cost,
            'from_location_id' => $from->id,
            'remarks' => 'Cycle count correction — not consumption.',
            'moved_at' => now()->subDays(9)->setTime(15, 5),
            'user_id' => $userId,
        ]);
    }
}
