<x-app-layout>
    <div x-data="{ createItemModal: {{ $errors->any() ? 'true' : 'false' }}, openDropdown: null }"
         @keydown.escape.window="createItemModal = false"
         class="space-y-6">

        <x-ui.page-header
            title="Inventory Items"
            subtitle="Manage your hospital medical supply catalogue, stock tracking, and supplier links."
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory Items' => null]">
            <x-slot:actions>
                @can(\App\Enums\Permission::ManageItems->value)
                    <button
                        type="button"
                        @click="createItemModal = true"
                        id="btn-open-create-item-modal"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-primary-700 transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Create Inventory Item</span>
                    </button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Workflows & Tools Bar --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-neutral-100">
                <div>
                    <h3 class="text-sm font-bold text-neutral-900">Inventory Workflows &amp; Tools</h3>
                    <p class="text-xs text-neutral-500">Open a workflow when you need it; only actions allowed for your role are shown.</p>
                </div>
            </div>

            {{-- Mobile / Small Screen Quick Selector (< sm) --}}
            <div class="mt-3 sm:hidden">
                <label for="inventory-tools-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                    Select Inventory Workflow:
                </label>
                <select
                    id="inventory-tools-select"
                    onchange="if (this.value) window.location.href = this.value;"
                    class="block w-full rounded-lg border border-neutral-300 py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
                >
                    <option value="">Choose an action or workflow...</option>
                    <optgroup label="Stock &amp; Movements">
                        <option value="{{ route('inventory.stock-movements') }}">Stock Movements</option>
                        @can(\App\Enums\Permission::AcknowledgeAlerts->value)
                            <option value="{{ route('inventory.alerts') }}">Stock Alerts {{ ($openAlertCount ?? 0) > 0 ? '('.$openAlertCount.')' : '' }}</option>
                        @endcan
                    </optgroup>
                    @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value])
                        <optgroup label="Requisitions &amp; Transfers">
                            @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value])
                                <option value="{{ route('inventory.requisitions.index') }}">Store Requisitions</option>
                            @endcanany
                            @can(\App\Enums\Permission::TransferStock->value)
                                <option value="{{ route('inventory.transfers.index') }}">Stock Transfers</option>
                            @endcan
                        </optgroup>
                    @endcanany
                    @canany([\App\Enums\Permission::PerformCycleCount->value, \App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value, \App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                        <optgroup label="Audits &amp; Data Operations">
                            @can(\App\Enums\Permission::PerformCycleCount->value)
                                <option value="{{ route('inventory.cycle-counts.index') }}">Cycle Counts</option>
                            @endcan
                            @canany([\App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value])
                                <option value="{{ route('inventory.adjustments') }}">Stock Adjustments</option>
                            @endcanany
                            @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                                <option value="{{ route('inventory.import.index') }}">Import Data (CSV/Excel)</option>
                            @endcanany
                        </optgroup>
                    @endcanany
                </select>
            </div>

            {{-- Desktop & Tablet Major Dropdown Tabs (>= sm) --}}
            <div class="hidden sm:flex sm:items-center sm:gap-3 flex-wrap text-xs mt-3">
                {{-- Major 1: Stock & Movements --}}
                <div class="relative" @click.outside="if (openDropdown === 'stock') openDropdown = null">
                    <button
                        type="button"
                        @click="openDropdown = openDropdown === 'stock' ? null : 'stock'"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-semibold transition border border-neutral-200 bg-neutral-50 text-neutral-700 hover:bg-neutral-100 shadow-2xs"
                    >
                        <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span>Stock &amp; Movements</span>
                        @if (($openAlertCount ?? 0) > 0)
                            <span class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">{{ $openAlertCount }}</span>
                        @endif
                        <svg class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" :class="openDropdown === 'stock' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div
                        x-show="openDropdown === 'stock'"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1.5 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in-out duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-y-1.5 scale-95"
                        class="absolute left-0 z-40 mt-1.5 w-60 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-1"
                    >
                        <a href="{{ route('inventory.stock-movements') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                            <span class="flex items-center gap-2">
                                <x-ui.icon name="arrows-right-left" class="w-4 h-4 text-neutral-500" />
                                <span>Stock Movements</span>
                            </span>
                        </a>
                        @can(\App\Enums\Permission::AcknowledgeAlerts->value)
                            <a href="{{ route('inventory.alerts') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="bell-alert" class="w-4 h-4 text-rose-500" />
                                    <span>Stock Alerts</span>
                                </span>
                                @if (($openAlertCount ?? 0) > 0)
                                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">{{ $openAlertCount }}</span>
                                @endif
                            </a>
                        @endcan
                    </div>
                </div>

                {{-- Major 2: Requisitions & Transfers --}}
                @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value, \App\Enums\Permission::TransferStock->value])
                    <div class="relative" @click.outside="if (openDropdown === 'req') openDropdown = null">
                        <button
                            type="button"
                            @click="openDropdown = openDropdown === 'req' ? null : 'req'"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-semibold transition border border-neutral-200 bg-neutral-50 text-neutral-700 hover:bg-neutral-100 shadow-2xs"
                        >
                            <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Requisitions &amp; Transfers</span>
                            <svg class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" :class="openDropdown === 'req' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div
                            x-show="openDropdown === 'req'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1.5 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in-out duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1.5 scale-95"
                            class="absolute left-0 z-40 mt-1.5 w-60 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-1"
                        >
                            @canany([\App\Enums\Permission::CreateRequisition->value, \App\Enums\Permission::ApproveRequisition->value, \App\Enums\Permission::IssueStock->value])
                                <a href="{{ route('inventory.requisitions.index') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="document-duplicate" class="w-4 h-4 text-neutral-500" />
                                        <span>Store Requisitions</span>
                                    </span>
                                </a>
                            @endcanany
                            @can(\App\Enums\Permission::TransferStock->value)
                                <a href="{{ route('inventory.transfers.index') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="truck" class="w-4 h-4 text-neutral-500" />
                                        <span>Stock Transfers</span>
                                    </span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- Major 3: Audits & Operations --}}
                @canany([\App\Enums\Permission::PerformCycleCount->value, \App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value, \App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                    <div class="relative" @click.outside="if (openDropdown === 'ops') openDropdown = null">
                        <button
                            type="button"
                            @click="openDropdown = openDropdown === 'ops' ? null : 'ops'"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-semibold transition border border-neutral-200 bg-neutral-50 text-neutral-700 hover:bg-neutral-100 shadow-2xs"
                        >
                            <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span>Audits &amp; Data Operations</span>
                            <svg class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" :class="openDropdown === 'ops' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div
                            x-show="openDropdown === 'ops'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1.5 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in-out duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1.5 scale-95"
                            class="absolute left-0 z-40 mt-1.5 w-60 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-1"
                        >
                            @can(\App\Enums\Permission::PerformCycleCount->value)
                                <a href="{{ route('inventory.cycle-counts.index') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="clipboard-document-check" class="w-4 h-4 text-neutral-500" />
                                        <span>Cycle Counts</span>
                                    </span>
                                </a>
                            @endcan
                            @canany([\App\Enums\Permission::AdjustStock->value, \App\Enums\Permission::ApproveAdjustment->value])
                                <a href="{{ route('inventory.adjustments') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="clipboard-document-list" class="w-4 h-4 text-neutral-500" />
                                        <span>Stock Adjustments</span>
                                    </span>
                                </a>
                            @endcanany
                            @canany([\App\Enums\Permission::ManageItems->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ManageSuppliers->value])
                                <a href="{{ route('inventory.import.index') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="arrow-up-tray" class="w-4 h-4 text-sky-600" />
                                        <span>Import Data</span>
                                    </span>
                                    <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800">CSV/XLS</span>
                                </a>
                            @endcanany
                        </div>
                    </div>
                @endcanany
            </div>
        </div>

        {{-- Inventory Items Table Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
                <div>
                    <h3 class="text-sm font-bold text-neutral-900">Inventory Items Catalog</h3>
                    <p class="text-xs text-neutral-500">Current stock on hand, reorder thresholds, and active suppliers.</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-xs">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">Item</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">SKU</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">Qty</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">Reorder</th>
                            @can(\App\Enums\Permission::ViewSuppliers->value)
                                <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">Supplier</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody id="inventory-items-table-body" class="divide-y divide-neutral-200">
                        @forelse ($items as $item)
                            <tr class="hover:bg-neutral-50/70 transition-colors">
                                <td class="px-3 py-2.5 font-medium text-neutral-900">{{ $item->name }}</td>
                                <td class="px-3 py-2.5 font-mono text-neutral-600">{{ $item->sku }}</td>
                                <td class="px-3 py-2.5 font-semibold {{ $item->quantity_on_hand <= $item->reorder_level ? 'text-amber-600' : 'text-neutral-800' }}">{{ $item->quantity_on_hand }}</td>
                                <td class="px-3 py-2.5 text-neutral-600">{{ $item->reorder_level }}</td>
                                @can(\App\Enums\Permission::ViewSuppliers->value)
                                    <td class="px-3 py-2.5 text-neutral-600">{{ $item->supplier?->name ?? '—' }}</td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value) ? 5 : 4 }}" class="px-3 py-6 text-center text-neutral-500">No inventory items yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.loader id="inventory-api-status" size="sm" label="Loading inventory from API..." class="mt-3 text-xs text-neutral-500" />
        </div>

        {{-- Include Modal --}}
        @include('inventory.items.partials.create_modal')
    </div>

    <script>
        async function loadInventoryItemsFromApi() {
            const status = document.getElementById('inventory-api-status');
            const tbody = document.getElementById('inventory-items-table-body');
            const canViewSuppliers = @json(auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value));

            try {
                await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                const response = await fetch('/api/v1/inventory-items', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const payload = await response.json();
                const items = payload.data || [];

                if (items.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${canViewSuppliers ? 5 : 4}" class="px-3 py-6 text-center text-neutral-500">No inventory items found via API.</td></tr>`;
                } else {
                    tbody.innerHTML = items.map(item => `
                        <tr class="hover:bg-neutral-50/70 transition-colors">
                            <td class="px-3 py-2.5 font-medium text-neutral-900">${item.name}</td>
                            <td class="px-3 py-2.5 font-mono text-neutral-600">${item.sku}</td>
                            <td class="px-3 py-2.5 font-semibold text-neutral-800">${item.quantity_on_hand}</td>
                            <td class="px-3 py-2.5 text-neutral-600">${item.reorder_level}</td>
                            ${canViewSuppliers ? `<td class="px-3 py-2.5 text-neutral-600">${item.supplier ? item.supplier.name : '—'}</td>` : ''}
                        </tr>
                    `).join('');
                }

                status.textContent = 'Inventory loaded from API.';
            } catch (error) {
                console.error(error);
                status.textContent = 'Unable to load inventory from API. Check console for details.';
            }
        }

        document.addEventListener('DOMContentLoaded', loadInventoryItemsFromApi);
    </script>
</x-app-layout>
