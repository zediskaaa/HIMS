@php
    $openAlertCount = $openAlertCount ?? \App\Models\StockAlert::active()->count();

    $isStockActive = request()->routeIs('inventory.items*', 'inventory.stock-movements*', 'inventory.adjustments*', 'inventory.cycle-counts*', 'inventory.alerts*');
    $isRequisitionActive = request()->routeIs('inventory.requisitions*', 'inventory.transfers*');
    $isAuditsActive = request()->routeIs('inventory.import*', 'inventory.reports*', 'admin.audit-logs*');
@endphp

<div
    class="rounded-xl border border-neutral-200 bg-white p-2.5 shadow-sm"
    x-data="{ openDropdown: null }"
    @keydown.escape.window="openDropdown = null"
>
    {{-- Mobile Screen Selector (< sm) --}}
    <div class="sm:hidden space-y-2">
        <div>
            <label for="inventory-workflow-mobile-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                Inventory Workflows:
            </label>
            <select
                id="inventory-workflow-mobile-select"
                onchange="if (this.value) window.location.href = this.value;"
                class="block w-full rounded-lg border border-neutral-300 py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
            >
                <option value="">Jump to inventory workflow...</option>
                <optgroup label="Stock &amp; Movements">
                    @can(\App\Enums\Permission::ViewInventory->value)
                        <option value="{{ route('inventory.items') }}" @selected(request()->routeIs('inventory.items*'))>Inventory Items</option>
                        <option value="{{ route('inventory.stock-movements') }}" @selected(request()->routeIs('inventory.stock-movements*'))>Stock Movements</option>
                    @endcan
                    @canany([\App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value])
                        <option value="{{ route('inventory.adjustments') }}" @selected(request()->routeIs('inventory.adjustments*'))>Stock Adjustments</option>
                    @endcanany
                    @can(\App\Enums\Permission::PerformCycleCount->value)
                        <option value="{{ route('inventory.cycle-counts.index') }}" @selected(request()->routeIs('inventory.cycle-counts*'))>Cycle Counts</option>
                    @endcan
                    @can(\App\Enums\Permission::AcknowledgeAlerts->value)
                        <option value="{{ route('inventory.alerts') }}" @selected(request()->routeIs('inventory.alerts*'))>Stock Alerts {{ $openAlertCount > 0 ? '('.$openAlertCount.')' : '' }}</option>
                    @endcan
                </optgroup>

                @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value])
                    <optgroup label="Requisitions &amp; Transfers">
                        @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value])
                            <option value="{{ route('inventory.requisitions.index') }}" @selected(request()->routeIs('inventory.requisitions*'))>Store Requisitions</option>
                        @endcanany
                        @can(\App\Enums\Permission::TransferStock->value)
                            <option value="{{ route('inventory.transfers.index') }}" @selected(request()->routeIs('inventory.transfers*'))>Stock Transfers</option>
                        @endcan
                    </optgroup>
                @endcanany

                @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value, \App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewAuditTrail->value])
                    <optgroup label="Audits &amp; Data Operations">
                        @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                            <option value="{{ route('inventory.import.index') }}" @selected(request()->routeIs('inventory.import*'))>Import Data</option>
                        @endcanany
                        @can(\App\Enums\Permission::ViewReports->value)
                            <option value="{{ route('inventory.reports') }}" @selected(request()->routeIs('inventory.reports*'))>Inventory Reports</option>
                        @endcan
                        @can(\App\Enums\Permission::ViewAuditTrail->value)
                            <option value="{{ route('admin.audit-logs.index') }}" @selected(request()->routeIs('admin.audit-logs*'))>Audit Trail</option>
                        @endcan
                    </optgroup>
                @endcanany
            </select>
        </div>
    </div>

    {{-- Desktop & Tablet Major Dropdown Tabs (>= sm) --}}
    <div class="hidden sm:flex sm:items-center sm:gap-2.5 flex-wrap text-xs">
            {{-- 1. Stock & Movements --}}
        @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value, \App\Enums\Permission::PerformCycleCount->value, \App\Enums\Permission::AcknowledgeAlerts->value])
            <div class="relative" @click.outside="if (openDropdown === 'stock') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'stock' ? null : 'stock'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isStockActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900' }}"
                >
                    <x-ui.icon name="chart-bar" class="h-4 w-4 {{ $isStockActive ? 'text-primary-600' : 'text-neutral-500' }}" />
                    <span>Stock &amp; Movements</span>
                    @if ($openAlertCount > 0)
                        <span class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">{{ $openAlertCount }}</span>
                    @endif
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'stock' ? 'rotate-180' : ''" />
                </button>

                <div
                    x-show="openDropdown === 'stock'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                >
                    @can(\App\Enums\Permission::ViewInventory->value)
                        <a
                            href="{{ route('inventory.items') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.items*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="cube" class="w-4 h-4 {{ request()->routeIs('inventory.items*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Inventory Items</span>
                            </span>
                            @if (request()->routeIs('inventory.items*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>

                        <a
                            href="{{ route('inventory.stock-movements') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.stock-movements*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="arrows-right-left" class="w-4 h-4 {{ request()->routeIs('inventory.stock-movements*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Stock Movements</span>
                            </span>
                            @if (request()->routeIs('inventory.stock-movements*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @canany([\App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value])
                        <a
                            href="{{ route('inventory.adjustments') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.adjustments*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="clipboard-document-list" class="w-4 h-4 {{ request()->routeIs('inventory.adjustments*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Stock Adjustments</span>
                            </span>
                            @if (request()->routeIs('inventory.adjustments*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcanany

                    @can(\App\Enums\Permission::PerformCycleCount->value)
                        <a
                            href="{{ route('inventory.cycle-counts.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.cycle-counts*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="clipboard-document-check" class="w-4 h-4 {{ request()->routeIs('inventory.cycle-counts*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Cycle Counts</span>
                            </span>
                            @if (request()->routeIs('inventory.cycle-counts*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::AcknowledgeAlerts->value)
                        <a
                            href="{{ route('inventory.alerts') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.alerts*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="bell-alert" class="w-4 h-4 {{ request()->routeIs('inventory.alerts*') ? 'text-rose-600' : 'text-rose-500' }}" />
                                <span>Stock Alerts</span>
                            </span>
                            @if ($openAlertCount > 0)
                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">{{ $openAlertCount }}</span>
                            @elseif (request()->routeIs('inventory.alerts*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        {{-- 2. Requisitions & Transfers --}}
        @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value])
            <div class="relative" @click.outside="if (openDropdown === 'req') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'req' ? null : 'req'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isRequisitionActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900' }}"
                >
                    <x-ui.icon name="arrows-right-left" class="h-4 w-4 {{ $isRequisitionActive ? 'text-primary-600' : 'text-neutral-500' }}" />
                    <span>Requisitions &amp; Transfers</span>
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'req' ? 'rotate-180' : ''" />
                </button>

                <div
                    x-show="openDropdown === 'req'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                >
                    @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value])
                        <a
                            href="{{ route('inventory.requisitions.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.requisitions*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="document-duplicate" class="w-4 h-4 {{ request()->routeIs('inventory.requisitions*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Store Requisitions</span>
                            </span>
                            @if (request()->routeIs('inventory.requisitions*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcanany

                    @can(\App\Enums\Permission::TransferStock->value)
                        <a
                            href="{{ route('inventory.transfers.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.transfers*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="truck" class="w-4 h-4 {{ request()->routeIs('inventory.transfers*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Stock Transfers</span>
                            </span>
                            @if (request()->routeIs('inventory.transfers*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        {{-- 3. Audits & Data Operations --}}
        @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value, \App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewAuditTrail->value])
            <div class="relative" @click.outside="if (openDropdown === 'ops') openDropdown = null">
                <button
                    type="button"
                    @click="openDropdown = openDropdown === 'ops' ? null : 'ops'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs {{ $isAuditsActive ? 'border-primary-300 bg-primary-50 text-primary-800 ring-1 ring-primary-500/20' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900' }}"
                >
                    <x-ui.icon name="clipboard-document-check" class="h-4 w-4 {{ $isAuditsActive ? 'text-primary-600' : 'text-neutral-500' }}" />
                    <span>Audits &amp; Data Operations</span>
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'ops' ? 'rotate-180' : ''" />
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
                    class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                >
                    @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                        <a
                            href="{{ route('inventory.import.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.import*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="arrow-up-tray" class="w-4 h-4 {{ request()->routeIs('inventory.import*') ? 'text-sky-600' : 'text-neutral-400' }}" />
                                <span>Import Data</span>
                            </span>
                            <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800">CSV/XLS</span>
                        </a>
                    @endcanany

                    @can(\App\Enums\Permission::ViewReports->value)
                        <a
                            href="{{ route('inventory.reports') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('inventory.reports*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="document-chart-bar" class="w-4 h-4 {{ request()->routeIs('inventory.reports*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Inventory Reports</span>
                            </span>
                            @if (request()->routeIs('inventory.reports*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan

                    @can(\App\Enums\Permission::ViewAuditTrail->value)
                        <a
                            href="{{ route('admin.audit-logs.index') }}"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('admin.audit-logs*') ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50' }}"
                        >
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="shield-check" class="w-4 h-4 {{ request()->routeIs('admin.audit-logs*') ? 'text-primary-600' : 'text-neutral-400' }}" />
                                <span>Audit Trail</span>
                            </span>
                            @if (request()->routeIs('admin.audit-logs*'))
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            @endif
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany
    </div>
</div>
