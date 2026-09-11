<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" x-data="{ createModal: false }">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Spatial Topology</p>
                <h2 class="text-2xl font-bold text-neutral-900">Warehouse Storage Locations</h2>
                <p class="text-sm text-neutral-600">Physical coordinate hierarchy: Warehouses &rarr; Zones &rarr; Aisles &rarr; Racks &rarr; Shelves &rarr; Bins.</p>
            </div>
            <div class="flex items-center gap-2">
                @can(\App\Enums\Permission::ManageWarehouseTopology->value)
                    <button type="button" @click="$dispatch('open-create-modal')" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Add Storage Bin
                    </button>
                @endcan
                <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    &larr; Warehouse Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ showCreateModal: false }" @open-create-modal.window="showCreateModal = true">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            @if(session('success'))
                <x-ui.alert variant="success" :message="session('success')" />
            @endif
            @if($errors->any())
                <x-ui.alert variant="danger" title="Validation errors occurred">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            {{-- Filter Bar --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('inventory.warehousing.locations') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="search" class="text-xs font-semibold uppercase text-neutral-500">Search</label>
                        <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Code, name, or barcode..." class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="type" class="text-xs font-semibold uppercase text-neutral-500">Location Type</label>
                        <select id="type" name="type" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Types</option>
                            @foreach(['warehouse' => 'Warehouse', 'zone' => 'Zone', 'aisle' => 'Aisle', 'rack' => 'Rack', 'shelf' => 'Shelf', 'bin' => 'Bin', 'cold_room' => 'Cold Room', 'vault' => 'Narcotics Vault'] as $k => $v)
                                <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="thermal" class="text-xs font-semibold uppercase text-neutral-500">Thermal Zone</label>
                        <select id="thermal" name="thermal" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Temperatures</option>
                            <option value="ambient" @selected(request('thermal') === 'ambient')>Ambient (15°C - 25°C)</option>
                            <option value="refrigerated" @selected(request('thermal') === 'refrigerated')>Refrigerated (2°C - 8°C)</option>
                            <option value="frozen" @selected(request('thermal') === 'frozen')>Frozen (-20°C)</option>
                            <option value="ultra_cold" @selected(request('thermal') === 'ultra_cold')>Ultra-Cold (-80°C)</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800">
                            Apply Filters
                        </button>
                        <a href="{{ route('inventory.warehousing.locations') }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Locations Registry Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-6 py-3">Location & Coordinate</th>
                                <th class="px-6 py-3">Type & Topology</th>
                                <th class="px-6 py-3">Thermal Class</th>
                                <th class="px-6 py-3">Capacity & Occupancy</th>
                                <th class="px-6 py-3">Special Designation</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse($locations as $loc)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <div class="font-mono text-sm font-bold text-neutral-900">{{ $loc->code }}</div>
                                        <div class="text-xs text-neutral-500">{{ $loc->name }}</div>
                                        @if($loc->barcode_value)
                                            <div class="font-mono text-[11px] text-neutral-400">Barcode: {{ $loc->barcode_value }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-800 capitalize">
                                            {{ str_replace('_', ' ', $loc->type) }}
                                        </span>
                                        <div class="mt-1 text-xs text-neutral-500">
                                            {{ $loc->fullPath() }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($loc->temperature_classification === 'refrigerated')
                                            <span class="inline-flex items-center gap-1 rounded bg-cyan-100 px-2.5 py-0.5 text-xs font-medium text-cyan-800">
                                                Cold Chain (2°C-8°C)
                                            </span>
                                        @elseif($loc->temperature_classification === 'frozen')
                                            <span class="inline-flex items-center gap-1 rounded bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                                Frozen (-20°C)
                                            </span>
                                        @elseif($loc->temperature_classification === 'ultra_cold')
                                            <span class="inline-flex items-center gap-1 rounded bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                                Ultra-Cold (-80°C)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                                Ambient (15°C-25°C)
                                            </span>
                                        @endif
                                        @if($loc->excursion_hold)
                                            <div class="mt-1"><span class="rounded bg-red-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">EXCURSION HOLD</span></div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($loc->capacity)
                                            <div class="w-32">
                                                <div class="flex justify-between text-xs text-neutral-600">
                                                    <span>{{ $loc->totalQuantity() }} / {{ $loc->capacity }}</span>
                                                    <span>{{ $loc->utilisation() }}%</span>
                                                </div>
                                                <div class="mt-1 h-2 w-full rounded-full bg-neutral-200">
                                                    <div class="h-2 rounded-full @if($loc->utilisation() > 90) bg-red-500 @elseif($loc->utilisation() > 70) bg-amber-500 @else bg-emerald-500 @endif" style="width: {{ min(100, $loc->utilisation() ?? 0) }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs text-neutral-400">Unrestricted</span>
                                        @endif
                                        @if($loc->max_weight_kg)
                                            <div class="mt-0.5 text-[11px] text-neutral-500">Max {{ $loc->max_weight_kg }} kg</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            @if($loc->is_narcotics_vault)
                                                <span class="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-purple-800">Narcotics Vault</span>
                                            @endif
                                            @if($loc->is_hazardous_containment)
                                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-800">Hazardous / Cytotoxic</span>
                                            @endif
                                            @if($loc->is_pick_face)
                                                <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-blue-800">Pick Face</span>
                                            @endif
                                            @if($loc->is_reserve)
                                                <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-neutral-700">Reserve</span>
                                            @endif
                                            @if($loc->is_quarantine)
                                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Quarantine</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @can(\App\Enums\Permission::PrintWarehouseLabels->value)
                                        <form method="POST" action="{{ route('inventory.storage-locations.label', $loc) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" title="Print location barcode label" class="rounded border border-neutral-300 p-1 text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            </button>
                                        </form>
                                        @else
                                            <span class="text-xs text-neutral-400">Read only</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-sm text-neutral-500">
                                        No storage locations found matching criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-200 px-6 py-4">
                    {{ $locations->links() }}
                </div>
            </div>

        </div>

        {{-- Add Storage Location Modal --}}
        @can(\App\Enums\Permission::ManageWarehouseTopology->value)
        <div x-show="showCreateModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center" x-cloak style="display: none;">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl" @click.away="showCreateModal = false">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                    <h3 class="text-lg font-bold text-neutral-900">Add Warehouse Storage Coordinate</h3>
                    <button type="button" @click="showCreateModal = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                </div>

                <form method="POST" action="{{ route('inventory.warehousing.locations.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="modal_code" class="text-xs font-semibold uppercase text-neutral-500">Location Code *</label>
                            <input type="text" id="modal_code" name="code" required placeholder="e.g. CMW-AMB-A01-R02-B01" class="mt-1 w-full rounded-lg border-neutral-300 text-sm font-mono focus:border-primary-500 focus:ring-primary-500">
                        </div>
                        <div>
                            <label for="modal_name" class="text-xs font-semibold uppercase text-neutral-500">Display Name *</label>
                            <input type="text" id="modal_name" name="name" required placeholder="e.g. Ambient Bin 01" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="modal_parent" class="text-xs font-semibold uppercase text-neutral-500">Parent Location</label>
                            <select id="modal_parent" name="parent_id" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                                <option value="">None (Top Level)</option>
                                @foreach($parentLocations as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->code }} ({{ $parent->name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="modal_type" class="text-xs font-semibold uppercase text-neutral-500">Coordinate Type *</label>
                            <select id="modal_type" name="type" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                                <option value="bin" selected>Bin</option>
                                <option value="shelf">Shelf / Level</option>
                                <option value="rack">Rack</option>
                                <option value="aisle">Aisle</option>
                                <option value="zone">Zone</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="vault">Narcotics Vault</option>
                                <option value="cold_room">Cold Room</option>
                            </select>
                        </div>
                        <div>
                            <label for="modal_thermal" class="text-xs font-semibold uppercase text-neutral-500">Thermal Class</label>
                            <select id="modal_thermal" name="temperature_classification" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                                <option value="ambient">Ambient (15°C-25°C)</option>
                                <option value="refrigerated">Refrigerated (2°C-8°C)</option>
                                <option value="frozen">Frozen (-20°C)</option>
                                <option value="ultra_cold">Ultra-Cold (-80°C)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-4 font-mono text-xs">
                        <div>
                            <label for="modal_aisle" class="font-sans uppercase text-neutral-500 font-semibold">Aisle</label>
                            <input type="text" id="modal_aisle" name="aisle" placeholder="A01" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                        <div>
                            <label for="modal_rack" class="font-sans uppercase text-neutral-500 font-semibold">Rack</label>
                            <input type="text" id="modal_rack" name="rack" placeholder="R01" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                        <div>
                            <label for="modal_shelf" class="font-sans uppercase text-neutral-500 font-semibold">Shelf</label>
                            <input type="text" id="modal_shelf" name="shelf" placeholder="S01" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                        <div>
                            <label for="modal_bin" class="font-sans uppercase text-neutral-500 font-semibold">Bin</label>
                            <input type="text" id="modal_bin" name="bin" placeholder="B01" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="modal_capacity" class="text-xs font-semibold uppercase text-neutral-500">Max Units Capacity</label>
                            <input type="number" id="modal_capacity" name="capacity" min="1" placeholder="e.g. 500" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                        <div>
                            <label for="modal_weight" class="text-xs font-semibold uppercase text-neutral-500">Max Weight (kg)</label>
                            <input type="number" step="0.01" id="modal_weight" name="max_weight_kg" placeholder="e.g. 200.00" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3 pt-2 text-xs">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_pick_face" value="1" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                            <span>Pick Face</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_reserve" value="1" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                            <span>Reserve Storage</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_narcotics_vault" value="1" class="rounded border-neutral-300 text-purple-600 focus:ring-purple-500">
                            <span class="font-semibold text-purple-800">Narcotics Vault</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_quarantine" value="1" class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500">
                            <span>Quarantine Area</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_receiving_staging" value="1" class="rounded border-neutral-300 text-neutral-600 focus:ring-neutral-500">
                            <span>Receiving Staging</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_dispatch_staging" value="1" class="rounded border-neutral-300 text-neutral-600 focus:ring-neutral-500">
                            <span>Dispatch Staging</span>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-neutral-100 pt-4">
                        <button type="button" @click="showCreateModal = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                            Create Storage Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>
</x-app-layout>
