<?php

namespace Database\Seeders;

use App\Enums\WarehouseTaskType;
use App\Models\InventoryItem;
use App\Models\InventorySerial;
use App\Models\IoTTelemetryLog;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\PdeaDangerousDrugsRegister;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\SurgicalConsignmentBillOnly;
use App\Models\User;
use App\Services\InventoryAutomationService;
use App\Services\Warehouse\WarehouseTaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmartWarehousingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Smart warehousing demonstration data is disabled in production.');
        }

        DB::transaction(function (): void {
            $user = User::query()->first() ?? User::factory()->inventoryManager()->create();

            $category = ItemCategory::firstOrCreate(
                ['code' => 'SWS-DEMO-MED'],
                ['name' => 'Smart Warehouse Demo Medical Supplies', 'description' => 'Non-clinical workflow demonstration catalogue.', 'is_active' => true],
            );

            $pharmaCategory = ItemCategory::firstOrCreate(
                ['code' => 'SWS-DEMO-PHARMA'],
                ['name' => 'Clinical Pharmaceuticals', 'description' => 'Demo therapeutic drugs.', 'is_active' => true],
            );

            $surgicalCategory = ItemCategory::firstOrCreate(
                ['code' => 'SWS-DEMO-SURG'],
                ['name' => 'Surgical Consignment Implants', 'description' => 'Demo prostheses and stents.', 'is_active' => true],
            );

            // 1. Storage Topology
            $warehouse = $this->location('SWS-DEMO-WH', 'Smart Warehouse Demonstration', 'warehouse', null, ['capacity' => 10000]);
            $receiving = $this->location('SWS-DEMO-RCV', 'Receiving Staging', 'zone', $warehouse, ['is_receiving_staging' => true, 'capacity' => 500]);
            $this->location('SWS-DEMO-QA', 'Inspection Quarantine', 'zone', $warehouse, ['is_quarantine' => true, 'capacity' => 250]);
            $reserve = $this->location('SWS-DEMO-RSV', 'Medical Supply Reserve', 'zone', $warehouse, ['is_reserve' => true, 'capacity' => 1000]);
            $pickFace = $this->location('SWS-DEMO-PICK', 'Medical Supply Pick Face', 'bin', $reserve, ['is_pick_face' => true, 'capacity' => 120, 'sort_sequence' => 10]);
            $this->location('SWS-DEMO-DSP', 'Department Dispatch Staging', 'zone', $warehouse, ['is_dispatch_staging' => true, 'capacity' => 300]);

            // Ambient Zone & Bins (with LASA coordinate testing)
            $ambientZone = $this->location('Z-AMB-01', 'Central Ambient Zone', 'zone', $warehouse, ['temperature_classification' => 'ambient', 'capacity' => 5000]);
            $binA01 = $this->location('W1-Z1-A01-R01-B01', 'Ambient Storage Bin A01-R01-B01', 'bin', $ambientZone, [
                'aisle' => 'A01', 'rack' => 'R01', 'shelf' => 'S01', 'bin' => 'B01',
                'temperature_classification' => 'ambient', 'capacity' => 500, 'max_weight_kg' => 200.00,
            ]);
            $binA02 = $this->location('W1-Z1-A01-R01-B02', 'Ambient Storage Bin A01-R01-B02 (Adjacent)', 'bin', $ambientZone, [
                'aisle' => 'A01', 'rack' => 'R01', 'shelf' => 'S01', 'bin' => 'B02',
                'temperature_classification' => 'ambient', 'capacity' => 500, 'max_weight_kg' => 200.00,
            ]);

            // Cold Chain Storage (2°C - 8°C)
            $coldZone = $this->location('Z-REF-01', 'Central Pharmacy Cold Room', 'cold_room', $warehouse, [
                'temperature_classification' => 'refrigerated', 'capacity' => 2000,
            ]);
            $binCold = $this->location('W1-Z2-COLD-R01-B01', 'Pharmacy Cold Storage Shelf 01', 'bin', $coldZone, [
                'aisle' => 'C01', 'rack' => 'R01', 'shelf' => 'S01', 'bin' => 'B01',
                'temperature_classification' => 'refrigerated', 'capacity' => 200, 'max_weight_kg' => 50.00,
            ]);

            // Narcotics Vault (RA 9165 Dual-Custody)
            $vaultZone = $this->location('Z-NAR-01', 'High-Security Central Narcotics Vault', 'vault', $warehouse, [
                'temperature_classification' => 'ambient', 'is_narcotics_vault' => true, 'capacity' => 1000,
            ]);
            $binVault = $this->location('W1-Z3-VAULT-S01-B01', 'Narcotics Vault Safe Drawer 01', 'bin', $vaultZone, [
                'aisle' => 'V01', 'rack' => 'R01', 'shelf' => 'S01', 'bin' => 'B01',
                'is_narcotics_vault' => true, 'capacity' => 300, 'max_weight_kg' => 100.00,
            ]);

            // Surgical Operating Suite Cleanroom
            $orZone = $this->location('Z-OR-01', 'Operating Theater Suite 3 Cleanroom', 'zone', $warehouse, [
                'temperature_classification' => 'ambient', 'is_dispatch_staging' => true, 'capacity' => 500,
            ]);
            $binConsignment = $this->location('W1-OR-CONS-R01-B01', 'OR Suite 3 Consignment Cabinet', 'bin', $orZone, [
                'aisle' => 'OR1', 'rack' => 'R01', 'shelf' => 'S01', 'bin' => 'B01',
                'capacity' => 100, 'max_weight_kg' => 25.00,
            ]);

            // 2. Clinical Items Master Data
            $syringe = InventoryItem::firstOrCreate(
                ['sku' => 'SWS-DEMO-SYRINGE-5ML'],
                [
                    'name' => 'Sterile Syringe 5 mL — Warehouse Workflow Demo',
                    'barcode_value' => 'SWS-ITEM-SYRINGE-5ML',
                    'gtin' => '04801234567897',
                    'category_id' => $category->id,
                    'unit' => 'box',
                    'is_batch_tracked' => true,
                    'is_serial_tracked' => false,
                    'is_expiry_tracked' => true,
                    'storage_classification' => 'medical_supply',
                    'temperature_classification' => 'ambient',
                    'pick_face_minimum' => 12,
                    'pick_face_maximum' => 48,
                    'quantity_on_hand' => 0,
                    'reserved_quantity' => 0,
                    'reorder_level' => 20,
                    'expiry_alert_days' => 90,
                    'unit_cost' => 275.00,
                    'total_value' => 0,
                    'default_location_id' => $pickFace->id,
                    'status' => 'active',
                ],
            );

            // Dangerous Drug: Morphine Sulfate
            $morphine = InventoryItem::firstOrCreate(
                ['sku' => 'DRG-MORS-002'],
                [
                    'name' => 'Morphine Sulfate 10 mg/mL Ampoule',
                    'generic_name' => 'Morphine Sulfate',
                    'brand_name' => 'Morphine',
                    'dosage_form_strength' => '10 mg/mL, 1 mL Ampoule',
                    'regulatory_category' => 'DANGEROUS_DRUG',
                    'barcode_value' => 'DRG-MORS-002',
                    'gtin' => '04800123456789',
                    'category_id' => $pharmaCategory->id,
                    'unit' => 'ampoule',
                    'is_batch_tracked' => true,
                    'is_expiry_tracked' => true,
                    'storage_classification' => 'narcotics',
                    'temperature_classification' => 'ambient',
                    'fda_cpr_number' => 'DRP-0491-01',
                    'unit_cost' => 85.00,
                    'default_location_id' => $binVault->id,
                    'status' => 'active',
                ],
            );

            // Cold Chain: Rabies Vaccine
            $rabies = InventoryItem::firstOrCreate(
                ['sku' => 'DRG-RABV-003'],
                [
                    'name' => 'Verorab Rabies Vaccine (Human Diploid Cell)',
                    'generic_name' => 'Rabies Vaccine',
                    'brand_name' => 'Verorab',
                    'dosage_form_strength' => 'Freeze-dried, Single Dose + Diluent',
                    'regulatory_category' => 'GENERAL_RX',
                    'barcode_value' => 'DRG-RABV-003',
                    'gtin' => '03400987654321',
                    'category_id' => $pharmaCategory->id,
                    'unit' => 'vial',
                    'is_batch_tracked' => true,
                    'is_expiry_tracked' => true,
                    'temperature_classification' => 'refrigerated',
                    'storage_temp_min' => 2.00,
                    'storage_temp_max' => 8.00,
                    'fda_cpr_number' => 'BR-0941-05',
                    'unit_cost' => 1450.00,
                    'default_location_id' => $binCold->id,
                    'status' => 'active',
                ],
            );

            // LASA Pair: Dobutamine and Dopamine
            $dobutamine = InventoryItem::firstOrCreate(
                ['sku' => 'DRG-DOBU-004'],
                [
                    'name' => 'Dobutrex (Dobutamine HCl 12.5 mg/mL)',
                    'generic_name' => 'Dobutamine HCl',
                    'brand_name' => 'Dobutrex',
                    'dosage_form_strength' => '12.5 mg/mL, 20 mL Vial',
                    'regulatory_category' => 'HIGH_ALERT',
                    'lasa_group_code' => 'LASA-D',
                    'category_id' => $pharmaCategory->id,
                    'unit' => 'vial',
                    'is_batch_tracked' => true,
                    'is_expiry_tracked' => true,
                    'temperature_classification' => 'ambient',
                    'fda_cpr_number' => 'DRP-2210-03',
                    'unit_cost' => 320.00,
                    'default_location_id' => $binA01->id,
                    'status' => 'active',
                ],
            );

            $dopamine = InventoryItem::firstOrCreate(
                ['sku' => 'DRG-DOPA-005'],
                [
                    'name' => 'Intropin (Dopamine HCl 40 mg/mL)',
                    'generic_name' => 'Dopamine HCl',
                    'brand_name' => 'Intropin',
                    'dosage_form_strength' => '40 mg/mL, 5 mL Ampoule',
                    'regulatory_category' => 'HIGH_ALERT',
                    'lasa_group_code' => 'LASA-D',
                    'category_id' => $pharmaCategory->id,
                    'unit' => 'ampoule',
                    'is_batch_tracked' => true,
                    'is_expiry_tracked' => true,
                    'temperature_classification' => 'ambient',
                    'fda_cpr_number' => 'DRP-1102-04',
                    'unit_cost' => 110.00,
                    'status' => 'active',
                ],
            );

            // Surgical Consignment Implant: Coronary Stent
            $stent = InventoryItem::firstOrCreate(
                ['sku' => 'MED-STNT-006'],
                [
                    'name' => 'Xience Sierra Everolimus-Eluting Coronary Stent',
                    'generic_name' => 'Everolimus-Eluting Stent',
                    'brand_name' => 'Xience Sierra',
                    'dosage_form_strength' => '3.00 mm x 18 mm Rapid Exchange',
                    'regulatory_category' => 'CONSIGNMENT',
                    'is_consignment' => true,
                    'is_serial_tracked' => true,
                    'is_batch_tracked' => true,
                    'is_expiry_tracked' => true,
                    'category_id' => $surgicalCategory->id,
                    'unit' => 'unit',
                    'fda_cpr_number' => 'MDR-03819',
                    'unit_cost' => 45000.00,
                    'default_location_id' => $binConsignment->id,
                    'status' => 'active',
                ],
            );

            // 3. Batches & Initial Stock
            $batchSyringe = ItemBatch::firstOrCreate(
                ['item_id' => $syringe->id, 'batch_number' => 'SWS-DEMO-LOT-001'],
                [
                    'lot_number' => 'SWS-DEMO-LOT-001',
                    'expiry_date' => now()->addMonths(18)->startOfMonth(),
                    'received_at' => today(),
                    'unit_cost' => 275.00,
                    'initial_quantity' => 60,
                    'status' => 'active',
                ],
            );

            $batchMorphine = ItemBatch::firstOrCreate(
                ['item_id' => $morphine->id, 'batch_number' => 'LOT-MS-2601'],
                [
                    'lot_number' => 'LOT-MS-2601',
                    'expiry_date' => now()->addMonths(24)->endOfMonth(),
                    'received_at' => today(),
                    'unit_cost' => 85.00,
                    'initial_quantity' => 100,
                    'status' => 'active',
                ],
            );

            $batchRabies = ItemBatch::firstOrCreate(
                ['item_id' => $rabies->id, 'batch_number' => 'VR-7781'],
                [
                    'lot_number' => 'VR-7781',
                    'expiry_date' => now()->addMonths(14)->endOfMonth(),
                    'received_at' => today(),
                    'unit_cost' => 1450.00,
                    'initial_quantity' => 50,
                    'status' => 'active',
                ],
            );

            $batchDobutamine = ItemBatch::firstOrCreate(
                ['item_id' => $dobutamine->id, 'batch_number' => 'LOT-DOB-901'],
                [
                    'lot_number' => 'LOT-DOB-901',
                    'expiry_date' => now()->addMonths(12)->endOfMonth(),
                    'received_at' => today(),
                    'unit_cost' => 320.00,
                    'initial_quantity' => 40,
                    'status' => 'active',
                ],
            );

            $batchStent = ItemBatch::firstOrCreate(
                ['item_id' => $stent->id, 'batch_number' => 'LOT-STNT-041'],
                [
                    'lot_number' => 'LOT-STNT-041',
                    'expiry_date' => now()->addMonths(36)->endOfMonth(),
                    'received_at' => today(),
                    'unit_cost' => 45000.00,
                    'initial_quantity' => 5,
                    'status' => 'active',
                ],
            );

            // Balances
            $this->balance($syringe, $reserve, $batchSyringe, 60);
            $this->balance($morphine, $binVault, $batchMorphine, 100);
            $this->balance($rabies, $binCold, $batchRabies, 50);
            $this->balance($dobutamine, $binA01, $batchDobutamine, 40);
            $this->balance($stent, $binConsignment, $batchStent, 5);

            // Serial for stent
            InventorySerial::firstOrCreate(
                ['item_id' => $stent->id, 'serial_number' => 'SN-99401'],
                [
                    'item_batch_id' => $batchStent->id,
                    'storage_location_id' => $binConsignment->id,
                    'status' => 'available',
                ],
            );

            // 4. Seed Simulated Telemetry Stream
            IoTTelemetryLog::firstOrCreate(
                ['sensor_id' => 'IOT-TMP-COLD01', 'storage_location_id' => $binCold->id, 'recorded_at' => now()->subMinutes(10)->startOfMinute()],
                [
                    'temperature_celsius' => 4.50,
                    'relative_humidity_pct' => 52.10,
                    'excursion_status' => 'normal',
                    'resulting_event' => 'Steady-state cold chain compliance',
                ],
            );

            // 5. Seed DDRB Register Entry
            PdeaDangerousDrugsRegister::firstOrCreate(
                ['register_number' => 'DDRB-2026-DEMO-01'],
                [
                    'item_id' => $morphine->id,
                    'item_batch_id' => $batchMorphine->id,
                    'storage_location_id' => $binVault->id,
                    'quantity' => 100,
                    'running_balance' => 100,
                    'custodian_id' => $user->id,
                    'witness_pharmacist_id' => $user->id,
                    'witness_authenticated_at' => now(),
                    'notes' => 'Opening vault balance for demonstration of PDEA electronic register.',
                    'recorded_at' => now(),
                ],
            );

            // 6. Seed Replenishment Task
            app(WarehouseTaskService::class)->create([
                'task_type' => WarehouseTaskType::Replenishment,
                'priority' => 'normal',
                'source_location_id' => $reserve->id,
                'destination_location_id' => $pickFace->id,
                'item_id' => $syringe->id,
                'item_batch_id' => $batchSyringe->id,
                'requested_quantity' => 24,
                'idempotency_key' => 'smart-warehousing-demo-replenishment-v1',
                'recommendation_reason' => 'Restore the pick face toward its configured maximum.',
                'notes' => 'Scan reserve location, GS1 item/lot, then pick-face destination.',
            ], $user);

            // Sync totals
            $automation = app(InventoryAutomationService::class);
            $automation->syncItemTotals($syringe);
            $automation->syncItemTotals($morphine);
            $automation->syncItemTotals($rabies);
            $automation->syncItemTotals($dobutamine);
            $automation->syncItemTotals($dopamine);
            $automation->syncItemTotals($stent);
        });

        $this->command?->info('Smart Warehousing demo data seeded: topology, cold chain, narcotics vault, LASA pairs, and consignment implants.');
    }

    private function location(string $code, string $name, string $type, ?StorageLocation $parent, array $attributes = []): StorageLocation
    {
        return StorageLocation::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'barcode_value' => $code,
                'parent_id' => $parent?->id,
                'type' => $type,
                'storage_classification' => 'medical_supply',
                'temperature_classification' => 'ambient',
                'capacity_unit' => 'units',
                'status' => 'active',
                ...$attributes,
            ],
        );
    }

    private function balance(InventoryItem $item, StorageLocation $location, ItemBatch $batch, int $qty): void
    {
        ItemStockLevel::firstOrCreate(
            ['item_id' => $item->id, 'storage_location_id' => $location->id, 'item_batch_id' => $batch->id],
            ['quantity' => $qty, 'reserved_quantity' => 0, 'quarantined_quantity' => 0, 'blocked_quantity' => 0, 'in_transit_quantity' => 0],
        );

        StockMovement::firstOrCreate(
            [
                'item_id' => $item->id,
                'item_batch_id' => $batch->id,
                'remarks' => "Opening balance for {$item->name}",
            ],
            [
                'movement_type' => 'stock_in',
                'quantity' => $qty,
                'unit_cost' => $item->unit_cost,
                'to_location_id' => $location->id,
                'moved_at' => now(),
                'user_id' => User::query()->value('id'),
            ],
        );
    }
}
