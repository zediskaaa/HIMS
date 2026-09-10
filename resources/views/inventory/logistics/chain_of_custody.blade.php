<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Forensic Physical & Document Accountability</p>
                <h2 class="text-2xl font-bold text-neutral-900">Chain of Custody Ledger</h2>
                <p class="text-sm text-neutral-600">Append-only, immutable transaction log of all cargo handoffs, carrier dispatches, inspection sign-offs, and custodial transfers.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Append-Only Immutability Guarded
                </span>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filter Bar --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('inventory.logistics.chain-of-custody') }}" class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-6">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search custodian, party name, location, or remarks..."
                                   class="block w-full rounded-lg border-neutral-300 pl-10 text-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <select name="action" class="block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Custody Event Types</option>
                            <option value="dock_arrival" {{ request('action') === 'dock_arrival' ? 'selected' : '' }}>Dock Arrival</option>
                            <option value="dock_receiving" {{ request('action') === 'dock_receiving' ? 'selected' : '' }}>Dock Receiving</option>
                            <option value="inspection_handover" {{ request('action') === 'inspection_handover' ? 'selected' : '' }}>Inspection Handover</option>
                            <option value="inspection_completed" {{ request('action') === 'inspection_completed' ? 'selected' : '' }}>Inspection Completed</option>
                            <option value="acceptance_custody" {{ request('action') === 'acceptance_custody' ? 'selected' : '' }}>Custodial Acceptance</option>
                            <option value="coa_transmittal" {{ request('action') === 'coa_transmittal' ? 'selected' : '' }}>COA Transmittal</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <button type="submit" class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800">
                            Filter
                        </button>
                        @if(request()->hasAny(['search', 'action']))
                            <a href="{{ route('inventory.logistics.chain-of-custody') }}" class="rounded-lg border border-neutral-300 p-2 text-neutral-600 hover:bg-neutral-50" title="Reset">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- CoC Timeline Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <tr>
                                <th class="px-6 py-3.5">Timestamp</th>
                                <th class="px-6 py-3.5">Custody Event</th>
                                <th class="px-6 py-3.5">Trackable Reference</th>
                                <th class="px-6 py-3.5">Transfer Parties (Released &rarr; Received)</th>
                                <th class="px-6 py-3.5">Location & Condition</th>
                                <th class="px-6 py-3.5">Forensic Fingerprint</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white">
                            @forelse($logs as $log)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-xs">
                                        <div class="font-bold text-neutral-900">{{ $log->transferred_at->format('M d, Y') }}</div>
                                        <div class="font-mono text-neutral-500">{{ $log->transferred_at->format('H:i:s T') }}</div>
                                        <div class="text-[10px] text-neutral-400">{{ $log->transferred_at->diffForHumans() }}</div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                            @if(str_contains($log->event_type, 'acceptance')) bg-emerald-100 text-emerald-800
                                            @elseif(str_contains($log->event_type, 'dock') || str_contains($log->event_type, 'inspection')) bg-blue-100 text-blue-800
                                            @elseif(str_contains($log->event_type, 'coa')) bg-purple-100 text-purple-800
                                            @else bg-neutral-100 text-neutral-800 @endif">
                                            {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        <div class="font-semibold text-neutral-900">
                                            {{ class_basename($log->trackable_type) }} #{{ $log->trackable_id }}
                                        </div>
                                        <div class="font-mono text-[10px] text-neutral-500">
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

                                    <td class="px-6 py-4 text-xs">
                                        <div class="flex items-center gap-1.5 font-medium text-neutral-900">
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[11px]">{{ $log->releasing_party_name ?? ($log->releasingUser->name ?? 'Issuer') }}</span>
                                            <span class="text-neutral-400">&rarr;</span>
                                            <span class="rounded bg-primary-50 px-1.5 py-0.5 text-[11px] font-bold text-primary-800">{{ $log->receiving_party_name ?? ($log->receivingUser->name ?? 'Recipient') }}</span>
                                        </div>
                                        @if($log->notes)
                                            <div class="mt-1 text-[11px] text-neutral-600 italic">{{ $log->notes }}</div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        <div class="flex items-center gap-1.5 font-semibold text-neutral-800">
                                            <x-ui.icon name="map-pin" class="h-4 w-4 shrink-0 text-neutral-400" />
                                            <span>{{ $log->destination_location ?? $log->origin_location }}</span>
                                        </div>
                                        <div class="mt-0.5">
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium {{ $log->package_condition === 'good_order' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                                Condition: {{ ucwords(str_replace('_', ' ', $log->package_condition)) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-xs font-mono text-neutral-500">
                                        <div>IP: {{ $log->ip_address ?? '127.0.0.1' }}</div>
                                        <div class="text-[10px] text-neutral-400 truncate max-w-xs">{{ $log->user_agent ?? 'System Console' }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500">
                                        No chain of custody logs found matching your filter criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($logs->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
