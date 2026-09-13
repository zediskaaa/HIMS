<x-app-layout :full-width="true">
    <x-slot:title>Demand Forecasting</x-slot:title>

    <x-ui.page-header
        title="AI-Based Stock Demand Forecasting"
        subtitle="Review validated demand forecasts, stock risk, and advisory reorder recommendations derived from HIMS inventory activity."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Procurement' => route('inventory.purchases'), 'Demand Forecast' => null]"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('inventory.purchases')" icon="arrow-left">Back to Procurement</x-ui.button>
            @can(\App\Enums\Permission::GenerateForecasts->value)
                <form method="POST" action="{{ route('inventory.demand-forecast.refresh') }}">
                    @csrf
                    <input type="hidden" name="analysis_days" value="{{ $analysisDays }}">
                    <input type="hidden" name="forecast_days" value="{{ $forecastDays }}">
                    <input type="hidden" name="return_to" value="forecast">
                    <x-ui.button type="submit" icon="arrow-path" data-loading-text="Generating forecast...">
                        {{ $aiForecast ? 'Refresh AI Forecast' : 'Generate AI Forecast' }}
                    </x-ui.button>
                </form>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="This plan was not saved">
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="space-y-4">
        <x-ui.card title="Forecast scope" subtitle="Changing the analysis or forecast window changes the underlying calculations.">
            <form method="GET" action="{{ route('inventory.demand-forecast') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <div>
                    <label for="analysis-days" class="block text-xs font-medium text-neutral-700">Historical window</label>
                    <select id="analysis-days" name="analysis_days" class="mt-1.5 block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30">
                        @foreach ([30, 60, 90, 180, 365] as $days)
                            <option value="{{ $days }}" @selected($analysisDays === $days)>{{ $days }} days</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="forecast-days" class="block text-xs font-medium text-neutral-700">Forecast horizon</label>
                    <select id="forecast-days" name="forecast_days" class="mt-1.5 block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30">
                        @foreach ([7, 30, 60, 90] as $days)
                            <option value="{{ $days }}" @selected($forecastDays === $days)>Next {{ $days }} days</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="forecast-risk" class="block text-xs font-medium text-neutral-700">AI risk</label>
                    <select id="forecast-risk" name="risk" class="mt-1.5 block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30">
                        <option value="">All risks</option>
                        @foreach (['high', 'medium', 'low'] as $risk)
                            <option value="{{ $risk }}" @selected($aiFilters['risk'] === $risk)>{{ ucfirst($risk) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="forecast-category" class="block text-xs font-medium text-neutral-700">Category</label>
                    <select id="forecast-category" name="category_id" class="mt-1.5 block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($aiFilters['categoryId'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="forecast-search" class="block text-xs font-medium text-neutral-700">Item or SKU</label>
                    <input id="forecast-search" name="search" type="search" value="{{ $aiFilters['search'] }}" maxlength="100" placeholder="Search inventory" class="mt-1.5 block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm placeholder:text-neutral-400 focus:border-primary-500 focus:ring-primary-500/30">
                </div>

                <div class="flex gap-2">
                    <x-ui.button type="submit" size="sm" icon="funnel" class="flex-1">Apply</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" :href="route('inventory.demand-forecast')">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($aiForecast)
            <x-ui.card :padding="false">
                <x-slot:header>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-semibold text-neutral-900">AI Forecast Details</h2>
                            <x-ui.badge :variant="$aiForecast['source'] === 'ai' ? 'primary' : 'warning'" dot>
                                {{ $aiForecast['source_label'] }}
                            </x-ui.badge>
                        </div>
                        <p class="mt-1 text-xs text-neutral-500">
                            {{ $aiForecast['forecast_period'] }} · Generated {{ \Illuminate\Support\Carbon::parse($aiForecast['generated_at'])->format('M d, Y g:i A') }}
                        </p>
                    </div>
                </x-slot:header>

                @if ($aiForecast['notice'])
                    <div class="px-4 pt-4">
                        <x-ui.alert variant="warning" title="Statistical fallback active">
                            {{ $aiForecast['notice'] }}
                        </x-ui.alert>
                    </div>
                @endif

                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['High risk', $aiForecast['summary']['high_risk_items'], 'border-danger-200 bg-danger-50 text-danger-900'],
                        ['Medium risk', $aiForecast['summary']['medium_risk_items'], 'border-warning-200 bg-warning-50 text-warning-900'],
                        ['Low risk', $aiForecast['summary']['low_risk_items'], 'border-success-200 bg-success-50 text-success-900'],
                        ['Suggested units', $aiForecast['summary']['recommended_reorder_units'], 'border-neutral-200 bg-neutral-50 text-neutral-900'],
                    ] as [$label, $value, $classes])
                        <div class="rounded-lg border px-3 py-3 {{ $classes }}">
                            <p class="text-xs font-medium opacity-75">{{ $label }}</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($value) }}</p>
                        </div>
                    @endforeach
                </div>

                <x-ui.table>
                    <x-ui.table.head>
                        <x-ui.table.th>Item</x-ui.table.th>
                        <x-ui.table.th>Risk</x-ui.table.th>
                        <x-ui.table.th numeric>Current</x-ui.table.th>
                        <x-ui.table.th numeric>Historical</x-ui.table.th>
                        <x-ui.table.th numeric>Predicted</x-ui.table.th>
                        <x-ui.table.th numeric>Reorder</x-ui.table.th>
                        <x-ui.table.th>Trend / Confidence</x-ui.table.th>
                        <x-ui.table.th>Explanation</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($aiItems as $item)
                            <x-ui.table.row>
                                <x-ui.table.td>
                                    <span class="font-medium text-neutral-900">{{ $item['item_name'] }}</span>
                                    <span class="block text-xs text-neutral-500">{{ $item['sku'] }}@if($item['category']) · {{ $item['category'] }}@endif</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <x-ui.badge :variant="match($item['risk_level']) { 'high' => 'danger', 'medium' => 'warning', default => 'success' }" dot>{{ ucfirst($item['risk_level']) }}</x-ui.badge>
                                    <span class="mt-1 block text-xs text-neutral-500">{{ ucfirst($item['reorder_priority']) }} priority</span>
                                </x-ui.table.td>
                                <x-ui.table.td numeric>{{ number_format($item['current_stock']) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format($item['historical_consumption']) }}</x-ui.table.td>
                                <x-ui.table.td numeric>{{ number_format($item['predicted_demand']) }}</x-ui.table.td>
                                <x-ui.table.td numeric><span class="font-semibold text-primary-700">{{ number_format($item['recommended_reorder_quantity']) }}</span></x-ui.table.td>
                                <x-ui.table.td>
                                    <span class="text-sm text-neutral-800">{{ ucfirst($item['demand_trend']) }}</span>
                                    <span class="block text-xs text-neutral-500">{{ ucfirst($item['confidence']) }} confidence</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <p class="max-w-md whitespace-normal text-sm text-neutral-600">{{ $item['explanation'] }}</p>
                                    @if ($item['limited_data'])
                                        <span class="mt-1 block text-xs font-medium text-warning-700">Limited movement history</span>
                                    @endif
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty :colspan="8" icon="magnifying-glass" title="No matching forecast items" message="Adjust or clear the filters to see more results." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @else
            <x-ui.alert variant="info" title="No generated forecast for this window">
                Generate an advisory AI forecast to populate the detailed risk and reorder results. The statistical forecast below remains available from recorded HIMS demand.
            </x-ui.alert>
        @endif

        <x-ui.card title="Statistical Forecast by Item" :subtitle="'Sorted by days of cover · the items closest to running out sit at the top.'" :padding="false">
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>Item</x-ui.table.th>
                    <x-ui.table.th numeric>On Hand</x-ui.table.th>
                    <x-ui.table.th numeric>Used ({{ $analysisDays }}d)</x-ui.table.th>
                    <x-ui.table.th numeric>Avg / Day</x-ui.table.th>
                    <x-ui.table.th numeric>Days Cover</x-ui.table.th>
                    <x-ui.table.th numeric>Reorder Pt</x-ui.table.th>
                    <x-ui.table.th numeric>Suggested</x-ui.table.th>
                    <x-ui.table.th>Trend</x-ui.table.th>
                    <x-ui.table.th>Status</x-ui.table.th>
                    @can(\App\Enums\Permission::GenerateForecasts->value)
                        <x-ui.table.th align="right">Action</x-ui.table.th>
                    @endcan
                </x-ui.table.head>
                <tbody>
                    @forelse ($forecasts as $row)
                        @php($item = $row['item'])
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <span class="font-medium text-neutral-900">{{ $item->name }}</span>
                                <span class="block text-xs text-neutral-500">{{ $item->sku }}@if($item->supplier) · {{ $item->supplier->name }}@endif</span>
                            </x-ui.table.td>
                            <x-ui.table.td numeric>{{ number_format($row['current_stock']) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row['historical_usage']) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row['average_daily_usage'], 2) }}</x-ui.table.td>
                            <x-ui.table.td numeric>{{ $row['days_of_cover'] === null ? '—' : number_format($row['days_of_cover']) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row['reorder_point']) }}</x-ui.table.td>
                            <x-ui.table.td numeric><span class="font-semibold">{{ number_format($row['suggested_order_quantity']) }}</span></x-ui.table.td>
                            <x-ui.table.td><x-ui.badge :variant="$row['trend']->badgeVariant()">{{ $row['trend']->label() }}</x-ui.badge></x-ui.table.td>
                            <x-ui.table.td>
                                @if ($row['needs_reorder'])
                                    <x-ui.badge variant="warning" dot title="{{ $row['trigger_reason'] }}">Reorder now</x-ui.badge>
                                @elseif ($row['average_daily_usage'] <= 0)
                                    <x-ui.badge variant="neutral" title="{{ $row['trigger_reason'] }}">No usage</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success" title="{{ $row['trigger_reason'] }}">Sufficient</x-ui.badge>
                                @endif
                            </x-ui.table.td>
                            @can(\App\Enums\Permission::GenerateForecasts->value)
                                <x-ui.table.td align="right">
                                    <form method="POST" action="{{ route('inventory.demand-forecast.store') }}">
                                        @csrf
                                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                                        <input type="hidden" name="analysis_days" value="{{ $analysisDays }}">
                                        <input type="hidden" name="forecast_days" value="{{ $forecastDays }}">
                                        <x-ui.button type="submit" variant="secondary" size="sm">Save Plan</x-ui.button>
                                    </form>
                                </x-ui.table.td>
                            @endcan
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty :colspan="auth()->user()?->hasPermission(\App\Enums\Permission::GenerateForecasts) ? 10 : 9" icon="chart-bar" title="No items to forecast" message="Add inventory items and record stock movements, then the forecast will populate." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="Saved Plans" :subtitle="$plans->count().' most recent'" :padding="false">
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>Plan No.</x-ui.table.th>
                    <x-ui.table.th>Item</x-ui.table.th>
                    <x-ui.table.th numeric>On Hand</x-ui.table.th>
                    <x-ui.table.th numeric>Avg / Day</x-ui.table.th>
                    <x-ui.table.th numeric>Reorder Pt</x-ui.table.th>
                    <x-ui.table.th numeric>Suggested</x-ui.table.th>
                    <x-ui.table.th>Basis</x-ui.table.th>
                    <x-ui.table.th>Generated</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($plans as $plan)
                        <x-ui.table.row>
                            <x-ui.table.td><span class="font-mono text-xs font-medium text-neutral-900">{{ $plan->plan_number }}</span></x-ui.table.td>
                            <x-ui.table.td>{{ $plan->item?->name ?? '—' }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format((int) $plan->current_stock) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format((float) $plan->average_daily_usage, 2) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format((int) $plan->reorder_point) }}</x-ui.table.td>
                            <x-ui.table.td numeric><span class="font-semibold">{{ number_format((int) $plan->suggested_order_quantity) }}</span></x-ui.table.td>
                            <x-ui.table.td muted>{{ $plan->analysis_days }}d history · {{ $plan->forecast_days }}d ahead · {{ $plan->lead_time_days }}d lead</x-ui.table.td>
                            <x-ui.table.td muted>
                                {{ $plan->generated_at?->format('M d, Y g:i A') ?? '—' }}
                                @if ($plan->generatedBy)
                                    <span class="block text-xs text-neutral-400">{{ $plan->generatedBy->name }}</span>
                                @endif
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty :colspan="8" icon="document-text" title="No plans saved yet" message="Save a statistical forecast above to preserve the basis for a reorder decision." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>
</x-app-layout>
