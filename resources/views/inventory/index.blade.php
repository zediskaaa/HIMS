<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
                    Supply Chain & Inventory
                </h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Core modules for purchasing, receiving, stock movement, alerts, and reporting.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Total suppliers</p>
                    <p class="mt-2 text-3xl font-semibold text-[var(--text)]">{{ $totalSuppliers }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Active suppliers</p>
                    <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ $activeSuppliers }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Inactive suppliers</p>
                    <p class="mt-2 text-3xl font-semibold text-amber-600">{{ $inactiveSuppliers }}</p>
                </div>
            </div>

            {{-- Module tiles, each gated on the permission its screen enforces.
                 /dashboard is reachable by every signed-in account because it is
                 where login lands, so the tiles are what narrow it per role —
                 a pharmacy account sees stock and reports, not procurement. --}}
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @can(\App\Enums\Permission::ViewSuppliers->value)
                <a href="{{ route('inventory.suppliers') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Supplier & Vendor Management</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Maintain vendor profiles, rebates, and supplier records.</p>
                </a>
                @endcan

                @can(\App\Enums\Permission::ViewInventory->value)
                <a href="{{ route('inventory.items') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Inventory Items</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Register stock items, track quantities, and link them to suppliers and warehouse locations.</p>
                </a>
                @endcan

                @canany([\App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                <a href="{{ route('inventory.storage-locations') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Storage Locations</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Define warehouse zones, shelves, bins, and storage points for smart warehousing.</p>
                </a>
                @endcanany

                @canany([\App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value])
                <a href="{{ route('inventory.adjustments') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stock Adjustments</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Correct discrepancies, record damage or loss, and update inventory counts safely.</p>
                </a>
                @endcanany

                @can(\App\Enums\Permission::ViewProcurement->value)
                <a href="{{ route('inventory.purchases') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Purchase Orders & Receiving</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Track purchase requisitions, purchase orders, and goods receiving.</p>
                </a>
                @endcan

                @can(\App\Enums\Permission::ViewInventory->value)
                <a href="{{ route('inventory.stock') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stock Movement & Transfers</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Monitor stock in, stock out, internal transfers, and warehouse movement.</p>
                </a>
                @endcan

                @can(\App\Enums\Permission::AcknowledgeAlerts->value)
                <a href="{{ route('inventory.alerts') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Low Stock & Alerts</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Flag critical items, reorder points, and expiry risk.</p>
                </a>
                @endcan

                @can(\App\Enums\Permission::ViewReports->value)
                <a href="{{ route('inventory.reports') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Reports & Dashboard</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Review inventory summaries, movement history, and usage reporting.</p>
                </a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
