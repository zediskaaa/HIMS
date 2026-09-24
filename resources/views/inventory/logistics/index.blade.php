<x-app-layout full-width>
    <x-slot name="header">
        <x-ui.page-header
            :title="new \Illuminate\Support\HtmlString('Document Tracking & Logistics Records (DTRS)')"
            subtitle="COA GAM App. 50 statutory acceptance, WHO GDP cold chain custody, and BIR RA 11976 digital document vault"
            :breadcrumbs="[
                'Inventory' => route('inventory.items'),
                'Logistics & DTRS' => '',
            ]"
        />
    </x-slot>

    <div class="w-full space-y-6">
        {{-- Flash Notification Messages (Persistent Operational Warnings / Errors) --}}
        @if(session('warning'))
            <x-ui.alert variant="warning" :message="session('warning')" />
        @endif

        @if(session('error'))
            <x-ui.alert variant="danger" :message="session('error')" />
        @endif

        {{-- Metric Cards: Standardized 3-Zone Architecture --}}
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Zone 1: Document Registry --}}
            <div class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Document Registry</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50">
                        <x-ui.icon name="document-text" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white">{{ number_format($metrics['total_documents']) }}</span>
                    <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">records</span>
                </div>
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">
                        @if($metrics['pending_verification'] > 0)
                            {{ $metrics['pending_verification'] }} pending audit verification
                        @else
                            All records verified &amp; SHA-256 sealed
                        @endif
                    </span>
                </div>
            </div>

            {{-- Zone 2: COA GAM App. 50 (IAR) --}}
            <div class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">COA GAM App. 50 (IAR)</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-950/80 dark:text-indigo-300 dark:ring-indigo-800/50">
                        <x-ui.icon name="clipboard-document-check" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white">{{ number_format($metrics['iar_pending_inspection'] + $metrics['iar_pending_acceptance']) }}</span>
                    <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">in review</span>
                </div>
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium {{ $metrics['iar_coa_due'] > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-neutral-600 dark:text-neutral-300' }} truncate">
                        @if($metrics['iar_coa_due'] > 0)
                            {{ $metrics['iar_coa_due'] }} approaching 5-day COA deadline
                        @else
                            {{ $metrics['iar_pending_inspection'] }} inspect / {{ $metrics['iar_pending_acceptance'] }} accept
                        @endif
                    </span>
                </div>
            </div>

            {{-- Zone 3: Inbound Shipments --}}
            <div class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Inbound Shipments</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700 ring-1 ring-cyan-200 dark:bg-cyan-950/80 dark:text-cyan-300 dark:ring-cyan-800/50">
                        <x-ui.icon name="truck" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white">{{ number_format($metrics['active_shipments']) }}</span>
                    <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">in transit</span>
                </div>
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium {{ $metrics['overdue_shipments'] > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-neutral-600 dark:text-neutral-300' }} truncate">
                        @if($metrics['overdue_shipments'] > 0)
                            {{ $metrics['overdue_shipments'] }} delayed shipments
                        @else
                            Carrier dispatch &amp; GS1 SSCC tracking on schedule
                        @endif
                    </span>
                </div>
            </div>

            {{-- Zone 4: Cold Chain Integrity --}}
            @php
                $hasExcursion = $metrics['dock_excursions'] > 0;
            @endphp
            <div class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider {{ $hasExcursion ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">Cold Chain Integrity</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $hasExcursion ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950/80 dark:text-rose-300 dark:ring-rose-800/50' : 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:ring-emerald-800/50' }} ring-1">
                        <x-ui.icon name="beaker" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums {{ $hasExcursion ? 'text-rose-600 dark:text-rose-400' : 'text-neutral-950 dark:text-white' }}">{{ number_format($metrics['dock_excursions']) }}</span>
                    <span class="text-sm sm:text-base font-bold {{ $hasExcursion ? 'text-rose-600/80 dark:text-rose-400/80' : 'text-neutral-500 dark:text-neutral-400' }}">excursions</span>
                </div>
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium {{ $hasExcursion ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-neutral-600 dark:text-neutral-300' }} truncate">
                        @if($hasExcursion)
                            Excursion holds active on receiving dock
                        @else
                            2.0°C – 8.0°C WHO GDP thermal logger normal
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Navigation: Major Dropdown Tabs --}}
        @include('inventory.logistics.partials.nav')

        {{-- Main Two-Column Layout --}}
        {{-- Main Layout: Responsive Split (xl:grid-cols-12) to give tables full horizontal breathing room --}}
        @can(\App\Enums\Permission::ViewLogisticsSensitiveData->value)
        <div class="grid gap-6 xl:grid-cols-12 items-start">
            {{-- Left Section (8 cols on xl displays, full width on tablets/laptops): Shipments and IARs --}}
            <div class="space-y-6 xl:col-span-8 min-w-0">
                {{-- Active Inbound Shipments Table --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 sm:px-5 sm:py-3.5 dark:border-neutral-800">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-neutral-900 dark:text-neutral-100">Inbound Logistics &amp; Carrier Deliveries</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Live 3PL carriers, GS1 SSCC, and receiving dock status</p>
                        </div>
                        <a href="{{ route('inventory.logistics.shipments') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors shrink-0">
                            View all shipments
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-sm">
                            <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                <tr>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[130px]">Shipment # / PO</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 min-w-[140px]">Carrier / Tracking</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[110px]">Expected Date</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[95px]">Cold Chain</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[90px]">Status</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 text-right whitespace-nowrap min-w-[75px] w-20">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                                @forelse($recentShipments as $shipment)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                                        <td class="px-3.5 py-3.5 sm:px-4 whitespace-nowrap">
                                            <div class="font-semibold font-mono text-neutral-900 dark:text-neutral-100">{{ $shipment->shipment_number }}</div>
                                            <div class="text-xs font-mono text-neutral-500 dark:text-neutral-400">
                                                {{ $shipment->purchaseOrder ? 'PO: '.$shipment->purchaseOrder->po_number : 'Direct Transfer' }}
                                            </div>
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4">
                                            <div class="font-medium text-neutral-900 dark:text-neutral-200 truncate max-w-[160px]">{{ $shipment->carrier_name }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400 font-mono truncate max-w-[160px]" title="{{ $shipment->tracking_number ?? $shipment->waybill_number }}">
                                                {{ $shipment->tracking_number ?? 'Waybill: '.($shipment->waybill_number ?? 'N/A') }}
                                            </div>
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 whitespace-nowrap">
                                            <div class="text-neutral-900 dark:text-neutral-200 font-medium">{{ $shipment->estimated_delivery_date?->format('M d, Y') ?? 'Not specified' }}</div>
                                            @if($shipment->isDelayed())
                                                <span class="inline-flex items-center rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-900/50">
                                                    Delay: {{ $shipment->calculateDaysDelayed() }} days
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 whitespace-nowrap">
                                            @if($shipment->is_cold_chain)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 dark:border dark:border-blue-900/50">
                                                    <x-ui.icon name="beaker" class="h-3.5 w-3.5" /> Cold Chain
                                                </span>
                                                @if($shipment->temp_excursion)
                                                    <span class="mt-1 flex items-center gap-1 text-[10px] font-bold text-rose-600 dark:text-rose-400">
                                                        <x-ui.icon name="exclamation-triangle" class="h-3 w-3 shrink-0" /> EXCURSION
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-xs text-neutral-400 dark:text-neutral-500">Ambient</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 whitespace-nowrap">
                                            <x-ui.badge :status="$shipment->status" />
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 text-right whitespace-nowrap">
                                            <a href="{{ route('inventory.logistics.shipments', ['search' => $shipment->shipment_number]) }}" class="inline-flex items-center rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
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
                    <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 sm:px-5 sm:py-3.5 dark:border-neutral-800">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-neutral-900 dark:text-neutral-100">COA Inspection &amp; Acceptance Reports (Appendix 50)</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Mandatory dual-step Technical Inspection &amp; Property Custodial Acceptance</p>
                        </div>
                        <a href="{{ route('inventory.logistics.iar.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors shrink-0">
                            View full IAR ledger
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-sm">
                            <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                <tr>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[135px]">IAR #</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 min-w-[135px]">PO &amp; Supplier</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[85px]">Delivery Docs</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[85px]">Status</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 whitespace-nowrap min-w-[125px]">COA Transmittal</th>
                                    <th class="px-3.5 py-3 sm:px-4 sm:py-3 text-right whitespace-nowrap min-w-[85px] w-24">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                                @forelse($recentIars as $iar)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                                        <td class="px-3.5 py-3.5 sm:px-4 whitespace-nowrap">
                                            <div class="font-bold text-neutral-900 dark:text-neutral-100 font-mono text-xs tracking-tight whitespace-nowrap leading-relaxed">{{ $iar->iar_number }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap">{{ $iar->created_at->format('M d, Y') }}</div>
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4">
                                            <div class="font-medium font-mono text-neutral-900 dark:text-neutral-200 whitespace-nowrap text-xs">{{ $iar->purchaseOrder->po_number ?? 'Direct Receipt' }}</div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400 truncate max-w-[160px]" title="{{ $iar->supplier->name ?? 'N/A' }}">{{ $iar->supplier->name ?? 'N/A' }}</div>
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 text-xs text-neutral-600 dark:text-neutral-400 whitespace-nowrap">
                                            <div>DR: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-300">{{ $iar->goodsReceiptNote->dr_number ?? 'None' }}</span></div>
                                            <div class="mt-0.5">SI: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-300">{{ $iar->invoice_number ?? 'Pending' }}</span></div>
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 whitespace-nowrap">
                                            <x-ui.badge :status="$iar->status" />
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 text-xs whitespace-nowrap">
                                            @if($iar->coa_transmitted_at)
                                                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400">
                                                    <x-ui.icon name="check-circle" class="h-3.5 w-3.5 shrink-0" /> Transmitted ({{ $iar->coa_transmitted_at->format('M d') }})
                                                </span>
                                            @elseif($iar->isAccepted())
                                                <span class="inline-flex items-center gap-1 font-semibold text-amber-700 dark:text-amber-400">
                                                    <x-ui.icon name="clock" class="h-3.5 w-3.5 shrink-0" /> Due within 5 days
                                                </span>
                                            @else
                                                <span class="text-neutral-400 dark:text-neutral-500">Awaiting acceptance</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3.5 sm:px-4 text-right whitespace-nowrap">
                                            <a href="{{ route('inventory.logistics.iar.show', $iar) }}" class="inline-flex items-center rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition whitespace-nowrap">
                                                View Report
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                            No Inspection and Acceptance Reports recorded. Receipts can be converted to IAR in the IAR Processing tab.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Right Section: Chain of Custody & Documents Registry (stacks in 4 cols on xl, responsive 2 cols side-by-side on lg) --}}
            <div class="space-y-6 md:grid md:grid-cols-2 md:gap-6 md:space-y-0 xl:block xl:space-y-6 xl:col-span-4 min-w-0">
                {{-- Immutable Chain of Custody Stream --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-800/40">
                                <x-ui.icon name="clipboard-document-list" class="h-4 w-4" />
                            </span>
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Chain of Custody Stream</h3>
                        </div>
                        <a href="{{ route('inventory.logistics.chain-of-custody') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors shrink-0">
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
                                                @php
                                                    $isDock = str_contains(strtolower($log->event_type), 'dock');
                                                    $isAcceptance = str_contains(strtolower($log->event_type), 'acceptance') || str_contains(strtolower($log->event_type), 'passed');
                                                @endphp
                                                <span class="flex h-8 w-8 items-center justify-center rounded-full ring-4 ring-white dark:ring-neutral-900 {{ $isDock ? 'bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-400' : ($isAcceptance ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400') }}">
                                                    <x-ui.icon name="{{ $isDock ? 'truck' : ($isAcceptance ? 'check-circle' : 'clock') }}" class="h-4 w-4" />
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                                        {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                                    </p>
                                                    <span class="shrink-0 text-[10px] font-medium text-neutral-400 dark:text-neutral-500">
                                                        {{ $log->transferred_at->diffForHumans() }}
                                                    </span>
                                                </div>

                                                {{-- Handover Parties Route Box --}}
                                                <div class="mt-2 rounded-lg border border-neutral-100 bg-neutral-50/80 p-2.5 dark:border-neutral-800/80 dark:bg-neutral-800/40">
                                                    <div class="flex items-center justify-between gap-2 text-xs">
                                                        <div class="min-w-0 flex-1">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">From</span>
                                                            <p class="truncate text-xs font-medium text-neutral-800 dark:text-neutral-200" title="{{ $log->releasing_party_name ?? ($log->releasingUser->name ?? 'Issuer') }}">
                                                                {{ $log->releasing_party_name ?? ($log->releasingUser->name ?? 'Issuer') }}
                                                            </p>
                                                        </div>
                                                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-neutral-200/60 text-neutral-500 dark:bg-neutral-700/60 dark:text-neutral-400">
                                                            <x-ui.icon name="arrow-right" class="h-3 w-3" />
                                                        </div>
                                                        <div class="min-w-0 flex-1 text-right">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">To</span>
                                                            <p class="truncate text-xs font-medium text-neutral-800 dark:text-neutral-200" title="{{ $log->receiving_party_name ?? ($log->receivingUser->name ?? 'Recipient') }}">
                                                                {{ $log->receiving_party_name ?? ($log->receivingUser->name ?? 'Recipient') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Location & Condition --}}
                                                <div class="mt-1.5 flex items-center justify-between gap-2 text-[10px] text-neutral-400 dark:text-neutral-500">
                                                    <span class="inline-flex items-center gap-1 truncate" title="{{ $log->destination_location ?? $log->origin_location }}">
                                                        <x-ui.icon name="map-pin" class="h-3.5 w-3.5 shrink-0" />
                                                        <span class="truncate">{{ $log->destination_location ?? ($log->origin_location ?? 'Dock Terminal') }}</span>
                                                    </span>
                                                    @if($log->package_condition)
                                                        <span class="shrink-0 font-medium text-neutral-500 dark:text-neutral-400">
                                                            {{ ucwords(str_replace('_', ' ', $log->package_condition)) }}
                                                        </span>
                                                    @endif
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
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 ring-1 ring-indigo-100 dark:ring-indigo-800/40">
                                <x-ui.icon name="document-text" class="h-4 w-4" />
                            </span>
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Recent Documents</h3>
                        </div>
                        <a href="{{ route('inventory.logistics.documents') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline transition-colors shrink-0">
                            Full registry
                        </a>
                    </div>
                    <div class="mt-3 space-y-1.5">
                        @forelse($recentDocuments as $doc)
                            @php
                                $supplier = $doc->supplier ?? $doc->purchaseOrder?->supplier;
                            @endphp
                            <div class="group relative flex items-start gap-3 rounded-xl p-2.5 transition-all duration-150 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 border border-transparent hover:border-neutral-200 dark:hover:border-neutral-700/60">
                                <div class="shrink-0 pt-0.5">
                                    @if($supplier)
                                        <x-ui.supplier-logo :supplier="$supplier" size="md" class="rounded-lg shadow-2xs" />
                                    @else
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-100 text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                                            <x-ui.icon name="document-text" class="h-4 w-4" />
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200/80 dark:border-neutral-700/80">
                                            {{ $doc->document_type->abbreviation() }}
                                        </span>
                                        <p class="truncate text-xs font-semibold text-neutral-900 dark:text-neutral-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors" title="{{ $doc->title }}">
                                            {{ $doc->title }}
                                        </p>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1.5 text-[10px] text-neutral-500 dark:text-neutral-400">
                                        @if($supplier)
                                            <span class="truncate max-w-[130px] font-medium text-neutral-700 dark:text-neutral-300" title="{{ $supplier->name }}">
                                                {{ $supplier->name }}
                                            </span>
                                            <span>•</span>
                                        @endif
                                        <span class="font-mono">{{ $doc->tracking_number }}</span>
                                        <span>•</span>
                                        <span>v{{ $doc->version_number }}</span>
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2 text-[10px]">
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
                                <a
                                    href="{{ route('inventory.logistics.documents.download', $doc) }}"
                                    class="shrink-0 mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-400 shadow-2xs hover:border-primary-300 hover:bg-primary-50 hover:text-primary-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:border-primary-700 dark:hover:bg-primary-950/50 dark:hover:text-primary-300 transition-all active:scale-95"
                                    data-hims-download
                                    data-loading-text="Preparing document..."
                                    data-download-name="{{ $doc->original_name ?: ($doc->file_name ?: 'document') }}"
                                    title="Download Document"
                                >
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
