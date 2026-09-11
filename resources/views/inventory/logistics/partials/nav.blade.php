<div class="border-b border-neutral-200 bg-white" x-data="{ openDropdown: null }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-2.5">
        {{-- Mobile / Small Screen Quick Selector (< sm) --}}
        <div class="sm:hidden">
            <label for="logistics-mobile-tab-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                Logistics Module:
            </label>
            <select
                id="logistics-mobile-tab-select"
                onchange="if (this.value) window.location.href = this.value;"
                class="block w-full rounded-lg border border-neutral-300 py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
            >
                <optgroup label="Operations">
                    <option value="{{ route('inventory.logistics') }}" @selected(request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*'))>
                        Overview Dashboard
                    </option>
                    <option value="{{ route('inventory.logistics.shipments') }}" @selected(request()->routeIs('inventory.logistics.shipments*'))>
                        Shipments &amp; 3PL Logistics
                    </option>
                </optgroup>
                @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                    <optgroup label="Compliance &amp; Governance">
                        <option value="{{ route('inventory.logistics.documents') }}" @selected(request()->routeIs('inventory.logistics.documents*'))>
                            Documents Registry
                        </option>
                        <option value="{{ route('inventory.logistics.iar.index') }}" @selected(request()->routeIs('inventory.logistics.iar*'))>
                            COA IAR Reports (App. 50)
                        </option>
                        <option value="{{ route('inventory.logistics.chain-of-custody') }}" @selected(request()->routeIs('inventory.logistics.chain-of-custody*'))>
                            Chain of Custody Ledger
                        </option>
                    </optgroup>
                @endcan
            </select>
        </div>

        {{-- Desktop & Tablet Major Dropdown Tabs (>= sm) --}}
        <div class="hidden sm:flex sm:items-center sm:gap-3 flex-wrap text-xs">
            @php
                $isOpsActive = (request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*')) || request()->routeIs('inventory.logistics.shipments*');
                $isGovActive = request()->routeIs('inventory.logistics.documents*', 'inventory.logistics.iar*', 'inventory.logistics.chain-of-custody*');
            @endphp

            {{-- Major Tab 1: Operations --}}
            <div class="relative" @click.outside="if (openDropdown === 'ops') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'ops' ? null : 'ops'"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-semibold transition shadow-2xs"
                    :class="{{ $isOpsActive ? 'true' : 'false' }}
                        ? 'bg-primary-50 text-primary-700 border border-primary-200'
                        : 'bg-neutral-50 text-neutral-700 border border-neutral-200 hover:bg-neutral-100'"
                >
                    <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Operations &amp; Freight</span>
                    <svg class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" :class="openDropdown === 'ops' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div
                    x-show="openDropdown === 'ops'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-1.5 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in-out duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1.5 scale-95"
                    class="absolute left-0 z-40 mt-1.5 w-60 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-1"
                >
                    <a
                        href="{{ route('inventory.logistics') }}"
                        class="block px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'bg-primary-50 text-primary-700 font-bold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                    >
                        Overview Dashboard
                    </a>
                    <a
                        href="{{ route('inventory.logistics.shipments') }}"
                        class="block px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.shipments*') ? 'bg-primary-50 text-primary-700 font-bold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                    >
                        Shipments &amp; 3PL Logistics
                    </a>
                </div>
            </div>

            {{-- Major Tab 2: Compliance & Governance --}}
            @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                <div class="relative" @click.outside="if (openDropdown === 'gov') openDropdown = null">
                    <button
                        type="button"
                        @click="openDropdown = openDropdown === 'gov' ? null : 'gov'"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-semibold transition shadow-2xs"
                        :class="{{ $isGovActive ? 'true' : 'false' }}
                            ? 'bg-primary-50 text-primary-700 border border-primary-200'
                            : 'bg-neutral-50 text-neutral-700 border border-neutral-200 hover:bg-neutral-100'"
                    >
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Compliance &amp; Governance</span>
                        <svg class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" :class="openDropdown === 'gov' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div
                        x-show="openDropdown === 'gov'"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1.5 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in-out duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-y-1.5 scale-95"
                        class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-1"
                    >
                        <a
                            href="{{ route('inventory.logistics.documents') }}"
                            class="block px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.documents*') ? 'bg-primary-50 text-primary-700 font-bold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            Documents Registry
                        </a>
                        <a
                            href="{{ route('inventory.logistics.iar.index') }}"
                            class="block px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.iar*') ? 'bg-primary-50 text-primary-700 font-bold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            COA IAR Reports (App. 50)
                        </a>
                        <a
                            href="{{ route('inventory.logistics.chain-of-custody') }}"
                            class="block px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'bg-primary-50 text-primary-700 font-bold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            Chain of Custody Ledger
                        </a>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
