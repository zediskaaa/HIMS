<?php

namespace Tests\Feature;

use App\Enums\DemandTrend;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\DemandPlan;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The forecast is a moving average over recorded consumption, so every figure
 * on the screen traces back to stock_movements rows. These tests pin the
 * arithmetic with quantities chosen to divide evenly, so an expected value can
 * be read off the formula rather than copied from a previous run:
 *
 *   average daily usage = consumed in window / days in window
 *   projected usage     = max(average daily usage, consumed in later half / half
 *                          the window)
 *   safety stock        = average daily usage x buffer days
 *   reorder point       = (average daily usage x lead time) + safety stock
 *   suggested order     = (projected usage x horizon) + safety stock - on hand
 *
 * The projection is the only figure the later half feeds into, so a fixture
 * wants its movements clear of the midpoint if it expects a round number. Where
 * one does sit on the midpoint it counts as earlier: the service reads `now()`
 * a moment after the fixture wrote its rows, so the midpoint it derives is
 * always fractionally later than the movement.
 *
 * Which movement types count as demand is the other half of the module's
 * correctness, and is asserted separately below.
 */
class DemandForecastTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DemandForecastService
    {
        return app(DemandForecastService::class);
    }

    private function item(int $onHand = 0, int $reorderLevel = 20): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PHARMA-PARA-500',
            'unit' => 'bottle',
            'quantity_on_hand' => $onHand,
            'reorder_level' => $reorderLevel,
            'unit_cost' => 8.50,
            'status' => 'active',
        ]);
    }

    /**
     * Backdated history, written straight to the table. Going through
     * InventoryAutomationService would also move stock, and these tests are
     * about reading history rather than creating it.
     */
    private function consume(
        InventoryItem $item,
        int $quantity,
        int $daysAgo,
        MovementType $type = MovementType::StockOut
    ): StockMovement {
        return StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'moved_at' => now()->subDays($daysAgo),
        ]);
    }

    // ------------------------------------------------------------- arithmetic

    /**
     * 90 units over a 90-day window is 1/day, which makes every downstream
     * figure checkable by eye.
     */
    public function test_it_derives_every_figure_from_consumption_history(): void
    {
        $item = $this->item(onHand: 40);

        // 9 issues of 10 units, spread inside the 90-day window.
        foreach (range(1, 9) as $i) {
            $this->consume($item, 10, $i * 9);
        }

        $forecast = $this->service()->forecast($item, analysisDays: 90, forecastDays: 30, leadTimeDays: 7);

        $this->assertSame(90, $forecast['historical_usage']);
        $this->assertSame(1.0, (float) $forecast['average_daily_usage']);

        // The later half of this spread holds 40 units — 0.89/day, under the
        // 1/day average — so the projection stays on the average.
        $this->assertSame(30, $forecast['upcoming_need']);

        // 1/day x 7 buffer days.
        $this->assertSame(7, $forecast['safety_stock']);

        // (1/day x 7 lead) + 7 safety.
        $this->assertSame(14, $forecast['reorder_point']);

        // 30 need + 7 safety - 40 on hand = -3, floored at 0.
        $this->assertSame(0, $forecast['suggested_order_quantity']);

        // 40 on hand at 1/day.
        $this->assertSame(40, $forecast['days_of_cover']);

        // 40 on hand is above the reorder point of 14.
        $this->assertFalse($forecast['needs_reorder']);
        $this->assertSame(9, $forecast['movement_count']);
    }

    public function test_a_short_cover_produces_a_suggested_order(): void
    {
        $item = $this->item(onHand: 5);

        foreach (range(1, 9) as $i) {
            $this->consume($item, 20, $i * 9);
        }

        // 180 units / 90 days = 2/day.
        $forecast = $this->service()->forecast($item, analysisDays: 90, forecastDays: 30, leadTimeDays: 7);

        $this->assertSame(2.0, (float) $forecast['average_daily_usage']);
        $this->assertSame(60, $forecast['upcoming_need']);      // 2 x 30; 80/45 = 1.78, under the 2/day average
        $this->assertSame(14, $forecast['safety_stock']);       // 2 x 7
        $this->assertSame(28, $forecast['reorder_point']);      // (2 x 7) + 14
        $this->assertSame(69, $forecast['suggested_order_quantity']); // 60 + 14 - 5
        $this->assertSame(2, $forecast['days_of_cover']);       // floor(5 / 2)

        $this->assertTrue($forecast['needs_reorder']);
        $this->assertStringContainsString('at or below', $forecast['trigger_reason']);
    }

    /**
     * Null days-of-cover is deliberate: an item nobody has consumed is not
     * counting down to anything, which is a different statement from
     * "0 days left" and must not be rendered as one.
     */
    public function test_an_item_with_no_consumption_reports_no_usage_rather_than_zero_cover(): void
    {
        $forecast = $this->service()->forecast($this->item(onHand: 100));

        $this->assertSame(0, $forecast['historical_usage']);
        $this->assertSame(0.0, (float) $forecast['average_daily_usage']);
        $this->assertNull($forecast['days_of_cover']);
        $this->assertSame(0, $forecast['suggested_order_quantity']);
        $this->assertFalse($forecast['needs_reorder']);
        $this->assertSame(DemandTrend::Insufficient, $forecast['trend']);
        $this->assertSame('No recorded consumption in the analysis window.', $forecast['trigger_reason']);
    }

    /**
     * Zero usage means no reorder signal even at zero stock — otherwise every
     * dormant item in the catalogue would demand an order.
     */
    public function test_out_of_stock_with_no_usage_still_suggests_nothing(): void
    {
        $forecast = $this->service()->forecast($this->item(onHand: 0));

        $this->assertFalse($forecast['needs_reorder']);
        $this->assertSame(0, $forecast['suggested_order_quantity']);
    }

    public function test_consumption_outside_the_window_is_ignored(): void
    {
        $item = $this->item(onHand: 50);

        $this->consume($item, 100, daysAgo: 200);  // well outside
        $this->consume($item, 30, daysAgo: 10);    // inside

        $forecast = $this->service()->forecast($item, analysisDays: 90);

        $this->assertSame(30, $forecast['historical_usage']);
        $this->assertSame(1, $forecast['movement_count']);
    }

    /**
     * The same history over a shorter window reads as a higher daily rate,
     * which is what makes the window selector on the screen meaningful.
     */
    public function test_the_analysis_window_changes_the_average(): void
    {
        $item = $this->item(onHand: 100);

        // Kept clear of the 30-day edge: `now()` is re-evaluated inside the
        // service a moment after these rows are written, so a movement sitting
        // exactly on the boundary would fall in or out by microseconds.
        foreach (range(1, 6) as $i) {
            $this->consume($item, 15, $i * 4);
        }

        // 90 units, all inside the last 30 days.
        $this->assertSame(3.0, (float) $this->service()->forecast($item, analysisDays: 30)['average_daily_usage']);
        $this->assertSame(1.0, (float) $this->service()->forecast($item, analysisDays: 90)['average_daily_usage']);
    }

    // -------------------------------------------------- what counts as demand

    /**
     * The single most consequential rule in the module. A transfer relocates
     * stock without anyone using it, a disposal is waste, a return goes back to
     * the vendor and an adjustment corrects a miscount — counting any of them
     * would inflate the forecast and order stock nobody needs.
     */
    public function test_only_stock_out_and_issuance_count_as_demand(): void
    {
        $item = $this->item(onHand: 100);

        $this->consume($item, 20, 10, MovementType::StockOut);
        $this->consume($item, 25, 12, MovementType::Issuance);

        foreach ([
            MovementType::StockIn,
            MovementType::Transfer,
            MovementType::Adjustment,
            MovementType::Disposal,
            MovementType::ReturnToSupplier,
        ] as $ignored) {
            $this->consume($item, 500, 14, $ignored);
        }

        $forecast = $this->service()->forecast($item, analysisDays: 90);

        // 20 + 25 only. The five 500-unit rows are in the table but not in the
        // forecast — if any leaked in, this would read 2500 higher.
        $this->assertSame(45, $forecast['historical_usage']);
        $this->assertSame(2, $forecast['movement_count']);
        $this->assertSame(7, StockMovement::where('item_id', $item->id)->count());
    }

    public function test_the_enum_agrees_with_that_list(): void
    {
        $this->assertSame(['stock_out', 'issuance'], MovementType::consumptionValues());

        foreach (MovementType::cases() as $type) {
            $expected = in_array($type, [MovementType::StockOut, MovementType::Issuance], true);
            $this->assertSame($expected, $type->isConsumption(), $type->value.' consumption flag');
        }
    }

    public function test_another_items_consumption_does_not_leak_in(): void
    {
        $item = $this->item(onHand: 50);
        $other = InventoryItem::create([
            'name' => 'Surgical Gloves (Large)',
            'sku' => 'PPE-GLOVE-L',
            'unit' => 'box',
            'quantity_on_hand' => 10,
            'reorder_level' => 5,
            'status' => 'active',
        ]);

        $this->consume($item, 30, 10);
        $this->consume($other, 900, 10);

        $this->assertSame(30, $this->service()->forecast($item)['historical_usage']);
    }

    // ------------------------------------------------------------------ trends

    public function test_a_rising_pattern_reads_as_increasing(): void
    {
        $item = $this->item(onHand: 100);

        // Earlier half light, later half heavy.
        $this->consume($item, 5, 80);
        $this->consume($item, 5, 70);
        $this->consume($item, 40, 20);
        $this->consume($item, 40, 10);

        $this->assertSame(DemandTrend::Increasing, $this->service()->forecast($item, analysisDays: 90)['trend']);
    }

    public function test_a_tapering_pattern_reads_as_decreasing(): void
    {
        $item = $this->item(onHand: 100);

        $this->consume($item, 40, 80);
        $this->consume($item, 40, 70);
        $this->consume($item, 5, 20);
        $this->consume($item, 5, 10);

        $this->assertSame(DemandTrend::Decreasing, $this->service()->forecast($item, analysisDays: 90)['trend']);
    }

    /**
     * Within 15% either way is called stable — a hospital's week-to-week
     * variation should not be reported as a trend.
     */
    public function test_an_even_pattern_reads_as_stable(): void
    {
        $item = $this->item(onHand: 100);

        $this->consume($item, 20, 80);
        $this->consume($item, 20, 70);
        $this->consume($item, 20, 20);
        $this->consume($item, 20, 10);

        $this->assertSame(DemandTrend::Stable, $this->service()->forecast($item, analysisDays: 90)['trend']);
    }

    public function test_a_single_movement_is_not_enough_to_call_a_trend(): void
    {
        $item = $this->item(onHand: 100);
        $this->consume($item, 30, 10);

        $forecast = $this->service()->forecast($item, analysisDays: 90);

        $this->assertSame(DemandTrend::Insufficient, $forecast['trend']);
        // The average is still computed — only the trend is withheld.
        $this->assertSame(30, $forecast['historical_usage']);
    }

    // ------------------------------------------------------------------- plans

    public function test_saving_a_plan_snapshots_the_computed_figures(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item(onHand: 5);

        foreach (range(1, 9) as $i) {
            $this->consume($item, 20, $i * 9);
        }

        $this->actingAs($manager)->post('/inventory/demand-forecast', [
            'item_id' => $item->id,
            'analysis_days' => 90,
            'forecast_days' => 30,
            'lead_time_days' => 7,
            'notes' => 'Ahead of the flu season.',
        ])->assertRedirect('/inventory/demand-forecast');

        $plan = DemandPlan::where('item_id', $item->id)->firstOrFail();

        $this->assertSame(2.0, (float) $plan->average_daily_usage);
        $this->assertSame(60, $plan->upcoming_need);
        $this->assertSame(14, $plan->safety_stock);
        $this->assertSame(28, $plan->reorder_point);
        $this->assertSame(69, $plan->suggested_order_quantity);
        $this->assertSame(5, $plan->current_stock);
        $this->assertSame(180, $plan->historical_usage);
        $this->assertSame('draft', $plan->status);
        $this->assertSame('Ahead of the flu season.', $plan->notes);
        $this->assertSame($manager->id, $plan->generated_by);
        $this->assertNotNull($plan->generated_at);
        $this->assertStringStartsWith('PLAN-', $plan->plan_number);
    }

    /**
     * The figures are recalculated on save rather than read from the posted
     * form, so a page left open does not freeze stale numbers into a plan.
     */
    public function test_posted_figures_cannot_override_the_computed_ones(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item(onHand: 5);

        foreach (range(1, 9) as $i) {
            $this->consume($item, 20, $i * 9);
        }

        $this->actingAs($manager)->post('/inventory/demand-forecast', [
            'item_id' => $item->id,
            'analysis_days' => 90,
            'forecast_days' => 30,
            'lead_time_days' => 7,
            // Nonsense a tampered form might send.
            'suggested_order_quantity' => 99999,
            'average_daily_usage' => 1234,
            'current_stock' => 7777,
        ])->assertRedirect('/inventory/demand-forecast');

        $plan = DemandPlan::where('item_id', $item->id)->firstOrFail();

        $this->assertSame(69, $plan->suggested_order_quantity);
        $this->assertSame(2.0, (float) $plan->average_daily_usage);
        $this->assertSame(5, $plan->current_stock);
    }

    public function test_two_plans_saved_together_get_distinct_numbers(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item(onHand: 5);
        $this->consume($item, 20, 10);

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($manager)->post('/inventory/demand-forecast', [
                'item_id' => $item->id,
            ])->assertRedirect('/inventory/demand-forecast');
        }

        $numbers = DemandPlan::pluck('plan_number');

        $this->assertCount(2, $numbers);
        $this->assertCount(2, $numbers->unique(), 'Plan numbers collided.');
    }

    public function test_a_plan_needs_a_real_item_and_sane_windows(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($manager)->post('/inventory/demand-forecast', [
            'item_id' => 99999,
        ])->assertSessionHasErrors('item_id');

        $item = $this->item();

        $this->actingAs($manager)->post('/inventory/demand-forecast', [
            'item_id' => $item->id,
            'analysis_days' => 3,
        ])->assertSessionHasErrors('analysis_days');

        $this->actingAs($manager)->post('/inventory/demand-forecast', [
            'item_id' => $item->id,
            'lead_time_days' => 500,
        ])->assertSessionHasErrors('lead_time_days');

        $this->assertDatabaseCount('demand_plans', 0);
    }

    // ------------------------------------------------------- access and screen

    /**
     * Reading a forecast is not the same as committing to an order: any signed
     * in user may look, but saving a plan needs generate_forecasts.
     */
    public function test_any_signed_in_user_can_read_the_forecast(): void
    {
        $viewer = User::factory()->role(UserRole::Viewer)->create();

        $this->actingAs($viewer)->get('/inventory/demand-forecast')->assertStatus(200);
    }

    public function test_saving_a_plan_requires_the_forecast_permission(): void
    {
        $item = $this->item(onHand: 5);
        $this->consume($item, 20, 10);

        foreach ([UserRole::Viewer, UserRole::WarehouseStaff, UserRole::PharmacyStaff] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post('/inventory/demand-forecast', ['item_id' => $item->id])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('demand_plans', 0);

        // The two roles that do hold it.
        foreach ([UserRole::InventoryManager, UserRole::Administrator] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post('/inventory/demand-forecast', ['item_id' => $item->id])
                ->assertRedirect('/inventory/demand-forecast');
        }

        $this->assertDatabaseCount('demand_plans', 2);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/inventory/demand-forecast')->assertRedirect('/login');
    }

    /**
     * A nonsense window in the query string comes from a select on the page,
     * so it is clamped into range rather than allowed to 500 the screen.
     */
    public function test_an_out_of_range_window_is_clamped_not_rejected(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $this->actingAs($user)
            ->get('/inventory/demand-forecast?analysis_days=99999&forecast_days=0')
            ->assertStatus(200);

        $this->actingAs($user)
            ->get('/inventory/demand-forecast?analysis_days=banana')
            ->assertStatus(200);
    }

    public function test_the_screen_lists_items_worst_cover_first(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $comfortable = $this->item(onHand: 500);
        $this->consume($comfortable, 90, 10);

        $critical = InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'PPE-MASK-N95',
            'unit' => 'box',
            'quantity_on_hand' => 2,
            'reorder_level' => 50,
            'status' => 'active',
        ]);
        $this->consume($critical, 90, 10);

        $forecasts = $this->service()->forecastAll(90, 30);

        $this->assertSame($critical->id, $forecasts->first()['item_id']);
        $this->assertSame($comfortable->id, $forecasts->last()['item_id']);

        $this->actingAs($user)->get('/inventory/demand-forecast')
            ->assertStatus(200)
            ->assertSee('N95 Respirator Mask')
            ->assertSee('Paracetamol 500mg Tablets');
    }

    /**
     * Items with no usage sort last rather than first: null cover must not be
     * treated as zero, or dormant stock would crowd out the real shortages.
     */
    public function test_items_with_no_usage_sort_after_items_with_shortages(): void
    {
        $dormant = $this->item(onHand: 0);

        $active = InventoryItem::create([
            'name' => 'Surgical Gloves (Large)',
            'sku' => 'PPE-GLOVE-L',
            'unit' => 'box',
            'quantity_on_hand' => 3,
            'reorder_level' => 30,
            'status' => 'active',
        ]);
        $this->consume($active, 90, 10);

        $forecasts = $this->service()->forecastAll(90, 30);

        $this->assertSame($active->id, $forecasts->first()['item_id']);
        $this->assertSame($dormant->id, $forecasts->last()['item_id']);
        $this->assertNull($forecasts->last()['days_of_cover']);
    }

    public function test_the_retired_hand_entry_route_is_gone(): void
    {
        $this->assertFalse(
            app('router')->getRoutes()->hasNamedRoute('inventory.purchases.plans.store'),
            'The hand-entry demand plan route should have been removed.'
        );
    }
}
