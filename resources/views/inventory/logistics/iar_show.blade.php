<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between print:hidden">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.logistics.iar.index') }}" class="text-xs font-semibold text-primary-700 hover:underline">&larr; Back to IAR Ledger</a>
                    <span class="text-neutral-300">•</span>
                    <span class="inline-flex items-center rounded-md bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-800">
                        COA GAM Appendix 50
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-bold text-neutral-900">{{ $iar->iar_number }}</h2>
                <p class="text-sm text-neutral-600">Statutory Inspection and Acceptance Report for {{ $iar->purchaseOrder->po_number ?? 'Inbound Goods' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print GAM App. 50
                </button>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6" x-data="{ inspectModalOpen: false, acceptModalOpen: false, coaModalOpen: false }">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm print:hidden">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm print:hidden">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- Action Banner for Inspectors & Custodians --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-neutral-900">Current Lifecycle: {{ ucwords(str_replace('_', ' ', $iar->status)) }}</div>
                        <div class="text-xs text-neutral-500">
                            Segregation of Duties: Inspection and Custodial Acceptance must be executed by distinct authorized personnel.
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Technical Inspection Button --}}
                    @can(\App\Enums\Permission::PerformTechnicalInspection->value)
                        @if($iar->status === 'pending_inspection')
                            <button @click="inspectModalOpen = true" class="rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-blue-700 shadow-sm">
                                Perform Technical Inspection
                            </button>
                        @endif
                    @endcan

                    {{-- Custodial Acceptance Button --}}
                    @can(\App\Enums\Permission::ApproveIarAcceptance->value)
                        @if($iar->status === 'inspected_passed')
                            <button @click="acceptModalOpen = true" class="rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700 shadow-sm">
                                Approve Custodial Acceptance
                            </button>
                        @endif

                        @if($iar->isAccepted() && !$iar->coa_transmitted_at)
                            <button @click="coaModalOpen = true" class="rounded-lg bg-purple-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-purple-700 shadow-sm">
                                Transmit to Resident COA Auditor
                            </button>
                        @endif
                    @endcan
                </div>
            </div>

            {{-- FORMAL COA GAM VOLUME II APPENDIX 50 DOCUMENT CANVAS --}}
            <div class="border border-neutral-300 bg-white p-8 shadow-sm print:border-none print:p-0 print:shadow-none font-serif text-neutral-900">
                <div class="text-center">
                    <p class="text-[11px] font-sans font-semibold tracking-wider text-neutral-500 uppercase">Appendix 50</p>
                    <h1 class="text-xl font-bold tracking-tight uppercase">Inspection and Acceptance Report</h1>
                    <p class="text-xs italic font-sans text-neutral-600 mt-0.5">Republic of the Philippines</p>
                </div>

                <div class="mt-6 border-t border-b border-neutral-800 py-2 flex justify-between text-xs font-sans">
                    <div>
                        <span class="font-bold">Entity Name:</span>
                        <span class="underline font-semibold ml-1">{{ $iar->purchaseOrder?->entity_name ?? 'HOSPITAL INFORMATION MANAGEMENT SYSTEM' }}</span>
                    </div>
                    <div>
                        <span class="font-bold">Fund Cluster:</span>
                        <span class="underline font-semibold ml-1">{{ $iar->purchaseOrder?->fund_cluster ?? '01 - Regular Agency Fund' }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 border-b border-neutral-800 text-xs font-sans">
                    <div class="border-r border-neutral-800 p-2 space-y-1">
                        <div><span class="font-bold">Supplier:</span> {{ $iar->supplier->name ?? 'N/A' }}</div>
                        <div><span class="font-bold">PO No./Date:</span> {{ $iar->purchaseOrder->po_number ?? 'Direct' }} / {{ $iar->purchaseOrder?->created_at?->format('m/d/Y') ?? 'N/A' }}</div>
                        <div><span class="font-bold">Requisitioning Office/Dept:</span> Hospital Central Pharmacy & Supply</div>
                        <div><span class="font-bold">Responsibility Center Code:</span> HIMS-101-02</div>
                    </div>
                    <div class="p-2 space-y-1">
                        <div><span class="font-bold">IAR No.:</span> <span class="font-mono font-bold">{{ $iar->iar_number }}</span></div>
                        <div><span class="font-bold">Date:</span> {{ $iar->created_at->format('F d, Y') }}</div>
                        <div><span class="font-bold">Invoice No. (BIR RA 11976):</span> <span class="font-mono">{{ $iar->invoice_number ?? 'Pending' }}</span></div>
                        <div><span class="font-bold">Delivery Receipt (DR) No.:</span> <span class="font-mono">{{ $iar->goodsReceiptNote->dr_number ?? 'N/A' }}</span></div>
                    </div>
                </div>

                {{-- Items Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full border-b border-neutral-800 text-left text-xs font-sans">
                        <thead class="bg-neutral-100 font-bold uppercase border-b border-neutral-800 text-[10px]">
                            <tr>
                                <th class="border-r border-neutral-800 px-3 py-2 text-center w-12">Item #</th>
                                <th class="border-r border-neutral-800 px-3 py-2">Stock / Description</th>
                                <th class="border-r border-neutral-800 px-3 py-2 text-center w-16">Unit</th>
                                <th class="border-r border-neutral-800 px-3 py-2 text-right w-20">Qty Delivered</th>
                                <th class="border-r border-neutral-800 px-3 py-2 text-right w-24">Unit Cost (₱)</th>
                                <th class="px-3 py-2 text-right w-28">Amount (₱)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-300">
                            @php $totalAmount = 0; @endphp
                            @if($iar->goodsReceiptNote && $iar->goodsReceiptNote->lines)
                                @foreach($iar->goodsReceiptNote->lines as $idx => $line)
                                    @php
                                        $unitCost = $line->unit_cost ?? $line->item?->unit_cost ?? 0;
                                        $lineTotal = $line->received_quantity * $unitCost;
                                        $totalAmount += $lineTotal;
                                    @endphp
                                    <tr>
                                        <td class="border-r border-neutral-800 px-3 py-2 text-center font-mono">{{ $idx + 1 }}</td>
                                        <td class="border-r border-neutral-800 px-3 py-2">
                                            <div class="font-bold text-neutral-900">{{ $line->item->name ?? 'Medical Supply' }}</div>
                                            <div class="text-[10px] text-neutral-600 font-mono">SKU: {{ $line->item->sku ?? 'N/A' }} | Batch: {{ $line->batch_number ?? 'N/A' }} | Exp: {{ $line->expiry_date?->format('Y-m-d') ?? 'N/A' }}</div>
                                        </td>
                                        <td class="border-r border-neutral-800 px-3 py-2 text-center uppercase">{{ $line->item->unit ?? 'pcs' }}</td>
                                        <td class="border-r border-neutral-800 px-3 py-2 text-right font-mono font-bold">{{ number_format($line->received_quantity) }}</td>
                                        <td class="border-r border-neutral-800 px-3 py-2 text-right font-mono">{{ number_format($unitCost, 2) }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($lineTotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-800 font-bold">
                            <tr>
                                <td colspan="5" class="border-r border-neutral-800 px-3 py-2 text-right uppercase">Total Value of Delivery</td>
                                <td class="px-3 py-2 text-right font-mono text-sm">₱{{ number_format($totalAmount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- DUAL SECTION STATUTORY SIGNATURE EXECUTION --}}
                <div class="grid grid-cols-2 border-b border-neutral-800 font-sans text-xs">
                    {{-- Technical Inspection Section --}}
                    <div class="border-r border-neutral-800 p-4 flex flex-col justify-between">
                        <div>
                            <div class="font-bold uppercase tracking-wider text-center border-b border-neutral-400 pb-1 mb-2">
                                INSPECTION
                            </div>
                            <div class="text-[11px] space-y-1">
                                <div><span class="font-semibold">Date Inspected:</span> {{ $iar->inspection_date?->format('F d, Y') ?? '___________________' }}</div>
                                <div class="mt-2 flex items-start gap-2">
                                    <span class="font-bold text-base">[{{ $iar->inspection_date ? '✓' : ' ' }}]</span>
                                    <span>Inspected, verified and found in order as to quantity and technical specifications.</span>
                                </div>
                                @if($iar->inspection_findings)
                                    <div class="mt-2 text-[10px] bg-neutral-50 p-2 rounded border border-neutral-200">
                                        <span class="font-bold">Findings:</span> {{ $iar->inspection_findings }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-8 text-center">
                            <div class="border-b border-neutral-800 mx-auto w-48 mb-1">
                                <span class="font-bold">{{ $iar->inspectedBy->name ?? '____________________________' }}</span>
                            </div>
                            <div class="text-[10px] font-semibold uppercase text-neutral-600">Inspection Officer / Committee</div>
                        </div>
                    </div>

                    {{-- Custodial Acceptance Section --}}
                    <div class="p-4 flex flex-col justify-between">
                        <div>
                            <div class="font-bold uppercase tracking-wider text-center border-b border-neutral-400 pb-1 mb-2">
                                ACCEPTANCE
                            </div>
                            <div class="text-[11px] space-y-1">
                                <div><span class="font-semibold">Date Received:</span> {{ $iar->acceptance_date?->format('F d, Y') ?? '___________________' }}</div>
                                <div class="mt-2 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-base">[{{ $iar->delivery_status === 'complete' || $iar->status === 'accepted' ? '✓' : ' ' }}]</span>
                                        <span>Complete</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-base">[{{ $iar->delivery_status === 'partial' ? '✓' : ' ' }}]</span>
                                        <span>Partial (pls. specify quantity)</span>
                                    </div>
                                </div>

                                {{-- Liquidated Damages Assessment --}}
                                @if($iar->days_delayed > 0 || $iar->liquidated_damages_amount > 0)
                                    <div class="mt-3 rounded border border-red-300 bg-red-50 p-2 text-[10px] text-red-900">
                                        <div class="font-bold uppercase">COA GAM App. 61 Liquidated Damages Assessed:</div>
                                        <div>Delay: <span class="font-bold">{{ $iar->days_delayed }} days</span></div>
                                        <div>Rate: 1/10 of 1% (0.001) per day of delay</div>
                                        <div class="font-bold text-xs mt-0.5">Assessed Penalty: ₱{{ number_format($iar->liquidated_damages_amount, 2) }}</div>
                                    </div>
                                @endif

                                @if($iar->notes)
                                    <div class="mt-2 text-[10px] bg-neutral-50 p-2 rounded border border-neutral-200">
                                        <span class="font-bold">Remarks:</span> {{ $iar->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-8 text-center">
                            <div class="border-b border-neutral-800 mx-auto w-48 mb-1">
                                <span class="font-bold">{{ $iar->acceptedBy->name ?? '____________________________' }}</span>
                            </div>
                            <div class="text-[10px] font-semibold uppercase text-neutral-600">Property and/or Supply Custodian</div>
                        </div>
                    </div>
                </div>

                {{-- COA Transmittal Compliance Footer --}}
                <div class="p-3 bg-neutral-50 text-xs font-sans flex items-center justify-between">
                    <div>
                        <span class="font-bold">COA 5-Day Statutory Transmittal:</span>
                        @if($iar->coa_transmitted_at)
                            <span class="text-emerald-800 font-semibold ml-1">✓ Transmitted on {{ $iar->coa_transmitted_at->format('F d, Y') }} (Received by: {{ $iar->coa_received_by }})</span>
                        @elseif($iar->isAccepted())
                            <span class="text-amber-800 font-bold ml-1">⏳ Pending Transmittal (Deadline: {{ $iar->coa_transmittal_deadline_at?->format('F d, Y') ?? 'Within 5 days' }})</span>
                        @else
                            <span class="text-neutral-500 ml-1">Pending custodial acceptance</span>
                        @endif
                    </div>
                    <div class="text-[10px] text-neutral-400 font-mono">
                        System Verification ID: {{ $iar->iar_number }}-SHA256
                    </div>
                </div>
            </div>

            {{-- Linked Supporting Documents & Chain of Custody (Screen Only) --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm print:hidden space-y-4">
                <h3 class="text-sm font-bold text-neutral-900">Archived Supporting Records & Chain of Custody</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 p-3">
                        <div class="text-xs font-bold text-neutral-700">Delivery Receipts & Commercial Invoices</div>
                        <ul class="mt-2 space-y-1 text-xs">
                            @forelse($iar->documents as $doc)
                                <li class="flex items-center justify-between py-1">
                                    <span class="truncate font-medium text-neutral-900">{{ $doc->title }} ({{ $doc->tracking_number }})</span>
                                    <a href="{{ route('inventory.logistics.documents.download', $doc) }}" class="text-primary-600 hover:underline">Download</a>
                                </li>
                            @empty
                                <li class="text-neutral-400 text-xs italic">No direct file attachments linked to this IAR.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 p-3">
                        <div class="text-xs font-bold text-neutral-700">Immutable Custody Handoffs</div>
                        <ul class="mt-2 space-y-1 text-xs">
                            @forelse($iar->custodyLogs as $log)
                                <li class="py-1">
                                    <div class="font-semibold text-neutral-800">{{ ucwords(str_replace('_', ' ', $log->event_type)) }}</div>
                                    <div class="text-[10px] text-neutral-500">{{ $log->releasing_party_name }} &rarr; {{ $log->receiving_party_name }} on {{ $log->transferred_at->format('M d, Y h:i A') }}</div>
                                </li>
                            @empty
                                <li class="text-neutral-400 text-xs italic">No custody transfers recorded for this IAR yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Perform Technical Inspection Modal --}}
            <div x-show="inspectModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="inspectModalOpen" @click="inspectModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="inspectModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Execute Technical Inspection</h3>
                            <button @click="inspectModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form action="{{ route('inventory.logistics.iar.technical-inspection', $iar) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">Signing as Technical Inspector. Buyer cannot sign inspection per Segregation of Duties.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Inspection Status *</label>
                                <select name="status" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="inspected">Inspected & Verified (Meets Technical Specs)</option>
                                    <option value="rejected">Rejected (Defective / Non-conforming)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Inspection Date *</label>
                                <input type="date" name="inspection_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Inspection Remarks / Observations *</label>
                                <textarea name="remarks" required rows="3" placeholder="Lot numbers verified against Certificate of Analysis. Packaging intact and free from defects..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="inspectModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Submit Inspection</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Approve Custodial Acceptance Modal --}}
            <div x-show="acceptModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="acceptModalOpen" @click="acceptModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="acceptModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Approve Custodial Acceptance</h3>
                            <button @click="acceptModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form action="{{ route('inventory.logistics.iar.custodial-acceptance', $iar) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">Signing as Property / Supply Custodian. Items will be formally posted to the hospital stock ledger.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Acceptance Type *</label>
                                <select name="acceptance_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="complete">Complete Acceptance</option>
                                    <option value="partial">Partial Acceptance</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Acceptance Date *</label>
                                <input type="date" name="acceptance_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Custodial Acceptance Remarks</label>
                                <textarea name="remarks" rows="3" placeholder="Stock posted to storage locations. Stock cards updated..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="acceptModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve Acceptance</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Transmit to Resident COA Auditor Modal --}}
            <div x-show="coaModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="coaModalOpen" @click="coaModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="coaModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Transmit to Resident COA Auditor</h3>
                            <button @click="coaModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form action="{{ route('inventory.logistics.iar.transmit-coa', $iar) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">Per COA Circular, a copy of the IAR must be transmitted to the resident auditor within 5 days of delivery acceptance.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">COA Auditor / Received By Staff Name *</label>
                                <input type="text" name="transmittal_reference" required placeholder="e.g. Maria Teresa Gomez, CPA (COA State Auditor IV)"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Transmittal Date *</label>
                                <input type="date" name="transmittal_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="coaModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">Record Transmittal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
