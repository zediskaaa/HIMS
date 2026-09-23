@php
    $isOperationsActive = request()->routeIs(
        'inventory.warehousing.dashboard',
        'inventory.warehousing.scan-station',
        'inventory.receiving*',
        'inventory.qc*',
        'inventory.warehouse-tasks*'
    );

    $isLocationsActive = request()->routeIs(
        'inventory.warehousing.locations',
        'inventory.storage-locations*'
    );

    $isComplianceActive = request()->routeIs(
        'inventory.warehousing.narcotics*',
        'inventory.warehousing.consignment*'
    );
@endphp

<div
    class="rounded-xl border border-neutral-200 bg-white p-2.5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
    x-data="{ openDropdown: null }"
    @keydown.escape.window="openDropdown = null"
>
    {{-- Mobile Screen Selector (< sm) --}}
    <div class="sm:hidden space-y-2">
        <div>
            <label for="warehousing-workflow-mobile-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 mb-1.5">
                Smart Warehousing Workflows:
            </label>
            <select
                id="warehousing-workflow-mobile-select"
                onchange="if (this.value) window.location.href = this.value;"
                class="block w-full rounded-lg border border-neutral-300 bg-white py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
            >
                <option value="">Jump to warehousing workflow...</option>

            @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ExecuteWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::InspectStock->value])
                <optgroup label="Warehouse Operations">
                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <option value="{{ route('inventory.warehousing.dashboard') }}" @selected(request()->routeIs('inventory.warehousing.dashboard'))>Warehouse Dashboard</option>
                    @endcan
                    @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                        <option value="{{ route('inventory.receiving.index') }}" @selected(request()->routeIs('inventory.receiving*'))>Dock Receiving</option>
                    @endcan
                    @can(\App\Enums\Permission::InspectStock->value)
                        <option value="{{ route('inventory.qc.index') }}" @selected(request()->routeIs('inventory.qc*'))>QC Inspection</option>
                    @endcan
                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <option value="{{ route('inventory.warehouse-tasks.index') }}" @selected(request()->routeIs('inventory.warehouse-tasks*'))>Warehouse Tasks</option>
                    @endcan
                </optgroup>
            @endcanany

            @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ViewInventory->value])
                <optgroup label="Locations &amp; Storage">
                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                        <option value="{{ route('inventory.warehousing.locations') }}" @selected(request()->routeIs('inventory.warehousing.locations'))>Spatial Topology &amp; Locations</option>
                    @endcanany
                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewInventory->value])
                        <option value="{{ route('inventory.storage-locations') }}" @selected(request()->routeIs('inventory.storage-locations*'))>Location Registry</option>
                    @endcanany
                </optgroup>
            @endcanany

            @canany([\App\Enums\Permission::AccessNarcoticsVault->value, \App\Enums\Permission::RecordConsignments->value])
                <optgroup label="Compliance &amp; Special Handling">
                    @can(\App\Enums\Permission::AccessNarcoticsVault->value)
                        <option value="{{ route('inventory.warehousing.narcotics') }}" @selected(request()->routeIs('inventory.warehousing.narcotics*'))>PDEA Narcotics Vault</option>
                    @endcan
                    @can(\App\Enums\Permission::RecordConsignments->value)
                        <option value="{{ route('inventory.warehousing.consignment') }}" @selected(request()->routeIs('inventory.warehousing.consignment*'))>Consignments &amp; Implants</option>
                    @endcan
                </optgroup>
            @endcanany
        </select>
        </div>

        @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
            <div class="pt-1">
                <a
                    href="{{ route('inventory.warehousing.scan-station', ['camera' => 1]) }}"
                    @click="if (window.location.pathname.includes('/inventory/warehousing/scan-station')) { $event.preventDefault(); window.dispatchEvent(new CustomEvent('open-camera-scanner-camera-scanner-standby')); }"
                    title="Scan Workstation"
                    class="flex items-center justify-center gap-2 w-full rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700"
                >
                    <x-ui.icon name="qr-code" class="w-4 h-4" />
                    <span>Launch Scanner</span>
                </a>
            </div>
        @endcan
    </div>

    {{-- Desktop & Tablet Major Dropdown Tabs (>= sm) --}}
    <div class="hidden sm:flex sm:items-center sm:gap-2.5 flex-wrap text-xs">
        {{-- 1. Warehouse Operations --}}
        @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ExecuteWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::InspectStock->value])
            <div class="relative" @click.outside="if (openDropdown === 'operations') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'operations' ? null : 'operations'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isOperationsActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20 dark:border-primary-700 dark:bg-primary-950/60 dark:text-primary-200' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100' }}"
                >
                    <x-ui.icon name="building-storefront" class="h-4 w-4 {{ $isOperationsActive ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-500 dark:text-neutral-400' }}" />
                    <span>Warehouse Operations</span>
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'operations' ? 'rotate-180' : ''" />
                </button>

                <div
                    x-show="openDropdown === 'operations'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <a
                            href="{{ route('inventory.warehousing.dashboard') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.dashboard') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="chart-bar" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.dashboard') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>Warehouse Dashboard</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.dashboard'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                        <a
                            href="{{ route('inventory.receiving.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.receiving*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="arrow-down-tray" class="w-4 h-4 {{ request()->routeIs('inventory.receiving*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>Dock Receiving</span>
                            </span>
                            @if (request()->routeIs('inventory.receiving*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::InspectStock->value)
                        <a
                            href="{{ route('inventory.qc.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.qc*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="shield-check" class="w-4 h-4 {{ request()->routeIs('inventory.qc*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>QC Inspection</span>
                            </span>
                            @if (request()->routeIs('inventory.qc*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <a
                            href="{{ route('inventory.warehouse-tasks.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehouse-tasks*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="clipboard-document-list" class="w-4 h-4 {{ request()->routeIs('inventory.warehouse-tasks*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>Warehouse Tasks</span>
                            </span>
                            @if (request()->routeIs('inventory.warehouse-tasks*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        {{-- 2. Locations & Storage --}}
        @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ViewInventory->value])
            <div class="relative" @click.outside="if (openDropdown === 'locations') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'locations' ? null : 'locations'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isLocationsActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20 dark:border-primary-700 dark:bg-primary-950/60 dark:text-primary-200' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100' }}"
                >
                    <x-ui.icon name="map-pin" class="h-4 w-4 {{ $isLocationsActive ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-500 dark:text-neutral-400' }}" />
                    <span>Locations &amp; Storage</span>
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'locations' ? 'rotate-180' : ''" />
                </button>

                <div
                    x-show="openDropdown === 'locations'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                        <a
                            href="{{ route('inventory.warehousing.locations') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.locations') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="map-pin" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.locations') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>Spatial Topology &amp; Locations</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.locations'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcanany

                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewInventory->value])
                        <a
                            href="{{ route('inventory.storage-locations') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.storage-locations*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="cube" class="w-4 h-4 {{ request()->routeIs('inventory.storage-locations*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>Location Registry</span>
                            </span>
                            @if (request()->routeIs('inventory.storage-locations*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcanany
                </div>
            </div>
        @endcanany

        {{-- 3. Compliance & Special Handling --}}
        @canany([\App\Enums\Permission::AccessNarcoticsVault->value, \App\Enums\Permission::RecordConsignments->value])
            <div class="relative" @click.outside="if (openDropdown === 'compliance') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'compliance' ? null : 'compliance'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isComplianceActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20 dark:border-primary-700 dark:bg-primary-950/60 dark:text-primary-200' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100' }}"
                >
                    <x-ui.icon name="shield-check" class="h-4 w-4 {{ $isComplianceActive ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-500 dark:text-neutral-400' }}" />
                    <span>Compliance &amp; Special Handling</span>
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'compliance' ? 'rotate-180' : ''" />
                </button>

                <div
                    x-show="openDropdown === 'compliance'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    @can(\App\Enums\Permission::AccessNarcoticsVault->value)
                        <a
                            href="{{ route('inventory.warehousing.narcotics') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.narcotics*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="lock-closed" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.narcotics*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>PDEA Narcotics Vault</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.narcotics*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::RecordConsignments->value)
                        <a
                            href="{{ route('inventory.warehousing.consignment') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.consignment*') ? 'bg-primary-50 text-primary-800 font-semibold dark:bg-primary-950/60 dark:text-primary-200' : 'text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="clipboard-document-check" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.consignment*') ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500' }}" />
                                <span>Consignments &amp; Implants</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.consignment*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        {{-- Quick Action: Launch Scanner --}}
        @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
            <div class="sm:ml-auto">
                <a
                    href="{{ route('inventory.warehousing.scan-station', ['camera' => 1]) }}"
                    @click="if (window.location.pathname.includes('/inventory/warehousing/scan-station')) { $event.preventDefault(); window.dispatchEvent(new CustomEvent('open-camera-scanner-camera-scanner-standby')); }"
                    title="Scan Workstation"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 font-semibold text-white shadow-2xs hover:bg-primary-700 transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
                >
                    <x-ui.icon name="qr-code" class="w-4 h-4" />
                    <span>Launch Scanner</span>
                </a>
            </div>
        @endcan
    </div>
</div>
