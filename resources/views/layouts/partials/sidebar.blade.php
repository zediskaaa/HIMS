<aside
    class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[calc(100vw-2rem)] flex-col bg-white border-r border-neutral-200
           transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- Brand --}}
    <div class="flex items-center gap-2.5 h-16 px-5 border-b border-neutral-200 shrink-0">
        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-9 w-9 shrink-0 rounded-md bg-white object-cover ring-1 ring-inset ring-neutral-200" />
        <div class="min-w-0">
            <p class="text-sm font-semibold text-neutral-900 leading-tight truncate">DJNRMHS</p>
            <p class="text-[11px] text-neutral-500 leading-tight truncate">
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
            'inventory.items*', 'inventory.stock', 'inventory.stock-movements*',
            'inventory.requisitions*', 'inventory.transfers*', 'inventory.cycle-counts*',
            'inventory.adjustments*', 'inventory.alerts*', 'inventory.import*'
        )) {
            $initialOpenDropdown = 'inventory';
        } elseif (request()->routeIs(
            'inventory.warehousing*', 'inventory.receiving*', 'inventory.qc*',
            'inventory.warehouse-tasks*', 'inventory.storage-locations*'
        )) {
            $initialOpenDropdown = 'warehousing';
        } elseif (request()->routeIs(
            'inventory.purchases*', 'inventory.suppliers*', 'inventory.demand-forecast*'
        )) {
            $initialOpenDropdown = 'procurement';
        } elseif (request()->routeIs(
            'inventory.logistics*', 'inventory.reports*', 'reviews.*'
        )) {
            $initialOpenDropdown = 'records';
        } elseif (request()->routeIs(
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

        {{-- 2. Inventory (Major Tab Dropdown) --}}
        @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value, \App\Enums\Permission::PerformCycleCount->value, \App\Enums\Permission::ManageItems->value])
            @php
                $isInventoryActive = request()->routeIs(
                    'inventory.items*', 'inventory.stock', 'inventory.stock-movements*',
                    'inventory.requisitions*', 'inventory.transfers*', 'inventory.cycle-counts*',
                    'inventory.adjustments*', 'inventory.alerts*', 'inventory.import*'
                );
            @endphp
            <x-ui.nav-dropdown
                id="inventory"
                title="Inventory"
                icon="cube"
                :active="$isInventoryActive"
                :badge="$openAlertCount ?? null"
            >
                @can(\App\Enums\Permission::ViewInventory->value)
                    <x-ui.nav-item sub :href="route('inventory.items')" :active="request()->routeIs('inventory.items*')">
                        Inventory Items
                    </x-ui.nav-item>
                    <x-ui.nav-item sub :href="route('inventory.stock')" :active="request()->routeIs('inventory.stock')">
                        Stock Levels
                    </x-ui.nav-item>
                    <x-ui.nav-item sub :href="route('inventory.stock-movements')" :active="request()->routeIs('inventory.stock-movements*')">
                        Stock Movements
                    </x-ui.nav-item>
                @endcan

                @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value])
                    <x-ui.nav-item sub :href="route('inventory.requisitions.index')" :active="request()->routeIs('inventory.requisitions*')">
                        Store Requisitions
                    </x-ui.nav-item>
                @endcanany

                @can(\App\Enums\Permission::TransferStock->value)
                    <x-ui.nav-item sub :href="route('inventory.transfers.index')" :active="request()->routeIs('inventory.transfers*')">
                        Stock Transfers
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::PerformCycleCount->value)
                    <x-ui.nav-item sub :href="route('inventory.cycle-counts.index')" :active="request()->routeIs('inventory.cycle-counts*')">
                        Cycle Counts
                    </x-ui.nav-item>
                @endcan

                @canany([\App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value])
                    <x-ui.nav-item sub :href="route('inventory.adjustments')" :active="request()->routeIs('inventory.adjustments*')">
                        Stock Adjustments
                    </x-ui.nav-item>
                @endcanany

                @can(\App\Enums\Permission::AcknowledgeAlerts->value)
                    <x-ui.nav-item sub :href="route('inventory.alerts')" :active="request()->routeIs('inventory.alerts*')" :badge="$openAlertCount ?? null">
                        Stock Alerts
                    </x-ui.nav-item>
                @endcan

                @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                    <x-ui.nav-item sub :href="route('inventory.import.index')" :active="request()->routeIs('inventory.import*')">
                        Import Data
                    </x-ui.nav-item>
                @endcanany
            </x-ui.nav-dropdown>
        @endcanany

        {{-- 3. Smart Warehousing (Major Tab Dropdown) --}}
        @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::InspectStock->value, \App\Enums\Permission::ExecuteWarehouseTasks->value, \App\Enums\Permission::ManageTelemetryExcursions->value, \App\Enums\Permission::AccessNarcoticsVault->value, \App\Enums\Permission::RecordConsignments->value])
            @php
                $isWarehousingActive = request()->routeIs(
                    'inventory.warehousing*', 'inventory.receiving*', 'inventory.qc*',
                    'inventory.warehouse-tasks*', 'inventory.storage-locations*'
                );
            @endphp
            <x-ui.nav-dropdown
                id="warehousing"
                title="Smart Warehousing"
                icon="building-storefront"
                :active="$isWarehousingActive"
            >
                @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                    <x-ui.nav-item sub :href="route('inventory.warehousing.dashboard')" :active="request()->routeIs('inventory.warehousing.dashboard')">
                        Warehouse Dashboard
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ManageLocations->value)
                    <x-ui.nav-item sub :href="route('inventory.warehousing.locations')" :active="request()->routeIs('inventory.warehousing.locations')">
                        Locations Explorer
                    </x-ui.nav-item>
                    <x-ui.nav-item sub :href="route('inventory.storage-locations')" :active="request()->routeIs('inventory.storage-locations*')">
                        Location Registry
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                    <x-ui.nav-item sub :href="route('inventory.warehouse-tasks.index')" :active="request()->routeIs('inventory.warehouse-tasks*')">
                        Warehouse Tasks
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                    <x-ui.nav-item sub :href="route('inventory.receiving.index')" :active="request()->routeIs('inventory.receiving*')">
                        Dock Receiving
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::InspectStock->value)
                    <x-ui.nav-item sub :href="route('inventory.qc.index')" :active="request()->routeIs('inventory.qc*')">
                        QC Inspection
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
                    <x-ui.nav-item sub :href="route('inventory.warehousing.scan-station')" :active="request()->routeIs('inventory.warehousing.scan-station')">
                        Scan Workstation
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ManageTelemetryExcursions->value)
                    <x-ui.nav-item sub :href="route('inventory.warehousing.telemetry')" :active="request()->routeIs('inventory.warehousing.telemetry')">
                        IoT Telemetry
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::AccessNarcoticsVault->value)
                    <x-ui.nav-item sub :href="route('inventory.warehousing.narcotics')" :active="request()->routeIs('inventory.warehousing.narcotics')">
                        PDEA Narcotics Vault
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::RecordConsignments->value)
                    <x-ui.nav-item sub :href="route('inventory.warehousing.consignment')" :active="request()->routeIs('inventory.warehousing.consignment')">
                        Consignments
                    </x-ui.nav-item>
                @endcan
            </x-ui.nav-dropdown>
        @endcanany

        {{-- 4. Procurement & Sourcing (Major Tab Dropdown) --}}
        @canany([\App\Enums\Permission::ViewProcurement->value, \App\Enums\Permission::ViewSuppliers->value, \App\Enums\Permission::GenerateForecasts->value])
            @php
                $isProcurementActive = request()->routeIs(
                    'inventory.purchases*', 'inventory.suppliers*', 'inventory.demand-forecast*'
                );
            @endphp
            <x-ui.nav-dropdown
                id="procurement"
                title="Procurement"
                icon="clipboard-document-list"
                :active="$isProcurementActive"
            >
                @can(\App\Enums\Permission::ViewProcurement->value)
                    <x-ui.nav-item sub :href="route('inventory.purchases')" :active="request()->routeIs('inventory.purchases*')">
                        Purchase Orders &amp; S2P
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ViewSuppliers->value)
                    <x-ui.nav-item sub :href="route('inventory.suppliers')" :active="request()->routeIs('inventory.suppliers*')">
                        Suppliers Directory
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::GenerateForecasts->value)
                    <x-ui.nav-item sub :href="route('inventory.demand-forecast')" :active="request()->routeIs('inventory.demand-forecast*')">
                        Demand Forecasts
                    </x-ui.nav-item>
                @endcan
            </x-ui.nav-dropdown>
        @endcanany

        {{-- 5. Records & Logistics (Major Tab Dropdown) --}}
        @canany([\App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewLogisticsRecords->value, \App\Enums\Permission::ViewProcessReviews->value])
            @php
                $isRecordsActive = request()->routeIs(
                    'inventory.logistics*', 'inventory.reports*', 'reviews.*'
                );
            @endphp
            <x-ui.nav-dropdown
                id="records"
                title="Records & Logistics"
                icon="document-text"
                :active="$isRecordsActive"
            >
                @can(\App\Enums\Permission::ViewLogisticsRecords->value)
                    <x-ui.nav-item sub :href="route('inventory.logistics')" :active="request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*')">
                        Logistics Overview
                    </x-ui.nav-item>

                    @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                        <x-ui.nav-item sub :href="route('inventory.logistics.documents')" :active="request()->routeIs('inventory.logistics.documents*')">
                            Documents Registry
                        </x-ui.nav-item>
                    @endcan

                    <x-ui.nav-item sub :href="route('inventory.logistics.shipments')" :active="request()->routeIs('inventory.logistics.shipments*')">
                        Shipments &amp; 3PL
                    </x-ui.nav-item>

                    <x-ui.nav-item sub :href="route('inventory.logistics.iar.index')" :active="request()->routeIs('inventory.logistics.iar*')">
                        COA IAR Reports
                    </x-ui.nav-item>

                    @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
                        <x-ui.nav-item sub :href="route('inventory.logistics.chain-of-custody')" :active="request()->routeIs('inventory.logistics.chain-of-custody*')">
                            Chain of Custody
                        </x-ui.nav-item>
                    @endcan
                @endcan

                @can(\App\Enums\Permission::ViewReports->value)
                    <x-ui.nav-item sub :href="route('inventory.reports')" :active="request()->routeIs('inventory.reports*')">
                        Reports &amp; Analytics
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ViewProcessReviews->value)
                    <x-ui.nav-item sub :href="route('reviews.index')" :active="request()->routeIs('reviews.index', 'reviews.show', 'reviews.create')">
                        Process Reviews
                    </x-ui.nav-item>
                    <x-ui.nav-item sub :href="route('reviews.dpri')" :active="request()->routeIs('reviews.dpri*')">
                        DOH DPRI Benchmarks
                    </x-ui.nav-item>
                @endcan
            </x-ui.nav-dropdown>
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
    <div class="px-3 py-3 border-t border-neutral-200 shrink-0">
        <p class="px-3 text-[11px] text-neutral-400">
            {{ config('app.name', 'HIMS') }} &middot; v1.0
        </p>
    </div>
</aside>
