<x-app-layout :full-width="true">
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Spatial Topology</p>
                <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Warehouse Storage Locations</h2>
            </div>
            <div class="flex items-center gap-2">
                @can(\App\Enums\Permission::ManageWarehouseTopology->value)
                    <button type="button" x-data @click="$dispatch('open-modal', 'add-storage-location')" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Add Storage Bin
                    </button>
                @endcan
                <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 shadow-2xs hover:bg-neutral-50 dark:hover:bg-neutral-700">
                    &larr; Warehouse Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div x-data="locationItemsManager()" class="space-y-6">

        {{-- SWS Consolidated Workflow Navigation --}}
        @include('inventory.warehousing.partials.workflow_nav')

        @if(session('success'))
            <x-ui.alert variant="success" :message="session('success')" />
        @endif
        <template x-if="statusSuccessMessage">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/60 p-4 text-sm text-emerald-800 dark:text-emerald-300 flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="statusSuccessMessage"></span>
                </div>
                <button type="button" @click="statusSuccessMessage = null" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
        @if($errors->any())
            <x-ui.alert variant="danger" title="Validation errors occurred">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-ui.alert>
        @endif

        {{-- Filter Bar --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
            <form method="GET" action="{{ route('inventory.warehousing.locations') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="search" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Search</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Code, name, or barcode..." class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="type" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Location Type</label>
                    <select id="type" name="type" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">All Types</option>
                        @foreach(['warehouse' => 'Warehouse', 'zone' => 'Zone', 'aisle' => 'Aisle', 'rack' => 'Rack', 'shelf' => 'Shelf', 'bin' => 'Bin', 'cold_room' => 'Cold Room', 'vault' => 'Narcotics Vault'] as $k => $v)
                            <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Status</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="thermal" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Thermal Zone</label>
                    <select id="thermal" name="thermal" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">All Temperatures</option>
                        <option value="ambient" @selected(request('thermal') === 'ambient')>Ambient (15°C - 25°C)</option>
                        <option value="refrigerated" @selected(request('thermal') === 'refrigerated')>Refrigerated (2°C - 8°C)</option>
                        <option value="frozen" @selected(request('thermal') === 'frozen')>Frozen (-20°C)</option>
                        <option value="ultra_cold" @selected(request('thermal') === 'ultra_cold')>Ultra-Cold (-80°C)</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full rounded-lg bg-neutral-900 dark:bg-neutral-100 px-4 py-2 text-sm font-semibold text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-white shadow-2xs">
                        Apply Filters
                    </button>
                    <a href="{{ route('inventory.warehousing.locations') }}" class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 shadow-2xs">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Locations Registry Table --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-800">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 sm:px-5">Location & Coordinate</th>
                            <th class="px-4 py-3 sm:px-5">Type & Topology</th>
                            <th class="px-4 py-3 sm:px-5">Thermal Class</th>
                            <th class="px-4 py-3 sm:px-5">Capacity & Occupancy</th>
                            <th class="px-4 py-3 sm:px-5">Status</th>
                            <th class="px-4 py-3 sm:px-5">Special Designation</th>
                            <th class="px-4 py-3 sm:px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse($locations as $loc)
                            <tr id="location-row-{{ $loc->id }}" data-location-id="{{ $loc->id }}" data-location-code="{{ $loc->code }}" data-location-name="{{ $loc->name }}" class="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50 transition-colors">
                                <td class="px-4 py-2.5 sm:px-5 sm:py-3">
                                    <div class="font-mono text-sm font-bold text-neutral-900 dark:text-neutral-100">{{ $loc->code }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $loc->name }}</div>
                                    @if($loc->barcode_value)
                                        <div class="font-mono text-[11px] text-neutral-400 dark:text-neutral-500">Barcode: {{ $loc->barcode_value }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 sm:px-5 sm:py-3">
                                    <span class="inline-flex rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 capitalize">
                                        {{ str_replace('_', ' ', $loc->type) }}
                                    </span>
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $loc->fullPath() }}
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 sm:px-5 sm:py-3">
                                    @if($loc->temperature_classification === 'refrigerated')
                                        <span class="inline-flex items-center gap-1 rounded bg-cyan-100 px-2.5 py-0.5 text-xs font-medium text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300">
                                            Cold Chain (2°C-8°C)
                                        </span>
                                    @elseif($loc->temperature_classification === 'frozen')
                                        <span class="inline-flex items-center gap-1 rounded bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                            Frozen (-20°C)
                                        </span>
                                    @elseif($loc->temperature_classification === 'ultra_cold')
                                        <span class="inline-flex items-center gap-1 rounded bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                                            Ultra-Cold (-80°C)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                            Ambient (15°C-25°C)
                                        </span>
                                    @endif
                                    @if($loc->excursion_hold)
                                        <div class="mt-1"><span class="rounded bg-red-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">EXCURSION HOLD</span></div>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 sm:px-5 sm:py-3">
                                    @if($loc->capacity)
                                        <div class="w-32">
                                            <div class="flex justify-between text-xs text-neutral-600 dark:text-neutral-400">
                                                <span>{{ $loc->totalQuantity() }} / {{ $loc->capacity }}</span>
                                                <span>{{ $loc->utilisation() }}%</span>
                                            </div>
                                            <div class="mt-1 h-2 w-full rounded-full bg-neutral-200 dark:bg-neutral-700">
                                                <div class="h-2 rounded-full @if($loc->utilisation() > 90) bg-red-500 @elseif($loc->utilisation() > 70) bg-amber-500 @else bg-emerald-500 @endif" style="width: {{ min(100, $loc->utilisation() ?? 0) }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-neutral-400 dark:text-neutral-500">Unrestricted</span>
                                    @endif
                                    @if($loc->max_weight_kg)
                                        <div class="mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">Max {{ $loc->max_weight_kg }} kg</div>
                                    @endif
                                </td>
                                <td id="location-status-cell-{{ $loc->id }}" class="px-4 py-2.5 sm:px-5 sm:py-3 whitespace-nowrap">
                                    <x-ui.badge :status="$loc->status" :dot="true" />
                                </td>
                                <td class="px-4 py-2.5 sm:px-5 sm:py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if($loc->is_narcotics_vault)
                                             <span class="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-purple-800 dark:bg-purple-950 dark:text-purple-300">Narcotics Vault</span>
                                        @endif
                                        @if($loc->is_hazardous_containment)
                                            <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-800 dark:bg-rose-950 dark:text-rose-300">Hazardous / Cytotoxic</span>
                                        @endif
                                        @if($loc->is_pick_face)
                                            <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-blue-800 dark:bg-blue-950 dark:text-blue-300">Pick Face</span>
                                        @endif
                                        @if($loc->is_reserve)
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">Reserve</span>
                                        @endif
                                        @if($loc->is_quarantine)
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-950 dark:text-amber-300">Quarantine</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 sm:px-5 sm:py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="viewLocationItems({{ $loc->id }}, '{{ addslashes($loc->code) }}', '{{ addslashes($loc->name) }}')"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 shadow-2xs hover:bg-primary-100 hover:border-primary-300 dark:border-primary-800 dark:bg-primary-950/60 dark:text-primary-300 dark:hover:bg-primary-900/80 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
                                            title="View items stored in {{ $loc->name }} ({{ $loc->code }})">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            <span>See Items</span>
                                            <span class="rounded-full bg-primary-200/80 dark:bg-primary-800 px-1.5 py-0.2 text-[10px] font-bold text-primary-900 dark:text-primary-100">
                                                {{ $loc->active_items_count ?? 0 }}
                                            </span>
                                        </button>

                                        @if(auth()->user()?->isSuperAdministrator())
                                            <span id="location-status-btn-container-{{ $loc->id }}" class="inline-flex items-center">
                                                @if($loc->status === 'active')
                                                    <button
                                                        type="button"
                                                        id="btn-deactivate-location-{{ $loc->id }}"
                                                        @click="openDeactivateModal({{ $loc->id }}, '{{ addslashes($loc->code) }}', '{{ addslashes($loc->name) }}', {{ $loc->active_items_count ?? 0 }}, {{ $loc->totalQuantity() }}, {{ $loc->pending_inbound_count ?? $loc->pendingInboundCount() }})"
                                                        data-confirm-prompt="Are you sure you want to deactivate {{ $loc->code }} ({{ $loc->name }})?"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/60 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/80 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500"
                                                        title="Deactivate this storage location">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                        <span>Deactivate</span>
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        id="btn-activate-location-{{ $loc->id }}"
                                                        @click="openActivateModal({{ $loc->id }}, '{{ addslashes($loc->code) }}', '{{ addslashes($loc->name) }}')"
                                                        data-confirm-prompt="Are you sure you want to reactivate {{ $loc->code }} ({{ $loc->name }})?"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/80 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                                        title="Reactivate this storage location">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        <span>Activate</span>
                                                    </button>
                                                @endif
                                            </span>
                                        @endif

                                        @can(\App\Enums\Permission::PrintWarehouseLabels->value)
                                        <form method="POST" action="{{ route('inventory.storage-locations.label', $loc) }}" target="_blank" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="copies" value="1">
                                            <button type="submit" title="Print location barcode label" class="rounded border border-neutral-300 p-1.5 text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                                <span class="sr-only">Print location barcode label</span>
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                    No storage locations found matching criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($locations->hasPages())
                <div class="border-t border-neutral-200 px-4 py-3 sm:px-5 dark:border-neutral-800">
                    {{ $locations->links() }}
                </div>
            @endif
        </div>

        {{-- Location Items Inventory Modal --}}
        <div
            x-show="openModal"
            x-cloak
            x-on:keydown.escape.window="closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="location-modal-title"
        >
            {{-- Backdrop --}}
            <div
                x-show="openModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeModal()"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            {{-- Modal Dialog Panel --}}
            <div
                x-show="openModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative flex max-h-[calc(100dvh-3rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900"
            >
                {{-- Header --}}
                <header class="flex items-start justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex rounded bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-800 dark:bg-primary-950/80 dark:text-primary-300 font-mono" x-text="location?.code"></span>
                            <span class="inline-flex rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 capitalize" x-text="location?.type"></span>
                        </div>
                        <h3 id="location-modal-title" class="mt-1 text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Items in <span x-text="location?.name"></span>
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            <span x-text="location?.full_path"></span>
                            <span class="mx-1.5 text-neutral-300 dark:text-neutral-700">&bull;</span>
                            <span class="font-semibold text-neutral-700 dark:text-neutral-300" x-text="pagination.total + ' ' + (pagination.total === 1 ? 'item' : 'items') + ' recorded'"></span>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal()"
                        class="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        aria-label="Close modal"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                {{-- Toolbar: Search & Summary Info --}}
                <div class="border-b border-neutral-200 bg-neutral-50/75 px-5 py-3 dark:border-neutral-800 dark:bg-neutral-800/40">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative max-w-sm flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 dark:text-neutral-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input
                                type="text"
                                x-model="search"
                                @input.debounce.300ms="onSearchInput()"
                                placeholder="Search item, SKU, barcode, batch..."
                                class="w-full rounded-lg border-neutral-300 bg-white pl-9 pr-8 text-xs text-neutral-900 placeholder-neutral-400 focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder-neutral-500"
                            >
                            <button
                                type="button"
                                x-show="search.length > 0"
                                @click="search = ''; fetchItems(1)"
                                class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-3">
                            <span x-show="pagination.total > 0">
                                Showing <strong class="text-neutral-700 dark:text-neutral-200" x-text="pagination.from || 0"></strong>–<strong class="text-neutral-700 dark:text-neutral-200" x-text="pagination.to || 0"></strong> of <strong class="text-neutral-700 dark:text-neutral-200" x-text="pagination.total"></strong> items
                            </span>
                            <span class="inline-flex items-center gap-1 rounded bg-neutral-200/70 px-2 py-0.5 text-[11px] font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                Total Units: <strong class="ml-1 font-mono text-neutral-900 dark:text-neutral-100" x-text="location?.total_quantity ?? 0"></strong>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Modal Body (Internal scroll only) --}}
                <div class="min-h-0 flex-1 overflow-y-auto overflow-x-auto p-4 sm:p-5">
                    {{-- Loading State --}}
                    <div x-show="loading" class="flex flex-col items-center justify-center py-12 text-neutral-500 dark:text-neutral-400">
                        <svg class="h-8 w-8 animate-spin text-primary-600 mb-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <p class="text-sm font-medium">Loading inventory for <span class="font-mono font-semibold" x-text="location?.code"></span>...</p>
                    </div>

                    {{-- Error State --}}
                    <div x-show="!loading && errorMessage" class="rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-900/60 dark:bg-danger-950/40 text-danger-800 dark:text-danger-300 text-sm">
                        <div class="flex items-center justify-between">
                            <span x-text="errorMessage"></span>
                            <button type="button" @click="fetchItems(pagination.current_page)" class="text-xs font-semibold underline hover:no-underline">Retry</button>
                        </div>
                    </div>

                    {{-- Empty State --}}
                    <div x-show="!loading && !errorMessage && items.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <template x-if="search.length === 0">
                            <div>
                                <h4 class="mt-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No items are currently stored in this location.</h4>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">This storage coordinate currently has zero physical stock recorded in the inventory ledger.</p>
                            </div>
                        </template>
                        <template x-if="search.length > 0">
                            <div>
                                <h4 class="mt-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No matching items found</h4>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">No inventory in this location matches "<span class="font-medium" x-text="search"></span>".</p>
                                <button type="button" @click="search = ''; fetchItems(1)" class="mt-2 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                    Clear search filter
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Inventory Items Table --}}
                    <div x-show="!loading && !errorMessage && items.length > 0" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
                        <table class="min-w-full divide-y divide-neutral-200 text-left text-xs dark:divide-neutral-800">
                            <thead class="bg-neutral-50 text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                <tr>
                                    <th class="px-3.5 py-2.5">Item & SKU</th>
                                    <th class="px-3.5 py-2.5">Category</th>
                                    <th class="px-3.5 py-2.5">Batch / Lot</th>
                                    <th class="px-3.5 py-2.5">Expiry Date</th>
                                    <th class="px-3.5 py-2.5 text-right">Location Qty</th>
                                    <th class="px-3.5 py-2.5">Unit</th>
                                    <th class="px-3.5 py-2.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                                <template x-for="item in items" :key="item.id">
                                    <tr class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50 transition-colors">
                                        <td class="px-3.5 py-2.5">
                                            <div class="font-medium text-neutral-900 dark:text-neutral-100" x-text="item.name"></div>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="font-mono text-[11px] text-neutral-500 dark:text-neutral-400" x-text="'SKU: ' + item.sku"></span>
                                                <template x-if="item.barcode">
                                                    <span class="font-mono text-[10px] text-neutral-400 dark:text-neutral-500" x-text="'• Barcode: ' + item.barcode"></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            <span class="inline-flex rounded bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300" x-text="item.category"></span>
                                        </td>
                                        <td class="px-3.5 py-2.5 font-mono">
                                            <template x-if="item.batch_number">
                                                <div>
                                                    <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="item.batch_number"></span>
                                                    <template x-if="item.lot_number">
                                                        <div class="text-[10px] text-neutral-400 dark:text-neutral-500" x-text="'Lot: ' + item.lot_number"></div>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="!item.batch_number">
                                                <span class="text-neutral-400 dark:text-neutral-500 italic">Unbatched</span>
                                            </template>
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            <template x-if="item.expiry_date">
                                                <div>
                                                    <span class="font-mono" x-text="item.expiry_formatted || item.expiry_date"></span>
                                                    <template x-if="item.is_expired">
                                                        <span class="ml-1.5 inline-flex rounded bg-red-100 px-1.5 py-0.2 text-[10px] font-bold uppercase text-red-800 dark:bg-red-950 dark:text-red-300">Expired</span>
                                                    </template>
                                                    <template x-if="!item.is_expired && item.is_expiring_soon">
                                                        <span class="ml-1.5 inline-flex rounded bg-amber-100 px-1.5 py-0.2 text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-950 dark:text-amber-300">Soon</span>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="!item.expiry_date">
                                                <span class="text-neutral-400 dark:text-neutral-500">—</span>
                                            </template>
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right font-mono">
                                            <div class="font-bold text-sm text-neutral-900 dark:text-neutral-100" x-text="item.quantity.toLocaleString()"></div>
                                            <template x-if="item.reserved_quantity > 0">
                                                <div class="text-[10px] text-neutral-500 dark:text-neutral-400" x-text="'Avail: ' + item.available_quantity.toLocaleString()"></div>
                                            </template>
                                        </td>
                                        <td class="px-3.5 py-2.5 text-neutral-600 dark:text-neutral-400 capitalize" x-text="item.unit"></td>
                                        <td class="px-3.5 py-2.5 text-center">
                                            <template x-if="item.stock_status === 'out_of_stock'">
                                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800 dark:bg-red-950 dark:text-red-300">Out of Stock</span>
                                            </template>
                                            <template x-if="item.stock_status === 'low_stock'">
                                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Low Stock</span>
                                            </template>
                                            <template x-if="item.stock_status !== 'out_of_stock' && item.stock_status !== 'low_stock'">
                                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">In Stock</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Footer with Pagination --}}
                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 bg-neutral-50/80 px-5 py-3 dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="changePage(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1"
                            class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-2xs hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-750"
                        >
                            &larr; Prev
                        </button>
                        <span class="px-2 text-xs text-neutral-500 dark:text-neutral-400">
                            Page <strong class="text-neutral-900 dark:text-neutral-100" x-text="pagination.current_page"></strong> of <strong class="text-neutral-900 dark:text-neutral-100" x-text="pagination.last_page"></strong>
                        </span>
                        <button
                            type="button"
                            @click="changePage(pagination.current_page + 1)"
                            :disabled="pagination.current_page >= pagination.last_page"
                            class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-2xs hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-750"
                        >
                            Next &rarr;
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="closeModal()"
                        class="rounded-lg border border-neutral-300 bg-white px-4 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                    >
                        Close
                    </button>
                </footer>
            </div>
        </div>

        {{-- Step 1: Deactivation Reason & Impact Modal (Super Admin only) --}}
        @if(auth()->user()?->isSuperAdministrator())
        <div
            x-show="deactivateModal"
            x-cloak
            x-on:keydown.escape.window="closeDeactivateModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="deactivate-modal-title"
        >
            <div
                x-show="deactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDeactivateModal()"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="deactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-lg rounded-xl border border-amber-200 bg-white shadow-xl dark:border-amber-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="deactivate-modal-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Deactivate Storage Location
                        </h3>
                        <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                            Specify the reason for deactivation and review location inventory before proceeding to confirmation.
                        </p>
                    </div>
                </div>

                {{-- Location Impact Summary Card --}}
                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/50 p-3.5 text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Location:</span>
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100 font-mono" x-text="(targetLocation?.code || '') + ' — ' + (targetLocation?.name || '')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Current status:</span>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Active</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Distinct items:</span>
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="targetLocation?.itemsCount || 0"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Remaining stock:</span>
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="(targetLocation?.totalStock || 0).toLocaleString() + ' units'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Pending inbound transactions:</span>
                        <span class="font-semibold" :class="(targetLocation?.pendingInbound || 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-900 dark:text-neutral-100'" x-text="targetLocation?.pendingInbound || 0"></span>
                    </div>
                </div>

                <template x-if="(targetLocation?.pendingInbound || 0) > 0">
                    <div class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/60 p-3 text-xs text-amber-800 dark:text-amber-300">
                        <strong>Notice:</strong> There are <span class="font-bold" x-text="targetLocation?.pendingInbound"></span> pending inbound transaction(s) destined for this location. Incoming deliveries and transfers cannot be received into this location once deactivated.
                    </div>
                </template>

                <template x-if="submitError">
                    <div class="rounded-lg border border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/60 p-3 text-xs text-red-800 dark:text-red-300 flex items-center justify-between">
                        <span x-text="submitError"></span>
                        <button type="button" @click="submitError = null" class="text-red-500 hover:text-red-700">&times;</button>
                    </div>
                </template>

                <form @submit.prevent="openDeactivateConfirmModal()" class="space-y-4">
                    <div>
                        <label for="deactivate-reason" class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                            Reason for Deactivation <span class="text-red-500">*</span> <span class="font-normal text-neutral-500">(Required for audit trail)</span>
                        </label>
                        <textarea
                            id="deactivate-reason"
                            name="reason"
                            rows="2"
                            x-model="reason"
                            required
                            placeholder="e.g. Warehouse under renovation, consolidation to Main Warehouse..."
                            class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:border-amber-500 focus:ring-amber-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="closeDeactivateModal()"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            id="proceed-deactivate-btn"
                            class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                        >
                            <span>Deactivate Location</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Step 2: Confirmation Modal for Deactivation (with Yes / Cancel) --}}
        <div
            x-show="confirmDeactivateModal"
            x-cloak
            x-on:keydown.escape.window="closeDeactivateConfirmModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-deactivate-title"
        >
            <div
                x-show="confirmDeactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDeactivateConfirmModal()"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="confirmDeactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-md rounded-xl border border-amber-200 bg-white shadow-xl dark:border-amber-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="confirm-deactivate-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Are you sure you want to deactivate this storage location?
                        </h3>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            Are you sure you want to deactivate <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="targetLocation?.code"></span> (<span x-text="targetLocation?.name"></span>)? Once deactivated, this location cannot receive new inventory or Purchase Orders. Existing stock can still be released or transferred out. The location and its history will remain intact.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 p-3 text-xs space-y-1">
                    <div class="text-neutral-500 dark:text-neutral-400">Recorded Reason:</div>
                    <div class="font-medium text-neutral-800 dark:text-neutral-200 italic" x-text="reason"></div>
                </div>

                <template x-if="submitError">
                    <div class="rounded-lg border border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/60 p-3 text-xs text-red-800 dark:text-red-300 flex items-center justify-between">
                        <span x-text="submitError"></span>
                        <button type="button" @click="submitError = null" class="text-red-500 hover:text-red-700">&times;</button>
                    </div>
                </template>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="closeDeactivateConfirmModal()"
                        :disabled="submitting"
                        class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        id="confirm-deactivate-btn"
                        @click="submitDeactivate()"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="submitting ? 'Deactivating...' : 'Yes, Deactivate Location'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 1: Reactivation Reason Modal (Super Admin only) --}}
        <div
            x-show="activateModal"
            x-cloak
            x-on:keydown.escape.window="closeActivateModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="activate-modal-title"
        >
            <div
                x-show="activateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeActivateModal()"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="activateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-md rounded-xl border border-emerald-200 bg-white shadow-xl dark:border-emerald-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="activate-modal-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Reactivate Storage Location
                        </h3>
                        <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                            Specify the reason for reactivating <span class="font-mono font-bold" x-text="targetLocation?.code"></span> before proceeding to confirmation.
                        </p>
                    </div>
                </div>

                <template x-if="submitError">
                    <div class="rounded-lg border border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/60 p-3 text-xs text-red-800 dark:text-red-300 flex items-center justify-between">
                        <span x-text="submitError"></span>
                        <button type="button" @click="submitError = null" class="text-red-500 hover:text-red-700">&times;</button>
                    </div>
                </template>

                <form @submit.prevent="openActivateConfirmModal()" class="space-y-4">
                    <div>
                        <label for="activate-reason" class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                            Reason for Reactivation <span class="font-normal text-neutral-500">(for audit trail)</span>
                        </label>
                        <textarea
                            id="activate-reason"
                            name="reason"
                            rows="2"
                            x-model="reason"
                            placeholder="e.g. Facility reopening, maintenance completed..."
                            class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:border-emerald-500 focus:ring-emerald-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="closeActivateModal()"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            id="proceed-activate-btn"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            <span>Activate Location</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Step 2: Confirmation Modal for Reactivation (with Yes / Cancel) --}}
        <div
            x-show="confirmActivateModal"
            x-cloak
            x-on:keydown.escape.window="closeActivateConfirmModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-activate-title"
        >
            <div
                x-show="confirmActivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeActivateConfirmModal()"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="confirmActivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-md rounded-xl border border-emerald-200 bg-white shadow-xl dark:border-emerald-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="confirm-activate-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Are you sure you want to reactivate this storage location?
                        </h3>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            Are you sure you want to reactivate <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="targetLocation?.code"></span> (<span x-text="targetLocation?.name"></span>)? Once reactivated, this location will be able to receive incoming inventory, purchase orders, and stock transfers normally.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 p-3 text-xs space-y-1">
                    <div class="text-neutral-500 dark:text-neutral-400">Recorded Reason:</div>
                    <div class="font-medium text-neutral-800 dark:text-neutral-200 italic" x-text="reason || 'Reactivated location'"></div>
                </div>

                <template x-if="submitError">
                    <div class="rounded-lg border border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/60 p-3 text-xs text-red-800 dark:text-red-300 flex items-center justify-between">
                        <span x-text="submitError"></span>
                        <button type="button" @click="submitError = null" class="text-red-500 hover:text-red-700">&times;</button>
                    </div>
                </template>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="closeActivateConfirmModal()"
                        :disabled="submitting"
                        class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        id="confirm-activate-btn"
                        @click="submitActivate()"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="submitting ? 'Activating...' : 'Yes, Activate Location'"></span>
                    </button>
                </div>
            </div>
        </div>
        @endif

    {{-- Add Storage Location Modal --}}
    @can(\App\Enums\Permission::ManageWarehouseTopology->value)
    <x-ui.modal name="add-storage-location" title="Add Warehouse Storage Coordinate" maxWidth="2xl" x-on:open-create-modal.window="open = true">
        <form method="POST" action="{{ route('inventory.warehousing.locations.store') }}" class="space-y-4" @if ($errors->hasAny(['code', 'name', 'type', 'parent_id', 'capacity', 'max_weight_kg'])) x-init="open = true" @endif>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="modal_code" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Location Code *</label>
                    <input type="text" id="modal_code" name="code" value="{{ old('code') }}" required placeholder="e.g. CMW-AMB-A01-R02-B01" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm font-mono focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="modal_name" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Display Name *</label>
                    <input type="text" id="modal_name" name="name" value="{{ old('name') }}" required placeholder="e.g. Ambient Bin 01" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="modal_parent" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Parent Location</label>
                    <select id="modal_parent" name="parent_id" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">None (Top Level)</option>
                        @foreach($parentLocations as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>{{ $parent->code }} ({{ $parent->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="modal_type" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Coordinate Type *</label>
                    <select id="modal_type" name="type" required class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="bin" @selected(old('type', 'bin') === 'bin')>Bin</option>
                        <option value="shelf" @selected(old('type') === 'shelf')>Shelf / Level</option>
                        <option value="rack" @selected(old('type') === 'rack')>Rack</option>
                        <option value="aisle" @selected(old('type') === 'aisle')>Aisle</option>
                        <option value="zone" @selected(old('type') === 'zone')>Zone</option>
                        <option value="warehouse" @selected(old('type') === 'warehouse')>Warehouse</option>
                        <option value="vault" @selected(old('type') === 'vault')>Narcotics Vault</option>
                        <option value="cold_room" @selected(old('type') === 'cold_room')>Cold Room</option>
                    </select>
                </div>
                <div>
                    <label for="modal_thermal" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Thermal Class</label>
                    <select id="modal_thermal" name="temperature_classification" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="ambient" @selected(old('temperature_classification', 'ambient') === 'ambient')>Ambient (15°C-25°C)</option>
                        <option value="refrigerated" @selected(old('temperature_classification') === 'refrigerated')>Refrigerated (2°C-8°C)</option>
                        <option value="frozen" @selected(old('temperature_classification') === 'frozen')>Frozen (-20°C)</option>
                        <option value="ultra_cold" @selected(old('temperature_classification') === 'ultra_cold')>Ultra-Cold (-80°C)</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-4 font-mono text-xs">
                <div>
                    <label for="modal_aisle" class="font-sans uppercase text-neutral-500 dark:text-neutral-400 font-semibold">Aisle</label>
                    <input type="text" id="modal_aisle" name="aisle" value="{{ old('aisle') }}" placeholder="A01" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="modal_rack" class="font-sans uppercase text-neutral-500 dark:text-neutral-400 font-semibold">Rack</label>
                    <input type="text" id="modal_rack" name="rack" value="{{ old('rack') }}" placeholder="R01" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="modal_shelf" class="font-sans uppercase text-neutral-500 dark:text-neutral-400 font-semibold">Shelf</label>
                    <input type="text" id="modal_shelf" name="shelf" value="{{ old('shelf') }}" placeholder="S01" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="modal_bin" class="font-sans uppercase text-neutral-500 dark:text-neutral-400 font-semibold">Bin</label>
                    <input type="text" id="modal_bin" name="bin" value="{{ old('bin') }}" placeholder="B01" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="modal_capacity" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Max Units Capacity</label>
                    <input type="number" id="modal_capacity" name="capacity" value="{{ old('capacity') }}" min="1" placeholder="e.g. 500" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="modal_weight" class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">Max Weight (kg)</label>
                    <input type="number" step="0.01" id="modal_weight" name="max_weight_kg" value="{{ old('max_weight_kg') }}" placeholder="e.g. 200.00" class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-3 pt-2 text-xs">
                <label class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <input type="checkbox" name="is_pick_face" value="1" @checked(old('is_pick_face')) class="rounded border-neutral-300 dark:border-neutral-700 text-primary-600 focus:ring-primary-500">
                    <span>Pick Face</span>
                </label>
                <label class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <input type="checkbox" name="is_reserve" value="1" @checked(old('is_reserve')) class="rounded border-neutral-300 dark:border-neutral-700 text-primary-600 focus:ring-primary-500">
                    <span>Reserve Storage</span>
                </label>
                <label class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <input type="checkbox" name="is_narcotics_vault" value="1" @checked(old('is_narcotics_vault')) class="rounded border-neutral-300 dark:border-neutral-700 text-purple-600 focus:ring-purple-500">
                    <span class="font-semibold text-purple-800 dark:text-purple-400">Narcotics Vault</span>
                </label>
                <label class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <input type="checkbox" name="is_quarantine" value="1" @checked(old('is_quarantine')) class="rounded border-neutral-300 dark:border-neutral-700 text-amber-600 focus:ring-amber-500">
                    <span>Quarantine Area</span>
                </label>
                <label class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <input type="checkbox" name="is_receiving_staging" value="1" @checked(old('is_receiving_staging')) class="rounded border-neutral-300 dark:border-neutral-700 text-neutral-600 focus:ring-neutral-500">
                    <span>Receiving Staging</span>
                </label>
                <label class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <input type="checkbox" name="is_dispatch_staging" value="1" @checked(old('is_dispatch_staging')) class="rounded border-neutral-300 dark:border-neutral-700 text-neutral-600 focus:ring-neutral-500">
                    <span>Dispatch Staging</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-100 dark:border-neutral-800 pt-4">
                <button type="button" @click="$dispatch('close-modal', 'add-storage-location')" class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Create Storage Location
                </button>
            </div>
        </form>
    </x-ui.modal>
    @endcan
    </div>

    <script>
        function locationItemsManager() {
            return {
                init() {
                    window.__locationItemsManager = this;
                },

                // Location Items Modal State
                openModal: false,
                loading: false,
                errorMessage: null,
                location: null,
                items: [],
                search: '',
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 15,
                    total: 0,
                    from: 0,
                    to: 0,
                },

                // Location Lifecycle Modals State (Super Admin)
                deactivateModal: false,
                confirmDeactivateModal: false,
                activateModal: false,
                confirmActivateModal: false,
                targetLocation: null,
                reason: '',
                submitting: false,
                submitError: null,
                statusSuccessMessage: null,

                viewLocationItems(locId, locCode, locName) {
                    this.location = { id: locId, code: locCode, name: locName, full_path: locName, total_quantity: 0 };
                    this.search = '';
                    this.openModal = true;
                    this.fetchItems(1);
                },
                closeModal() {
                    this.openModal = false;
                    this.items = [];
                    this.search = '';
                    this.errorMessage = null;
                },
                async fetchItems(page = 1) {
                    if (!this.location || !this.location.id) return;
                    this.loading = true;
                    this.errorMessage = null;
                    try {
                        const baseUrl = '{{ url('/inventory/warehousing/locations') }}/' + this.location.id + '/items';
                        const url = new URL(baseUrl, window.location.origin);
                        url.searchParams.set('page', page);
                        if (this.search && this.search.trim()) {
                            url.searchParams.set('search', this.search.trim());
                        }
                        const res = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        });
                        if (!res.ok) {
                            throw new Error(`Failed to load items (${res.status} ${res.statusText})`);
                        }
                        const data = await res.json();
                        this.location = data.location;
                        this.items = data.items || [];
                        this.pagination = data.pagination || this.pagination;
                    } catch (err) {
                        console.error(err);
                        this.errorMessage = err.message || 'Error loading location items.';
                    } finally {
                        this.loading = false;
                    }
                },
                onSearchInput() {
                    this.fetchItems(1);
                },
                changePage(page) {
                    if (page >= 1 && page <= this.pagination.last_page) {
                        this.fetchItems(page);
                    }
                },

                // Lifecycle Actions: Step 1 & Step 2
                openDeactivateModal(locIdOrData, locCode, locName, itemsCount = 0, totalStock = 0, pendingInbound = 0) {
                    if (typeof locIdOrData === 'object' && locIdOrData !== null) {
                        this.targetLocation = {
                            id: locIdOrData.id,
                            code: locIdOrData.code,
                            name: locIdOrData.name,
                            itemsCount: locIdOrData.itemsCount || 0,
                            totalStock: locIdOrData.totalStock || 0,
                            pendingInbound: locIdOrData.pendingInbound || 0,
                        };
                    } else {
                        this.targetLocation = {
                            id: locIdOrData,
                            code: locCode,
                            name: locName,
                            itemsCount: itemsCount,
                            totalStock: totalStock,
                            pendingInbound: pendingInbound,
                        };
                    }
                    this.reason = '';
                    this.submitError = null;
                    this.confirmDeactivateModal = false;
                    this.deactivateModal = true;
                },
                closeDeactivateModal() {
                    this.deactivateModal = false;
                    this.confirmDeactivateModal = false;
                    this.targetLocation = null;
                    this.reason = '';
                    this.submitError = null;
                },
                openDeactivateConfirmModal() {
                    if (!this.reason || !this.reason.trim()) {
                        this.submitError = 'Please provide a reason for deactivation.';
                        return;
                    }
                    this.submitError = null;
                    this.deactivateModal = false;
                    this.confirmDeactivateModal = true;
                },
                closeDeactivateConfirmModal() {
                    this.confirmDeactivateModal = false;
                    this.deactivateModal = true;
                },

                openActivateModal(locIdOrData, locCode, locName) {
                    if (typeof locIdOrData === 'object' && locIdOrData !== null) {
                        this.targetLocation = {
                            id: locIdOrData.id,
                            code: locIdOrData.code,
                            name: locIdOrData.name,
                        };
                    } else {
                        this.targetLocation = {
                            id: locIdOrData,
                            code: locCode,
                            name: locName,
                        };
                    }
                    this.reason = '';
                    this.submitError = null;
                    this.confirmActivateModal = false;
                    this.activateModal = true;
                },
                closeActivateModal() {
                    this.activateModal = false;
                    this.confirmActivateModal = false;
                    this.targetLocation = null;
                    this.reason = '';
                    this.submitError = null;
                },
                openActivateConfirmModal() {
                    this.submitError = null;
                    this.activateModal = false;
                    this.confirmActivateModal = true;
                },
                closeActivateConfirmModal() {
                    this.confirmActivateModal = false;
                    this.activateModal = true;
                },

                async submitDeactivate() {
                    if (!this.targetLocation || !this.targetLocation.id) return;
                    if (!this.reason || !this.reason.trim()) {
                        this.submitError = 'Please provide a reason for deactivation.';
                        return;
                    }
                    this.submitting = true;
                    this.submitError = null;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || document.querySelector('input[name="_token"]')?.value;
                        const res = await fetch(`{{ url('/inventory/storage-locations') }}/${this.targetLocation.id}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                status: 'inactive',
                                reason: this.reason.trim()
                            })
                        });

                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            throw new Error(data.message || `Failed to deactivate location (${res.status})`);
                        }

                        this.statusSuccessMessage = data.message || `Storage location ${this.targetLocation.code} has been deactivated.`;
                        this.updateLocationRow(this.targetLocation.id, 'inactive', this.targetLocation.code, this.targetLocation.name, this.targetLocation.itemsCount, this.targetLocation.totalStock);
                        this.closeDeactivateModal();
                    } catch (err) {
                        console.error(err);
                        this.submitError = err.message || 'An error occurred while deactivating the storage location.';
                    } finally {
                        this.submitting = false;
                    }
                },
                async submitActivate() {
                    if (!this.targetLocation || !this.targetLocation.id) return;
                    this.submitting = true;
                    this.submitError = null;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || document.querySelector('input[name="_token"]')?.value;
                        const res = await fetch(`{{ url('/inventory/storage-locations') }}/${this.targetLocation.id}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                status: 'active',
                                reason: this.reason ? this.reason.trim() : 'Reactivated location'
                            })
                        });

                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            throw new Error(data.message || `Failed to activate location (${res.status})`);
                        }

                        this.statusSuccessMessage = data.message || `Storage location ${this.targetLocation.code} has been reactivated.`;
                        this.updateLocationRow(this.targetLocation.id, 'active', this.targetLocation.code, this.targetLocation.name, this.targetLocation.itemsCount, this.targetLocation.totalStock);
                        this.closeActivateModal();
                    } catch (err) {
                        console.error(err);
                        this.submitError = err.message || 'An error occurred while activating the storage location.';
                    } finally {
                        this.submitting = false;
                    }
                },
                updateLocationRow(locId, newStatus, code, name, itemsCount = 0, totalStock = 0) {
                    const statusCell = document.getElementById(`location-status-cell-${locId}`);
                    if (statusCell) {
                        if (newStatus === 'active') {
                            statusCell.innerHTML = `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active</span>`;
                        } else {
                            statusCell.innerHTML = `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300"><span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span>Inactive</span>`;
                        }
                    }
                    const btnContainer = document.getElementById(`location-status-btn-container-${locId}`);
                    if (btnContainer) {
                        const safeCode = (code || '').replace(/'/g, "\\'");
                        const safeName = (name || '').replace(/'/g, "\\'");
                        if (newStatus === 'active') {
                            btnContainer.innerHTML = `
                                <button
                                    type="button"
                                    id="btn-deactivate-location-${locId}"
                                    onclick="window.__locationItemsManager?.openDeactivateModal(${locId}, '${safeCode}', '${safeName}', ${itemsCount}, ${totalStock}, 0)"
                                    data-confirm-prompt="Are you sure you want to deactivate ${safeCode} (${safeName})?"
                                    class="inline-flex items-center gap-1 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/60 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/80 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    title="Deactivate this storage location">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    <span>Deactivate</span>
                                </button>
                            `;
                        } else {
                            btnContainer.innerHTML = `
                                <button
                                    type="button"
                                    id="btn-activate-location-${locId}"
                                    onclick="window.__locationItemsManager?.openActivateModal(${locId}, '${safeCode}', '${safeName}')"
                                    data-confirm-prompt="Are you sure you want to reactivate ${safeCode} (${safeName})?"
                                    class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/80 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    title="Reactivate this storage location">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span>Activate</span>
                                </button>
                            `;
                        }
                    }
                }
            };
        }
    </script>
</x-app-layout>
