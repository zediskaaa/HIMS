<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between print:hidden">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.requisitions.index') }}" class="text-xs font-semibold text-primary-700 hover:underline">&larr; Back to Requisitions</a>
                    <span class="text-neutral-300">•</span>
                    <span class="inline-flex items-center rounded-md bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-800">
                        COA GAM Appendix 63
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-bold text-neutral-900">Requisition and Issue Slip (RIS)</h2>
                <p class="text-sm text-neutral-600">Statutory internal store requisition and issuance slip for {{ $requisition->requisition_number }}</p>
            </div>
            <div>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print GAM App. 63
                </button>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- FORMAL COA GAM VOLUME II APPENDIX 63 CANVAS --}}
            <div class="border border-neutral-300 bg-white p-8 shadow-sm print:border-none print:p-0 print:shadow-none font-serif text-neutral-900">
                <div class="text-center">
                    <p class="text-[11px] font-sans font-semibold tracking-wider text-neutral-500 uppercase">Appendix 63</p>
                    <h1 class="text-xl font-bold tracking-tight uppercase">Requisition and Issue Slip</h1>
                    <p class="text-xs italic font-sans text-neutral-600 mt-0.5">Republic of the Philippines</p>
                </div>

                <div class="mt-6 border-t border-b border-neutral-800 py-2 flex justify-between text-xs font-sans">
                    <div>
                        <span class="font-bold">Entity Name:</span>
                        <span class="underline font-semibold ml-1">HOSPITAL INFORMATION MANAGEMENT SYSTEM</span>
                    </div>
                    <div>
                        <span class="font-bold">Fund Cluster:</span>
                        <span class="underline font-semibold ml-1">01 - Regular Agency Fund</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 border-b border-neutral-800 text-xs font-sans">
                    <div class="border-r border-neutral-800 p-2 space-y-1">
                        <div><span class="font-bold">Division:</span> {{ $requisition->costCenter->department ?? 'Clinical Services' }}</div>
                        <div><span class="font-bold">Office:</span> {{ $requisition->costCenter->name ?? 'Ward Pharmacy' }}</div>
                        <div><span class="font-bold">Responsibility Center Code:</span> {{ $requisition->costCenter->code ?? 'RCC-2001' }}</div>
                    </div>
                    <div class="p-2 space-y-1">
                        <div><span class="font-bold">RIS No.:</span> <span class="font-mono font-bold">{{ $requisition->requisition_number }}</span></div>
                        <div><span class="font-bold">Date:</span> {{ $requisition->created_at->format('F d, Y') }}</div>
                        <div><span class="font-bold">Status:</span> {{ ucwords($requisition->status) }}</div>
                    </div>
                </div>

                {{-- Requisition Items Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full border-b border-neutral-800 text-left text-xs font-sans">
                        <thead class="bg-neutral-100 font-bold uppercase border-b border-neutral-800 text-[10px]">
                            <tr>
                                <th colspan="4" class="border-r border-neutral-800 py-1 text-center bg-neutral-200">Requisition</th>
                                <th colspan="2" class="border-r border-neutral-800 py-1 text-center bg-neutral-100">Stock Availability</th>
                                <th colspan="2" class="py-1 text-center bg-neutral-200">Issue</th>
                            </tr>
                            <tr class="border-t border-neutral-800">
                                <th class="border-r border-neutral-800 px-3 py-2 text-center w-12">Stock #</th>
                                <th class="border-r border-neutral-800 px-3 py-2 text-center w-14">Unit</th>
                                <th class="border-r border-neutral-800 px-3 py-2">Item Description</th>
                                <th class="border-r border-neutral-800 px-3 py-2 text-right w-16">Qty</th>
                                <th class="border-r border-neutral-800 px-2 py-2 text-center w-12">Yes</th>
                                <th class="border-r border-neutral-800 px-2 py-2 text-center w-12">No</th>
                                <th class="border-r border-neutral-800 px-3 py-2 text-right w-16">Qty</th>
                                <th class="px-3 py-2">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-300">
                            @forelse($requisition->lines as $idx => $line)
                                <tr>
                                    <td class="border-r border-neutral-800 px-3 py-2 text-center font-mono">{{ $idx + 1 }}</td>
                                    <td class="border-r border-neutral-800 px-3 py-2 text-center uppercase">{{ $line->item->unit_of_measure ?? 'pcs' }}</td>
                                    <td class="border-r border-neutral-800 px-3 py-2">
                                        <div class="font-bold text-neutral-900">{{ $line->item->name ?? 'Supply Item' }}</div>
                                        <div class="text-[10px] text-neutral-500 font-mono">SKU: {{ $line->item->sku ?? 'N/A' }}</div>
                                    </td>
                                    <td class="border-r border-neutral-800 px-3 py-2 text-right font-mono font-bold">{{ $line->quantity_requested }}</td>
                                    <td class="border-r border-neutral-800 px-2 py-2 text-center font-bold">[X]</td>
                                    <td class="border-r border-neutral-800 px-2 py-2 text-center font-bold">[ ]</td>
                                    <td class="border-r border-neutral-800 px-3 py-2 text-right font-mono font-bold">{{ $line->quantity_issued ?? $line->quantity_requested }}</td>
                                    <td class="px-3 py-2 text-[10px] text-neutral-600">{{ $line->remarks ?? 'Issued in good condition' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-neutral-400">No line items in this requisition.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Purpose Section --}}
                <div class="border-b border-neutral-800 p-3 text-xs font-sans">
                    <span class="font-bold">Purpose:</span>
                    <span class="ml-1 text-neutral-800">{{ $requisition->remarks ?? 'Ward patient care and medical operational stock replenishment.' }}</span>
                </div>

                {{-- QUADRUPLE STATUTORY SIGNATURE EXECUTION (COA GAM App. 63) --}}
                <div class="grid grid-cols-4 border-b border-neutral-800 font-sans text-[11px]">
                    {{-- 1. Requested By --}}
                    <div class="border-r border-neutral-800 p-3 flex flex-col justify-between">
                        <div>
                            <div class="font-bold uppercase text-[10px] text-neutral-500 mb-4">Requested By:</div>
                            <div class="border-b border-neutral-800 pb-1 text-center font-bold">
                                {{ $requisition->requestingUser->name ?? '____________________' }}
                            </div>
                            <div class="text-[9px] text-center text-neutral-600 mt-1 uppercase">Printed Name & Signature</div>
                        </div>
                        <div class="mt-4 text-[10px]">
                            <div>Designation: Nurse / Ward Officer</div>
                            <div>Date: {{ $requisition->created_at->format('m/d/Y') }}</div>
                        </div>
                    </div>

                    {{-- 2. Approved By --}}
                    <div class="border-r border-neutral-800 p-3 flex flex-col justify-between">
                        <div>
                            <div class="font-bold uppercase text-[10px] text-neutral-500 mb-4">Approved By:</div>
                            <div class="border-b border-neutral-800 pb-1 text-center font-bold">
                                {{ $requisition->approvedBy->name ?? '____________________' }}
                            </div>
                            <div class="text-[9px] text-center text-neutral-600 mt-1 uppercase">Printed Name & Signature</div>
                        </div>
                        <div class="mt-4 text-[10px]">
                            <div>Designation: Dept Head / Supervisor</div>
                            <div>Date: {{ $requisition->approved_at?->format('m/d/Y') ?? '___________' }}</div>
                        </div>
                    </div>

                    {{-- 3. Issued By --}}
                    <div class="border-r border-neutral-800 p-3 flex flex-col justify-between">
                        <div>
                            <div class="font-bold uppercase text-[10px] text-neutral-500 mb-4">Issued By:</div>
                            <div class="border-b border-neutral-800 pb-1 text-center font-bold">
                                {{ auth()->user()->name ?? '____________________' }}
                            </div>
                            <div class="text-[9px] text-center text-neutral-600 mt-1 uppercase">Printed Name & Signature</div>
                        </div>
                        <div class="mt-4 text-[10px]">
                            <div>Designation: Store Custodian</div>
                            <div>Date: {{ date('m/d/Y') }}</div>
                        </div>
                    </div>

                    {{-- 4. Received By --}}
                    <div class="p-3 flex flex-col justify-between">
                        <div>
                            <div class="font-bold uppercase text-[10px] text-neutral-500 mb-4">Received By:</div>
                            <div class="border-b border-neutral-800 pb-1 text-center font-bold">
                                {{ $requisition->requestingUser->name ?? '____________________' }}
                            </div>
                            <div class="text-[9px] text-center text-neutral-600 mt-1 uppercase">Printed Name & Signature</div>
                        </div>
                        <div class="mt-4 text-[10px]">
                            <div>Designation: Requisitioner</div>
                            <div>Date: {{ date('m/d/Y') }}</div>
                        </div>
                    </div>
                </div>

                <div class="p-2 text-right text-[10px] text-neutral-400 font-mono">
                    HIMS System Reference: RIS-{{ $requisition->id }}-GAM63
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
