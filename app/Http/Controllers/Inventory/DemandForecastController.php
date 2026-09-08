<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemandForecastPlanRequest;
use App\Models\DemandPlan;
use App\Models\InventoryItem;
use App\Services\DemandForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Replaces the hand-typed demand plan form that used to sit on the
 * procurement page. The numbers now come from recorded stock movements
 * instead of from whoever was filling in the form.
 */
class DemandForecastController extends Controller implements HasMiddleware
{
    public function __construct(private readonly DemandForecastService $forecasts) {}

    /**
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            // Reading the forecast is a reporting activity; saving a plan
            // commits a reorder decision and needs the planning permission.
            new Middleware('can:'.Permission::ViewReports->value, only: ['index']),
            new Middleware('can:'.Permission::GenerateForecasts->value, only: ['store']),
        ];
    }

    public function index(Request $request): View
    {
        $analysisDays = (int) $request->integer('analysis_days', DemandForecastService::DEFAULT_ANALYSIS_DAYS);
        $forecastDays = (int) $request->integer('forecast_days', DemandForecastService::DEFAULT_FORECAST_DAYS);

        // Clamp rather than reject: these arrive from a <select> on the page,
        // and a nonsense value in the query string should not 500 the screen.
        $analysisDays = max(7, min(365, $analysisDays));
        $forecastDays = max(7, min(180, $forecastDays));

        $forecasts = $this->forecasts->forecastAll($analysisDays, $forecastDays);

        return view('inventory.demand_forecast.index', [
            'forecasts' => $forecasts,
            'analysisDays' => $analysisDays,
            'forecastDays' => $forecastDays,
            'plans' => DemandPlan::with(['item', 'generatedBy'])
                ->latest('generated_at')
                ->latest('id')
                ->limit(20)
                ->get(),
            'summary' => [
                'items' => $forecasts->count(),
                'needs_reorder' => $forecasts->where('needs_reorder', true)->count(),
                'no_usage' => $forecasts->where('average_daily_usage', 0.0)->count(),
                'suggested_units' => (int) $forecasts->sum('suggested_order_quantity'),
            ],
        ]);
    }

    /**
     * Freeze one item's forecast as a DemandPlan.
     */
    public function store(StoreDemandForecastPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $item = InventoryItem::findOrFail($validated['item_id']);

        // Recomputed here rather than taken from the form: the posted numbers
        // came from a page that may have been open for a while, and a plan is
        // meant to record what was true when it was saved.
        $forecast = $this->forecasts->forecast(
            $item,
            (int) ($validated['analysis_days'] ?? DemandForecastService::DEFAULT_ANALYSIS_DAYS),
            (int) ($validated['forecast_days'] ?? DemandForecastService::DEFAULT_FORECAST_DAYS),
            isset($validated['lead_time_days']) ? (int) $validated['lead_time_days'] : null,
        );

        $plan = $this->forecasts->storePlan($forecast, $request->user()?->id, $validated['notes'] ?? null);

        return redirect()
            ->route('inventory.demand-forecast')
            ->with('success', sprintf(
                '%s saved for %s — suggested order %s %s.',
                $plan->plan_number,
                $item->name,
                number_format($plan->suggested_order_quantity),
                $item->unit ?? 'units'
            ));
    }
}
