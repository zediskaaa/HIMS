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
                <a href="{{ route('inventory.warehousing.scan-station') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                    Scan Workstation
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif
        {{-- SWS Consolidated Workflow Navigation --}}
        @include('inventory.warehousing.partials.workflow_nav')

        @php
            // Calculate workflow stages
            $allInspections = $goodsReceiptNote->lines->flatMap->inspections;
            $allTasks = $allInspections->flatMap->warehouseTasks;

            $hasPendingInspection = $allInspections->contains(fn($i) => in_array($i->inspection_status, ['pending_sample', 'partially_disposed', 'under_review']));
            $hasApprovedInspection = $goodsReceiptNote->lines->sum('accepted_quantity') > 0;
            $hasRejectedInspection = $allInspections->contains(fn($i) => $i->inspection_status === 'rejected');

            $allTasksCompleted = $allTasks->isNotEmpty() && $allTasks->every(fn($t) => $t->status === \App\Enums\WarehouseTaskStatus::Completed);
            $hasPendingTasks = $goodsReceiptNote->lines->sum('pending_put_away_quantity') > 0;

            $discrepancyLines = $goodsReceiptNote->lines->filter(fn($l) => $l->item_condition !== 'good' || $l->discrepancy_type !== null);
        @endphp

        {{-- 1. End-to-End Receiving Lifecycle Stepper --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500 mb-4">Post-Delivery Receiving Lifecycle</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                {{-- Step 1: PO Approved --}}
                <div class="flex items-center gap-2.5 p-2 rounded-lg bg-emerald-50 border border-emerald-200">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-bold">1</div>
                    <div>
                        <p class="text-xs font-bold text-emerald-900">1. PO Approved</p>
                        <p class="text-[10px] text-emerald-700 font-mono">{{ $goodsReceiptNote->purchaseOrder?->po_number ?? 'Direct' }}</p>
                    </div>
                </div>

                {{-- Step 2: Delivery Received --}}
                <div class="flex items-center gap-2.5 p-2 rounded-lg bg-emerald-50 border border-emerald-200">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-bold">2</div>
                    <div>
                        <p class="text-xs font-bold text-emerald-900">2. Dock Intake</p>
                        <p class="text-[10px] text-emerald-700">{{ $goodsReceiptNote->received_at ? $goodsReceiptNote->received_at->format('M d, H:i') : 'Received' }}</p>
                    </div>
                </div>

                {{-- Step 3: Under Inspection / QA --}}
                <div class="flex items-center gap-2.5 p-2 rounded-lg {{ $hasPendingInspection ? 'bg-amber-50 border-amber-300 ring-2 ring-amber-400/30' : ($allInspections->isNotEmpty() ? 'bg-emerald-50 border-emerald-200' : 'bg-neutral-50 border-neutral-200') }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $hasPendingInspection ? 'bg-amber-500 text-white animate-pulse' : ($allInspections->isNotEmpty() ? 'bg-emerald-600 text-white' : 'bg-neutral-300 text-neutral-600') }} text-xs font-bold">
                        {{ $hasPendingInspection ? '!' : '3' }}
                    </div>
                    <div>
                        <p class="text-xs font-bold {{ $hasPendingInspection ? 'text-amber-900' : ($allInspections->isNotEmpty() ? 'text-emerald-900' : 'text-neutral-600') }}">3. QA Assay</p>
                        <p class="text-[10px] {{ $hasPendingInspection ? 'text-amber-700 font-semibold' : 'text-neutral-500' }}">
                            {{ $hasPendingInspection ? 'In Quarantine' : 'Completed' }}
                        </p>
                    </div>
                </div>

                {{-- Step 4: Accepted / Rejected --}}
                <div class="flex items-center gap-2.5 p-2 rounded-lg {{ $hasRejectedInspection ? 'bg-rose-50 border-rose-200' : ($hasApprovedInspection ? 'bg-emerald-50 border-emerald-200' : 'bg-neutral-50 border-neutral-200') }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $hasRejectedInspection ? 'bg-rose-600 text-white' : ($hasApprovedInspection ? 'bg-emerald-600 text-white' : 'bg-neutral-300 text-neutral-600') }} text-xs font-bold">
                        4
                    </div>
                    <div>
                        <p class="text-xs font-bold {{ $hasRejectedInspection ? 'text-rose-900' : ($hasApprovedInspection ? 'text-emerald-900' : 'text-neutral-600') }}">4. Disposition</p>
                        <p class="text-[10px] text-neutral-500">
                            {{ $hasRejectedInspection ? 'Rejected/Blocked' : ($hasApprovedInspection ? 'Approved / Staging' : 'Pending') }}
                        </p>
                    </div>
                </div>

                {{-- Step 5: Awaiting Put-Away --}}
                <div class="flex items-center gap-2.5 p-2 rounded-lg {{ $hasPendingTasks ? 'bg-blue-50 border-blue-300 ring-2 ring-blue-400/30' : ($allTasksCompleted ? 'bg-emerald-50 border-emerald-200' : 'bg-neutral-50 border-neutral-200') }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $hasPendingTasks ? 'bg-blue-600 text-white animate-pulse' : ($allTasksCompleted ? 'bg-emerald-600 text-white' : 'bg-neutral-300 text-neutral-600') }} text-xs font-bold">
                        5
                    </div>
                    <div>
                        <p class="text-xs font-bold {{ $hasPendingTasks ? 'text-blue-900' : ($allTasksCompleted ? 'text-emerald-900' : 'text-neutral-600') }}">5. Put-Away</p>
                        <p class="text-[10px] text-neutral-500">
                            {{ $hasPendingTasks ? 'Transfer Pending' : ($allTasksCompleted ? 'Transferred' : 'Not required') }}
                        </p>
                    </div>
                </div>

                {{-- Step 6: Available Stock --}}
                <div class="flex items-center gap-2.5 p-2 rounded-lg {{ $allTasksCompleted ? 'bg-emerald-50 border-emerald-200' : 'bg-neutral-50 border-neutral-200' }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $allTasksCompleted ? 'bg-emerald-600 text-white' : 'bg-neutral-300 text-neutral-600' }} text-xs font-bold">
                        6
                    </div>
                    <div>
                        <p class="text-xs font-bold {{ $allTasksCompleted ? 'text-emerald-900' : 'text-neutral-600' }}">6. Available</p>
                        <p class="text-[10px] text-neutral-500">
                            {{ $allTasksCompleted ? 'Active Stock' : 'Locked' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Discrepancy Alert Banner --}}
        @if($discrepancyLines->isNotEmpty())
            <div class="rounded-xl border border-amber-300 bg-amber-50/90 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-200 text-amber-900">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="space-y-2 flex-1">
                        <h4 class="text-sm font-bold text-amber-950">Inbound Discrepancy &amp; Inspection Flags Detected</h4>
                        <p class="text-xs text-amber-800">
                            The following line items were flagged with packaging defects, shortages, or discrepancies during physical dock intake and have been quarantined:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mt-2">
                            @foreach($discrepancyLines as $disc)
                                <div class="p-2.5 rounded-lg bg-white/80 border border-amber-200 text-xs space-y-1">
                                    <div class="font-bold text-neutral-900">{{ $disc->item?->name ?? 'Item' }}</div>
                                    <div class="text-[11px] text-neutral-600">Condition: <strong class="uppercase text-amber-900">{{ str_replace('_', ' ', $disc->item_condition) }}</strong></div>
                                    @if($disc->discrepancy_type)
                                        <div class="text-[11px] text-neutral-600">Type: <span class="capitalize font-semibold text-neutral-800">{{ str_replace('_', ' ', $disc->discrepancy_type) }}</span></div>
                                    @endif
                                    @if($disc->discrepancy_action)
                                        <div class="text-[11px] text-neutral-600">Action: <span class="capitalize font-semibold text-primary-700">{{ str_replace('_', ' ', $disc->discrepancy_action) }}</span></div>
                                    @endif
                                    @if($disc->discrepancy_notes)
                                        <div class="text-[10px] text-neutral-500 italic">"{{ $disc->discrepancy_notes }}"</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Put-Away Tasks Queue --}}
        @if($allTasks->isNotEmpty())
            <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-600 text-white text-xs font-bold">5</span>
                        <h4 class="text-sm font-bold text-neutral-900">Put-Away Warehouse Transfer Tasks</h4>
                    </div>
                    <a href="{{ route('inventory.warehousing.scan-station') }}" class="text-xs font-semibold text-primary-700 hover:underline">
                        Open Scan Workstation &rarr;
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($allTasks as $task)
                        <div class="p-3 rounded-lg bg-white border border-neutral-200 shadow-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold text-primary-800">{{ $task->task_number }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $task->status === \App\Enums\WarehouseTaskStatus::Completed ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ ucfirst($task->status->value) }}
                                </span>
                            </div>
                            <div class="text-xs text-neutral-700">
                                <div>Item: <strong class="text-neutral-900">{{ $task->item?->name }}</strong></div>
                                <div>Transfer: <strong class="font-mono">{{ $task->requested_quantity }}</strong> units</div>
                                <div class="text-[11px] text-neutral-500 mt-1">
                                    <span>From: <strong>{{ $task->sourceLocation?->name ?? 'LOC-STAGING' }}</strong></span><br>
                                    <span>To: <strong class="text-primary-800">{{ $task->destinationLocation?->name ?? 'Rack/Bin' }}</strong></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Status & Summary Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">GRN Status</p>
                    <div class="mt-2">
                        @if($goodsReceiptNote->status === 'quarantined')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                Quarantined (Inbound QA Assay)
                            </span>
                        @elseif($goodsReceiptNote->status === 'received')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Released &amp; Stored
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800">
                                {{ ucfirst(str_replace('_', ' ', $goodsReceiptNote->status)) }}
                            </span>
                        @endif
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Purchase Order</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900 font-mono">
                        {{ $goodsReceiptNote->purchaseOrder->po_number ?? 'Direct Receipt' }}
                    </p>
                    <p class="text-xs text-neutral-500">
                        Supplier: <strong>{{ $goodsReceiptNote->supplier->name ?? 'N/A' }}</strong>
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Carrier Logistics</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">
                        {{ $goodsReceiptNote->carrier_name ?: 'Not recorded' }}
                    </p>
                    <p class="text-xs text-neutral-500">
                        Waybill: {{ $goodsReceiptNote->waybill_number ?? 'N/A' }} | Slip: {{ $goodsReceiptNote->packing_slip_number ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Dock Intake Audit</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">
                        {{ $goodsReceiptNote->receivedBy->name ?? 'Warehouse Staff' }}
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
            <div class="border-b border-neutral-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-neutral-900">Delivered Line Items, UoM Conversions &amp; Quality Assay</h3>
                    <p class="text-xs text-neutral-500">Line verification against ordered quantities, registered lot numbers, and current inspection checkpoints.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-neutral-600">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                        <tr>
                            <th class="px-6 py-3 font-medium">Item &amp; SKU</th>
                            <th class="px-6 py-3 font-medium">PO Expected vs Delivered</th>
                            <th class="px-6 py-3 font-medium">Batch / Serial / Expiry</th>
                            <th class="px-6 py-3 font-medium">Condition &amp; Route</th>
                            <th class="px-6 py-3 font-medium text-right">Unit Cost</th>
                            <th class="px-6 py-3 font-medium text-right">Total Price</th>
                            <th class="px-6 py-3 font-medium text-center">QC Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($goodsReceiptNote->lines as $line)
                            @php
                                $lineUnit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
                                $baseUnit = $line->item?->unit ?: 'unit';
                                $lineTotal = (float) ($line->received_quantity * $line->unit_cost);
                                $conversion = $line->conversionFactor();
                                $baseQty = $line->calculatedReceivedBaseQuantity();
                                $inspection = $line->inspections->first();
                            @endphp
                            <tr class="hover:bg-neutral-50 align-top">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                    <p class="text-xs font-mono text-neutral-500">{{ $line->item->sku ?? 'No SKU' }}</p>
                                    @if($conversion > 1)
                                        <span class="inline-block mt-1 rounded bg-neutral-100 px-2 py-0.5 text-[10px] font-mono font-medium text-neutral-700">
                                            1 {{ $lineUnit }} = {{ $conversion }} {{ $baseUnit }}s
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs space-y-1">
                                        <div>Ordered: <span class="font-mono font-bold">{{ $line->ordered_quantity }} {{ \Illuminate\Support\Str::plural($lineUnit, $line->ordered_quantity) }}</span></div>
                                        <div>Received: <span class="font-mono font-bold text-primary-700">{{ $line->received_quantity }} {{ \Illuminate\Support\Str::plural($lineUnit, $line->received_quantity) }}</span></div>
                                        <div>QC accepted: <strong>{{ $line->accepted_quantity }} {{ $lineUnit }}</strong></div>
                                        <div>QC rejected: <strong>{{ $line->rejected_quantity }} {{ $lineUnit }}</strong></div>
                                        <div>Pending QC: <strong>{{ $line->quarantined_quantity }} {{ $lineUnit }}</strong></div>
                                        <div>Awaiting put-away: <strong>{{ $line->pending_put_away_quantity }} {{ $baseUnit }}</strong></div>
                                        <div>Available from this receipt: <strong>{{ max(0, $line->accepted_quantity * $conversion - $line->pending_put_away_quantity) }} {{ $baseUnit }}</strong></div>
                                        <div>PO outstanding: <strong>{{ $line->purchaseOrderLine?->outstandingQuantity() ?? 0 }} {{ $lineUnit }}</strong></div>
                                        <div>Returned: <strong>{{ $line->returned_quantity }} {{ $baseUnit }}</strong></div>
                                        <div class="rounded bg-neutral-50 px-2 py-1 text-[11px] font-mono text-neutral-600 border border-neutral-200">
                                            = <strong class="text-primary-800">{{ $baseQty }}</strong> {{ \Illuminate\Support\Str::plural($baseUnit, $baseQty) }} inventory
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs space-y-0.5">
                                        @if($line->batch_number)
                                            <p><span class="font-medium text-neutral-700">Batch:</span> <span class="font-mono">{{ $line->batch_number }}</span></p>
                                        @endif
                                        @if($line->serial_number)
                                            <p><span class="font-medium text-neutral-700">Serial:</span> <span class="font-mono">{{ $line->serial_number }}</span></p>
                                        @endif
                                        @if($line->expiry_date)
                                            <p><span class="font-medium text-neutral-700">Expiry:</span> <span class="{{ $line->expiry_date->isPast() ? 'text-rose-600 font-semibold' : 'text-neutral-700' }}">{{ $line->expiry_date->format('M d, Y') }}</span></p>
                                        @endif
                                        @if(!$line->batch_number && !$line->serial_number && !$line->expiry_date)
                                            <span class="text-neutral-400 italic">No batch/serial registered</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs space-y-1">
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold {{ $line->item_condition === 'good' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                            {{ $line->item_condition === 'good' ? 'Good Condition' : ucfirst(str_replace('_', ' ', $line->item_condition ?? 'Inspection')) }}
                                        </span>
                                        @if($line->destinationLocation)
                                            <div class="text-[11px] text-neutral-500">
                                                Target: <strong class="text-neutral-800">{{ $line->destinationLocation->name }}</strong>
                                            </div>
                                        @endif
                                        @can(\App\Enums\Permission::RecordMovements->value)
                                            @if($line->rejected_quantity * $conversion > $line->returned_quantity)
                                                <form method="POST" action="{{ route('inventory.receiving.return', [$goodsReceiptNote, $line]) }}"
                                                      data-confirm-title="Return rejected stock"
                                                      data-confirm-message="Confirm physical return of the rejected base units to the supplier."
                                                      data-confirm-label="Record return" class="mt-2 space-y-1">
                                                    @csrf
                                                    <input type="hidden" name="return_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <label class="block text-[11px] font-medium">Return quantity ({{ $baseUnit }})</label>
                                                    <input type="number" name="quantity" min="1" max="{{ $line->rejected_quantity * $conversion - $line->returned_quantity }}" value="{{ $line->rejected_quantity * $conversion - $line->returned_quantity }}" required class="w-24 rounded border-neutral-300 text-xs">
                                                    <button type="submit" class="block text-xs font-semibold text-rose-700 underline">Return rejected stock</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-neutral-700 text-xs">
                                    ₱{{ number_format($line->unit_cost, 2) }}/{{ $lineUnit }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-semibold text-neutral-900 text-xs">
                                    ₱{{ number_format($lineTotal, 2) }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(!$inspection || in_array($inspection->inspection_status, ['pending_sample', 'partially_disposed']))
                                        <span class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 border border-amber-200">
                                            Pending QA Assay
                                        </span>
                                    @elseif($inspection->inspection_status === 'approved')
                                        <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                            Accepted (Put-Away Pending)
                                        </span>
                                    @elseif($inspection->inspection_status === 'rejected')
                                        <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-800 border border-rose-200">
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
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-neutral-500">
                                    No line items associated with this Goods Receipt Note.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-neutral-200 bg-neutral-50 font-semibold text-neutral-900 text-xs">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right">Total Received Goods Value:</td>
                            <td class="px-6 py-3 text-right font-mono font-bold text-primary-700" colspan="2">
                                ₱{{ number_format($goodsReceiptNote->lines->sum(fn ($l) => $l->received_quantity * $l->unit_cost), 2) }}
                            </td>
                            <td></td>
                        </tr>
                        @if($goodsReceiptNote->purchaseOrder)
                            <tr class="text-neutral-500 font-normal">
                                <td colspan="4" class="px-6 py-2 text-right">Original Purchase Order Total:</td>
                                <td class="px-6 py-2 text-right font-mono" colspan="2">
                                    ₱{{ number_format((float) $goodsReceiptNote->purchaseOrder->total_amount, 2) }}
                                </td>
                                <td></td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
