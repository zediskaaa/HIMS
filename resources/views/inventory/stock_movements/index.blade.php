<x-app-layout>
    <x-ui.page-header
        title="Stock Movement & Transfer"
        subtitle="Record stock in, stock out and transfers between storage locations."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Stock Movements' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="This movement was not recorded">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{--
        Stock in needs a destination, stock out needs a source, and a transfer
        needs both — the service rejects the movement otherwise. Alpine mirrors
        those rules so the form only ever asks for the locations that apply.
        Issuance and returns additionally name a counterparty, which is stored
        on the movement's polymorphic reference.

        The whole card is skipped for an account that may record nothing — a
        viewer or auditor gets the history below without a form that would only
        refuse them. $movementTypes is already filtered to what they may post.
    --}}
    @if (! empty($movementTypes))
    <x-ui.card
        title="Record Movement"
        subtitle="Balances update immediately; outbound quantities are drawn earliest-expiry-first."
        x-data="{
            type: '{{ old('movement_type', $movementTypes[0]->value) }}',
            itemId: '{{ old('item_id', $items->first()?->id) }}',
            stock: {{ Js::from($availability->map(fn ($levels) => $levels->map(fn ($l) => [
                'location' => $l->location?->name,
                'code' => $l->location?->code,
                'qty' => (int) $l->quantity,
            ])->values())) }},
            get needsSource() { return ['stock_out', 'transfer', 'issuance', 'return_to_supplier'].includes(this.type) },
            get needsDestination() { return ['stock_in', 'transfer'].includes(this.type) },
            get needsRecipient() { return this.type === 'issuance' },
            get needsSupplier() { return this.type === 'return_to_supplier' },
            get onHand() { return this.stock[this.itemId] ?? [] },
        }">
        <form method="POST" action="{{ route('inventory.stock-movements.store') }}" class="space-y-5"
              data-confirm-title="Confirm stock movement"
              data-confirm-message="Are you sure you want to record this stock movement?"
              data-confirm-label="Record Movement">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.field
                    name="item_id"
                    label="Item"
                    type="select"
                    required
                    x-model="itemId">
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                            {{ $item->name }} ({{ $item->sku }})
                        </option>
                    @endforeach
                </x-ui.field>

                {{-- Only the types this account may record. A pharmacy user is
                     not offered "Stock In", because the POST would refuse it. --}}
                <x-ui.field
                    name="movement_type"
                    label="Movement Type"
                    type="select"
                    required
                    x-model="type"
                    :options="collect($movementTypes)
                        ->mapWithKeys(fn ($type) => [$type->value => $type->label().' — '.$type->hint()])
                        ->all()" />

                <div x-show="needsSource" x-cloak>
                    <x-ui.field
                        name="from_location_id"
                        label="From Location"
                        type="select"
                        placeholder="Select a source location"
                        x-bind:required="needsSource"
                        hint="Stock is drawn from this location.">
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('from_location_id') == $location->id)>
                                {{ $location->name }} ({{ $location->code }})
                            </option>
                        @endforeach
                    </x-ui.field>
                </div>

                <div x-show="needsDestination" x-cloak>
                    <x-ui.field
                        name="to_location_id"
                        label="To Location"
                        type="select"
                        placeholder="Select a destination location"
                        x-bind:required="needsDestination"
                        hint="Stock is received into this location.">
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('to_location_id') == $location->id)>
                                {{ $location->name }} ({{ $location->code }})
                            </option>
                        @endforeach
                    </x-ui.field>
                </div>

                {{-- Issuance records who consumed the stock, which is what
                     separates it from a generic stock out. --}}
                <div x-show="needsRecipient" x-cloak>
                    <x-ui.field
                        name="issued_to_location_id"
                        label="Issued To"
                        type="select"
                        placeholder="Select a ward or department"
                        x-bind:required="needsRecipient"
                        hint="Recorded against the movement for consumption reporting.">
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('issued_to_location_id') == $location->id)>
                                {{ $location->name }} ({{ $location->code }})
                            </option>
                        @endforeach
                    </x-ui.field>
                </div>

                {{-- A return names the vendor it goes back to; the reason
                     rides along in Remarks. --}}
                <div x-show="needsSupplier" x-cloak>
                    <x-ui.field
                        name="return_supplier_id"
                        label="Return To Supplier"
                        type="select"
                        placeholder="Select a supplier"
                        x-bind:required="needsSupplier"
                        hint="Use Remarks to record why it is going back.">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('return_supplier_id') == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </x-ui.field>
                </div>

                <x-ui.field
                    name="quantity"
                    label="Quantity"
                    type="number"
                    min="1"
                    required
                    placeholder="0" />

                <x-ui.field
                    name="remarks"
                    label="Remarks"
                    x-bind:placeholder="needsSupplier
                        ? 'e.g. Damaged on delivery'
                        : (needsRecipient ? 'e.g. Dispensed for night shift' : 'e.g. Issued to Ward 3')" />
            </div>

            {{-- Without this, picking a source location is guesswork. --}}
            <div x-show="needsSource" x-cloak class="rounded-md bg-neutral-50 border border-neutral-200 px-4 py-3">
                <p class="text-xs font-medium text-neutral-700">Currently on hand</p>
                <template x-if="onHand.length">
                    <ul class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
                        <template x-for="level in onHand" :key="level.code">
                            <li class="text-xs text-neutral-600">
                                <span x-text="level.location"></span>
                                <span class="font-semibold tabular-nums text-neutral-900" x-text="level.qty"></span>
                            </li>
                        </template>
                    </ul>
                </template>
                <template x-if="!onHand.length">
                    <p class="mt-1.5 text-xs text-neutral-500">
                        No stock on hand for this item — record a Stock In first.
                    </p>
                </template>
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit" icon="plus">Save Movement</x-ui.button>
            </div>
        </form>
    </x-ui.card>
    @endif

    <x-ui.card title="Movement History" :subtitle="$movements->count().' recorded'" :padding="false">
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Item</x-ui.table.th>
                <x-ui.table.th>Type</x-ui.table.th>
                <x-ui.table.th numeric>Qty</x-ui.table.th>
                <x-ui.table.th>From</x-ui.table.th>
                <x-ui.table.th>To / Reference</x-ui.table.th>
                <x-ui.table.th>Remarks</x-ui.table.th>
                <x-ui.table.th>Recorded</x-ui.table.th>
            </x-ui.table.head>
            <tbody>
                @forelse ($movements as $movement)
                    <x-ui.table.row>
                        <x-ui.table.td>
                            <span class="font-medium text-neutral-900">{{ $movement->item?->name ?? '—' }}</span>
                            @if ($movement->item?->sku)
                                <span class="block text-xs text-neutral-500">{{ $movement->item->sku }}</span>
                            @endif
                        </x-ui.table.td>
                        <x-ui.table.td>
                            <x-ui.badge :status="$movement->movement_type->value">
                                {{ $movement->movement_type->label() }}
                            </x-ui.badge>
                        </x-ui.table.td>
                        <x-ui.table.td numeric>{{ number_format($movement->quantity) }}</x-ui.table.td>
                        <x-ui.table.td muted>{{ $movement->fromLocation?->name ?? '—' }}</x-ui.table.td>
                        <x-ui.table.td muted>
                            {{-- Issuances and returns have no destination balance;
                                 their counterparty lives on the reference morph. --}}
                            @if ($movement->toLocation)
                                {{ $movement->toLocation->name }}
                            @elseif ($movement->reference)
                                {{ $movement->reference->name ?? class_basename($movement->reference) }}
                                <span class="block text-xs text-neutral-400">
                                    {{ $movement->movement_type === \App\Enums\MovementType::Issuance ? 'Issued to' : 'Returned to' }}
                                </span>
                            @else
                                —
                            @endif
                        </x-ui.table.td>
                        <x-ui.table.td muted>{{ $movement->remarks ?? '—' }}</x-ui.table.td>
                        <x-ui.table.td muted>
                            {{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}
                            @if ($movement->user)
                                <span class="block text-xs text-neutral-400">{{ $movement->user->name }}</span>
                            @endif
                        </x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty
                        :colspan="7"
                        icon="arrows-right-left"
                        title="No movements yet"
                        message="Recorded stock in, stock out and transfers will appear here." />
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
</x-app-layout>
