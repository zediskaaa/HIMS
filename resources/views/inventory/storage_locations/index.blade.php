<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Smart Warehousing</p>
            <h2 class="text-2xl font-bold text-neutral-900">Warehouse Hierarchy &amp; Locations</h2>
            <p class="text-sm text-neutral-600">Configure operational storage points, capacity, classifications, and scannable internal codes.</p>
        </div>
    </x-slot>

    <div class="py-6"><div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if ($errors->any())<x-ui.alert variant="danger" title="Location could not be saved"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

        @can(\App\Enums\Permission::ManageLocations->value)
            <x-ui.card title="Add location" subtitle="Use only the hierarchy levels your physical warehouse actually needs.">
                <form method="POST" action="{{ route('inventory.storage-locations.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf
                    <div><label for="name" class="text-sm font-medium">Display name</label><input id="name" type="text" name="name" required maxlength="255" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-neutral-300"></div>
                    <div><label for="type" class="text-sm font-medium">Location type</label><select id="type" name="type" required class="mt-1 w-full rounded-lg border-neutral-300"><option value="">Select type</option>@foreach(['warehouse'=>'Warehouse','zone'=>'Zone','aisle'=>'Aisle','rack'=>'Rack','shelf'=>'Shelf','level'=>'Level','bin'=>'Bin','pharmacy'=>'Pharmacy stockroom','department'=>'Department stockroom'] as $value=>$label)<option value="{{ $value }}" @selected(old('type')===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label for="parent_id" class="text-sm font-medium">Parent location</label><select id="parent_id" name="parent_id" class="mt-1 w-full rounded-lg border-neutral-300"><option value="">None / root</option>@foreach($parentLocations as $parent)<option value="{{ $parent->id }}" @selected((string)old('parent_id')===(string)$parent->id)>{{ $parent->code }} - {{ $parent->fullPath() }}</option>@endforeach</select></div>
                    <div><label for="code" class="text-sm font-medium">Internal code <span class="font-normal text-neutral-500">(optional)</span></label><input id="code" type="text" name="code" maxlength="100" value="{{ old('code') }}" placeholder="Auto-generated when blank" class="mt-1 w-full rounded-lg border-neutral-300 font-mono"><p class="mt-1 text-xs text-neutral-500">Letters, numbers, dot, dash, and underscore only.</p></div>
                    <div><label for="storage_classification" class="text-sm font-medium">Storage classification</label><select id="storage_classification" name="storage_classification" class="mt-1 w-full rounded-lg border-neutral-300"><option value="">Any compatible item</option>@foreach(['general'=>'General','medical_supply'=>'Medical supply','pharmaceutical'=>'Pharmaceutical','sterile'=>'Sterile','cold_chain'=>'Cold chain','hazardous'=>'Hazardous','flammable'=>'Flammable','controlled'=>'Controlled / restricted'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label for="temperature_classification" class="text-sm font-medium">Temperature classification</label><select id="temperature_classification" name="temperature_classification" class="mt-1 w-full rounded-lg border-neutral-300"><option value="">Use product/manufacturer requirement</option>@foreach(['ambient'=>'Ambient','controlled_room'=>'Controlled room temperature','refrigerated'=>'Refrigerated','frozen'=>'Frozen','deep_frozen'=>'Deep frozen'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label for="capacity" class="text-sm font-medium">Capacity</label><input id="capacity" type="number" name="capacity" min="1" step="1" inputmode="numeric" value="{{ old('capacity') }}" class="mt-1 w-full rounded-lg border-neutral-300"></div>
                    <div><label for="capacity_unit" class="text-sm font-medium">Capacity unit</label><select id="capacity_unit" name="capacity_unit" class="mt-1 w-full rounded-lg border-neutral-300">@foreach(['units'=>'Units','boxes'=>'Boxes','pallets'=>'Pallets'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label for="status" class="text-sm font-medium">Operational status</label><select id="status" name="status" required class="mt-1 w-full rounded-lg border-neutral-300"><option value="active">Active</option><option value="blocked">Blocked</option><option value="inactive">Inactive</option></select></div>
                    <div><label for="sort_sequence" class="text-sm font-medium">Pick sort sequence</label><input id="sort_sequence" type="number" name="sort_sequence" min="0" step="1" value="{{ old('sort_sequence', 0) }}" class="mt-1 w-full rounded-lg border-neutral-300"></div>
                    <fieldset class="md:col-span-2 xl:col-span-4"><legend class="text-sm font-medium">Operational purpose</legend><div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">@foreach(['is_receiving_staging'=>'Receiving staging','is_quarantine'=>'Quarantine','is_pick_face'=>'Pick face','is_reserve'=>'Reserve storage','is_dispatch_staging'=>'Dispatch staging','is_returns_area'=>'Returns area','is_damaged_stock'=>'Damaged stock'] as $name=>$label)<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name)) class="rounded border-neutral-300 text-primary-600">{{ $label }}</label>@endforeach</div></fieldset>
                    <div class="md:col-span-2 xl:col-span-3"><label for="description" class="text-sm font-medium">Description</label><textarea id="description" name="description" maxlength="255" rows="2" class="mt-1 w-full rounded-lg border-neutral-300">{{ old('description') }}</textarea></div>
                    <div class="flex items-end"><x-ui.button type="submit">Save location</x-ui.button></div>
                </form>
            </x-ui.card>
        @endcan

        <x-ui.card title="Location registry" subtitle="Occupancy is calculated from the authoritative location balance.">
            <x-ui.table>
                <x-ui.table.head><tr><x-ui.table.th>Code / location</x-ui.table.th><x-ui.table.th>Type</x-ui.table.th><x-ui.table.th>Classification</x-ui.table.th><x-ui.table.th>Purpose</x-ui.table.th><x-ui.table.th>Occupancy</x-ui.table.th><x-ui.table.th>Status</x-ui.table.th><x-ui.table.th>Controls</x-ui.table.th></tr></x-ui.table.head>
                <tbody>@forelse($locations as $location)<x-ui.table.row>
                    <x-ui.table.td><p class="font-mono text-xs font-semibold text-primary-700">{{ $location->code }}</p><p class="font-medium">{{ $location->fullPath() }}</p></x-ui.table.td>
                    <x-ui.table.td>{{ str($location->type)->replace('_',' ')->title() }}</x-ui.table.td>
                    <x-ui.table.td><p>{{ $location->storage_classification ? str($location->storage_classification)->replace('_',' ')->title() : 'Any' }}</p><p class="text-xs text-neutral-500">{{ $location->temperature_classification ? str($location->temperature_classification)->replace('_',' ')->title() : 'Not fixed' }}</p></x-ui.table.td>
                    <x-ui.table.td><div class="flex max-w-xs flex-wrap gap-1">@foreach(['is_receiving_staging'=>'Receiving','is_quarantine'=>'Quarantine','is_pick_face'=>'Pick face','is_reserve'=>'Reserve','is_dispatch_staging'=>'Dispatch','is_returns_area'=>'Returns','is_damaged_stock'=>'Damaged'] as $flag=>$label)@if($location->{$flag})<span class="rounded bg-neutral-100 px-2 py-0.5 text-xs">{{ $label }}</span>@endif @endforeach</div></x-ui.table.td>
                    <x-ui.table.td>{{ number_format($location->totalQuantity()) }}@if($location->capacity) / {{ number_format($location->capacity) }} {{ $location->capacity_unit }}<p class="text-xs text-neutral-500">{{ $location->utilisation() }}%</p>@endif</x-ui.table.td>
                    <x-ui.table.td><x-ui.badge :status="$location->status">{{ str($location->status)->title() }}</x-ui.badge></x-ui.table.td>
                    <x-ui.table.td><div class="flex min-w-52 flex-wrap gap-2">
                        @can(\App\Enums\Permission::PrintWarehouseLabels->value)<form method="POST" action="{{ route('inventory.storage-locations.label', $location) }}" target="_blank">@csrf<input type="hidden" name="copies" value="1"><button class="text-xs font-semibold text-primary-700 hover:underline">Print QR</button></form>@endcan
                        @can(\App\Enums\Permission::ManageLocations->value)<form method="POST" action="{{ route('inventory.storage-locations.status', $location) }}" class="flex items-center gap-1">@csrf @method('PATCH')<select name="status" class="rounded border-neutral-300 py-1 text-xs">@foreach(['active'=>'Active','blocked'=>'Blocked','inactive'=>'Inactive'] as $value=>$label)<option value="{{ $value }}" @selected($location->status===$value)>{{ $label }}</option>@endforeach</select><input name="reason" required maxlength="1000" placeholder="Reason" class="w-24 rounded border-neutral-300 py-1 text-xs"><button class="text-xs font-semibold text-primary-700 hover:underline">Apply</button></form>@endcan
                    </div></x-ui.table.td>
                </x-ui.table.row>@empty<x-ui.table.empty colspan="7" title="No storage locations" message="Configure the real warehouse hierarchy before creating physical tasks." />@endforelse</tbody>
            </x-ui.table>
        </x-ui.card>
    </div></div>
</x-app-layout>
