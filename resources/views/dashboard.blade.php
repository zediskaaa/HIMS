@php
    $isSuperAdminPanel = $superAdminPanel ?? false;
    $isAdminPanel = \App\Support\AuthenticationContext::isAdmin();
    $dashboardTitle = match (true) {
        $isSuperAdminPanel => 'Super Admin Dashboard',
        $isAdminPanel => 'Admin Dashboard',
        default => 'Staff Dashboard',
    };
@endphp

<x-app-layout full-width>
    <x-slot:title>{{ $dashboardTitle }}</x-slot:title>

    <x-ui.page-header
        :title="$dashboardTitle"
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
        class="hims-dashboard !mt-3 space-y-3 xl:space-y-4"
        x-data="dashboardLive({{ Js::from(route('dashboard.live')) }})"
        x-init="start()"
        @dashboard-refresh.window="refresh()"
    >
    @php
        $pendingRecoveryCount = 0;
    @endphp
    @can(\App\Enums\Permission::ManageSystemRecovery->value)
        @php
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('system_recovery_records')) {
                    $pendingRecoveryCount = \App\Models\SystemRecoveryRecord::open()->count();
                }
            } catch (\Throwable) {
                $pendingRecoveryCount = 0;
            }
        @endphp
    @endcan

    {{-- Key figures: Standardized 3-Zone Operational KPI Cards --}}
    <div @class([
        'grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 xl:gap-4',
        'xl:grid-cols-5' => $pendingRecoveryCount > 0,
    ])>
        {{-- 1. Tracked items --}}
        <a href="{{ route('inventory.items') }}" x-ref="trackedItemsTile"
           class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-primary-400 dark:hover:border-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Tracked items</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300 ring-1 ring-primary-200 dark:ring-primary-800/50 group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon name="cube" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white" data-stat-value>{{ number_format($totalItems) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">items</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate" data-stat-hint>{{ number_format($totalOnHand) }} units on hand</span>
            </div>
        </a>

        {{-- 2. Needs reorder --}}
        <a href="{{ route('inventory.alerts') }}" x-ref="lowStockTile"
           class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-amber-400 dark:hover:border-amber-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider {{ $lowStockItems > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">Needs reorder</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $lowStockItems > 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800/50' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50' }} group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon :name="$lowStockItems > 0 ? 'exclamation-triangle' : 'check-circle'" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums {{ $lowStockItems > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}" data-stat-value>{{ number_format($lowStockItems) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">items</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate" data-stat-hint>{{ $outOfStockItems > 0 ? number_format($outOfStockItems).' fully out of stock' : 'No items out of stock' }}</span>
            </div>
        </a>

        {{-- 3. Expiring soon --}}
        <a href="{{ route('inventory.alerts') }}" x-ref="expiryTile"
           class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-rose-400 dark:hover:border-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider {{ $expiringSoonCount > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">Expiring soon</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $expiringSoonCount > 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800/50' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50' }} group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon :name="$expiringSoonCount > 0 ? 'clock' : 'check-circle'" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums {{ $expiringSoonCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}" data-stat-value>{{ number_format($expiringSoonCount) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">batches</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate" data-stat-hint>
                    {{ $criticalExpiryCount > 0
                        ? number_format($criticalExpiryCount).' critical / near expiry'
                        : ($expiringSoonCount > 0 ? '1–90 days remaining' : 'No batches expiring within 90 days') }}
                </span>
            </div>
        </a>

        {{-- 4. Inventory value --}}
        @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
        <a href="{{ route('inventory.reports') }}" x-ref="inventoryValueTile"
           class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-emerald-400 dark:hover:border-emerald-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Inventory value</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50 group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon name="chart-bar" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white truncate" data-stat-value>₱{{ number_format($totalInventoryValue, 2) }}</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate" data-stat-hint>{{ number_format($storageLocations) }} storage locations</span>
            </div>
        </a>
        @endcan

        {{-- 5. System incidents --}}
        @can(\App\Enums\Permission::ManageSystemRecovery->value)
            @if ($pendingRecoveryCount > 0)
                <a href="{{ route('super-admin.recovery.index') }}" data-recovery-incident-card
                   class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-amber-400 dark:hover:border-amber-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 transition-all duration-150 col-span-1 sm:col-span-2 lg:col-span-1">
                    {{-- Zone 1: Header --}}
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">System incidents</p>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800/50 group-hover:scale-105 transition-transform duration-150">
                            <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                        </span>
                    </div>
                    {{-- Zone 2: Value --}}
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-amber-600 dark:text-amber-400" data-stat-value>{{ number_format($pendingRecoveryCount) }}</span>
                        <span class="text-sm sm:text-base font-bold text-amber-600/80 dark:text-amber-400/80">pending</span>
                    </div>
                    {{-- Zone 3: Footer --}}
                    <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                        <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate" data-stat-hint>Recovery review required</span>
                    </div>
                </a>
            @endif
        @endcan
    </div>
    @can(\App\Enums\Permission::ViewReports->value)
        @php
            $dashboardForecastConfig = [
                'initialForecast' => $aiForecast,
                'endpoint' => route('inventory.demand-forecast.refresh'),
            ];
        @endphp
        <div
            class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)] xl:grid-cols-[minmax(0,2.1fr)_minmax(19rem,1fr)]"
            x-data="demandForecastDashboard({{ Js::from($dashboardForecastConfig) }})"
        >
            <x-ui.card class="min-w-0" :padding="false">
                <header class="flex flex-col gap-2 border-b border-neutral-200 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                            <x-ui.icon name="chart-bar" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-neutral-900">AI-Based Stock Demand Forecasting</h2>
                            <p class="truncate text-[11px] text-neutral-500">Recorded demand and AI forecast in one decision view.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:shrink-0">
                        <span x-show="isLoading()" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                            <span class="h-2 w-2 rounded-full bg-primary-500 animate-pulse"></span>
                            <span>Scanning stock levels...</span>
                        </span>
                        <x-ui.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon="eye"
                            x-on:click="$dispatch('open-modal', 'dashboard-demand-forecast')"
                            x-bind:disabled="!isSuccess()"
                        >
                            Forecast details
                        </x-ui.button>
                    </div>
                </header>

                <div class="space-y-3 p-3">
                    <div class="sr-only" aria-live="polite" x-text="success"></div>
                    <div
                        x-show="error"
                        x-cloak
                        role="alert"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700"
                    >
                        <span x-text="error"></span>
                        <button
                            type="button"
                            x-show="failedForecastDays"
                            x-on:click="retryForecast()"
                            x-bind:disabled="loading"
                            class="rounded-md px-2 py-1 text-xs font-semibold text-danger-700 ring-1 ring-inset ring-danger-300 hover:bg-danger-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 disabled:opacity-60"
                        >
                            Retry
                        </button>
                    </div>

                    {{-- 1. ERROR STATE: When the forecast request fails, with retry --}}
                    <div
                        x-show="isError()"
                        x-cloak
                        role="alert"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-900/60 dark:bg-rose-950/20 dark:text-rose-300"
                    >
                        <span x-text="error || 'A problem occurred while retrieving the forecast.'"></span>
                        <button
                            type="button"
                            x-on:click="retryForecast()"
                            x-bind:disabled="loading"
                            class="inline-flex items-center gap-1.5 rounded-md bg-danger-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-danger-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 disabled:opacity-60 dark:text-white dark:bg-rose-600 dark:hover:bg-rose-700"
                        >
                            <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                            <span>Retry Forecast</span>
                        </button>
                    </div>

                    {{-- 2. LOADING STATE: Animated Skeleton (Exact Pic 2 layout) --}}
                    <div
                        x-show="isLoading()"
                        aria-busy="true"
                    >
                        <x-ui.forecast-chart-skeleton />
                    </div>

                    {{-- 3. EMPTY STATE: When there is no forecast data yet (Exact Pic 2 layout) --}}
                    <div
                        x-show="isEmpty()"
                        x-cloak
                        aria-busy="true"
                    >
                        <x-ui.forecast-chart-skeleton />
                    </div>

                    {{-- 4. SUCCESS / POPULATED FORECAST STATE (Only revealed when real data exists) --}}
                    <div
                        x-show="isSuccess()"
                        x-cloak
                        class="space-y-3"
                    >
                        {{-- Filter Controls --}}
                        <div class="grid min-w-0 gap-2.5 rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 sm:grid-cols-2 lg:grid-cols-5 lg:items-end dark:border-neutral-800 dark:bg-neutral-800/40">
                            {{-- Item Selection --}}
                            <div class="min-w-0 space-y-1.5 sm:col-span-2 lg:col-span-3">
                                <div class="flex items-center justify-between">
                                    <label for="dashboard-forecast-item" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Select Item</label>
                                    <button
                                        type="button"
                                        x-show="selectedItemId"
                                        x-cloak
                                        x-on:click="selectedItemId = ''"
                                        class="text-[11px] font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                                    >
                                        View All Items
                                    </button>
                                </div>
                                <select
                                    id="dashboard-forecast-item"
                                    x-model="selectedItemId"
                                    class="block min-h-10 w-full rounded-md border-neutral-300 pl-3 pr-10 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                                >
                                    <option value="">All Inventory Items (Overall Hospital Demand)</option>
                                    <template x-for="item in allItems()" x-bind:key="item.item_id">
                                        <option x-bind:value="String(item.item_id)" x-text="`${item.item_name} (${item.sku})`"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Forecast Period --}}
                            <div class="min-w-0 space-y-1.5">
                                <label for="dashboard-forecast-period" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Forecast period</label>
                                <select
                                    id="dashboard-forecast-period"
                                    name="forecast_days"
                                    x-model="forecastDays"
                                    x-on:change="updateForecastPeriod($event.target.value)"
                                    @cannot(\App\Enums\Permission::GenerateForecasts->value) disabled @endcannot
                                    class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30 disabled:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                                >
                                    @foreach ([7 => 'Next 7 days', 14 => 'Next 14 days', 30 => 'Next 30 days', 60 => 'Next 60 days', 90 => 'Next 90 days'] as $days => $label)
                                        <option value="{{ $days }}" @selected(($aiForecast['forecast_days'] ?? 30) === $days)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex min-w-0 items-end">
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    icon="funnel"
                                    class="w-full"
                                    x-on:click="filtersOpen = !filtersOpen"
                                    x-bind:aria-expanded="filtersOpen"
                                    aria-controls="dashboard-forecast-filters"
                                >
                                    <span x-text="filtersOpen ? 'Hide filters' : 'More filters'"></span>
                                    <span
                                        x-show="activeFilterCount() > 0"
                                        x-cloak
                                        class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/60 dark:text-primary-300"
                                        x-text="activeFilterCount()"
                                    ></span>
                                </x-ui.button>
                            </div>

                            <div
                                id="dashboard-forecast-filters"
                                x-show="filtersOpen"
                                x-cloak
                                class="grid min-w-0 gap-3 rounded-lg border border-neutral-200 bg-white p-3 sm:col-span-2 sm:grid-cols-3 lg:col-span-5 dark:border-neutral-700 dark:bg-neutral-900"
                            >
                                {{-- Item category --}}
                                <div class="min-w-0 space-y-1.5">
                                    <label for="dashboard-forecast-category" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Item category</label>
                                    <select
                                        id="dashboard-forecast-category"
                                        x-model="category"
                                        class="block min-h-10 w-full rounded-md border-neutral-300 pl-3 pr-10 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        <option value="">All categories</option>
                                        @foreach ($forecastCategories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Risk level --}}
                                <div class="min-w-0 space-y-1.5">
                                    <label for="dashboard-forecast-risk" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Risk level</label>
                                    <select
                                        id="dashboard-forecast-risk"
                                        x-model="risk"
                                        class="block min-h-10 w-full rounded-md border-neutral-300 pl-3 pr-10 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        <option value="">All risk levels</option>
                                        <option value="high">High risk</option>
                                        <option value="medium">Medium risk</option>
                                        <option value="low">Low risk</option>
                                    </select>
                                </div>

                                {{-- Search item --}}
                                <div class="min-w-0 space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <label for="dashboard-forecast-search" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Search item</label>
                                        <button
                                            type="button"
                                            x-show="activeFilterCount() > 0"
                                            x-on:click="clearFilters()"
                                            class="text-[11px] font-medium text-primary-700 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                                        >
                                            Clear filters
                                        </button>
                                    </div>
                                    <input
                                        id="dashboard-forecast-search"
                                        type="search"
                                        x-model.debounce.200ms="search"
                                        placeholder="Name or SKU"
                                        autocomplete="off"
                                        class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm placeholder:text-neutral-400 focus:border-primary-500 focus:ring-primary-500/30 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500"
                                    >
                                </div>
                            </div>
                        </div>
                        {{-- Compact forecast summary strip --}}
                        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200 sm:grid-cols-3 xl:grid-cols-6">
                            <div class="bg-neutral-50 p-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Forecast period</dt>
                                <dd class="mt-1 text-sm font-semibold tabular-nums text-neutral-900" x-text="forecast?.forecast_period || `Next ${forecastDays} days`"></dd>
                            </div>

                            <div class="bg-neutral-50 p-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Predicted demand</dt>
                                <dd class="mt-1 text-sm font-semibold tabular-nums text-neutral-900">
                                    <span x-text="formatNumber(summaryPredictedDemand())"></span>
                                    <span class="text-[11px] font-normal text-neutral-500">units</span>
                                </dd>
                            </div>

                            <div class="bg-neutral-50 p-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Daily forecast</dt>
                                <dd class="mt-1 text-sm font-semibold tabular-nums text-neutral-900">
                                    <span x-text="formatNumber(summaryPredictedDailyDemand(), 1)"></span>
                                    <span class="text-[11px] font-normal text-neutral-500">units/day</span>
                                </dd>
                            </div>

                            <div class="bg-neutral-50 p-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Current Stock</dt>
                                <dd class="mt-1 text-sm font-semibold tabular-nums text-neutral-900">
                                    <span x-text="formatNumber(summaryCurrentStock())"></span>
                                    <span class="text-[11px] font-normal text-neutral-500">units</span>
                                </dd>
                            </div>

                            <div class="bg-neutral-50 p-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Estimated Stock Risk</dt>
                                <dd class="mt-1">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                        x-bind:class="riskClasses(summaryStockRisk())"
                                        x-text="`${summaryStockRisk()} risk`"
                                    ></span>
                                </dd>
                            </div>

                            <div class="bg-neutral-50 p-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Reorder units</dt>
                                <dd class="mt-1 text-sm font-semibold tabular-nums text-neutral-900">
                                    <span x-text="formatNumber(summaryRecommendedReorder())"></span>
                                    <span class="text-[11px] font-normal text-neutral-500">units</span>
                                </dd>
                            </div>
                        </dl>

                        {{-- Combined Demand vs Forecast Card --}}
                        <section
                            class="min-w-0 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
                            aria-labelledby="dashboard-forecast-overview-title"
                            x-bind:aria-busy="loading"
                        >
                            <header class="flex flex-col gap-2 border-b border-neutral-200 px-3 py-2 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 id="dashboard-forecast-overview-title" class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Demand vs Forecast</h3>
                                        <template x-if="selectedItem()">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-700/50">
                                                <span class="truncate max-w-[200px]" x-text="selectedItem().item_name"></span>
                                                <button type="button" x-on:click="selectedItemId = ''" class="hover:text-primary-900 dark:hover:text-primary-200" aria-label="Clear selected item">
                                                    <x-ui.icon name="x-mark" class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Interactive legend and scrubber guidance --}}
                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-neutral-600 sm:gap-3 dark:text-neutral-400">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md px-1.5 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        x-bind:class="showActual ? 'text-neutral-800 dark:text-neutral-200' : 'text-neutral-400 line-through dark:text-neutral-600'"
                                        x-bind:aria-pressed="showActual"
                                        x-on:click="toggleSeries('actual')"
                                    >
                                        <span class="h-1 w-4 rounded-full bg-primary-600"></span>
                                        <span class="font-medium">Historical demand</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md px-1.5 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        x-bind:class="showForecast ? 'text-neutral-800 dark:text-neutral-200' : 'text-neutral-400 line-through dark:text-neutral-600'"
                                        x-bind:aria-pressed="showForecast"
                                        x-on:click="toggleSeries('forecast')"
                                    >
                                        <span class="h-0.5 w-4 border-t-2 border-dashed border-violet-600"></span>
                                        <span class="font-medium">AI forecast</span>
                                    </button>
                                </div>
                            </header>

                            <div class="min-w-0 px-3 py-2.5">
                                <div x-show="hasChartData()" class="min-w-0 select-none">
                                    <div
                                        class="relative min-w-0"
                                        x-ref="chartContainer"
                                        x-on:pointerenter="isHovering = true"
                                        x-on:pointerleave="onChartPointerLeave($event)"
                                    >
                                        <div
                                            x-show="loading"
                                            x-cloak
                                            class="pointer-events-none absolute right-2 top-2 z-20 inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-white/95 px-2 py-1 text-[11px] font-medium text-primary-700 shadow-sm backdrop-blur-sm dark:border-primary-900/60 dark:bg-neutral-900/95 dark:text-primary-300"
                                        >
                                            <span class="h-3 w-3 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600 motion-reduce:animate-none dark:border-primary-800 dark:border-t-primary-400" aria-hidden="true"></span>
                                            Updating forecast
                                        </div>

                                        {{-- Y-axis scale figures (cleanly positioned without overlapping text) --}}
                                        <div class="pointer-events-none absolute left-0.5 top-0 z-10 select-none">
                                            <span class="text-[9px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Units/day</span>
                                        </div>
                                        <template x-for="(tick, index) in displayChartTicks()" x-bind:key="`y-label-${index}`">
                                            <span
                                                class="pointer-events-none absolute left-0.5 z-10 -translate-y-1/2 select-none text-[10px] font-medium tabular-nums text-neutral-400 dark:text-neutral-500"
                                                x-bind:style="`top: ${tick.top}%`"
                                                x-text="formatNumber(tick.value, 1)"
                                            ></span>
                                        </template>

                                        {{-- Interactive Hover Tooltip Popover --}}
                                        <div
                                            data-chart-inspector
                                            x-ref="chartInspector"
                                            x-show="activePoint && (isHovering || isDragging || isFocused)"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                            x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                            class="pointer-events-none absolute z-30 min-w-[210px] max-w-[280px] rounded-lg border border-neutral-200/90 bg-white/95 p-2.5 shadow-lg backdrop-blur-sm transition-[left,top] duration-75 ease-out dark:border-neutral-700/80 dark:bg-neutral-900/95 dark:shadow-2xl dark:shadow-black/50"
                                            x-bind:style="tooltipStyle()"
                                        >
                                            <p class="sr-only">Hover, drag, or use the arrow keys to inspect either line.</p>

                                            {{-- Tooltip Header: Date & Horizon Badge --}}
                                            <div class="mb-2 flex items-center justify-between gap-2 border-b border-neutral-100 pb-1.5 dark:border-neutral-800">
                                                <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-100" x-text="activePoint?.formattedDate"></span>
                                                <template x-if="activePoint?.isFuture">
                                                    <span class="inline-flex items-center rounded border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-violet-700 dark:border-violet-800/80 dark:bg-violet-950/60 dark:text-violet-300">
                                                        AI Forecast
                                                    </span>
                                                </template>
                                                <template x-if="!activePoint?.isFuture">
                                                    <span class="inline-flex items-center rounded border border-primary-200 bg-primary-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-primary-700 dark:border-primary-800/80 dark:bg-primary-950/60 dark:text-primary-300">
                                                        Historical
                                                    </span>
                                                </template>
                                            </div>

                                            {{-- Tooltip Content: Future Forecast Horizon --}}
                                            <template x-if="activePoint?.isFuture">
                                                <div class="space-y-1.5 text-xs">
                                                    {{-- AI Forecast Row --}}
                                                    <div class="flex items-center justify-between gap-3">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="h-2 w-2 rounded-full bg-violet-600 ring-2 ring-violet-200 dark:ring-violet-900/60"></span>
                                                            <span class="font-medium text-neutral-700 dark:text-neutral-300">AI Forecast:</span>
                                                        </div>
                                                        <div class="text-right tabular-nums">
                                                            <span class="font-bold text-violet-700 dark:text-violet-400" x-text="formatNumber(activePoint?.forecastPoint?.value ?? activePoint?.value, 1)"></span>
                                                            <span class="text-[10px] font-normal text-neutral-500 dark:text-neutral-400">units/day</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex justify-between pl-3.5 text-[10px] tabular-nums text-neutral-400 dark:text-neutral-400">
                                                        <span>Period total:</span>
                                                        <span x-text="`${formatNumber(activePoint?.forecastPoint?.quantity ?? activePoint?.quantity)} units`"></span>
                                                    </div>

                                                    <template x-if="activePoint?.forecastPoint?.confidence">
                                                        <div class="flex justify-between pl-3.5 text-[10px] text-neutral-400 dark:text-neutral-400">
                                                            <span>Confidence:</span>
                                                            <span class="font-medium capitalize text-neutral-600 dark:text-neutral-300" x-text="activePoint.forecastPoint.confidence"></span>
                                                        </div>
                                                    </template>

                                                </div>
                                            </template>

                                            {{-- Tooltip Content: Recorded History --}}
                                            <template x-if="!activePoint?.isFuture">
                                                <div class="space-y-1 text-xs">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                                                            <span class="font-medium text-neutral-700 dark:text-neutral-300">Recorded Demand:</span>
                                                        </div>
                                                        <div class="text-right tabular-nums">
                                                            <span class="font-bold text-primary-700 dark:text-primary-400" x-text="formatNumber(activePoint?.value, 1)"></span>
                                                            <span class="text-[10px] font-normal text-neutral-500 dark:text-neutral-400">units/day</span>
                                                        </div>
                                                    </div>
                                                    <div class="text-right text-[10px] tabular-nums text-neutral-400 dark:text-neutral-400">
                                                        <span x-text="`${formatNumber(activePoint?.quantity)} units across ${activePoint?.days || 1} ${(activePoint?.days || 1) === 1 ? 'day' : 'days'}`"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <svg
                                            x-ref="chartSvg"
                                            class="h-48 w-full cursor-crosshair select-none touch-none sm:h-52 lg:h-72 xl:h-80"
                                            viewBox="0 0 760 240"
                                            role="img"
                                            aria-label="Demand vs Forecast: Historical and forecast inventory demand"
                                            aria-describedby="dashboard-demand-chart-description"
                                            preserveAspectRatio="none"
                                            x-on:pointerdown="onChartPointerDown($event)"
                                            x-on:pointermove="onChartPointerMove($event)"
                                            x-on:pointerup="onChartPointerUp($event)"
                                            x-on:pointercancel="onChartPointerCancel($event)"
                                            x-on:pointerleave="onChartPointerLeave($event)"
                                            x-on:focus="isFocused = true"
                                            x-on:blur="isFocused = false"
                                            tabindex="0"
                                            x-on:keydown.arrow-left.prevent="stepPoint(-1)"
                                            x-on:keydown.arrow-right.prevent="stepPoint(1)"
                                        >
                                            <desc id="dashboard-demand-chart-description">The blue solid line shows recorded consumption. The violet dashed line continues from the historical boundary with the validated AI forecast.</desc>
                                            <defs>
                                                <linearGradient id="dashboard-historical-area" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#1c75f5" stop-opacity="0.22"></stop>
                                                    <stop offset="100%" stop-color="#1c75f5" stop-opacity="0.01"></stop>
                                                </linearGradient>
                                                <linearGradient id="dashboard-forecast-area" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.18"></stop>
                                                    <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.01"></stop>
                                                </linearGradient>
                                            </defs>

                                            {{-- Static SVG lines render reliably; their positions match chartTicks(). --}}
                                            <line data-chart-grid-line x1="50" y1="40" x2="725" y2="40" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line data-chart-grid-line x1="50" y1="81.25" x2="725" y2="81.25" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line data-chart-grid-line x1="50" y1="122.5" x2="725" y2="122.5" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line data-chart-grid-line x1="50" y1="163.75" x2="725" y2="163.75" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                            <line data-chart-grid-line x1="50" y1="205" x2="725" y2="205" class="stroke-slate-200 dark:stroke-neutral-700" stroke-width="1" vector-effect="non-scaling-stroke"></line>

                                            {{-- Forecast horizon wash keeps the two time regions legible without relying on line color. --}}
                                            <rect
                                                x-bind:x="displayTransitionX()"
                                                y="20"
                                                x-bind:width="Math.max(0, 725 - displayTransitionX())"
                                                height="185"
                                                rx="4"
                                                fill="#8b5cf6"
                                                fill-opacity="0.035"
                                            ></rect>

                                            {{-- Subtle Forecast Boundary Line --}}
                                            <line x-bind:x1="displayTransitionX()" y1="20" x-bind:x2="displayTransitionX()" y2="205" class="stroke-slate-300 dark:stroke-neutral-600" stroke-width="1.5" stroke-dasharray="2 3" vector-effect="non-scaling-stroke"></line>

                                            {{-- Gradient Fills --}}
                                            <path x-show="showActual" x-bind:d="displayHistoricalAreaPath()" fill="url(#dashboard-historical-area)"></path>
                                            <path x-show="showForecast" x-bind:d="displayForecastAreaPath()" fill="url(#dashboard-forecast-area)"></path>

                                            {{-- Recorded demand (solid blue) --}}
                                            <path x-show="showActual" x-bind:d="seriesPath(displayHistoricalRenderPoints())" fill="none" stroke="#1c75f5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>

                                            {{-- AI Forecast Line (Dashed Violet) --}}
                                            <path x-show="showForecast" x-bind:d="seriesPath(chartLinePoints(displayForecastRenderPoints(), 725))" fill="none" stroke="#8b5cf6" stroke-width="3" stroke-dasharray="6 4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>

                                            {{-- Historical Data Markers --}}
                                            <template x-for="(point, index) in displayHistoricalPoints()" x-bind:key="`hist-${index}`">
                                                <circle x-show="showActual" x-bind:cx="point.x" x-bind:cy="point.y" r="3" fill="#1c75f5" fill-opacity="0.6" vector-effect="non-scaling-stroke"></circle>
                                            </template>

                                            {{-- Forecast Point Markers --}}
                                            <template x-for="(point, index) in displayForecastPoints()" x-bind:key="`fore-${index}`">
                                                <circle x-show="showForecast" x-bind:cx="point.x" x-bind:cy="point.y" r="3.5" class="fill-white dark:fill-neutral-900" stroke="#8b5cf6" stroke-width="2" vector-effect="non-scaling-stroke"></circle>
                                            </template>

                                            {{-- Interactive Timeline Scrubber Line & Handles (Moves dynamically with hover, drag, and click) --}}
                                            <template x-if="activePoint && (isHovering || isDragging || isFocused)">
                                                <g class="transition-all duration-75">
                                                    {{-- Vertical Scrubber Guideline --}}
                                                    <line
                                                        x-bind:x1="activePoint.x"
                                                        y1="20"
                                                        x-bind:x2="activePoint.x"
                                                        y2="205"
                                                        class="stroke-slate-700 dark:stroke-neutral-300"
                                                        stroke-width="1.5"
                                                        stroke-dasharray="3 3"
                                                        vector-effect="non-scaling-stroke"
                                                    ></line>

                                                    {{-- Forecast point bead --}}
                                                    <template x-if="activePoint.isFuture">
                                                        <g>
                                                            {{-- AI Forecast Bead (Violet) --}}
                                                            <template x-if="showForecast && activePoint.forecastPoint">
                                                                <g>
                                                                    <circle
                                                                        x-bind:cx="activePoint.x"
                                                                        x-bind:cy="activePoint.forecastPoint.y"
                                                                        r="11"
                                                                        fill="#8b5cf6"
                                                                        fill-opacity="0.25"
                                                                        class="animate-pulse"
                                                                    ></circle>
                                                                    <circle
                                                                        x-bind:cx="activePoint.x"
                                                                        x-bind:cy="activePoint.forecastPoint.y"
                                                                        r="5"
                                                                        stroke="#8b5cf6"
                                                                        stroke-width="2.5"
                                                                        vector-effect="non-scaling-stroke"
                                                                        class="cursor-grab active:cursor-grabbing fill-white dark:fill-neutral-900"
                                                                    ></circle>
                                                                    <circle
                                                                        x-bind:cx="activePoint.x"
                                                                        x-bind:cy="activePoint.forecastPoint.y"
                                                                        r="2"
                                                                        fill="#8b5cf6"
                                                                        vector-effect="non-scaling-stroke"
                                                                    ></circle>
                                                                </g>
                                                            </template>

                                                        </g>
                                                    </template>

                                                    {{-- Historical point bead (Blue) --}}
                                                    <template x-if="!activePoint.isFuture">
                                                        <g>
                                                            <circle
                                                                x-bind:cx="activePoint.x"
                                                                x-bind:cy="activePoint.y"
                                                                r="11"
                                                                fill="#1c75f5"
                                                                fill-opacity="0.25"
                                                                class="animate-pulse"
                                                            ></circle>
                                                            <circle
                                                                x-bind:cx="activePoint.x"
                                                                x-bind:cy="activePoint.y"
                                                                r="5"
                                                                stroke="#1c75f5"
                                                                stroke-width="2.5"
                                                                vector-effect="non-scaling-stroke"
                                                                class="cursor-grab active:cursor-grabbing fill-white dark:fill-neutral-900"
                                                            ></circle>
                                                            <circle
                                                                x-bind:cx="activePoint.x"
                                                                x-bind:cy="activePoint.y"
                                                                r="2"
                                                                fill="#1c75f5"
                                                                vector-effect="non-scaling-stroke"
                                                            ></circle>
                                                        </g>
                                                    </template>
                                                </g>
                                            </template>
                                        </svg>
                                    </div>

                                    {{-- Chronological X-axis Labels --}}
                                    <div
                                        class="mt-2 grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-neutral-100 pt-2 text-xs text-neutral-500 transition-opacity duration-150 motion-reduce:transition-none dark:border-neutral-800 dark:text-neutral-400"
                                        x-bind:class="chartAnimating ? 'opacity-60' : 'opacity-100'"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="tabular-nums font-medium text-neutral-700 dark:text-neutral-300" x-text="chartStartLabel(historicalSeries())"></span>
                                            <span class="hidden text-neutral-400 dark:text-neutral-500 sm:inline">Recorded history</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 px-3 font-semibold text-neutral-800 dark:text-neutral-200">
                                            <span class="h-2 w-2 rounded-full bg-neutral-600 dark:bg-neutral-400"></span>
                                            <span>Forecast starts (<span class="tabular-nums text-primary-700 dark:text-primary-400" x-text="chartTransitionLabel()"></span>)</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="hidden text-neutral-400 dark:text-neutral-500 sm:inline">AI forecast horizon</span>
                                            <span class="tabular-nums font-semibold text-violet-700 dark:text-violet-400" x-text="chartEndLabel(forecastSeries())"></span>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="!hasChartData()" x-cloak class="border-y border-neutral-100 py-12 text-center text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                    Historical and forecast series are not available for the current selection.
                                </div>
                            </div>

                        </section>

                    </div>
                </div>
            </x-ui.card>

            <aside class="space-y-4" aria-label="Dashboard attention panels">
                <x-ui.card :padding="false">
                    <x-slot:header>
                        <h2 class="text-sm font-semibold text-neutral-900">Inventory attention</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Highest-priority live alerts <span class="text-neutral-400" x-text="statusLabel"></span>
                        </p>
                    </x-slot:header>

                    <x-slot:actions>
                        <x-ui.button variant="ghost" size="sm" :href="route('inventory.alerts')">View all</x-ui.button>
                    </x-slot:actions>

                    <div data-dashboard-alerts>
                        @include('inventory.partials.dashboard-alerts')
                    </div>
                </x-ui.card>

                <x-ui.card :padding="false" class="min-w-0">
                    <div class="p-3 space-y-2.5">
                        {{-- Header & Context: Compact AI Forecast Insight Identity & Dynamic Statement --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300 ring-1 ring-primary-200 dark:ring-primary-800/50">
                                    <x-ui.icon name="arrow-trending-up" class="h-3.5 w-3.5" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h2 class="text-xs font-bold text-neutral-900 dark:text-neutral-100" x-text="forecast?.source === 'ai' ? 'AI forecast insight' : 'Forecast insight'">AI forecast insight</h2>
                                        <span class="text-[10px] font-medium text-neutral-400 dark:text-neutral-500" x-text="`&middot; ${forecast?.forecast_period || `Next ${forecastDays} days`}`"></span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] leading-snug text-neutral-600 dark:text-neutral-300 line-clamp-2" x-text="isLoading() ? 'Calculating demand insights and risk projections...' : (forecast ? insight() : 'No forecast insights available.')"></p>
                                </div>
                            </div>
                            <span x-show="isLoading()" x-cloak class="inline-flex shrink-0 items-center gap-1 text-[10px] text-primary-600 dark:text-primary-400">
                                <x-ui.icon name="arrow-path" class="h-3 w-3 animate-spin" />
                            </span>
                        </div>

                        {{-- 1. Loading Skeleton State --}}
                        <div
                            x-show="isLoading()"
                            aria-busy="true"
                        >
                            <x-ui.forecast-insight-skeleton />
                        </div>

                        {{-- 2. Empty Skeleton State --}}
                        <div
                            x-show="isEmpty()"
                            x-cloak
                            aria-busy="true"
                        >
                            <x-ui.forecast-insight-skeleton />
                        </div>

                        {{-- 3. Error State --}}
                        <div x-show="isError()" x-cloak class="rounded-lg border border-danger-200 bg-danger-50/60 p-4 text-center text-xs text-danger-700 dark:border-danger-900/60 dark:bg-rose-950/20 dark:text-rose-300">
                            Unable to calculate forecast insights.
                        </div>

                        {{-- 4. Compact Forecast Overview Content --}}
                        <div x-show="isSuccess()" x-cloak class="space-y-2.5">
                            {{-- 1. Key Forecast Indicators (Ultra-Dense 4-Col Grid) --}}
                            <div class="grid grid-cols-4 gap-1.5 rounded-lg border border-neutral-200/90 bg-neutral-50/70 p-1.5 text-center dark:border-neutral-800 dark:bg-neutral-800/40">
                                {{-- Projected at-risk items --}}
                                <div title="Projected at-risk items" class="min-w-0">
                                    <span class="block text-[10px] font-medium text-neutral-500 dark:text-neutral-400 truncate">Projected at-risk items</span>
                                    <span class="text-base font-bold tabular-nums text-danger-700 dark:text-rose-400" x-text="highRiskCount()"></span>
                                </div>
                                {{-- Low / No Stock --}}
                                <div title="Low / No Stock" class="min-w-0">
                                    <span class="block text-[10px] font-medium text-neutral-500 dark:text-neutral-400 truncate">Low / No Stock</span>
                                    <span class="text-base font-bold tabular-nums text-amber-600 dark:text-amber-400" x-text="lowStockRiskCount()"></span>
                                </div>
                                {{-- High Demand --}}
                                <div title="High Demand" class="min-w-0">
                                    <span class="block text-[10px] font-medium text-neutral-500 dark:text-neutral-400 truncate">High Demand</span>
                                    <span class="text-base font-bold tabular-nums text-violet-700 dark:text-violet-400" x-text="highDemandCount()"></span>
                                </div>
                                {{-- Forecast Items --}}
                                <div title="Forecast Items" class="min-w-0">
                                    <span class="block text-[10px] font-medium text-neutral-500 dark:text-neutral-400 truncate">Forecast Items</span>
                                    <span class="text-base font-bold tabular-nums text-neutral-900 dark:text-neutral-100" x-text="allItems().length"></span>
                                </div>
                            </div>

                            {{-- 2. Confidence & Demand Risk Breakdown (Combined Dense Row) --}}
                            <div class="space-y-1 text-[11px]">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1">
                                        <span class="font-medium text-neutral-500 dark:text-neutral-400">Risk:</span>
                                        <span class="font-bold text-danger-600 dark:text-rose-400 tabular-nums" x-text="`${highRiskCount()} High`"></span> &middot;
                                        <span class="font-bold text-amber-600 dark:text-amber-400 tabular-nums" x-text="`${moderateRiskCount()} Med`"></span> &middot;
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400 tabular-nums" x-text="`${lowRiskCount()} Low`"></span>
                                    </div>
                                    <div class="text-[10px] tabular-nums text-neutral-400 dark:text-neutral-500 flex items-center gap-1">
                                        <span class="font-medium">Forecast confidence:</span>
                                        <strong class="capitalize font-bold text-neutral-800 dark:text-neutral-200" x-text="confidenceLabel()"></strong>
                                        <span
                                            x-show="lowStockRiskCount() > 0 && confidenceLabel() === 'Low'"
                                            x-cloak
                                            class="text-[10px] font-medium text-warning-700 dark:text-amber-400"
                                        >(review needed)</span>
                                    </div>
                                </div>
                                {{-- Proportional Segmented Bar --}}
                                <div class="flex h-1.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <div
                                        class="bg-danger-500 dark:bg-rose-500 transition-all duration-300"
                                        x-bind:style="`width: ${allItems().length ? (highRiskCount() / allItems().length) * 100 : 0}%`"
                                    ></div>
                                    <div
                                        class="bg-warning-500 dark:bg-amber-500 transition-all duration-300"
                                        x-bind:style="`width: ${allItems().length ? (moderateRiskCount() / allItems().length) * 100 : 0}%`"
                                    ></div>
                                    <div
                                        class="bg-success-500 dark:bg-emerald-500 transition-all duration-300"
                                        x-bind:style="`width: ${allItems().length ? (lowRiskCount() / allItems().length) * 100 : 0}%`"
                                    ></div>
                                </div>
                            </div>

                            {{-- 3. Top Forecast Risks: Compact Dual-Bar Comparison --}}
                            <div class="space-y-1.5 pt-0.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold text-neutral-900 dark:text-neutral-100">Top Forecast Risks</span>
                                    <button
                                        type="button"
                                        x-on:click="clearFilters(); $dispatch('open-modal', 'dashboard-demand-forecast')"
                                        class="text-[10px] font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        Review all <span x-text="allItems().length"></span> forecast items &rarr;
                                    </button>
                                </div>

                                <div class="space-y-1.5">
                                    <template x-for="item in topRiskItems(2)" x-bind:key="item.item_id">
                                        <div
                                            class="group/item cursor-pointer rounded-md border border-neutral-200/80 bg-neutral-50/50 p-2 transition hover:border-primary-300 hover:bg-primary-50/20 dark:border-neutral-800 dark:bg-neutral-800/30 dark:hover:border-primary-700/50 dark:hover:bg-primary-950/20"
                                            x-on:click="selectItem(item.item_id)"
                                            x-on:mouseenter="setMiniHover(item, $event)"
                                            x-on:mouseleave="clearMiniHover()"
                                            tabindex="0"
                                            x-on:keydown.enter="selectItem(item.item_id)"
                                            aria-label="Select item for chart inspection"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="truncate text-xs font-semibold text-neutral-900 dark:text-neutral-100" x-text="item.item_name"></p>
                                                <span
                                                    class="shrink-0 text-[10px] font-bold uppercase tracking-wider"
                                                    x-bind:class="item.risk_level === 'high' ? 'text-danger-600 dark:text-rose-400' : (item.risk_level === 'medium' ? 'text-amber-600 dark:text-amber-400' : 'text-success-600 dark:text-emerald-400')"
                                                    x-text="`${item.risk_level} risk`"
                                                ></span>
                                            </div>

                                            {{-- Compact Dual Micro-Bars --}}
                                            <div class="mt-1 space-y-1 text-[10px]">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-11 text-neutral-500 dark:text-neutral-400 shrink-0">Demand</span>
                                                    <div class="h-1.5 flex-1 rounded-full bg-neutral-200/70 dark:bg-neutral-700 overflow-hidden">
                                                        <div
                                                            class="h-full rounded-full bg-violet-600 dark:bg-violet-500 transition-all"
                                                            x-bind:style="`width: ${Math.min(100, Math.max(10, (Number(item.predicted_demand || 0) / Math.max(Number(item.predicted_demand || 1), Number(item.current_stock || 1))) * 100))}%`"
                                                        ></div>
                                                    </div>
                                                    <span class="w-11 text-right font-medium tabular-nums text-neutral-800 dark:text-neutral-200 shrink-0" x-text="formatNumber(item.predicted_demand)"></span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-11 text-neutral-500 dark:text-neutral-400 shrink-0">Stock</span>
                                                    <div class="h-1.5 flex-1 rounded-full bg-neutral-200/70 dark:bg-neutral-700 overflow-hidden">
                                                        <div
                                                            class="h-full rounded-full transition-all"
                                                            x-bind:class="item.current_stock <= 0 ? 'bg-danger-500 dark:bg-rose-500' : (item.current_stock < item.predicted_demand ? 'bg-amber-500 dark:bg-amber-400' : 'bg-emerald-500 dark:bg-emerald-400')"
                                                            x-bind:style="`width: ${Math.min(100, Math.max(item.current_stock > 0 ? 8 : 0, (Number(item.current_stock || 0) / Math.max(Number(item.predicted_demand || 1), Number(item.current_stock || 1))) * 100))}%`"
                                                        ></div>
                                                    </div>
                                                    <span class="w-11 text-right font-medium tabular-nums shrink-0" x-bind:class="item.current_stock <= 0 ? 'text-danger-600 dark:text-rose-400 font-bold' : 'text-neutral-800 dark:text-neutral-200'" x-text="formatNumber(item.current_stock)"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- 4. Compact Action Bar --}}
                            <div class="flex items-center gap-1.5 pt-0.5">
                                <button
                                    type="button"
                                    x-show="forecast"
                                    x-cloak
                                    x-on:click="risk = 'high'; $dispatch('open-modal', 'dashboard-demand-forecast')"
                                    class="flex-1 inline-flex items-center justify-center gap-1 rounded-md border border-danger-200 bg-danger-50/80 py-1 px-2 text-xs font-semibold text-danger-700 shadow-2xs hover:bg-danger-100 dark:border-danger-800/80 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/50 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                                >
                                    <x-ui.icon name="exclamation-triangle" class="h-3 w-3" />
                                    <span>Review High-Risk (<span x-text="highRiskCount()"></span>)</span>
                                </button>
                                <a
                                    href="{{ route('inventory.demand-forecast') }}"
                                    class="flex-1 inline-flex items-center justify-center gap-1 rounded-md border border-neutral-300 bg-white py-1 px-2 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 transition truncate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                >
                                    <span>Full Module</span>
                                    <x-ui.icon name="arrow-right" class="h-3 w-3" />
                                </a>
                            </div>
                        </div>

                        {{-- Preserved Advisory Footer --}}
                        <div class="border-t border-neutral-100 pt-1.5 dark:border-neutral-800/80 flex items-center justify-between text-[10px]">
                            <p
                                class="leading-tight text-neutral-400 dark:text-neutral-500"
                                x-bind:class="lowStockRiskCount() > 0 && confidenceLabel() === 'Low' ? 'font-medium text-warning-700 dark:text-amber-400' : 'text-neutral-400 dark:text-neutral-500'"
                                x-text="lowStockRiskCount() > 0 && confidenceLabel() === 'Low'
                                    ? 'Low confidence: risk is preliminary - verify movement history before acting.'
                                    : 'Advisory only; no stock or purchase order is changed automatically.'"
                            >Advisory only; no stock or purchase order is changed automatically.</p>
                            @can(\App\Enums\Permission::CreateRequisition->value)
                                <a href="{{ route('inventory.requisitions.index') }}" class="shrink-0 text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 ml-2">Requisitions &rarr;</a>
                            @endcan
                        </div>
                    </div>

                    {{-- Fixed Intelligent Tooltip for Top Forecast Risks (Positioned safely without clipping) --}}
                    <div
                        x-show="hoveredMiniItem"
                        x-cloak
                        x-bind:style="miniTooltipStyle"
                        class="pointer-events-none w-64 rounded-xl border border-neutral-200/90 bg-white/95 p-2.5 text-xs shadow-xl backdrop-blur-xs transition-all dark:border-neutral-700 dark:bg-neutral-900/95 dark:text-neutral-100"
                    >
                        <div class="border-b border-neutral-100 pb-1 dark:border-neutral-800">
                            <p class="font-bold text-neutral-900 dark:text-neutral-100 truncate" x-text="hoveredMiniItem?.item_name"></p>
                            <p class="text-[10px] text-neutral-400 dark:text-neutral-500" x-text="`SKU: ${hoveredMiniItem?.sku || 'N/A'} &middot; ${hoveredMiniItem?.category || 'General'}`"></p>
                        </div>
                        <dl class="mt-1.5 grid grid-cols-2 gap-x-2 gap-y-1 text-[10px]">
                            <div>
                                <dt class="text-neutral-400 dark:text-neutral-500">Predicted Demand</dt>
                                <dd class="font-bold tabular-nums text-violet-700 dark:text-violet-400" x-text="`${formatNumber(hoveredMiniItem?.predicted_demand)} units`"></dd>
                            </div>
                            <div>
                                <dt class="text-neutral-400 dark:text-neutral-500">Available Stock</dt>
                                <dd class="font-bold tabular-nums" x-bind:class="hoveredMiniItem?.current_stock <= 0 ? 'text-danger-600 dark:text-rose-400' : 'text-neutral-800 dark:text-neutral-200'" x-text="`${formatNumber(hoveredMiniItem?.current_stock)} units`"></dd>
                            </div>
                            <div>
                                <dt class="text-neutral-400 dark:text-neutral-500">Reorder Suggested</dt>
                                <dd class="font-bold tabular-nums text-amber-600 dark:text-amber-400" x-text="`${formatNumber(hoveredMiniItem?.recommended_reorder_quantity)} units`"></dd>
                            </div>
                            <div>
                                <dt class="text-neutral-400 dark:text-neutral-500">Demand Trend</dt>
                                <dd class="font-semibold capitalize text-neutral-800 dark:text-neutral-200" x-text="hoveredMiniItem?.demand_trend || 'stable'"></dd>
                            </div>
                        </dl>
                        <p class="mt-1.5 text-[10px] italic leading-tight text-neutral-500 dark:text-neutral-400 line-clamp-2" x-text="hoveredMiniItem?.explanation"></p>
                    </div>
                </x-ui.card>
            </aside>

            <x-ui.modal name="dashboard-demand-forecast" title="Full Demand Forecast" maxWidth="6xl">
                <div class="space-y-4">
                    {{-- Modal Header Bar: Context badges, live item counter, and conditional clear action --}}
                    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                <span class="font-medium text-neutral-700 dark:text-neutral-300" x-text="forecast?.forecast_period"></span> · Showing <strong class="font-bold tabular-nums text-neutral-900 dark:text-neutral-100" x-text="filteredItems().length"></strong> of <strong class="font-semibold tabular-nums text-neutral-700 dark:text-neutral-300" x-text="allItems().length"></strong> items
                            </span>
                        </div>

                        {{-- Active Filter Action: Only shows when filters are actually active --}}
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                x-show="activeFilterCount() > 0"
                                x-cloak
                                x-on:click="clearFilters()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-primary-300 bg-primary-50/90 px-2.5 py-1.5 text-xs font-semibold text-primary-700 shadow-2xs hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-primary-800 dark:bg-primary-950/60 dark:text-primary-300 dark:hover:bg-primary-900/60 transition"
                            >
                                <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                                <span>Clear Filters</span>
                                <span
                                    class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary-200/80 px-1 text-[10px] font-bold text-primary-800 dark:bg-primary-800/80 dark:text-primary-200"
                                    x-text="activeFilterCount()"
                                ></span>
                            </button>
                        </div>
                    </div>

                    {{-- Live Filter Toolbar: Search, Category, and Risk filters right inside the modal --}}
                    <div class="rounded-xl border border-neutral-200/90 bg-neutral-50/70 p-3 dark:border-neutral-800 dark:bg-neutral-800/40">
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-12">
                            {{-- Search Input (lg:col-span-5) --}}
                            <div class="relative lg:col-span-5">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 dark:text-neutral-500">
                                    <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                                </div>
                                <input
                                    id="dashboard-forecast-modal-search"
                                    type="search"
                                    x-model.debounce.150ms="search"
                                    placeholder="Search medicine, supply name, SKU..."
                                    autocomplete="off"
                                    class="block w-full min-h-9 rounded-lg border border-neutral-300 bg-white pl-9 pr-8 text-xs text-neutral-900 shadow-2xs placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-500"
                                >
                                <button
                                    type="button"
                                    x-show="search.trim().length > 0"
                                    x-cloak
                                    x-on:click="search = ''"
                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                    aria-label="Clear search text"
                                >
                                    <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            {{-- Category Select (lg:col-span-4) - Mandatory pl-2.5 pr-8 clearance --}}
                            <div class="min-w-0 lg:col-span-4">
                                <select
                                    id="dashboard-forecast-modal-category"
                                    x-model="category"
                                    class="block w-full min-h-9 rounded-lg border border-neutral-300 bg-white pl-2.5 pr-8 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"
                                    aria-label="Filter by item category"
                                >
                                    <option value="">All item categories</option>
                                    @foreach ($forecastCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Risk Level Select (lg:col-span-3) - Mandatory pl-2.5 pr-8 clearance --}}
                            <div class="min-w-0 lg:col-span-3">
                                <select
                                    id="dashboard-forecast-modal-risk"
                                    x-model="risk"
                                    class="block w-full min-h-9 rounded-lg border border-neutral-300 bg-white pl-2.5 pr-8 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"
                                    aria-label="Filter by risk level"
                                >
                                    <option value="">All risk levels</option>
                                    <option value="high">High risk only</option>
                                    <option value="medium">Medium risk only</option>
                                    <option value="low">Low risk only</option>
                                </select>
                            </div>
                        </div>

                        {{-- Active Filter Chips Row (renders dynamically when activeFilterCount() > 0) --}}
                        <div
                            x-show="activeFilterCount() > 0"
                            x-cloak
                            class="mt-2.5 flex flex-wrap items-center gap-1.5 border-t border-neutral-200/80 pt-2 text-xs dark:border-neutral-700/60"
                        >
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 mr-1">Active:</span>

                            {{-- Search Chip --}}
                            <template x-if="search.trim().length > 0">
                                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-0.5 text-xs font-medium text-neutral-700 shadow-2xs ring-1 ring-inset ring-neutral-300 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span>Keyword: <strong class="text-neutral-900 dark:text-white" x-text="search.trim()"></strong></span>
                                    <button type="button" x-on:click="search = ''" class="text-neutral-400 hover:text-neutral-700 dark:hover:text-white" aria-label="Clear keyword filter">
                                        <x-ui.icon name="x-mark" class="h-3 w-3" />
                                    </button>
                                </span>
                            </template>

                            {{-- Category Chip --}}
                            <template x-if="category !== ''">
                                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-0.5 text-xs font-medium text-neutral-700 shadow-2xs ring-1 ring-inset ring-neutral-300 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span>Category: <strong class="text-neutral-900 dark:text-white" x-text="activeCategoryName()"></strong></span>
                                    <button type="button" x-on:click="category = ''" class="text-neutral-400 hover:text-neutral-700 dark:hover:text-white" aria-label="Clear category filter">
                                        <x-ui.icon name="x-mark" class="h-3 w-3" />
                                    </button>
                                </span>
                            </template>

                            {{-- Risk Chip --}}
                            <template x-if="risk !== ''">
                                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-0.5 text-xs font-medium text-neutral-700 shadow-2xs ring-1 ring-inset ring-neutral-300 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span>Risk: <strong class="capitalize text-neutral-900 dark:text-white" x-text="risk"></strong></span>
                                    <button type="button" x-on:click="risk = ''" class="text-neutral-400 hover:text-neutral-700 dark:hover:text-white" aria-label="Clear risk filter">
                                        <x-ui.icon name="x-mark" class="h-3 w-3" />
                                    </button>
                                </span>
                            </template>

                            <button
                                type="button"
                                x-on:click="clearFilters()"
                                class="ml-1 text-[11px] font-semibold text-primary-700 hover:text-primary-800 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                            >
                                Reset all
                            </button>
                        </div>
                    </div>

                    {{-- Empty Filter Results State --}}
                    <div x-show="filteredItems().length === 0" class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50 px-4 py-8 text-center dark:border-neutral-700 dark:bg-neutral-900/50">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-neutral-200/70 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                            <x-ui.icon name="funnel" class="h-5 w-5" />
                        </span>
                        <p class="mt-2.5 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No matching forecast items</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">None of the forecast items match your current filter criteria.</p>
                        <div class="mt-3.5">
                            <x-ui.button type="button" variant="secondary" size="sm" icon="arrow-path" x-on:click="clearFilters()">Reset All Filters</x-ui.button>
                        </div>
                    </div>

                    {{-- Desktop & Tablet Table (100% width, no horizontal scrollbar, clear column proportions) --}}
                    <div x-show="filteredItems().length > 0" class="hidden md:block rounded-lg border border-neutral-200 overflow-hidden bg-white dark:border-neutral-800 dark:bg-neutral-900">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-neutral-50 text-neutral-500 border-b border-neutral-200 dark:bg-neutral-800/80 dark:text-neutral-400 dark:border-neutral-700">
                                <tr>
                                    <th scope="col" class="w-[34%] px-3.5 py-2.5 font-semibold text-neutral-700 dark:text-neutral-300">Item & Explanation</th>
                                    <th scope="col" class="w-[9%] px-2.5 py-2.5 text-right font-semibold text-neutral-700 dark:text-neutral-300">Current</th>
                                    <th scope="col" class="w-[10%] px-2.5 py-2.5 text-right font-semibold text-neutral-700 dark:text-neutral-300">Historical</th>
                                    <th scope="col" class="w-[11%] px-2.5 py-2.5 text-right font-semibold text-neutral-700 dark:text-neutral-300">Forecast</th>
                                    <th scope="col" class="w-[11%] px-2.5 py-2.5 text-center font-semibold text-neutral-700 dark:text-neutral-300">Risk</th>
                                    <th scope="col" class="w-[11%] px-2.5 py-2.5 text-right font-semibold text-neutral-700 dark:text-neutral-300">Reorder</th>
                                    <th scope="col" class="w-[14%] px-3 py-2.5 font-semibold text-neutral-700 dark:text-neutral-300">Confidence</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                <template x-for="item in filteredItems()" x-bind:key="item.item_id">
                                    <tr class="align-top transition-colors hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50">
                                        <td class="px-3.5 py-3">
                                            <p class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="item.item_name"></p>
                                            <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5" x-text="`${item.sku}${item.category ? ` · ${item.category}` : ''}`"></p>
                                            <p x-show="item.explanation" class="mt-1.5 text-[11px] leading-relaxed text-neutral-600 dark:text-neutral-300" x-text="item.explanation"></p>
                                            <p x-show="item.limited_data" class="mt-1 font-medium text-[10px] text-warning-700 dark:text-warning-400">Limited movement history</p>
                                        </td>
                                        <td class="px-2.5 py-3 text-right tabular-nums text-neutral-700 dark:text-neutral-300" x-text="formatNumber(item.current_stock)"></td>
                                        <td class="px-2.5 py-3 text-right tabular-nums text-neutral-600 dark:text-neutral-400" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></td>
                                        <td class="px-2.5 py-3 text-right font-bold tabular-nums text-neutral-900 dark:text-neutral-100" x-text="formatNumber(item.predicted_demand)"></td>
                                        <td class="px-2.5 py-3 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset whitespace-nowrap" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                        </td>
                                        <td class="px-2.5 py-3 text-right font-bold tabular-nums text-primary-700 dark:text-primary-400" x-text="formatNumber(item.recommended_reorder_quantity)"></td>
                                        <td class="px-3 py-3 text-neutral-700 dark:text-neutral-300">
                                            <span class="font-medium capitalize text-neutral-900 dark:text-neutral-100 block" x-text="item.confidence"></span>
                                            <span class="text-[10px] text-neutral-500 dark:text-neutral-400 block leading-tight" x-text="`${item.demand_trend} trend`"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Card List (Zero horizontal scrolling on smartphones) --}}
                    <div x-show="filteredItems().length > 0" class="space-y-3 md:hidden">
                        <template x-for="item in filteredItems()" x-bind:key="item.item_id">
                            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 shadow-2xs space-y-2.5 dark:border-neutral-800 dark:bg-neutral-900">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-neutral-900 dark:text-neutral-100 text-xs leading-snug" x-text="item.item_name"></p>
                                        <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5" x-text="`${item.sku}${item.category ? ` · ${item.category}` : ''}`"></p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset whitespace-nowrap" x-bind:class="riskClasses(item.risk_level)" x-text="`${item.risk_level} risk`"></span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 p-2 text-center text-[11px]">
                                    <div class="rounded bg-white/80 dark:bg-neutral-800 py-1 border border-neutral-100 dark:border-neutral-700/60">
                                        <span class="block text-[10px] text-neutral-500 dark:text-neutral-400">Current</span>
                                        <span class="font-semibold text-neutral-800 dark:text-neutral-200 tabular-nums" x-text="formatNumber(item.current_stock)"></span>
                                    </div>
                                    <div class="rounded bg-white/80 dark:bg-neutral-800 py-1 border border-neutral-100 dark:border-neutral-700/60">
                                        <span class="block text-[10px] text-neutral-500 dark:text-neutral-400">Historical</span>
                                        <span class="font-medium text-neutral-600 dark:text-neutral-400 tabular-nums" x-text="item.historical_consumption == null ? '—' : formatNumber(item.historical_consumption)"></span>
                                    </div>
                                    <div class="rounded bg-white/80 dark:bg-neutral-800 py-1 border border-neutral-100 dark:border-neutral-700/60">
                                        <span class="block text-[10px] text-neutral-500 dark:text-neutral-400">Forecast</span>
                                        <span class="font-bold text-neutral-900 dark:text-neutral-100 tabular-nums" x-text="formatNumber(item.predicted_demand)"></span>
                                    </div>
                                    <div class="rounded bg-white/80 dark:bg-neutral-800 py-1 border border-neutral-100 dark:border-neutral-700/60">
                                        <span class="block text-[10px] text-primary-700 dark:text-primary-400">Reorder</span>
                                        <span class="font-bold text-primary-700 dark:text-primary-400 tabular-nums" x-text="formatNumber(item.recommended_reorder_quantity)"></span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
                                    <span>Confidence: <strong class="font-semibold capitalize text-neutral-800 dark:text-neutral-200" x-text="item.confidence"></strong></span>
                                    <span class="capitalize" x-text="`${item.demand_trend} trend`"></span>
                                </div>

                                <template x-if="item.explanation">
                                    <div class="text-[11px] text-neutral-600 dark:text-neutral-300 bg-neutral-50/80 dark:bg-neutral-800/60 rounded-lg p-2.5 border border-neutral-100 dark:border-neutral-700/60 leading-relaxed">
                                        <span class="font-semibold text-neutral-700 dark:text-neutral-200">Explanation:</span>
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
    @else
        <x-ui.card :padding="false">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Inventory attention</h2>
                <p class="mt-0.5 text-xs text-neutral-500">
                    Highest-priority live alerts <span class="text-neutral-400" x-text="statusLabel"></span>
                </p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.alerts')">View all</x-ui.button>
            </x-slot:actions>

            <div data-dashboard-alerts>
                @include('inventory.partials.dashboard-alerts')
            </div>
        </x-ui.card>
    @endcan

    <details hidden class="group overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm" data-dashboard-secondary>
        <summary class="flex cursor-pointer list-none flex-col gap-2 px-4 py-3 marker:content-none sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-neutral-900">Operational details</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Purchase orders, stock movements, suppliers, and storage summaries.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs sm:shrink-0">
                <span class="rounded-full bg-danger-50 px-2 py-0.5 font-medium text-danger-700">{{ number_format($outOfStockItems) }} out of stock</span>
                @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
                    <span class="rounded-full bg-warning-50 px-2 py-0.5 font-medium text-warning-700">{{ number_format($pendingPoCount) }} pending POs</span>
                @endcan
                <span class="inline-flex items-center gap-1 font-semibold text-primary-700">
                    <span class="group-open:hidden">Show details</span>
                    <span class="hidden group-open:inline">Hide details</span>
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform group-open:rotate-180" />
                </span>
            </div>
        </summary>

        <div class="border-t border-neutral-200 bg-neutral-50/60 p-3">
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
        </div>
    </details>
    </div>{{-- /dashboardLive --}}


    {{-- HIMS AI Inventory Assistant Floating Chatbot --}}
    @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::ViewReports->value])
    <aside
        x-data="himsAiAssistant({
            endpoint: '{{ route('dashboard.ai-assistant') }}',
            conversationsEndpoint: '{{ route('dashboard.ai-assistant.conversations') }}',
            activeEndpoint: '{{ route('dashboard.ai-assistant.active') }}',
            conversationShowBase: '{{ url('/dashboard/ai-assistant/conversations') }}',
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
            {{-- Confirmation Dialog for New Chat --}}
            <div
                x-show="showNewChatConfirm"
                x-transition.opacity
                class="absolute inset-0 z-40 flex items-center justify-center bg-neutral-900/40 p-4 backdrop-blur-2xs"
            >
                <div
                    x-show="showNewChatConfirm"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="scale-95 opacity-0"
                    x-transition:enter-end="scale-100 opacity-100"
                    class="w-full max-w-xs rounded-2xl border border-neutral-200 bg-white p-4 shadow-xl"
                >
                    <div class="flex items-center gap-2.5 text-primary-600">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                        <h4 class="text-xs font-bold text-neutral-900">Start a new chat?</h4>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-neutral-600">
                        This will start a new conversation. Your current chat will remain available in your chat history.
                    </p>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            x-on:click="cancelNewChat()"
                            class="rounded-xl border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            x-on:click="startNewChat()"
                            class="rounded-xl bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-700"
                        >
                            New Chat
                        </button>
                    </div>
                </div>
            </div>

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

            {{-- Redesigned Clean Header --}}
            <header class="flex items-center justify-between border-b border-neutral-200/80 bg-white/95 px-3.5 py-2.5 shadow-2xs backdrop-blur-xs">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-primary-800 text-white shadow-xs">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.154-.672c.07-.866.27-1.77.585-2.556C3.593 16.32 3 14.28 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 id="hims-assistant-title" class="text-xs sm:text-sm font-semibold tracking-tight text-neutral-900 truncate">
                            HIMS AI Assistant
                        </h3>
                        <p class="text-[10px] sm:text-[11px] font-medium text-neutral-500 truncate" x-text="conversationTitle ? conversationTitle : 'Inventory Intelligence Assistant'">
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    {{-- New Chat Button --}}
                    <button
                        type="button"
                        x-on:click="requestNewChat()"
                        class="inline-flex items-center gap-1 rounded-lg border border-neutral-200/90 bg-neutral-50 px-2 py-1 text-[11px] font-medium text-neutral-700 transition-colors hover:border-primary-300 hover:bg-primary-50/60 hover:text-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        title="Start a new chat (+ New Chat)"
                        aria-label="Start new chat"
                    >
                        <svg class="h-3.5 w-3.5 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>New Chat</span>
                    </button>

                    {{-- History Button --}}
                    <button
                        type="button"
                        x-on:click="toggleHistory()"
                        class="inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-[11px] font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        x-bind:class="viewMode === 'history' ? 'border-primary-300 bg-primary-50 text-primary-800 font-semibold' : 'border-neutral-200/90 bg-neutral-50 text-neutral-700 hover:border-neutral-300 hover:bg-neutral-100'"
                        title="View conversation history"
                        aria-label="Toggle chat history"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>History</span>
                    </button>

                    {{-- Close Button (only hides/minimizes the panel; NEVER clears or deletes chat) --}}
                    <button
                        type="button"
                        x-on:click="close()"
                        title="Close assistant (conversation remains saved)"
                        class="rounded-lg p-1 text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700 focus:outline-none"
                        aria-label="Close assistant"
                    >
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </header>

            {{-- History Drawer View --}}
            <div
                x-show="viewMode === 'history'"
                x-transition
                class="flex flex-1 flex-col overflow-y-auto bg-neutral-50/50 p-3 text-xs"
            >
                <div class="mb-2.5 flex items-center justify-between px-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Chat History</span>
                    <button
                        type="button"
                        x-on:click="viewMode = 'chat'"
                        class="text-xs font-semibold text-primary-600 hover:text-primary-800"
                    >
                        &larr; Back to chat
                    </button>
                </div>

                {{-- Loading indicator --}}
                <div x-show="isHistoryLoading" class="flex items-center justify-center py-12 text-neutral-400">
                    <svg class="h-5 w-5 animate-spin text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>

                {{-- Empty History State --}}
                <div x-show="!isHistoryLoading && conversationsList.length === 0" class="py-12 text-center text-neutral-500">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-neutral-700">No previous conversations</p>
                    <p class="mt-0.5 text-[11px] text-neutral-400">Your chat conversations will be saved and listed here.</p>
                </div>

                {{-- Grouped Conversations Loop --}}
                <div x-show="!isHistoryLoading && conversationsList.length > 0" class="space-y-3.5">
                    <template x-for="(groupItems, groupName) in groupedConversations" x-bind:key="groupName">
                        <div class="space-y-1">
                            <p class="px-1 text-[10px] font-bold uppercase tracking-wider text-neutral-400" x-text="groupName"></p>
                            <div class="space-y-1">
                                <template x-for="conv in groupItems" x-bind:key="conv.id">
                                    <button
                                        type="button"
                                        x-on:click="selectConversation(conv.id)"
                                        class="group flex w-full items-center justify-between rounded-xl border p-2.5 text-left transition"
                                        x-bind:class="conversationId === conv.id ? 'border-primary-300 bg-primary-50/70 text-primary-900 shadow-2xs' : 'border-neutral-200/80 bg-white hover:border-neutral-300 hover:bg-neutral-50 text-neutral-800'"
                                    >
                                        <div class="min-w-0 flex-1 pr-2">
                                            <p class="truncate text-xs font-semibold text-neutral-900" x-text="conv.title"></p>
                                            <p class="text-[10px] text-neutral-400" x-text="conv.time_ago + ' &bull; ' + conv.message_count + ' message' + (conv.message_count === 1 ? '' : 's')"></p>
                                        </div>
                                        <svg class="h-4 w-4 shrink-0 text-neutral-400 transition-transform group-hover:translate-x-0.5 group-hover:text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Message Area --}}
            <div
                x-ref="messagesContainer"
                x-show="viewMode === 'chat'"
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
                x-show="viewMode === 'chat' && errorMessage"
                x-transition
                class="flex items-center justify-between border-t border-danger-200 bg-danger-50 px-3 py-1.5 text-xs text-danger-700"
            >
                <span class="truncate font-medium" x-text="errorMessage"></span>
                <button type="button" x-on:click="errorMessage = ''" class="ml-2 font-bold text-danger-500 hover:text-danger-800" aria-label="Dismiss error">&times;</button>
            </div>

            {{-- Selected File Preview Strip --}}
            <div
                x-show="viewMode === 'chat' && selectedFile"
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
                x-show="viewMode === 'chat'"
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
