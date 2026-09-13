{{-- Load Chart.js Alpine component for this page only --}}
@vite('resources/js/demand-forecast-dashboard.js')

<x-app-layout :full-width="true">
    <x-slot:title>AI Demand Forecasting Dashboard</x-slot:title>

    <x-ui.page-header
        title="AI-Based Stock Demand Forecasting"
        subtitle="Projected demand, stockout risk analysis, and actionable reorder recommendations powered by AI."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Procurement' => route('inventory.purchases'), 'Demand Forecast' => null]">
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
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 1 — Top KPI Metric Cards
         ═══════════════════════════════════════════════════════════════ --}}
    <div
        x-data="forecastDashboardCharts()"
        x-on:destroy.window="destroy()"
        class="space-y-6"
    >
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- KPI 1: Projected Demand --}}
            <div class="relative overflow-hidden bg-white border border-neutral-200 rounded-lg shadow-sm p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Projected Demand</p>
                    <span class="flex items-center justify-center w-8 h-8 rounded-md bg-primary-50 text-primary-600">
                        <x-ui.icon name="chart-bar" class="w-4 h-4" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums text-neutral-900"
                   x-text="formatNumber(totalProjectedDemand()) + ' units'">— units</p>
                <div class="mt-1.5 flex items-center gap-1.5">
                    <template x-if="avgTrend() >= 0">
                        <span class="inline-flex items-center gap-0.5 text-xs font-medium text-danger-700">
                            <x-ui.icon name="arrow-trending-up" class="w-3.5 h-3.5" />
                            <span x-text="'+' + Math.abs(avgTrend()).toFixed(1) + '%'"></span>
                        </span>
                    </template>
                    <template x-if="avgTrend() < 0">
                        <span class="inline-flex items-center gap-0.5 text-xs font-medium text-success-700">
                            <x-ui.icon name="arrow-trending-down" class="w-3.5 h-3.5" />
                            <span x-text="Math.abs(avgTrend()).toFixed(1) + '%'"></span>
                        </span>
                    </template>
                    <span class="text-xs text-neutral-500">vs previous period</span>
                </div>
            </div>

            {{-- KPI 2: Stockout Risk Count --}}
            <div class="relative overflow-hidden bg-white border border-neutral-200 rounded-lg shadow-sm p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Stockout Risk</p>
                    <span class="flex items-center justify-center w-8 h-8 rounded-md bg-danger-50 text-danger-600">
                        <x-ui.icon name="exclamation-triangle" class="w-4 h-4" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums text-neutral-900"
                   x-text="stockoutRiskCount() + ' items'">— items</p>
                <div class="mt-1.5">
                    <x-ui.badge variant="danger" dot>Flagged for action</x-ui.badge>
                </div>
            </div>

            {{-- KPI 3: Capital at Risk / Excess Stock --}}
            <div class="relative overflow-hidden bg-white border border-neutral-200 rounded-lg shadow-sm p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Capital at Risk</p>
                    <span class="flex items-center justify-center w-8 h-8 rounded-md bg-warning-50 text-warning-600">
                        <x-ui.icon name="currency-dollar" class="w-4 h-4" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums text-neutral-900"
                   x-text="formatCurrency(capitalAtRisk())">—</p>
                <p class="mt-1 text-xs text-neutral-500">Excess stock value estimate</p>
            </div>

            {{-- KPI 4: AI Confidence Score --}}
            <div class="relative overflow-hidden bg-white border border-neutral-200 rounded-lg shadow-sm p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">AI Confidence</p>
                    <span class="flex items-center justify-center w-8 h-8 rounded-md bg-primary-50 text-primary-600">
                        <x-ui.icon name="sparkles" class="w-4 h-4" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums text-neutral-900"
                   x-text="aiConfidenceScore() + '%'">—%</p>
                <div class="mt-1.5">
                    <template x-if="confidenceTone() === 'success'">
                        <x-ui.badge variant="success" dot>High Reliability</x-ui.badge>
                    </template>
                    <template x-if="confidenceTone() === 'warning'">
                        <x-ui.badge variant="warning" dot>Medium Reliability</x-ui.badge>
                    </template>
                    <template x-if="confidenceTone() === 'danger'">
                        <x-ui.badge variant="danger" dot>Low Reliability</x-ui.badge>
                    </template>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 2 — Main 2-Column Grid: Chart + Explainability
             ═══════════════════════════════════════════════════════════ --}}
        <div class="grid gap-4 lg:grid-cols-5">

            {{-- Left Column: Demand Time-Series Chart --}}
            <x-ui.card class="lg:col-span-3" :padding="false">
                <x-slot:header>
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                            <x-ui.icon name="chart-bar" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-neutral-900">Demand Trend Analysis</h2>
                            <p class="mt-0.5 text-xs text-neutral-500">Historical consumption vs AI-projected forecast with confidence bands.</p>
                        </div>
                    </div>
                </x-slot:header>
                <x-slot:actions>
                    <div class="flex items-center gap-1.5">
                        <template x-for="horizon in ['7D', '30D', '90D']" x-bind:key="horizon">
                            <button
                                type="button"
                                x-text="horizon"
                                x-on:click="timeHorizon = horizon"
                                x-bind:class="timeHorizon === horizon
                                    ? 'bg-primary-600 text-white border-primary-600'
                                    : 'bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50'"
                                class="px-2.5 py-1 text-xs font-medium border rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            ></button>
                        </template>
                    </div>
                </x-slot:actions>

                <div class="p-4 sm:p-5">
                    {{-- SKU selector --}}
                    <div class="mb-4 max-w-xs">
                        <label for="chart-sku-select" class="block text-xs font-medium text-neutral-700 mb-1.5">Filter by SKU</label>
                        <select
                            id="chart-sku-select"
                            x-model="selectedSku"
                            class="block min-h-10 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500/30"
                        >
                            <option value="">All Items (Aggregated)</option>
                            <template x-for="item in inventoryItems" x-bind:key="item.id">
                                <option x-bind:value="item.sku" x-text="item.sku + ' · ' + item.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Chart canvas --}}
                    <div class="relative h-72 sm:h-80">
                        <canvas x-ref="demandChartCanvas"></canvas>
                    </div>
                </div>
            </x-ui.card>

            {{-- Right Column: Explainability & What-If Sandbox --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Feature Importance --}}
                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="sparkles" class="w-4 h-4 text-primary-600" />
                            <h2 class="text-sm font-semibold text-neutral-900">Key Forecast Drivers</h2>
                        </div>
                    </x-slot:header>

                    <div class="h-40">
                        <canvas x-ref="importanceChartCanvas"></canvas>
                    </div>
                </x-ui.card>

                {{-- What-If Simulation --}}
                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="adjustments-horizontal" class="w-4 h-4 text-warning-600" />
                            <h2 class="text-sm font-semibold text-neutral-900">What-If Simulation</h2>
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        {{-- Lead Time Slider --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="lead-time-slider" class="text-xs font-medium text-neutral-700">Lead Time Adjustment</label>
                                <span class="text-xs font-semibold tabular-nums text-neutral-900"
                                      x-text="(leadTimeAdjust >= 0 ? '+' : '') + leadTimeAdjust + ' days'"></span>
                            </div>
                            <input
                                type="range"
                                id="lead-time-slider"
                                x-model.number="leadTimeAdjust"
                                min="-14"
                                max="14"
                                step="1"
                                class="w-full h-2 rounded-lg appearance-none cursor-pointer bg-neutral-200 accent-primary-600"
                            >
                            <div class="flex justify-between text-[10px] text-neutral-400 mt-0.5">
                                <span>−14 days</span>
                                <span>0</span>
                                <span>+14 days</span>
                            </div>
                        </div>

                        {{-- Demand Spike Slider --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="demand-spike-slider" class="text-xs font-medium text-neutral-700">Demand Spike</label>
                                <span class="text-xs font-semibold tabular-nums text-neutral-900"
                                      x-text="(demandSpikeAdjust >= 0 ? '+' : '') + demandSpikeAdjust + '%'"></span>
                            </div>
                            <input
                                type="range"
                                id="demand-spike-slider"
                                x-model.number="demandSpikeAdjust"
                                min="-50"
                                max="50"
                                step="5"
                                class="w-full h-2 rounded-lg appearance-none cursor-pointer bg-neutral-200 accent-warning-600"
                            >
                            <div class="flex justify-between text-[10px] text-neutral-400 mt-0.5">
                                <span>−50%</span>
                                <span>0%</span>
                                <span>+50%</span>
                            </div>
                        </div>

                        {{-- Dynamic Stockout Alert --}}
                        <div
                            class="rounded-lg border px-3 py-2.5 text-sm transition-colors"
                            x-bind:class="{
                                'border-danger-200 bg-danger-50 text-danger-700': whatIfAlertVariant() === 'danger',
                                'border-warning-200 bg-warning-50 text-warning-700': whatIfAlertVariant() === 'warning',
                                'border-primary-200 bg-primary-50 text-primary-700': whatIfAlertVariant() === 'info',
                                'border-success-200 bg-success-50 text-success-700': whatIfAlertVariant() === 'success',
                            }"
                        >
                            <p class="text-xs font-semibold mb-0.5"
                               x-text="whatIfAlertVariant() === 'danger' ? 'Critical Stockout Warning' : 'Projected Stockout'"></p>
                            <p class="text-xs leading-relaxed" x-text="projectedStockoutDate()"></p>
                        </div>

                        {{-- Reset button --}}
                        <div class="flex justify-end">
                            <button
                                type="button"
                                x-on:click="leadTimeAdjust = 0; demandSpikeAdjust = 0"
                                x-show="leadTimeAdjust !== 0 || demandSpikeAdjust !== 0"
                                x-cloak
                                class="text-xs font-medium text-primary-600 hover:text-primary-800 transition-colors"
                            >
                                Reset simulation
                            </button>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 3 — Actionable Recommendations Table
             ═══════════════════════════════════════════════════════════ --}}
        <x-ui.card
            title="Actionable Recommendations"
            subtitle="AI-generated reorder suggestions sorted by urgency. Review and generate purchase orders."
            :padding="false">

            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>SKU / Item Name</x-ui.table.th>
                    <x-ui.table.th numeric>Current Stock</x-ui.table.th>
                    <x-ui.table.th numeric>Projected Demand</x-ui.table.th>
                    <x-ui.table.th numeric>DOI (Days)</x-ui.table.th>
                    <x-ui.table.th>AI Recommendation</x-ui.table.th>
                    <x-ui.table.th numeric>Suggested Qty</x-ui.table.th>
                    <x-ui.table.th align="right">Actions</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    <template x-for="item in inventoryItems" x-bind:key="item.id">
                        <tr class="border-b border-neutral-100 hover:bg-neutral-50/50">
                            <td class="px-3 py-3 sm:px-4 text-sm">
                                <span class="font-medium text-neutral-900" x-text="item.name"></span>
                                <span class="block text-xs text-neutral-500">
                                    <span x-text="item.sku"></span>
                                    <span class="mx-1">&middot;</span>
                                    <span x-text="item.category"></span>
                                </span>
                            </td>
                            <td class="px-3 py-3 sm:px-4 text-sm text-right tabular-nums text-neutral-700" x-text="formatNumber(item.currentStock)"></td>
                            <td class="px-3 py-3 sm:px-4 text-sm text-right tabular-nums text-neutral-700" x-text="formatNumber(item.projectedDemand)"></td>
                            <td class="px-3 py-3 sm:px-4 text-sm text-right tabular-nums"
                                x-bind:class="doiColorClass(item.daysOfInventory)"
                                x-text="item.daysOfInventory"></td>
                            <td class="px-3 py-3 sm:px-4">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset whitespace-nowrap"
                                    x-bind:class="{
                                        'bg-danger-50 text-danger-700 ring-danger-600/20': badgeVariant(item.recommendation) === 'danger',
                                        'bg-warning-50 text-warning-700 ring-warning-600/20': badgeVariant(item.recommendation) === 'warning',
                                        'bg-primary-50 text-primary-700 ring-primary-600/20': badgeVariant(item.recommendation) === 'primary',
                                        'bg-success-50 text-success-700 ring-success-600/20': badgeVariant(item.recommendation) === 'success',
                                    }"
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full"
                                        x-bind:class="{
                                            'bg-danger-500': badgeVariant(item.recommendation) === 'danger',
                                            'bg-warning-500': badgeVariant(item.recommendation) === 'warning',
                                            'bg-primary-500': badgeVariant(item.recommendation) === 'primary',
                                            'bg-success-500': badgeVariant(item.recommendation) === 'success',
                                        }"
                                        aria-hidden="true"
                                    ></span>
                                    <span x-text="badgeLabel(item.recommendation)"></span>
                                </span>
                            </td>
                            <td class="px-3 py-3 sm:px-4 text-sm text-right tabular-nums font-semibold text-neutral-900"
                                x-text="item.suggestedQty > 0 ? formatNumber(item.suggestedQty) : '—'"></td>
                            <td class="px-3 py-3 sm:px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <template x-if="item.suggestedQty > 0">
                                        <button
                                            type="button"
                                            x-on:click="openGeneratePO(item)"
                                            class="inline-flex items-center justify-center gap-1.5 font-medium rounded-md border transition-colors
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary-500
                                                   bg-primary-600 border-primary-600 text-white hover:bg-primary-700 hover:border-primary-700
                                                   min-h-9 px-2.5 py-1.5 text-xs"
                                        >
                                            <x-ui.icon name="shopping-cart" class="w-3.5 h-3.5 shrink-0" />
                                            Generate PO
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        x-on:click="openOverride(item)"
                                        class="inline-flex items-center justify-center gap-1.5 font-medium rounded-md border transition-colors
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary-500
                                               bg-white border-neutral-300 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900
                                               min-h-9 px-2.5 py-1.5 text-xs"
                                    >
                                        <x-ui.icon name="pencil-square" class="w-3.5 h-3.5 shrink-0" />
                                        Override
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </x-ui.table>
        </x-ui.card>

        {{-- ═══════════════════════════════════════════════════════════
             MODAL: Generate Purchase Order
             ═══════════════════════════════════════════════════════════ --}}
        <x-ui.modal name="generate-po" title="Generate Purchase Order" maxWidth="lg">
            <template x-if="poItem">
                <div class="space-y-4">
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                        <dl class="grid gap-2 sm:grid-cols-2 text-sm">
                            <div>
                                <dt class="text-xs font-medium text-neutral-500">Item</dt>
                                <dd class="font-medium text-neutral-900" x-text="poItem.name"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-neutral-500">SKU</dt>
                                <dd class="font-mono text-xs text-neutral-700" x-text="poItem.sku"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-neutral-500">Current Stock</dt>
                                <dd class="tabular-nums" x-text="formatNumber(poItem.currentStock) + ' units'"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-neutral-500">Suggested Order</dt>
                                <dd class="font-semibold text-primary-700 tabular-nums" x-text="formatNumber(poItem.suggestedQty) + ' units'"></dd>
                            </div>
                        </dl>
                    </div>
                    <x-ui.alert variant="info">
                        This action will create a draft Purchase Order for the suggested quantity. You can review and submit it from the Procurement page.
                    </x-ui.alert>
                </div>
            </template>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'generate-po')">Cancel</x-ui.button>
                {{-- UI-ready placeholder: wire to PurchaseOrderController@store in a future iteration --}}
                <x-ui.button type="button" icon="shopping-cart" x-on:click="$dispatch('close-modal', 'generate-po')">
                    Create Draft PO
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- ═══════════════════════════════════════════════════════════
             MODAL: Manual Override
             ═══════════════════════════════════════════════════════════ --}}
        <x-ui.modal name="manual-override" title="Manual Forecast Override" maxWidth="md">
            <template x-if="overrideItem">
                <div class="space-y-4">
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2">
                        <p class="text-sm font-medium text-neutral-900" x-text="overrideItem.name"></p>
                        <p class="text-xs text-neutral-500" x-text="overrideItem.sku + ' · AI suggested: ' + formatNumber(overrideItem.suggestedQty) + ' units'"></p>
                    </div>

                    <x-ui.field
                        name="override_quantity"
                        label="Override Quantity"
                        type="number"
                        required
                        hint="Enter the quantity you want to order instead of the AI suggestion."
                        x-model="overrideQty"
                    />

                    <div class="min-w-0 max-w-full space-y-1.5">
                        <label for="override-reason" class="block text-sm font-medium text-neutral-700">
                            Override Reason
                            <span class="text-danger-600" aria-hidden="true">*</span>
                            <span class="sr-only">(required)</span>
                        </label>
                        <select
                            id="override-reason"
                            x-model="overrideReason"
                            required
                            class="block min-h-10 min-w-0 max-w-full w-full rounded-md border border-neutral-300 text-sm shadow-sm transition-colors focus:border-primary-500 focus:ring-2 focus:ring-offset-0 focus:ring-primary-500/30"
                        >
                            <option value="">Select a reason…</option>
                            <option value="supplier_confirmed">Supplier confirmed different qty</option>
                            <option value="budget_constraint">Budget constraint</option>
                            <option value="known_demand">Known upcoming demand</option>
                            <option value="seasonal_adjustment">Seasonal adjustment</option>
                            <option value="clinical_decision">Clinical / operational decision</option>
                            <option value="other">Other</option>
                        </select>
                        <p class="text-xs text-neutral-500">Required for AI model audit logs.</p>
                    </div>

                    <x-ui.field
                        name="override_notes"
                        label="Additional Notes"
                        type="textarea"
                        :rows="2"
                        placeholder="Optional justification details…"
                        x-model="overrideNotes"
                    />
                </div>
            </template>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'manual-override')">Cancel</x-ui.button>
                <x-ui.button
                    type="button"
                    x-on:click="submitOverride()"
                    x-bind:disabled="!overrideReason || !overrideQty"
                >
                    Apply Override
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         EXISTING SECTIONS — Preserved from original page
         ═══════════════════════════════════════════════════════════════ --}}

    {{-- AI Forecast Card (existing server-rendered data) --}}
    @if ($aiForecast)
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                        <x-ui.icon name="chart-bar" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-900">Server-Side AI Forecast Details</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Gemini analyzes aggregated HIMS consumption history; recommendations are advisory only.
                        </p>
                    </div>
                </div>
            </x-slot:header>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge :variant="$aiForecast['source'] === 'ai' ? 'primary' : 'warning'" dot>
                    {{ $aiForecast['source_label'] }}
                </x-ui.badge>
                <span class="text-xs text-neutral-500">{{ $aiForecast['forecast_period'] }}</span>
                <span class="text-xs text-neutral-400">Generated {{ \Illuminate\Support\Carbon::parse($aiForecast['generated_at'])->format('M d, Y g:i A') }}</span>
            </div>

            @if ($aiForecast['notice'])
                <div class="mt-4">
                    <x-ui.alert variant="warning" title="Statistical fallback active">
                        {{ $aiForecast['notice'] }}
                    </x-ui.alert>
                </div>
            @endif

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

            <div class="mt-4 overflow-hidden rounded-lg border border-neutral-200">
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head>
                        <x-ui.table.th>Item</x-ui.table.th>
                        <x-ui.table.th>Risk</x-ui.table.th>
                        <x-ui.table.th numeric>Current</x-ui.table.th>
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
                                    <span class="block text-xs text-neutral-500">{{ $item['sku'] }}@if($item['category']) &middot; {{ $item['category'] }}@endif</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <x-ui.badge :variant="match($item['risk_level']) { 'high' => 'danger', 'medium' => 'warning', default => 'success' }" dot>
                                        {{ ucfirst($item['risk_level']) }}
                                    </x-ui.badge>
                                    <span class="mt-1 block text-xs text-neutral-500">{{ ucfirst($item['reorder_priority']) }} priority</span>
                                </x-ui.table.td>
                                <x-ui.table.td numeric>{{ number_format($item['current_stock']) }}</x-ui.table.td>
                                <x-ui.table.td numeric>{{ number_format($item['predicted_demand']) }}</x-ui.table.td>
                                <x-ui.table.td numeric>
                                    <span class="font-semibold text-neutral-900">{{ number_format($item['recommended_reorder_quantity']) }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <span class="text-sm text-neutral-800">{{ ucfirst($item['demand_trend']) }}</span>
                                    <span class="block text-xs text-neutral-500">{{ ucfirst($item['confidence']) }} confidence</span>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <p class="max-w-md whitespace-normal text-sm text-neutral-600">{{ $item['explanation'] }}</p>
                                    @if ($item['limited_data'])
                                        <span class="mt-1 block text-xs font-medium text-warning-700">Limited history</span>
                                    @endif
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty :colspan="7" icon="magnifying-glass" title="No matching forecast items" message="Adjust or clear the filters to see more results." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>
        </x-ui.card>
    @endif

    {{-- Statistical Forecast by Item (existing) --}}
    <x-ui.card
        title="Statistical Forecast by Item"
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

    {{-- Saved Plans (existing) --}}
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
