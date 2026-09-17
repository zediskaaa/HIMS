<x-app-layout full-width>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-800/60">
                    <x-ui.icon name="truck" class="h-3 w-3 text-primary-600 dark:text-primary-400" />
                    3PL Carrier &amp; Dock Inbound Tracking
                </span>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-neutral-100">Shipments &amp; Carrier Logistics</h1>
            </div>
            <div class="flex items-center gap-2" x-data>
                @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                <button @click="$dispatch('open-shipment-modal')" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-primary-700 dark:hover:bg-primary-500 transition active:scale-[0.98]">
                    <x-ui.icon name="plus" class="h-4 w-4" />
                    <span>Register Inbound Shipment</span>
                </button>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="space-y-6" x-data="{ shipmentModalOpen: false, dockModalOpen: false, selectedShipment: null, selectedShipmentNumber: '', isColdChain: false }"
         @open-shipment-modal.window="shipmentModalOpen = true">

        {{-- Flash Notifications --}}
        @if(session('success'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-3.5 text-sm text-emerald-800 shadow-2xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                <x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div class="font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('warning'))
            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/90 p-3.5 text-sm text-amber-800 shadow-2xs dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                <x-ui.icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="font-medium">{{ session('warning') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50/90 p-3.5 text-sm text-red-800 shadow-2xs dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                <x-ui.icon name="x-circle" class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400" />
                <div class="font-medium">{{ session('error') }}</div>
            </div>
        @endif

        {{-- Search & Filter Toolbar --}}
        <div class="rounded-xl border border-neutral-200/90 bg-white p-3 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
            <form method="GET" action="{{ route('inventory.logistics.shipments') }}" class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                {{-- Search Input (Largest flexible width) --}}
                <div class="relative flex-1 min-w-0">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 dark:text-neutral-500">
                        <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                    </div>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           placeholder="Search shipment #, carrier, tracking #, or GS1 SSCC..."
                           class="block w-full rounded-lg border-neutral-300 pl-9 pr-3 py-1.5 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                </div>

                {{-- Status Filter --}}
                <div class="w-full sm:w-52">
                    <select name="status" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        <option value="">All Statuses</option>
                        <option value="dispatched" {{ request('status') === 'dispatched' ? 'selected' : '' }}>Dispatched</option>
                        <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                        <option value="customs_hold" {{ request('status') === 'customs_hold' ? 'selected' : '' }}>Customs / Quarantine Hold</option>
                        <option value="arrived_at_dock" {{ request('status') === 'arrived_at_dock' ? 'selected' : '' }}>Arrived at Dock</option>
                        <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received & Accepted</option>
                    </select>
                </div>

                {{-- Temperature Filter --}}
                <div class="w-full sm:w-52">
                    <select name="cold_chain" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        <option value="">All Temperatures</option>
                        <option value="1" {{ request('cold_chain') === '1' ? 'selected' : '' }}>Cold Chain Only (2°-8°C)</option>
                        <option value="0" {{ request('cold_chain') === '0' ? 'selected' : '' }}>Ambient Only</option>
                    </select>
                </div>

                {{-- Filter Action Buttons --}}
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 dark:bg-primary-600 dark:hover:bg-primary-500 transition">
                        <x-ui.icon name="funnel" class="h-3.5 w-3.5 text-neutral-300 dark:text-white" />
                        <span>Filter</span>
                    </button>
                    @if(request()->hasAny(['search', 'status', 'cold_chain']))
                        <a href="{{ route('inventory.logistics.shipments') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white p-1.5 text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100 transition" title="Reset Filters">
                            <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Shipments Table --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200/90 bg-white shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto min-w-full">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs dark:divide-neutral-800">
                    <thead class="bg-neutral-50/90 text-[11px] font-semibold uppercase tracking-wider text-neutral-600 dark:bg-neutral-800/80 dark:text-neutral-400">
                        <tr>
                            <th class="px-5 py-3">Shipment &amp; Origin</th>
                            <th class="px-5 py-3">Carrier / 3PL Info</th>
                            <th class="px-5 py-3">GS1 SSCC Barcode</th>
                            <th class="px-5 py-3">Delivery Schedule</th>
                            <th class="px-5 py-3">Cold Chain Integrity</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right w-36 min-w-36">Dock Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                        @forelse($shipments as $shipment)
                            <tr class="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50 transition-colors">
                                <td class="px-5 py-3.5 align-top">
                                    <div class="font-bold text-neutral-900 dark:text-neutral-100">{{ $shipment->shipment_number }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        @if($shipment->purchaseOrder)
                                            PO: <span class="font-semibold text-primary-700 dark:text-primary-400">{{ $shipment->purchaseOrder->po_number }}</span>
                                        @else
                                            <span class="text-neutral-400 dark:text-neutral-500">Direct Inbound</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                        Supplier: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $shipment->supplier->name ?? 'N/A' }}</span>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $shipment->carrier_name }}</div>
                                    @if($shipment->tracking_number)
                                        <div class="mt-0.5 text-neutral-600 dark:text-neutral-400">Trk: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-200">{{ $shipment->tracking_number }}</span></div>
                                    @endif
                                    @if($shipment->vehicle_plate_number || $shipment->driver_name)
                                        <div class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                            {{ $shipment->driver_name ?: 'Driver N/A' }} <span class="text-neutral-400 dark:text-neutral-500">({{ $shipment->vehicle_plate_number ?? 'No Plate' }})</span>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    @if($shipment->sscc)
                                        <div class="font-mono font-bold tracking-wider text-neutral-900 dark:text-neutral-100">
                                            {{ substr($shipment->sscc, 0, 1) }} {{ substr($shipment->sscc, 1, 7) }} {{ substr($shipment->sscc, 8, 9) }} {{ substr($shipment->sscc, 17, 1) }}
                                        </div>
                                        <span class="mt-1 inline-flex items-center rounded-md bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600 ring-1 ring-inset ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700">GS1-128 SSCC</span>
                                    @else
                                        <span class="text-neutral-400 dark:text-neutral-500">Not serialized</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs whitespace-nowrap">
                                    <div class="text-neutral-600 dark:text-neutral-400">Exp: <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $shipment->estimated_delivery_date?->format('M d, Y') ?? 'Unscheduled' }}</span></div>
                                    @if($shipment->actual_delivery_date)
                                        <div class="mt-0.5 text-emerald-700 dark:text-emerald-400 font-medium">Act: {{ $shipment->actual_delivery_date->format('M d, Y') }}</div>
                                    @endif
                                    @if($shipment->isDelayed())
                                        <div class="mt-1">
                                            <span class="inline-flex items-center rounded-md bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-800/60">
                                                Delayed by {{ $shipment->calculateDaysDelayed() }} days
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    @if($shipment->is_cold_chain)
                                        <div class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 font-bold text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-950/60 dark:text-blue-300 dark:ring-blue-800/60">
                                            <x-ui.icon name="beaker" class="h-3.5 w-3.5 shrink-0" />
                                            <span>Cold Chain</span>
                                        </div>
                                        @if($shipment->temp_logger_serial)
                                            <div class="mt-1 text-[10px] font-mono text-neutral-500 dark:text-neutral-400">Logger: {{ $shipment->temp_logger_serial }}</div>
                                        @endif
                                        @if($shipment->temp_min !== null && $shipment->temp_max !== null)
                                            <div class="mt-0.5 text-[11px] font-semibold {{ $shipment->temp_excursion ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                                {{ number_format($shipment->temp_min, 1) }}°C - {{ number_format($shipment->temp_max, 1) }}°C
                                            </div>
                                        @endif
                                        @if($shipment->temp_excursion)
                                            <div class="mt-1">
                                                <span class="inline-flex items-center rounded-md bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-2xs animate-pulse">
                                                    EXCURSION DETECTED
                                                </span>
                                            </div>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">Ambient</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 align-top">
                                    @php
                                        $statusBadge = match($shipment->status) {
                                            'received' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/60',
                                            'arrived_at_dock' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-950/60 dark:text-teal-300 dark:ring-teal-800/60',
                                            'in_transit' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950/60 dark:text-blue-300 dark:ring-blue-800/60',
                                            'customs_hold' => 'bg-purple-50 text-purple-700 ring-purple-600/20 dark:bg-purple-950/60 dark:text-purple-300 dark:ring-purple-800/60',
                                            'dispatched' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800/60',
                                            default => 'bg-neutral-100 text-neutral-700 ring-neutral-600/20 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $statusBadge }}">
                                        {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
                                    </span>
                                </td>

                                <td class="px-5 py-3.5 align-top text-right whitespace-nowrap w-36 min-w-36">
                                    @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                                        @if(!$shipment->isDelivered())
                                            <button
                                                type="button"
                                                @click="selectedShipment = {{ $shipment->id }}; selectedShipmentNumber = '{{ $shipment->shipment_number }}'; isColdChain = {{ $shipment->is_cold_chain ? 'true' : 'false' }}; dockModalOpen = true"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-emerald-700 dark:hover:bg-emerald-500 transition active:scale-[0.98]"
                                            >
                                                <x-ui.icon name="check" class="h-3.5 w-3.5" />
                                                <span>Dock Arrival</span>
                                            </button>
                                        @else
                                            <span class="inline-flex h-8 items-center text-xs font-medium text-neutral-400 dark:text-neutral-500">Docked</span>
                                        @endif
                                    @else
                                        <span class="inline-flex h-8 items-center text-xs font-medium text-neutral-400 dark:text-neutral-500">Read only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-xs">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500">
                                        <x-ui.icon name="truck" class="h-6 w-6" />
                                    </div>
                                    <p class="mt-3 font-semibold text-neutral-700 dark:text-neutral-300">No shipments found</p>
                                    <p class="mt-1 text-neutral-500 dark:text-neutral-400">No inbound freight records match the active search and filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($shipments->hasPages())
                <div class="border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                    {{ $shipments->links() }}
                </div>
            @endif
        </div>

        {{-- Register Inbound Shipment Modal --}}
        @can(\App\Enums\Permission::ManageLogisticsRecords->value)
        <div x-show="shipmentModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="shipmentModalOpen" @click="shipmentModalOpen = false" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-2xs transition-opacity"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div x-show="shipmentModalOpen" class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle dark:bg-neutral-900 dark:border dark:border-neutral-800">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Register Inbound 3PL Shipment</h3>
                        <button @click="shipmentModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('inventory.logistics.shipments.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Linked Purchase Order</label>
                                <select name="purchase_order_id" class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                    <option value="">None / Direct Transfer</option>
                                    @foreach($openPurchaseOrders as $po)
                                        <option value="{{ $po->id }}">PO: {{ $po->po_number }} (Due: {{ $po->delivery_date?->format('M d, Y') ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Supplier</label>
                                <select name="supplier_id" class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                    <option value="">Select Supplier</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Carrier / Logistics Provider *</label>
                                <input type="text" name="carrier_name" required placeholder="e.g. Zuellig Pharma Logistics, 2GO, Lalamove"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Tracking / Air Waybill #</label>
                                <input type="text" name="tracking_number" placeholder="e.g. TRK-99882241"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Vehicle Plate #</label>
                                <input type="text" name="vehicle_plate_number" placeholder="e.g. NCD-8812"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Driver Name</label>
                                <input type="text" name="driver_name" placeholder="e.g. Juan dela Cruz"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Driver Contact #</label>
                                <input type="text" name="driver_contact" placeholder="e.g. 0917-123-4567"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="shipment_sscc_input" class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">GS1 SSCC Barcode (18 Numeric Digits)</label>
                                <x-ui.camera-scanner
                                    id="camera-scanner-shipment-sscc"
                                    target-input-id="shipment_sscc_input"
                                    validate-format="sscc"
                                    button-text="Scan SSCC"
                                    button-size="sm"
                                    button-variant="secondary"
                                    title="Scan Pallet / Shipment GS1 SSCC Barcode"
                                    hint="Align the 18-digit Serial Shipping Container Code barcode within the frame."
                                />
                            </div>
                            <input type="text" id="shipment_sscc_input" name="sscc" maxlength="18" placeholder="e.g. 000123456700000018"
                                   class="mt-1 block w-full font-mono rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            <p class="mt-1 text-[10px] text-neutral-500 dark:text-neutral-400">Serial Shipping Container Code per GS1-128 standard with Modulo-10 check digit.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Dispatch Date</label>
                                <input type="date" name="dispatch_date" value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Estimated Delivery Date</label>
                                <input type="date" name="estimated_delivery_date" value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            </div>
                        </div>

                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/80 p-4 space-y-3 dark:border-neutral-800 dark:bg-neutral-800/50" x-data="{ isCold: false }">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="is_cold_chain" id="is_cold_chain" value="1" x-model="isCold"
                                       class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900">
                                <label for="is_cold_chain" class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                                    <x-ui.icon name="beaker" class="inline-block h-4 w-4 align-text-bottom text-blue-600 dark:text-blue-400" /> Cold Chain Cargo (Vaccines / Biologics / Reagents 2°-8°C)
                                </label>
                            </div>

                            <div x-show="isCold" class="mt-2" x-cloak>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Temperature Data Logger Serial #</label>
                                <input type="text" name="temp_logger_serial" placeholder="e.g. SEN-LOG-9941A"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Logistics Notes</label>
                            <textarea name="notes" rows="2" placeholder="Packaging units, pallet counts, or special handling instructions..."
                                      class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500"></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-2.5 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                            <button type="button" @click="shipmentModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">Cancel</button>
                            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white hover:bg-primary-700 dark:hover:bg-primary-500 shadow-2xs transition">Register Shipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan

        {{-- Record Dock Arrival Modal --}}
        @can(\App\Enums\Permission::ManageLogisticsRecords->value)
        <div x-show="dockModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="dockModalOpen" @click="dockModalOpen = false" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-2xs transition-opacity"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div x-show="dockModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle dark:bg-neutral-900 dark:border dark:border-neutral-800">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Record Dock Arrival &amp; Telemetry</h3>
                        <button @click="dockModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition">&times;</button>
                    </div>

                    <form :action="'/inventory/logistics/shipments/' + selectedShipment + '/dock-arrival'" method="POST" class="mt-4 space-y-4"
                          data-confirm-title="Record dock arrival"
                          data-confirm-message="Are you sure you want to record the physical dock arrival for this shipment?"
                          data-confirm-label="Confirm Dock Arrival">
                        @csrf
                        <p class="text-xs text-neutral-600 dark:text-neutral-400">Arrival intake for <span class="font-bold text-neutral-900 dark:text-neutral-100" x-text="selectedShipmentNumber"></span> at HIMS Receiving Dock.</p>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Actual Delivery Date *</label>
                            <input type="date" name="actual_delivery_date" required value="{{ date('Y-m-d') }}"
                                   class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                        </div>

                        <template x-if="isColdChain">
                            <div class="rounded-xl border border-blue-200 bg-blue-50/80 p-3.5 space-y-3 dark:border-blue-900/60 dark:bg-blue-950/40">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-blue-900 dark:text-blue-200">
                                    <x-ui.icon name="beaker" class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                    <span>Thermal Data Logger Verification</span>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-300">Min Recorded (°C)</label>
                                        <input type="number" step="0.1" name="temp_min" placeholder="e.g. 3.2" required
                                               class="mt-1 block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-300">Max Recorded (°C)</label>
                                        <input type="number" step="0.1" name="temp_max" placeholder="e.g. 5.8" required
                                               class="mt-1 block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                    </div>
                                </div>
                                <p class="text-[10px] text-blue-700 dark:text-blue-300">Readings outside 2.0°C to 8.0°C will automatically trigger Quarantine Hold.</p>
                            </div>
                        </template>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Receiving Dock Remarks</label>
                            <textarea name="notes" rows="2" placeholder="Driver identification confirmed, seals intact..."
                                      class="mt-1 block w-full rounded-lg border-neutral-300 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500"></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-2.5 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                            <button type="button" @click="dockModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">Cancel</button>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 dark:hover:bg-emerald-500 shadow-2xs transition">Confirm Dock Arrival</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan
    </div>
</x-app-layout>
