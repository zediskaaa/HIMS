<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiDemandForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.gemini.key', 'synthetic-test-key');
        config()->set('services.gemini.model', 'gemini-test-flash');
        config()->set('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
        config()->set('services.gemini.forecast_cache_minutes', 60);
    }

    public function test_authorized_user_generates_a_validated_forecast_from_real_inventory_history(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 15, 18);
        $this->consume($item, 30, 8);
        $this->consume($item, 25, 2);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'items' => [[
                                    'item_id' => $item->id,
                                    'predicted_demand' => 42,
                                    'risk_level' => 'high',
                                    'reorder_priority' => 'high',
                                    'recommended_reorder_quantity' => 37,
                                    'projected_stock_status' => 'low_stock',
                                    'demand_trend' => 'increasing',
                                    'confidence' => 'medium',
                                    'explanation' => 'Recent consumption is rising while available stock is low.',
                                ]],
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 30,
            ])
            ->assertRedirect(route('inventory.demand-forecast', [
                'analysis_days' => 90,
                'forecast_days' => 30,
            ]));

        $cached = Cache::get('demand-forecast:v2:90:30');
        $this->assertSame('ai', $cached['source']);
        $response->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($item): bool {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text');

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-flash:generateContent'
                && $request->hasHeader('X-goog-api-key', 'synthetic-test-key')
                && str_contains($prompt, $item->sku)
                && str_contains($prompt, 'historical_consumption')
                && str_contains($prompt, 'consumption_series')
                && data_get($request->data(), 'generationConfig.responseMimeType') === 'application/json';
        });

        $this->assertSame('ai', $cached['source']);
        $this->assertSame($item->id, $cached['items'][0]['item_id']);
        $this->assertSame('Bandages', $cached['items'][0]['item_name']);
        $this->assertSame(37, $cached['items'][0]['recommended_reorder_quantity']);
        $this->assertSame(70, $cached['items'][0]['historical_consumption']);
        $this->assertSame(0.78, $cached['items'][0]['average_daily_consumption']);
        $this->assertSame(70, collect($cached['items'][0]['historical_series'])->sum('quantity'));
        $this->assertSame(90, collect($cached['items'][0]['historical_series'])->sum('days'));
        $this->assertSame(42, collect($cached['items'][0]['forecast_series'])->sum('quantity'));
        $this->assertSame(30, collect($cached['items'][0]['forecast_series'])->sum('days'));
        // The horizon is drawn flat rather than ramping across it: 42 units over
        // 30 days is 1.4/day, and every bucket carries that rate. The ramp this
        // replaces opened below the history it was meant to continue.
        $rates = collect($cached['items'][0]['forecast_series'])
            ->map(fn (array $bucket): float => $bucket['quantity'] / $bucket['days']);

        foreach ($rates as $rate) {
            $this->assertEqualsWithDelta(1.4, $rate, 0.2);
        }

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::GeneratedDemandForecast->value,
            'outcome' => 'success',
        ]);

        $this->actingAs($manager)->get(route('inventory.demand-forecast'))
            ->assertOk()
            ->assertSee('AI Forecast')
            ->assertSee('Bandages')
            ->assertSee('Recent consumption is rising')
            ->assertSee('Forecast scope')
            ->assertSee('Historical window')
            ->assertSee('Statistical Forecast by Item')
            ->assertDontSee('Capital at Risk')
            ->assertDontSee('What-If Simulation');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('AI-Based Stock Demand Forecasting')
            ->assertSee('Bandages');
    }

    public function test_dashboard_forecast_redesign_renders_real_controls_modal_and_ajax_results(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $category = ItemCategory::create([
            'name' => 'Clinical Consumables',
            'code' => 'CLIN-CONS',
            'is_active' => true,
        ]);
        $item = $this->item();
        $item->update(['category_id' => $category->id]);
        $this->consume($item, 18, 12);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['items' => [[
                            'item_id' => $item->id,
                            'predicted_demand' => 24,
                            'risk_level' => 'high',
                            'reorder_priority' => 'high',
                            'recommended_reorder_quantity' => 19,
                            'projected_stock_status' => 'low_stock',
                            'demand_trend' => 'increasing',
                            'confidence' => 'low',
                            'explanation' => 'Recorded consumption is rising above available stock.',
                        ]]], JSON_THROW_ON_ERROR),
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 30,
                'return_to' => 'dashboard',
            ])
            ->assertOk()
            ->assertJsonPath('forecast.source', 'ai')
            ->assertJsonPath('forecast.items.0.item_name', 'Bandages')
            ->assertJsonPath('forecast.items.0.historical_consumption', 18)
            ->assertJsonPath('forecast.items.0.predicted_demand', 24)
            ->assertJsonCount(6, 'forecast.items.0.historical_series')
            ->assertJsonCount(5, 'forecast.items.0.forecast_series');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Historical baseline and AI forecast in one decision view.')
            ->assertSee('Demand vs Forecast')
            ->assertSee('Select Item')
            ->assertSee('All Inventory Items (Overall Hospital Demand)')
            ->assertSee('More filters')
            ->assertSee('Estimated Stock Risk')
            ->assertSee('Current Stock')
            ->assertSee('Projected at-risk items')
            ->assertSee('Forecast confidence')
            ->assertSee('(review needed)')
            ->assertSee('Low confidence: risk is preliminary - verify movement history before acting.')
            ->assertSee('Reorder units')
            ->assertSee('Forecast starts')
            ->assertSee('Units/day')
            ->assertSee('Period total:')
            ->assertSee('Inventory attention')
            ->assertSee('AI forecast insight')
            ->assertSee('Forecast details')
            ->assertSee('Historical baseline')
            ->assertSee('AI forecast')
            ->assertSee('data-chart-inspector', false)
            ->assertSee('lg:h-72 xl:h-80', false)
            ->assertSee('Hover, drag, or use the arrow keys to inspect either line.')
            ->assertSee('<details hidden', false)
            ->assertSee('data-dashboard-secondary', false)
            ->assertSee('Clinical Consumables')
            ->assertSee('Full Demand Forecast')
            ->assertSee('Explanation')
            ->assertSee('data-dashboard-alerts', false)
            ->assertSee('demandForecastDashboard(', false)
            ->assertSee('x-on:click="toggleSeries(\'actual\')"', false)
            ->assertSee('x-on:click="toggleSeries(\'forecast\')"', false)
            ->assertSee('seriesPath(historicalBaselinePoints())', false)
            ->assertSee('seriesPath(forecastPoints())', false)
            ->assertSee('lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]', false)
            ->assertSee('x-ref="trackedItemsTile"', false)
            ->assertSee('x-ref="inventoryValueTile"', false)
            ->assertSee('x-on:submit.prevent="generateForecast()"', false)
            ->assertSee('data-no-loading', false)
            ->assertDontSee('Forecasted inventory items')
            ->assertDontSee('forecastPointsWithTransition()', false)
            ->assertDontSee('Price Recommendations');
    }

    public function test_dashboard_ajax_forecast_returns_a_safe_empty_inventory_error(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        Http::fake();

        $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 30,
                'return_to' => 'dashboard',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No active inventory items are available to forecast.');

        Http::assertNothingSent();
    }

    public function test_chart_series_preserves_partial_bucket_durations(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 14, 4);

        Http::fake(['*' => Http::response([], 503)]);

        $response = $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 7,
                'return_to' => 'dashboard',
            ])
            ->assertOk();

        $historical = collect($response->json('forecast.items.0.historical_series'));
        $forecast = collect($response->json('forecast.items.0.forecast_series'));

        $this->assertSame(90, $historical->sum('days'));
        $this->assertSame([4, 3], $forecast->pluck('days')->all());
        $this->assertSame(7, $forecast->sum('days'));
        $this->assertSame(now()->addDay()->toDateString(), $forecast->first()['period_start']);
    }

    /**
     * The window is split into six buckets, and ninety days does not divide by
     * six. Rounding the width up used to leave a stub at the end of the window,
     * and a stub that short catches one movement or none at all — so the last
     * point on the chart drew demand collapsing to zero days after the most
     * recent movement had been recorded.
     */
    public function test_every_historical_bucket_covers_a_full_share_of_the_window(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 40, 2);

        Http::fake(['*' => Http::response([], 503)]);

        $response = $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 30,
                'return_to' => 'dashboard',
            ])
            ->assertOk();

        $historical = collect($response->json('forecast.items.0.historical_series'));

        $this->assertSame(
            [15, 15, 15, 15, 15, 15],
            $historical->pluck('days')->all()
        );
        $this->assertSame(90, $historical->sum('days'));

        // The most recent movement belongs to the final bucket rather than
        // falling outside a stub that closed before it.
        $this->assertSame(40, $historical->last()['quantity']);
    }

    public function test_the_forecast_series_stays_at_or_above_the_recorded_average_rate(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 70, 80);
        $this->consume($item, 12, 20);
        $this->consume($item, 8, 5);

        Http::fake(['*' => Http::response([], 503)]);

        $response = $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 30,
                'return_to' => 'dashboard',
            ])
            ->assertOk();

        $series = collect($response->json('forecast.items.0.forecast_series'));
        $average = (float) $response->json('forecast.items.0.average_daily_consumption');

        // The figures below are the statistical projection's, so the fixture
        // needs the fallback path to have been taken.
        $this->assertSame('statistical', $response->json('forecast.source'));

        // 90 units over the 90-day window, so the baseline sits at 1/day while
        // the later half has fallen to 20/45 = 0.44/day.
        $this->assertSame(1.0, $average);
        $this->assertSame(30, $series->sum('quantity'));
        $this->assertSame(30, $series->sum('days'));

        // The projection is floored at the average, so no bucket draws the
        // forecast under the baseline the screen sets beside it — even on an
        // item that is tapering off.
        foreach ($series as $bucket) {
            $this->assertGreaterThanOrEqual($average, $bucket['quantity'] / $bucket['days']);
        }

        // With nothing to climb to, the line sits flat on that baseline.
        $this->assertSame(1.0, round($series->first()['quantity'] / $series->first()['days'], 2));
    }

    /**
     * The forecast has to pick up where the recorded history stops. Opening at
     * the window average instead starts the line below the last recorded point
     * whenever demand is rising, which reads on screen as the forecast dropping
     * away from the history it is meant to continue.
     */
    public function test_the_forecast_series_opens_level_with_the_rate_the_history_ended_on(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 20, 80);
        $this->consume($item, 50, 20);
        $this->consume($item, 50, 5);

        Http::fake(['*' => Http::response([], 503)]);

        $response = $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 30,
                'return_to' => 'dashboard',
            ])
            ->assertOk();

        $series = collect($response->json('forecast.items.0.forecast_series'));
        $rates = $series->map(fn (array $bucket): float => $bucket['quantity'] / $bucket['days']);

        // 120 units over ninety days is 1.33/day, but 100 of them fall in the
        // later half, so the horizon is projected at 100/45 = 2.22/day.
        $this->assertSame(1.33, (float) $response->json('forecast.items.0.average_daily_consumption'));
        $this->assertSame(67, $series->sum('quantity'));

        // Every bucket carries that projected rate, the first one included. The
        // line is level from where the history stopped rather than climbing to
        // it from the long-run average underneath.
        foreach ($rates as $rate) {
            $this->assertEqualsWithDelta(2.22, $rate, 0.2);
        }

        $this->assertGreaterThan(1.33, $rates->first());
    }

    public function test_dashboard_chart_scales_units_per_day_from_bucket_rates(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertIsString($script);
        $this->assertIsString($view);
        $this->assertStringContainsString(
            'hist.map((point) => Number(point.rate || 0))',
            $script,
        );
        $this->assertStringContainsString(
            'fore.map((point) => Number(point.rate || 0))',
            $script,
        );
        $this->assertSame(2, substr_count($script, 'y: this.chartY(rate),'));
        $this->assertStringContainsString('y: this.chartY(baselineRate),', $script);
        $this->assertStringContainsString('this.niceAxisStep(rawMaximum / 4) * 4', $script);
        $this->assertStringContainsString('Array.from({ length: 5 }', $script);
        $this->assertSame(5, substr_count($view, 'data-chart-grid-line'));
        $this->assertStringNotContainsString('y: this.chartY(point.quantity),', $script);
    }

    public function test_missing_api_key_uses_a_clearly_labeled_statistical_fallback(): void
    {
        config()->set('services.gemini.key', null);
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 60, 5);

        Http::fake();

        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertRedirect()
            ->assertSessionHas('info');

        Http::assertNothingSent();
        $cached = Cache::get('demand-forecast:v2:90:30');
        $this->assertSame('statistical', $cached['source']);
        $this->assertSame('Statistical Forecast', $cached['source_label']);
        $this->assertNotEmpty($cached['notice']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::FailedDemandForecast->value,
            'outcome' => 'failure',
        ]);

        $this->actingAs($manager)->get(route('inventory.demand-forecast'))
            ->assertOk()
            ->assertSee('Statistical Forecast')
            ->assertSee('Statistical fallback active');
    }

    public function test_a_second_successful_generation_is_audited_as_a_refresh(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $responseItem = [
            'item_id' => $item->id,
            'predicted_demand' => 0,
            'risk_level' => 'low',
            'reorder_priority' => 'none',
            'recommended_reorder_quantity' => 0,
            'projected_stock_status' => 'uncertain',
            'demand_trend' => 'insufficient',
            'confidence' => 'low',
            'explanation' => 'There is not enough recorded consumption for a confident forecast.',
        ];

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode(['items' => [$responseItem]], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($manager)->post(route('inventory.demand-forecast.refresh'))->assertSessionHas('success');
        $this->actingAs($manager)->post(route('inventory.demand-forecast.refresh'))->assertSessionHas('success');

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::GeneratedDemandForecast->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::RefreshedDemandForecast->value]);
    }

    public function test_transient_gemini_failure_is_retried_before_using_fallback(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $responseItem = [
            'item_id' => $item->id,
            'predicted_demand' => 12,
            'risk_level' => 'medium',
            'reorder_priority' => 'medium',
            'recommended_reorder_quantity' => 7,
            'projected_stock_status' => 'low_stock',
            'demand_trend' => 'stable',
            'confidence' => 'low',
            'explanation' => 'Limited history indicates possible low stock.',
        ];

        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode(['items' => [$responseItem]], JSON_THROW_ON_ERROR),
                        ]]],
                    ]],
                ]),
        ]);

        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertSessionHas('success');

        Http::assertSentCount(2);
        $this->assertSame('ai', Cache::get('demand-forecast:v2:90:30')['source']);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::FailedDemandForecast->value,
        ]);
    }

    public function test_non_retryable_bad_request_is_classified_without_repeated_calls(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $this->item();

        Http::fake(['*' => Http::response(['error' => ['status' => 'INVALID_ARGUMENT']], 400)]);

        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertSessionHas('info');

        Http::assertSentCount(1);
        $failure = AuditLog::query()
            ->where('action', AuditAction::FailedDemandForecast->value)
            ->firstOrFail();
        $this->assertSame('gemini_invalid_request', data_get($failure->new_values, 'failure_type'));
        $this->assertStringContainsString(
            'usable validated forecast',
            Cache::get('demand-forecast:v2:90:30')['notice'],
        );
    }

    public function test_invalid_ai_item_ids_are_rejected_and_never_rendered(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $this->item();

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['items' => [[
                            'item_id' => 999999,
                            'predicted_demand' => 10,
                            'risk_level' => 'high',
                            'reorder_priority' => 'critical',
                            'recommended_reorder_quantity' => 10,
                            'projected_stock_status' => 'out_of_stock',
                            'demand_trend' => 'stable',
                            'confidence' => 'high',
                            'explanation' => 'Hallucinated item.',
                        ]]], JSON_THROW_ON_ERROR),
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($manager)->post(route('inventory.demand-forecast.refresh'))
            ->assertRedirect()
            ->assertSessionHas('info');

        $cached = Cache::get('demand-forecast:v2:90:30');
        $this->assertSame('statistical', $cached['source']);
        $this->assertSame('Bandages', $cached['items'][0]['item_name']);
        $this->assertStringNotContainsString('Hallucinated item', json_encode($cached, JSON_THROW_ON_ERROR));
    }

    public function test_refresh_requires_forecast_permission_and_does_not_call_gemini(): void
    {
        $viewer = User::factory()->create();
        $this->item();
        Http::fake();

        $this->actingAs($viewer)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertNull(Cache::get('demand-forecast:v2:90:30'));
    }

    public function test_dashboard_never_exposes_the_configured_api_key(): void
    {
        config()->set('services.gemini.key', 'do-not-render-this-secret');
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('AI-Based Stock Demand Forecasting')
            ->assertDontSee('do-not-render-this-secret');
    }

    public function test_empty_inventory_returns_a_safe_error_and_records_failure(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        Http::fake();

        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertRedirect()
            ->assertSessionHas('error', 'No active inventory items are available to forecast.');

        Http::assertNothingSent();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::FailedDemandForecast->value,
            'outcome' => 'failure',
        ]);
    }

    public function test_service_unavailable_primary_model_falls_back_to_secondary_model_before_statistical_fallback(): void
    {
        config()->set('services.gemini.model', 'gemini-test-primary');
        config()->set('services.gemini.fallback_models', 'gemini-test-backup');

        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 10, 5);

        $responseItem = [
            'item_id' => $item->id,
            'predicted_demand' => 15,
            'risk_level' => 'medium',
            'reorder_priority' => 'medium',
            'recommended_reorder_quantity' => 10,
            'projected_stock_status' => 'low_stock',
            'demand_trend' => 'stable',
            'confidence' => 'high',
            'explanation' => 'Handled seamlessly by backup model.',
        ];

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-primary:generateContent' => Http::response([
                'error' => ['code' => 503, 'message' => 'Service Unavailable', 'status' => 'UNAVAILABLE'],
            ], 503),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-backup:generateContent' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['items' => [$responseItem]], JSON_THROW_ON_ERROR),
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertSessionHas('success');

        $cached = Cache::get('demand-forecast:v2:90:30');
        $this->assertSame('ai', $cached['source']);
        $this->assertSame('gemini-test-backup', $cached['model']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::GeneratedDemandForecast->value,
            'outcome' => 'success',
        ]);
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'Bandages',
            'sku' => 'BAND-AI-001',
            'unit' => 'box',
            'quantity_on_hand' => 5,
            'reorder_level' => 20,
            'lead_time_days' => 7,
            'status' => 'active',
        ]);
    }

    private function consume(InventoryItem $item, int $quantity, int $daysAgo): void
    {
        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => $quantity,
            'moved_at' => now()->subDays($daysAgo),
        ]);
    }
}
