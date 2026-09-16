<aside
    class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[calc(100vw-2rem)] flex-col bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800
           transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- Brand --}}
    <div class="flex items-center gap-2.5 h-16 px-5 border-b border-neutral-200 dark:border-neutral-800 shrink-0">
        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="hims-keep-light h-9 w-9 shrink-0 rounded-md bg-white object-cover ring-1 ring-inset ring-neutral-200 dark:ring-neutral-700" />
        <div class="min-w-0">
            <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 leading-tight truncate">DJNRMHS</p>
            <p class="text-[11px] text-neutral-500 dark:text-neutral-400 leading-tight truncate">
                {{ match (\App\Support\AuthenticationContext::authenticatedGuard()) {
                    \App\Support\AuthenticationContext::SUPER_ADMIN_GUARD => 'Super Admin Panel',
                    \App\Support\AuthenticationContext::ADMIN_GUARD => 'Admin Panel',
                    default => 'Staff Panel',
                } }}
            </p>
        </div>
    </div>

    {{--
        Navigation.
        Major modules are collapsible accordion dropdowns; clicking a major module expands its
        minor submodule links while automatically closing any other open major tab.
    --}}
    @php
        $initialOpenDropdown = null;
        if (request()->routeIs(
            'admin.users.*', 'admin.permissions', 'admin.audit-logs.*',
            'admin.recovery.*', 'super-admin.recovery.*'
        )) {
            $initialOpenDropdown = 'administration';
        }
    @endphp
    <nav
        class="flex-1 px-3 py-4 space-y-3 overflow-y-auto"
        aria-label="Main navigation"
        x-data="{ activeDropdown: '{{ $initialOpenDropdown }}' }"
    >
        {{-- 1. Core Dashboard --}}
        <div class="space-y-0.5">
            <x-ui.nav-item
                :href="route(\App\Support\AuthenticationContext::dashboardRoute())"
                icon="home"
                :active="request()->routeIs('dashboard', 'super-admin.dashboard')"
            >
                Dashboard
            </x-ui.nav-item>
        </div>

        {{-- 2. Inventory --}}
        @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value, \App\Enums\Permission::PerformCycleCount->value, \App\Enums\Permission::ManageItems->value])
            @php
                $isInventoryActive = request()->routeIs(
                    'inventory.items*', 'inventory.stock', 'inventory.stock-movements*',
                    'inventory.requisitions*', 'inventory.transfers*', 'inventory.cycle-counts*',
                    'inventory.adjustments*', 'inventory.alerts*', 'inventory.import*'
                );
            @endphp
            <div class="space-y-0.5">
                <x-ui.nav-item
                    :href="route('inventory.items')"
                    icon="cube"
                    :active="$isInventoryActive"
                    :badge="$openAlertCount ?? null"
                >
                    Inventory
                </x-ui.nav-item>
            </div>
        @endcanany

        {{-- 3. Smart Warehousing --}}
        @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ExecuteWarehouseTasks->value, \App\Enums\Permission::ManageTelemetryExcursions->value])
            @php
                $isWarehousingActive = request()->routeIs(
                    'inventory.warehousing*', 'inventory.receiving*', 'inventory.qc*',
                    'inventory.warehouse-tasks*', 'inventory.storage-locations*'
                );
            @endphp
            <div class="relative">
                <x-ui.nav-item
                    :href="route('inventory.warehousing.dashboard')"
                    icon="building-storefront"
                    :active="$isWarehousingActive"
                >
                    Smart Warehousing
                </x-ui.nav-item>
            </div>
        @endcanany

        {{-- 4. Procurement & Sourcing --}}
        @canany([\App\Enums\Permission::ViewProcurement->value, \App\Enums\Permission::GenerateForecasts->value])
            @php
                $isProcurementActive = request()->routeIs(
                    'inventory.purchases*', 'inventory.demand-forecast*'
                );
            @endphp
            <div class="relative">
                <x-ui.nav-item
                    :href="route('inventory.purchases')"
                    icon="clipboard-document-list"
                    :active="$isProcurementActive"
                >
                    Procurement &amp; Sourcing
                </x-ui.nav-item>
            </div>
        @endcanany

        {{-- 5. Supplier Management --}}
        @can(\App\Enums\Permission::ViewSuppliers->value)
            @php
                $isSuppliersActive = request()->routeIs('inventory.suppliers*');
            @endphp
            <div class="relative">
                <x-ui.nav-item
                    :href="route('inventory.suppliers')"
                    icon="truck"
                    :active="$isSuppliersActive"
                >
                    Supplier Management
                </x-ui.nav-item>
            </div>
        @endcan

        {{-- 6. Documents & Logistics --}}
        @canany([\App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewLogisticsRecords->value, \App\Enums\Permission::ViewProcessReviews->value])
            @php
                $isRecordsActive = request()->routeIs(
                    'inventory.logistics*', 'inventory.reports*', 'reviews.*'
                );
            @endphp
            <div class="relative">
                <x-ui.nav-item
                    :href="route('inventory.logistics')"
                    icon="document-text"
                    :active="$isRecordsActive"
                >
                    Documents &amp; Logistics
                </x-ui.nav-item>
            </div>
        @endcanany

        {{-- 6. Administration & Governance (Major Tab Dropdown) --}}
        @canany([\App\Enums\Permission::ManageUsers->value, \App\Enums\Permission::ViewAuditTrail->value, \App\Enums\Permission::ManageSystemRecovery->value])
            @php
                $isAdminActive = request()->routeIs(
                    'admin.users.*', 'admin.permissions', 'admin.audit-logs.*',
                    'admin.recovery.*', 'super-admin.recovery.*'
                );
            @endphp
            <x-ui.nav-dropdown
                id="administration"
                title="Administration"
                icon="shield-check"
                :active="$isAdminActive"
            >
                @can(\App\Enums\Permission::ManageUsers->value)
                    <x-ui.nav-item sub :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        User Management
                    </x-ui.nav-item>
                    <x-ui.nav-item sub :href="route('admin.permissions')" :active="request()->routeIs('admin.permissions')">
                        Roles &amp; Permissions
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ViewAuditTrail->value)
                    <x-ui.nav-item sub :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')">
                        Audit Trail
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ManageSystemRecovery->value)
                    <x-ui.nav-item sub :href="route('admin.recovery.index')" :active="request()->routeIs('admin.recovery.index', 'super-admin.recovery.index', 'admin.recovery.show')">
                        Recovery Center
                    </x-ui.nav-item>
                    <x-ui.nav-item sub :href="route('admin.recovery.health')" :active="request()->routeIs('admin.recovery.health', 'super-admin.recovery.health')">
                        Health Telemetry
                    </x-ui.nav-item>
                @endcan
            </x-ui.nav-dropdown>
        @endcanany
    </nav>

    {{-- Footer --}}
    <div class="px-3 py-3 border-t border-neutral-200 dark:border-neutral-800 shrink-0">
        <p class="px-3 text-[11px] text-neutral-400 dark:text-neutral-500">
            {{ config('app.name', 'HIMS') }} &middot; v1.0
        </p>
    </div>
</aside>
