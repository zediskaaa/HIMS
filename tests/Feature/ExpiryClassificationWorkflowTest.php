<?php

namespace Tests\Feature;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\InventoryReportService;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpiryClassificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expiry_boundaries_use_one_dynamic_classification(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        $expected = [
            91 => ItemBatch::EXPIRY_NORMAL,
            90 => ItemBatch::EXPIRY_SOON,
            75 => ItemBatch::EXPIRY_SOON,
            60 => ItemBatch::EXPIRY_SOON,
            45 => ItemBatch::EXPIRY_SOON,
            30 => ItemBatch::EXPIRY_CRITICAL,
            15 => ItemBatch::EXPIRY_CRITICAL,
            1 => ItemBatch::EXPIRY_CRITICAL,
            0 => ItemBatch::EXPIRY_EXPIRED,
            -1 => ItemBatch::EXPIRY_EXPIRED,
        ];

        foreach ($expected as $days => $classification) {
            $expiry = today()->addDays($days);

            $this->assertSame($days, ItemBatch::daysUntil($expiry));
            $this->assertSame($classification, ItemBatch::classifyExpiryDate($expiry));
        }

        $this->assertNull(ItemBatch::daysUntil(null));
        $this->assertNull(ItemBatch::classifyExpiryDate(null));
    }

    public function test_expiring_soon_filter_reports_all_active_batches_from_one_through_ninety_days(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        [$item, $location] = $this->stockContext();

        foreach ([91, 90, 75, 60, 45, 30, 15, 1, 0, -1] as $days) {
            $this->stockBatch($item, $location, "DAY-{$days}", today()->addDays($days));
        }
        $this->stockBatch($item, $location, 'NO-EXPIRY', null);

        $expiring = ItemBatch::query()->expiringSoon()->pluck('batch_number')->all();
        $expired = ItemBatch::query()->expired()->pluck('batch_number')->all();

        $this->assertEqualsCanonicalizing(
            ['DAY-90', 'DAY-75', 'DAY-60', 'DAY-45', 'DAY-30', 'DAY-15', 'DAY-1'],
            $expiring,
        );
        $this->assertEqualsCanonicalizing(['DAY-0', 'DAY--1'], $expired);
        $this->assertNotContains('DAY-91', $expiring);
        $this->assertNotContains('DAY-0', $expiring);
        $this->assertNotContains('DAY--1', $expiring);
        $this->assertNotContains('NO-EXPIRY', $expiring);

        $exposure = app(InventoryReportService::class)->expiryExposure();

        $this->assertSame(7, $exposure['expiring_soon']['batches']);
        $this->assertSame(3, $exposure['critical']['batches']);
        $this->assertSame(2, $exposure['expired']['batches']);
    }

    public function test_api_and_stock_alerts_share_the_same_expiry_statuses(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        [$item, $location] = $this->stockContext();
        $manager = User::factory()->inventoryManager()->create();
        $item->update(['expiry_alert_days' => 1]);

        $normal = $this->stockBatch($item, $location, 'NORMAL-91', today()->addDays(91));
        $soon = $this->stockBatch($item, $location, 'SOON-90', today()->addDays(90));
        $critical = $this->stockBatch($item, $location, 'CRITICAL-30', today()->addDays(30));
        $expired = $this->stockBatch($item, $location, 'EXPIRED-0', today());
        $this->stockBatch($item, $location, 'NO-EXPIRY', null);

        $alerts = app(StockAlertService::class);

        $this->assertSame(0, $alerts->evaluateBatchExpiry($normal));
        $this->assertSame(1, $alerts->evaluateBatchExpiry($soon));
        $this->assertSame(1, $alerts->evaluateBatchExpiry($critical));
        $this->assertSame(1, $alerts->evaluateBatchExpiry($expired));

        $this->assertDatabaseHas('stock_alerts', [
            'item_batch_id' => $soon->id,
            'type' => AlertType::ExpiringSoon->value,
            'severity' => AlertSeverity::Warning->value,
        ]);
        $this->assertDatabaseHas('stock_alerts', [
            'item_batch_id' => $critical->id,
            'type' => AlertType::ExpiringSoon->value,
            'severity' => AlertSeverity::Critical->value,
        ]);
        $this->assertDatabaseHas('stock_alerts', [
            'item_batch_id' => $expired->id,
            'type' => AlertType::Expired->value,
            'severity' => AlertSeverity::Critical->value,
        ]);

        // Re-evaluation keeps the same alert but escalates its severity and
        // notification when the live date crosses into the 30-day window.
        $soon->update(['expiry_date' => today()->addDays(30)]);
        $this->assertSame(0, $alerts->evaluateBatchExpiry($soon->fresh()));
        $this->assertDatabaseHas('stock_alerts', [
            'item_batch_id' => $soon->id,
            'type' => AlertType::ExpiringSoon->value,
            'severity' => AlertSeverity::Critical->value,
        ]);
        $this->assertSame(4, $manager->fresh()->notifications()->count());

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/inventory-items');

        $response->assertOk();
        $batches = collect($response->json('data.0.expiry_batches'))->keyBy('batch_number');

        $this->assertSame(ItemBatch::EXPIRY_NORMAL, $batches['NORMAL-91']['expiry_status']);
        $this->assertSame(ItemBatch::EXPIRY_CRITICAL, $batches['SOON-90']['expiry_status']);
        $this->assertSame(ItemBatch::EXPIRY_CRITICAL, $batches['CRITICAL-30']['expiry_status']);
        $this->assertSame(ItemBatch::EXPIRY_EXPIRED, $batches['EXPIRED-0']['expiry_status']);
        $this->assertFalse($batches->has('NO-EXPIRY'));
    }

    public function test_alerts_screen_describes_the_shared_ninety_day_window_and_separate_expired_group(): void
    {
        $this->actingAs(User::factory()->inventoryManager()->create())
            ->get(route('inventory.alerts'))
            ->assertOk()
            ->assertSee('1&ndash;90 days remaining, including critical', false)
            ->assertSee('id="stat-expired"', false)
            ->assertSee("['expiring_soon', 'critical'].includes(batch.expiry_status)", false)
            ->assertSee("batch.expiry_status === 'expired'", false);
    }

    public function test_dashboard_expiry_tile_counts_only_stocked_batches_with_one_to_ninety_days_remaining(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        [$item, $location] = $this->stockContext();
        $manager = User::factory()->inventoryManager()->create();

        $this->stockBatch($item, $location, 'SOON-90', today()->addDays(90));
        $this->stockBatch($item, $location, 'CRITICAL-30', today()->addDays(30));
        $this->stockBatch($item, $location, 'EXPIRED-0', today());
        $this->stockBatch($item, $location, 'NORMAL-91', today()->addDays(91));
        $this->stockBatch($item, $location, 'NO-EXPIRY', null);

        $this->actingAs($manager)
            ->get('/dashboard/live')
            ->assertOk()
            ->assertJsonPath('expiringSoonCount', 2)
            ->assertJsonPath('criticalExpiryCount', 1);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Expiring soon')
            ->assertSee('1 critical / near expiry')
            ->assertDontSee('Inventory conditions needing action');
    }

    /** @return array{InventoryItem, StorageLocation} */
    private function stockContext(): array
    {
        $item = InventoryItem::create([
            'name' => 'Expiry Boundary Medicine',
            'sku' => 'EXP-BOUNDARY',
            'unit' => 'vial',
            'quantity_on_hand' => 11,
            'reorder_level' => 0,
            'unit_cost' => 10,
            'status' => 'active',
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
        ]);
        $location = StorageLocation::create([
            'name' => 'Expiry Test Store',
            'code' => 'EXP-TEST',
            'status' => 'active',
        ]);

        return [$item, $location];
    }

    private function stockBatch(
        InventoryItem $item,
        StorageLocation $location,
        string $number,
        ?Carbon $expiryDate,
    ): ItemBatch {
        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => $number,
            'expiry_date' => $expiryDate?->toDateString(),
            'received_at' => today()->subMonth(),
            'unit_cost' => 10,
            'initial_quantity' => 1,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'item_batch_id' => $batch->id,
            'quantity' => 1,
            'reserved_quantity' => 0,
        ]);

        return $batch;
    }
}
