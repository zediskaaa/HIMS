<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Inventory Items
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- The catalogue is readable by anyone with view_inventory, but only
                 manage_items may add to it, so the form is hidden rather than
                 shown-and-refused. --}}
            @can(\App\Enums\Permission::ManageItems->value)
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Create Inventory Item</h3>
                <form method="POST" action="{{ route('inventory.items.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <x-ui.field name="name" label="Item name" required />
                    <x-ui.field name="sku" label="SKU" required />
                    <x-ui.field name="category_id" label="Category" type="select" :options="$categories" placeholder="Select category" />
                    <div>
                        <x-ui.field name="unit" label="Unit of measure" list="inventory-unit-options" placeholder="Select or enter a unit" maxlength="50" />
                        <datalist id="inventory-unit-options">
                            @foreach ($unitOptions as $unit)
                                <option value="{{ $unit }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <x-ui.field
                        name="is_batch_tracked"
                        label="Batch / lot tracking"
                        type="select"
                        :options="['1' => 'Yes — track batches and expiry', '0' => 'No — quantity by location only']"
                        value="1"
                        required />
                    <x-ui.field
                        name="default_location_id"
                        label="Default storage location"
                        type="select"
                        :options="$locations"
                        placeholder="Select location"
                        hint="Required when recording an opening quantity." />
                    <x-ui.field
                        name="initial_quantity"
                        label="Opening quantity"
                        type="number"
                        value="0"
                        min="0"
                        step="1"
                        inputmode="numeric"
                        hint="Recorded as a Stock In movement so the warehouse ledger stays accurate." />
                    <x-ui.field name="reorder_level" label="Reorder level" type="number" value="0" min="0" step="1" inputmode="numeric" />
                    <x-ui.field name="expiry_alert_days" label="Expiry alert lead time (days)" type="number" value="30" min="0" max="3650" step="1" inputmode="numeric" />
                    <x-ui.field name="unit_cost" label="Unit cost" type="number" value="0.00" min="0" max="9999999999.99" step="0.01" inputmode="decimal" />
                    <x-ui.field
                        name="batch_number"
                        label="Opening batch / lot number"
                        hint="Required only when opening quantity is above zero and batch tracking is enabled." />
                    <x-ui.field name="expiry_date" label="Opening batch expiry date" type="date" min="{{ today()->toDateString() }}" />
                    <div class="md:col-span-2">
                        <x-ui.field name="supplier_id" label="Supplier" type="select" placeholder="No supplier / select later">
                            @foreach ($eligibleSuppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                                    {{ $supplier->name }} — Accredited
                                </option>
                            @endforeach
                            @if ($unavailableSuppliers->isNotEmpty())
                                <optgroup label="Unavailable for procurement">
                                    @foreach ($unavailableSuppliers as $supplier)
                                        <option value="{{ $supplier->id }}" disabled>
                                            {{ $supplier->name }} — {{ $supplier->eligibility_reason }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </x-ui.field>
                        <p class="mt-1.5 text-xs text-neutral-500">
                            Only active, approved, and compliant suppliers can be selected.
                            @can(\App\Enums\Permission::ManageSuppliers->value)
                                <a href="{{ route('inventory.suppliers') }}" class="font-medium text-primary-700 hover:underline">Review supplier accreditation</a>.
                            @endcan
                        </p>
                        @if ($eligibleSuppliers->isEmpty())
                            <x-ui.alert variant="warning" title="No accredited supplier is selectable yet" class="mt-3">
                                Existing supplier records are shown above as unavailable until their accreditation and compliance review is completed. You may save the item without a supplier and link one later.
                            </x-ui.alert>
                        @endif
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.button type="submit" data-loading-text="Saving item...">Save Item</x-ui.button>
                    </div>
                </form>
            </div>
            @endcan

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Inventory List</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--background)]">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Item</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">SKU</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Qty</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Reorder</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Supplier</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-items-table-body" class="divide-y divide-[var(--border)]">
                            @forelse ($items as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item->name }}</td>
                                    <td class="px-3 py-2">{{ $item->sku }}</td>
                                    <td class="px-3 py-2">{{ $item->quantity_on_hand }}</td>
                                    <td class="px-3 py-2">{{ $item->reorder_level }}</td>
                                    <td class="px-3 py-2">{{ $item->supplier?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-[var(--muted)]">No inventory items yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-ui.loader id="inventory-api-status" size="sm" label="Loading inventory from API..." class="mt-3 text-sm text-[var(--muted)]" />
            </div>
        </div>
    </div>

    <script>
        async function loadInventoryItemsFromApi() {
            const status = document.getElementById('inventory-api-status');
            const tbody = document.getElementById('inventory-items-table-body');

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
                    tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-[var(--muted)]">No inventory items found via API.</td></tr>`;
                } else {
                    tbody.innerHTML = items.map(item => `
                        <tr>
                            <td class="px-3 py-2">${item.name}</td>
                            <td class="px-3 py-2">${item.sku}</td>
                            <td class="px-3 py-2">${item.quantity_on_hand}</td>
                            <td class="px-3 py-2">${item.reorder_level}</td>
                            <td class="px-3 py-2">${item.supplier ? item.supplier.name : '—'}</td>
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
