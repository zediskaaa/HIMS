@php
    $isSuperAdminPanel = $superAdminPanel ?? false;
    $isAdminPanel = \App\Support\AuthenticationContext::isAdmin();
    $dashboardTitle = match (true) {
        $isSuperAdminPanel => 'Super Admin Dashboard',
        $isAdminPanel => 'Admin Dashboard',
        default => 'Staff Dashboard',
    };
    $dashboardSubtitle = match (true) {
        $isSuperAdminPanel => 'Full administrative oversight of HIMS inventory, users, procurement, and records.',
        $isAdminPanel => 'Manage HIMS operations and user access within the Administrator role.',
        default => 'Monitor inventory health and complete the operations authorized for your staff role.',
    };
@endphp

<x-app-layout full-width>
    <x-slot:title>{{ $dashboardTitle }}</x-slot:title>

    <x-ui.page-header
        :title="$dashboardTitle"
        :subtitle="$dashboardSubtitle"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), $dashboardTitle => null]"
    >
        <x-slot:actions>
            @canany([\App\Enums\Permission::IssueStock->value, \App\Enums\Permission::RecordMovements->value, \App\Enums\Permission::TransferStock->value])
            <x-ui.button variant="secondary" icon="arrows-right-left" :href="route('inventory.stock-movements')">
                Record movement
            </x-ui.button>
            @endcanany
            @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
            <x-ui.button variant="secondary" icon="arrow-up-tray" :href="route('inventory.import.index')">
                Import data
            </x-ui.button>
            @endcanany
            @can(\App\Enums\Permission::ManageItems->value)
            <x-ui.button variant="primary" icon="plus" :href="route('inventory.items')">
                New item
            </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{--
        Alerts and the counters above them are re-fetched every 30s so the
        dashboard reflects stock recorded from another screen (or by someone
        else) without a manual reload. Polling rather than websockets: no extra
        server process to keep alive, and nothing new to install.
    --}}
    <div
        class="hims-dashboard space-y-4 xl:space-y-5"
        x-data="dashboardLive({{ Js::from(route('dashboard.live')) }})"
        x-init="start()"
        @dashboard-refresh.window="refresh()"
    >
    @can(\App\Enums\Permission::ManageSystemRecovery->value)
        @php
            $pendingRecoveryCount = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('system_recovery_records')) {
                    $pendingRecoveryCount = \App\Models\SystemRecoveryRecord::pending()->count();
                }
            } catch (\Throwable) {
                $pendingRecoveryCount = 0;
            }
        @endphp
        @if ($pendingRecoveryCount > 0)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-amber-950">
                            Attention: {{ $pendingRecoveryCount }} Unresolved System {{ \Illuminate\Support\Str::plural('Incident', $pendingRecoveryCount) }}
                        </h4>
                        <p class="text-xs text-amber-800">
                            Failed transactions, data imports, or background jobs require review in the Recovery Center.
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('admin.recovery.index') }}"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-800 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-900 transition shadow-sm self-start sm:self-auto"
                >
                    <span>Open Recovery Center</span>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        @endif
    @endcan

    {{-- Key figures --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat
            label="Tracked items"
            :value="number_format($totalItems)"
            icon="cube"
            tone="primary"
            :hint="number_format($totalOnHand).' units on hand'"
            :href="route('inventory.items')"
        />

        <x-ui.stat
            label="Needs reorder"
            icon="exclamation-triangle"
            :tone="$lowStockItems > 0 ? 'warning' : 'success'"
            :href="route('inventory.alerts')"
            x-ref="lowStockTile"
        >
            <x-slot:value><span data-stat-value>{{ number_format($lowStockItems) }}</span></x-slot:value>
            <x-slot:hint><span data-stat-hint>{{ $outOfStockItems > 0
                ? number_format($outOfStockItems).' fully out of stock'
                : 'No items out of stock' }}</span></x-slot:hint>
        </x-ui.stat>

        <x-ui.stat
            label="Open alerts"
            icon="bell-alert"
            :tone="$openAlertCount > 0 ? 'danger' : 'success'"
            :href="route('inventory.alerts')"
            x-ref="openAlertTile"
        >
            <x-slot:value><span data-stat-value>{{ number_format($openAlertCount) }}</span></x-slot:value>
            <x-slot:hint><span data-stat-hint>{{ $openAlertCount > 0 ? 'Awaiting acknowledgement' : 'Nothing outstanding' }}</span></x-slot:hint>
        </x-ui.stat>

        @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
        <x-ui.stat
            label="Inventory value"
            :value="'₱'.number_format($totalInventoryValue, 2)"
            icon="chart-bar"
            tone="neutral"
            :hint="number_format($storageLocations).' storage locations'"
            :href="route('inventory.reports')"
        />
        @endcan
    </div>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 xl:gap-5">
        {{-- Stock alerts --}}
        <x-ui.card class="lg:col-span-2" :padding="false">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Stock alerts</h2>
                <p class="mt-0.5 text-xs text-neutral-500">
                    Shortages and expiry risk needing attention
                    <span class="text-neutral-400" x-text="statusLabel"></span>
                </p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.alerts')">View all</x-ui.button>
            </x-slot:actions>

            <div x-ref="alerts">
                @include('inventory.partials.alerts-table')
            </div>
        </x-ui.card>

        {{-- Expiring batches --}}
        <x-ui.card title="Expiring soon" subtitle="Next 90 days, earliest first">
            @forelse ($expiringBatches as $batch)
                @php
                    $days = (int) now()->startOfDay()->diffInDays($batch->expiry_date, false);
                @endphp
                <div class="flex items-start justify-between gap-3 py-3 border-b border-neutral-100
                            first:pt-0 last:pb-0 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-neutral-900 truncate">
                            {{ $batch->item?->name ?? 'Unknown item' }}
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Batch {{ $batch->batch_number }} &middot; {{ $batch->expiry_date->format('d M Y') }}
                        </p>
                    </div>

                    <x-ui.badge :variant="$days < 0 ? 'danger' : ($days <= 30 ? 'warning' : 'neutral')">
                        {{ $days < 0 ? abs($days).'d overdue' : $days.'d left' }}
                    </x-ui.badge>
                </div>
            @empty
                <p class="py-6 text-sm text-center text-neutral-500">
                    No batches expiring in the next 90 days.
                </p>
            @endforelse
        </x-ui.card>
    </div>

    @can(\App\Enums\Permission::ViewReports->value)
        @php
            $dashboardForecastConfig = [
                'initialForecast' => $aiForecast,
                'endpoint' => route('inventory.demand-forecast.refresh'),
            ];
        @endphp
        <div
            class="min-w-0"
            x-data="demandForecastDashboard({{ Js::from($dashboardForecastConfig) }})"
        >
            <x-ui.card :padding="false">
                <x-slot:header>
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                            <x-ui.icon name="chart-bar" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-neutral-900">AI-Based Stock Demand Forecasting</h2>
                            <p class="mt-0.5 max-w-2xl text-xs text-neutral-500">
                                Predict upcoming inventory demand using historical stock movement and consumption data.
                            </p>
                        </div>
                    </div>
                </x-slot:header>

                <x-slot:actions>
                    <x-ui.button
                        type="button"
                        variant="ghost"
                        size="sm"
                        icon="eye"
                        x-on:click="$dispatch('open-modal', 'dashboard-demand-forecast')"
                        x-bind:disabled="!forecast"
                    >
                        View Full Forecast
                    </x-ui.button>
                </x-slot:actions>

                <div class="space-y-4 p-4 sm:p-5">
                    <div class="sr-only" aria-live="polite" x-text="success"></div>
                    <div
                        x-show="error"
                        x-cloak
                        role="alert"
                        class="rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700"
                        x-text="error"
                    ></div>
                    <div
                        x-show="success"
                        x-cloak
                        class="rounded-lg border border-success-200 bg-success-50 px-3 py-2 text-sm text-success-700"
                        x-text="success"
                    ></div>

                    <div x-show="forecast" x-cloak class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            x-bind:class="sourceClasses()"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                            <span x-text="forecast?.source_label"></span>
                        </span>
                        <span class="text-xs text-neutral-500" x-text="forecast?.forecast_period"></span>
                        <span class="text-xs text-neutral-400" x-text="`Generated ${formatDate(forecast?.generated_at)}`"></span>
                    </div>

                    <div
                        x-show="forecast?.notice"
                        x-cloak
                        class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2"
                    >
                        <p class="text-xs font-semibold text-warning-700">Statistical fallback active</p>
                        <p class="mt-0.5 text-xs text-warning-700" x-text="forecast?.notice"></p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('inventory.demand-forecast.refresh') }}"
                        data-no-loading
                        @can(\App\Enums\Permission::GenerateForecasts->value)
                            x-on:submit.prevent="generateForecast()"
                        @endcan
                        class="grid min-w-0 gap-3 border-y border-neutral-200 bg-neutral-50 p-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
                    >
                        @csrf
                        <input type="hidden" name="analysis_days" value="90">
                        <input type="hidden" name="return_to" value="dashboard">
                        <div class="min-w-0 space-y-1.5">
                            <label for="dashboard-forecast-period" class="block text-xs font-medium text-neutral-700">Forecast period</label>
                            <select
                                id="dashboard-forecast-period"
                                name="forecast_days"
                                x-model="forecastDays"
                                @cannot(\App\Enums\Permission::GenerateForecasts->value) disabled @endcannot
                                class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30 disabled:bg-neutral-100"
                            >
                                <option value="7">Next 7 days</option>
                                <option value="14">Next 14 days</option>
                                <option value="30">Next 30 days</option>
                                <option value="60">Next 60 days</option>
                                <option value="90">Next 90 days</option>
                            </select>
                        </div>

                        <div class="min-w-0 space-y-1.5">
                            <label for="dashboard-forecast-category" class="block text-xs font-medium text-neutral-700">Item category</label>
                            <select
                                id="dashboard-forecast-category"
                                x-model="category"
                                class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30"
                            >
                                <option value="">All categories</option>
                                @foreach ($forecastCategories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="min-w-0 space-y-1.5">
                            <label for="dashboard-forecast-risk" class="block text-xs font-medium text-neutral-700">Risk level</label>
                            <select
                                id="dashboard-forecast-risk"
                                x-model="risk"
                                class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30"
                            >
                                <option value="">All risk levels</option>
                                <option value="high">High risk</option>
                                <option value="medium">Medium risk</option>
                                <option value="low">Low risk</option>
                            </select>
                        </div>

                        <div class="min-w-0 space-y-1.5">
                            <label for="dashboard-forecast-search" class="block text-xs font-medium text-neutral-700">Search item</label>
                            <input
                                id="dashboard-forecast-search"
                                type="search"
                                x-model.debounce.200ms="search"
                                placeholder="Name or SKU"
                                autocomplete="off"
                                class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm placeholder:text-neutral-400 focus:border-primary-500 focus:ring-primary-500/30"
                            >
                        </div>

                        <div class="flex min-w-0 items-end gap-2">
                            @can(\App\Enums\Permission::GenerateForecasts->value)
                                <x-ui.button
                                    type="submit"
                                    size="sm"
                                    icon="arrow-path"
                                    class="w-full"
                                    x-bind:disabled="loading"
                                >
                                    <span x-text="loading ? 'Generating...' : 'Generate Forecast'"></span>
                                </x-ui.button>
                            @else
                                <p class="text-xs text-neutral-500">Generation requires forecast permission.</p>
                            @endcan
                        </div>

                        <p
                            x-show="isForecastWindowChanged()"
                            x-cloak
                            class="text-xs text-warning-700 sm:col-span-2 lg:col-span-5"
                        >
                            Generate the forecast to apply the selected period.
                        </p>
                    </form>

                    <div x-show="!forecast" class="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-4 py-7 text-center">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-primary-700">
                            <x-ui.icon name="chart-bar" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-medium text-neutral-800">No forecast generated yet</p>
                        <p class="mx-auto mt-1 max-w-xl text-xs text-neutral-500">
                            Generate an advisory forecast from recorded HIMS inventory history. No inventory record or purchase order will be changed.
                        </p>
                    </div>

                    <div x-show="forecast" x-cloak>
                        <section class="min-w-0 overflow-hidden rounded-lg border border-neutral-200" aria-labelledby="dashboard-forecast-overview-title">
                            <header class="flex flex-col gap-4 border-b border-neutral-200 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h3 id="dashboard-forecast-overview-title" class="text-sm font-semibold text-neutral-900">Demand forecast</h3>
                                    <p class="mt-0.5 text-xs text-neutral-500">Historical consumption and projected inventory demand</p>
                                </div>

                                <dl class="grid grid-cols-2 gap-x-5 gap-y-3 text-xs sm:flex sm:items-center sm:divide-x sm:divide-neutral-200">
                                    <div class="sm:pr-5">
                                        <dt class="text-neutral-500">Predicted demand</dt>
                                        <dd class="mt-0.5 font-semibold tabular-nums text-neutral-900"><span x-text="formatNumber(predictedDemand())"></span> units</dd>
                                    </div>
                                    <div class="sm:px-5">
                                        <dt class="text-neutral-500">At-risk items</dt>
                                        <dd class="mt-0.5 font-semibold tabular-nums text-danger-700" x-text="formatNumber(lowStockRiskCount())"></dd>
                                    </div>
                                    <div class="sm:px-5">
                                        <dt class="text-neutral-500">Reorder units</dt>
                                        <dd class="mt-0.5 font-semibold tabular-nums text-neutral-900" x-text="formatNumber(recommendedReorder())"></dd>
                                    </div>
                                    <div class="sm:pl-5">
                                        <dt class="text-neutral-500">Confidence</dt>
                                        <dd class="mt-0.5 font-semibold text-neutral-900" x-text="confidenceLabel()"></dd>
                                    </div>
                                </dl>
                            </header>

                            <div class="min-w-0 px-3 py-4 sm:px-5">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-4 text-xs text-neutral-600">
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary-600"></span>Historical demand</span>
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-success-600"></span>Forecast demand</span>
                                        <span class="inline-flex items-center gap-1.5"><span class="h-4 border-l border-dashed border-neutral-400"></span>Forecast starts</span>
                                    </div>
                                    <span class="text-xs font-medium text-primary-700" x-text="trendLabel()"></span>
                                </div>

                                <div x-show="hasChartData()" class="min-w-0">
                                    <div class="relative min-w-0">
                                        <span class="absolute left-0 top-1 text-[10px] tabular-nums text-neutral-400" x-text="formatNumber(chartMaximum())"></span>
                                        <span class="absolute bottom-5 left-0 text-[10px] tabular-nums text-neutral-400">0</span>
                                        <svg
                                            class="h-64 w-full"
                                            viewBox="0 0 760 240"
                                            role="img"
                                            aria-labelledby="dashboard-demand-chart-title dashboard-demand-chart-description"
                                            preserveAspectRatio="none"
                                        >
                                            <title id="dashboard-demand-chart-title">Historical and forecast inventory demand</title>
                                            <desc id="dashboard-demand-chart-description">The blue line shows recorded consumption buckets. The green line shows forecast demand buckets calculated from the validated period forecast and demand trend.</desc>
                                            <defs>
                                                <linearGradient id="dashboard-historical-area" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#3395ff" stop-opacity="0.24"></stop>
                                                    <stop offset="100%" stop-color="#3395ff" stop-opacity="0.02"></stop>
                                                </linearGradient>
                                                <linearGradient id="dashboard-forecast-area" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#16a34a" stop-opacity="0.20"></stop>
                                                    <stop offset="100%" stop-color="#16a34a" stop-opacity="0.02"></stop>
                                                </linearGradient>
                                            </defs>
                                            <path d="M 42 40 H 728 M 42 96 H 728 M 42 152 H 728 M 42 208 H 728" fill="none" stroke="#e5e5e5" stroke-width="1" vector-effect="non-scaling-stroke"></path>
                                            <path d="M 385 26 V 208" fill="none" stroke="#a3a3a3" stroke-width="1.5" stroke-dasharray="5 5" vector-effect="non-scaling-stroke"></path>
                                            <path x-bind:d="areaPath(historicalPoints())" fill="url(#dashboard-historical-area)"></path>
                                            <path x-bind:d="areaPath(forecastPoints())" fill="url(#dashboard-forecast-area)"></path>
                                            <path x-bind:d="seriesPath(historicalPoints())" fill="none" stroke="#1c75f5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>
                                            <path x-bind:d="seriesPath(forecastPoints())" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>
                                            <template x-for="point in forecastPoints()" x-bind:key="point.date">
                                                <circle x-bind:cx="point.x" x-bind:cy="point.y" r="3.5" fill="#ffffff" stroke="#16a34a" stroke-width="2" vector-effect="non-scaling-stroke"></circle>
                                            </template>
                                        </svg>
                                    </div>
                                    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-3 text-[10px] text-neutral-500 sm:text-xs">
                                        <div class="flex justify-between gap-2"><span x-text="chartStartLabel(historicalSeries())"></span><span x-text="chartEndLabel(historicalSeries())"></span></div>
                                        <span class="font-medium text-neutral-600">Forecast</span>
                                        <div class="flex justify-between gap-2"><span x-text="chartStartLabel(forecastSeries())"></span><span x-text="chartEndLabel(forecastSeries())"></span></div>
                                    </div>
                                </div>

                                <div x-show="!hasChartData()" class="border-y border-neutral-100 py-12 text-center text-sm text-neutral-500">
                                    Generate a fresh forecast to plot the historical and predicted demand curves.
                                </div>
                            </div>

                            <div class="border-t border-neutral-200">
                                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-neutral-900">Forecasted inventory items</h3>
                                        <p class="mt-0.5 text-xs text-neutral-500"><span x-text="filteredItems().length"></span> matching results; highest risk first</p>
                                    </div>
                                    <button type="button" x-on:click="clearFilters()" class="text-xs font-medium text-primary-700 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                        Clear filters
                                    </button>
                                </div>

                                <div class="min-w-0 overflow-x-auto">
                                    <table class="min-w-[760px] w-full text-left text-xs">
                                        <thead class="border-y border-neutral-200 bg-neutral-50 text-neutral-500">
                                            <tr>
                                                <th scope="col" class="px-4 py-2 font-medium">Item</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium">On hand</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium">Historical</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium">Forecast</th>
                                                <th scope="col" class="px-3 py-2 font-medium">Stock risk</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium">Reorder</th>
                                                <th scope="col" class="px-4 py-2 font-medium">Confidence</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-neutral-100">
                                            <template x-for="item in topItems()" x-bind:key="item.item_id">
                                                <tr class="hover:bg-neutral-50">
                                                    <td class="px-4 py-2.5">
                                                        <p class="font-medium text-neutral-900" x-text="item.item_name"></p>
                                                        <p class="text-neutral-500" x-text="item.sku"></p>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums text-neutral-700" x-text="formatNumber(item.current_stock)"></td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums text-neutral-700" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></td>
                                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-neutral-900" x-text="formatNumber(item.predicted_demand)"></td>
                                                    <td class="px-3 py-2.5">
                                                        <span class="inline-flex rounded-full px-2 py-0.5 font-medium ring-1 ring-inset" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-neutral-900" x-text="formatNumber(item.recommended_reorder_quantity)"></td>
                                                    <td class="px-4 py-2.5 capitalize text-neutral-700" x-text="item.confidence"></td>
                                                </tr>
                                            </template>
                                            <tr x-show="topItems().length === 0">
                                                <td colspan="7" class="px-4 py-8 text-center text-sm text-neutral-500">No forecast items match the current filters.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <aside class="flex items-start gap-3 border-t border-neutral-200 bg-neutral-50 px-4 py-3">
                                <x-ui.icon name="arrow-trending-up" class="mt-0.5 h-4 w-4 shrink-0 text-primary-700" />
                                <div class="min-w-0">
                                    <h3 class="text-xs font-semibold text-neutral-900" x-text="forecast?.source === 'ai' ? 'AI Insight' : 'Forecast Insight'"></h3>
                                    <p class="mt-0.5 text-sm text-neutral-700" x-text="insight()"></p>
                                </div>
                            </aside>
                        </section>

                        <div class="mt-3 flex flex-col gap-2 text-xs text-neutral-400 sm:flex-row sm:items-center sm:justify-between">
                            <p>Recommendations are advisory and never modify inventory or purchase orders automatically.</p>
                            <button type="button" x-on:click="$dispatch('open-modal', 'dashboard-demand-forecast')" class="self-start font-medium text-primary-700 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:self-auto">
                                View all <span x-text="filteredItems().length"></span> results
                            </button>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.modal name="dashboard-demand-forecast" title="Full Demand Forecast" maxWidth="2xl">
                <div class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    x-bind:class="sourceClasses()"
                                    x-text="forecast?.source_label"
                                ></span>
                                <span class="text-xs text-neutral-500" x-text="forecast?.forecast_period"></span>
                            </div>
                            <p class="mt-1 text-xs text-neutral-500">Dashboard category, risk, and search filters apply to these results.</p>
                        </div>
                        <x-ui.button type="button" variant="secondary" size="sm" x-on:click="clearFilters()">Clear Filters</x-ui.button>
                    </div>

                    <div x-show="filteredItems().length === 0" class="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-4 py-8 text-center">
                        <p class="text-sm font-medium text-neutral-800">No matching forecast items</p>
                        <p class="mt-1 text-xs text-neutral-500">Clear or adjust the dashboard filters to see more results.</p>
                    </div>

                    <div class="max-w-full overflow-x-auto rounded-lg border border-neutral-200">
                        <table class="min-w-[980px] w-full text-left text-xs">
                            <thead class="sticky top-0 bg-neutral-50 text-neutral-500">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-medium">Item</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Current</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Historical</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Forecast</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Risk</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Reorder</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Confidence</th>
                                    <th scope="col" class="w-[30%] px-3 py-2 font-medium">Explanation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <template x-for="item in filteredItems()" x-bind:key="item.item_id">
                                    <tr class="align-top hover:bg-neutral-50">
                                        <td class="px-3 py-3">
                                            <p class="font-medium text-neutral-900" x-text="item.item_name"></p>
                                            <p class="text-neutral-500" x-text="`${item.sku}${item.category ? ` · ${item.category}` : ''}`"></p>
                                        </td>
                                        <td class="px-3 py-3 text-right tabular-nums text-neutral-700" x-text="formatNumber(item.current_stock)"></td>
                                        <td class="px-3 py-3 text-right tabular-nums text-neutral-700" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></td>
                                        <td class="px-3 py-3 text-right font-semibold tabular-nums text-neutral-900" x-text="formatNumber(item.predicted_demand)"></td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex rounded-full px-2 py-0.5 font-medium ring-1 ring-inset" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                        </td>
                                        <td class="px-3 py-3 text-right font-semibold tabular-nums text-neutral-900" x-text="formatNumber(item.recommended_reorder_quantity)"></td>
                                        <td class="px-3 py-3 capitalize text-neutral-700">
                                            <span x-text="item.confidence"></span>
                                            <span class="block text-neutral-500" x-text="`${item.demand_trend} trend`"></span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="whitespace-normal leading-5 text-neutral-600" x-text="item.explanation"></p>
                                            <p x-show="item.limited_data" class="mt-1 font-medium text-warning-700">Limited movement history</p>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <x-slot:footer>
                    <p class="mr-auto text-xs text-neutral-500">Read-only recommendations based on validated forecast output.</p>
                    <x-ui.button type="button" variant="secondary" size="sm" x-data x-on:click="$dispatch('close-modal', 'dashboard-demand-forecast')">Close</x-ui.button>
                    <x-ui.button size="sm" :href="route('inventory.demand-forecast')">Open Forecasting Workspace</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endcan

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-12 2xl:gap-5">
        {{-- Operational snapshot. The supplier and purchase-order figures are
             procurement's business, so an account without it sees the stock
             lines only rather than counts it cannot act on. --}}
        <x-ui.card
            title="Operational snapshot"
            @class([
                'lg:col-span-2',
                '2xl:col-span-3' => auth()->user()->can(\App\Enums\Permission::ViewProcurementSensitiveData->value),
                '2xl:col-span-4' => auth()->user()->cannot(\App\Enums\Permission::ViewProcurementSensitiveData->value),
            ])
        >
            <dl class="divide-y divide-neutral-100">
                @foreach (array_merge(
                    auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value) ? [
                        ['Suppliers', number_format($totalSuppliers), 'text-neutral-900'],
                        ['Active suppliers', number_format($activeSuppliers), 'text-success-700'],
                    ] : [],
                    [['Storage locations', number_format($storageLocations), 'text-neutral-900']],
                    auth()->user()->can(\App\Enums\Permission::ViewProcurementSensitiveData->value) ? [
                        ['Pending purchase orders', number_format($pendingPoCount), $pendingPoCount > 0 ? 'text-warning-700' : 'text-neutral-900'],
                    ] : [],
                    [['Out of stock', number_format($outOfStockItems), $outOfStockItems > 0 ? 'text-danger-700' : 'text-neutral-900']],
                ) as [$label, $figure, $tone])
                    <div class="flex items-center justify-between py-2.5 first:pt-0 last:pb-0">
                        <dt class="text-sm text-neutral-600">{{ $label }}</dt>
                        <dd class="text-sm font-semibold tabular-nums {{ $tone }}">{{ $figure }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        {{-- Pending purchase orders. Hidden without manage_procurement: the
             card's "View all" leads to a screen that would refuse them, and
             the rows name suppliers and amounts they have no business in. --}}
        @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
        <x-ui.card class="dashboard-pending-orders 2xl:col-span-4" :padding="false">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Pending purchase orders</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Awaiting approval or delivery</p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.purchases')">View all</x-ui.button>
            </x-slot:actions>

            <x-ui.table class="2xl:table-fixed" :sticky-header="false">
                <colgroup>
                    <col class="2xl:w-[28%]">
                    <col class="2xl:w-[28%]">
                    <col class="2xl:w-[24%]">
                    <col class="2xl:w-[20%]">
                </colgroup>

                <x-ui.table.head>
                    <x-ui.table.th>PO number</x-ui.table.th>
                    <x-ui.table.th>Supplier</x-ui.table.th>
                    <x-ui.table.th numeric>Amount</x-ui.table.th>
                    <x-ui.table.th>Status</x-ui.table.th>
                </x-ui.table.head>

                <tbody>
                    @forelse ($pendingPurchaseOrders as $po)
                        <x-ui.table.row>
                            <x-ui.table.td class="break-words">
                                <span class="font-medium text-neutral-900">{{ $po->po_number }}</span>
                                @if ($po->requested_at)
                                    <span class="block text-xs text-neutral-500">
                                        {{ $po->requested_at->format('d M Y') }}
                                    </span>
                                @endif
                            </x-ui.table.td>
                            <x-ui.table.td class="break-words" muted>{{ $po->supplier?->name ?? '—' }}</x-ui.table.td>
                            <x-ui.table.td class="whitespace-nowrap text-xs 2xl:text-sm" numeric>₱{{ number_format((float) $po->total_amount, 2) }}</x-ui.table.td>
                            <x-ui.table.td>
                                <x-ui.badge :status="$po->status" />
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="4"
                            icon="clipboard-document-list"
                            title="No pending purchase orders"
                            message="Approved and fulfilled orders are hidden from this view."
                        />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
        @endcan

        {{-- Recent stock movements --}}
        <x-ui.card
            :padding="false"
            @class([
                '2xl:col-span-5' => auth()->user()->can(\App\Enums\Permission::ViewProcurementSensitiveData->value),
                'lg:col-span-2 2xl:col-span-8' => auth()->user()->cannot(\App\Enums\Permission::ViewProcurementSensitiveData->value),
            ])
        >
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Recent stock movements</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Latest issued, received and transferred stock</p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.stock-movements')">View all</x-ui.button>
            </x-slot:actions>

            <x-ui.table :sticky-header="false">
                <x-ui.table.head>
                    <x-ui.table.th>Item</x-ui.table.th>
                    <x-ui.table.th>Type</x-ui.table.th>
                    <x-ui.table.th numeric>Qty</x-ui.table.th>
                    <x-ui.table.th>When</x-ui.table.th>
                </x-ui.table.head>

                <tbody>
                    @forelse ($recentMovements as $movement)
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <span class="font-medium text-neutral-900">
                                    {{ $movement->item?->name ?? 'Unknown item' }}
                                </span>
                                @if ($movement->toLocation || $movement->fromLocation)
                                    <span class="block text-xs text-neutral-500">
                                        {{ $movement->fromLocation?->name ?? '—' }}
                                        &rarr;
                                        {{ $movement->toLocation?->name ?? '—' }}
                                    </span>
                                @endif
                            </x-ui.table.td>

                            <x-ui.table.td>
                                <x-ui.badge :status="$movement->movement_type->value">
                                    {{ $movement->movement_type->label() }}
                                </x-ui.badge>
                            </x-ui.table.td>

                            <x-ui.table.td numeric>{{ number_format((int) $movement->quantity) }}</x-ui.table.td>

                            <x-ui.table.td muted>
                                {{ $movement->moved_at?->diffForHumans() ?? '—' }}
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="4"
                            icon="arrows-right-left"
                            title="No stock movements yet"
                            message="Recorded stock in, stock out and transfers will appear here."
                        >
                            @canany([\App\Enums\Permission::IssueStock->value, \App\Enums\Permission::RecordMovements->value, \App\Enums\Permission::TransferStock->value])
                                <x-slot:action>
                                    <x-ui.button size="sm" icon="plus" :href="route('inventory.stock-movements')">
                                        Record movement
                                    </x-ui.button>
                                </x-slot:action>
                            @endcanany
                        </x-ui.table.empty>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>
    </div>{{-- /dashboardLive --}}
</x-app-layout>
