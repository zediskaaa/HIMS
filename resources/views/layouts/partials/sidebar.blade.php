<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col w-64 bg-white border-r border-neutral-200
           transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- Brand --}}
    <div class="flex items-center gap-2.5 h-16 px-5 border-b border-neutral-200 shrink-0">
        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-9 w-9 shrink-0 rounded-md bg-white object-cover ring-1 ring-inset ring-neutral-200" />
        <div class="min-w-0">
            <p class="text-sm font-semibold text-neutral-900 leading-tight truncate">DJNRMHS</p>
            <p class="text-[11px] text-neutral-500 leading-tight truncate">Supply Chain &amp; Inventory</p>
        </div>
    </div>

    {{--
        Navigation.

        Every entry is gated on the same permission its route enforces, so the
        sidebar shows a door only when it will open. The section headers use
        @canany so a group whose items are all hidden does not leave a heading
        floating above nothing — a pharmacy account sees no "Procurement"
        caption at all, rather than an empty one.

        The permissions themselves live in App\Enums\UserRole::permissions(),
        and /admin/permissions renders the full matrix.
    --}}
    <nav class="flex-1 px-3 py-4 space-y-6 overflow-y-auto" aria-label="Main navigation">
        <div class="space-y-0.5">
            <x-ui.nav-item :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-ui.nav-item>
        </div>

        @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::AdjustStock->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Inventory
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ViewInventory->value)
                        <x-ui.nav-item :href="route('inventory.items')" icon="cube"
                                       :active="request()->routeIs('inventory.items*')">
                            Items
                        </x-ui.nav-item>
                        <x-ui.nav-item :href="route('inventory.stock')" icon="chart-bar"
                                       :active="request()->routeIs('inventory.stock')">
                            Stock Levels
                        </x-ui.nav-item>
                        <x-ui.nav-item :href="route('inventory.stock-movements')" icon="arrows-right-left"
                                       :active="request()->routeIs('inventory.stock-movements*')">
                            Stock Movements
                        </x-ui.nav-item>
                    @endcan
                    @can(\App\Enums\Permission::AdjustStock->value)
                        <x-ui.nav-item :href="route('inventory.adjustments')" icon="clipboard-document-list"
                                       :active="request()->routeIs('inventory.adjustments*')">
                            Adjustments
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany([\App\Enums\Permission::ViewInventory->value, \App\Enums\Permission::ManageLocations->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Warehousing
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ViewInventory->value)
                        <x-ui.nav-item :href="route('inventory.storage-locations')" icon="building-storefront"
                                       :active="request()->routeIs('inventory.storage-locations*')">
                            Storage Locations
                        </x-ui.nav-item>
                        <x-ui.nav-item :href="route('inventory.alerts')" icon="bell-alert"
                                       :active="request()->routeIs('inventory.alerts')"
                                       :badge="$openAlertCount ?? null">
                            Stock Alerts
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany([\App\Enums\Permission::ManageProcurement->value, \App\Enums\Permission::ManageSuppliers->value])
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Procurement
                </p>
                <div class="space-y-0.5">
                    @can(\App\Enums\Permission::ManageProcurement->value)
                        <x-ui.nav-item :href="route('inventory.purchases')" icon="clipboard-document-list"
                                       :active="request()->routeIs('inventory.purchases*')">
                            Requisitions &amp; POs
                        </x-ui.nav-item>
                    @endcan
                    @can(\App\Enums\Permission::ManageSuppliers->value)
                        <x-ui.nav-item :href="route('inventory.suppliers')" icon="truck"
                                       :active="request()->routeIs('inventory.suppliers*')">
                            Suppliers
                        </x-ui.nav-item>
                    @endcan
                </div>
            </div>
        @endcanany

        @can(\App\Enums\Permission::ViewReports->value)
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Records &amp; Analysis
                </p>
                <div class="space-y-0.5">
                    <x-ui.nav-item :href="route('inventory.logistics')" icon="document-text"
                                   :active="request()->routeIs('inventory.logistics')">
                        Documents &amp; Logistics
                    </x-ui.nav-item>
                    <x-ui.nav-item :href="route('inventory.reports')" icon="chart-bar"
                                   :active="request()->routeIs('inventory.reports')">
                        Reports
                    </x-ui.nav-item>
                    <x-ui.nav-item :href="route('inventory.demand-forecast')" icon="arrow-trending-up"
                                   :active="request()->routeIs('inventory.demand-forecast*')">
                        Demand Forecast
                    </x-ui.nav-item>
                </div>
            </div>
        @endcan

        {{-- Only administrators hold manage_users, so the section is hidden
             rather than shown-and-refused for everyone else. --}}
        @can(\App\Enums\Permission::ManageUsers->value)
            <div>
                <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                    Administration
                </p>
                <div class="space-y-0.5">
                    <x-ui.nav-item :href="route('admin.users.index')" icon="users"
                                   :active="request()->routeIs('admin.users.*')">
                        User Management
                    </x-ui.nav-item>
                    <x-ui.nav-item :href="route('admin.permissions')" icon="shield-check"
                                   :active="request()->routeIs('admin.permissions')">
                        Access Control
                    </x-ui.nav-item>
                </div>
            </div>
        @endcan
    </nav>

    {{-- Footer --}}
    <div class="px-3 py-3 border-t border-neutral-200 shrink-0">
        <p class="px-3 text-[11px] text-neutral-400">
            {{ config('app.name', 'HIMS') }} &middot; v1.0
        </p>
    </div>
</aside>
