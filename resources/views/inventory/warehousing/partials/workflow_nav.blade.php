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
        'inventory.storage-locations*',
        'inventory.warehousing.telemetry*'
    );

    $isComplianceActive = request()->routeIs(
        'inventory.warehousing.narcotics*',
        'inventory.warehousing.consignment*'
    );
@endphp

<div
    x-data="{ openDropdown: null }"
    @keydown.escape.window="openDropdown = null"
    class="rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-sm"
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 pb-3 border-b border-neutral-100">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500">Smart Warehousing Workflows &amp; Operations</h3>
            <p class="text-xs text-neutral-600">Access warehouse execution, spatial topology, cold-chain telemetry, and compliance vaults.</p>
        </div>
    </div>

    {{-- Mobile Screen Selector (< sm) --}}
    <div class="mt-3 sm:hidden">
        <label for="warehousing-workflow-mobile-select" class="sr-only">Choose Warehousing Workflow</label>
        <select
            id="warehousing-workflow-mobile-select"
            onchange="if (this.value) window.location.href = this.value;"
            class="block w-full rounded-lg border border-neutral-300 py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
        >
            <option value="">Jump to warehousing workflow...</option>

            @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ExecuteWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::InspectStock->value])
                <optgroup label="Warehouse Operations">
                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <option value="{{ route('inventory.warehousing.dashboard') }}" @selected(request()->routeIs('inventory.warehousing.dashboard'))>Warehouse Dashboard</option>
                    @endcan
                    @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
                        <option value="{{ route('inventory.warehousing.scan-station') }}" @selected(request()->routeIs('inventory.warehousing.scan-station'))>Scan Workstation</option>
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

            @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::ManageTelemetryExcursions->value])
                <optgroup label="Locations &amp; Storage">
                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                        <option value="{{ route('inventory.warehousing.locations') }}" @selected(request()->routeIs('inventory.warehousing.locations'))>Locations Explorer</option>
                    @endcanany
                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewInventory->value])
                        <option value="{{ route('inventory.storage-locations') }}" @selected(request()->routeIs('inventory.storage-locations*'))>Location Registry</option>
                    @endcanany
                    @can(\App\Enums\Permission::ManageTelemetryExcursions->value)
                        <option value="{{ route('inventory.warehousing.telemetry') }}" @selected(request()->routeIs('inventory.warehousing.telemetry*'))>IoT Telemetry Monitor</option>
                    @endcan
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

    {{-- Desktop & Tablet Major Dropdown Tabs (>= sm) --}}
    <div class="hidden sm:flex sm:items-center sm:gap-2.5 flex-wrap text-xs mt-3">
        {{-- 1. Warehouse Operations --}}
        @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ExecuteWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::InspectStock->value])
            <div class="relative" @click.outside="if (openDropdown === 'operations') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'operations' ? null : 'operations'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isOperationsActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900' }}"
                >
                    <x-ui.icon name="building-storefront" class="h-4 w-4 {{ $isOperationsActive ? 'text-primary-600' : 'text-neutral-500' }}" />
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
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                >
                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <a
                            href="{{ route('inventory.warehousing.dashboard') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.dashboard') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="chart-bar" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.dashboard') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Warehouse Dashboard</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.dashboard'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
                        <a
                            href="{{ route('inventory.warehousing.scan-station') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.scan-station') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="qr-code" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.scan-station') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Scan Workstation</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.scan-station'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                        <a
                            href="{{ route('inventory.receiving.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.receiving*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="arrow-down-tray" class="w-4 h-4 {{ request()->routeIs('inventory.receiving*') ? 'text-primary-600' : 'text-neutral-400' }}" />
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
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.qc*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="shield-check" class="w-4 h-4 {{ request()->routeIs('inventory.qc*') ? 'text-primary-600' : 'text-neutral-400' }}" />
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
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehouse-tasks*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="clipboard-document-list" class="w-4 h-4 {{ request()->routeIs('inventory.warehouse-tasks*') ? 'text-primary-600' : 'text-neutral-400' }}" />
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
        @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::ManageTelemetryExcursions->value])
            <div class="relative" @click.outside="if (openDropdown === 'locations') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'locations' ? null : 'locations'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isLocationsActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900' }}"
                >
                    <x-ui.icon name="map-pin" class="h-4 w-4 {{ $isLocationsActive ? 'text-primary-600' : 'text-neutral-500' }}" />
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
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                >
                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                        <a
                            href="{{ route('inventory.warehousing.locations') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.locations') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="map-pin" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.locations') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Locations Explorer</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.locations'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcanany

                    @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewInventory->value])
                        <a
                            href="{{ route('inventory.storage-locations') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.storage-locations*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="cube" class="w-4 h-4 {{ request()->routeIs('inventory.storage-locations*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Location Registry</span>
                            </span>
                            @if (request()->routeIs('inventory.storage-locations*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcanany

                    @can(\App\Enums\Permission::ManageTelemetryExcursions->value)
                        <a
                            href="{{ route('inventory.warehousing.telemetry') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.telemetry*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="bolt" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.telemetry*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>IoT Telemetry Monitor</span>
                            </span>
                            @if (request()->routeIs('inventory.warehousing.telemetry*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        {{-- 3. Compliance & Special Handling --}}
        @canany([\App\Enums\Permission::AccessNarcoticsVault->value, \App\Enums\Permission::RecordConsignments->value])
            <div class="relative" @click.outside="if (openDropdown === 'compliance') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'compliance' ? null : 'compliance'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isComplianceActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900' }}"
                >
                    <x-ui.icon name="shield-check" class="h-4 w-4 {{ $isComplianceActive ? 'text-primary-600' : 'text-neutral-500' }}" />
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
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                >
                    @can(\App\Enums\Permission::AccessNarcoticsVault->value)
                        <a
                            href="{{ route('inventory.warehousing.narcotics') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.narcotics*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="lock-closed" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.narcotics*') ? 'text-primary-600' : 'text-neutral-400' }}" />
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
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.warehousing.consignment*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="clipboard-document-check" class="w-4 h-4 {{ request()->routeIs('inventory.warehousing.consignment*') ? 'text-primary-600' : 'text-neutral-400' }}" />
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
    </div>
</div>
