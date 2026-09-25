<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ route('inventory.receiving.index') }}" class="inline-flex items-center gap-1.5 font-semibold text-primary-600 hover:text-primary-700">
                        <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                        Inbound Receiving
                    </a>
                    <span class="text-neutral-400">/</span>
                    <span class="truncate font-mono text-neutral-500" title="{{ $goodsReceiptNote->grn_number }}">{{ $goodsReceiptNote->grn_number }}</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-950 dark:text-white">Goods Receiving Record</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Delivery Receipt Details — verified receiving, inspection, and warehouse disposition.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($goodsReceiptNote->inspectionAcceptanceReport)
                    <a href="{{ route('inventory.logistics.iar.show', $goodsReceiptNote->inspectionAcceptanceReport) }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800">
                        <x-ui.icon name="document-check" class="h-4 w-4" />
                        View IAR
                    </a>
                @endif
                @can(\App\Enums\Permission::InspectStock->value)
                    <a href="{{ route('inventory.qc.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                        <x-ui.icon name="clipboard-document-check" class="h-4 w-4" />
                        QC Inspection Queue
                    </a>
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
    @endphp

    <div class="space-y-5">
        @if($errors->any())
            <x-ui.alert variant="danger" title="Unable to complete receiving action" :message="$errors->first()" />
        @endif

        @include('inventory.warehousing.partials.workflow_nav')

        <article class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <header class="relative overflow-hidden border-b border-neutral-200 bg-gradient-to-r from-primary-50/90 via-white to-white px-5 py-6 dark:border-neutral-800 dark:from-primary-950/40 dark:via-neutral-900 dark:to-neutral-900 sm:px-7">
                <div class="absolute inset-y-0 right-0 hidden w-1/3 bg-gradient-to-l from-primary-100/40 to-transparent lg:block" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800">
                            <x-ui.icon name="clipboard-document-check" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary-700 dark:text-primary-300">Goods Receiving Record</p>
                            <h3 class="mt-1 truncate font-mono text-xl font-bold text-neutral-950 dark:text-white sm:text-2xl" title="{{ $goodsReceiptNote->grn_number }}">{{ $goodsReceiptNote->grn_number }}</h3>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Delivery reference:
                                <span class="font-mono font-semibold text-neutral-800 dark:text-neutral-200">{{ $deliveryReceiptNumber ?: 'Not provided' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <x-ui.badge :status="$receiptStatus" dot />
                        @if($deliveryStatus)
                            <x-ui.badge :status="$deliveryStatus">Delivery: {{ \Illuminate\Support\Str::headline($deliveryStatus) }}</x-ui.badge>
                        @endif
                    </div>
                </div>
            </header>

            <section class="border-b border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-950/30 sm:p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white px-4 py-3.5 dark:border-neutral-800 dark:bg-neutral-900">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Supplier</p>
                    <p class="mt-1.5 break-words text-sm font-semibold leading-5 text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->supplier?->name ?? 'Not recorded' }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white px-4 py-3.5 dark:border-neutral-800 dark:bg-neutral-900">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Purchase Order</p>
                    <p class="mt-1.5 truncate font-mono text-sm font-semibold text-neutral-900 dark:text-neutral-100" title="{{ $goodsReceiptNote->purchaseOrder?->po_number ?? 'Not recorded' }}">{{ $goodsReceiptNote->purchaseOrder?->po_number ?? 'Not recorded' }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white px-4 py-3.5 dark:border-neutral-800 dark:bg-neutral-900">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Received At</p>
                    <p class="mt-1.5 text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->received_at?->format('M d, Y') ?? 'Not recorded' }}</p>
                    @if($goodsReceiptNote->received_at)
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $goodsReceiptNote->received_at->format('h:i A') }}</p>
                    @endif
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white px-4 py-3.5 dark:border-neutral-800 dark:bg-neutral-900">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Receiving Officer</p>
                    <p class="mt-1.5 break-words text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->receivedBy?->name ?? 'Not recorded' }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white px-4 py-3.5 sm:col-span-2 lg:col-span-1 dark:border-neutral-800 dark:bg-neutral-900">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Receiving Destination</p>
                    <p class="mt-1.5 break-words text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $destinations->isNotEmpty() ? $destinations->pluck('name')->join(', ') : 'Not assigned' }}</p>
                </div>
                </div>
                @if($goodsReceiptNote->carrier_name || $goodsReceiptNote->waybill_number || $goodsReceiptNote->sales_invoice_number)
                    <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-3 rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                @if($goodsReceiptNote->carrier_name)
                    <div class="min-w-0">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Carrier</dt>
                        <dd class="mt-1 font-semibold text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->carrier_name }}</dd>
                    </div>
                @endif
                @if($goodsReceiptNote->waybill_number)
                    <div class="min-w-0">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Waybill</dt>
                        <dd class="mt-1 font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->waybill_number }}</dd>
                    </div>
                @endif
                @if($goodsReceiptNote->sales_invoice_number)
                    <div class="min-w-0">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Sales Invoice</dt>
                        <dd class="mt-1 font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->sales_invoice_number }}</dd>
                    </div>
                @endif
                    </dl>
                @endif
            </section>

            <section>
                <div class="flex flex-col gap-3 border-b border-neutral-200 px-5 py-5 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div>
                        <h3 class="text-base font-bold text-neutral-950 dark:text-white">Delivered Items</h3>
                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Product identity, traceability, destination, quantity, and valuation.</p>
                    </div>
                    <div class="flex items-center gap-5 text-sm">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Line items</p>
                            <p class="mt-0.5 font-mono font-bold text-neutral-900 dark:text-neutral-100">{{ $goodsReceiptNote->lines->count() }}</p>
                        </div>
                        <div class="border-l border-neutral-200 pl-5 text-right dark:border-neutral-700">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Received value</p>
                            <p class="mt-0.5 font-mono font-bold text-primary-700 dark:text-primary-300">₱{{ number_format($totalReceivedValue, 2) }}</p>
                        </div>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <table class="w-full table-fixed text-left text-sm">
                        <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500 dark:border-neutral-800 dark:bg-neutral-950/40 dark:text-neutral-400">
                            <tr>
                                <th class="w-[27%] px-5 py-3 font-semibold xl:px-7">Item / SKU</th>
                                <th class="w-[20%] px-4 py-3 font-semibold">Traceability</th>
                                <th class="w-[17%] px-4 py-3 font-semibold">Destination</th>
                                <th class="w-[14%] px-4 py-3 font-semibold text-right">Received</th>
                                <th class="w-[10%] px-4 py-3 font-semibold text-right">Unit Cost</th>
                                <th class="w-[12%] px-5 py-3 text-right font-semibold xl:pr-7">Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                            @forelse($goodsReceiptNote->lines as $line)
                                @php
                                    $lineUnit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
                                    $lineTotal = (float) $line->received_quantity * (float) $line->unit_cost;
                                    $inspection = $line->inspections->first();
                                    $expiryStatus = $line->expiry_date ? $line->batch?->expiryStatusLabel() : null;
                                @endphp
                                <tr class="align-top transition-colors hover:bg-neutral-50/70 dark:hover:bg-neutral-800/30">
                                    <td class="px-5 py-4 xl:px-7">
                                        <p class="break-words font-semibold leading-5 text-neutral-950 dark:text-neutral-100">{{ $line->item?->name ?? 'Item record unavailable' }}</p>
                                        @if($line->item?->sku)
                                            <p class="mt-1 truncate font-mono text-xs text-neutral-500 dark:text-neutral-400" title="{{ $line->item->sku }}">{{ $line->item->sku }}</p>
                                        @endif
                                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                                            <x-ui.badge :status="$line->item_condition ?: 'good'" />
                                            @if($inspection)
                                                <x-ui.badge :status="$inspection->inspection_status">QC: {{ \Illuminate\Support\Str::headline($inspection->inspection_status) }}</x-ui.badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-xs leading-5 text-neutral-700 dark:text-neutral-300">
                                        @if($line->batch_number || $line->lot_number || $line->serial_number || $line->expiry_date)
                                            <div class="space-y-1">
                                                @if($line->batch_number)
                                                    <p>Batch: <span class="font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->batch_number }}</span></p>
                                                @endif
                                                @if($line->lot_number && $line->lot_number !== $line->batch_number)
                                                    <p>Lot: <span class="font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->lot_number }}</span></p>
                                                @endif
                                                @if($line->serial_number)
                                                    <p>Serial: <span class="font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->serial_number }}</span></p>
                                                @endif
                                                @if($line->expiry_date)
                                                    <p>Expiry: <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->expiry_date->format('M d, Y') }}</span></p>
                                                    @if($expiryStatus)
                                                        <p class="text-[11px] text-neutral-500">{{ $expiryStatus }}</p>
                                                    @endif
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-neutral-400">Not recorded</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-xs leading-5 text-neutral-700 dark:text-neutral-300">
                                        @if($line->destinationLocation)
                                            <p class="break-words font-medium text-neutral-900 dark:text-neutral-100">{{ $line->destinationLocation->name }}</p>
                                            @if($line->destinationLocation->code)
                                                <p class="mt-0.5 font-mono text-neutral-500">{{ $line->destinationLocation->code }}</p>
                                            @endif
                                        @else
                                            <span class="text-neutral-400">Not recorded</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <p class="font-mono font-bold text-neutral-950 dark:text-neutral-100">{{ $line->received_quantity }} {{ \Illuminate\Support\Str::plural($lineUnit, $line->received_quantity) }}</p>
                                        @if($line->ordered_quantity)
                                            <p class="mt-0.5 text-[11px] text-neutral-500">of {{ $line->ordered_quantity }} ordered</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs text-neutral-700 dark:text-neutral-300">₱{{ number_format((float) $line->unit_cost, 2) }}/{{ $lineUnit }}</td>
                                    <td class="px-5 py-4 text-right font-mono text-sm font-bold text-neutral-950 dark:text-neutral-100 xl:pr-7">₱{{ number_format($lineTotal, 2) }}</td>
                                </tr>
                                @if($line->discrepancy_type || $line->discrepancy_notes || $line->notes)
                                    <tr class="{{ $line->discrepancy_type ? 'bg-amber-50/70 dark:bg-amber-950/20' : 'bg-neutral-50/80 dark:bg-neutral-950/30' }}">
                                        <td colspan="6" class="px-5 py-3 text-xs leading-5 text-neutral-700 dark:text-neutral-300 xl:px-7">
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
                    </table>
                </div>

                <div class="divide-y divide-neutral-200 dark:divide-neutral-800 lg:hidden">
                    @forelse($goodsReceiptNote->lines as $line)
                        @php
                            $lineUnit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
                            $lineTotal = (float) $line->received_quantity * (float) $line->unit_cost;
                            $inspection = $line->inspections->first();
                        @endphp
                        <article class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h4 class="break-words text-sm font-bold text-neutral-950 dark:text-white">{{ $line->item?->name ?? 'Item record unavailable' }}</h4>
                                    <p class="mt-1 truncate font-mono text-xs text-neutral-500 dark:text-neutral-400" title="{{ $line->item?->sku }}">{{ $line->item?->sku ?? 'SKU not recorded' }}</p>
                                </div>
                                <p class="shrink-0 font-mono text-sm font-bold text-neutral-950 dark:text-white">₱{{ number_format($lineTotal, 2) }}</p>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <x-ui.badge :status="$line->item_condition ?: 'good'" />
                                @if($inspection)
                                    <x-ui.badge :status="$inspection->inspection_status">QC: {{ \Illuminate\Support\Str::headline($inspection->inspection_status) }}</x-ui.badge>
                                @endif
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                                <div>
                                    <dt class="font-medium text-neutral-500 dark:text-neutral-400">Received</dt>
                                    <dd class="mt-1 font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->received_quantity }} {{ \Illuminate\Support\Str::plural($lineUnit, $line->received_quantity) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-neutral-500 dark:text-neutral-400">Unit cost</dt>
                                    <dd class="mt-1 font-mono font-semibold text-neutral-900 dark:text-neutral-100">₱{{ number_format((float) $line->unit_cost, 2) }}/{{ $lineUnit }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-neutral-500 dark:text-neutral-400">Batch / Lot</dt>
                                    <dd class="mt-1 break-words font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->batch_number ?: ($line->lot_number ?: 'Not recorded') }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-neutral-500 dark:text-neutral-400">Expiry</dt>
                                    <dd class="mt-1 font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->expiry_date?->format('M d, Y') ?? 'Not recorded' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="font-medium text-neutral-500 dark:text-neutral-400">Destination</dt>
                                    <dd class="mt-1 font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->destinationLocation?->name ?? 'Not assigned' }}</dd>
                                </div>
                            </dl>
                            @if($line->discrepancy_type || $line->discrepancy_notes || $line->notes)
                                <div class="mt-4 rounded-lg px-3 py-2.5 text-xs leading-5 {{ $line->discrepancy_type ? 'bg-amber-50 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200' : 'bg-neutral-50 text-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-300' }}">
                                    @if($line->discrepancy_type)
                                        <p class="font-semibold">Discrepancy: {{ \Illuminate\Support\Str::headline($line->discrepancy_type) }}</p>
                                    @endif
                                    @if($line->discrepancy_notes)<p>{{ $line->discrepancy_notes }}</p>@endif
                                    @if($line->notes)<p>Line note: {{ $line->notes }}</p>@endif
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-neutral-500 dark:text-neutral-400">No delivered items are recorded for this receipt.</p>
                    @endforelse
                </div>

                <div class="flex justify-end border-t border-neutral-200 bg-neutral-50/70 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/30 sm:px-7">
                    <dl class="w-full space-y-2 sm:max-w-md">
                        <div class="flex items-center justify-between gap-6 text-sm">
                            <dt class="font-semibold text-neutral-600 dark:text-neutral-300">Total received goods value</dt>
                            <dd class="font-mono text-base font-bold text-primary-700 dark:text-primary-300">₱{{ number_format($totalReceivedValue, 2) }}</dd>
                        </div>
                        @if($goodsReceiptNote->purchaseOrder)
                            <div class="flex items-center justify-between gap-6 border-t border-neutral-200 pt-2 text-xs dark:border-neutral-800">
                                <dt class="text-neutral-500 dark:text-neutral-400">Original purchase order total</dt>
                                <dd class="font-mono font-semibold text-neutral-700 dark:text-neutral-300">₱{{ number_format((float) $goodsReceiptNote->purchaseOrder->total_amount, 2) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 border-t border-neutral-200 bg-neutral-50/70 p-5 dark:border-neutral-800 dark:bg-neutral-950/30 lg:grid-cols-2 sm:p-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-800">
                            <x-ui.icon name="document-check" class="h-4 w-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-neutral-950 dark:text-white">Inspection &amp; Acceptance</h3>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Formal IAR disposition and sign-off.</p>
                        </div>
                    </div>
                    @if($goodsReceiptNote->inspectionAcceptanceReport)
                        @php
                            $iar = $goodsReceiptNote->inspectionAcceptanceReport;
                        @endphp
                        <dl class="mt-5 grid grid-cols-2 gap-4 text-xs">
                            <div><dt class="text-neutral-500 dark:text-neutral-400">IAR Reference</dt><dd class="mt-1 font-mono font-semibold text-neutral-900 dark:text-neutral-100">{{ $iar->iar_number }}</dd></div>
                            <div><dt class="text-neutral-500 dark:text-neutral-400">IAR Status</dt><dd class="mt-1"><x-ui.badge :status="$iar->status" /></dd></div>
                            @if($iar->inspectedBy)
                                <div><dt class="text-neutral-500 dark:text-neutral-400">Inspector</dt><dd class="mt-1 font-semibold text-neutral-900 dark:text-neutral-100">{{ $iar->inspectedBy->name }}</dd></div>
                            @endif
                            @if($iar->acceptedBy)
                                <div><dt class="text-neutral-500 dark:text-neutral-400">Accepting Officer</dt><dd class="mt-1 font-semibold text-neutral-900 dark:text-neutral-100">{{ $iar->acceptedBy->name }}</dd></div>
                            @endif
                        </dl>
                    @else
                        <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">No Inspection and Acceptance Report is linked to this receipt.</p>
                    @endif
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/50 dark:text-primary-300 dark:ring-primary-800">
                            <x-ui.icon name="document-text" class="h-4 w-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-neutral-950 dark:text-white">Receiving Notes</h3>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Operational remarks captured at receiving.</p>
                        </div>
                    </div>
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-neutral-700 dark:text-neutral-300">{{ $goodsReceiptNote->notes ?: 'No receiving notes were recorded.' }}</p>
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

        <section class="rounded-2xl border border-neutral-200/90 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/50 dark:text-primary-300 dark:ring-primary-800">
                        <x-ui.icon name="arrow-path" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-neutral-950 dark:text-white">Receiving Workflow</h3>
                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Operational progress from dock receipt to final storage.</p>
                    </div>
                </div>
                @if($allTasks->isNotEmpty())
                    <a href="{{ route('inventory.warehousing.scan-station') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200">
                        Open Scan Workstation
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>
            <ol class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <li class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/30">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">1</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-emerald-950 dark:text-emerald-100">Delivery recorded</p>
                            <p class="mt-1 text-xs leading-5 text-emerald-700 dark:text-emerald-300">{{ $goodsReceiptNote->received_at?->format('M d, Y · h:i A') ?? 'Timestamp unavailable' }}</p>
                        </div>
                    </div>
                </li>
                <li class="rounded-xl border p-4 {{ $hasPendingInspection ? 'border-amber-300 bg-amber-50/80 dark:border-amber-900/70 dark:bg-amber-950/30' : 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/70 dark:bg-emerald-950/30' }}">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white {{ $hasPendingInspection ? 'bg-amber-500' : 'bg-emerald-600' }}">2</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-neutral-950 dark:text-neutral-100">Quality inspection</p>
                            <p class="mt-1 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ $hasPendingInspection ? 'Pending or in progress' : ($allInspections->isNotEmpty() ? 'Inspection recorded' : 'No inspection record') }}</p>
                        </div>
                    </div>
                </li>
                <li class="rounded-xl border p-4 {{ $hasRejectedInspection ? 'border-rose-200 bg-rose-50/80 dark:border-rose-900/70 dark:bg-rose-950/30' : ($acceptedQuantity > 0 ? 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/70 dark:bg-emerald-950/30' : 'border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950/40') }}">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white {{ $hasRejectedInspection ? 'bg-rose-600' : ($acceptedQuantity > 0 ? 'bg-emerald-600' : 'bg-neutral-400 dark:bg-neutral-600') }}">3</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-neutral-950 dark:text-neutral-100">Disposition</p>
                            <p class="mt-1 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ $hasRejectedInspection ? 'Rejected quantity recorded' : ($acceptedQuantity > 0 ? 'Accepted quantity recorded' : 'Awaiting disposition') }}</p>
                        </div>
                    </div>
                </li>
                <li class="rounded-xl border p-4 {{ $hasPendingPutAway ? 'border-primary-200 bg-primary-50/80 dark:border-primary-900/70 dark:bg-primary-950/30' : ($allTasksCompleted ? 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/70 dark:bg-emerald-950/30' : 'border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950/40') }}">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white {{ $hasPendingPutAway ? 'bg-primary-600' : ($allTasksCompleted ? 'bg-emerald-600' : 'bg-neutral-400 dark:bg-neutral-600') }}">4</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-neutral-950 dark:text-neutral-100">Put-away</p>
                            <p class="mt-1 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ $hasPendingPutAway ? 'Transfer pending' : ($allTasksCompleted ? 'Stored in destination' : 'Not yet required') }}</p>
                        </div>
                    </div>
                </li>
            </ol>
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
