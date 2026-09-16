@php
    $activeFilterCount = collect($filters)->filter(fn ($value) => filled($value))->count();
@endphp

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
                    <p class="text-xs text-neutral-500">{{ number_format($items->count()) }} matching {{ str('item')->plural($items->count()) }} · Current stock on hand, reorder thresholds, and active suppliers.</p>
                </div>
                @can(\App\Enums\Permission::ManageItems->value)
                    <x-ui.button variant="primary" size="sm" icon="plus" @click="createItemModal = true" id="btn-open-create-item-modal">
                        Create Inventory Item
                    </x-ui.button>
                @endcan
            </div>

            {{-- Filtering runs server-side so the rows, the reorder badges and
                 the supplier column always come from the same authorized
                 response instead of being narrowed in the browser. --}}
            <form method="GET" action="{{ route('inventory.items') }}" class="mt-4" role="search">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(15rem,1fr)_minmax(10rem,0.55fr)_minmax(9rem,0.45fr)_auto]">
                    <div class="relative">
                        <label for="item-search" class="sr-only">Search inventory items</label>
                        <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-neutral-400" />
                        <input id="item-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search item name, SKU, or barcode"
                               class="block min-h-10 w-full rounded-md border border-neutral-300 py-2 pl-10 pr-3 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                    </div>
                    <label class="sr-only" for="item-category">Item category</label>
                    <select id="item-category" name="category_id" class="block min-h-10 w-full rounded-md border border-neutral-300 px-3 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                        <option value="">All categories</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected((string) ($filters['category_id'] ?? '') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="item-status">Stock state</label>
                    <select id="item-status" name="status" class="block min-h-10 w-full rounded-md border border-neutral-300 px-3 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                        <option value="">All stock states</option>
                        @foreach ($stockStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                        <x-ui.button type="submit" class="flex-1 lg:flex-none">Apply</x-ui.button>
                        @if ($activeFilterCount > 0)
                            <x-ui.button variant="ghost" :href="route('inventory.items')" aria-label="Clear all inventory item filters">Clear</x-ui.button>
                        @endif
                    </div>
                </div>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-xs">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">Item</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">SKU</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-neutral-600">Unit</th>
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
                                <td class="px-3 py-2.5 text-neutral-600">{{ $item->unit ?: 'unit' }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-semibold text-neutral-800">{{ $item->quantity_on_hand }}</span>
                                        {{-- Derived from the quantity against the reorder level, so the
                                             badge cannot drift from the figures in the same row. --}}
                                        @php($stockStatus = $item->stockStatus())
                                        @if (in_array($stockStatus, ['low_stock', 'out_of_stock'], true))
                                            <x-ui.badge :status="$stockStatus" dot />
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-neutral-600">{{ $item->reorder_level }}</td>
                                @can(\App\Enums\Permission::ViewSuppliers->value)
                                    <td class="px-3 py-2.5 text-neutral-600">{{ $item->supplier?->name ?? '—' }}</td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value) ? 6 : 5 }}" class="px-3 py-6 text-center text-neutral-500">
                                    {{ $activeFilterCount > 0 ? 'No items match these filters.' : 'No inventory items yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Include Modal --}}
        @include('inventory.items.partials.create_modal')
    </div>
</x-app-layout>
