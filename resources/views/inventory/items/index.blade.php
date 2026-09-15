<x-app-layout>
    <div x-data="{ createItemModal: {{ $errors->any() ? 'true' : 'false' }}, openDropdown: null }"
         @keydown.escape.window="createItemModal = false"
         class="space-y-6">

        <x-ui.page-header
            title="Inventory Items"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory Items' => null]" />

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

        {{-- Inventory Items Table Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
                <div>
                    <h3 class="text-sm font-bold text-neutral-900">Inventory Items Catalog</h3>
                    <p class="text-xs text-neutral-500">Current stock on hand, reorder thresholds, and active suppliers.</p>
                </div>
                @can(\App\Enums\Permission::ManageItems->value)
                    <x-ui.button variant="primary" size="sm" icon="plus" @click="createItemModal = true" id="btn-open-create-item-modal">
                        Create Inventory Item
                    </x-ui.button>
                @endcan
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
