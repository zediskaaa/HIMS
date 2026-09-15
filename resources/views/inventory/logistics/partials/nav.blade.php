@php
    $isOpsActive = (request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*'))
        || request()->routeIs('inventory.logistics.documents*')
        || request()->routeIs('inventory.logistics.shipments*')
        || request()->routeIs('inventory.logistics.chain-of-custody*')
        || request()->routeIs('inventory.logistics.ris*');

    $isGovActive = request()->routeIs('inventory.logistics.iar*')
        || request()->routeIs('inventory.reports*')
        || request()->routeIs('reviews.*');
@endphp

<div class="border-b border-neutral-200/80 bg-white/95 backdrop-blur-xs print:hidden" x-data="{ openDropdown: null }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-2.5">
        {{-- Mobile / Small Screen Quick Selector (< sm) --}}
        <div class="sm:hidden">
            <label for="logistics-mobile-tab-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                DTRS Workflows:
            </label>
            <select
                id="logistics-mobile-tab-select"
                onchange="if (this.value) window.location.href = this.value;"
                class="block w-full rounded-lg border border-neutral-300 py-2 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
            >
                <option value="">Jump to DTRS workflow...</option>

                @canany([\App\Enums\Permission::ViewLogisticsRecords->value, \App\Enums\Permission::ViewLogisticsSensitiveData->value])
                    <optgroup label="Operations &amp; Freight">
                        @can(\App\Enums\Permission::ViewLogisticsRecords->value)
                            <option value="{{ route('inventory.logistics') }}" @selected(request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*'))>
                                Overview Dashboard
                            </option>
                        @endcan
                        @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                            <option value="{{ route('inventory.logistics.documents') }}" @selected(request()->routeIs('inventory.logistics.documents*'))>
                                Documents Registry
                            </option>
                            <option value="{{ route('inventory.logistics.shipments') }}" @selected(request()->routeIs('inventory.logistics.shipments*'))>
                                Shipments &amp; 3PL Logistics
                            </option>
                            <option value="{{ route('inventory.logistics.chain-of-custody') }}" @selected(request()->routeIs('inventory.logistics.chain-of-custody*'))>
                                Chain of Custody Ledger
                            </option>
                        @endcan
                    </optgroup>
                @endcanany

                @canany([\App\Enums\Permission::ViewLogisticsSensitiveData->value, \App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewProcessReviews->value])
                    <optgroup label="Compliance &amp; Governance">
                        @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                            <option value="{{ route('inventory.logistics.iar.index') }}" @selected(request()->routeIs('inventory.logistics.iar*'))>
                                COA IAR Reports (App. 50)
                            </option>
                        @endcan
                        @can(\App\Enums\Permission::ViewReports->value)
                            <option value="{{ route('inventory.reports') }}" @selected(request()->routeIs('inventory.reports*'))>
                                Reports &amp; Analytics
                            </option>
                        @endcan
                        @can(\App\Enums\Permission::ViewProcessReviews->value)
                            <option value="{{ route('reviews.index') }}" @selected(request()->routeIs('reviews.index', 'reviews.show', 'reviews.create'))>
                                Process Reviews
                            </option>
                            <option value="{{ route('reviews.dpri') }}" @selected(request()->routeIs('reviews.dpri*'))>
                                DOH DPRI Reference Prices
                            </option>
                        @endcan
                    </optgroup>
                @endcanany
            </select>
        </div>

        {{-- Desktop & Tablet Integrated Segmented Navigation (>= sm) --}}
        <div class="hidden sm:flex sm:items-center sm:justify-between">
            <div class="inline-flex items-center gap-1 rounded-xl bg-neutral-100/90 p-1 border border-neutral-200/80 shadow-2xs text-xs">
                {{-- Segment 1: Operations & Freight --}}
                @canany([\App\Enums\Permission::ViewLogisticsRecords->value, \App\Enums\Permission::ViewLogisticsSensitiveData->value])
                    <div class="relative" @click.outside="if (openDropdown === 'ops') openDropdown = null">
                        <button
                            type="button"
                            @click="openDropdown = openDropdown === 'ops' ? null : 'ops'"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $isOpsActive ? 'bg-white text-neutral-900 shadow-xs ring-1 ring-black/5 font-semibold' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-200/50 font-medium' }}"
                        >
                            <x-ui.icon name="truck" class="h-3.5 w-3.5 {{ $isOpsActive ? 'text-primary-600' : 'text-neutral-400' }}" />
                            <span>Operations &amp; Freight</span>
                            <x-ui.icon name="chevron-down" class="h-3 w-3 text-neutral-400 transition-transform duration-150" ::class="openDropdown === 'ops' ? 'rotate-180' : ''" />
                        </button>

                        <div
                            x-show="openDropdown === 'ops'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                            class="absolute left-0 z-40 mt-1.5 w-60 rounded-xl border border-neutral-200 bg-white p-1 shadow-lg ring-1 ring-black/5 space-y-0.5"
                        >
                            @can(\App\Enums\Permission::ViewLogisticsRecords->value)
                                <a
                                    href="{{ route('inventory.logistics') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="chart-bar" class="w-4 h-4 {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>Overview Dashboard</span>
                                    </span>
                                    @if(request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>
                            @endcan

                            @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                                <a
                                    href="{{ route('inventory.logistics.documents') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.documents*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="document-text" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.documents*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>Documents Registry</span>
                                    </span>
                                    @if(request()->routeIs('inventory.logistics.documents*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>

                                <a
                                    href="{{ route('inventory.logistics.shipments') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.shipments*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="truck" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.shipments*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>Shipments &amp; 3PL Logistics</span>
                                    </span>
                                    @if(request()->routeIs('inventory.logistics.shipments*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>

                                <a
                                    href="{{ route('inventory.logistics.chain-of-custody') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="clipboard-document-list" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>Chain of Custody Ledger</span>
                                    </span>
                                    @if(request()->routeIs('inventory.logistics.chain-of-custody*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- Segment 2: Compliance & Governance --}}
                @canany([\App\Enums\Permission::ViewLogisticsSensitiveData->value, \App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewProcessReviews->value])
                    <div class="relative" @click.outside="if (openDropdown === 'gov') openDropdown = null">
                        <button
                            type="button"
                            @click="openDropdown = openDropdown === 'gov' ? null : 'gov'"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $isGovActive ? 'bg-white text-neutral-900 shadow-xs ring-1 ring-black/5 font-semibold' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-200/50 font-medium' }}"
                        >
                            <x-ui.icon name="shield-check" class="h-3.5 w-3.5 {{ $isGovActive ? 'text-emerald-600' : 'text-neutral-400' }}" />
                            <span>Compliance &amp; Governance</span>
                            <x-ui.icon name="chevron-down" class="h-3 w-3 text-neutral-400 transition-transform duration-150" ::class="openDropdown === 'gov' ? 'rotate-180' : ''" />
                        </button>

                        <div
                            x-show="openDropdown === 'gov'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                            class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1 shadow-lg ring-1 ring-black/5 space-y-0.5"
                        >
                            @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                                <a
                                    href="{{ route('inventory.logistics.iar.index') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.iar*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="clipboard-document-check" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.iar*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>COA IAR Reports (App. 50)</span>
                                    </span>
                                    @if(request()->routeIs('inventory.logistics.iar*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>
                            @endcan

                            @can(\App\Enums\Permission::ViewReports->value)
                                <a
                                    href="{{ route('inventory.reports') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.reports*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="document-duplicate" class="w-4 h-4 {{ request()->routeIs('inventory.reports*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>Reports &amp; Analytics</span>
                                    </span>
                                    @if(request()->routeIs('inventory.reports*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>
                            @endcan

                            @can(\App\Enums\Permission::ViewProcessReviews->value)
                                <a
                                    href="{{ route('reviews.index') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('reviews.index', 'reviews.show', 'reviews.create') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="check-circle" class="w-4 h-4 {{ request()->routeIs('reviews.index', 'reviews.show', 'reviews.create') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>Process Reviews</span>
                                    </span>
                                    @if(request()->routeIs('reviews.index', 'reviews.show', 'reviews.create'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>

                                <a
                                    href="{{ route('reviews.dpri') }}"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('reviews.dpri*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="currency-dollar" class="w-4 h-4 {{ request()->routeIs('reviews.dpri*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                        <span>DOH DPRI Reference Prices</span>
                                    </span>
                                    @if(request()->routeIs('reviews.dpri*'))
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    @endif
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany
            </div>

            {{-- Context Active Sub-Page Pill on Right --}}
            <div class="hidden lg:flex items-center gap-1.5 text-xs text-neutral-500">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span>Active view:</span>
                <span class="font-semibold text-neutral-800">
                    @if(request()->routeIs('inventory.logistics.documents*'))
                        Documents Registry
                    @elseif(request()->routeIs('inventory.logistics.shipments*'))
                        Shipments &amp; 3PL Logistics
                    @elseif(request()->routeIs('inventory.logistics.chain-of-custody*'))
                        Chain of Custody Ledger
                    @elseif(request()->routeIs('inventory.logistics.iar*'))
                        COA IAR Reports
                    @elseif(request()->routeIs('inventory.reports*'))
                        Reports &amp; Analytics
                    @elseif(request()->routeIs('reviews.dpri*'))
                        DOH DPRI Reference Prices
                    @elseif(request()->routeIs('reviews.*'))
                        Process Reviews
                    @else
                        Overview Dashboard
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>
