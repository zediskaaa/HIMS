<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.receiving.index') }}" class="text-xs font-semibold text-primary-600 hover:underline">
                        &larr; Inbound Receiving
                    </a>
                    <span class="text-xs text-neutral-400">/</span>
                    <span class="text-xs text-neutral-500">{{ $goodsReceiptNote->grn_number }}</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">
                    Goods Receipt Note: {{ $goodsReceiptNote->grn_number }}
                </h2>
                <p class="text-sm text-neutral-600">
                    Carrier intake documentation and initial quarantine staging for PO #{{ $goodsReceiptNote->purchaseOrder->po_number ?? 'N/A' }}.
                </p>
            </div>
            <div class="flex items-center gap-3">
                @can(\App\Enums\Permission::InspectStock->value)
                    <a href="{{ route('inventory.qc.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 text-sm font-medium text-amber-800 shadow-sm hover:bg-amber-100">
                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        QC Inspection Queue
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Status & Summary Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Status</p>
                        <div class="mt-2">
                            @if($goodsReceiptNote->status === 'quarantined')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Quarantined (Pending QA Assay)
                                </span>
                            @elseif($goodsReceiptNote->status === 'received')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Released / Received
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800">
                                    {{ ucfirst($goodsReceiptNote->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Purchase Order</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $goodsReceiptNote->purchaseOrder->po_number ?? 'Direct Receipt' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            Supplier: {{ $goodsReceiptNote->supplier->name ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Carrier Logistics</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $goodsReceiptNote->carrier_name ?? 'Internal Logistics' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            Waybill: {{ $goodsReceiptNote->waybill_number ?? 'N/A' }} | Slip: {{ $goodsReceiptNote->packing_slip_number ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Intake Ledger</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $goodsReceiptNote->receivedBy->name ?? 'System' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            {{ $goodsReceiptNote->received_at ? $goodsReceiptNote->received_at->format('M d, Y h:i A') : 'N/A' }}
                        </p>
                    </div>
                </div>

                @if($goodsReceiptNote->notes)
                    <div class="mt-6 border-t border-neutral-100 pt-4">
                        <p class="text-xs font-medium text-neutral-500">Intake Observations / Inspection Notes:</p>
                        <p class="mt-1 text-sm text-neutral-700">{{ $goodsReceiptNote->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Line Items Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Received Line Items &amp; Quality Assay Records</h3>
                    <p class="text-xs text-neutral-500">Line verification against ordered quantities, registered lot numbers, and current inspection checkpoints.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Item &amp; SKU</th>
                                <th class="px-6 py-3 font-medium">Batch / Lot / Serial</th>
                                <th class="px-6 py-3 font-medium">Expiration</th>
                                <th class="px-6 py-3 font-medium text-right">Received Qty</th>
                                <th class="px-6 py-3 font-medium text-right">Unit Cost</th>
                                <th class="px-6 py-3 font-medium">QC Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($goodsReceiptNote->lines as $line)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                        <p class="text-xs text-neutral-500">{{ $line->item->sku ?? 'No SKU' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-xs space-y-0.5">
                                            @if($line->batch_number)
                                                <p><span class="font-medium text-neutral-700">Batch:</span> {{ $line->batch_number }}</p>
                                            @endif
                                            @if($line->lot_number)
                                                <p><span class="font-medium text-neutral-700">Lot:</span> {{ $line->lot_number }}</p>
                                            @endif
                                            @if($line->serial_number)
                                                <p><span class="font-medium text-neutral-700">Serial:</span> {{ $line->serial_number }}</p>
                                            @endif
                                            @if(!$line->batch_number && !$line->lot_number && !$line->serial_number)
                                                <span class="text-neutral-400 italic">No batch registered</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        @if($line->expiry_date)
                                            <span class="{{ $line->expiry_date->isPast() ? 'text-rose-600 font-semibold' : 'text-neutral-700' }}">
                                                {{ $line->expiry_date->format('M d, Y') }}
                                            </span>
                                        @else
                                            <span class="text-neutral-400">Non-expiring</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-neutral-900">
                                        {{ number_format($line->received_quantity) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-neutral-700">
                                        ₱{{ number_format($line->unit_cost, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $inspection = $line->inspections->first();
                                        @endphp
                                        @if(!$inspection || $inspection->inspection_status === 'pending_sample')
                                            <span class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 border border-amber-200">
                                                Pending QC Assay
                                            </span>
                                        @elseif($inspection->inspection_status === 'passed')
                                            <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 border border-emerald-200">
                                                Passed (Released)
                                            </span>
                                        @elseif($inspection->inspection_status === 'rejected')
                                            <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-800 border border-rose-200">
                                                Rejected (Blocked)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-800 border border-neutral-200">
                                                {{ ucfirst($inspection->inspection_status) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500">
                                        No line items associated with this Goods Receipt Note.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
