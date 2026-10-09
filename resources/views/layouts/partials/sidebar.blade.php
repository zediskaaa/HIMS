<aside
    id="primary-navigation"
    class="hims-sidebar fixed inset-y-0 left-0 z-40 flex w-64 max-w-[calc(100vw-2rem)] flex-col transition-transform duration-200 -translate-x-full lg:translate-x-0"
    :class="{ 'translate-x-0 lg:translate-x-0': sidebarOpen, '-translate-x-full lg:-translate-x-full': !sidebarOpen }"
    :aria-hidden="sidebarOpen ? 'false' : 'true'"
    :inert="!sidebarOpen"
>
    {{-- Brand --}}
    <div class="hims-sidebar__brand flex h-16 shrink-0 items-center justify-between gap-2.5 px-5 py-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <img src="{{ asset('img/hims-logo.png') }}" alt="" class="hims-keep-light h-10 w-10 shrink-0 rounded-lg bg-white object-cover ring-1 ring-inset ring-white/40 shadow-lg" />
            <div class="min-w-0">
                <p class="truncate text-sm font-bold leading-tight tracking-wide text-white">DJNRMHS</p>
                <p class="mt-0.5 truncate text-[11px] leading-tight text-emerald-100/75">
                    {{ auth()->user()?->role?->isSupplier() ? 'Supplier Portal' : match (\App\Support\AuthenticationContext::authenticatedGuard()) {
                        \App\Support\AuthenticationContext::SUPER_ADMIN_GUARD => 'Super Admin Panel',
                        \App\Support\AuthenticationContext::ADMIN_GUARD => 'Admin Panel',
                        default => 'Staff Panel',
                    } }}
                </p>
            </div>
        </div>

        {{-- Sidebar collapse button --}}
        <button
            type="button"
            x-on:click="sidebarOpen = false"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-emerald-100/70 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
            aria-label="Close navigation"
            title="Close navigation"
        >
            <x-ui.icon name="x-mark" class="h-5 w-5 lg:hidden" />
            <x-ui.icon name="bars-3" class="hidden h-5 w-5 lg:block" />
        </button>
    </div>

    {{--
        Navigation.
        Major modules are collapsible accordion dropdowns; clicking a major module expands its
        minor submodule links while automatically closing any other open major tab.
    --}}
    @if (auth()->user()?->role?->isSupplier())
        @php
            $supplierNavigation = [
                ['route' => 'supplier.dashboard', 'pattern' => 'supplier.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
                ...(auth()->user()->can(\App\Enums\Permission::SupplierManageProfile->value)
                    ? [['route' => 'supplier.company-profile.edit', 'pattern' => 'supplier.company-profile.*', 'label' => 'Company Profile', 'icon' => 'building-office-2']]
                    : []),
                ['route' => 'supplier.orders.index', 'pattern' => 'supplier.orders.*', 'label' => 'Purchase Orders', 'icon' => 'clipboard-document-list'],
                ['route' => 'supplier.rfqs.index', 'pattern' => 'supplier.rfqs.*', 'label' => 'RFQs', 'icon' => 'scale'],
                ['route' => 'supplier.discrepancies.index', 'pattern' => 'supplier.discrepancies.*', 'label' => 'Discrepancies', 'icon' => 'exclamation-triangle'],
                ['route' => 'supplier.catalog.index', 'pattern' => 'supplier.catalog.*', 'label' => 'Catalog', 'icon' => 'shopping-bag'],
                ['route' => 'supplier.compliance.index', 'pattern' => 'supplier.compliance.*', 'label' => 'Compliance', 'icon' => 'shield-check'],
                ['route' => 'supplier.invoices.index', 'pattern' => 'supplier.invoices.*', 'label' => 'Invoices', 'icon' => 'document-text'],
                ['route' => 'supplier.performance', 'pattern' => 'supplier.performance', 'label' => 'Performance', 'icon' => 'chart-bar'],
            ];
        @endphp
        <nav
            class="hims-sidebar__nav flex-1 space-y-1 overflow-y-auto px-3 py-5"
            aria-label="Supplier portal navigation"
            x-on:click="if (isMobile && $event.target.closest('a')) sidebarOpen = false"
        >
            @foreach ($supplierNavigation as $item)
                <x-ui.nav-item
                    :href="route($item['route'])"
                    :icon="$item['icon']"
                    :active="request()->routeIs($item['pattern'])"
                >
                    {{ $item['label'] }}
                </x-ui.nav-item>
            @endforeach
        </nav>
    @else
    @php
        $initialOpenDropdown = null;
        if (request()->routeIs(
            'admin.users.*', 'super-admin.users.*', 'admin.permissions', 'super-admin.permissions',
            'admin.audit-logs.*', 'super-admin.audit-logs.*', 'admin.privacy.*', 'super-admin.privacy.*',
            'admin.archive.*', 'super-admin.archive.*'
        )) {
            $initialOpenDropdown = 'administration';
        }
    @endphp
    <nav
        class="hims-sidebar__nav flex-1 space-y-3 overflow-y-auto px-3 py-5"
        aria-label="Main navigation"
        x-data="{ activeDropdown: '{{ $initialOpenDropdown }}' }"
        x-on:click="if (isMobile && $event.target.closest('a')) sidebarOpen = false"
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
        @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ExecuteWarehouseTasks->value])
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
        @canany([\App\Enums\Permission::ManageUsers->value, \App\Enums\Permission::ViewAuditTrail->value, \App\Enums\Permission::ManagePrivacyCompliance->value, \App\Enums\Permission::ViewArchive->value])
            @php
                $isAdminActive = request()->routeIs(
                    'admin.users.*', 'super-admin.users.*', 'admin.permissions', 'super-admin.permissions',
                    'admin.audit-logs.*', 'super-admin.audit-logs.*', 'admin.privacy.*', 'super-admin.privacy.*',
                    'admin.archive.*', 'super-admin.archive.*'
                );
            @endphp
            <x-ui.nav-dropdown
                id="administration"
                title="Administration"
                icon="shield-check"
                :active="$isAdminActive"
            >
                @can(\App\Enums\Permission::ManageUsers->value)
                    <x-ui.nav-item sub :href="route(\App\Support\AuthenticationContext::administrationRoute('users.index'))" :active="request()->routeIs('admin.users.*', 'super-admin.users.*')">
                        User Management
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ViewArchive->value)
                    <x-ui.nav-item sub :href="route(\App\Support\AuthenticationContext::administrationRoute('archive.index'))" :active="request()->routeIs('admin.archive.*', 'super-admin.archive.*')">
                        Archive
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ViewAuditTrail->value)
                    <x-ui.nav-item sub :href="route(\App\Support\AuthenticationContext::auditLogRoute())" :active="request()->routeIs('admin.audit-logs.*', 'super-admin.audit-logs.*')">
                        Audit Trail
                    </x-ui.nav-item>
                @endcan

                @can(\App\Enums\Permission::ManagePrivacyCompliance->value)
                    <x-ui.nav-item sub :href="route(\App\Support\AuthenticationContext::administrationRoute('privacy.index'))" :active="request()->routeIs('admin.privacy.*', 'super-admin.privacy.*')">
                        Privacy &amp; Governance
                    </x-ui.nav-item>
                @endcan

            </x-ui.nav-dropdown>
        @endcanany
    </nav>
    @endif

    {{-- Footer --}}
    <div class="hims-sidebar__footer shrink-0 border-t px-3 py-3">
        @if (auth()->user()?->role?->isSupplier())
            <p class="truncate px-3 text-[11px] font-medium text-emerald-100/80">{{ auth()->user()?->supplier?->name }}</p>
        @endif
        <p class="mt-0.5 px-3 text-[11px] text-emerald-100/60">{{ config('app.name', 'HIMS') }} &middot; v1.0</p>
    </div>
</aside>
