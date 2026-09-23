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

<div
    class="rounded-xl border border-neutral-200 bg-white p-2.5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 print:hidden"
    x-data="{ openDropdown: null }"
    @keydown.escape.window="openDropdown = null"
>
    {{-- Mobile / Small Screen Quick Selector (< sm) --}}
    <div class="sm:hidden space-y-2">
        <div>
            <label for="logistics-mobile-tab-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 mb-1.5">
                DTRS Workflows:
            </label>
            <select
                id="logistics-mobile-tab-select"
                onchange="if (this.value) window.location.href = this.value;"
                class="block w-full rounded-lg border border-neutral-300 py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
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

        @canany([\App\Enums\Permission::ManageLogisticsRecords->value, \App\Enums\Permission::ViewLogisticsSensitiveData->value])
            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-neutral-100 dark:border-neutral-800">
                @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        :href="route('inventory.logistics.shipments')"
                        @click="if (window.location.pathname.includes('/inventory/logistics/shipments')) { $event.preventDefault(); $dispatch('open-shipment-modal'); }"
                        icon="plus"
                        class="flex-1"
                    >
                        New Shipment
                    </x-ui.button>
                @endcan
                @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                    <x-ui.button variant="secondary" size="sm" :href="route('inventory.logistics.iar.index')" icon="clipboard-document-check" class="flex-1">
                        IAR Processing
                    </x-ui.button>
                @endcan
            </div>
        @endcanany
    </div>

    {{-- Desktop & Tablet Major Dropdowns + Action Buttons (>= sm) --}}
    <div class="hidden sm:flex sm:items-center sm:justify-between sm:gap-4 flex-wrap text-xs">
        <div class="flex items-center gap-2 flex-wrap">
            {{-- 1. Operations & Freight --}}
            @canany([\App\Enums\Permission::ViewLogisticsRecords->value, \App\Enums\Permission::ViewLogisticsSensitiveData->value])
                <div class="relative" @click.outside="if (openDropdown === 'ops') openDropdown = null">
                    <button
                        type="button"
                        @click="openDropdown = openDropdown === 'ops' ? null : 'ops'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isOpsActive ? 'bg-primary-50 text-primary-800 border-primary-200 ring-1 ring-primary-500/20 dark:bg-primary-950/60 dark:text-primary-300 dark:border-primary-700/60' : 'bg-white text-neutral-700 border-neutral-200 hover:bg-neutral-50 hover:text-neutral-900 dark:bg-neutral-800 dark:text-neutral-200 dark:border-neutral-700 dark:hover:bg-neutral-750 dark:hover:text-neutral-100' }}"
                    >
                        <x-ui.icon name="truck" class="h-4 w-4 {{ $isOpsActive ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-500 dark:text-neutral-400' }}" />
                        <span>Operations &amp; Freight</span>
                        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 dark:text-neutral-500 transition-transform duration-200" ::class="openDropdown === 'ops' ? 'rotate-180' : ''" />
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
                        class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        @can(\App\Enums\Permission::ViewLogisticsRecords->value)
                            <a
                                href="{{ route('inventory.logistics') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="chart-bar" class="w-4 h-4 {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>Overview Dashboard</span>
                                </span>
                                @if(request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>
                        @endcan

                        @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                            <a
                                href="{{ route('inventory.logistics.documents') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.documents*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="document-text" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.documents*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>Documents Registry</span>
                                </span>
                                @if(request()->routeIs('inventory.logistics.documents*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>

                            <a
                                href="{{ route('inventory.logistics.shipments') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.shipments*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="truck" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.shipments*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>Shipments &amp; 3PL Logistics</span>
                                </span>
                                @if(request()->routeIs('inventory.logistics.shipments*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>

                            <a
                                href="{{ route('inventory.logistics.chain-of-custody') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="clipboard-document-list" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>Chain of Custody Ledger</span>
                                </span>
                                @if(request()->routeIs('inventory.logistics.chain-of-custody*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>
                        @endcan
                    </div>
                </div>
            @endcanany

            {{-- 2. Compliance & Governance --}}
            @canany([\App\Enums\Permission::ViewLogisticsSensitiveData->value, \App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewProcessReviews->value])
                <div class="relative" @click.outside="if (openDropdown === 'gov') openDropdown = null">
                    <button
                        type="button"
                        @click="openDropdown = openDropdown === 'gov' ? null : 'gov'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isGovActive ? 'bg-primary-50 text-primary-800 border-primary-200 ring-1 ring-primary-500/20 dark:bg-primary-950/60 dark:text-primary-300 dark:border-primary-700/60' : 'bg-white text-neutral-700 border-neutral-200 hover:bg-neutral-50 hover:text-neutral-900 dark:bg-neutral-800 dark:text-neutral-200 dark:border-neutral-700 dark:hover:bg-neutral-750 dark:hover:text-neutral-100' }}"
                    >
                        <x-ui.icon name="shield-check" class="h-4 w-4 {{ $isGovActive ? 'text-purple-600 dark:text-purple-400' : 'text-neutral-500 dark:text-neutral-400' }}" />
                        <span>Compliance &amp; Governance</span>
                        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 dark:text-neutral-500 transition-transform duration-200" ::class="openDropdown === 'gov' ? 'rotate-180' : ''" />
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
                        class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                            <a
                                href="{{ route('inventory.logistics.iar.index') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.iar*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="clipboard-document-check" class="w-4 h-4 {{ request()->routeIs('inventory.logistics.iar*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>COA IAR Reports (App. 50)</span>
                                </span>
                                @if(request()->routeIs('inventory.logistics.iar*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>
                        @endcan

                        @can(\App\Enums\Permission::ViewReports->value)
                            <a
                                href="{{ route('inventory.reports') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.reports*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="document-duplicate" class="w-4 h-4 {{ request()->routeIs('inventory.reports*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>Reports &amp; Analytics</span>
                                </span>
                                @if(request()->routeIs('inventory.reports*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>
                        @endcan

                        @can(\App\Enums\Permission::ViewProcessReviews->value)
                            <a
                                href="{{ route('reviews.index') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('reviews.index', 'reviews.show', 'reviews.create') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="check-circle" class="w-4 h-4 {{ request()->routeIs('reviews.index', 'reviews.show', 'reviews.create') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>Process Reviews</span>
                                </span>
                                @if(request()->routeIs('reviews.index', 'reviews.show', 'reviews.create'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>

                            <a
                                href="{{ route('reviews.dpri') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('reviews.dpri*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-300' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="currency-dollar" class="w-4 h-4 {{ request()->routeIs('reviews.dpri*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                    <span>DOH DPRI Reference Prices</span>
                                </span>
                                @if(request()->routeIs('reviews.dpri*'))
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                                @endif
                            </a>
                        @endcan
                    </div>
                </div>
            @endcanany
        </div>

        {{-- Action Buttons --}}
        @canany([\App\Enums\Permission::ManageLogisticsRecords->value, \App\Enums\Permission::ViewLogisticsSensitiveData->value])
            <div class="flex items-center gap-2 flex-wrap">
                @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        :href="route('inventory.logistics.shipments')"
                        @click="if (window.location.pathname.includes('/inventory/logistics/shipments')) { $event.preventDefault(); $dispatch('open-shipment-modal'); }"
                        icon="plus"
                    >
                        New Shipment
                    </x-ui.button>
                @endcan
                @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                    <x-ui.button variant="secondary" size="sm" :href="route('inventory.logistics.iar.index')" icon="clipboard-document-check">
                        IAR Processing
                    </x-ui.button>
                @endcan
            </div>
        @endcanany
    </div>
</div>
