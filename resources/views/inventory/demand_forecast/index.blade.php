<x-app-layout>
    <x-ui.page-header
        title="Demand Forecasting"
        subtitle="Reorder quantities worked out from recorded consumption, not from typed-in estimates."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Procurement' => route('inventory.purchases'), 'Demand Forecast' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('inventory.purchases')" icon="arrow-left">Back to Procurement</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="This plan was not saved">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat
            label="Items forecast"
            :value="number_format($summary['items'])"
            icon="cube"
            tone="primary"
            :hint="$analysisDays.'-day history, '.$forecastDays.'-day horizon'" />

        <x-ui.stat
            label="At or below reorder point"
            :value="number_format($summary['needs_reorder'])"
            icon="exclamation-triangle"
            tone="warning"
            hint="Order these before the lead time runs out." />

        <x-ui.stat
            label="Suggested units"
            :value="number_format($summary['suggested_units'])"
            icon="truck"
            tone="neutral"
            hint="Total across every item needing stock." />

        <x-ui.stat
            label="No recorded usage"
            :value="number_format($summary['no_usage'])"
            icon="minus"
            tone="neutral"
            hint="Nothing consumed in the window — not forecastable yet." />
    </div>

    {{--
        The panel will ask how the numbers are produced, so the formulas sit on
        the screen itself rather than only in the code. Collapsed by default so
        it does not push the table down for day-to-day users.
    --}}
    <div x-data="{ open: false }">
        <x-ui.card>
            <button type="button" x-on:click="open = !open"
                    class="flex items-center justify-between w-full gap-3 text-left">
                <span class="flex items-center gap-2">
                    <x-ui.icon name="chart-bar" class="w-4 h-4 text-primary-600" />
                    <span class="text-sm font-semibold text-neutral-900">How these numbers are worked out</span>
                </span>
                <x-ui.icon name="chevron-down" class="w-4 h-4 text-neutral-400 transition-transform"
                           x-bind:class="open && 'rotate-180'" />
            </button>

            <div x-show="open" x-cloak class="mt-4 space-y-3 text-sm text-neutral-600">
                <p>
                    Usage is taken from <span class="font-medium text-neutral-900">stock movements</span> only —
                    stock out and issuance, the two types that mean stock was actually consumed. Transfers,
                    disposals, returns and adjustments are excluded: counting them as demand would inflate the
                    forecast and order stock nobody needs.
                </p>
                <dl class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-md bg-neutral-50 border border-neutral-200 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Average daily usage</dt>
                        <dd class="mt-0.5 font-mono text-xs">consumed in window ÷ days in window</dd>
                    </div>
                    <div class="rounded-md bg-neutral-50 border border-neutral-200 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Safety stock</dt>
                        <dd class="mt-0.5 font-mono text-xs">average daily usage × {{ \App\Services\DemandForecastService::DEFAULT_BUFFER_DAYS }} buffer days</dd>
                    </div>
                    <div class="rounded-md bg-neutral-50 border border-neutral-200 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Reorder point</dt>
                        <dd class="mt-0.5 font-mono text-xs">(average daily usage × lead time) + safety stock</dd>
                    </div>
                    <div class="rounded-md bg-neutral-50 border border-neutral-200 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Suggested order</dt>
                        <dd class="mt-0.5 font-mono text-xs">(avg daily × horizon) + safety stock − on hand</dd>
                    </div>
                </dl>
                <p class="text-xs text-neutral-500">
                    A moving average over a fixed window, which is the standard inventory-control method. A seasonal
                    model would fit a hospital's yearly pattern better but needs years of history to beat a moving
                    average, and this system does not have that history yet.
                </p>
            </div>
        </x-ui.card>
    </div>

    {{--
        Two knobs only. Everything else the forecast needs is already recorded,
        and adding more inputs would put us back where the old hand-typed form
        was — a forecast that restates whatever was entered.
    --}}
    <x-ui.card
        title="Forecast Window"
        subtitle="Changing either value re-runs every item against the new window.">
        <form method="GET" action="{{ route('inventory.demand-forecast') }}"
              class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-ui.field
                name="analysis_days"
                label="History Analysed"
                type="select"
                :value="$analysisDays"
                hint="How far back usage is averaged over."
                :options="[
                    30 => 'Last 30 days',
                    60 => 'Last 60 days',
                    90 => 'Last 90 days (default)',
                    180 => 'Last 180 days',
                    365 => 'Last 365 days',
                ]" />

            <x-ui.field
                name="forecast_days"
                label="Forecast Horizon"
                type="select"
                :value="$forecastDays"
                hint="How far ahead the suggested order covers."
                :options="[
                    14 => 'Next 14 days',
                    30 => 'Next 30 days (default)',
                    60 => 'Next 60 days',
                    90 => 'Next 90 days',
                ]" />

            <div class="lg:col-span-2 flex items-center gap-2">
                <x-ui.button type="submit" icon="magnifying-glass">Recalculate</x-ui.button>
                @if ($analysisDays !== \App\Services\DemandForecastService::DEFAULT_ANALYSIS_DAYS
                    || $forecastDays !== \App\Services\DemandForecastService::DEFAULT_FORECAST_DAYS)
                    <x-ui.button variant="secondary" :href="route('inventory.demand-forecast')">
                        Reset to defaults
                    </x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card
        title="Forecast by Item"
        :subtitle="'Sorted by days of cover — the items closest to running out sit at the top.'"
        :padding="false">
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Item</x-ui.table.th>
                <x-ui.table.th numeric>On Hand</x-ui.table.th>
                <x-ui.table.th numeric>Used ({{ $analysisDays }}d)</x-ui.table.th>
                <x-ui.table.th numeric>Avg / Day</x-ui.table.th>
                <x-ui.table.th numeric>Days Cover</x-ui.table.th>
                <x-ui.table.th numeric>Reorder Pt</x-ui.table.th>
                <x-ui.table.th numeric>Suggested Order</x-ui.table.th>
                <x-ui.table.th>Trend</x-ui.table.th>
                <x-ui.table.th>Status</x-ui.table.th>
                @can(\App\Enums\Permission::GenerateForecasts->value)
                    <x-ui.table.th align="right">Action</x-ui.table.th>
                @endcan
            </x-ui.table.head>
            <tbody>
                @forelse ($forecasts as $row)
                    @php
                        $item = $row['item'];
                        $cover = $row['days_of_cover'];
                    @endphp
                    <x-ui.table.row>
                        <x-ui.table.td>
                            <span class="font-medium text-neutral-900">{{ $item->name }}</span>
                            <span class="block text-xs text-neutral-500">
                                {{ $item->sku }}@if ($item->supplier) &middot; {{ $item->supplier->name }}@endif
                            </span>
                        </x-ui.table.td>

                        <x-ui.table.td numeric>{{ number_format($row['current_stock']) }}</x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format($row['historical_usage']) }}</x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format($row['average_daily_usage'], 2) }}</x-ui.table.td>

                        <x-ui.table.td numeric>
                            {{-- Null cover is "nothing consumed", which is not the
                                 same statement as "0 days left". --}}
                            @if ($cover === null)
                                <span class="text-neutral-400">—</span>
                            @else
                                <span @class([
                                    'font-semibold',
                                    'text-danger-700' => $cover <= 7,
                                    'text-warning-700' => $cover > 7 && $cover <= 14,
                                    'text-neutral-800' => $cover > 14,
                                ])>{{ number_format($cover) }}</span>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td numeric muted>{{ number_format($row['reorder_point']) }}</x-ui.table.td>

                        <x-ui.table.td numeric>
                            @if ($row['suggested_order_quantity'] > 0)
                                <span class="font-semibold text-neutral-900">
                                    {{ number_format($row['suggested_order_quantity']) }}
                                </span>
                                <span class="block text-xs text-neutral-400">{{ $item->unit ?? 'units' }}</span>
                            @else
                                <span class="text-neutral-400">—</span>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <x-ui.badge :variant="$row['trend']->badgeVariant()">
                                <x-ui.icon :name="$row['trend']->icon()" class="w-3 h-3" />
                                {{ $row['trend']->label() }}
                            </x-ui.badge>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            @if ($row['needs_reorder'])
                                <x-ui.badge variant="warning" dot title="{{ $row['trigger_reason'] }}">
                                    Reorder now
                                </x-ui.badge>
                            @elseif ($row['average_daily_usage'] <= 0)
                                <x-ui.badge variant="neutral" title="{{ $row['trigger_reason'] }}">
                                    No usage
                                </x-ui.badge>
                            @else
                                <x-ui.badge variant="success" title="{{ $row['trigger_reason'] }}">
                                    Sufficient
                                </x-ui.badge>
                            @endif
                        </x-ui.table.td>

                        @can(\App\Enums\Permission::GenerateForecasts->value)
                            <x-ui.table.td align="right">
                                {{-- Posts the item and window only; the server recomputes
                                     so a page left open overnight cannot save stale numbers. --}}
                                <form method="POST" action="{{ route('inventory.demand-forecast.store') }}">
                                    @csrf
                                    <input type="hidden" name="item_id" value="{{ $item->id }}">
                                    <input type="hidden" name="analysis_days" value="{{ $analysisDays }}">
                                    <input type="hidden" name="forecast_days" value="{{ $forecastDays }}">
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        Save Plan
                                    </x-ui.button>
                                </form>
                            </x-ui.table.td>
                        @endcan
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty
                        :colspan="auth()->user()?->hasPermission(\App\Enums\Permission::GenerateForecasts) ? 10 : 9"
                        icon="chart-bar"
                        title="No items to forecast"
                        message="Add inventory items and record some stock movements, then the forecast fills in." />
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    {{--
        A saved plan is a snapshot on purpose: the movement history behind it
        keeps moving, so re-running the numbers next month would not reproduce
        what an order was actually based on.
    --}}
    <x-ui.card
        title="Saved Plans"
        :subtitle="$plans->count().' most recent'"
        :padding="false">
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
                        <x-ui.table.td>
                            <span class="font-mono text-xs font-medium text-neutral-900">{{ $plan->plan_number }}</span>
                            @if ($plan->notes)
                                <span class="block text-xs text-neutral-500">{{ $plan->notes }}</span>
                            @endif
                        </x-ui.table.td>
                        <x-ui.table.td>{{ $plan->item?->name ?? '—' }}</x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format((int) $plan->current_stock) }}</x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format((float) $plan->average_daily_usage, 2) }}</x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format((int) $plan->reorder_point) }}</x-ui.table.td>
                        <x-ui.table.td numeric>
                            <span class="font-semibold">{{ number_format((int) $plan->suggested_order_quantity) }}</span>
                        </x-ui.table.td>
                        <x-ui.table.td muted>
                            <span class="text-xs">
                                {{ $plan->analysis_days }}d history &middot; {{ $plan->forecast_days }}d ahead
                                &middot; {{ $plan->lead_time_days }}d lead
                            </span>
                        </x-ui.table.td>
                        <x-ui.table.td muted>
                            {{ $plan->generated_at?->format('M d, Y g:i A') ?? '—' }}
                            @if ($plan->generatedBy)
                                <span class="block text-xs text-neutral-400">{{ $plan->generatedBy->name }}</span>
                            @endif
                        </x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty
                        :colspan="8"
                        icon="document-text"
                        title="No plans saved yet"
                        message="Save a forecast from the table above to keep a record of what an order was based on." />
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
</x-app-layout>
