<x-app-layout full-width>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 dark:border dark:border-blue-800/60">
                        COA GAM &amp; BIR RA 11976 Compliant
                    </span>
                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/60">
                        WHO GDP Cold Chain
                    </span>
                </div>
                <h2 class="mt-1.5 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">Document Tracking & Logistics Records (DTRS)</h2>
            </div>
        </div>
    </x-slot>

    <div class="w-full space-y-6">
        {{-- Flash Notification Messages --}}
        @if(session('success'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-xs dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">
                <x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('warning'))
            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 shadow-xs dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
                <x-ui.icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div>{{ session('warning') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-xs dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
                <x-ui.icon name="exclamation-circle" class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400" />
                <div>{{ session('error') }}</div>
            </div>
        @endif

        {{-- Metric Cards Grid --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Document Records Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Document Registry</span>
                    <span class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                        <x-ui.icon name="document-text" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 tabular-nums">{{ $metrics['total_documents'] }}</span>
                    @if($metrics['pending_verification'] > 0)
                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 dark:border dark:border-amber-800/50">
                            {{ $metrics['pending_verification'] }} pending audit
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/50">
                            All Verified
                        </span>
                    @endif
                </div>
                <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">Secure SHA-256 vault &amp; NAP retention rules</p>
            </div>

            {{-- COA IAR Progress Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">COA GAM App. 50 (IAR)</span>
                    <span class="rounded-lg bg-indigo-50 p-2 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                        <x-ui.icon name="clipboard-document-check" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 tabular-nums">{{ $metrics['iar_pending_inspection'] + $metrics['iar_pending_acceptance'] }}</span>
                    <span class="text-xs font-medium text-neutral-600 dark:text-neutral-400">
                        {{ $metrics['iar_pending_inspection'] }} inspect / {{ $metrics['iar_pending_acceptance'] }} accept
                    </span>
                </div>
                @if($metrics['iar_coa_due'] > 0)
                    <p class="mt-1.5 flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                        <x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5 shrink-0" />
                        <span>{{ $metrics['iar_coa_due'] }} approaching 5-day COA deadline</span>
                    </p>
                @else
                    <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">Statutory SoD inspection &amp; property acceptance</p>
                @endif
            </div>

            {{-- Active Inbound Shipments --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Inbound Shipments</span>
                    <span class="rounded-lg bg-cyan-50 p-2 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-400">
                        <x-ui.icon name="truck" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 tabular-nums">{{ $metrics['active_shipments'] }}</span>
                    @if($metrics['overdue_shipments'] > 0)
                        <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-800/50">
                            {{ $metrics['overdue_shipments'] }} delayed
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/50">
                            On schedule
                        </span>
                    @endif
                </div>
                <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">Carrier dispatch &amp; GS1 SSCC tracking</p>
            </div>

            {{-- Cold Chain & Temperature Excursion --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Cold Chain Integrity</span>
                    <span class="rounded-lg bg-teal-50 p-2 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400">
                        <x-ui.icon name="beaker" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 tabular-nums">{{ $metrics['dock_excursions'] }}</span>
                    @if($metrics['dock_excursions'] > 0)
                        <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-800/50">
                            Excursion Holds
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/50">
                            2.0°C - 8.0°C Normal
                        </span>
                    @endif
                </div>
                <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">WHO GDP thermal logger monitoring</p>
            </div>
        </div>

        {{-- Navigation: Major Dropdown Tabs --}}
        @include('inventory.logistics.partials.nav')

        {{-- Main Two-Column Layout --}}
        @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
        <div class="grid gap-6 lg:grid-cols-12 2xl:grid-cols-12 items-start">
            {{-- Left Section (8 cols on desktop): Shipments and IARs --}}
            <div class="space-y-6 lg:col-span-8 2xl:col-span-8 min-w-0">
                {{-- Active Inbound Shipments Table --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4 dark:border-neutral-800">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Inbound Logistics &amp; Carrier Deliveries</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Live 3PL carriers, GS1 SSCC, and receiving dock status</p>
                        </div>
                        <a href="{{ route('inventory.logistics.shipments') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors">
                            View all shipments
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-sm">
                            <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                <tr>
                                    <th class="px-6 py-3.5">Shipment # / PO</th>
                                    <th class="px-6 py-3.5">Carrier / Tracking</th>
                                    <th class="px-6 py-3.5">Expected Date</th>
                                    <th class="px-6 py-3.5">Cold Chain</th>
                                    <th class="px-6 py-3.5">Status</th>
                                    <th class="px-6 py-3.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                                @forelse($recentShipments as $shipment)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $shipment->shipment_number }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                                {{ $shipment->purchaseOrder ? 'PO: '.$shipment->purchaseOrder->po_number : 'Direct Transfer' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-neutral-900 dark:text-neutral-200">{{ $shipment->carrier_name }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400 font-mono">{{ $shipment->tracking_number ?? 'Waybill: '.($shipment->waybill_number ?? 'N/A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-neutral-900 dark:text-neutral-200">{{ $shipment->estimated_delivery_date?->format('M d, Y') ?? 'Not specified' }}</div>
                                            @if($shipment->isDelayed())
                                                <span class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-900/50">
                                                    Delay: {{ $shipment->calculateDaysDelayed() }} days
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($shipment->is_cold_chain)
                                                <span class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 dark:border dark:border-blue-900/50">
                                                    <x-ui.icon name="beaker" class="inline-block h-3.5 w-3.5 align-text-bottom" /> Cold Chain
                                                </span>
                                                @if($shipment->temp_excursion)
                                                    <span class="mt-1 flex items-center gap-1 text-[10px] font-bold text-rose-600 dark:text-rose-400"><x-ui.icon name="exclamation-triangle" class="h-3 w-3 shrink-0" /> EXCURSION</span>
                                                @endif
                                            @else
                                                <span class="text-xs text-neutral-400 dark:text-neutral-500">Ambient</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                                @if($shipment->status === 'received' || $shipment->status === 'arrived_at_dock') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/40
                                                @elseif($shipment->status === 'in_transit') bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 dark:border dark:border-blue-800/40
                                                @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 dark:border dark:border-amber-800/40 @endif">
                                                {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('inventory.logistics.shipments', ['search' => $shipment->shipment_number]) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                            No active inbound shipments recorded. Register a new shipment above.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- COA GAM Appendix 50 Inspection & Acceptance Reports --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4 dark:border-neutral-800">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">COA Inspection &amp; Acceptance Reports (Appendix 50)</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Mandatory dual-step Technical Inspection &amp; Property Custodial Acceptance</p>
                        </div>
                        <a href="{{ route('inventory.logistics.iar.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors">
                            View full IAR ledger
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-sm">
                            <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                <tr>
                                    <th class="px-6 py-3.5">IAR #</th>
                                    <th class="px-6 py-3.5">PO &amp; Supplier</th>
                                    <th class="px-6 py-3.5">Delivery Docs</th>
                                    <th class="px-6 py-3.5">Status</th>
                                    <th class="px-6 py-3.5">COA Transmittal</th>
                                    <th class="px-6 py-3.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                                @forelse($recentIars as $iar)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-neutral-900 dark:text-neutral-100 font-mono">{{ $iar->iar_number }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $iar->created_at->format('M d, Y') }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-neutral-900 dark:text-neutral-200">{{ $iar->purchaseOrder->po_number ?? 'Direct Receipt' }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $iar->supplier->name ?? 'N/A' }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-neutral-600 dark:text-neutral-400">
                                            <div>DR: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-300">{{ $iar->goodsReceiptNote->dr_number ?? 'None' }}</span></div>
                                            <div>SI: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-300">{{ $iar->invoice_number ?? 'Pending' }}</span></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                                @if($iar->status === 'accepted') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/40
                                                @elseif($iar->status === 'inspected_passed') bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 dark:border dark:border-blue-800/40
                                                @elseif($iar->status === 'inspected_failed' || $iar->status === 'rejected') bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-800/40
                                                @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 dark:border dark:border-amber-800/40 @endif">
                                                {{ ucwords(str_replace('_', ' ', $iar->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-xs">
                                            @if($iar->coa_transmitted_at)
                                                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400">
                                                    <x-ui.icon name="check-circle" class="inline-block h-3.5 w-3.5 align-text-bottom" /> Transmitted ({{ $iar->coa_transmitted_at->format('M d') }})
                                                </span>
                                            @elseif($iar->isAccepted())
                                                <span class="inline-flex items-center gap-1 font-semibold text-amber-700 dark:text-amber-400">
                                                    <x-ui.icon name="clock" class="h-3.5 w-3.5 shrink-0" /> Due within 5 days
                                                </span>
                                            @else
                                                <span class="text-neutral-400 dark:text-neutral-500">Awaiting acceptance</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('inventory.logistics.iar.show', $iar) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">
                                                View Report
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                            No Inspection and Acceptance Reports recorded. Receipts can be converted to IAR in the IAR Processing tab.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Right Section (4 cols on desktop, 2-cols side-by-side on tablet): Chain of Custody & Documents Registry --}}
            <div class="space-y-6 md:grid md:grid-cols-2 md:gap-6 md:space-y-0 lg:block lg:space-y-6 lg:col-span-4 2xl:col-span-4 min-w-0">
                {{-- Immutable Chain of Custody Stream --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Chain of Custody Stream</h3>
                        <a href="{{ route('inventory.logistics.chain-of-custody') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors">
                            View ledger
                        </a>
                    </div>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-mb-6">
                            @forelse($recentCustodyLogs as $log)
                                <li>
                                    <div class="relative pb-6">
                                        @if(!$loop->last)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-neutral-200 dark:bg-neutral-800" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-4 ring-white dark:bg-blue-950/60 dark:text-blue-400 dark:ring-neutral-900">
                                                    <x-ui.icon name="clock" class="h-4 w-4" />
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <p class="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                                    {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-300 flex items-center flex-wrap gap-1">
                                                    <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $log->releasing_party_name ?? ($log->releasingUser->name ?? 'Issuer') }}</span>
                                                    <x-ui.icon name="arrow-right" class="h-3 w-3 text-neutral-400 dark:text-neutral-500 shrink-0" />
                                                    <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $log->receiving_party_name ?? ($log->receivingUser->name ?? 'Recipient') }}</span>
                                                </p>
                                                <div class="mt-1.5 flex items-center gap-2 text-[10px] text-neutral-400 dark:text-neutral-500">
                                                    <span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" class="h-3.5 w-3.5 shrink-0" /> {{ $log->destination_location ?? $log->origin_location }}</span>
                                                    <span>•</span>
                                                    <span>{{ $log->transferred_at->diffForHumans() }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="text-center py-6 text-xs text-neutral-400 dark:text-neutral-500">
                                    No custody transfers logged yet.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Recent Logistics Documents --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Recent Documents</h3>
                        <a href="{{ route('inventory.logistics.documents') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors">
                            Full registry
                        </a>
                    </div>
                    <div class="mt-3 divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse($recentDocuments as $doc)
                            <div class="py-3 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                            {{ $doc->document_type->abbreviation() }}
                                        </span>
                                        <p class="truncate text-xs font-semibold text-neutral-900 dark:text-neutral-100">{{ $doc->title }}</p>
                                    </div>
                                    <p class="mt-0.5 font-mono text-[10px] text-neutral-500 dark:text-neutral-400">{{ $doc->tracking_number }} • v{{ $doc->version_number }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-[10px]">
                                        @if($doc->isVerified())
                                            <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                                <x-ui.icon name="check-circle" class="h-3.5 w-3.5 shrink-0" /> Verified SHA-256
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400">
                                                <x-ui.icon name="clock" class="h-3.5 w-3.5 shrink-0" /> Pending Audit
                                            </span>
                                        @endif
                                        <span class="text-neutral-400 dark:text-neutral-500">• {{ number_format($doc->file_size_bytes / 1024, 1) }} KB</span>
                                    </div>
                                </div>
                                <a href="{{ route('inventory.logistics.documents.download', $doc) }}" class="rounded-md p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-300 transition" data-hims-download data-loading-text="Preparing document..." data-download-name="{{ $doc->original_name ?: ($doc->file_name ?: 'document') }}" title="Download Document">
                                    <x-ui.icon name="arrow-down-tray" class="h-4 w-4" />
                                </a>
                            </div>
                        @empty
                            <div class="text-center py-6 text-xs text-neutral-400 dark:text-neutral-500">
                                No documents uploaded yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @else
            <x-ui.alert variant="info" title="Summary access only">
                Detailed shipment identifiers, logistics documents, inspection reports, and chain-of-custody evidence are restricted to operational and audit roles.
            </x-ui.alert>
        @endcan
    </div>
</x-app-layout>
