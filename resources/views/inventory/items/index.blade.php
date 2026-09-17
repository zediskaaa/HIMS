@php
    $activeFilterCount = collect($filters)->filter(fn ($value) => filled($value))->count();
@endphp

<x-app-layout full-width>
    <div x-data="{ createItemModal: {{ $errors->any() ? 'true' : 'false' }}, openDropdown: null }"
         @keydown.escape.window="createItemModal = false"
         class="space-y-6">

        <x-ui.page-header
            title="Inventory Items"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory Items' => null]" />

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

        {{-- Inventory Items Table Card --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 p-4 sm:p-5 dark:border-neutral-800/80">
                <div>
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-neutral-100">Inventory Items Catalog</h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ number_format($items->total()) }} matching {{ str('item')->plural($items->total()) }} · Current stock on hand, reorder thresholds, and active suppliers.</p>
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
            <form method="GET" action="{{ route('inventory.items') }}" class="p-4 sm:p-5 border-b border-neutral-100 dark:border-neutral-800/80" role="search">
                <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                    <div class="relative flex-1 min-w-0">
                        <label for="item-search" class="sr-only">Search inventory items</label>
                        <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        <input id="item-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search item name, SKU, or barcode"
                               class="block w-full rounded-lg border border-neutral-300 bg-white py-2 pl-9 pr-3 text-xs text-neutral-900 shadow-2xs placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                    </div>
                    <div class="w-full sm:w-56 lg:w-64 shrink-0">
                        <label class="sr-only" for="item-category">Item category</label>
                        <select id="item-category" name="category_id" class="block w-full rounded-lg border border-neutral-300 bg-white py-2 pl-3 pr-8 text-xs text-neutral-900 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            <option value="">All categories</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($filters['category_id'] ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-44 lg:w-48 shrink-0">
                        <label class="sr-only" for="item-status">Stock state</label>
                        <select id="item-status" name="status" class="block w-full rounded-lg border border-neutral-300 bg-white py-2 pl-3 pr-8 text-xs text-neutral-900 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            <option value="">All stock states</option>
                            @foreach ($stockStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <x-ui.button type="submit" size="sm" class="px-4 py-2 text-xs">Apply</x-ui.button>
                        @if ($activeFilterCount > 0)
                            <x-ui.button variant="ghost" size="sm" :href="route('inventory.items')" aria-label="Clear all inventory item filters" class="px-3 py-2 text-xs">Clear</x-ui.button>
                        @endif
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto min-w-full">
                <table class="w-full min-w-full divide-y divide-neutral-200 text-xs dark:divide-neutral-800">
                    <thead class="bg-neutral-50 dark:bg-neutral-800/60">
                        <tr>
                            <th scope="col" class="w-auto min-w-[280px] lg:min-w-[340px] px-3.5 py-2.5 text-left font-semibold text-neutral-600 dark:text-neutral-300">Item</th>
                            <th scope="col" class="w-36 min-w-[130px] px-3 py-2.5 text-left font-semibold text-neutral-600 dark:text-neutral-300 whitespace-nowrap">SKU</th>
                            <th scope="col" class="w-20 min-w-[70px] px-3 py-2.5 text-left font-semibold text-neutral-600 dark:text-neutral-300 whitespace-nowrap">Unit</th>
                            <th scope="col" class="w-40 min-w-[140px] px-3 py-2.5 text-left font-semibold text-neutral-600 dark:text-neutral-300 whitespace-nowrap">Qty</th>
                            <th scope="col" class="w-24 min-w-[80px] px-3 py-2.5 text-left font-semibold text-neutral-600 dark:text-neutral-300 whitespace-nowrap">Reorder</th>
                            @can(\App\Enums\Permission::ViewSuppliers->value)
                                <th scope="col" class="w-56 min-w-[180px] px-3.5 py-2.5 text-left font-semibold text-neutral-600 dark:text-neutral-300 whitespace-nowrap">Supplier</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody id="inventory-items-table-body" class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        @forelse ($items as $item)
                            <tr class="transition-colors hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50">
                                <td class="px-3.5 py-2.5 font-medium text-neutral-900 dark:text-neutral-100">{{ $item->name }}</td>
                                <td class="px-3 py-2.5 font-mono text-neutral-600 dark:text-neutral-400 whitespace-nowrap">{{ $item->sku }}</td>
                                <td class="px-3 py-2.5 text-neutral-600 dark:text-neutral-400 whitespace-nowrap">{{ $item->unit ?: 'unit' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $item->quantity_on_hand }}</span>
                                        {{-- Derived from the quantity against the reorder level, so the
                                             badge cannot drift from the figures in the same row. --}}
                                        @php($stockStatus = $item->stockStatus())
                                        @if (in_array($stockStatus, ['low_stock', 'out_of_stock'], true))
                                            <x-ui.badge :status="$stockStatus" dot />
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-neutral-600 dark:text-neutral-400 whitespace-nowrap">{{ $item->reorder_level }}</td>
                                @can(\App\Enums\Permission::ViewSuppliers->value)
                                    <td class="px-3.5 py-2.5 text-neutral-600 dark:text-neutral-400 whitespace-nowrap truncate max-w-[220px]" title="{{ $item->supplier?->name ?? '—' }}">
                                        {{ $item->supplier?->name ?? '—' }}
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can(\App\Enums\Permission::ViewSuppliers->value) ? 6 : 5 }}" class="px-3 py-8 text-center text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $activeFilterCount > 0 ? 'No items match these filters.' : 'No inventory items yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="border-t border-neutral-200 px-4 py-3 sm:px-5 dark:border-neutral-800">
                    {{ $items->links() }}
                </div>
            @endif
        </div>

        {{-- Include Modal --}}
        @include('inventory.items.partials.create_modal')
    </div>
</x-app-layout>
