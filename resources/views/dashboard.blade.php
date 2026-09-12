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

                                {{-- Desktop & Tablet Table (Responsive, 100% width, no horizontal scrollbar) --}}
                                <div class="hidden md:block overflow-hidden border-y border-neutral-200 bg-white">
                                    <table class="w-full text-left text-xs">
                                        <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                                            <tr>
                                                <th scope="col" class="w-[30%] px-4 py-2.5 font-semibold text-neutral-700">Item</th>
                                                <th scope="col" class="w-[10%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Current Stock</th>
                                                <th scope="col" class="w-[10%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Historical</th>
                                                <th scope="col" class="w-[12%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Predicted Demand</th>
                                                <th scope="col" class="w-[12%] px-2.5 py-2.5 text-center font-semibold text-neutral-700">Stock risk</th>
                                                <th scope="col" class="w-[10%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Reorder</th>
                                                <th scope="col" class="w-[8%] px-2.5 py-2.5 font-semibold text-neutral-700">Confidence</th>
                                                <th scope="col" class="w-[8%] px-3 py-2.5 text-right font-semibold text-neutral-700">Action</th>
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
                                                            <div class="min-w-0">
                                                                <p class="truncate font-semibold text-neutral-900" x-text="item.item_name"></p>
                                                                <p class="text-neutral-500" x-text="item.sku"></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2.5 py-2.5 text-right tabular-nums text-neutral-700" x-text="formatNumber(item.current_stock)"></td>
                                                    <td class="px-2.5 py-2.5 text-right tabular-nums text-neutral-700" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></td>
                                                    <td class="px-2.5 py-2.5 text-right font-bold tabular-nums text-neutral-900" x-text="formatNumber(item.predicted_demand)"></td>
                                                    <td class="px-2.5 py-2.5 text-center">
                                                        <span class="inline-flex rounded-full px-2 py-0.5 font-semibold text-[11px] ring-1 ring-inset whitespace-nowrap" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                                    </td>
                                                    <td class="px-2.5 py-2.5 text-right font-semibold tabular-nums text-neutral-900" x-text="formatNumber(item.recommended_reorder_quantity)"></td>
                                                    <td class="px-2.5 py-2.5 capitalize text-neutral-700 text-[11px]" x-text="item.confidence"></td>
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

                                {{-- Mobile Card List (Zero horizontal scrolling on smartphones) --}}
                                <div class="md:hidden space-y-2.5 p-3">
                                    <template x-for="item in topItems()" x-bind:key="item.item_id">
                                        <div
                                            class="cursor-pointer rounded-xl border p-3 transition-colors space-y-2"
                                            x-bind:class="selectedItemId === String(item.item_id) ? 'border-primary-300 bg-primary-50/70 shadow-xs' : 'border-neutral-200 bg-white hover:bg-neutral-50/80'"
                                            x-on:click="selectItem(item.item_id)"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-semibold text-neutral-900 text-xs leading-snug" x-text="item.item_name"></p>
                                                    <p class="text-[11px] text-neutral-500" x-text="item.sku"></p>
                                                </div>
                                                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset whitespace-nowrap" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                            </div>

                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 rounded-lg bg-neutral-50 p-2 text-center text-[11px]">
                                                <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                                    <span class="block text-[10px] text-neutral-500">Current</span>
                                                    <span class="font-semibold text-neutral-800 tabular-nums" x-text="formatNumber(item.current_stock)"></span>
                                                </div>
                                                <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                                    <span class="block text-[10px] text-neutral-500">Historical</span>
                                                    <span class="font-medium text-neutral-600 tabular-nums" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></span>
                                                </div>
                                                <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                                    <span class="block text-[10px] text-neutral-500">Forecast</span>
                                                    <span class="font-bold text-neutral-900 tabular-nums" x-text="formatNumber(item.predicted_demand)"></span>
                                                </div>
                                                <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                                    <span class="block text-[10px] text-primary-700">Reorder</span>
                                                    <span class="font-bold text-primary-700 tabular-nums" x-text="formatNumber(item.recommended_reorder_quantity)"></span>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between pt-1">
                                                <span class="text-[11px] text-neutral-500">Confidence: <span class="capitalize font-medium text-neutral-700" x-text="item.confidence"></span></span>
                                                <button
                                                    type="button"
                                                    class="rounded px-2.5 py-1 text-xs font-medium"
                                                    x-bind:class="selectedItemId === String(item.item_id) ? 'bg-primary-600 text-white' : 'text-primary-700 bg-primary-50'"
                                                    x-text="selectedItemId === String(item.item_id) ? 'Active Curve' : 'Plot Curve'"
                                                ></button>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="topItems().length === 0" class="py-6 text-center text-sm text-neutral-500">
                                        No forecast items match the current filters.
                                    </div>
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

            <x-ui.modal name="dashboard-demand-forecast" title="Full Demand Forecast" maxWidth="6xl">
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

                    {{-- Desktop & Tablet Table (100% width, no horizontal scrollbar, clear column proportions) --}}
                    <div x-show="filteredItems().length > 0" class="hidden md:block rounded-lg border border-neutral-200 overflow-hidden bg-white">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-neutral-50 text-neutral-500 border-b border-neutral-200">
                                <tr>
                                    <th scope="col" class="w-[34%] px-3.5 py-2.5 font-semibold text-neutral-700">Item & Explanation</th>
                                    <th scope="col" class="w-[9%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Current</th>
                                    <th scope="col" class="w-[10%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Historical</th>
                                    <th scope="col" class="w-[11%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Forecast</th>
                                    <th scope="col" class="w-[11%] px-2.5 py-2.5 text-center font-semibold text-neutral-700">Risk</th>
                                    <th scope="col" class="w-[11%] px-2.5 py-2.5 text-right font-semibold text-neutral-700">Reorder</th>
                                    <th scope="col" class="w-[14%] px-3 py-2.5 font-semibold text-neutral-700">Confidence</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <template x-for="item in filteredItems()" x-bind:key="item.item_id">
                                    <tr class="align-top transition-colors hover:bg-neutral-50/80">
                                        <td class="px-3.5 py-3">
                                            <p class="font-semibold text-neutral-900" x-text="item.item_name"></p>
                                            <p class="text-[11px] text-neutral-500 mt-0.5" x-text="`${item.sku}${item.category ? ` · ${item.category}` : ''}`"></p>
                                            <p x-show="item.explanation" class="mt-1.5 text-[11px] leading-relaxed text-neutral-600" x-text="item.explanation"></p>
                                            <p x-show="item.limited_data" class="mt-1 font-medium text-[10px] text-warning-700">Limited movement history</p>
                                        </td>
                                        <td class="px-2.5 py-3 text-right tabular-nums text-neutral-700" x-text="formatNumber(item.current_stock)"></td>
                                        <td class="px-2.5 py-3 text-right tabular-nums text-neutral-600" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></td>
                                        <td class="px-2.5 py-3 text-right font-bold tabular-nums text-neutral-900" x-text="formatNumber(item.predicted_demand)"></td>
                                        <td class="px-2.5 py-3 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset whitespace-nowrap" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                        </td>
                                        <td class="px-2.5 py-3 text-right font-bold tabular-nums text-primary-700" x-text="formatNumber(item.recommended_reorder_quantity)"></td>
                                        <td class="px-3 py-3 text-neutral-700">
                                            <span class="font-medium capitalize text-neutral-900 block" x-text="item.confidence"></span>
                                            <span class="text-[10px] text-neutral-500 block leading-tight" x-text="`${item.demand_trend} trend`"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Card List (Zero horizontal scrolling on smartphones) --}}
                    <div x-show="filteredItems().length > 0" class="space-y-3 md:hidden">
                        <template x-for="item in filteredItems()" x-bind:key="item.item_id">
                            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 shadow-2xs space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-neutral-900 text-xs leading-snug" x-text="item.item_name"></p>
                                        <p class="text-[11px] text-neutral-500 mt-0.5" x-text="`${item.sku}${item.category ? ` · ${item.category}` : ''}`"></p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset whitespace-nowrap" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 rounded-lg bg-neutral-50 p-2 text-center text-[11px]">
                                    <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                        <span class="block text-[10px] text-neutral-500">Current</span>
                                        <span class="font-semibold text-neutral-800 tabular-nums" x-text="formatNumber(item.current_stock)"></span>
                                    </div>
                                    <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                        <span class="block text-[10px] text-neutral-500">Historical</span>
                                        <span class="font-medium text-neutral-600 tabular-nums" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></span>
                                    </div>
                                    <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                        <span class="block text-[10px] text-neutral-500">Forecast</span>
                                        <span class="font-bold text-neutral-900 tabular-nums" x-text="formatNumber(item.predicted_demand)"></span>
                                    </div>
                                    <div class="rounded bg-white/80 py-1 border border-neutral-100">
                                        <span class="block text-[10px] text-primary-700">Reorder</span>
                                        <span class="font-bold text-primary-700 tabular-nums" x-text="formatNumber(item.recommended_reorder_quantity)"></span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-neutral-500">
                                    <span>Confidence: <strong class="font-semibold capitalize text-neutral-800" x-text="item.confidence"></strong></span>
                                    <span class="capitalize" x-text="`${item.demand_trend} trend`"></span>
                                </div>

                                <template x-if="item.explanation">
                                    <div class="text-[11px] text-neutral-600 bg-neutral-50/80 rounded-lg p-2.5 border border-neutral-100 leading-relaxed">
                                        <span class="font-semibold text-neutral-700">Explanation:</span>
                                        <span x-text="item.explanation"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
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
        x-data="himsAiAssistant({
            endpoint: '{{ route('dashboard.ai-assistant') }}',
            knownItems: {{ Js::from(\App\Models\InventoryItem::query()->pluck('name')->values()) }}
        })"
        x-cloak
        class="fixed bottom-5 right-5 z-50 select-none print:hidden sm:bottom-6 sm:right-6"
        aria-label="HIMS AI Assistant"
    >
        {{-- Floating Trigger Button --}}
        <button
            type="button"
            x-on:click="toggle()"
            x-bind:aria-expanded="isOpen.toString()"
            class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-primary-700 text-white shadow-xl transition-all duration-200 hover:scale-105 hover:from-primary-500 hover:to-primary-600 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-primary-500/30 active:scale-95"
            title="Open HIMS AI Assistant"
            aria-label="Toggle HIMS AI Assistant"
        >
            <span class="sr-only">Toggle HIMS AI Assistant</span>
            {{-- Chatbot Icon when closed --}}
            <svg x-show="!isOpen" class="relative h-6 w-6 transition-transform duration-200 group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.154-.672c.07-.866.27-1.77.585-2.556C3.593 16.32 3 14.28 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>

            {{-- Close X icon when open --}}
            <svg x-show="isOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>

        {{-- Modern Chat Panel Container --}}
        <section
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            x-on:keydown.escape.window="close()"
            x-on:dragover.prevent="isDraggingOver = true"
            x-on:dragleave.prevent="isDraggingOver = false"
            x-on:drop.prevent="handleFileDrop($event)"
            role="dialog"
            aria-labelledby="hims-assistant-title"
            aria-modal="false"
            class="absolute bottom-16 right-0 flex h-[580px] max-h-[calc(100vh-5rem)] w-[400px] max-w-[calc(100vw-1.5rem)] flex-col overflow-hidden rounded-2xl border border-neutral-200/90 bg-neutral-50/60 shadow-2xl ring-1 ring-black/10 backdrop-blur-sm sm:w-[440px]"
        >
            {{-- Drag and Drop Visual Overlay --}}
            <div
                x-show="isDraggingOver"
                x-transition.opacity
                class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-primary-900/90 p-4 text-center text-white backdrop-blur-xs"
            >
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 shadow-inner animate-pulse">
                    <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold">Drop file to attach</p>
                <p class="mt-1 text-xs text-primary-200">PDF, Excel, Word, CSV, TXT, or images (up to 35MB)</p>
            </div>

            {{-- Redesigned Header --}}
            <header class="flex items-center justify-between border-b border-neutral-200/80 bg-white/95 px-4 py-3 shadow-2xs backdrop-blur-xs">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-primary-800 text-white shadow-xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.154-.672c.07-.866.27-1.77.585-2.556C3.593 16.32 3 14.28 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="hims-assistant-title" class="text-sm font-semibold tracking-tight text-neutral-900">
                            HIMS AI Assistant
                        </h3>
                        <p class="text-[11px] font-medium text-neutral-500">
                            Inventory Intelligence Assistant
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        x-on:click="clearChat()"
                        title="Clear conversation"
                        class="rounded-lg p-1.5 text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700"
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
                        class="rounded-lg p-1.5 text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700"
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
                class="flex-1 space-y-3.5 overflow-y-auto p-4 text-xs select-text scroll-smooth"
            >
                {{-- Welcome / Empty State with Suggested Inquiries --}}
                <template x-if="messages.length === 0">
                    <div class="space-y-3.5 py-2">
                        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-xs">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.154-.672c.07-.866.27-1.77.585-2.556C3.593 16.32 3 14.28 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-neutral-900">Welcome to HIMS AI Assistant</h4>
                                    <p class="text-[11px] text-neutral-500">Real-time inventory intelligence & analysis</p>
                                </div>
                            </div>
                            <p class="mt-2.5 text-xs leading-relaxed text-neutral-600">
                                Ask about inventory, demand, and stock levels. You can also attach spreadsheets, documents, and images for deep analysis.
                            </p>
                        </div>

                        {{-- Suggested Inquiry Chips --}}
                        <div class="space-y-2 pt-1">
                            <p class="px-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Suggested inquiries:</p>
                            <div class="grid grid-cols-1 gap-1.5">
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('Which items are low in stock?')"
                                    class="group flex items-center justify-between rounded-xl border border-neutral-200/80 bg-white px-3.5 py-2.5 text-left text-xs font-medium text-neutral-700 shadow-2xs transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-warning-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                        <span>Which items are low in stock?</span>
                                    </span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400 transition-colors group-hover:text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('What should we reorder?')"
                                    class="group flex items-center justify-between rounded-xl border border-neutral-200/80 bg-white px-3.5 py-2.5 text-left text-xs font-medium text-neutral-700 shadow-2xs transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                                        <span>What should we reorder?</span>
                                    </span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400 transition-colors group-hover:text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('Explain the demand forecast')"
                                    class="group flex items-center justify-between rounded-xl border border-neutral-200/80 bg-white px-3.5 py-2.5 text-left text-xs font-medium text-neutral-700 shadow-2xs transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-info-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" /></svg>
                                        <span>Explain the demand forecast</span>
                                    </span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400 transition-colors group-hover:text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="sendSuggested('Summarize inventory status')"
                                    class="group flex items-center justify-between rounded-xl border border-neutral-200/80 bg-white px-3.5 py-2.5 text-left text-xs font-medium text-neutral-700 shadow-2xs transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                        <span>Summarize inventory status</span>
                                    </span>
                                    <svg class="h-3.5 w-3.5 text-neutral-400 transition-colors group-hover:text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Conversation Messages Loop --}}
                <template x-for="(msg, index) in messages" x-bind:key="index">
                    <div
                        class="flex w-full"
                        x-bind:class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        {{-- User Bubble --}}
                        <template x-if="msg.role === 'user'">
                            <div class="max-w-[85%] rounded-2xl rounded-tr-sm bg-primary-600 px-3.5 py-2.5 text-white shadow-xs">
                                {{-- Image Attachment Preview --}}
                                <template x-if="msg.attachment && msg.attachment.type === 'image' && msg.attachment.previewUrl">
                                    <div class="mb-2 overflow-hidden rounded-xl border border-primary-400/40 bg-black/10">
                                        <img :src="msg.attachment.previewUrl" :alt="msg.attachment.name" class="max-h-40 w-full object-cover" />
                                        <div class="flex items-center justify-between bg-primary-700/80 px-2 py-1 text-[10px] text-primary-100">
                                            <span class="truncate font-medium text-white" x-text="msg.attachment.name"></span>
                                            <span x-text="msg.attachment.size"></span>
                                        </div>
                                    </div>
                                </template>

                                {{-- Non-image Attachment Preview --}}
                                <template x-if="msg.attachment && (msg.attachment.type !== 'image' || !msg.attachment.previewUrl)">
                                    <div class="mb-2 flex items-center gap-2 rounded-xl border border-primary-400/40 bg-primary-700/70 p-2 text-xs">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/20 font-mono text-[10px] font-bold uppercase text-white">
                                            <span x-text="msg.attachment.extension"></span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-medium text-white" x-text="msg.attachment.name"></p>
                                            <p class="text-[10px] text-primary-200" x-text="msg.attachment.size"></p>
                                        </div>
                                        <span class="rounded bg-primary-800/90 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-primary-100">Attached</span>
                                    </div>
                                </template>

                                <p class="whitespace-pre-wrap text-xs leading-relaxed text-white" x-text="msg.content"></p>
                                <div class="mt-1 flex items-center justify-end text-[10px] text-primary-200">
                                    <span x-text="msg.time"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Assistant Response Card --}}
                        <template x-if="msg.role !== 'user'">
                            <div
                                class="w-full max-w-[94%] rounded-2xl rounded-tl-sm border p-3.5 shadow-xs transition-all"
                                x-bind:class="msg.isError ? 'border-danger-200 bg-danger-50 text-danger-800' : 'border-neutral-200/90 bg-white text-neutral-800'"
                            >
                                {{-- Card Header: Avatar, Brand, Time, Copy Button --}}
                                <div class="mb-2.5 flex items-center justify-between border-b border-neutral-100 pb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-5 w-5 items-center justify-center rounded-md bg-primary-100 text-primary-700">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.154-.672c.07-.866.27-1.77.585-2.556C3.593 16.32 3 14.28 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                            </svg>
                                        </div>
                                        <span class="text-xs font-bold tracking-tight text-neutral-900">HIMS AI</span>
                                        <span class="text-[10px] text-neutral-400" x-text="msg.time"></span>
                                    </div>

                                    {{-- Functional Copy Message Button --}}
                                    <button
                                        type="button"
                                        x-on:click="copyMessage(msg.content, index)"
                                        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-800"
                                        title="Copy response"
                                    >
                                        <template x-if="copiedIndex === index">
                                            <span class="inline-flex items-center gap-1 font-semibold text-emerald-600">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                                <span>Copied!</span>
                                            </span>
                                        </template>
                                        <template x-if="copiedIndex !== index">
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                                                <span>Copy</span>
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- Rendered Markdown Body --}}
                                <div
                                    class="text-xs leading-relaxed"
                                    x-bind:class="msg.isError ? 'text-danger-800' : 'text-neutral-800'"
                                    x-html="formatMarkdown(msg.content)"
                                ></div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Dynamic Typing / Loading State --}}
                <div x-show="isLoading" class="flex w-full max-w-[85%] gap-2.5">
                    <div class="rounded-2xl rounded-tl-sm border border-neutral-200/80 bg-white p-3 shadow-xs">
                        <div class="flex items-center gap-2">
                            <div class="flex h-5 w-5 items-center justify-center rounded-md bg-primary-100 text-primary-700">
                                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-neutral-700" x-text="loadingStatus"></span>
                            <span class="ml-1 flex gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 animate-bounce"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 animate-bounce" style="animation-delay: 150ms;"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 animate-bounce" style="animation-delay: 300ms;"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Error Message Alert --}}
            <div
                x-show="errorMessage"
                x-transition
                class="flex items-center justify-between border-t border-danger-200 bg-danger-50 px-3 py-1.5 text-xs text-danger-700"
            >
                <span class="truncate font-medium" x-text="errorMessage"></span>
                <button type="button" x-on:click="errorMessage = ''" class="ml-2 font-bold text-danger-500 hover:text-danger-800" aria-label="Dismiss error">&times;</button>
            </div>

            {{-- Selected File Preview Strip --}}
            <div
                x-show="selectedFile"
                x-transition
                class="flex items-center justify-between border-t border-neutral-200/80 bg-neutral-100/70 px-3.5 py-2 text-xs"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <template x-if="selectedFile && selectedFile.previewUrl">
                        <img :src="selectedFile.previewUrl" class="h-8 w-8 rounded-lg object-cover border border-neutral-200" />
                    </template>
                    <template x-if="!selectedFile || !selectedFile.previewUrl">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 font-mono text-[10px] font-bold uppercase text-primary-700">
                            <span x-text="selectedFile ? selectedFile.extension : ''"></span>
                        </div>
                    </template>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-neutral-800" x-text="selectedFile ? selectedFile.name : ''"></p>
                        <p class="text-[10px] text-neutral-500" x-text="selectedFile ? selectedFile.size : ''"></p>
                    </div>
                </div>
                <button
                    type="button"
                    x-on:click="removeFile(true)"
                    class="ml-2 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-neutral-400 transition-colors hover:bg-neutral-200 hover:text-neutral-700"
                    title="Remove attachment"
                    aria-label="Remove attachment"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Input Area --}}
            <form
                x-on:submit.prevent="sendMessage()"
                data-no-loading
                class="border-t border-neutral-200/80 bg-white p-2.5"
            >
                {{-- Hidden file input --}}
                <input
                    type="file"
                    x-ref="fileInput"
                    x-on:change="handleFileSelect($event)"
                    accept=".pdf,.csv,.xlsx,.docx,.txt,.jpg,.jpeg,.png"
                    class="hidden"
                    aria-label="Upload file attachment"
                />

                <div class="relative flex items-center">
                    {{-- Attachment Paperclip Button --}}
                    <button
                        type="button"
                        x-on:click="triggerFileInput()"
                        x-bind:disabled="isLoading"
                        class="absolute left-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700 disabled:opacity-50"
                        title="Attach file (PDF, Excel, Word, CSV, Image - Max 35MB)"
                        aria-label="Attach file"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                        </svg>
                    </button>

                    <input
                        x-ref="chatInput"
                        x-model="input"
                        type="text"
                        maxlength="2000"
                        placeholder="Ask about inventory or attach a file..."
                        x-bind:disabled="isLoading"
                        class="w-full rounded-xl border border-neutral-300 py-2.5 pl-10 pr-11 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:bg-neutral-50"
                    />

                    {{-- Send Button --}}
                    <button
                        type="submit"
                        x-bind:disabled="isLoading || (!input.trim() && !selectedFile)"
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

