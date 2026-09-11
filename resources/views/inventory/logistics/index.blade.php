<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">
                        COA GAM & BIR RA 11976 Compliant
                    </span>
                    <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                        WHO GDP Cold Chain
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-bold text-neutral-900">Document Tracking & Logistics Records (DTRS)</h2>
                <p class="text-sm text-neutral-600">Centralized procurement records, COA Appendix 50 IAR, GS1 logistics tracking, and immutable chain of custody.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                <a href="{{ route('inventory.logistics.documents') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Document
                </a>
                <a href="{{ route('inventory.logistics.shipments') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Shipment
                </a>
                @endcan
                <a href="{{ route('inventory.logistics.iar.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    IAR Processing
                </a>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Flash Notification Messages --}}
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

            {{-- Metric Cards Grid --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Document Records Card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Document Registry</span>
                        <span class="rounded-full bg-blue-100 p-2 text-blue-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['total_documents'] }}</span>
                        @if($metrics['pending_verification'] > 0)
                            <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                {{ $metrics['pending_verification'] }} pending audit
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                All Verified
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Secure SHA-256 vault & NAP retention rules</p>
                </div>

                {{-- COA IAR Progress Card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">COA GAM App. 50 (IAR)</span>
                        <span class="rounded-full bg-indigo-100 p-2 text-indigo-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['iar_pending_inspection'] + $metrics['iar_pending_acceptance'] }}</span>
                        <span class="text-xs font-medium text-neutral-600">
                            {{ $metrics['iar_pending_inspection'] }} inspect / {{ $metrics['iar_pending_acceptance'] }} accept
                        </span>
                    </div>
                    @if($metrics['iar_coa_due'] > 0)
                        <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-red-600"><x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5 shrink-0" /> {{ $metrics['iar_coa_due'] }} approaching 5-day COA deadline</p>
                    @else
                        <p class="mt-1 text-xs text-neutral-500">Statutory SoD inspection & property acceptance</p>
                    @endif
                </div>

                {{-- Active Inbound Shipments --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Inbound Shipments</span>
                        <span class="rounded-full bg-cyan-100 p-2 text-cyan-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['active_shipments'] }}</span>
                        @if($metrics['overdue_shipments'] > 0)
                            <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                                {{ $metrics['overdue_shipments'] }} delayed
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                On schedule
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Carrier dispatch & GS1 SSCC tracking</p>
                </div>

                {{-- Cold Chain & Temperature Excursion --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Cold Chain Integrity</span>
                        <span class="rounded-full bg-teal-100 p-2 text-teal-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['dock_excursions'] }}</span>
                        @if($metrics['dock_excursions'] > 0)
                            <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                Excursion Holds
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                2.0°C - 8.0°C Normal
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">WHO GDP thermal logger monitoring</p>
                </div>
            </div>

            {{-- Main Two-Column Layout --}}
            <div class="grid gap-6 lg:grid-cols-12">
                {{-- Left Section (8 cols): Shipments and IARs --}}
                <div class="space-y-6 lg:col-span-8">
                    {{-- Active Inbound Shipments Table --}}
                    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-neutral-900">Inbound Logistics & Carrier Deliveries</h3>
                                <p class="text-xs text-neutral-500">Live 3PL carriers, GS1 SSCC, and receiving dock status</p>
                            </div>
                            <a href="{{ route('inventory.logistics.shipments') }}" class="text-xs font-semibold text-primary-700 hover:text-primary-800">
                                View all shipments &rarr;
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                                    <tr>
                                        <th class="px-6 py-3">Shipment # / PO</th>
                                        <th class="px-6 py-3">Carrier / Tracking</th>
                                        <th class="px-6 py-3">Expected Date</th>
                                        <th class="px-6 py-3">Cold Chain</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 bg-white">
                                    @forelse($recentShipments as $shipment)
                                        <tr class="hover:bg-neutral-50">
                                            <td class="px-6 py-3.5">
                                                <div class="font-semibold text-neutral-900">{{ $shipment->shipment_number }}</div>
                                                <div class="text-xs text-neutral-500">
                                                    {{ $shipment->purchaseOrder ? 'PO: '.$shipment->purchaseOrder->po_number : 'Direct Transfer' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <div class="text-neutral-900">{{ $shipment->carrier_name }}</div>
                                                <div class="text-xs text-neutral-500">{{ $shipment->tracking_number ?? 'Waybill: '.($shipment->waybill_number ?? 'N/A') }}</div>
                                            </td>
                                            <td class="px-6 py-3.5 whitespace-nowrap">
                                                <div class="text-neutral-900">{{ $shipment->estimated_delivery_date?->format('M d, Y') ?? 'Not specified' }}</div>
                                                @if($shipment->isDelayed())
                                                    <span class="inline-flex items-center rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">
                                                        Delay: {{ $shipment->calculateDaysDelayed() }} days
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3.5">
                                                @if($shipment->is_cold_chain)
                                                    <span class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                                        <x-ui.icon name="beaker" class="inline-block h-3.5 w-3.5 align-text-bottom" /> Cold Chain
                                                    </span>
                                                    @if($shipment->temp_excursion)
                                                        <span class="mt-1 flex items-center gap-1 text-[10px] font-bold text-red-600"><x-ui.icon name="exclamation-triangle" class="h-3 w-3 shrink-0" /> EXCURSION</span>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-neutral-400">Ambient</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                                    @if($shipment->status === 'received' || $shipment->status === 'arrived_at_dock') bg-green-100 text-green-800
                                                    @elseif($shipment->status === 'in_transit') bg-blue-100 text-blue-800
                                                    @else bg-amber-100 text-amber-800 @endif">
                                                    {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3.5 text-right">
                                                <a href="{{ route('inventory.logistics.shipments', ['search' => $shipment->shipment_number]) }}" class="text-xs font-medium text-primary-600 hover:text-primary-800">
                                                    Manage
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500">
                                                No active inbound shipments recorded. Register a new shipment above.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- COA GAM Appendix 50 Inspection & Acceptance Reports --}}
                    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-neutral-900">COA Inspection & Acceptance Reports (Appendix 50)</h3>
                                <p class="text-xs text-neutral-500">Mandatory dual-step Technical Inspection & Property Custodial Acceptance</p>
                            </div>
                            <a href="{{ route('inventory.logistics.iar.index') }}" class="text-xs font-semibold text-primary-700 hover:text-primary-800">
                                View full IAR ledger &rarr;
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                                    <tr>
                                        <th class="px-6 py-3">IAR #</th>
                                        <th class="px-6 py-3">PO & Supplier</th>
                                        <th class="px-6 py-3">Delivery Docs</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3">COA Transmittal</th>
                                        <th class="px-6 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 bg-white">
                                    @forelse($recentIars as $iar)
                                        <tr class="hover:bg-neutral-50">
                                            <td class="px-6 py-3.5">
                                                <div class="font-bold text-neutral-900">{{ $iar->iar_number }}</div>
                                                <div class="text-xs text-neutral-500">{{ $iar->created_at->format('M d, Y') }}</div>
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <div class="font-medium text-neutral-900">{{ $iar->purchaseOrder->po_number ?? 'Direct Receipt' }}</div>
                                                <div class="text-xs text-neutral-500">{{ $iar->supplier->name ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-6 py-3.5 text-xs text-neutral-600">
                                                <div>DR: <span class="font-mono font-medium">{{ $iar->goodsReceiptNote->dr_number ?? 'None' }}</span></div>
                                                <div>SI: <span class="font-mono font-medium">{{ $iar->invoice_number ?? 'Pending' }}</span></div>
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                                    @if($iar->status === 'accepted') bg-emerald-100 text-emerald-800
                                                    @elseif($iar->status === 'inspected_passed') bg-blue-100 text-blue-800
                                                    @elseif($iar->status === 'inspected_failed' || $iar->status === 'rejected') bg-red-100 text-red-800
                                                    @else bg-amber-100 text-amber-800 @endif">
                                                    {{ ucwords(str_replace('_', ' ', $iar->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3.5 text-xs">
                                                @if($iar->coa_transmitted_at)
                                                    <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                                                        <x-ui.icon name="check-circle" class="inline-block h-3.5 w-3.5 align-text-bottom" /> Transmitted ({{ $iar->coa_transmitted_at->format('M d') }})
                                                    </span>
                                                @elseif($iar->isAccepted())
                                                    <span class="inline-flex items-center gap-1 font-semibold text-amber-700">
                                                        ⏳ Due within 5 days
                                                    </span>
                                                @else
                                                    <span class="text-neutral-400">Awaiting acceptance</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3.5 text-right">
                                                <a href="{{ route('inventory.logistics.iar.show', $iar) }}" class="rounded bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-200">
                                                    View Report
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500">
                                                No Inspection and Acceptance Reports recorded. Receipts can be converted to IAR in the IAR Processing tab.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Right Section (4 cols): Chain of Custody & Documents Registry --}}
                <div class="space-y-6 lg:col-span-4">
                    {{-- Immutable Chain of Custody Stream --}}
                    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                            <h3 class="text-sm font-semibold text-neutral-900">Chain of Custody Stream</h3>
                            <a href="{{ route('inventory.logistics.chain-of-custody') }}" class="text-xs font-semibold text-primary-700 hover:text-primary-800">
                                View ledger &rarr;
                            </a>
                        </div>
                        <div class="mt-4 flow-root">
                            <ul role="list" class="-mb-6">
                                @forelse($recentCustodyLogs as $log)
                                    <li>
                                        <div class="relative pb-6">
                                            @if(!$loop->last)
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-neutral-200" aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-4 ring-white">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5">
                                                    <p class="text-xs font-bold text-neutral-900">
                                                        {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                                    </p>
                                                    <p class="text-xs text-neutral-600">
                                                        <span class="font-medium text-neutral-800">{{ $log->releasing_party_name ?? ($log->releasingUser->name ?? 'Issuer') }}</span> &rarr;
                                                        <span class="font-medium text-neutral-800">{{ $log->receiving_party_name ?? ($log->receivingUser->name ?? 'Recipient') }}</span>
                                                    </p>
                                                    <div class="mt-1 flex items-center gap-2 text-[10px] text-neutral-400">
                                                        <span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" class="h-3.5 w-3.5 shrink-0" /> {{ $log->destination_location ?? $log->origin_location }}</span>
                                                        <span>•</span>
                                                        <span>{{ $log->transferred_at->diffForHumans() }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-center py-6 text-xs text-neutral-400">
                                        No custody transfers logged yet.
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    {{-- Recent Logistics Documents --}}
                    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                            <h3 class="text-sm font-semibold text-neutral-900">Recent Documents</h3>
                            <a href="{{ route('inventory.logistics.documents') }}" class="text-xs font-semibold text-primary-700 hover:text-primary-800">
                                Full registry &rarr;
                            </a>
                        </div>
                        <div class="mt-3 divide-y divide-neutral-100">
                            @forelse($recentDocuments as $doc)
                                <div class="py-3 flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-neutral-600">
                                                {{ $doc->document_type->abbreviation() }}
                                            </span>
                                            <p class="truncate text-xs font-semibold text-neutral-900">{{ $doc->title }}</p>
                                        </div>
                                        <p class="mt-0.5 font-mono text-[10px] text-neutral-500">{{ $doc->tracking_number }} • v{{ $doc->version_number }}</p>
                                        <div class="mt-1 flex items-center gap-2 text-[10px]">
                                            @if($doc->isVerified())
                                                <span class="inline-flex items-center gap-1 font-semibold text-emerald-600"><x-ui.icon name="check-circle" class="h-3.5 w-3.5 shrink-0" /> Verified SHA-256</span>
                                            @else
                                                <span class="font-semibold text-amber-600">⏳ Pending Audit</span>
                                            @endif
                                            <span class="text-neutral-400">• {{ number_format($doc->file_size_bytes / 1024, 1) }} KB</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('inventory.logistics.documents.download', $doc) }}" class="rounded p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600" title="Download Document">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                </div>
                            @empty
                                <div class="text-center py-6 text-xs text-neutral-400">
                                    No documents uploaded yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
