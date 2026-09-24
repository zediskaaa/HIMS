<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ route('inventory.receiving.index') }}" class="font-semibold text-primary-600 hover:underline">&larr; Inbound Receiving</a>
                    <span class="text-neutral-400">/</span>
                    <span class="text-neutral-500">{{ $goodsReceiptNote->grn_number }}</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Delivery Receipt Details</h2>
                <p class="mt-1 text-sm text-neutral-500">Verified receiving record linked to the purchase order and inventory intake workflow.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($goodsReceiptNote->inspectionAcceptanceReport)
                    <a href="{{ route('inventory.logistics.iar.show', $goodsReceiptNote->inspectionAcceptanceReport) }}" class="inline-flex items-center rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">View IAR</a>
                @endif
                @can(\App\Enums\Permission::InspectStock->value)
                    <a href="{{ route('inventory.qc.index') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">QC Inspection Queue</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @php
        $deliveryReceiptNumber = $goodsReceiptNote->dr_number ?: $goodsReceiptNote->packing_slip_number;
        $receiptStatus = $goodsReceiptNote->receipt_status ?: 'draft';
        $deliveryStatus = $goodsReceiptNote->delivery_status;
        $allInspections = $goodsReceiptNote->lines->flatMap->inspections;
        $allTasks = $allInspections->flatMap->warehouseTasks;
        $hasPendingInspection = $allInspections->contains(fn ($inspection) => in_array($inspection->inspection_status, ['pending_sample', 'partially_disposed', 'under_review'], true));
        $hasRejectedInspection = $allInspections->contains(fn ($inspection) => $inspection->inspection_status === 'rejected');
        $acceptedQuantity = (int) $goodsReceiptNote->lines->sum('accepted_quantity');
        $hasPendingPutAway = (int) $goodsReceiptNote->lines->sum('pending_put_away_quantity') > 0;
        $allTasksCompleted = $allTasks->isNotEmpty() && $allTasks->every(fn ($task) => $task->status === \App\Enums\WarehouseTaskStatus::Completed);
        $discrepancyLines = $goodsReceiptNote->lines->filter(fn ($line) => $line->item_condition !== 'good' || filled($line->discrepancy_type));
        $destinations = $goodsReceiptNote->lines->pluck('destinationLocation')->filter()->unique('id');
        $linkedDocuments = $goodsReceiptNote->documents
            ->merge($goodsReceiptNote->inspectionAcceptanceReport?->documents ?? collect())
            ->unique('id');
        $totalReceivedValue = $goodsReceiptNote->lines->sum(fn ($line) => (float) $line->received_quantity * (float) $line->unit_cost);
        $receiptStatusClasses = match ($receiptStatus) {
            'received', 'stored', 'posted' => 'bg-emerald-100 text-emerald-800',
            'quarantined', 'under_qc', 'under_inspection' => 'bg-amber-100 text-amber-800',
            'rejected' => 'bg-rose-100 text-rose-800',
            default => 'bg-neutral-100 text-neutral-700',
        };
    @endphp

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        @include('inventory.warehousing.partials.workflow_nav')

        <article class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <header class="border-b border-neutral-200 bg-neutral-50 px-5 py-5 sm:px-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary-700">Delivery Receipt / Goods Receiving Record</p>
                        <h3 class="mt-2 font-mono text-xl font-bold text-neutral-950 sm:text-2xl">{{ $deliveryReceiptNumber ?: 'Reference not recorded' }}</h3>
                        <p class="mt-1 text-xs text-neutral-500">HIMS Goods Receipt Note: <span class="font-mono font-semibold text-neutral-700">{{ $goodsReceiptNote->grn_number }}</span></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $receiptStatusClasses }}">{{ \Illuminate\Support\Str::headline($receiptStatus) }}</span>
                        @if($deliveryStatus)
                            <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">Delivery: {{ \Illuminate\Support\Str::headline($deliveryStatus) }}</span>
                        @endif
                    </div>
                </div>
            </header>

            <section class="grid grid-cols-1 gap-px border-b border-neutral-200 bg-neutral-200 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white px-5 py-4 sm:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Supplier</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->supplier?->name ?? 'Not recorded' }}</p>
                </div>
                <div class="bg-white px-5 py-4 sm:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Purchase Order</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->purchaseOrder?->po_number ?? 'Not recorded' }}</p>
                </div>
                <div class="bg-white px-5 py-4 sm:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Delivered / Received</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->received_at?->format('M d, Y') ?? 'Not recorded' }}</p>
                    @if($goodsReceiptNote->received_at)
                        <p class="text-xs text-neutral-500">{{ $goodsReceiptNote->received_at->format('h:i A') }}</p>
                    @endif
                </div>
                <div class="bg-white px-5 py-4 sm:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Receiving Officer</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->receivedBy?->name ?? 'Not recorded' }}</p>
                </div>
                <div class="bg-white px-5 py-4 sm:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Receiving Destination</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $destinations->isNotEmpty() ? $destinations->pluck('name')->join(', ') : 'Not recorded' }}</p>
                </div>
                @if($goodsReceiptNote->carrier_name)
                    <div class="bg-white px-5 py-4 sm:px-7">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Carrier</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->carrier_name }}</p>
                    </div>
                @endif
                @if($goodsReceiptNote->waybill_number)
                    <div class="bg-white px-5 py-4 sm:px-7">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Waybill</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->waybill_number }}</p>
                    </div>
                @endif
                @if($goodsReceiptNote->sales_invoice_number)
                    <div class="bg-white px-5 py-4 sm:px-7">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Sales Invoice</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-neutral-900">{{ $goodsReceiptNote->sales_invoice_number }}</p>
                    </div>
                @endif
            </section>

            <section>
                <div class="border-b border-neutral-200 px-5 py-4 sm:px-7">
                    <h3 class="text-sm font-bold text-neutral-900">Delivered Items</h3>
                    <p class="mt-0.5 text-xs text-neutral-500">Quantities and values captured from the linked purchase order receiving record.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[980px] w-full text-left text-sm">
                        <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold sm:px-7">Item / SKU</th>
                                <th class="px-5 py-3 font-semibold">Batch, Lot &amp; Expiry</th>
                                <th class="px-5 py-3 font-semibold">Destination</th>
                                <th class="px-5 py-3 font-semibold text-right">Quantity Received</th>
                                <th class="px-5 py-3 font-semibold text-right">Unit Cost</th>
                                <th class="px-5 py-3 font-semibold text-right sm:pr-7">Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($goodsReceiptNote->lines as $line)
                                @php
                                    $lineUnit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
                                    $lineTotal = (float) $line->received_quantity * (float) $line->unit_cost;
                                    $inspection = $line->inspections->first();
                                    $expiryStatus = $line->expiry_date ? $line->batch?->expiryStatusLabel() : null;
                                @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-4 sm:px-7">
                                        <p class="font-semibold text-neutral-900">{{ $line->item?->name ?? 'Item record unavailable' }}</p>
                                        @if($line->item?->sku)
                                            <p class="mt-0.5 font-mono text-xs text-neutral-500">{{ $line->item->sku }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <span class="rounded bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-700">{{ \Illuminate\Support\Str::headline($line->item_condition ?: 'good') }}</span>
                                            @if($inspection)
                                                <span class="rounded bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">QC: {{ \Illuminate\Support\Str::headline($inspection->inspection_status) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-xs text-neutral-700">
                                        @if($line->batch_number || $line->lot_number || $line->serial_number || $line->expiry_date)
                                            <div class="space-y-1">
                                                @if($line->batch_number)
                                                    <p>Batch: <span class="font-mono font-semibold text-neutral-900">{{ $line->batch_number }}</span></p>
                                                @endif
                                                @if($line->lot_number && $line->lot_number !== $line->batch_number)
                                                    <p>Lot: <span class="font-mono font-semibold text-neutral-900">{{ $line->lot_number }}</span></p>
                                                @endif
                                                @if($line->serial_number)
                                                    <p>Serial: <span class="font-mono font-semibold text-neutral-900">{{ $line->serial_number }}</span></p>
                                                @endif
                                                @if($line->expiry_date)
                                                    <p>Expiry: <span class="font-semibold text-neutral-900">{{ $line->expiry_date->format('M d, Y') }}</span></p>
                                                    @if($expiryStatus)
                                                        <p class="text-[11px] text-neutral-500">{{ $expiryStatus }}</p>
                                                    @endif
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-neutral-400">Not recorded</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-xs text-neutral-700">
                                        @if($line->destinationLocation)
                                            <p class="font-medium text-neutral-900">{{ $line->destinationLocation->name }}</p>
                                            @if($line->destinationLocation->code)
                                                <p class="mt-0.5 font-mono text-neutral-500">{{ $line->destinationLocation->code }}</p>
                                            @endif
                                        @else
                                            <span class="text-neutral-400">Not recorded</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <p class="font-mono font-bold text-neutral-900">{{ $line->received_quantity }} {{ \Illuminate\Support\Str::plural($lineUnit, $line->received_quantity) }}</p>
                                        @if($line->ordered_quantity)
                                            <p class="mt-0.5 text-[11px] text-neutral-500">of {{ $line->ordered_quantity }} ordered</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right font-mono text-xs text-neutral-700">₱{{ number_format((float) $line->unit_cost, 2) }}/{{ $lineUnit }}</td>
                                    <td class="px-5 py-4 text-right font-mono text-xs font-bold text-neutral-900 sm:pr-7">₱{{ number_format($lineTotal, 2) }}</td>
                                </tr>
                                @if($line->discrepancy_type || $line->discrepancy_notes || $line->notes)
                                    <tr class="bg-amber-50/60">
                                        <td colspan="6" class="px-5 py-3 text-xs text-neutral-700 sm:px-7">
                                            @if($line->discrepancy_type)
                                                <span class="font-semibold text-amber-900">Discrepancy: {{ \Illuminate\Support\Str::headline($line->discrepancy_type) }}</span>
                                                @if($line->discrepancy_action)
                                                    <span class="text-neutral-500"> · Action: {{ \Illuminate\Support\Str::headline($line->discrepancy_action) }}</span>
                                                @endif
                                            @endif
                                            @if($line->discrepancy_notes)
                                                <p class="mt-1">{{ $line->discrepancy_notes }}</p>
                                            @endif
                                            @if($line->notes)
                                                <p class="mt-1 text-neutral-600">Line note: {{ $line->notes }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-neutral-500">No delivered items are recorded for this receipt.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t border-neutral-300 bg-neutral-50">
                            <tr>
                                <td colspan="4" class="px-5 py-3 text-right text-xs font-semibold text-neutral-600 sm:px-7">Total Received Goods Value:</td>
                                <td colspan="2" class="px-5 py-3 text-right font-mono text-sm font-bold text-primary-700 sm:pr-7">₱{{ number_format($totalReceivedValue, 2) }}</td>
                            </tr>
                            @if($goodsReceiptNote->purchaseOrder)
                                <tr class="border-t border-neutral-200">
                                    <td colspan="4" class="px-5 py-3 text-right text-xs text-neutral-500 sm:px-7">Original Purchase Order Total:</td>
                                    <td colspan="2" class="px-5 py-3 text-right font-mono text-xs text-neutral-700 sm:pr-7">₱{{ number_format((float) $goodsReceiptNote->purchaseOrder->total_amount, 2) }}</td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="grid grid-cols-1 border-t border-neutral-200 lg:grid-cols-2 lg:divide-x lg:divide-neutral-200">
                <div class="px-5 py-5 sm:px-7">
                    <h3 class="text-sm font-bold text-neutral-900">Inspection &amp; Acceptance</h3>
                    @if($goodsReceiptNote->inspectionAcceptanceReport)
                        @php
                            $iar = $goodsReceiptNote->inspectionAcceptanceReport;
                        @endphp
                        <dl class="mt-3 grid grid-cols-2 gap-4 text-xs">
                            <div><dt class="text-neutral-500">IAR Reference</dt><dd class="mt-1 font-mono font-semibold text-neutral-900">{{ $iar->iar_number }}</dd></div>
                            <div><dt class="text-neutral-500">IAR Status</dt><dd class="mt-1 font-semibold text-neutral-900">{{ \Illuminate\Support\Str::headline($iar->status) }}</dd></div>
                            @if($iar->inspectedBy)
                                <div><dt class="text-neutral-500">Inspector</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $iar->inspectedBy->name }}</dd></div>
                            @endif
                            @if($iar->acceptedBy)
                                <div><dt class="text-neutral-500">Accepting Officer</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $iar->acceptedBy->name }}</dd></div>
                            @endif
                        </dl>
                    @else
                        <p class="mt-2 text-xs text-neutral-500">No Inspection and Acceptance Report is linked to this receipt.</p>
                    @endif
                </div>
                <div class="border-t border-neutral-200 px-5 py-5 sm:px-7 lg:border-t-0">
                    <h3 class="text-sm font-bold text-neutral-900">Receiving Notes</h3>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-700">{{ $goodsReceiptNote->notes ?: 'No receiving notes were recorded.' }}</p>
                </div>
            </section>
        </article>

        @if($linkedDocuments->isNotEmpty())
            <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-sm font-bold text-neutral-900">Linked Documents</h3>
                <p class="mt-0.5 text-xs text-neutral-500">Files already registered against this receiving record or its IAR.</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($linkedDocuments as $document)
                        <div class="flex items-center justify-between gap-4 rounded-lg border border-neutral-200 p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $document->title }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ $document->document_type?->label() ?? \Illuminate\Support\Str::headline((string) $document->document_type) }}
                                    @if($document->reference_number)
                                        · {{ $document->reference_number }}
                                    @endif
                                </p>
                            </div>
                            @if($document->hasFile())
                                <a href="{{ route('inventory.logistics.documents.download', $document) }}" class="shrink-0 text-xs font-semibold text-primary-700 hover:underline" data-hims-download data-loading-text="Preparing document..." data-download-name="{{ $document->original_name ?: ($document->file_name ?: 'document') }}">Download</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-bold text-neutral-900">Receiving Workflow</h3>
                    <p class="mt-0.5 text-xs text-neutral-500">Operational progress for this recorded delivery.</p>
                </div>
                @if($allTasks->isNotEmpty())
                    <a href="{{ route('inventory.warehousing.scan-station') }}" class="text-xs font-semibold text-primary-700 hover:underline">Open Scan Workstation &rarr;</a>
                @endif
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs font-bold text-emerald-900">1. Delivery recorded</p>
                    <p class="mt-1 text-[11px] text-emerald-700">{{ $goodsReceiptNote->received_at?->format('M d, Y h:i A') ?? 'Timestamp unavailable' }}</p>
                </div>
                <div class="rounded-lg border p-3 {{ $hasPendingInspection ? 'border-amber-300 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }}">
                    <p class="text-xs font-bold {{ $hasPendingInspection ? 'text-amber-900' : 'text-emerald-900' }}">2. Quality inspection</p>
                    <p class="mt-1 text-[11px] {{ $hasPendingInspection ? 'text-amber-700' : 'text-emerald-700' }}">{{ $hasPendingInspection ? 'Pending or in progress' : ($allInspections->isNotEmpty() ? 'Inspection recorded' : 'No inspection record') }}</p>
                </div>
                <div class="rounded-lg border p-3 {{ $hasRejectedInspection ? 'border-rose-200 bg-rose-50' : ($acceptedQuantity > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-neutral-200 bg-neutral-50') }}">
                    <p class="text-xs font-bold text-neutral-900">3. Disposition</p>
                    <p class="mt-1 text-[11px] text-neutral-600">{{ $hasRejectedInspection ? 'Rejected quantity recorded' : ($acceptedQuantity > 0 ? 'Accepted quantity recorded' : 'Awaiting disposition') }}</p>
                </div>
                <div class="rounded-lg border p-3 {{ $hasPendingPutAway ? 'border-blue-300 bg-blue-50' : ($allTasksCompleted ? 'border-emerald-200 bg-emerald-50' : 'border-neutral-200 bg-neutral-50') }}">
                    <p class="text-xs font-bold text-neutral-900">4. Put-away</p>
                    <p class="mt-1 text-[11px] text-neutral-600">{{ $hasPendingPutAway ? 'Transfer pending' : ($allTasksCompleted ? 'Stored in destination' : 'Not yet required') }}</p>
                </div>
            </div>
        </section>

        @if($discrepancyLines->isNotEmpty())
            <section class="rounded-xl border border-amber-300 bg-amber-50 p-5 shadow-sm sm:p-6">
                <h3 class="text-sm font-bold text-amber-950">Recorded Discrepancies</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach($discrepancyLines as $line)
                        <div class="rounded-lg border border-amber-200 bg-white p-3 text-xs">
                            <p class="font-bold text-neutral-900">{{ $line->item?->name ?? 'Item' }}</p>
                            <p class="mt-1 text-neutral-600">{{ \Illuminate\Support\Str::headline($line->discrepancy_type ?: $line->item_condition) }}</p>
                            @if($line->discrepancy_notes)
                                <p class="mt-1 text-neutral-500">{{ $line->discrepancy_notes }}</p>
                            @endif
                            @can(\App\Enums\Permission::RecordMovements->value)
                                @php
                                    $conversion = $line->conversionFactor();
                                    $returnableQuantity = max(0, (int) round($line->rejected_quantity * $conversion) - $line->returned_quantity);
                                    $baseUnit = $line->item?->unit ?: 'unit';
                                @endphp
                                @if($returnableQuantity > 0)
                                    <form method="POST" action="{{ route('inventory.receiving.return', [$goodsReceiptNote, $line]) }}" data-confirm-title="Return rejected stock" data-confirm-message="Confirm physical return of the rejected base units to the supplier." data-confirm-label="Record return" class="mt-3 flex flex-wrap items-end gap-2 border-t border-amber-100 pt-3">
                                        @csrf
                                        <input type="hidden" name="return_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <label class="block">
                                            <span class="block text-[11px] font-medium text-neutral-600">Return quantity ({{ $baseUnit }})</span>
                                            <input type="number" name="quantity" min="1" max="{{ $returnableQuantity }}" value="{{ $returnableQuantity }}" required class="mt-1 w-24 rounded border-neutral-300 text-xs">
                                        </label>
                                        <button type="submit" class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Record return</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
