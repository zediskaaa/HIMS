<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">3PL Carrier & Dock Inbound Tracking</p>
                <h2 class="text-2xl font-bold text-neutral-900">Shipments & Carrier Logistics</h2>
                <p class="text-sm text-neutral-600">Inbound logistics monitoring, GS1 SSCC validation, WHO GDP cold-chain thermal loggers, and dock arrival handoffs.</p>
            </div>
            <div class="flex items-center gap-2" x-data>
                <button @click="$dispatch('open-shipment-modal')" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Register Inbound Shipment
                </button>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6" x-data="{ shipmentModalOpen: false, dockModalOpen: false, selectedShipment: null, selectedShipmentNumber: '', isColdChain: false }"
         @open-shipment-modal.window="shipmentModalOpen = true">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Flash Notifications --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('warning'))
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>{{ session('warning') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- Filter Bar --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('inventory.logistics.shipments') }}" class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-5">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search shipment #, carrier, tracking #, or GS1 SSCC..."
                                   class="block w-full rounded-lg border-neutral-300 pl-10 text-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <select name="status" class="block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Statuses</option>
                            <option value="dispatched" {{ request('status') === 'dispatched' ? 'selected' : '' }}>Dispatched</option>
                            <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                            <option value="customs_hold" {{ request('status') === 'customs_hold' ? 'selected' : '' }}>Customs / Quarantine Hold</option>
                            <option value="arrived_at_dock" {{ request('status') === 'arrived_at_dock' ? 'selected' : '' }}>Arrived at Dock</option>
                            <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received & Accepted</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <select name="cold_chain" class="block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Temperatures</option>
                            <option value="1" {{ request('cold_chain') === '1' ? 'selected' : '' }}>Cold Chain Only (2°-8°C)</option>
                            <option value="0" {{ request('cold_chain') === '0' ? 'selected' : '' }}>Ambient Only</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <button type="submit" class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800">
                            Filter
                        </button>
                        @if(request()->hasAny(['search', 'status', 'cold_chain']))
                            <a href="{{ route('inventory.logistics.shipments') }}" class="rounded-lg border border-neutral-300 p-2 text-neutral-600 hover:bg-neutral-50" title="Reset Filters">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Shipments Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <tr>
                                <th class="px-6 py-3.5">Shipment & Origin</th>
                                <th class="px-6 py-3.5">Carrier / 3PL Info</th>
                                <th class="px-6 py-3.5">GS1 SSCC Barcode</th>
                                <th class="px-6 py-3.5">Delivery Schedule</th>
                                <th class="px-6 py-3.5">Cold Chain Integrity</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-right">Dock Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white">
                            @forelse($shipments as $shipment)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-neutral-900">{{ $shipment->shipment_number }}</div>
                                        <div class="text-xs text-neutral-600">
                                            @if($shipment->purchaseOrder)
                                                PO: <span class="font-semibold text-primary-700">{{ $shipment->purchaseOrder->po_number }}</span>
                                            @else
                                                <span class="text-neutral-400">Direct Inbound</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-[11px] text-neutral-500">
                                            Supplier: <span class="font-medium text-neutral-700">{{ $shipment->supplier->name ?? 'N/A' }}</span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        <div class="font-semibold text-neutral-900">{{ $shipment->carrier_name }}</div>
                                        @if($shipment->tracking_number)
                                            <div class="text-neutral-600">Trk: <span class="font-mono font-medium">{{ $shipment->tracking_number }}</span></div>
                                        @endif
                                        @if($shipment->vehicle_plate_number || $shipment->driver_name)
                                            <div class="mt-1 text-[10px] text-neutral-500">
                                                {{ $shipment->driver_name }} ({{ $shipment->vehicle_plate_number ?? 'No Plate' }})
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        @if($shipment->sscc)
                                            <div class="font-mono font-bold text-neutral-900 tracking-wider">
                                                {{ substr($shipment->sscc, 0, 1) }} {{ substr($shipment->sscc, 1, 7) }} {{ substr($shipment->sscc, 8, 9) }} {{ substr($shipment->sscc, 17, 1) }}
                                            </div>
                                            <span class="inline-flex rounded bg-neutral-100 px-1.5 py-0.5 text-[9px] font-semibold text-neutral-600">GS1-128 SSCC</span>
                                        @else
                                            <span class="text-neutral-400">Not serialized</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs whitespace-nowrap">
                                        <div>Exp: <span class="font-medium text-neutral-900">{{ $shipment->estimated_delivery_date?->format('M d, Y') ?? 'Unscheduled' }}</span></div>
                                        @if($shipment->actual_delivery_date)
                                            <div class="mt-0.5 text-emerald-700 font-medium">Act: {{ $shipment->actual_delivery_date->format('M d, Y') }}</div>
                                        @endif
                                        @if($shipment->isDelayed())
                                            <div class="mt-1">
                                                <span class="inline-flex items-center rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-800">
                                                    Delayed by {{ $shipment->calculateDaysDelayed() }} days
                                                </span>
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        @if($shipment->is_cold_chain)
                                            <div class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 font-bold text-blue-700">
                                                <x-ui.icon name="beaker" class="inline-block h-3.5 w-3.5 align-text-bottom" /> Cold Chain
                                            </div>
                                            @if($shipment->temp_logger_serial)
                                                <div class="mt-1 text-[10px] text-neutral-500 font-mono">Logger: {{ $shipment->temp_logger_serial }}</div>
                                            @endif
                                            @if($shipment->temp_min !== null && $shipment->temp_max !== null)
                                                <div class="mt-0.5 text-[11px] font-semibold {{ $shipment->temp_excursion ? 'text-red-600' : 'text-emerald-700' }}">
                                                    {{ number_format($shipment->temp_min, 1) }}°C - {{ number_format($shipment->temp_max, 1) }}°C
                                                </div>
                                            @endif
                                            @if($shipment->temp_excursion)
                                                <span class="mt-1 inline-flex rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white animate-pulse">
                                                    EXCURSION DETECTED
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-neutral-400">Ambient</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                            @if($shipment->status === 'received' || $shipment->status === 'arrived_at_dock') bg-green-100 text-green-800
                                            @elseif($shipment->status === 'in_transit') bg-blue-100 text-blue-800
                                            @elseif($shipment->status === 'customs_hold') bg-purple-100 text-purple-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        @if(!$shipment->isDelivered())
                                            <button @click="selectedShipment = {{ $shipment->id }}; selectedShipmentNumber = '{{ $shipment->shipment_number }}'; isColdChain = {{ $shipment->is_cold_chain ? 'true' : 'false' }}; dockModalOpen = true"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                Dock Arrival
                                            </button>
                                        @else
                                            <span class="text-xs text-neutral-400">Docked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-neutral-500">
                                        No shipments registered matching the criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($shipments->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $shipments->links() }}
                    </div>
                @endif
            </div>

            {{-- Register Inbound Shipment Modal --}}
            <div x-show="shipmentModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="shipmentModalOpen" @click="shipmentModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="shipmentModalOpen" class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-lg font-bold text-neutral-900">Register Inbound 3PL Shipment</h3>
                            <button @click="shipmentModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('inventory.logistics.shipments.store') }}" class="mt-4 space-y-4">
                            @csrf
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Linked Purchase Order</label>
                                    <select name="purchase_order_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">None / Direct Transfer</option>
                                        @foreach($openPurchaseOrders as $po)
                                            <option value="{{ $po->id }}">PO: {{ $po->po_number }} (Due: {{ $po->delivery_date?->format('M d, Y') ?? 'N/A' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Supplier</label>
                                    <select name="supplier_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">Select Supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Carrier / Logistics Provider *</label>
                                    <input type="text" name="carrier_name" required placeholder="e.g. Zuellig Pharma Logistics, 2GO, Lalamove"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Tracking / Air Waybill #</label>
                                    <input type="text" name="tracking_number" placeholder="e.g. TRK-99882241"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Vehicle Plate #</label>
                                    <input type="text" name="vehicle_plate_number" placeholder="e.g. NCD-8812"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Driver Name</label>
                                    <input type="text" name="driver_name" placeholder="e.g. Juan dela Cruz"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Driver Contact #</label>
                                    <input type="text" name="driver_contact" placeholder="e.g. 0917-123-4567"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">GS1 SSCC Barcode (18 Numeric Digits)</label>
                                <input type="text" name="sscc" maxlength="18" placeholder="e.g. 000123456700000018"
                                       class="mt-1 block w-full font-mono rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <p class="mt-0.5 text-[10px] text-neutral-500">Serial Shipping Container Code per GS1-128 standard with Modulo-10 check digit.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Dispatch Date</label>
                                    <input type="date" name="dispatch_date" value="{{ date('Y-m-d') }}"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Estimated Delivery Date</label>
                                    <input type="date" name="estimated_delivery_date" value="{{ date('Y-m-d') }}"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>

                            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 space-y-3" x-data="{ isCold: false }">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="is_cold_chain" id="is_cold_chain" value="1" x-model="isCold"
                                           class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                                    <label for="is_cold_chain" class="text-sm font-semibold text-neutral-900">
                                        <x-ui.icon name="beaker" class="inline-block h-4 w-4 align-text-bottom" /> Cold Chain Cargo (Vaccines / Biologics / Reagents 2°-8°C)
                                    </label>
                                </div>

                                <div x-show="isCold" class="mt-2">
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Temperature Data Logger Serial #</label>
                                    <input type="text" name="temp_logger_serial" placeholder="e.g. SEN-LOG-9941A"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Logistics Notes</label>
                                <textarea name="notes" rows="2" placeholder="Packaging units, pallet counts, or special handling instructions..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="shipmentModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Register Shipment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Record Dock Arrival Modal --}}
            <div x-show="dockModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="dockModalOpen" @click="dockModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="dockModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Record Dock Arrival & Telemetry</h3>
                            <button @click="dockModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/shipments/' + selectedShipment + '/dock-arrival'" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">Arrival intake for <span class="font-bold text-neutral-900" x-text="selectedShipmentNumber"></span> at HIMS Receiving Dock.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Actual Delivery Date *</label>
                                <input type="date" name="actual_delivery_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <template x-if="isColdChain">
                                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 space-y-3">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-blue-900"><x-ui.icon name="beaker" class="h-4 w-4 shrink-0" /> Thermal Data Logger Verification</div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-neutral-600">Min Recorded (°C)</label>
                                            <input type="number" step="0.1" name="temp_min" placeholder="e.g. 3.2" required
                                                   class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-neutral-600">Max Recorded (°C)</label>
                                            <input type="number" step="0.1" name="temp_max" placeholder="e.g. 5.8" required
                                                   class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-blue-700">Readings outside 2.0°C to 8.0°C will automatically trigger Quarantine Hold.</p>
                                </div>
                            </template>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Receiving Dock Remarks</label>
                                <textarea name="notes" rows="2" placeholder="Driver identification confirmed, seals intact..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="dockModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Confirm Dock Arrival</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
