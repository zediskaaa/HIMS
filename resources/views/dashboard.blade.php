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
                        class="grid min-w-0 gap-3 border-y border-neutral-200 bg-neutral-50 p-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end"
                    >
                        @csrf
                        <input type="hidden" name="analysis_days" value="90">
                        <input type="hidden" name="return_to" value="dashboard">

                        {{-- Item Selection --}}
                        <div class="min-w-0 space-y-1.5 sm:col-span-2 lg:col-span-2">
                            <div class="flex items-center justify-between">
                                <label for="dashboard-forecast-item" class="block text-xs font-medium text-neutral-700">Select Item</label>
                                <button
                                    type="button"
                                    x-show="selectedItemId"
                                    x-cloak
                                    x-on:click="selectedItemId = ''"
                                    class="text-[11px] font-medium text-primary-600 hover:text-primary-800"
                                >
                                    View All Items
                                </button>
                            </div>
                            <select
                                id="dashboard-forecast-item"
                                x-model="selectedItemId"
                                class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30"
                            >
                                <option value="">All Inventory Items (Overall Hospital Demand)</option>
                                <template x-for="item in allItems()" x-bind:key="item.item_id">
                                    <option x-bind:value="String(item.item_id)" x-text="`${item.item_name} (${item.sku}) · ${item.risk_level.toUpperCase()} risk`"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Forecast Period --}}
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

                        {{-- Item category --}}
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

                        {{-- Risk level --}}
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

                        {{-- Search item --}}
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

                        <div class="flex min-w-0 items-end sm:col-span-2 lg:col-span-6">
                            @can(\App\Enums\Permission::GenerateForecasts->value)
                                <x-ui.button
                                    type="submit"
                                    size="sm"
                                    icon="arrow-path"
                                    class="w-full sm:w-auto"
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
                            class="text-xs text-warning-700 sm:col-span-2 lg:col-span-6"
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

                    <div x-show="forecast" x-cloak class="space-y-4">
                        {{-- Compact Forecast Summary Tiles --}}
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            <div class="rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
                                <dt class="text-xs font-medium text-neutral-500">Forecast Period</dt>
                                <dd class="mt-1 text-base font-semibold tabular-nums text-neutral-900 sm:text-lg" x-text="forecast?.forecast_period || `Next ${forecastDays} days`"></dd>
                                <p class="mt-0.5 text-[11px] text-neutral-400">Demand forecast window</p>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
                                <dt class="text-xs font-medium text-neutral-500">Predicted demand</dt>
                                <dd class="mt-1 text-base font-semibold tabular-nums text-neutral-900 sm:text-lg">
                                    <span x-text="formatNumber(summaryPredictedDemand())"></span>
                                    <span class="text-xs font-normal text-neutral-500">units</span>
                                </dd>
                                <p class="mt-0.5 text-[11px] font-medium text-primary-700" x-text="trendLabel()"></p>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
                                <dt class="text-xs font-medium text-neutral-500">Current Stock</dt>
                                <dd class="mt-1 text-base font-semibold tabular-nums text-neutral-900 sm:text-lg">
                                    <span x-text="formatNumber(summaryCurrentStock())"></span>
                                    <span class="text-xs font-normal text-neutral-500">units</span>
                                </dd>
                                <p class="mt-0.5 text-[11px] text-neutral-400" x-text="selectedItem() ? (selectedItem().category || 'In warehouse') : 'Filtered total on hand'"></p>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
                                <dt class="text-xs font-medium text-neutral-500">Estimated Stock Risk</dt>
                                <dd class="mt-1">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        x-bind:class="riskClasses(summaryStockRisk())"
                                        x-text="`${summaryStockRisk()} risk`"
                                    ></span>
                                </dd>
                                <p class="mt-1 text-[11px] text-neutral-500">
                                    <span x-text="lowStockRiskCount()"></span> <span class="text-danger-700 font-medium">At-risk items</span>
                                </p>
                            </div>

                            <div class="col-span-2 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:col-span-1">
                                <dt class="text-xs font-medium text-neutral-500">Reorder units</dt>
                                <dd class="mt-1 text-base font-semibold tabular-nums text-neutral-900 sm:text-lg">
                                    <span x-text="formatNumber(summaryRecommendedReorder())"></span>
                                    <span class="text-xs font-normal text-neutral-500">units</span>
                                </dd>
                                <p class="mt-0.5 text-[11px] text-neutral-400">
                                    Confidence: <span class="font-medium text-neutral-700 capitalize" x-text="confidenceLabel()"></span>
                                </p>
                            </div>
                        </div>

                        {{-- Combined Demand vs Forecast Card --}}
                        <section class="min-w-0 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm" aria-labelledby="dashboard-forecast-overview-title">
                            <header class="flex flex-col gap-3 border-b border-neutral-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 id="dashboard-forecast-overview-title" class="text-sm font-semibold text-neutral-900">Demand vs Forecast</h3>
                                        <template x-if="selectedItem()">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20">
                                                <span class="truncate max-w-[200px]" x-text="selectedItem().item_name"></span>
                                                <button type="button" x-on:click="selectedItemId = ''" class="hover:text-primary-900" title="Clear item focus">
                                                    <x-ui.icon name="x-mark" class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="mt-0.5 text-xs text-neutral-500">
                                        Demand forecast · Historical consumption and projected inventory demand
                                    </p>
                                </div>

                                {{-- Simple Clean Legend with Interactive Scrubber Hint --}}
                                <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs text-neutral-600">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="h-1 w-4 rounded-full bg-primary-600"></span>
                                        <span class="font-medium text-neutral-800">Historical demand</span>
                                    </span>
                                    <span class="inline-flex items-center gap-2">
                                        <span class="h-0.5 w-4 border-t-2 border-dashed border-emerald-600"></span>
                                        <span class="font-medium text-neutral-800">Forecast demand</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-neutral-500">
                                        <span class="h-3.5 border-l border-dashed border-neutral-300"></span>
                                        <span>Forecast starts</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                        <span>Drag or hover line to inspect</span>
                                    </span>
                                </div>
                            </header>

                            <div class="min-w-0 px-3 py-4 sm:px-5">
                                <div x-show="hasChartData()" class="relative min-w-0 select-none">
                                    {{-- Interactive Floating Tooltip --}}
                                    <div
                                        x-show="activePoint"
                                        x-cloak
                                        class="pointer-events-none absolute z-20 transition-all duration-75"
                                        x-bind:style="`left: ${Math.max(12, Math.min(88, activePoint?.percentageX ?? 50))}%; top: ${Math.max(6, (activePoint?.percentageY ?? 50) - 16)}%; transform: translate(-50%, -100%);`"
                                    >
                                        <div class="rounded-lg border border-neutral-700 bg-neutral-900/95 px-3 py-2 text-white shadow-xl backdrop-blur-sm">
                                            <div class="flex items-center justify-between gap-3 text-[11px]">
                                                <span class="text-neutral-300 font-medium" x-text="activePoint?.formattedDate"></span>
                                                <span
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                                    x-bind:class="activePoint?.type === 'actual' ? 'bg-primary-500/30 text-primary-300 border border-primary-400/30' : 'bg-emerald-500/30 text-emerald-300 border border-emerald-400/30'"
                                                    x-text="activePoint?.type === 'actual' ? 'Historical demand' : 'Forecast demand'"
                                                ></span>
                                            </div>
                                            <p class="mt-1 text-sm font-bold tabular-nums text-white">
                                                <span x-text="formatNumber(activePoint?.quantity)"></span>
                                                <span class="text-xs font-normal text-neutral-300">units</span>
                                                <template x-if="activePoint?.type === 'forecast'">
                                                    <span class="ml-1 text-[10px] font-normal text-emerald-400">· AI Projected</span>
                                                </template>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="relative min-w-0">
                                        {{-- Y-axis scale figures --}}
                                        <span class="absolute left-0 top-1 text-[10px] tabular-nums text-neutral-400" x-text="formatNumber(chartMaximum())"></span>
                                        <span class="absolute left-0 top-1/2 -translate-y-1/2 text-[10px] tabular-nums text-neutral-400" x-text="formatNumber(Math.round(chartMaximum() / 2))"></span>
                                        <span class="absolute bottom-5 left-0 text-[10px] tabular-nums text-neutral-400">0</span>

                                        <svg
                                            class="h-64 w-full cursor-crosshair select-none touch-none"
                                            viewBox="0 0 760 240"
                                            role="img"
                                            aria-labelledby="dashboard-demand-chart-title dashboard-demand-chart-description"
                                            preserveAspectRatio="none"
                                            x-on:pointerdown="onChartPointerDown($event)"
                                            x-on:pointermove="onChartPointerMove($event)"
                                            x-on:pointerup="onChartPointerUp($event)"
                                            x-on:pointercancel="onChartPointerCancel($event)"
                                            tabindex="0"
                                            x-on:keydown.arrow-left.prevent="stepPoint(-1)"
                                            x-on:keydown.arrow-right.prevent="stepPoint(1)"
                                        >
                                            <title id="dashboard-demand-chart-title">Demand vs Forecast: Historical and forecast inventory demand</title>
                                            <desc id="dashboard-demand-chart-description">The blue solid line shows recorded consumption buckets. The green dashed line shows forecast demand buckets calculated from the validated period forecast and demand trend.</desc>
                                            <defs>
                                                <linearGradient id="dashboard-historical-area" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#1c75f5" stop-opacity="0.22"></stop>
                                                    <stop offset="100%" stop-color="#1c75f5" stop-opacity="0.01"></stop>
                                                </linearGradient>
                                                <linearGradient id="dashboard-forecast-area" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.18"></stop>
                                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.01"></stop>
                                                </linearGradient>
                                            </defs>

                                            {{-- Horizontal Grid Lines --}}
                                            <line x1="50" y1="40" x2="725" y2="40" stroke="#f1f5f9" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line x1="50" y1="95" x2="725" y2="95" stroke="#f1f5f9" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line x1="50" y1="150" x2="725" y2="150" stroke="#f1f5f9" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line x1="50" y1="205" x2="725" y2="205" stroke="#e2e8f0" stroke-width="1" vector-effect="non-scaling-stroke"></line>

                                            {{-- Subtle Forecast Boundary Line --}}
                                            <line x1="470" y1="20" x2="470" y2="205" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="2 3" vector-effect="non-scaling-stroke"></line>

                                            {{-- Gradient Fills --}}
                                            <path x-bind:d="historicalAreaPath()" fill="url(#dashboard-historical-area)"></path>
                                            <path x-bind:d="forecastAreaPath()" fill="url(#dashboard-forecast-area)"></path>

                                            {{-- Actual Demand Line (Solid Blue) --}}
                                            <path x-bind:d="seriesPath(historicalPoints())" fill="none" stroke="#1c75f5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>

                                            {{-- AI Forecast Line (Dashed Green) --}}
                                            <path x-bind:d="seriesPath(forecastPointsWithTransition())" fill="none" stroke="#10b981" stroke-width="3" stroke-dasharray="6 4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>

                                            {{-- Historical Data Markers --}}
                                            <template x-for="point in historicalPoints()" x-bind:key="`hist-${point.date}`">
                                                <circle x-bind:cx="point.x" x-bind:cy="point.y" r="3" fill="#1c75f5" fill-opacity="0.6" vector-effect="non-scaling-stroke"></circle>
                                            </template>

                                            {{-- Forecast Point Markers --}}
                                            <template x-for="point in forecastPoints()" x-bind:key="`fore-${point.date}`">
                                                <circle x-bind:cx="point.x" x-bind:cy="point.y" r="3.5" fill="#ffffff" stroke="#10b981" stroke-width="2" vector-effect="non-scaling-stroke"></circle>
                                            </template>

                                            {{-- Interactive Timeline Scrubber Line & Handle (Moves dynamically with hover, drag, and click) --}}
                                            <template x-if="activePoint">
                                                <g class="transition-all duration-75">
                                                    {{-- Vertical Scrubber Guideline --}}
                                                    <line
                                                        x-bind:x1="activePoint.x"
                                                        y1="20"
                                                        x-bind:x2="activePoint.x"
                                                        y2="205"
                                                        stroke="#1e293b"
                                                        stroke-width="1.5"
                                                        stroke-dasharray="4 4"
                                                        vector-effect="non-scaling-stroke"
                                                    ></line>

                                                    {{-- Glowing Halo --}}
                                                    <circle
                                                        x-bind:cx="activePoint.x"
                                                        x-bind:cy="activePoint.y"
                                                        r="11"
                                                        x-bind:fill="activePoint.type === 'forecast' ? '#10b981' : '#1c75f5'"
                                                        fill-opacity="0.2"
                                                        class="animate-pulse"
                                                    ></circle>

                                                    {{-- Outer Ring Handle --}}
                                                    <circle
                                                        x-bind:cx="activePoint.x"
                                                        x-bind:cy="activePoint.y"
                                                        r="6"
                                                        fill="#ffffff"
                                                        stroke="#0f172a"
                                                        stroke-width="2.5"
                                                        vector-effect="non-scaling-stroke"
                                                        class="cursor-grab active:cursor-grabbing"
                                                    ></circle>

                                                    {{-- Center Bead --}}
                                                    <circle
                                                        x-bind:cx="activePoint.x"
                                                        x-bind:cy="activePoint.y"
                                                        r="2.5"
                                                        x-bind:fill="activePoint.type === 'forecast' ? '#10b981' : '#1c75f5'"
                                                        vector-effect="non-scaling-stroke"
                                                    ></circle>
                                                </g>
                                            </template>
                                        </svg>
                                    </div>

                                    {{-- Chronological X-axis Labels --}}
                                    <div class="mt-2 grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-neutral-100 pt-2 text-xs text-neutral-500">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="tabular-nums font-medium text-neutral-700" x-text="chartStartLabel(historicalSeries())"></span>
                                            <span class="hidden text-neutral-400 sm:inline">Historical demand</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 px-3 font-semibold text-neutral-800">
                                            <span class="h-2 w-2 rounded-full bg-neutral-600"></span>
                                            <span>Forecast starts (<span class="tabular-nums text-primary-700" x-text="chartTransitionLabel()"></span>)</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="hidden text-neutral-400 sm:inline">Forecast demand</span>
                                            <span class="tabular-nums font-semibold text-emerald-700" x-text="chartEndLabel(forecastSeries())"></span>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="!hasChartData()" class="border-y border-neutral-100 py-12 text-center text-sm text-neutral-500">
                                    Generate a fresh forecast to plot the historical and predicted demand curves.
                                </div>
                            </div>

                            {{-- Compact Top Risk Items Table --}}
                            <div class="border-t border-neutral-200">
                                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-neutral-900">Forecasted inventory items</h4>
                                        <p class="mt-0.5 text-xs text-neutral-500">
                                            <span x-text="filteredItems().length"></span> matching results; highest risk first. Click an item to plot its demand curve.
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            x-show="selectedItemId"
                                            x-cloak
                                            x-on:click="selectedItemId = ''"
                                            class="text-xs font-medium text-primary-700 hover:text-primary-800"
                                        >
                                            Show all items
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="clearFilters()"
                                            class="text-xs font-medium text-neutral-500 hover:text-neutral-700"
                                        >
                                            Clear filters
                                        </button>
                                    </div>
                                </div>

                                <div class="min-w-0 overflow-x-auto">
                                    <table class="min-w-[760px] w-full text-left text-xs">
                                        <thead class="border-y border-neutral-200 bg-neutral-50 text-neutral-500">
                                            <tr>
                                                <th scope="col" class="px-4 py-2.5 font-medium">Item</th>
                                                <th scope="col" class="px-3 py-2.5 text-right font-medium">Current Stock</th>
                                                <th scope="col" class="px-3 py-2.5 text-right font-medium">Historical</th>
                                                <th scope="col" class="px-3 py-2.5 text-right font-medium">Predicted Demand</th>
                                                <th scope="col" class="px-3 py-2.5 font-medium">Stock risk</th>
                                                <th scope="col" class="px-3 py-2.5 text-right font-medium">Reorder</th>
                                                <th scope="col" class="px-4 py-2.5 font-medium">Confidence</th>
                                                <th scope="col" class="px-3 py-2.5 text-right font-medium">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-neutral-100">
                                            <template x-for="item in topItems()" x-bind:key="item.item_id">
                                                <tr
                                                    class="cursor-pointer transition-colors hover:bg-neutral-50"
                                                    x-bind:class="selectedItemId === String(item.item_id) ? 'bg-primary-50/80 ring-1 ring-inset ring-primary-300' : ''"
                                                    x-on:click="selectItem(item.item_id)"
                                                >
                                                    <td class="px-4 py-2.5">
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                x-show="selectedItemId === String(item.item_id)"
                                                                class="h-1.5 w-1.5 rounded-full bg-primary-600"
                                                                aria-hidden="true"
                                                            ></span>
                                                            <div>
                                                                <p class="font-semibold text-neutral-900" x-text="item.item_name"></p>
                                                                <p class="text-neutral-500" x-text="item.sku"></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums text-neutral-700" x-text="formatNumber(item.current_stock)"></td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums text-neutral-700" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></td>
                                                    <td class="px-3 py-2.5 text-right font-bold tabular-nums text-neutral-900" x-text="formatNumber(item.predicted_demand)"></td>
                                                    <td class="px-3 py-2.5">
                                                        <span class="inline-flex rounded-full px-2 py-0.5 font-semibold text-[11px] ring-1 ring-inset" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-neutral-900" x-text="formatNumber(item.recommended_reorder_quantity)"></td>
                                                    <td class="px-4 py-2.5 capitalize text-neutral-700" x-text="item.confidence"></td>
                                                    <td class="px-3 py-2.5 text-right">
                                                        <button
                                                            type="button"
                                                            class="rounded px-2 py-1 text-xs font-medium"
                                                            x-bind:class="selectedItemId === String(item.item_id) ? 'bg-primary-600 text-white' : 'text-primary-700 hover:bg-primary-50'"
                                                            x-text="selectedItemId === String(item.item_id) ? 'Active' : 'Plot Curve'"
                                                        ></button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="topItems().length === 0">
                                                <td colspan="8" class="px-4 py-8 text-center text-sm text-neutral-500">No forecast items match the current filters.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Advisory Insight --}}
                            <aside class="flex items-start gap-3 border-t border-neutral-200 bg-neutral-50 px-4 py-3">
                                <x-ui.icon name="arrow-trending-up" class="mt-0.5 h-4 w-4 shrink-0 text-primary-700" />
                                <div class="min-w-0">
                                    <h4 class="text-xs font-semibold text-neutral-900" x-text="forecast?.source === 'ai' ? 'AI Insight' : 'Forecast Insight'"></h4>
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

    {{-- HIMS AI Inventory Assistant Floating Chatbot --}}
    @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::ViewReports->value])
    <aside
        x-data="himsAiAssistant({ endpoint: '{{ route('dashboard.ai-assistant') }}' })"
        x-cloak
        class="fixed bottom-5 right-5 z-50 select-none print:hidden sm:bottom-6 sm:right-6"
        aria-label="HIMS AI Assistant"
    >
        {{-- Floating Trigger Button --}}
        <button
            type="button"
            x-on:click="toggle()"
            x-bind:aria-expanded="isOpen.toString()"
            class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-xl transition-all duration-200 hover:scale-105 hover:bg-primary-700 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-primary-500/30"
            title="Open HIMS AI Assistant"
            aria-label="Toggle HIMS AI Assistant"
        >
            <span class="sr-only">Toggle HIMS AI Assistant</span>
            {{-- Pulse ring on idle --}}
            <span x-show="!isOpen" class="absolute -inset-0.5 rounded-full bg-primary-400/40 opacity-75 animate-ping"></span>

            {{-- Sparkle / Chat Icon when closed --}}
            <svg x-show="!isOpen" class="relative h-6 w-6 transition-transform duration-200 group-hover:rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
            </svg>

            {{-- Close X icon when open --}}
            <svg x-show="isOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>

        {{-- Compact Chat Panel --}}
        <section
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            x-on:keydown.escape.window="close()"
            role="dialog"
            aria-labelledby="hims-assistant-title"
            aria-modal="false"
            class="absolute bottom-16 right-0 flex h-[540px] max-h-[calc(100vh-6rem)] w-[380px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl ring-1 ring-black/5 sm:w-[420px]"
        >
            {{-- Header --}}
            <header class="flex items-center justify-between border-b border-neutral-200 bg-neutral-900 px-4 py-3 text-white">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white shadow-sm">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="hims-assistant-title" class="text-xs font-bold uppercase tracking-wider text-white sm:text-sm">
                            HIMS AI Assistant
                        </h3>
                        <p class="text-[11px] text-neutral-400">
                            Ask about inventory, demand, and stock levels.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        x-on:click="clearChat()"
                        title="Clear conversation"
                        class="rounded p-1 text-neutral-400 transition-colors hover:bg-neutral-800 hover:text-white"
                        aria-label="Clear conversation"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="close()"
                        title="Close assistant"
                        class="rounded p-1 text-neutral-400 transition-colors hover:bg-neutral-800 hover:text-white"
                        aria-label="Close assistant"
                    >
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </header>

            {{-- Message Area --}}
            <div
                x-ref="messagesContainer"
                class="flex-1 space-y-3 overflow-y-auto p-4 text-xs select-text"
            >
                {{-- Welcome / Empty State with Suggested Prompts --}}
                <template x-if="messages.length === 0">
                    <div class="space-y-3 py-2">
                        <div class="flex gap-2.5">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                            </div>
                            <div class="rounded-2xl rounded-tl-none border border-neutral-200 bg-neutral-50 px-3.5 py-2.5 text-neutral-800 shadow-xs">
                                <p class="font-medium text-neutral-900">Hello! I'm your HIMS Inventory Assistant.</p>
                                <p class="mt-1 text-neutral-600">
                                    I analyze real-time hospital stock levels, consumption movements, and AI demand forecasts. How can I help you today?
                                </p>
                            </div>
                        </div>

                        {{-- Suggested Prompt Chips --}}
                        <div class="space-y-1.5 pt-2">
                            <p class="text-[11px] font-semibold text-neutral-500 uppercase tracking-wider">Suggested questions:</p>
                            <div class="flex flex-col gap-1.5">
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('Which items are low in stock?')"
                                    class="flex items-center justify-between rounded-lg border border-neutral-200 bg-white px-3 py-2 text-left text-xs font-medium text-neutral-700 transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span>Which items are low in stock?</span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('What should we reorder?')"
                                    class="flex items-center justify-between rounded-lg border border-neutral-200 bg-white px-3 py-2 text-left text-xs font-medium text-neutral-700 transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span>What should we reorder?</span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('Explain the demand forecast')"
                                    class="flex items-center justify-between rounded-lg border border-neutral-200 bg-white px-3 py-2 text-left text-xs font-medium text-neutral-700 transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span>Explain the demand forecast</span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('Summarize inventory status')"
                                    class="flex items-center justify-between rounded-lg border border-neutral-200 bg-white px-3 py-2 text-left text-xs font-medium text-neutral-700 transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span>Summarize inventory status</span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Conversation Messages --}}
                <template x-for="(msg, index) in messages" x-bind:key="index">
                    <div
                        class="flex gap-2.5"
                        x-bind:class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        {{-- Assistant Avatar --}}
                        <template x-if="msg.role !== 'user'">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                            </div>
                        </template>

                        {{-- Bubble --}}
                        <div
                            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 shadow-xs"
                            x-bind:class="msg.role === 'user'
                                ? 'rounded-br-none bg-primary-600 text-white'
                                : (msg.isError ? 'rounded-tl-none border border-danger-200 bg-danger-50 text-danger-800' : 'rounded-tl-none border border-neutral-200 bg-neutral-50 text-neutral-800')"
                        >
                            <div
                                class="prose prose-xs max-w-none text-xs leading-relaxed"
                                x-bind:class="msg.role === 'user' ? 'text-white' : 'text-neutral-800'"
                                x-html="formatMarkdown(msg.content)"
                            ></div>
                            <div
                                class="mt-1 flex items-center justify-end gap-1.5 text-[10px]"
                                x-bind:class="msg.role === 'user' ? 'text-primary-100' : 'text-neutral-400'"
                            >
                                <span x-text="msg.time"></span>
                                <template x-if="msg.source === 'ai'">
                                    <span class="rounded bg-primary-100/50 px-1 py-0.2 text-[9px] font-medium text-primary-700">AI</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Typing / Loading Indicator --}}
                <div x-show="isLoading" class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                    <div class="rounded-2xl rounded-tl-none border border-neutral-200 bg-neutral-50 px-3.5 py-2.5 text-xs text-neutral-600 shadow-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium text-neutral-700">Analyzing HIMS inventory...</span>
                            <span class="flex gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 animate-bounce"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 animate-bounce" style="animation-delay: 150ms;"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 animate-bounce" style="animation-delay: 300ms;"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input Area --}}
            <form
                x-on:submit.prevent="sendMessage()"
                data-no-loading
                class="border-t border-neutral-200 bg-white p-2.5"
            >
                <div class="relative flex items-center">
                    <input
                        x-ref="chatInput"
                        x-model="input"
                        type="text"
                        maxlength="1000"
                        placeholder="Ask about inventory, stock, demand..."
                        x-bind:disabled="isLoading"
                        class="w-full rounded-xl border border-neutral-300 py-2.5 pl-3 pr-11 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:bg-neutral-50"
                    />
                    <button
                        type="submit"
                        x-bind:disabled="isLoading || !input.trim()"
                        class="absolute right-1.5 flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-neutral-200 disabled:text-neutral-400"
                        title="Send question"
                        aria-label="Send question"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </button>
                </div>
            </form>
        </section>
    </aside>
    @endcanany
</x-app-layout>

