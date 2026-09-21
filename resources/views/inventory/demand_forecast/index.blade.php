<x-app-layout :full-width="true">
    <x-slot:title>Demand Forecasting</x-slot:title>

    @php
        $dashboardForecastConfig = [
            'initialForecast' => $aiForecast,
            'endpoint' => route('inventory.demand-forecast.refresh'),
        ];
    @endphp

    <div x-data="demandForecastDashboard({{ Js::from($dashboardForecastConfig) }})"
         class="space-y-3 sm:space-y-3.5">

        {{-- PAGE HEADER WITH ACTIONS --}}
        <x-ui.page-header
            title="AI-Based Stock Demand Forecasting"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Procurement' => route('inventory.purchases'), 'Demand Forecast' => null]"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" :href="route('inventory.purchases')" icon="arrow-left">Back to Procurement</x-ui.button>
                @can(\App\Enums\Permission::GenerateForecasts->value)
                    <form method="POST" action="{{ route('inventory.demand-forecast.refresh') }}" class="inline">
                        @csrf
                        <input type="hidden" name="analysis_days" value="{{ $analysisDays }}">
                        <input type="hidden" name="forecast_days" value="{{ $forecastDays }}">
                        <input type="hidden" name="return_to" value="forecast">
                        <x-ui.button type="submit" size="sm" icon="arrow-path" data-loading-text="Generating forecast...">
                            {{ $aiForecast ? 'Refresh AI Forecast' : 'Generate AI Forecast' }}
                        </x-ui.button>
                    </form>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        {{-- SERVER-SIDE VALIDATION ERROR ALERT --}}
        @if ($errors->any())
            <x-ui.alert variant="danger" title="This plan was not saved">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        {{-- TIER 1: UNIFIED FORECAST SCOPE & CONTROL TOOLBAR --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-3 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-2.5 dark:border-neutral-800/80">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Forecast scope</h2>
                    @if ($aiForecast)
                        <span class="text-[11px] text-neutral-500 dark:text-neutral-400">
                            {{ $aiForecast['source_label'] }} · {{ $aiForecast['forecast_period'] }} · Generated {{ \Illuminate\Support\Carbon::parse($aiForecast['generated_at'])->format('M d, Y g:i A') }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                            x-on:click="filtersOpen = !filtersOpen"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/60 transition">
                        <x-ui.icon name="funnel" class="h-3.5 w-3.5 text-neutral-500" />
                        <span x-text="filtersOpen ? 'Hide parameters' : 'Parameters & window'"></span>
                        <span x-show="activeFilterCount() > 0"
                              x-cloak
                              class="rounded-full bg-primary-100 px-1.5 py-0.2 text-[10px] font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                              x-text="activeFilterCount()"></span>
                    </button>
                    <button type="button"
                            x-show="activeFilterCount() > 0 || selectedItemId"
                            x-cloak
                            x-on:click="clearFilters()"
                            class="text-xs font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 transition">
                        Clear filters
                    </button>
                </div>
            </div>

            {{-- INLINE CONTROLS BAR --}}
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-center">
                {{-- Search Item / SKU --}}
                <div class="lg:col-span-4 relative">
                    <label for="forecast-search" class="sr-only">Item or SKU</label>
                    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-neutral-400" />
                    <input id="forecast-search"
                           name="search"
                           type="search"
                           x-model.debounce.200ms="search"
                           placeholder="Filter item name, SKU, category..."
                           class="block w-full h-10 rounded-lg border-neutral-300 pl-9 pr-3 text-sm shadow-2xs placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100" />
                </div>

                {{-- Focus Item Dropdown --}}
                <div class="lg:col-span-5">
                    <label for="dashboard-forecast-item" class="sr-only">Select Item</label>
                    <select id="dashboard-forecast-item"
                            x-model="selectedItemId"
                            class="block w-full h-10 rounded-lg border-neutral-300 px-3 py-2 text-sm font-medium shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                        <option value="">All Inventory Items (Overall Hospital Demand)</option>
                        <template x-for="item in allItems()" :key="item.item_id">
                            <option :value="String(item.item_id)" x-text="`${item.item_name} (${item.sku}) · ${item.risk_level.toUpperCase()} risk`"></option>
                        </template>
                    </select>
                </div>

                {{-- Forecast Horizon --}}
                <div class="lg:col-span-3">
                    <label for="dashboard-forecast-period" class="sr-only">Forecast Horizon</label>
                    <select id="dashboard-forecast-period"
                            name="forecast_days"
                            x-model="forecastDays"
                            x-on:change="updateForecastPeriod($event.target.value)"
                            @cannot(\App\Enums\Permission::GenerateForecasts->value) disabled @endcannot
                            class="block w-full h-10 rounded-lg border-neutral-300 px-3 py-2 text-sm font-medium shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 disabled:opacity-60">
                        @foreach ([7 => 'Next 7 days', 14 => 'Next 14 days', 30 => 'Next 30 days', 60 => 'Next 60 days', 90 => 'Next 90 days'] as $days => $label)
                            <option value="{{ $days }}" @selected($forecastDays === $days)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- EXPANDABLE ADVANCED FILTER DRAWER (HISTORICAL WINDOW, CATEGORY, RISK) --}}
            <div x-show="filtersOpen"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-3.5 pt-3.5 border-t border-neutral-200 dark:border-neutral-800">
                <form method="GET" action="{{ route('inventory.demand-forecast') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 items-end">
                    <div>
                        <label for="analysis-days" class="block text-xs sm:text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">Historical window</label>
                        <select id="analysis-days" name="analysis_days" class="block w-full h-10 rounded-lg border-neutral-300 text-sm px-3 py-2 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            @foreach ([30, 60, 90, 180, 365] as $days)
                                <option value="{{ $days }}" @selected($analysisDays === $days)>{{ $days }} days</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="forecast-category" class="block text-xs sm:text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">Category</label>
                        <select id="forecast-category" name="category_id" x-model="category" class="block w-full h-10 rounded-lg border-neutral-300 text-sm px-3 py-2 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            <option value="">All categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($aiFilters['categoryId'] === $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="forecast-risk" class="block text-xs sm:text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">AI risk level</label>
                        <select id="forecast-risk" name="risk" x-model="risk" class="block w-full h-10 rounded-lg border-neutral-300 text-sm px-3 py-2 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            <option value="">All risk levels</option>
                            <option value="high" @selected($aiFilters['risk'] === 'high')>High risk</option>
                            <option value="medium" @selected($aiFilters['risk'] === 'medium')>Medium risk</option>
                            <option value="low" @selected($aiFilters['risk'] === 'low')>Low risk</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-ui.button type="submit" size="md" class="flex-1">Apply Window</x-ui.button>
                        <x-ui.button variant="secondary" size="md" :href="route('inventory.demand-forecast')">Reset</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LEVEL 1: OPERATIONAL KPI CARDS --}}
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5 lg:gap-4">
            {{-- 1. Current Stock --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Current Stock</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 ring-1 ring-neutral-200/80 dark:ring-neutral-700">
                        <x-ui.icon name="archive-box" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white" x-text="formatNumber(summaryCurrentStock())">
                        {{ number_format($forecasts->sum('current_stock')) }}
                    </span>
                    <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">units</span>
                </div>
                <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">On hand in warehouse</span>
                    <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $forecasts->count() }} items
                    </span>
                </div>
            </div>

            {{-- 2. Predicted Demand --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300">Predicted Demand</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/80 dark:text-violet-300 ring-1 ring-violet-200 dark:ring-violet-800/50">
                        <x-ui.icon name="sparkles" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-violet-700 dark:text-violet-300" x-text="formatNumber(summaryPredictedDemand())">
                        {{ number_format(collect($aiForecast['items'] ?? [])->sum('predicted_demand')) }}
                    </span>
                    <span class="text-sm sm:text-base font-bold text-violet-600/80 dark:text-violet-400/80">units</span>
                </div>
                <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">
                        ~<span class="font-bold text-violet-700 dark:text-violet-300" x-text="formatNumber(summaryPredictedDailyDemand(), 1)"></span> units / day
                    </span>
                    <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-400" x-text="`${forecastDays}d horizon`"></span>
                </div>
            </div>

            {{-- 3. Recommended Reorder --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Recommended Order</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300 ring-1 ring-primary-200 dark:ring-primary-800/50">
                        <x-ui.icon name="shopping-cart" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-primary-700 dark:text-primary-300" x-text="formatNumber(summaryRecommendedReorder())">
                        {{ number_format(collect($aiForecast['items'] ?? [])->sum('recommended_reorder_quantity')) }}
                    </span>
                    <span class="text-sm sm:text-base font-bold text-primary-600/80 dark:text-primary-400/80">units</span>
                </div>
                <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">Immediate replenishment need</span>
                    <span class="inline-flex shrink-0 items-center gap-1 text-xs font-bold text-primary-700 dark:text-primary-300" x-text="`${itemsRequiringReorderCount()} need restock`"></span>
                </div>
            </div>

            {{-- 4. Stockout Risk --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Stockout Risk</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800/50">
                        <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums"
                          :class="highRiskCount() > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                          x-text="highRiskCount()">
                        {{ $aiForecast['summary']['high_risk_items'] ?? 0 }}
                    </span>
                    <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">critical</span>
                </div>
                <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">Immediate action required</span>
                    <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-400" x-text="moderateRiskCount() > 0 ? `${moderateRiskCount()} moderate` : '0 moderate'"></span>
                </div>
            </div>

            {{-- 5. Model Confidence --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150 col-span-1 sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Model Confidence</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50">
                        <x-ui.icon name="cpu-chip" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-emerald-600 dark:text-emerald-400" x-text="confidenceLabel()">
                        High
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-600/30">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Active
                    </span>
                </div>
                <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate" x-text="forecast?.source_label || 'HIMS Statistical Model'">
                        {{ $aiForecast['source_label'] ?? 'HIMS Statistical Model' }}
                    </span>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">95% CI</span>
                </div>
            </div>
        </div>

        {{-- VISUAL CENTER: SPLIT OPERATIONAL DASHBOARD (CHART + REORDER ACTION CENTER) --}}
        <div class="grid grid-cols-1 gap-3.5 lg:grid-cols-12">
            {{-- LEFT: INTERACTIVE ACTUAL VS FORECAST CHART (7 or 8 COLS) --}}
            <section class="min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs lg:col-span-7 xl:col-span-8 dark:border-neutral-800 dark:bg-neutral-900"
                     aria-labelledby="forecast-chart-heading"
                     x-bind:aria-busy="loading">
                <header class="flex flex-col gap-2 border-b border-neutral-100 px-3.5 py-2.5 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800/80">
                    <div class="flex items-center gap-2 min-w-0">
                        <h3 id="forecast-chart-heading" class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
                            Demand vs Forecast
                        </h3>
                        <template x-if="selectedItem()">
                            <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-700/50">
                                <span class="truncate max-w-[180px]" x-text="selectedItem().item_name"></span>
                                <button type="button" x-on:click="selectedItemId = ''" class="hover:text-primary-900 dark:hover:text-primary-200" aria-label="Clear selected item">
                                    <x-ui.icon name="x-mark" class="h-3 w-3" />
                                </button>
                            </span>
                        </template>
                    </div>

                    {{-- Series Toggles --}}
                    <div class="flex flex-wrap items-center gap-2 text-[11px] text-neutral-600 sm:gap-3 dark:text-neutral-400">
                        <button type="button"
                                class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                x-bind:class="showActual ? 'text-neutral-800 dark:text-neutral-200 font-semibold' : 'text-neutral-400 line-through dark:text-neutral-600'"
                                x-bind:aria-pressed="showActual"
                                x-on:click="toggleSeries('actual')">
                            <span class="h-1 w-3.5 rounded-full bg-primary-600"></span>
                            <span>Historical demand</span>
                        </button>
                        <button type="button"
                                class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                x-bind:class="showForecast ? 'text-neutral-800 dark:text-neutral-200 font-semibold' : 'text-neutral-400 line-through dark:text-neutral-600'"
                                x-bind:aria-pressed="showForecast"
                                x-on:click="toggleSeries('forecast')">
                            <span class="h-0.5 w-3.5 border-t-2 border-dashed border-violet-600"></span>
                            <span>AI forecast</span>
                        </button>
                    </div>
                </header>

                <div class="p-3">
                    <div x-show="hasChartData()" class="min-w-0 select-none">
                        <div class="relative min-w-0"
                             x-ref="chartContainer"
                             x-on:pointerenter="isHovering = true"
                             x-on:pointerleave="onChartPointerLeave($event)">

                            {{-- Updating Spinner Overlay --}}
                            <div x-show="loading"
                                 x-cloak
                                 class="pointer-events-none absolute right-2 top-2 z-20 inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-white/95 px-2 py-1 text-[11px] font-medium text-primary-700 shadow-sm backdrop-blur-xs dark:border-primary-900/60 dark:bg-neutral-900/95 dark:text-primary-300">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600 dark:border-primary-800 dark:border-t-primary-400" aria-hidden="true"></span>
                                Updating forecast
                            </div>

                            {{-- Y-Axis Units/Day Label & Ticks --}}
                            <div class="pointer-events-none absolute left-0.5 top-0 z-10 select-none">
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Units/day</span>
                            </div>
                            <template x-for="(tick, index) in displayChartTicks()" :key="`y-label-${index}`">
                                <span class="pointer-events-none absolute left-0.5 z-10 -translate-y-1/2 select-none text-[10px] font-medium tabular-nums text-neutral-400 dark:text-neutral-500"
                                      :style="`top: ${tick.top}%`"
                                      x-text="formatNumber(tick.value, 1)"></span>
                            </template>

                            {{-- Interactive Tooltip Inspector Popover --}}
                            <div data-chart-inspector
                                 x-ref="chartInspector"
                                 x-show="activePoint && (isHovering || isDragging || isFocused)"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                 class="pointer-events-none absolute z-30 min-w-[200px] max-w-[260px] rounded-lg border border-neutral-200/90 bg-white/95 p-2.5 shadow-lg backdrop-blur-xs transition-[left,top] duration-75 ease-out dark:border-neutral-700/80 dark:bg-neutral-900/95 dark:shadow-2xl"
                                 :style="tooltipStyle()">
                                <p class="sr-only">Hover, drag, or use the arrow keys to inspect either line.</p>

                                {{-- Tooltip Header --}}
                                <div class="mb-1.5 flex items-center justify-between gap-2 border-b border-neutral-100 pb-1.5 dark:border-neutral-800">
                                    <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-100" x-text="activePoint?.formattedDate"></span>
                                    <template x-if="activePoint?.isFuture">
                                        <span class="inline-flex items-center rounded border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-700 dark:border-violet-800/80 dark:bg-violet-950/60 dark:text-violet-300">
                                            AI Forecast
                                        </span>
                                    </template>
                                    <template x-if="!activePoint?.isFuture">
                                        <span class="inline-flex items-center rounded border border-primary-200 bg-primary-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-primary-700 dark:border-primary-800/80 dark:bg-primary-950/60 dark:text-primary-300">
                                            Historical
                                        </span>
                                    </template>
                                </div>

                                {{-- Tooltip Content: Forecast --}}
                                <template x-if="activePoint?.isFuture">
                                    <div class="space-y-1 text-xs">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="h-2 w-2 rounded-full bg-violet-600"></span>
                                                <span class="font-medium text-neutral-700 dark:text-neutral-300">Predicted Rate:</span>
                                            </div>
                                            <div class="text-right tabular-nums">
                                                <span class="font-bold text-violet-700 dark:text-violet-400" x-text="formatNumber(activePoint?.forecastPoint?.value ?? activePoint?.value, 1)"></span>
                                                <span class="text-[10px] text-neutral-400">units/d</span>
                                            </div>
                                        </div>
                                        <div class="flex justify-between text-[10px] text-neutral-400">
                                            <span>Period total:</span>
                                            <span class="font-medium text-neutral-700 dark:text-neutral-300 tabular-nums" x-text="`${formatNumber(activePoint?.forecastPoint?.quantity ?? activePoint?.quantity)} units`"></span>
                                        </div>
                                        <template x-if="activePoint?.forecastPoint?.confidence">
                                            <div class="flex justify-between text-[10px] text-neutral-400">
                                                <span>Confidence:</span>
                                                <span class="font-medium capitalize text-neutral-600 dark:text-neutral-300" x-text="activePoint.forecastPoint.confidence"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Tooltip Content: Historical --}}
                                <template x-if="!activePoint?.isFuture">
                                    <div class="space-y-1 text-xs">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                                                <span class="font-medium text-neutral-700 dark:text-neutral-300">Demand Rate:</span>
                                            </div>
                                            <div class="text-right tabular-nums">
                                                <span class="font-bold text-primary-700 dark:text-primary-400" x-text="formatNumber(activePoint?.value, 1)"></span>
                                                <span class="text-[10px] text-neutral-400">units/d</span>
                                            </div>
                                        </div>
                                        <div class="text-right text-[10px] text-neutral-400 tabular-nums">
                                            <span x-text="`${formatNumber(activePoint?.quantity)} units across ${activePoint?.days || 1} d`"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- SVG Chart Canvas --}}
                            <svg x-ref="chartSvg"
                                 class="h-48 sm:h-56 lg:h-64 w-full cursor-crosshair select-none touch-none"
                                 viewBox="0 0 760 240"
                                 role="img"
                                 aria-label="Demand vs Forecast chart"
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
                                 x-on:keydown.arrow-right.prevent="stepPoint(1)">
                                <defs>
                                    <linearGradient id="forecast-historical-area" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#1c75f5" stop-opacity="0.22"></stop>
                                        <stop offset="100%" stop-color="#1c75f5" stop-opacity="0.01"></stop>
                                    </linearGradient>
                                    <linearGradient id="forecast-projected-area" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.18"></stop>
                                        <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.01"></stop>
                                    </linearGradient>
                                </defs>

                                {{-- Static Horizontal Gridlines --}}
                                <line data-chart-grid-line x1="50" y1="40" x2="725" y2="40" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                <line data-chart-grid-line x1="50" y1="81.25" x2="725" y2="81.25" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                <line data-chart-grid-line x1="50" y1="122.5" x2="725" y2="122.5" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                <line data-chart-grid-line x1="50" y1="163.75" x2="725" y2="163.75" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                                <line data-chart-grid-line x1="50" y1="205" x2="725" y2="205" class="stroke-slate-200 dark:stroke-neutral-700" stroke-width="1" vector-effect="non-scaling-stroke"></line>

                                {{-- Forecast Horizon Shading --}}
                                <rect :x="displayTransitionX()"
                                      y="20"
                                      :width="Math.max(0, 725 - displayTransitionX())"
                                      height="185"
                                      rx="4"
                                      fill="#8b5cf6"
                                      fill-opacity="0.035"></rect>

                                {{-- Transition Boundary Line --}}
                                <line :x1="displayTransitionX()" y1="20" :x2="displayTransitionX()" y2="205"
                                      class="stroke-slate-300 dark:stroke-neutral-600"
                                      stroke-width="1.5"
                                      stroke-dasharray="2 3"
                                      vector-effect="non-scaling-stroke"></line>

                                {{-- Gradient Area Fills --}}
                                <path x-show="showActual" :d="displayHistoricalAreaPath()" fill="url(#forecast-historical-area)"></path>
                                <path x-show="showForecast" :d="displayForecastAreaPath()" fill="url(#forecast-projected-area)"></path>

                                {{-- Solid Blue Recorded Demand Line --}}
                                <path x-show="showActual" :d="seriesPath(displayHistoricalRenderPoints())" fill="none" stroke="#1c75f5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>

                                {{-- Dashed Violet AI Forecast Line --}}
                                <path x-show="showForecast" :d="seriesPath(chartLinePoints(displayForecastRenderPoints(), 725))" fill="none" stroke="#8b5cf6" stroke-width="2.5" stroke-dasharray="5 3.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>

                                {{-- Historical Point Markers --}}
                                <template x-for="(point, index) in displayHistoricalPoints()" :key="`hist-${index}`">
                                    <circle x-show="showActual" :cx="point.x" :cy="point.y" r="2.5" fill="#1c75f5" fill-opacity="0.7" vector-effect="non-scaling-stroke"></circle>
                                </template>

                                {{-- Forecast Point Markers --}}
                                <template x-for="(point, index) in displayForecastPoints()" :key="`fore-${index}`">
                                    <circle x-show="showForecast" :cx="point.x" :cy="point.y" r="3" class="fill-white dark:fill-neutral-900" stroke="#8b5cf6" stroke-width="2" vector-effect="non-scaling-stroke"></circle>
                                </template>

                                {{-- Interactive Scrubber Line --}}
                                <template x-if="activePoint && (isHovering || isDragging || isFocused)">
                                    <g>
                                        <line :x1="activePoint.x" y1="20" :x2="activePoint.x" y2="205"
                                              class="stroke-neutral-700 dark:stroke-neutral-300"
                                              stroke-width="1.5"
                                              stroke-dasharray="3 3"
                                              vector-effect="non-scaling-stroke"></line>
                                        <template x-if="activePoint.isFuture && showForecast && activePoint.forecastPoint">
                                            <g>
                                                <circle :cx="activePoint.x" :cy="activePoint.forecastPoint.y" r="5" stroke="#8b5cf6" stroke-width="2.5" class="fill-white dark:fill-neutral-900" vector-effect="non-scaling-stroke"></circle>
                                            </g>
                                        </template>
                                        <template x-if="!activePoint.isFuture && showActual">
                                            <g>
                                                <circle :cx="activePoint.x" :cy="activePoint.y" r="5" stroke="#1c75f5" stroke-width="2.5" class="fill-white dark:fill-neutral-900" vector-effect="non-scaling-stroke"></circle>
                                            </g>
                                        </template>
                                    </g>
                                </template>
                            </svg>

                            {{-- X-Axis Date Range Labels --}}
                            <div class="mt-2.5 grid grid-cols-[1fr_auto_1fr] items-center gap-2 border-t border-neutral-100 pl-11 pr-2 pt-2 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="font-medium text-neutral-700 dark:text-neutral-300 tabular-nums truncate" x-text="chartStartLabel(historicalSeries())"></span>
                                    <span class="hidden sm:inline text-neutral-400 text-[11px]">· History start</span>
                                </div>
                                <div class="flex items-center gap-1.5 px-2 font-semibold text-neutral-800 dark:text-neutral-200 text-xs">
                                    <span class="h-1.5 w-1.5 rounded-full bg-neutral-500"></span>
                                    <span>Forecast starts (<span class="tabular-nums text-primary-700 dark:text-primary-400" x-text="chartTransitionLabel()"></span>)</span>
                                </div>
                                <div class="flex items-center justify-end gap-1.5 text-right truncate">
                                    <span class="hidden sm:inline text-neutral-400 text-[11px]">Horizon end ·</span>
                                    <span class="font-semibold text-violet-700 dark:text-violet-400 tabular-nums truncate" x-text="chartEndLabel(forecastSeries())"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="!hasChartData()" class="py-10 text-center text-xs text-neutral-500 dark:text-neutral-400">
                        Historical consumption and forecast points are calculating or unavailable for this item.
                    </div>
                </div>
            </section>

            {{-- RIGHT: AI DEMAND INTELLIGENCE & CLINICAL TRIAGE (4 or 5 COLS) --}}
            <section class="flex flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 lg:col-span-5 xl:col-span-4 min-w-0">
                <div>
                    {{-- Header --}}
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-2.5 dark:border-neutral-800/80">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/80 dark:text-violet-300 ring-1 ring-violet-200 dark:ring-violet-800/50">
                                <x-ui.icon name="sparkles" class="h-4 w-4" />
                            </span>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200 truncate">
                                Clinical Demand Intelligence
                            </h3>
                        </div>
                        <span class="shrink-0 rounded-full bg-violet-50 dark:bg-violet-950/60 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:text-violet-300 ring-1 ring-violet-600/20"
                              x-text="`${forecastDays}d Projection`">
                            {{ $forecastDays }}d Projection
                        </span>
                    </div>

                    {{-- Dynamic Content Area --}}
                    <div class="mt-3 space-y-3">
                        {{-- Focus Badge (when item selected) --}}
                        <template x-if="selectedItem()">
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-primary-50/80 px-2.5 py-1.5 dark:bg-primary-950/50 ring-1 ring-primary-200 dark:ring-primary-800/50">
                                <div class="min-w-0 flex items-center gap-1.5 text-xs">
                                    <span class="font-bold text-primary-900 dark:text-primary-100 truncate" x-text="selectedItem().item_name"></span>
                                    <span class="text-primary-600 dark:text-primary-400 truncate" x-text="`(${selectedItem().sku})`"></span>
                                </div>
                                <button type="button"
                                        x-on:click="selectItem('')"
                                        class="shrink-0 text-xs font-bold text-primary-700 hover:text-primary-900 dark:text-primary-300">
                                    &times; Reset
                                </button>
                            </div>
                        </template>

                        {{-- Clinical Narrative / Insight --}}
                        <p class="text-xs sm:text-sm leading-relaxed text-neutral-700 dark:text-neutral-300 font-medium min-h-[3rem]"
                           x-text="insightText()">
                            @if ($aiForecast && count($aiForecast['items'] ?? []) > 0)
                                @php
                                    $highRiskCount = collect($aiForecast['items'])->where('risk_level', 'high')->count();
                                    $firstItem = collect($aiForecast['items'])->firstWhere('risk_level', 'high') ?? $aiForecast['items'][0];
                                @endphp
                                @if ($highRiskCount > 0)
                                    {{ $highRiskCount }} {{ $highRiskCount === 1 ? 'item is' : 'items are' }} at high stockout risk. {{ $firstItem['item_name'] }}: {{ $firstItem['explanation'] }}
                                @else
                                    {{ $firstItem['item_name'] }}: {{ $firstItem['explanation'] }}
                                @endif
                            @else
                                Clinical demand forecast and consumption patterns across inventory.
                            @endif
                        </p>

                        {{-- Interactive Triage Filters Bar (Critical / Moderate / Low Risk) --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Quick Risk Triage</span>
                                <span class="text-[11px] text-neutral-400 dark:text-neutral-500">Click to filter table</span>
                            </div>
                            <div class="grid grid-cols-3 gap-1.5">
                                <button type="button"
                                        x-on:click="risk = (risk === 'high' ? '' : 'high'); activeTab = 'ai';"
                                        class="flex flex-col items-center justify-center rounded-lg border p-2 transition text-center"
                                        :class="risk === 'high' ? 'border-rose-500 bg-rose-50 dark:border-rose-600 dark:bg-rose-950/80 ring-1 ring-rose-500' : 'border-neutral-200 bg-neutral-50/70 hover:bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:bg-neutral-800'">
                                    <span class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">Critical</span>
                                    <span class="text-base font-black tabular-nums text-rose-700 dark:text-rose-300" x-text="highRiskCount()"></span>
                                </button>
                                <button type="button"
                                        x-on:click="risk = (risk === 'medium' ? '' : 'medium'); activeTab = 'ai';"
                                        class="flex flex-col items-center justify-center rounded-lg border p-2 transition text-center"
                                        :class="risk === 'medium' ? 'border-amber-500 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/80 ring-1 ring-amber-500' : 'border-neutral-200 bg-neutral-50/70 hover:bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:bg-neutral-800'">
                                    <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">Moderate</span>
                                    <span class="text-base font-black tabular-nums text-amber-700 dark:text-amber-300" x-text="moderateRiskCount()"></span>
                                </button>
                                <button type="button"
                                        x-on:click="risk = (risk === 'low' ? '' : 'low'); activeTab = 'ai';"
                                        class="flex flex-col items-center justify-center rounded-lg border p-2 transition text-center"
                                        :class="risk === 'low' ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-950/80 ring-1 ring-emerald-500' : 'border-neutral-200 bg-neutral-50/70 hover:bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:bg-neutral-800'">
                                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Low Risk</span>
                                    <span class="text-base font-black tabular-nums text-emerald-700 dark:text-emerald-300" x-text="lowRiskCount()"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card Actions Footer --}}
                <div class="mt-3.5 pt-3 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between gap-2">
                    <button type="button"
                            x-on:click="openCurrentInsightModal()"
                            class="inline-flex items-center gap-1 text-xs font-bold text-violet-700 hover:text-violet-900 dark:text-violet-400 dark:hover:text-violet-300 transition">
                        <span>Clinical diagnosis details</span>
                        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5" />
                    </button>

                    <button type="button"
                            x-on:click="activeTab = 'statistical'"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-200 transition">
                        <span>Statistical plans &rarr;</span>
                    </button>
                </div>
            </section>
        </div>

        {{-- LEVEL 2 & 3: COMPACT TABBED DETAILED DATA SECTION --}}
        <div class="rounded-xl border border-neutral-200 bg-white shadow-xs overflow-hidden dark:border-neutral-800 dark:bg-neutral-900">
            {{-- TAB NAVIGATION BAR --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-neutral-200 px-4 py-2.5 bg-neutral-50/75 dark:border-neutral-800 dark:bg-neutral-800/50 gap-2">
                <div class="flex items-center gap-2 overflow-x-auto">
                    <button type="button"
                            x-on:click="activeTab = 'ai'"
                            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition"
                            :class="activeTab === 'ai' ? 'bg-white text-primary-700 shadow-2xs dark:bg-neutral-800 dark:text-primary-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-200'">
                        <x-ui.icon name="sparkles" class="h-4 w-4" />
                        <span>AI Forecast &amp; Risk</span>
                        <span class="rounded-full bg-neutral-200/80 px-2 py-0.5 text-xs font-bold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300" x-text="filteredItems().length"></span>
                    </button>
                    <button type="button"
                            x-on:click="activeTab = 'statistical'"
                            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition"
                            :class="activeTab === 'statistical' ? 'bg-white text-primary-700 shadow-2xs dark:bg-neutral-800 dark:text-primary-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-200'">
                        <x-ui.icon name="chart-bar" class="h-4 w-4" />
                        <span>Statistical Forecast by Item</span>
                        <span class="rounded-full bg-neutral-200/80 px-2 py-0.5 text-xs font-bold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300">{{ $forecasts->count() }}</span>
                    </button>
                    <button type="button"
                            x-on:click="activeTab = 'plans'"
                            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition"
                            :class="activeTab === 'plans' ? 'bg-white text-primary-700 shadow-2xs dark:bg-neutral-800 dark:text-primary-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-200'">
                        <x-ui.icon name="document-text" class="h-4 w-4" />
                        <span>Saved Plans</span>
                        <span class="rounded-full bg-neutral-200/80 px-2 py-0.5 text-xs font-bold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300">{{ $plans->count() }}</span>
                    </button>
                </div>

                <p class="text-xs text-neutral-500 dark:text-neutral-400 hidden sm:block">Click any row to focus on the chart above</p>
            </div>

            {{-- TAB 1: AI FORECAST & RISK ANALYSIS TABLE --}}
            <div x-show="activeTab === 'ai'" class="overflow-x-auto max-h-96 overflow-y-auto">
                <x-ui.table>
                    <x-ui.table.head>
                        <x-ui.table.th>Item</x-ui.table.th>
                        <x-ui.table.th>Risk &amp; Priority</x-ui.table.th>
                        <x-ui.table.th numeric>On Hand</x-ui.table.th>
                        <x-ui.table.th numeric>Historical</x-ui.table.th>
                        <x-ui.table.th numeric>Predicted</x-ui.table.th>
                        <x-ui.table.th numeric>Reorder Qty</x-ui.table.th>
                        <x-ui.table.th>Trend / Confidence</x-ui.table.th>
                        <x-ui.table.th>AI Narrative</x-ui.table.th>
                        <x-ui.table.th align="right">Focus</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($aiItems as $item)
                            <x-ui.table.row x-show="itemMatchesFilter({{ Js::from($item) }})">
                                <x-ui.table.td>
                                    <span class="font-semibold text-sm text-neutral-900 dark:text-neutral-100">{{ $item['item_name'] }}</span>
                                    <span class="block text-xs text-neutral-500">{{ $item['sku'] }}@if(!empty($item['category'])) · {{ $item['category'] }}@endif</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <x-ui.badge :variant="match($item['risk_level']) { 'high' => 'danger', 'medium' => 'warning', default => 'success' }" dot>
                                        {{ ucfirst($item['risk_level']) }}
                                    </x-ui.badge>
                                    <span class="mt-0.5 block text-xs text-neutral-500">{{ ucfirst($item['reorder_priority']) }} priority</span>
                                </x-ui.table.td>
                                <x-ui.table.td numeric><span class="text-sm">{{ number_format($item['current_stock']) }}</span></x-ui.table.td>
                                <x-ui.table.td numeric muted><span class="text-sm">{{ number_format($item['historical_consumption']) }}</span></x-ui.table.td>
                                <x-ui.table.td numeric><span class="text-sm">{{ number_format($item['predicted_demand']) }}</span></x-ui.table.td>
                                <x-ui.table.td numeric><span class="font-bold text-sm text-primary-700 dark:text-primary-400">{{ number_format($item['recommended_reorder_quantity']) }}</span></x-ui.table.td>
                                <x-ui.table.td>
                                    <span class="text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ ucfirst($item['demand_trend']) }}</span>
                                    <span class="block text-xs text-neutral-500">{{ ucfirst($item['confidence']) }} confidence</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <div class="max-w-xs">
                                        <p class="truncate text-sm text-neutral-700 dark:text-neutral-300" title="{{ $item['explanation'] }}">{{ $item['explanation'] }}</p>
                                        @if ($item['limited_data'])
                                            <span class="mt-0.5 block text-xs font-medium text-warning-700 dark:text-warning-400">Limited movement history</span>
                                        @endif
                                        <button type="button"
                                                x-on:click="openAnalysisModal({{ Js::from($item) }})"
                                                class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                            View analysis
                                        </button>
                                    </div>
                                </x-ui.table.td>
                                <x-ui.table.td align="right">
                                    <button type="button"
                                            x-on:click="selectItem({{ $item['item_id'] }})"
                                            class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200 transition"
                                            title="Focus item on chart">
                                        <x-ui.icon name="chart-bar" class="h-4 w-4" />
                                    </button>
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty :colspan="9" icon="magnifying-glass" title="No matching forecast items" message="Adjust or clear the filters to see more results." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>

            {{-- TAB 2: STATISTICAL FORECAST BY ITEM TABLE --}}
            <div x-show="activeTab === 'statistical'" class="overflow-x-auto max-h-96 overflow-y-auto">
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
                            <x-ui.table.row x-show="statMatchesFilter('{{ addslashes($item->name) }}', '{{ addslashes($item->sku) }}')">
                                <x-ui.table.td>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $item->name }}</span>
                                    <span class="block text-xs text-neutral-500">{{ $item->sku }}@if($item->supplier) · {{ $item->supplier->name }}@endif</span>
                                </x-ui.table.td>
                                <x-ui.table.td numeric>{{ number_format($row['current_stock']) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format($row['historical_usage']) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format($row['average_daily_usage'], 2) }}</x-ui.table.td>
                                <x-ui.table.td numeric>{{ $row['days_of_cover'] === null ? '—' : number_format($row['days_of_cover']) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format($row['reorder_point']) }}</x-ui.table.td>
                                <x-ui.table.td numeric><span class="font-semibold text-primary-700 dark:text-primary-400">{{ number_format($row['suggested_order_quantity']) }}</span></x-ui.table.td>
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
            </div>

            {{-- TAB 3: SAVED PROCUREMENT PLANS TABLE --}}
            <div x-show="activeTab === 'plans'" class="overflow-x-auto max-h-96 overflow-y-auto">
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
                                <x-ui.table.td><span class="font-mono text-xs font-medium text-neutral-900 dark:text-neutral-100">{{ $plan->plan_number }}</span></x-ui.table.td>
                                <x-ui.table.td>{{ $plan->item?->name ?? '—' }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format((int) $plan->current_stock) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format((float) $plan->average_daily_usage, 2) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted>{{ number_format((int) $plan->reorder_point) }}</x-ui.table.td>
                                <x-ui.table.td numeric><span class="font-semibold text-primary-700 dark:text-primary-400">{{ number_format((int) $plan->suggested_order_quantity) }}</span></x-ui.table.td>
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
            </div>
        </div>

        {{-- MODAL: FULL AI FORECAST NARRATIVE & ITEM EXPLANATION --}}
        <x-ui.modal name="ai-forecast-explanation-modal" title="AI Forecast Analysis & Item Insights" maxWidth="3xl">
            <template x-if="!selectedAnalysisItem">
                <div class="py-8 text-center text-xs text-neutral-500 dark:text-neutral-400">
                    No item selected. Select an item from the table or chart to review detailed AI analysis.
                </div>
            </template>
            <template x-if="selectedAnalysisItem">
                <div class="space-y-4 p-1">
                    <div class="flex items-start justify-between gap-3 border-b border-neutral-200 pb-3 dark:border-neutral-800">
                        <div>
                            <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100" x-text="selectedAnalysisItem.item_name"></h3>
                            <p class="text-xs text-neutral-500 font-mono mt-0.5" x-text="`${selectedAnalysisItem.sku} · ${selectedAnalysisItem.category || 'General Supply'}`"></p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase ring-1 ring-inset"
                              :class="riskClasses(selectedAnalysisItem.risk_level)"
                              x-text="`${selectedAnalysisItem.risk_level} risk`"></span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-800/50">
                            <span class="text-[10px] font-semibold text-neutral-500 uppercase">Current Stock</span>
                            <p class="text-base font-bold text-neutral-900 dark:text-neutral-100 tabular-nums" x-text="`${formatNumber(selectedAnalysisItem.current_stock)} units`"></p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-800/50">
                            <span class="text-[10px] font-semibold text-neutral-500 uppercase">Historical ({{ $analysisDays }}d)</span>
                            <p class="text-base font-bold text-neutral-900 dark:text-neutral-100 tabular-nums" x-text="`${formatNumber(selectedAnalysisItem.historical_consumption)} units`"></p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-800/50">
                            <span class="text-[10px] font-semibold text-neutral-500 uppercase">Predicted Demand</span>
                            <p class="text-base font-bold text-violet-700 dark:text-violet-400 tabular-nums" x-text="`${formatNumber(selectedAnalysisItem.predicted_demand)} units`"></p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-800/50">
                            <span class="text-[10px] font-semibold text-neutral-500 uppercase">Recommended Order</span>
                            <p class="text-base font-bold text-primary-700 dark:text-primary-400 tabular-nums" x-text="`${formatNumber(selectedAnalysisItem.recommended_reorder_quantity)} units`"></p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">Model Analysis &amp; Narrative</h4>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50/70 p-3 text-xs leading-relaxed text-neutral-800 dark:border-neutral-800 dark:bg-neutral-800/40 dark:text-neutral-200" x-text="selectedAnalysisItem.explanation"></div>
                    </div>

                    <template x-if="selectedAnalysisItem.limited_data">
                        <div class="rounded-lg border border-warning-200 bg-warning-50 p-2.5 text-xs text-warning-800 dark:border-warning-900/60 dark:bg-warning-950/40 dark:text-warning-300 flex items-center gap-2">
                            <x-ui.icon name="exclamation-triangle" class="h-4 w-4 shrink-0 text-warning-600" />
                            <span>Limited movement history recorded for this item. Forecast uses conservative statistical smoothing.</span>
                        </div>
                    </template>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                        <x-ui.button type="button" variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'ai-forecast-explanation-modal')">
                            Close
                        </x-ui.button>
                        <x-ui.button type="button" size="sm" x-on:click="selectItem(selectedAnalysisItem.item_id); $dispatch('close-modal', 'ai-forecast-explanation-modal');">
                            Focus on Chart
                        </x-ui.button>
                    </div>
                </div>
            </template>
        </x-ui.modal>

    </div>
</x-app-layout>
