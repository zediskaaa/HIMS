<x-app-layout full-width>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-800/60">
                    <x-ui.icon name="shield-check" class="h-3 w-3 text-primary-600 dark:text-primary-400" />
                    Forensic Physical &amp; Document Accountability
                </span>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-neutral-100">Chain of Custody Ledger</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/60 shadow-2xs">
                    <x-ui.icon name="shield-check" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>Append-Only Immutability Guarded</span>
                </span>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="space-y-6">
        {{-- Flash Notifications --}}
        @if(session('success'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-3.5 text-sm text-emerald-800 shadow-2xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                <x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div class="font-medium">{{ session('success') }}</div>
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
            <form method="GET" action="{{ route('inventory.logistics.chain-of-custody') }}" class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                {{-- Search Input (Largest flexible width) --}}
                <div class="relative flex-1 min-w-0">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 dark:text-neutral-500">
                        <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                    </div>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           placeholder="Search custodian, party name, location, or remarks..."
                           class="block w-full rounded-lg border-neutral-300 pl-9 pr-3 py-1.5 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                </div>

                {{-- Custody Event Type Filter --}}
                <div class="w-full sm:w-64">
                    <select name="action" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        <option value="">All Custody Event Types</option>
                        <option value="dock_arrival" {{ request('action') === 'dock_arrival' ? 'selected' : '' }}>Dock Arrival</option>
                        <option value="dock_receiving" {{ request('action') === 'dock_receiving' ? 'selected' : '' }}>Dock Receiving</option>
                        <option value="inspection_handover" {{ request('action') === 'inspection_handover' ? 'selected' : '' }}>Inspection Handover</option>
                        <option value="inspection_completed" {{ request('action') === 'inspection_completed' ? 'selected' : '' }}>Inspection Completed</option>
                        <option value="acceptance_custody" {{ request('action') === 'acceptance_custody' ? 'selected' : '' }}>Custodial Acceptance</option>
                        <option value="coa_transmittal" {{ request('action') === 'coa_transmittal' ? 'selected' : '' }}>COA Transmittal</option>
                    </select>
                </div>

                {{-- Filter Action Buttons --}}
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 dark:bg-primary-600 dark:hover:bg-primary-500 transition">
                        <x-ui.icon name="funnel" class="h-3.5 w-3.5 text-neutral-300 dark:text-white" />
                        <span>Filter</span>
                    </button>
                    @if(request()->hasAny(['search', 'action']))
                        <a href="{{ route('inventory.logistics.chain-of-custody') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white p-1.5 text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100 transition" title="Reset Filters">
                            <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Chain of Custody Timeline Table --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200/90 bg-white shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
            <div class="overflow-x-auto min-w-full">
                <table class="w-full min-w-[1100px] table-fixed divide-y divide-neutral-200 text-left text-xs dark:divide-neutral-800">
                    <colgroup>
                        <col class="w-[12%]">
                        <col class="w-[13%]">
                        <col class="w-[14%]">
                        <col class="w-[27%]">
                        <col class="w-[21%]">
                        <col class="w-[13%]">
                    </colgroup>
                    <thead class="bg-neutral-50/90 text-[11px] font-semibold uppercase tracking-wider text-neutral-600 dark:bg-neutral-800/80 dark:text-neutral-400">
                        <tr>
                            <th class="px-5 py-3">Timestamp</th>
                            <th class="px-5 py-3">Custody Event</th>
                            <th class="px-5 py-3">Trackable Reference</th>
                            <th class="px-5 py-3">Transfer Parties (Released &rarr; Received)</th>
                            <th class="px-5 py-3">Location &amp; Condition</th>
                            <th class="px-5 py-3">Forensic Fingerprint</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                        @forelse($logs as $log)
                            <tr class="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50 transition-colors">
                                <td class="px-5 py-3.5 align-top whitespace-nowrap">
                                    <div class="font-bold text-neutral-900 dark:text-neutral-100">{{ $log->transferred_at->format('M d, Y') }}</div>
                                    <div class="font-mono text-[11px] text-neutral-500 dark:text-neutral-400">{{ $log->transferred_at->format('H:i:s T') }}</div>
                                    <div class="mt-0.5 text-[10px] text-neutral-400 dark:text-neutral-500">{{ $log->transferred_at->diffForHumans() }}</div>
                                </td>

                                <td class="px-5 py-3.5 align-top">
                                    @php
                                        $eventBadge = match(true) {
                                            str_contains($log->event_type, 'acceptance') => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/60',
                                            str_contains($log->event_type, 'dock') => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-950/60 dark:text-teal-300 dark:ring-teal-800/60',
                                            str_contains($log->event_type, 'inspection') => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950/60 dark:text-blue-300 dark:ring-blue-800/60',
                                            str_contains($log->event_type, 'coa') => 'bg-purple-50 text-purple-700 ring-purple-600/20 dark:bg-purple-950/60 dark:text-purple-300 dark:ring-purple-800/60',
                                            default => 'bg-neutral-100 text-neutral-700 ring-neutral-600/20 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $eventBadge }}">
                                        {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                    </span>
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    <div class="font-bold text-neutral-900 dark:text-neutral-100">
                                        {{ class_basename($log->trackable_type) }} #{{ $log->trackable_id }}
                                    </div>
                                    <div class="mt-0.5 font-mono text-[11px] text-neutral-500 dark:text-neutral-400">
                                        @if($log->trackable instanceof \App\Models\Shipment)
                                            {{ $log->trackable->shipment_number }}
                                        @elseif($log->trackable instanceof \App\Models\InspectionAcceptanceReport)
                                            {{ $log->trackable->iar_number }}
                                        @elseif($log->trackable instanceof \App\Models\LogisticsDocument)
                                            {{ $log->trackable->tracking_number }}
                                        @elseif($log->trackable instanceof \App\Models\GoodsReceiptNote)
                                            {{ $log->trackable->grn_number }}
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-800 ring-1 ring-inset ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700">
                                            {{ $log->releasing_party_name ?? ($log->releasingUser->name ?? 'Issuer') }}
                                        </span>
                                        <span class="text-neutral-400 dark:text-neutral-500 font-bold">&mdash;</span>
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-800 ring-1 ring-inset ring-primary-200 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-800/60">
                                            {{ $log->receiving_party_name ?? ($log->receivingUser->name ?? 'Recipient') }}
                                        </span>
                                    </div>
                                    @if($log->notes)
                                        <div class="mt-1 text-[11px] text-neutral-600 dark:text-neutral-400 italic leading-relaxed">{{ $log->notes }}</div>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    <div class="flex items-center gap-1.5 font-semibold text-neutral-900 dark:text-neutral-100">
                                        <x-ui.icon name="map-pin" class="h-3.5 w-3.5 shrink-0 text-primary-500 dark:text-primary-400" />
                                        <span class="truncate" title="{{ $log->destination_location ?: ($log->origin_location ?: 'Not recorded') }}">
                                            {{ $log->destination_location ?: ($log->origin_location ?: 'Not recorded') }}
                                        </span>
                                    </div>
                                    @if($log->origin_location && $log->destination_location && $log->origin_location !== $log->destination_location)
                                        <div class="mt-0.5 text-[10px] text-neutral-500 dark:text-neutral-400 truncate" title="From: {{ $log->origin_location }}">
                                            From: {{ $log->origin_location }}
                                        </div>
                                    @endif
                                    <div class="mt-1">
                                        @if($log->package_condition === 'good_order')
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/60">
                                                Condition: Good Order
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-800/60">
                                                Condition: {{ ucwords(str_replace('_', ' ', $log->package_condition)) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 align-top text-xs">
                                    @if($log->custody_number)
                                        <div class="font-mono text-[11px] font-semibold text-neutral-800 dark:text-neutral-200">
                                            {{ $log->custody_number }}
                                        </div>
                                    @endif
                                    <div class="text-[11px] font-mono text-neutral-500 dark:text-neutral-400 truncate" title="{{ $log->user_agent ?? 'System Console' }}">
                                        {{ $log->user_agent ?: 'System Console' }}
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-[10px] text-neutral-400 dark:text-neutral-500 font-mono">
                                        @if($log->verification_method)
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[9px] font-sans text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                                {{ ucwords(str_replace('_', ' ', $log->verification_method)) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-xs">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500">
                                        <x-ui.icon name="clipboard-document-list" class="h-6 w-6" />
                                    </div>
                                    <p class="mt-3 font-semibold text-neutral-700 dark:text-neutral-300">No custody records found</p>
                                    <p class="mt-1 text-neutral-500 dark:text-neutral-400">No chain of custody logs match the active filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
