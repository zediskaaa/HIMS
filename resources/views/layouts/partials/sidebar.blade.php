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

        Every entry is gated on the same permission its route enforces, so the
        sidebar shows a door only when it will open. The section headers use
        @canany so a group whose items are all hidden does not leave a heading
        floating above nothing. Secondary workflows live as contextual buttons
        on their major module page instead of crowding this navigation rail.

        The permissions themselves live in App\Enums\UserRole::permissions(),
        and /admin/permissions renders the full matrix.
    --}}
    <nav class="flex-1 px-3 py-4 space-y-6 overflow-y-auto" aria-label="Main navigation">
        <div class="space-y-0.5">
            <x-ui.nav-item
                :href="route(\App\Support\AuthenticationContext::dashboardRoute())"
                icon="home"
                :active="request()->routeIs('dashboard', 'super-admin.dashboard')"
            >
                Dashboard
            </x-ui.nav-item>
        </div>

        @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value, \App\Enums\Permission::PerformCycleCount->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Inventory
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ViewInventory->value)
                        <x-ui.nav-item :href="route('inventory.items')" icon="cube"
                                       :active="request()->routeIs('inventory.items*', 'inventory.stock', 'inventory.stock-movements*', 'inventory.requisitions*', 'inventory.transfers*', 'inventory.cycle-counts*', 'inventory.adjustments*', 'inventory.alerts')"
                                       :badge="$openAlertCount ?? null">
                            Inventory
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany([\App\Enums\Permission::ViewWarehouseTasks->value, \App\Enums\Permission::ReceivePurchaseOrder->value, \App\Enums\Permission::ManageLocations->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Warehousing
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                        <x-ui.nav-item :href="route('inventory.warehousing.dashboard')" icon="building-storefront"
                                       :active="request()->routeIs('inventory.warehousing*', 'inventory.receiving*', 'inventory.qc*', 'inventory.warehouse-tasks*', 'inventory.storage-locations*')">
                            Smart Warehousing
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany([\App\Enums\Permission::ViewProcurement->value, \App\Enums\Permission::ViewSuppliers->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Procurement
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ViewProcurement->value)
                        <x-ui.nav-item :href="route('inventory.purchases')" icon="clipboard-document-list"
                                       :active="request()->routeIs('inventory.purchases*', 'inventory.suppliers*', 'inventory.demand-forecast*')">
                            Procurement &amp; Sourcing
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany([\App\Enums\Permission::ViewReports->value, \App\Enums\Permission::ViewLogisticsRecords->value, \App\Enums\Permission::ViewProcessReviews->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Records &amp; Analysis
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ViewLogisticsRecords->value)
                        <x-ui.nav-item :href="route('inventory.logistics')" icon="document-text"
                                       :active="request()->routeIs('inventory.logistics*')">
                            Documents &amp; Logistics
                        </x-ui.nav-item>
                    @endcan
                    @can(\App\Enums\Permission::ViewReports->value)
                        <x-ui.nav-item :href="route('inventory.reports')" icon="chart-bar"
                                       :active="request()->routeIs('inventory.reports')">
                            Reports
                        </x-ui.nav-item>
                    @endcan
                    @can(\App\Enums\Permission::ViewProcessReviews->value)
                        <x-ui.nav-item :href="route('reviews.index')" icon="clipboard-document-check"
                                       :active="request()->routeIs('reviews.*')">
                            Process Reviews
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany([\App\Enums\Permission::ManageUsers->value, \App\Enums\Permission::ViewAuditTrail->value, \App\Enums\Permission::ManageSystemRecovery->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Administration
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ManageUsers->value)
                        <x-ui.nav-item :href="route('admin.users.index')" icon="users"
                                       :active="request()->routeIs('admin.users.*', 'admin.permissions')">
                            User Management
                        </x-ui.nav-item>
                    @endcan
                    @can(\App\Enums\Permission::ViewAuditTrail->value)
                        <x-ui.nav-item :href="route('admin.audit-logs.index')" icon="clipboard-document-list"
                                       :active="request()->routeIs('admin.audit-logs.*')">
                            Audit Trail
                        </x-ui.nav-item>
                    @endcan
                    @can(\App\Enums\Permission::ManageSystemRecovery->value)
                        <x-ui.nav-item :href="route('admin.recovery.index')" icon="shield-check"
                                       :active="request()->routeIs('admin.recovery.*', 'super-admin.recovery.*')">
                            Recovery Center
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany
    </nav>

    {{-- Footer --}}
    <div class="px-3 py-3 border-t border-neutral-200 shrink-0">
        <p class="px-3 text-[11px] text-neutral-400">
            {{ config('app.name', 'HIMS') }} &middot; v1.0
        </p>
    </div>
</aside>
