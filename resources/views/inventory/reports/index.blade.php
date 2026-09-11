@php
    $canViewFinancialData = auth()->user()->can(\App\Enums\Permission::ViewProcurementSensitiveData->value);
@endphp

<x-app-layout>
    <x-ui.page-header
        title="Reports & Analytics"
        :subtitle="($canViewFinancialData ? 'Inventory valuation, stock status, procurement spend and movement history' : 'Stock status and movement history').' for the '.(!empty($period['is_custom']) ? 'custom date range '.$period['from']->format('M d, Y').' — '.$period['to']->format('M d, Y') : $period['days'].'-day window ending '.$period['to']->format('M d, Y')).'.'"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Reports' => null]">
        <x-slot name="actions">
            <x-ui.button variant="secondary" icon="document-text"
                         onclick="window.print()" class="print:hidden">
                Print
            </x-ui.button>
            <x-ui.button icon="arrow-down-tray" class="print:hidden"
                         onclick="document.getElementById('report-generator')?.scrollIntoView({behavior: 'smooth'})">
                Generate Report
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{-- ------------------------------------------------ Integrated Full-Width Report Generator & Controls --}}
    <div id="report-generator"
         x-data="reportGenerator({
             initialPeriod: '{{ $currentPeriod }}',
             initialFrom: '{{ $currentFrom ?? $period['from']->format('Y-m-d') }}',
             initialTo: '{{ $currentTo ?? $period['to']->format('Y-m-d') }}',
             initialReportType: 'stock_status',
             generateUrl: '{{ route('inventory.reports.generate') }}',
             dashboardUrl: '{{ route('inventory.reports') }}',
             todayDate: '{{ now()->format('Y-m-d') }}',
             canViewFinancial: @json($canViewFinancialData)
         })"
         class="print:hidden">
        <x-ui.card>
            <div class="space-y-4">
                {{-- Header: Title, Active Mode Badge, and Reset --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-neutral-100">
                    <div>
                        <h2 class="text-sm font-bold text-neutral-900 flex items-center gap-2">
                            <x-ui.icon name="document-chart-bar" class="w-4 h-4 text-primary-600" />
                            <span>Report Generator & Timeline Controls</span>
                        </h2>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Customize report parameters, filter data by real clinical records, and export across multiple formats.
                        </p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-primary-50 text-primary-700 border border-primary-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary-500 animate-pulse"></span>
                            <span>Live Database Aggregation</span>
                        </span>
                        <button type="button" @click="resetFilters()"
                                class="text-xs text-neutral-500 hover:text-neutral-800 underline font-medium transition-colors">
                            Reset Defaults
                        </button>
                    </div>
                </div>

                {{-- Feedback / Alert Banners --}}
                <div x-show="errorMessage" x-cloak
                     class="flex items-start gap-2.5 p-3 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-xs transition-all">
                    <x-ui.icon name="exclamation-circle" class="w-4 h-4 text-danger-600 shrink-0 mt-0.5" />
                    <div class="flex-1 font-medium" x-text="errorMessage"></div>
                    <button type="button" @click="errorMessage = ''" class="text-danger-500 hover:text-danger-700">
                        <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <div x-show="successMessage" x-cloak
                     class="flex items-start gap-2.5 p-3 rounded-lg bg-success-50 border border-success-200 text-success-800 text-xs transition-all">
                    <x-ui.icon name="check-circle" class="w-4 h-4 text-success-600 shrink-0 mt-0.5" />
                    <div class="flex-1 font-medium" x-text="successMessage"></div>
                    <button type="button" @click="successMessage = ''" class="text-success-500 hover:text-success-700">
                        <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                    </button>
                </div>

                {{-- Row 1: Primary Controls (Scope, Timeline, Custom Date Range) --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 lg:gap-4 items-end">
                    {{-- 1. Report Type Selection --}}
                    <div class="md:col-span-6 lg:col-span-4">
                        <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                            <span>1. Report Module <span class="text-danger-500">*</span></span>
                            <span class="text-[10px] text-neutral-400 font-normal">10 modules</span>
                        </label>
                        <select x-model="reportType" @change="onReportTypeChange()"
                                class="w-full text-xs font-semibold rounded-lg border-neutral-300 bg-white py-2 focus:border-primary-500 focus:ring-primary-500 shadow-sm text-neutral-900">
                            <optgroup label="Complete Dossier">
                                <option value="all">⭐ All Reports (Complete Hospital Dossier)</option>
                            </optgroup>
                            <optgroup label="Stock & Valuation">
                                <option value="stock_status">Stock Status & Health</option>
                                <option value="valuation">Inventory Valuation by Category</option>
                                <option value="stock_by_location">Stock Distribution by Storage Location</option>
                                <option value="expiry_exposure">Expiry Exposure & Risk Batches</option>
                            </optgroup>
                            <optgroup label="Movements & Consumption">
                                <option value="movement_history">Stock Movement History & Ledger</option>
                                <option value="most_consumed">Most Consumed Items (Usage Velocity)</option>
                                <option value="movements_by_type">Activity by Movement Type</option>
                            </optgroup>
                            @if ($canViewFinancialData)
                            <optgroup label="Procurement & Financial (Protected)">
                                <option value="procurement_expense">Procurement Expense Breakdown</option>
                                <option value="spend_by_supplier">Spend by Supplier & Fulfilment</option>
                            </optgroup>
                            @endif
                        </select>
                    </div>

                    {{-- 2. Reporting Period / Timeline --}}
                    <div class="md:col-span-6 lg:col-span-3">
                        <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                            <span>2. Timeline / Window <span class="text-danger-500">*</span></span>
                            <span class="text-[10px] text-neutral-400 font-normal">Presets & Custom</span>
                        </label>
                        <select x-model="period" @change="onPeriodChange()"
                                class="w-full text-xs font-medium rounded-lg border-neutral-300 bg-white py-2 focus:border-primary-500 focus:ring-primary-500 shadow-sm text-neutral-900">
                            <option value="1">Today</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last 12 months</option>
                            <option value="all">All time</option>
                            <option value="custom">📅 Custom Date Range...</option>
                        </select>
                    </div>

                    {{-- 3. Custom Date Range Pickers OR Timeline Active Window Badge --}}
                    <div class="md:col-span-12 lg:col-span-5">
                        <div x-show="period === 'custom'" x-cloak class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 mb-1 flex items-center justify-between">
                                    <span>From Date</span>
                                    <span class="text-[10px] text-neutral-400 font-normal">start</span>
                                </label>
                                <input type="date" x-model="fromDate" max="{{ now()->format('Y-m-d') }}"
                                       class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 mb-1 flex items-center justify-between">
                                    <span>To Date</span>
                                    <span class="text-[10px] text-neutral-400 font-normal">end</span>
                                </label>
                                <input type="date" x-model="toDate" max="{{ now()->format('Y-m-d') }}"
                                       class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                            </div>
                        </div>

                        <div x-show="period !== 'custom'"
                             class="flex items-center justify-between p-2 rounded-lg bg-neutral-50 border border-neutral-200">
                            <div class="flex items-center gap-2">
                                <x-ui.icon name="calendar" class="w-4 h-4 text-primary-600 shrink-0" />
                                <div>
                                    <div class="text-[11px] font-semibold text-neutral-800" x-text="computedWindowText"></div>
                                    <div class="text-[10px] text-neutral-500">
                                        <span x-show="isPointInTimeReport()">Point-in-time catalogue snapshot (Dates apply to audit/history)</span>
                                        <span x-show="!isPointInTimeReport()">Historical transaction boundaries</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white border border-neutral-200 text-neutral-600">
                                Active Window
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Dynamic Filters & Sorting (Only applicable fields shown) --}}
                <div class="pt-3 border-t border-neutral-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-neutral-700 uppercase tracking-wider flex items-center gap-1.5">
                            <x-ui.icon name="funnel" class="w-3.5 h-3.5 text-primary-600" />
                            <span>3. Dynamic Filters & Sorting</span>
                        </span>
                        <span class="text-[11px] text-neutral-400">Filters dynamically show or hide based on the active report module</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        {{-- Category Filter --}}
                        <div x-show="hasCategoryFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Item Category</label>
                            <select x-model="categoryId"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Storage Location Filter --}}
                        <div x-show="hasLocationFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Storage Location</label>
                            <select x-model="locationId"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Locations</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Supplier Filter --}}
                        @if ($canViewFinancialData)
                        <div x-show="hasSupplierFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Supplier / Vendor</label>
                            <select x-model="supplierId"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Suppliers</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        {{-- Movement Type Filter --}}
                        <div x-show="hasMovementTypeFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Movement Type</label>
                            <select x-model="movementType"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Movement Types</option>
                                @foreach ($movementTypes as $mType)
                                    <option value="{{ $mType->value }}">{{ $mType->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Stock Status Filter --}}
                        <div x-show="hasStockStatusFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Stock Status</label>
                            <select x-model="status"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="all">All Levels</option>
                                <option value="in_stock">In Stock Only</option>
                                <option value="low_stock">Low Stock Only</option>
                                <option value="out_of_stock">Out of Stock Only</option>
                            </select>
                        </div>

                        {{-- Dynamic Sort Field --}}
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Sort By</label>
                            <select x-model="sortBy"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <template x-for="opt in currentSortOptions" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label" :selected="opt.value === sortBy"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Sort Direction --}}
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Order</label>
                            <select x-model="sortDir"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="desc">Descending (High/New)</option>
                                <option value="asc">Ascending (Low/Old)</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Row 3: Output Format & Action Bar (Full Width Footer) --}}
                <div class="pt-3 border-t border-neutral-200 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-neutral-50/70 -mx-6 -mb-6 p-4 rounded-b-xl">
                    {{-- Format Selector Pills --}}
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-neutral-700 uppercase tracking-wider hidden lg:inline mr-1">
                            4. Format:
                        </span>
                        <div class="inline-flex rounded-lg border border-neutral-300 bg-white p-0.5 shadow-sm">
                            <button type="button" @click="format = 'pdf'"
                                    :class="format === 'pdf' ? 'bg-primary-600 text-white font-semibold' : 'text-neutral-700 hover:text-neutral-900'"
                                    class="px-3 py-1.5 text-xs rounded-md flex items-center gap-1.5 transition-all">
                                <span>🖨️</span>
                                <span>PDF / Print</span>
                            </button>
                            <button type="button" @click="format = 'excel'"
                                    :class="format === 'excel' ? 'bg-primary-600 text-white font-semibold' : 'text-neutral-700 hover:text-neutral-900'"
                                    class="px-3 py-1.5 text-xs rounded-md flex items-center gap-1.5 transition-all">
                                <span>📊</span>
                                <span>Excel (.xls)</span>
                            </button>
                            <button type="button" @click="format = 'csv'"
                                    :class="format === 'csv' ? 'bg-primary-600 text-white font-semibold' : 'text-neutral-700 hover:text-neutral-900'"
                                    class="px-3 py-1.5 text-xs rounded-md flex items-center gap-1.5 transition-all">
                                <span>📄</span>
                                <span>CSV</span>
                            </button>
                            <button type="button" @click="format = 'json'"
                                    :class="format === 'json' ? 'bg-primary-600 text-white font-semibold' : 'text-neutral-700 hover:text-neutral-900'"
                                    class="px-3 py-1.5 text-xs rounded-md flex items-center gap-1.5 transition-all">
                                <span>⚙️</span>
                                <span>JSON</span>
                            </button>
                        </div>
                    </div>

                    {{-- Action Controls --}}
                    <div class="flex items-center gap-2.5 justify-end">
                        <button type="button" @click="applyToDashboard()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 shadow-sm transition-all">
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-neutral-500" />
                            <span>Apply to Dashboard</span>
                        </button>

                        <button type="button" @click="generate()" :disabled="loading"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-lg text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
                            <template x-if="loading">
                                <svg class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <template x-if="!loading">
                                <x-ui.icon name="arrow-down-tray" class="w-3.5 h-3.5 text-white" />
                            </template>
                            <span x-text="loading ? 'Generating Report...' : (format === 'pdf' ? 'Open Printable Report' : 'Generate & Export Report')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- ------------------------------------------------ 1. inventory summary --}}

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat
            label="Items in catalogue"
            :value="number_format($summary['items'])"
            icon="cube"
            tone="primary"
            :hint="number_format($summary['units_on_hand']).' units on hand'" />

        @if ($canViewFinancialData)
        <x-ui.stat
            label="Stock valuation"
            :value="'₱'.number_format($summary['stock_value'], 2)"
            icon="chart-bar"
            tone="neutral"
            hint="Units on hand × unit cost." />
        @endif

        <x-ui.stat
            label="Needs attention"
            :value="number_format($summary['needs_attention'])"
            icon="exclamation-triangle"
            :tone="$summary['needs_attention'] > 0 ? 'warning' : 'success'"
            hint="At or below reorder level, or out of stock."
            :href="route('inventory.alerts')" />

        <x-ui.stat
            label="Reserved units"
            :value="number_format($summary['reserved_units'])"
            icon="clipboard-document-list"
            tone="neutral"
            hint="Committed elsewhere — not available to issue." />
    </div>

    {{-- --------------------------------------------------- 2. stock status & executive health overview --}}

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Stock Status" subtitle="Every item, bucketed by how much is left.">
            @php
                $bars = [
                    'in_stock' => ['label' => 'In stock', 'variant' => 'success', 'bar' => 'bg-success-500'],
                    'low_stock' => ['label' => 'Low stock', 'variant' => 'warning', 'bar' => 'bg-warning-500'],
                    'out_of_stock' => ['label' => 'Out of stock', 'variant' => 'danger', 'bar' => 'bg-danger-500'],
                ];
            @endphp

            <dl class="space-y-4">
                @foreach ($bars as $key => $bar)
                    @php
                        $bucket = $stockStatus[$key];
                        $share = $summary['items'] > 0
                            ? round(($bucket['items'] / $summary['items']) * 100)
                            : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>
                                <x-ui.badge :variant="$bar['variant']" dot>{{ $bar['label'] }}</x-ui.badge>
                            </dt>
                            <dd class="text-sm font-semibold tabular-nums text-neutral-900">
                                {{ number_format($bucket['items']) }}
                                <span class="text-xs font-normal text-neutral-500">({{ $share }}%)</span>
                            </dd>
                        </div>

                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full {{ $bar['bar'] }}" style="width: {{ $share }}%"></div>
                        </div>

                        <p class="mt-1 text-xs text-neutral-500">
                            {{ number_format($bucket['units']) }} units
                            @if ($canViewFinancialData)
                                &middot; ₱{{ number_format($bucket['value'], 2) }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Expiry Exposure" subtitle="Batches still holding stock, valued at risk.">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-md border border-danger-200 bg-danger-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-danger-700">Expired</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-danger-800">
                        {{ number_format($expiry['expired']['units']) }}
                        <span class="text-xs font-normal">units</span>
                    </p>
                    <p class="text-xs text-danger-700">
                        {{ $expiry['expired']['batches'] }} batches
                        @if ($canViewFinancialData)
                            &middot; ₱{{ number_format($expiry['expired']['value'], 2) }}
                        @endif
                    </p>
                </div>

                <div class="rounded-md border border-warning-200 bg-warning-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-warning-700">Expiring soon</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-warning-800">
                        {{ number_format($expiry['expiring_soon']['units']) }}
                        <span class="text-xs font-normal">units</span>
                    </p>
                    <p class="text-xs text-warning-700">
                        {{ $expiry['expiring_soon']['batches'] }} batches
                        @if ($canViewFinancialData)
                            &middot; ₱{{ number_format($expiry['expiring_soon']['value'], 2) }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($expiry['rows']->isNotEmpty())
                <ul class="mt-4 space-y-2 border-t border-neutral-200 pt-3">
                    @foreach ($expiry['rows']->take(3) as $batch)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-neutral-900">
                                    {{ $batch->item?->name ?? 'Unknown item' }}
                                </span>
                                <span class="block text-xs text-neutral-500">
                                    {{ $batch->batch_number }} &middot; {{ number_format((int) $batch->units_on_hand) }} units
                                </span>
                            </span>
                            <x-ui.badge :status="$batch->isExpired() ? 'expired' : 'expiring_soon'">
                                {{ $batch->expiry_date?->format('M d, Y') }}
                            </x-ui.badge>
                        </li>
                    @endforeach
                </ul>
                @if ($expiry['rows']->count() > 3)
                    <p class="mt-2 text-xs text-neutral-500 text-right print:hidden">
                        +{{ $expiry['rows']->count() - 3 }} more in detailed tab below
                    </p>
                @endif
            @else
                <p class="mt-4 border-t border-neutral-200 pt-3 text-xs text-neutral-500">
                    No dated batch is expired or inside its warning window.
                </p>
            @endif
        </x-ui.card>

        <x-ui.card title="Movement Summary" :subtitle="'Recorded in the last '.$period['days'].' days.'">
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Movements recorded</dt>
                    <dd class="font-semibold tabular-nums text-neutral-900">{{ number_format($movementTotals['movements']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Units received in</dt>
                    <dd class="font-semibold tabular-nums text-success-700">+{{ number_format($movementTotals['units_in']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Units consumed</dt>
                    <dd class="font-semibold tabular-nums text-neutral-900">&minus;{{ number_format($movementTotals['units_out']) }}</dd>
                </div>
                @if ($canViewFinancialData)
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Consumption value</dt>
                    <dd class="font-semibold tabular-nums text-neutral-900">₱{{ number_format($movementTotals['consumption_value'], 2) }}</dd>
                </div>
                @endif
                <div class="flex items-center justify-between gap-3 border-t border-neutral-200 pt-3">
                    <dt class="text-neutral-600">Transfers</dt>
                    <dd class="tabular-nums text-neutral-700">{{ number_format($movementTotals['transfers']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Units disposed</dt>
                    <dd class="tabular-nums text-neutral-700">{{ number_format($movementTotals['disposals']) }}</dd>
                </div>
            </dl>

            <p class="mt-4 border-t border-neutral-200 pt-3 text-xs text-neutral-500">
                Consumed counts stock out and issuance only. Transfers relocate stock between zones without consumption.
            </p>
        </x-ui.card>
    </div>

    {{-- --------------------------------------------------- 3. interactive detailed report center --}}

    <div x-data="{ activeTab: '{{ $canViewFinancialData ? 'valuation' : 'movements' }}' }" class="space-y-6">
        {{-- Navigation tab bar (hidden on print) --}}
        <div class="border-b border-neutral-200 bg-white rounded-lg px-3 py-2 border shadow-xs print:hidden">
            <nav class="flex flex-wrap items-center gap-1.5" aria-label="Detailed Report Sections">
                <button
                    type="button"
                    x-on:click="activeTab = 'valuation'"
                    :class="activeTab === 'valuation'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="chart-bar" class="w-4 h-4" />
                    <span>Valuation &amp; Locations</span>
                </button>

                @if ($canViewFinancialData)
                <button
                    type="button"
                    x-on:click="activeTab = 'procurement'"
                    :class="activeTab === 'procurement'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="truck" class="w-4 h-4" />
                    <span>Procurement &amp; Spending</span>
                </button>
                @endif

                <button
                    type="button"
                    x-on:click="activeTab = 'movements'"
                    :class="activeTab === 'movements'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="arrows-right-left" class="w-4 h-4" />
                    <span>Movements &amp; Consumption</span>
                </button>

                <button
                    type="button"
                    x-on:click="activeTab = 'expiry'"
                    :class="activeTab === 'expiry'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="exclamation-triangle" class="w-4 h-4" />
                    <span>Expiry Risk Batches</span>
                    @if ($expiry['rows']->isNotEmpty())
                        <span class="rounded-full bg-warning-100 text-warning-800 px-1.5 py-0.2 text-[10px] font-mono">
                            {{ $expiry['rows']->count() }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Tab 1: Valuation & Locations --}}
        <div x-show="activeTab === 'valuation'" x-cloak class="space-y-6 print:!block">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.card title="Valuation by Category" subtitle="Where the money is tied up." :padding="false">
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head>
                            <x-ui.table.th class="px-3 py-2.5">Category</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Items</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Units</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                                <x-ui.table.th numeric class="px-3 py-2.5">Share</x-ui.table.th>
                            @endif
                        </x-ui.table.head>
                        <tbody>
                            @forelse ($valuationByCategory as $row)
                                <x-ui.table.row>
                                    <x-ui.table.td class="px-3 py-2.5 font-medium text-neutral-900">{{ $row->category }}</x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row->items) }}</x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row->units) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric class="px-3 py-2.5 font-medium">₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                                        <x-ui.table.td numeric muted class="px-3 py-2.5">
                                            {{ $summary['stock_value'] > 0
                                                ? round(($row->value / $summary['stock_value']) * 100).'%'
                                                : '—' }}
                                        </x-ui.table.td>
                                    @endif
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.empty
                                    :colspan="$canViewFinancialData ? 5 : 3"
                                    icon="cube"
                                    title="No items yet"
                                    message="Add inventory items and the valuation fills in." />
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card title="Stock by Location" subtitle="What each storage location is holding." :padding="false">
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head>
                            <x-ui.table.th class="px-3 py-2.5">Location</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Items</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Units</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                            @endif
                            <x-ui.table.th numeric class="px-3 py-2.5">Utilisation</x-ui.table.th>
                        </x-ui.table.head>
                        <tbody>
                            @forelse ($stockByLocation as $row)
                                <x-ui.table.row>
                                    <x-ui.table.td class="px-3 py-2.5">
                                        <span class="font-medium text-neutral-900">{{ $row['location'] }}</span>
                                        @if ($row['code'])
                                            <span class="block text-xs text-neutral-500 font-mono">{{ $row['code'] }}</span>
                                        @endif
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row['items']) }}</x-ui.table.td>
                                    <x-ui.table.td numeric class="px-3 py-2.5 font-medium">{{ number_format($row['units']) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric muted class="px-3 py-2.5">₱{{ number_format($row['value'], 2) }}</x-ui.table.td>
                                    @endif
                                    <x-ui.table.td numeric class="px-3 py-2.5">
                                        @if ($row['utilisation'] === null)
                                            <span class="text-neutral-400">—</span>
                                        @else
                                            <span @class([
                                                'font-semibold',
                                                'text-danger-700' => $row['utilisation'] >= 90,
                                                'text-warning-700' => $row['utilisation'] >= 75 && $row['utilisation'] < 90,
                                                'text-neutral-800' => $row['utilisation'] < 75,
                                            ])>{{ $row['utilisation'] }}%</span>
                                            <span class="block text-xs text-neutral-400">
                                                of {{ number_format($row['capacity']) }}
                                            </span>
                                        @endif
                                    </x-ui.table.td>
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.empty
                                    :colspan="$canViewFinancialData ? 5 : 4"
                                    icon="building-storefront"
                                    title="No stock in any location"
                                    message="Record a stock in and the location balances appear here." />
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
            </div>
        </div>

        {{-- Tab 2: Procurement & Spending --}}
        @if ($canViewFinancialData)
        <div x-show="activeTab === 'procurement'" x-cloak class="space-y-6 print:!block">
            <x-ui.card
                title="Procurement Expense"
                :subtitle="'Purchase orders raised in the last '.$period['days'].' days, plus everything still outstanding.'">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ordered</p>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-neutral-900">
                            ₱{{ number_format($spend['ordered']['value'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">{{ $spend['ordered']['orders'] }} purchase orders</p>
                    </div>

                    <div class="rounded-md border border-success-200 bg-success-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-success-700">Received</p>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-success-800">
                            ₱{{ number_format($spend['received']['value'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-success-700">{{ $spend['received']['orders'] }} booked into stock</p>
                    </div>

                    <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-warning-700">Outstanding</p>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-warning-800">
                            ₱{{ number_format($spend['outstanding']['value'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-warning-700">
                            {{ $spend['outstanding']['orders'] }} awaiting delivery, all time
                        </p>
                    </div>

                    <div class="rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Average order</p>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-neutral-900">
                            ₱{{ number_format($spend['average_order_value'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">Across orders raised in the window</p>
                    </div>
                </div>

                <p class="mt-4 text-xs text-neutral-500">
                    Ordered is dated by when the order was raised, Received by when the delivery was booked in.
                    Outstanding covers every order not yet received or cancelled, regardless of date.
                </p>
            </x-ui.card>

            <x-ui.card title="Spend by Supplier" :subtitle="'Top vendors in the last '.$period['days'].' days.'" :padding="false">
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-2.5">Supplier</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Orders</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Received</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Fulfilment</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($spendBySupplier as $row)
                            @php $rate = $row->orders > 0 ? round(($row->received_orders / $row->orders) * 100) : 0; @endphp
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <span class="font-medium text-neutral-900">{{ $row->supplier }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row->orders) }}</x-ui.table.td>
                                <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row->received_orders) }}</x-ui.table.td>
                                <x-ui.table.td numeric class="px-3 py-2.5">
                                    <x-ui.badge :variant="$rate >= 80 ? 'success' : ($rate >= 40 ? 'warning' : 'neutral')">
                                        {{ $rate }}%
                                    </x-ui.badge>
                                </x-ui.table.td>
                                <x-ui.table.td numeric class="px-3 py-2.5 font-medium">₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="5"
                                icon="truck"
                                title="No purchase orders in this window"
                                message="Raise one under Requisitions &amp; POs and the spend appears here." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
        @endif

        {{-- Tab 3: Movements & Consumption --}}
        <div x-show="activeTab === 'movements'" x-cloak class="space-y-6 print:!block">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.card title="Activity by Movement Type" :subtitle="'Last '.$period['days'].' days.'" :padding="false">
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head>
                            <x-ui.table.th class="px-3 py-2.5">Type</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Movements</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Units</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                            @endif
                        </x-ui.table.head>
                        <tbody>
                            @foreach ($movementsByType as $row)
                                <x-ui.table.row :class="$row['movements'] === 0 ? 'opacity-60' : ''">
                                    <x-ui.table.td class="px-3 py-2.5">
                                        <x-ui.badge :status="$row['type']->value">{{ $row['type']->label() }}</x-ui.badge>
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric class="px-3 py-2.5 font-medium">{{ number_format($row['movements']) }}</x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row['units']) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric muted class="px-3 py-2.5">₱{{ number_format($row['value'], 2) }}</x-ui.table.td>
                                    @endif
                                </x-ui.table.row>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card title="Most Consumed Items" subtitle="By units issued or taken out." :padding="false">
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head>
                            <x-ui.table.th class="px-3 py-2.5">Item</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Consumed</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">On Hand</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                            @endif
                        </x-ui.table.head>
                        <tbody>
                            @forelse ($topConsumedItems as $row)
                                <x-ui.table.row>
                                    <x-ui.table.td class="px-3 py-2.5">
                                        <span class="font-medium text-neutral-900">{{ $row->item }}</span>
                                        <span class="block text-xs text-neutral-500">
                                            {{ $row->sku }} &middot; {{ $row->movements }} movements
                                        </span>
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric class="px-3 py-2.5 font-medium">
                                        {{ number_format($row->units) }}
                                        <span class="block text-xs font-normal text-neutral-400">{{ $row->unit ?? 'units' }}</span>
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row->on_hand) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric muted class="px-3 py-2.5">₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                                    @endif
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.empty
                                    :colspan="$canViewFinancialData ? 4 : 3"
                                    icon="arrows-right-left"
                                    title="Nothing consumed in this window"
                                    message="Stock out and issuance movements are what this counts." />
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
            </div>

            <x-ui.card
                title="Movement History"
                :subtitle="$recentMovements->count().' most recent in this window'"
                :padding="false">
                <x-slot name="actions">
                    <x-ui.button variant="ghost" size="sm" :href="route('inventory.stock-movements')" class="print:hidden">
                        View all &rarr;
                    </x-ui.button>
                </x-slot>

                <x-ui.table :sticky-header="false">
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-2.5">Item</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Type</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Qty</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">From</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">To / Reference</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Recorded</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($recentMovements as $movement)
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <span class="font-medium text-neutral-900">{{ $movement->item?->name ?? '—' }}</span>
                                    @if ($movement->item?->sku)
                                        <span class="block text-xs text-neutral-500 font-mono">{{ $movement->item->sku }}</span>
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <x-ui.badge :status="$movement->movement_type->value">
                                        {{ $movement->movement_type->label() }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                <x-ui.table.td numeric class="px-3 py-2.5 font-medium">{{ number_format($movement->quantity) }}</x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5">{{ $movement->fromLocation?->name ?? '—' }}</x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5">
                                    @if ($movement->toLocation)
                                        {{ $movement->toLocation->name }}
                                    @elseif ($movement->reference)
                                        {{ $movement->reference->name ?? class_basename($movement->reference) }}
                                    @else
                                        —
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5">
                                    {{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}
                                    @if ($movement->user)
                                        <span class="block text-xs text-neutral-400">{{ $movement->user->name }}</span>
                                    @endif
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="6"
                                icon="arrows-right-left"
                                title="No movements in this window"
                                message="Widen the reporting period, or record a movement to start the history." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        {{-- Tab 4: Expiry Risk Batches --}}
        <div x-show="activeTab === 'expiry'" x-cloak class="space-y-6 print:!block">
            <x-ui.card title="Expiry Exposure - Detailed Batches" subtitle="Batches holding stock that are expired or approaching expiry." :padding="false">
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-2.5">Item Name</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Batch Number</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Units on Hand</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Expiry Date</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Status</x-ui.table.th>
                        @if ($canViewFinancialData)
                            <x-ui.table.th numeric class="px-3 py-2.5">At-Risk Value</x-ui.table.th>
                        @endif
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($expiry['rows'] as $batch)
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5 font-medium text-neutral-900">
                                    {{ $batch->item?->name ?? 'Unknown item' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 font-mono text-xs text-neutral-600">
                                    {{ $batch->batch_number }}
                                </x-ui.table.td>
                                <x-ui.table.td numeric class="px-3 py-2.5 font-medium">
                                    {{ number_format((int) $batch->units_on_hand) }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    {{ $batch->expiry_date?->format('M d, Y') }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <x-ui.badge :status="$batch->isExpired() ? 'expired' : 'expiring_soon'">
                                        {{ $batch->isExpired() ? 'Expired' : 'Expiring Soon' }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                @if ($canViewFinancialData)
                                    <x-ui.table.td numeric class="px-3 py-2.5 font-medium text-danger-700">
                                        ₱{{ number_format((float) ($batch->units_on_hand * ($batch->unit_cost ?? $batch->item?->unit_cost ?? 0)), 2) }}
                                    </x-ui.table.td>
                                @endif
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="$canViewFinancialData ? 6 : 5"
                                icon="check-circle"
                                title="No expired or expiring batches"
                                message="All dated inventory batches are currently healthy and well outside expiry warning windows." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>

    <p class="text-xs text-neutral-400">
        Generated {{ now()->format('M d, Y g:i A') }} for {{ auth()->user()?->name }}.
        Every figure is read live from the same records the operational screens use.
    </p>

    <script>
        function reportGenerator(config) {
            return {
                reportType: config.initialReportType || 'stock_status',
                period: config.initialPeriod || '30',
                fromDate: config.initialFrom || '',
                toDate: config.initialTo || '',
                categoryId: '',
                locationId: '',
                supplierId: '',
                movementType: '',
                status: 'all',
                sortBy: 'name',
                sortDir: 'asc',
                format: 'pdf',
                loading: false,
                errorMessage: '',
                successMessage: '',
                computedWindowText: '',

                init() {
                    this.updateComputedWindow();
                    this.updateSortOptions();
                },

                onPeriodChange() {
                    this.updateComputedWindow();
                    this.errorMessage = '';
                },

                onReportTypeChange() {
                    this.updateSortOptions();
                    this.errorMessage = '';
                },

                isPointInTimeReport() {
                    return ['stock_status', 'valuation', 'stock_by_location', 'expiry_exposure'].includes(this.reportType);
                },

                hasCategoryFilter() {
                    return ['all', 'stock_status', 'valuation', 'expiry_exposure', 'most_consumed'].includes(this.reportType);
                },

                hasLocationFilter() {
                    return ['all', 'stock_status', 'stock_by_location', 'expiry_exposure', 'movement_history'].includes(this.reportType);
                },

                hasSupplierFilter() {
                    return ['all', 'procurement_expense', 'spend_by_supplier'].includes(this.reportType);
                },

                hasMovementTypeFilter() {
                    return ['all', 'movement_history', 'movements_by_type'].includes(this.reportType);
                },

                hasStockStatusFilter() {
                    return ['all', 'stock_status'].includes(this.reportType);
                },

                get currentSortOptions() {
                    switch (this.reportType) {
                        case 'stock_status':
                            return [
                                { value: 'name', label: 'Item Description (A-Z)' },
                                { value: 'units', label: 'Units On Hand' },
                                { value: 'value', label: 'Stock Valuation' },
                                { value: 'status', label: 'Stock Health Status' },
                            ];
                        case 'valuation':
                            return [
                                { value: 'value', label: 'Valuation Amount (₱)' },
                                { value: 'name', label: 'Category Name' },
                                { value: 'items', label: 'Catalogue Items Count' },
                                { value: 'units', label: 'Total Units Stored' },
                            ];
                        case 'stock_by_location':
                            return [
                                { value: 'utilisation', label: 'Capacity Utilisation %' },
                                { value: 'name', label: 'Location Name' },
                                { value: 'units', label: 'Total Units Held' },
                                { value: 'value', label: 'Location Valuation' },
                            ];
                        case 'expiry_exposure':
                            return [
                                { value: 'date', label: 'Expiry Date (Soonest)' },
                                { value: 'units', label: 'At-Risk Units' },
                                { value: 'value', label: 'Financial Risk Exposure' },
                            ];
                        case 'movement_history':
                            return [
                                { value: 'date', label: 'Movement Timestamp (Recency)' },
                                { value: 'units', label: 'Quantity Moved' },
                                { value: 'value', label: 'Movement Valuation' },
                            ];
                        case 'procurement_expense':
                            return [
                                { value: 'date', label: 'Order Date' },
                                { value: 'amount', label: 'PO Total Amount' },
                                { value: 'orders', label: 'PO Number' },
                            ];
                        case 'spend_by_supplier':
                            return [
                                { value: 'value', label: 'Total Spend Amount' },
                                { value: 'fulfilment', label: 'Fulfilment Rate %' },
                                { value: 'orders', label: 'Order Count' },
                                { value: 'supplier', label: 'Supplier Name' },
                            ];
                        case 'most_consumed':
                            return [
                                { value: 'units', label: 'Units Consumed' },
                                { value: 'movements', label: 'Consumption Events' },
                                { value: 'value', label: 'Consumption Value' },
                            ];
                        case 'movements_by_type':
                            return [
                                { value: 'movements', label: 'Movement Count' },
                                { value: 'units', label: 'Units Transacted' },
                                { value: 'value', label: 'Total Value' },
                            ];
                        default:
                            return [
                                { value: 'date', label: 'Date / Recency' },
                                { value: 'value', label: 'Financial Value' },
                                { value: 'units', label: 'Units / Volume' },
                            ];
                    }
                },

                updateSortOptions() {
                    const opts = this.currentSortOptions;
                    if (!opts.some(o => o.value === this.sortBy)) {
                        this.sortBy = opts[0]?.value || 'name';
                    }
                },

                updateComputedWindow() {
                    const daysMap = { '1': 1, '7': 7, '30': 30, '90': 90, '365': 365 };
                    if (this.period === 'all') {
                        this.computedWindowText = 'All Historical Records (Up to Today)';
                    } else if (this.period === 'custom') {
                        this.computedWindowText = `Custom: ${this.fromDate || '...'} to ${this.toDate || '...'}`;
                    } else {
                        const days = daysMap[this.period] || 30;
                        this.computedWindowText = `Last ${days} days ending ${config.todayDate}`;
                    }
                },

                resetFilters() {
                    this.reportType = 'stock_status';
                    this.period = '30';
                    this.fromDate = config.initialFrom;
                    this.toDate = config.initialTo;
                    this.categoryId = '';
                    this.locationId = '';
                    this.supplierId = '';
                    this.movementType = '';
                    this.status = 'all';
                    this.sortBy = 'name';
                    this.sortDir = 'asc';
                    this.format = 'pdf';
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.updateComputedWindow();
                    this.updateSortOptions();
                },

                applyToDashboard() {
                    this.errorMessage = '';
                    if (this.period === 'custom') {
                        if (!this.fromDate) {
                            this.errorMessage = 'Please provide a start date for the custom date range.';
                            return;
                        }
                        if (!this.toDate) {
                            this.errorMessage = 'Please provide an end date for the custom date range.';
                            return;
                        }
                        if (this.fromDate > this.toDate) {
                            this.errorMessage = 'The start date cannot be later than the end date.';
                            return;
                        }
                        if (this.toDate > config.todayDate) {
                            this.errorMessage = 'The end date cannot be a future date.';
                            return;
                        }
                    }

                    const params = new URLSearchParams();
                    if (this.period === 'custom') {
                        params.set('period', 'custom');
                        params.set('from', this.fromDate);
                        params.set('to', this.toDate);
                    } else {
                        params.set('period', this.period);
                        params.set('days', this.period === 'all' ? '365' : this.period);
                    }

                    window.location.href = config.dashboardUrl + '?' + params.toString();
                },

                async generate() {
                    this.errorMessage = '';
                    this.successMessage = '';

                    // Validation
                    if (['procurement_expense', 'spend_by_supplier'].includes(this.reportType) && !config.canViewFinancial) {
                        this.errorMessage = 'You do not have permission to view or export procurement financial reports.';
                        return;
                    }

                    if (this.period === 'custom') {
                        if (!this.fromDate) {
                            this.errorMessage = 'Please provide a start date for the custom date range.';
                            return;
                        }
                        if (!this.toDate) {
                            this.errorMessage = 'Please provide an end date for the custom date range.';
                            return;
                        }
                        if (this.fromDate > this.toDate) {
                            this.errorMessage = 'The start date cannot be later than the end date.';
                            return;
                        }
                        if (this.toDate > config.todayDate) {
                            this.errorMessage = 'The end date cannot be a future date.';
                            return;
                        }
                    }

                    this.loading = true;

                    const params = new URLSearchParams();
                    params.set('report_type', this.reportType);
                    params.set('format', this.format);
                    params.set('period', this.period);
                    if (this.period === 'custom') {
                        params.set('from', this.fromDate);
                        params.set('to', this.toDate);
                    } else {
                        params.set('days', this.period === 'all' ? '3650' : this.period);
                    }

                    if (this.categoryId) params.set('category_id', this.categoryId);
                    if (this.locationId) params.set('storage_location_id', this.locationId);
                    if (this.supplierId) params.set('supplier_id', this.supplierId);
                    if (this.movementType) params.set('movement_type', this.movementType);
                    if (this.status && this.status !== 'all') params.set('status', this.status);
                    if (this.sortBy) params.set('sort_by', this.sortBy);
                    if (this.sortDir) params.set('sort_direction', this.sortDir);

                    const url = config.generateUrl + '?' + params.toString();

                    if (this.format === 'pdf' || this.format === 'print') {
                        window.open(url, '_blank');
                        this.loading = false;
                        this.successMessage = 'Official report opened in print view.';
                        return;
                    }

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': this.format === 'json' ? 'application/json' : '*/*'
                            }
                        });

                        if (!response.ok) {
                            let errMsg = 'Failed to generate report.';
                            try {
                                const errData = await response.json();
                                errMsg = errData.message || (errData.errors ? Object.values(errData.errors).flat().join(' ') : errMsg);
                            } catch (e) {
                                errMsg = `Error ${response.status}: ${response.statusText}`;
                            }
                            throw new Error(errMsg);
                        }

                        const blob = await response.blob();
                        let filename = `hims-${this.reportType}-${new Date().toISOString().slice(0, 10)}.${this.format === 'excel' ? 'xls' : this.format}`;

                        const disposition = response.headers.get('content-disposition');
                        if (disposition && disposition.indexOf('filename=') !== -1) {
                            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                            if (matches != null && matches[1]) {
                                filename = matches[1].replace(/['"]/g, '');
                            }
                        }

                        const blobUrl = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = blobUrl;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(blobUrl);
                        a.remove();

                        this.successMessage = `Successfully generated and downloaded ${filename}`;
                    } catch (err) {
                        this.errorMessage = err.message || 'An unexpected error occurred while generating the report.';
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>
