<div class="border-b border-neutral-200/80 bg-white/95 backdrop-blur-xs print:hidden" x-data="{ openDropdown: null }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-2">
        {{-- Mobile / Small Screen Quick Selector (< sm) --}}
        <div class="sm:hidden">
            <label for="logistics-mobile-tab-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                Logistics Module:
            </label>
            <select
                id="logistics-mobile-tab-select"
                onchange="if (this.value) window.location.href = this.value;"
                class="block w-full rounded-lg border border-neutral-300 py-2 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
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

        {{-- Desktop & Tablet Integrated Segmented Navigation (>= sm) --}}
        <div class="hidden sm:flex sm:items-center sm:justify-between">
            @php
                $isOpsActive = (request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*')) || request()->routeIs('inventory.logistics.shipments*');
                $isGovActive = request()->routeIs('inventory.logistics.documents*', 'inventory.logistics.iar*', 'inventory.logistics.chain-of-custody*');
            @endphp

            <div class="inline-flex items-center gap-1 rounded-xl bg-neutral-100/90 p-1 border border-neutral-200/80 shadow-2xs text-xs">
                {{-- Segment 1: Operations --}}
                <div class="relative" @click.outside="if (openDropdown === 'ops') openDropdown = null">
                    <button
                        type="button"
                        @click="openDropdown = openDropdown === 'ops' ? null : 'ops'"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                        :class="{{ $isOpsActive ? 'true' : 'false' }}
                            ? 'bg-white text-neutral-900 shadow-xs ring-1 ring-black/5 font-semibold'
                            : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-200/50 font-medium'"
                    >
                        <svg class="h-3.5 w-3.5 {{ $isOpsActive ? 'text-primary-600' : 'text-neutral-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        <span>Operations &amp; Freight</span>
                        <svg class="h-3 w-3 text-neutral-400 transition-transform duration-150" :class="openDropdown === 'ops' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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
                        class="absolute left-0 z-40 mt-1.5 w-56 rounded-xl border border-neutral-200 bg-white p-1 shadow-lg ring-1 ring-black/5 space-y-0.5"
                    >
                        <a
                            href="{{ route('inventory.logistics') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                        >
                            <span>Overview Dashboard</span>
                            @if(request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*'))
                                <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </a>
                        <a
                            href="{{ route('inventory.logistics.shipments') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.shipments*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                        >
                            <span>Shipments &amp; 3PL Logistics</span>
                            @if(request()->routeIs('inventory.logistics.shipments*'))
                                <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </a>
                    </div>
                </div>

                {{-- Segment 2: Compliance & Governance --}}
                @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                    <div class="relative" @click.outside="if (openDropdown === 'gov') openDropdown = null">
                        <button
                            type="button"
                            @click="openDropdown = openDropdown === 'gov' ? null : 'gov'"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                            :class="{{ $isGovActive ? 'true' : 'false' }}
                                ? 'bg-white text-neutral-900 shadow-xs ring-1 ring-black/5 font-semibold'
                                : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-200/50 font-medium'"
                        >
                            <svg class="h-3.5 w-3.5 {{ $isGovActive ? 'text-emerald-600' : 'text-neutral-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                            <span>Compliance &amp; Governance</span>
                            <svg class="h-3 w-3 text-neutral-400 transition-transform duration-150" :class="openDropdown === 'gov' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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
                            <a
                                href="{{ route('inventory.logistics.documents') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.documents*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                            >
                                <span>Documents Registry</span>
                                @if(request()->routeIs('inventory.logistics.documents*'))
                                    <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </a>
                            <a
                                href="{{ route('inventory.logistics.iar.index') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.iar*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                            >
                                <span>COA IAR Reports (App. 50)</span>
                                @if(request()->routeIs('inventory.logistics.iar*'))
                                    <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </a>
                            <a
                                href="{{ route('inventory.logistics.chain-of-custody') }}"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-neutral-700 hover:bg-neutral-50 font-medium' }}"
                            >
                                <span>Chain of Custody Ledger</span>
                                @if(request()->routeIs('inventory.logistics.chain-of-custody*'))
                                    <svg class="h-3.5 w-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </a>
                        </div>
                    </div>
                @endcan
            </div>

            {{-- Context Active Sub-Page Pill on Right --}}
            <div class="hidden lg:flex items-center gap-1.5 text-xs text-neutral-500">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span>Active view:</span>
                <span class="font-semibold text-neutral-800">
                    @if(request()->routeIs('inventory.logistics.documents*'))
                        Documents Registry
                    @elseif(request()->routeIs('inventory.logistics.iar*'))
                        COA IAR Reports
                    @elseif(request()->routeIs('inventory.logistics.chain-of-custody*'))
                        Chain of Custody Ledger
                    @elseif(request()->routeIs('inventory.logistics.shipments*'))
                        Shipments &amp; 3PL Logistics
                    @else
                        Overview Dashboard
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>
