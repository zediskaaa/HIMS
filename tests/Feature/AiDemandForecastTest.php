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

        $cached = Cache::get('demand-forecast:v3:90:30');
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
        // The validated model total remains exact while the chart allocation
        // carries the changing recorded-demand pattern into the horizon.
        $rates = collect($cached['items'][0]['forecast_series'])
            ->map(fn (array $bucket): float => $bucket['quantity'] / $bucket['days']);

        $this->assertCount(10, $rates);
        $this->assertGreaterThan(1, $rates->map(fn (float $rate): float => round($rate, 2))->unique()->count());
        $this->assertTrue($rates->every(fn (float $rate): bool => $rate >= 0));

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
            ->assertJsonCount(13, 'forecast.items.0.historical_series')
            ->assertJsonCount(10, 'forecast.items.0.forecast_series');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Recorded demand and AI forecast in one decision view.')
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
            ->assertSee('Historical demand')
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
            ->assertSee('x-on:change="updateForecastPeriod($event.target.value)"', false)
            ->assertSee('displayHistoricalRenderPoints()', false)
            ->assertSee('displayForecastRenderPoints()', false)
            ->assertSee('displayForecastPoints()', false)
            ->assertSee('Daily forecast')
            ->assertSee('Updating forecast')
            ->assertSee('Retry')
            ->assertSee('lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]', false)
            ->assertSee('x-ref="trackedItemsTile"', false)
            ->assertSee('x-ref="inventoryValueTile"', false)
            ->assertDontSee('Generate Forecast')
            ->assertDontSee('x-on:submit.prevent="generateForecast()"', false)
            ->assertDontSee('Forecasted inventory items')
            ->assertDontSee('forecastPointsWithTransition()', false)
            ->assertDontSee('Price Recommendations');
    }

    public function test_automatic_period_update_reuses_the_period_specific_cached_forecast(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $cached = [
            'source' => 'ai',
            'source_label' => 'AI Forecast',
            'forecast_days' => 60,
            'forecast_period' => 'Next 60 days',
            'analysis_days' => 90,
            'items' => [],
            'summary' => [],
        ];
        Cache::put('demand-forecast:v3:90:60', $cached, now()->addHour());
        Http::fake();

        $this->actingAs($manager)
            ->postJson(route('inventory.demand-forecast.refresh'), [
                'analysis_days' => 90,
                'forecast_days' => 60,
                'return_to' => 'dashboard',
                'reuse_cached' => true,
            ])
            ->assertOk()
            ->assertJsonPath('forecast.forecast_days', 60)
            ->assertJsonPath('forecast.forecast_period', 'Next 60 days')
            ->assertJsonPath('message', 'The 60-day forecast was loaded from the latest saved result.');

        Http::assertNothingSent();
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
        $this->assertSame([1, 1, 1, 1, 1, 1, 1], $forecast->pluck('days')->all());
        $this->assertSame(7, $forecast->sum('days'));
        $this->assertSame(now()->addDay()->toDateString(), $forecast->first()['period_start']);
    }

    public function test_each_dashboard_period_returns_its_full_forecast_horizon(): void
    {
        config()->set('services.gemini.key', null);
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 30, 5);
        Http::fake();

        foreach ([7 => 7, 30 => 10, 60 => 12, 90 => 13] as $days => $expectedBuckets) {
            $response = $this->actingAs($manager)
                ->postJson(route('inventory.demand-forecast.refresh'), [
                    'analysis_days' => 90,
                    'forecast_days' => $days,
                    'return_to' => 'dashboard',
                ])
                ->assertOk()
                ->assertJsonPath('forecast.forecast_days', $days)
                ->assertJsonPath('forecast.forecast_period', "Next {$days} days");

            $series = collect($response->json('forecast.items.0.forecast_series'));
            $this->assertCount($expectedBuckets, $series);
            $this->assertSame($days, $series->sum('days'));
            $this->assertSame(
                (int) $response->json('forecast.items.0.predicted_demand'),
                $series->sum('quantity'),
            );
            $this->assertTrue($series->every(fn (array $bucket): bool => $bucket['quantity'] >= 0));
        }

        Http::assertNothingSent();
    }

    /**
     * The window is split into weekly-scale buckets, and ninety days does not
     * divide by thirteen. Rounding the width up used to leave a stub at the end,
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
            [...array_fill(0, 12, 7), 6],
            $historical->pluck('days')->all()
        );
        $this->assertSame(90, $historical->sum('days'));

        // The most recent movement belongs to the final bucket rather than
        // falling outside a stub that closed before it.
        $this->assertSame(40, $historical->last()['quantity']);
    }

    public function test_the_forecast_series_preserves_the_validated_total_while_using_a_recent_demand_shape(): void
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

        // 90 units over the 90-day window, while the later half has fallen to
        // 20/45 = 0.44/day. The validated projection is still floored at 1/day.
        $this->assertSame(1.0, $average);
        $this->assertCount(10, $series);
        $this->assertSame(30, $series->sum('quantity'));
        $this->assertSame(30, $series->sum('days'));

        $rates = $series->map(fn (array $bucket): float => $bucket['quantity'] / $bucket['days']);
        $this->assertTrue($rates->every(fn (float $rate): bool => $rate >= 0));
        $this->assertGreaterThan(1, $rates->map(fn (float $rate): float => round($rate, 2))->unique()->count());
    }

    /**
     * A rising recent pattern should remain visible in the display allocation,
     * without changing the validated period total used for replenishment.
     */
    public function test_the_forecast_series_projects_recent_growth_without_changing_the_total(): void
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

        $this->assertCount(10, $rates);
        $this->assertSame(30, $series->sum('days'));
        $this->assertTrue($rates->every(fn (float $rate): bool => $rate >= 0));
        $this->assertGreaterThan(1, $rates->map(fn (float $rate): float => round($rate, 2))->unique()->count());
        $this->assertGreaterThan($rates->first(), $rates->last());
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
        $this->assertStringContainsString('forecastRenderPoints(', $script);
        $this->assertStringContainsString('x: transition,', $script);
        $this->assertStringContainsString('y: latestHistorical.y,', $script);
        $this->assertStringNotContainsString('baselineDailyRate()', $script);
        $this->assertStringContainsString('this.niceAxisStep(rawMaximum / 4) * 4', $script);
        $this->assertStringContainsString('Array.from({ length: 5 }', $script);
        $this->assertSame(5, substr_count($view, 'data-chart-grid-line'));
        $this->assertStringNotContainsString('y: this.chartY(point.quantity),', $script);
    }

    public function test_dashboard_period_updates_cancel_stale_requests_cache_results_and_morph_the_chart(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('async updateForecastPeriod(value, force = false)', $script);
        $this->assertStringContainsString('if (!force && Number(this.forecast?.forecast_days) === days) {', $script);
        $this->assertStringContainsString('this.forecastRequest.abort();', $script);
        $this->assertStringContainsString('if (!force && this.forecastCache[cacheKey])', $script);
        $this->assertStringContainsString('const request = new AbortController();', $script);
        $this->assertStringContainsString('if (requestId !== this.forecastRequestId) return;', $script);
        $this->assertStringContainsString('reuse_cached: !force', $script);
        $this->assertStringContainsString('this.applyForecast(payload.forecast);', $script);
        $this->assertStringContainsString("window.matchMedia('(prefers-reduced-motion: reduce)').matches", $script);
        $this->assertStringContainsString('this.chartAnimationFrame = requestAnimationFrame(animate);', $script);
    }

    public function test_dashboard_chart_tooltip_uses_dynamic_positioning_and_intelligent_boundary_detection(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertIsString($script);
        $this->assertIsString($view);

        // SVG accessibility uses aria-label instead of native <title> to prevent white browser popup
        $this->assertStringContainsString('aria-label="Demand vs Forecast: Historical and forecast inventory demand"', $view);
        $this->assertStringContainsString('aria-describedby="dashboard-demand-chart-description"', $view);
        $this->assertStringNotContainsString('<title id="dashboard-demand-chart-title">', $view);

        // Chart and tooltip refs
        $this->assertStringContainsString('x-ref="chartContainer"', $view);
        $this->assertStringContainsString('x-ref="chartInspector"', $view);
        $this->assertStringContainsString('x-ref="chartSvg"', $view);

        // Alpine tooltip state and positioning methods
        $this->assertStringContainsString('pointerX: null,', $script);
        $this->assertStringContainsString('pointerY: null,', $script);
        $this->assertStringContainsString('tooltipX: null,', $script);
        $this->assertStringContainsString('tooltipY: null,', $script);
        $this->assertStringContainsString('calculateTooltipPosition(', $script);
        $this->assertStringContainsString('overflowsContainerRight', $script);
        $this->assertStringContainsString('overflowsViewportRight', $script);
        $this->assertStringContainsString('overflowsContainerTop', $script);
        $this->assertStringContainsString('overflowsViewportTop', $script);
        $this->assertStringContainsString('overflowsContainerBottom', $script);
        $this->assertStringContainsString('overflowsViewportBottom', $script);
        $this->assertStringContainsString('candidateAbove', $script);

        // Dynamic style output without static top: 8px
        $this->assertStringContainsString('left: ${this.tooltipX}px; top: ${this.tooltipY}px; transform: none;', $script);
        $this->assertStringNotContainsString('top: 8px;', $script);
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
        $cached = Cache::get('demand-forecast:v3:90:30');
        $this->assertSame('statistical', $cached['source']);
        $this->assertSame('Statistical Forecast', $cached['source_label']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::FailedDemandForecast->value,
            'outcome' => 'failure',
        ]);

        $this->actingAs($manager)->get(route('inventory.demand-forecast'))
            ->assertOk()
            ->assertSee('Statistical Forecast')
            ->assertDontSee('Statistical fallback active')
            ->assertDontSee('The AI pass that refines this forecast');

        // The dashboard forecasting card shares the same envelope, so the
        // fallback must not surface there either.
        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('AI-Based Stock Demand Forecasting')
            ->assertDontSee('Statistical fallback active')
            ->assertDontSee('These recommendations use recorded consumption');
    }

    /**
     * A failed model pass has to expire with the warm-up window, not the AI
     * result's lifetime. Pinning the statistical fallback for the longer
     * window means the retry cadence the fallback window exists to provide
     * never happens: one transient failure would hold the screen for hours.
     */
    public function test_a_failed_gemini_pass_expires_with_the_fallback_window(): void
    {
        config()->set('services.gemini.forecast_fallback_minutes', 3);
        config()->set('services.gemini.forecast_cache_minutes', 60);

        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 60, 5);

        // No usable credential, so the pass fails before any HTTP call and the
        // test stays deterministic and offline.
        config()->set('services.gemini.key', null);
        Http::fake();

        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertRedirect()
            ->assertSessionHas('info');

        Http::assertNothingSent();
        $this->assertSame('statistical', Cache::get('demand-forecast:v3:90:30')['source']);

        // Past the warm-up window while still well inside the AI result's
        // lifetime, the failed result must be gone rather than pinned.
        $this->travel(4)->minutes();
        $this->assertNull(Cache::get('demand-forecast:v3:90:30'));

        // So the next view asks the model again instead of serving the stale
        // failure for the rest of the hour.
        $this->actingAs($manager)->get(route('inventory.demand-forecast'))->assertOk();

        $this->assertSame(2, AuditLog::where('action', AuditAction::FailedDemandForecast->value)->count());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::FailedDemandForecast->value,
            'outcome' => 'failure',
        ]);
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
        $this->assertSame('ai', Cache::get('demand-forecast:v3:90:30')['source']);
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
        $this->assertSame('statistical', Cache::get('demand-forecast:v3:90:30')['source']);
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

        $cached = Cache::get('demand-forecast:v3:90:30');
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
        $this->assertNull(Cache::get('demand-forecast:v3:90:30'));
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

        $cached = Cache::get('demand-forecast:v3:90:30');
        $this->assertSame('ai', $cached['source']);
        $this->assertSame('gemini-test-backup', $cached['model']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::GeneratedDemandForecast->value,
            'outcome' => 'success',
        ]);
    }

    /**
     * The screen a signed-in user opens first has to already carry a forecast.
     * Waiting for somebody to press Generate meant the panel was empty on the
     * very page it exists for, so the first view fills the cache itself: the
     * recorded consumption is summarised in the request, and the model pass
     * that refines it is deferred until after the response has been sent.
     */
    public function test_the_dashboard_fills_an_empty_forecast_cache_without_being_asked(): void
    {
        config()->set('services.gemini.forecast_fallback_minutes', 10);

        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 45, 20);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['items' => [[
                            'item_id' => $item->id,
                            'predicted_demand' => 20,
                            'risk_level' => 'high',
                            'reorder_priority' => 'high',
                            'recommended_reorder_quantity' => 15,
                            'projected_stock_status' => 'low_stock',
                            'demand_trend' => 'stable',
                            'confidence' => 'medium',
                            'explanation' => 'Recorded consumption continues above available stock.',
                        ]]], JSON_THROW_ON_ERROR),
                    ]]],
                ]],
            ]),
        ]);

        // Another visitor already claimed the warm-up, so this request serves
        // the placeholder and leaves the model call to the pass that holds it.
        $this->holdForecastWarmup();

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Statistical Forecast')
            ->assertSee('Bandages');

        // Nobody waits on the model to see the screen: the page renders the
        // recorded-consumption summary while the Gemini pass is still queued.
        Http::assertNothingSent();
        $this->assertSame('statistical', Cache::get('demand-forecast:v3:90:30')['source']);
    }

    public function test_a_forecast_generated_from_a_page_view_is_audited_as_generated_not_refreshed(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $item = $this->item();
        $this->consume($item, 30, 12);

        // The warm-up is claimed elsewhere, so the page view leaves the
        // unfinished placeholder behind rather than a landed forecast.
        $this->holdForecastWarmup();

        $this->actingAs($manager)->get(route('dashboard'))->assertOk();

        $this->assertSame('statistical', Cache::get('demand-forecast:v3:90:30')['source']);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['items' => [[
                            'item_id' => $item->id,
                            'predicted_demand' => 18,
                            'risk_level' => 'medium',
                            'reorder_priority' => 'medium',
                            'recommended_reorder_quantity' => 11,
                            'projected_stock_status' => 'low_stock',
                            'demand_trend' => 'stable',
                            'confidence' => 'medium',
                            'explanation' => 'Consumption remains steady against the reorder level.',
                        ]]], JSON_THROW_ON_ERROR),
                    ]]],
                ]],
            ]),
        ]);

        // Refreshing replaces the placeholder, and because the placeholder was
        // never a finished forecast the run is the first generation for this
        // window rather than a refresh of a forecast nobody had generated.
        $this->actingAs($manager)
            ->post(route('inventory.demand-forecast.refresh'))
            ->assertSessionHas('success');

        $this->assertSame('ai', Cache::get('demand-forecast:v3:90:30')['source']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::GeneratedDemandForecast->value,
            'outcome' => 'success',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::RefreshedDemandForecast->value,
        ]);
    }

    public function test_dashboard_forecast_renders_animated_skeleton_loading_states_and_reusable_components(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $this->holdForecastWarmup();

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('x-show="isLoading()"', false)
            ->assertSee('x-show="isSuccess()"', false)
            ->assertSee('x-show="isEmpty()"', false)
            ->assertSee('x-show="isError()"', false)
            ->assertSee('animate-pulse', false)
            ->assertSee('motion-reduce:animate-none', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('Retry Forecast', false)
            ->assertSee('Scanning stock levels...', false)
            ->assertSee('sm:grid-cols-2 lg:grid-cols-5 lg:items-end', false)
            ->assertSee('grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200 sm:grid-cols-3 xl:grid-cols-6', false)
            ->assertSee('viewBox="0 0 760 240"', false)
            ->assertSee('Units/day', false)
            ->assertSee('grid-cols-4 gap-1.5 rounded-lg border border-neutral-200/90', false)
            ->assertDontSee('Forecast data unavailable')
            ->assertDontSee('grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3', false)
            ->assertDontSee('h-28 bg-white dark:bg-neutral-800 rounded-xl', false);
    }

    public function test_dashboard_javascript_enforces_skeleton_loading_state_management_and_filter_transitions(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($script);

        $this->assertStringContainsString('currentState()', $script);
        $this->assertStringContainsString('isLoading()', $script);
        $this->assertStringContainsString('isSuccess()', $script);
        $this->assertStringContainsString('isEmpty()', $script);
        $this->assertStringContainsString('isError()', $script);
        $this->assertStringContainsString('triggerFilterTransition()', $script);
        $this->assertStringContainsString('filterLoading: false,', $script);
        $this->assertStringContainsString('this.filterLoading = true;', $script);
        $this->assertStringContainsString('this.triggerFilterTransition();', $script);
    }

    public function test_demand_forecast_index_renders_animated_skeleton_loading_states(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $this->holdForecastWarmup();

        $response = $this->actingAs($manager)->get(route('inventory.demand-forecast'));

        $response->assertOk()
            ->assertSee('x-show="isLoading()"', false)
            ->assertSee('x-show="isSuccess()"', false)
            ->assertSee('x-show="isEmpty()', false)
            ->assertSee('x-show="isError()"', false)
            ->assertSee('animate-pulse', false)
            ->assertSee('motion-reduce:animate-none', false)
            ->assertDontSee('Historical consumption and forecast points are calculating or unavailable');
    }

    /**
     * Claim the lock the deferred Gemini warm-up runs under.
     *
     * The harness terminates the request, so a deferred callback would run
     * before the assertions and the test would observe the cache after the
     * warm-up instead of the placeholder a visitor is served.
     */
    private function holdForecastWarmup(int $analysisDays = 90, int $forecastDays = 30): void
    {
        Cache::lock("demand-forecast:v3:warmup:{$analysisDays}:{$forecastDays}", 300)->get();
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
