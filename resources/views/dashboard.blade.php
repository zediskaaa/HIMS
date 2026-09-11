@php
    $isSuperAdminPanel = $superAdminPanel ?? false;
    $isAdminPanel = \App\Support\AuthenticationContext::isAdmin();
    $dashboardTitle = match (true) {
        $isSuperAdminPanel => 'Super Admin Dashboard',
        $isAdminPanel => 'Admin Dashboard',
        default => 'Staff Dashboard',
    };
    $dashboardSubtitle = match (true) {
        $isSuperAdminPanel => 'Full administrative oversight of HIMS inventory, users, procurement, and records.',
        $isAdminPanel => 'Manage HIMS operations and user access within the Administrator role.',
        default => 'Monitor inventory health and complete the operations authorized for your staff role.',
    };
@endphp

<x-app-layout>
    <x-slot:title>{{ $dashboardTitle }}</x-slot:title>

    <x-ui.page-header
        :title="$dashboardTitle"
        :subtitle="$dashboardSubtitle"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), $dashboardTitle => null]"
    >
        <x-slot:actions>
            @canany([\App\Enums\Permission::IssueStock->value, \App\Enums\Permission::RecordMovements->value, \App\Enums\Permission::TransferStock->value])
            <x-ui.button variant="secondary" icon="arrows-right-left" :href="route('inventory.stock-movements')">
                Record movement
            </x-ui.button>
            @endcanany
            @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
            <x-ui.button variant="secondary" icon="arrow-up-tray" :href="route('inventory.import.index')">
                Import data
            </x-ui.button>
            @endcanany
            @can(\App\Enums\Permission::ManageItems->value)
            <x-ui.button variant="primary" icon="plus" :href="route('inventory.items')">
                New item
            </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{--
        Alerts and the counters above them are re-fetched every 30s so the
        dashboard reflects stock recorded from another screen (or by someone
        else) without a manual reload. Polling rather than websockets: no extra
        server process to keep alive, and nothing new to install.
    --}}
    <div
        x-data="dashboardLive({{ Js::from(route('dashboard.live')) }})"
        x-init="start()"
        @dashboard-refresh.window="refresh()"
    >
    @can(\App\Enums\Permission::ManageSystemRecovery->value)
        @php
            $pendingRecoveryCount = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('system_recovery_records')) {
                    $pendingRecoveryCount = \App\Models\SystemRecoveryRecord::pending()->count();
                }
            } catch (\Throwable) {
                $pendingRecoveryCount = 0;
            }
        @endphp
        @if ($pendingRecoveryCount > 0)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-amber-950">
                            Attention: {{ $pendingRecoveryCount }} Unresolved System {{ \Illuminate\Support\Str::plural('Incident', $pendingRecoveryCount) }}
                        </h4>
                        <p class="text-xs text-amber-800">
                            Failed transactions, data imports, or background jobs require review in the Recovery Center.
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('admin.recovery.index') }}"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-800 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-900 transition shadow-sm self-start sm:self-auto"
                >
                    <span>Open Recovery Center</span>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        @endif
    @endcan

    {{-- Key figures --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat
            label="Tracked items"
            :value="number_format($totalItems)"
            icon="cube"
            tone="primary"
            :hint="number_format($totalOnHand).' units on hand'"
            :href="route('inventory.items')"
        />

        <x-ui.stat
            label="Needs reorder"
            icon="exclamation-triangle"
            :tone="$lowStockItems > 0 ? 'warning' : 'success'"
            :href="route('inventory.alerts')"
            x-ref="lowStockTile"
        >
            <x-slot:value><span data-stat-value>{{ number_format($lowStockItems) }}</span></x-slot:value>
            <x-slot:hint><span data-stat-hint>{{ $outOfStockItems > 0
                ? number_format($outOfStockItems).' fully out of stock'
                : 'No items out of stock' }}</span></x-slot:hint>
        </x-ui.stat>

        <x-ui.stat
            label="Open alerts"
            icon="bell-alert"
            :tone="$openAlertCount > 0 ? 'danger' : 'success'"
            :href="route('inventory.alerts')"
            x-ref="openAlertTile"
        >
            <x-slot:value><span data-stat-value>{{ number_format($openAlertCount) }}</span></x-slot:value>
            <x-slot:hint><span data-stat-hint>{{ $openAlertCount > 0 ? 'Awaiting acknowledgement' : 'Nothing outstanding' }}</span></x-slot:hint>
        </x-ui.stat>

        @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
        <x-ui.stat
            label="Inventory value"
            :value="'₱'.number_format($totalInventoryValue, 2)"
            icon="chart-bar"
            tone="neutral"
            :hint="number_format($storageLocations).' storage locations'"
            :href="route('inventory.reports')"
        />
        @endcan
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Stock alerts --}}
        <x-ui.card class="lg:col-span-2" :padding="false">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Stock alerts</h2>
                <p class="mt-0.5 text-xs text-neutral-500">
                    Shortages and expiry risk needing attention
                    <span class="text-neutral-400" x-text="statusLabel"></span>
                </p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.alerts')">View all</x-ui.button>
            </x-slot:actions>

            <div x-ref="alerts">
                @include('inventory.partials.alerts-table')
            </div>
        </x-ui.card>

        {{-- Expiring batches --}}
        <x-ui.card title="Expiring soon" subtitle="Next 90 days, earliest first">
            @forelse ($expiringBatches as $batch)
                @php
                    $days = (int) now()->startOfDay()->diffInDays($batch->expiry_date, false);
                @endphp
                <div class="flex items-start justify-between gap-3 py-3 border-b border-neutral-100
                            first:pt-0 last:pb-0 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-neutral-900 truncate">
                            {{ $batch->item?->name ?? 'Unknown item' }}
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Batch {{ $batch->batch_number }} &middot; {{ $batch->expiry_date->format('d M Y') }}
                        </p>
                    </div>

                    <x-ui.badge :variant="$days < 0 ? 'danger' : ($days <= 30 ? 'warning' : 'neutral')">
                        {{ $days < 0 ? abs($days).'d overdue' : $days.'d left' }}
                    </x-ui.badge>
                </div>
            @empty
                <p class="py-6 text-sm text-center text-neutral-500">
                    No batches expiring in the next 90 days.
                </p>
            @endforelse
        </x-ui.card>
    </div>

    <div>
        {{-- Operational snapshot. The supplier and purchase-order figures are
             procurement's business, so an account without it sees the stock
             lines only rather than counts it cannot act on. --}}
        <x-ui.card title="Operational snapshot">
            <dl class="divide-y divide-neutral-100">
                @foreach (array_merge(
                    auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value) ? [
                        ['Suppliers', number_format($totalSuppliers), 'text-neutral-900'],
                        ['Active suppliers', number_format($activeSuppliers), 'text-success-700'],
                    ] : [],
                    [['Storage locations', number_format($storageLocations), 'text-neutral-900']],
                    auth()->user()->can(\App\Enums\Permission::ViewProcurementSensitiveData->value) ? [
                        ['Pending purchase orders', number_format($pendingPoCount), $pendingPoCount > 0 ? 'text-warning-700' : 'text-neutral-900'],
                    ] : [],
                    [['Out of stock', number_format($outOfStockItems), $outOfStockItems > 0 ? 'text-danger-700' : 'text-neutral-900']],
                ) as [$label, $figure, $tone])
                    <div class="flex items-center justify-between py-2.5 first:pt-0 last:pb-0">
                        <dt class="text-sm text-neutral-600">{{ $label }}</dt>
                        <dd class="text-sm font-semibold tabular-nums {{ $tone }}">{{ $figure }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Pending purchase orders. Hidden without manage_procurement: the
             card's "View all" leads to a screen that would refuse them, and
             the rows name suppliers and amounts they have no business in. --}}
        @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
        <x-ui.card :padding="false">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Pending purchase orders</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Awaiting approval or delivery</p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.purchases')">View all</x-ui.button>
            </x-slot:actions>

            <x-ui.table :sticky-header="false">
                <x-ui.table.head>
                    <x-ui.table.th>PO number</x-ui.table.th>
                    <x-ui.table.th>Supplier</x-ui.table.th>
                    <x-ui.table.th numeric>Amount</x-ui.table.th>
                    <x-ui.table.th>Status</x-ui.table.th>
                </x-ui.table.head>

                <tbody>
                    @forelse ($pendingPurchaseOrders as $po)
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <span class="font-medium text-neutral-900">{{ $po->po_number }}</span>
                                @if ($po->requested_at)
                                    <span class="block text-xs text-neutral-500">
                                        {{ $po->requested_at->format('d M Y') }}
                                    </span>
                                @endif
                            </x-ui.table.td>
                            <x-ui.table.td muted>{{ $po->supplier?->name ?? '—' }}</x-ui.table.td>
                            <x-ui.table.td numeric>₱{{ number_format((float) $po->total_amount, 2) }}</x-ui.table.td>
                            <x-ui.table.td>
                                <x-ui.badge :status="$po->status" />
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="4"
                            icon="clipboard-document-list"
                            title="No pending purchase orders"
                            message="Approved and fulfilled orders are hidden from this view."
                        />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
        @endcan

        {{-- Recent stock movements --}}
        <x-ui.card :padding="false">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-neutral-900">Recent stock movements</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Latest issued, received and transferred stock</p>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.stock-movements')">View all</x-ui.button>
            </x-slot:actions>

            <x-ui.table :sticky-header="false">
                <x-ui.table.head>
                    <x-ui.table.th>Item</x-ui.table.th>
                    <x-ui.table.th>Type</x-ui.table.th>
                    <x-ui.table.th numeric>Qty</x-ui.table.th>
                    <x-ui.table.th>When</x-ui.table.th>
                </x-ui.table.head>

                <tbody>
                    @forelse ($recentMovements as $movement)
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <span class="font-medium text-neutral-900">
                                    {{ $movement->item?->name ?? 'Unknown item' }}
                                </span>
                                @if ($movement->toLocation || $movement->fromLocation)
                                    <span class="block text-xs text-neutral-500">
                                        {{ $movement->fromLocation?->name ?? '—' }}
                                        &rarr;
                                        {{ $movement->toLocation?->name ?? '—' }}
                                    </span>
                                @endif
                            </x-ui.table.td>

                            <x-ui.table.td>
                                <x-ui.badge :status="$movement->movement_type->value">
                                    {{ $movement->movement_type->label() }}
                                </x-ui.badge>
                            </x-ui.table.td>

                            <x-ui.table.td numeric>{{ number_format((int) $movement->quantity) }}</x-ui.table.td>

                            <x-ui.table.td muted>
                                {{ $movement->moved_at?->diffForHumans() ?? '—' }}
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="4"
                            icon="arrows-right-left"
                            title="No stock movements yet"
                            message="Recorded stock in, stock out and transfers will appear here."
                        >
                            @canany([\App\Enums\Permission::IssueStock->value, \App\Enums\Permission::RecordMovements->value, \App\Enums\Permission::TransferStock->value])
                                <x-slot:action>
                                    <x-ui.button size="sm" icon="plus" :href="route('inventory.stock-movements')">
                                        Record movement
                                    </x-ui.button>
                                </x-slot:action>
                            @endcanany
                        </x-ui.table.empty>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>
    </div>{{-- /dashboardLive --}}
</x-app-layout>
