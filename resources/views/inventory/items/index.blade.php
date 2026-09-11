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

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm" x-data="{ openDropdown: null }">
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
                            <option value="{{ route('inventory.stock') }}">Stock Levels</option>
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
                            <a href="{{ route('inventory.stock') }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="chart-bar" class="w-4 h-4 text-neutral-500" />
                                    <span>Stock Levels</span>
                                </span>
                            </a>
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
                    <x-ui.field name="barcode_value" label="Internal barcode" maxlength="100" placeholder="Defaults can be printed after creation" />
                    <x-ui.field name="gtin" label="GTIN" inputmode="numeric" pattern="[0-9]{8,14}" maxlength="14" hint="8 to 14 digits; leading zeroes are preserved." />
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
                        name="is_serial_tracked"
                        label="Serial tracking"
                        type="select"
                        :options="['0' => 'No', '1' => 'Yes — one serial per received unit']"
                        value="0"
                        required />
                    <x-ui.field
                        name="is_expiry_tracked"
                        label="Expiry tracking"
                        type="select"
                        :options="['1' => 'Yes — expiry is required on receipt', '0' => 'No']"
                        value="1"
                        required />
                    <x-ui.field name="storage_classification" label="Storage classification" type="select"
                        :options="['general' => 'General', 'medical_supply' => 'Medical supply', 'pharmaceutical' => 'Pharmaceutical', 'sterile' => 'Sterile', 'cold_chain' => 'Cold chain', 'controlled' => 'Controlled / restricted', 'flammable' => 'Flammable', 'hazardous' => 'Hazardous']"
                        placeholder="General / not restricted" />
                    <x-ui.field name="temperature_classification" label="Temperature classification" type="select"
                        :options="['ambient' => 'Ambient', 'controlled_room' => 'Controlled room temperature', 'refrigerated' => 'Refrigerated', 'frozen' => 'Frozen', 'deep_frozen' => 'Deep frozen']"
                        placeholder="Not specified" />
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
                    <x-ui.field name="pick_face_minimum" label="Pick-face minimum" type="number" value="0" min="0" step="1" inputmode="numeric" />
                    <x-ui.field name="pick_face_maximum" label="Pick-face maximum" type="number" value="0" min="0" step="1" inputmode="numeric" hint="Use 0 when no pick-face limit is configured." />
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
                                @can(\App\Enums\Permission::ViewSuppliers->value)
                                    <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Supplier</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody id="inventory-items-table-body" class="divide-y divide-[var(--border)]">
                            @forelse ($items as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item->name }}</td>
                                    <td class="px-3 py-2">{{ $item->sku }}</td>
                                    <td class="px-3 py-2">{{ $item->quantity_on_hand }}</td>
                                    <td class="px-3 py-2">{{ $item->reorder_level }}</td>
                                    @can(\App\Enums\Permission::ViewSuppliers->value)
                                        <td class="px-3 py-2">{{ $item->supplier?->name ?? '—' }}</td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value) ? 5 : 4 }}" class="px-3 py-4 text-[var(--muted)]">No inventory items yet.</td>
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
                    tbody.innerHTML = `<tr><td colspan="${canViewSuppliers ? 5 : 4}" class="px-3 py-4 text-[var(--muted)]">No inventory items found via API.</td></tr>`;
                } else {
                    tbody.innerHTML = items.map(item => `
                        <tr>
                            <td class="px-3 py-2">${item.name}</td>
                            <td class="px-3 py-2">${item.sku}</td>
                            <td class="px-3 py-2">${item.quantity_on_hand}</td>
                            <td class="px-3 py-2">${item.reorder_level}</td>
                            ${canViewSuppliers ? `<td class="px-3 py-2">${item.supplier ? item.supplier.name : '—'}</td>` : ''}
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
