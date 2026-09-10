<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Commission on Audit (COA) GAM Volume II App. 50</p>
                <h2 class="text-2xl font-bold text-neutral-900">Inspection & Acceptance Reports (IAR)</h2>
                <p class="text-sm text-neutral-600">Dual-stage statutory accountability: Technical Inspection and Property Custodial Acceptance with automated liquidated damages penalty computation.</p>
            </div>
            <div>
                <a href="{{ route('inventory.receiving.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Receiving Dock
                </a>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6" x-data="{ genModalOpen: false, selectedGrnId: null, selectedGrnNumber: '', defaultDr: '', defaultSi: '' }">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Flash Notifications --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- Unreported Receipts Queue (GRNs without IAR) --}}
            @if($unreportedReceipts->isNotEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-amber-900">📥 Goods Receipts Awaiting Statutory IAR Generation ({{ $unreportedReceipts->count() }})</h3>
                            <p class="text-xs text-amber-700">Goods have arrived at receiving dock. Formal COA GAM Appendix 50 report must be generated for Technical Inspection.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($unreportedReceipts as $receipt)
                            <div class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm text-neutral-900">{{ $receipt->grn_number }}</span>
                                        <span class="text-xs text-neutral-500">{{ $receipt->received_at?->format('M d, Y') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-neutral-600">
                                        PO: <span class="font-semibold text-primary-700">{{ $receipt->purchaseOrder?->po_number ?? 'Direct' }}</span>
                                    </div>
                                    <div class="text-xs text-neutral-600">
                                        Supplier: <span class="font-medium text-neutral-800">{{ $receipt->supplier?->name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="mt-2 text-[11px] text-neutral-500">
                                        DR: {{ $receipt->dr_number ?? 'Pending' }} • SI: {{ $receipt->sales_invoice_number ?? 'Pending' }}
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-t border-neutral-100 flex justify-end">
                                    <button @click="selectedGrnId = {{ $receipt->id }}; selectedGrnNumber = '{{ $receipt->grn_number }}'; defaultDr = '{{ $receipt->dr_number }}'; defaultSi = '{{ $receipt->sales_invoice_number }}'; genModalOpen = true"
                                            class="inline-flex items-center gap-1 rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                        Generate COA IAR
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Filter & Search Bar --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('inventory.logistics.iar.index') }}" class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-6">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search IAR #, PO #, DR #, or Sales Invoice #..."
                                   class="block w-full rounded-lg border-neutral-300 pl-10 text-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <select name="status" class="block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Statuses</option>
                            <option value="pending_inspection" {{ request('status') === 'pending_inspection' ? 'selected' : '' }}>Pending Technical Inspection</option>
                            <option value="inspected_passed" {{ request('status') === 'inspected_passed' ? 'selected' : '' }}>Inspected (Awaiting Property Acceptance)</option>
                            <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                            <option value="inspected_failed" {{ request('status') === 'inspected_failed' ? 'selected' : '' }}>Inspection Failed</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <button type="submit" class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800">
                            Filter
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('inventory.logistics.iar.index') }}" class="rounded-lg border border-neutral-300 p-2 text-neutral-600 hover:bg-neutral-50" title="Reset">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- IAR Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <tr>
                                <th class="px-6 py-3.5">IAR Reference</th>
                                <th class="px-6 py-3.5">PO & Entity</th>
                                <th class="px-6 py-3.5">Commercial Proof (BIR)</th>
                                <th class="px-6 py-3.5">Technical Inspection</th>
                                <th class="px-6 py-3.5">Custodial Acceptance</th>
                                <th class="px-6 py-3.5">COA Transmittal</th>
                                <th class="px-6 py-3.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white">
                            @forelse($iars as $iar)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-neutral-900">{{ $iar->iar_number }}</div>
                                        <div class="text-xs text-neutral-500">{{ $iar->created_at->format('M d, Y') }}</div>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold
                                            @if($iar->status === 'accepted') bg-emerald-100 text-emerald-800
                                            @elseif($iar->status === 'inspected_passed') bg-blue-100 text-blue-800
                                            @elseif($iar->status === 'inspected_failed' || $iar->status === 'rejected') bg-red-100 text-red-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ ucwords(str_replace('_', ' ', $iar->status)) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        <div class="font-semibold text-neutral-900">{{ $iar->purchaseOrder->po_number ?? 'Direct Receipt' }}</div>
                                        <div class="text-neutral-500">{{ $iar->supplier->name ?? 'N/A' }}</div>
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        <div>DR: <span class="font-mono font-semibold text-neutral-800">{{ $iar->goodsReceiptNote->dr_number ?? 'None' }}</span></div>
                                        <div class="mt-0.5">SI: <span class="font-mono font-semibold text-neutral-800">{{ $iar->invoice_number ?? 'Pending' }}</span></div>
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        @if($iar->inspection_date)
                                            <div class="font-semibold text-emerald-700">✓ Completed</div>
                                            <div class="text-neutral-500">{{ $iar->inspectedBy->name ?? 'Inspector' }}</div>
                                            <div class="text-[10px] text-neutral-400">{{ $iar->inspection_date->format('M d, Y') }}</div>
                                        @else
                                            <span class="inline-flex rounded bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">
                                                Awaiting Inspection
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        @if($iar->acceptance_date)
                                            <div class="font-semibold text-emerald-700">✓ Accepted</div>
                                            <div class="text-neutral-500">{{ $iar->acceptedBy->name ?? 'Custodian' }}</div>
                                            @if($iar->liquidated_damages_amount > 0)
                                                <div class="mt-0.5 font-bold text-red-600">
                                                    Penalty: ₱{{ number_format($iar->liquidated_damages_amount, 2) }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-neutral-400">Pending</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        @if($iar->coa_transmitted_at)
                                            <div class="font-bold text-purple-800">✓ Transmitted</div>
                                            <div class="text-neutral-500">{{ $iar->coa_transmitted_at->format('M d, Y') }}</div>
                                            <div class="font-mono text-[10px] text-neutral-400">Rec: {{ $iar->coa_received_by }}</div>
                                        @elseif($iar->isAccepted())
                                            @if($iar->isCoaDeadlineUrgent())
                                                <span class="inline-flex rounded bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800">
                                                    ⚠️ OVERDUE (&gt;5 Days)
                                                </span>
                                            @else
                                                <span class="inline-flex rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                                    ⏳ Due within 5 days
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-neutral-300">N/A</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('inventory.logistics.iar.show', $iar) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
                                            View Report &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-neutral-500">
                                        No Inspection and Acceptance Reports found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($iars->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $iars->links() }}
                    </div>
                @endif
            </div>

            {{-- Generate IAR Modal --}}
            <div x-show="genModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="genModalOpen" @click="genModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="genModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Generate COA GAM Appendix 50 IAR</h3>
                            <button @click="genModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/receipts/' + selectedGrnId + '/iar'" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">Generating formal statutory report from Goods Receipt <span class="font-bold text-neutral-900" x-text="selectedGrnNumber"></span>.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Supplier Delivery Receipt (DR) #</label>
                                <input type="text" name="dr_number" :value="defaultDr" placeholder="e.g. DR-889922"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">BIR Sales Invoice (SI) # (RA 11976 EOPT)</label>
                                <input type="text" name="invoice_number" :value="defaultSi" placeholder="e.g. SI-2026-0988"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="genModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Generate IAR</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
