<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.cycle-counts.index') }}" class="text-xs font-semibold text-emerald-600 hover:underline">
                        &larr; Cycle Count Audits
                    </a>
                    <span class="text-xs text-neutral-400">/</span>
                    <span class="text-xs text-neutral-500">{{ $cycleCountDoc->document_number }}</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">
                    Audit Document: {{ $cycleCountDoc->document_number }}
                </h2>
                <p class="text-sm text-neutral-600">
                    Blind physical count verification, variance calculation, and multi-tier discrepancy authorization.
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if(in_array($cycleCountDoc->status, ['completed', 'recount_pending']) && auth()->user()->can(\App\Enums\Permission::ApproveAdjustment->value) && auth()->id() !== $cycleCountDoc->assigned_counter_id)
                    <form action="{{ route('inventory.cycle-counts.approve', $cycleCountDoc) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Approve &amp; Post Variances
                        </button>
                    </form>
                @elseif(in_array($cycleCountDoc->status, ['completed', 'recount_pending']) && auth()->id() === $cycleCountDoc->assigned_counter_id)
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm" title="Internal control policy requires an independent approver.">
                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-11a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Approval Locked (You are the Assigned Counter)
                    </span>
                @elseif(in_array($cycleCountDoc->status, ['completed', 'recount_pending']))
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-600">
                        Awaiting Approver (Inventory Manager / Admin)
                    </span>
                @elseif(in_array($cycleCountDoc->status, ['posted', 'approved']))
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Reconciled &amp; Posted
                    </span>
                @elseif(in_array($cycleCountDoc->status, ['scheduled', 'in_progress', 'generated']))
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800">
                        Pending Physical Count Submission
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Flash Alerts --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Cycle Count Variance Processing Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Recount Trigger Alert --}}
            @php
                $recountCount = $cycleCountDoc->lines->where('recount_required', true)->count();
            @endphp
            @if($recountCount > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 flex items-start gap-3 shadow-sm">
                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="font-bold">Threshold Exceeded: Recount Flagged on {{ $recountCount }} Item(s)</p>
                        <p class="text-xs text-amber-700 mt-0.5">
                            One or more lines exceed the tolerance threshold (&gt; 2% count variance or &gt; ₱5,000 monetary variance). An independent verification count is strongly recommended before supervisor sign-off.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Segregation of Duties Notice --}}
            @if(in_array($cycleCountDoc->status, ['completed', 'recount_pending']) && auth()->id() === $cycleCountDoc->assigned_counter_id)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex items-start gap-3 shadow-sm">
                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-11a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <div>
                        <p class="font-bold text-amber-900">Segregation of Duties (SoD) Active: Independent Approval Required</p>
                        <p class="text-xs text-amber-800 mt-0.5">
                            You are the <strong>Assigned Counter</strong> who submitted the physical counts for this document. Under hospital internal controls and GxP compliance, the counting officer cannot approve ledger variance adjustments. An independent <strong>Inventory Manager</strong>, <strong>Administrator</strong>, or <strong>Super Administrator</strong> must log in to review and post these adjustments.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Audit Details Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Document Status</p>
                        <div class="mt-2">
                            @if(in_array($cycleCountDoc->status, ['scheduled', 'in_progress', 'generated']))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Scheduled (Awaiting Counts)
                                </span>
                            @elseif($cycleCountDoc->status === 'completed')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    Completed (Pending Approval)
                                </span>
                            @elseif($cycleCountDoc->status === 'recount_pending')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    Recount Flagged (Pending Review)
                                </span>
                            @elseif(in_array($cycleCountDoc->status, ['posted', 'approved']))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Approved &amp; Reconciled
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800">
                                    {{ ucfirst($cycleCountDoc->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Sampling &amp; Scope</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900 uppercase">
                            {{ $cycleCountDoc->count_type }} Stratification
                        </p>
                        <p class="text-xs text-neutral-500">
                            Location: {{ $cycleCountDoc->location->name ?? 'All Facility Locations' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Assigned Counter</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $cycleCountDoc->assignedCounter->name ?? 'Unassigned' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            {{ $cycleCountDoc->assignedCounter->email ?? '' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Snapshot Freeze</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $cycleCountDoc->snapshot_timestamp ? $cycleCountDoc->snapshot_timestamp->format('M d, Y h:i A') : 'N/A' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            Approver: {{ $cycleCountDoc->approvedBy->name ?? 'Pending' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Approver Role & Governance Guide --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/90 p-4 shadow-sm text-xs text-neutral-700">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-200 pb-2.5 mb-2.5">
                    <span class="font-bold text-neutral-900 flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Cycle Count Approval &amp; Internal Controls Matrix
                    </span>
                    <span class="text-neutral-500">Dual-Authorization &amp; Segregation of Duties (SoD) Protocol</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-lg border border-neutral-200/80 bg-white p-2.5">
                        <span class="font-semibold text-neutral-900 block mb-1 text-emerald-700">Sino ang Pwedeng Mag-Approve?</span>
                        <ul class="list-disc list-inside space-y-1 text-neutral-600">
                            <li><strong class="text-neutral-800">Inventory Manager:</strong> Primary approver para sa adjustments up to ₱25,000.</li>
                            <li><strong class="text-neutral-800">Administrator / Super Admin:</strong> Required kapag ang variance ay lumagpas sa ₱25,000.</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-neutral-200/80 bg-white p-2.5">
                        <span class="font-semibold text-neutral-900 block mb-1 text-amber-700">Bakit Walang Approve Button?</span>
                        <ul class="list-disc list-inside space-y-1 text-neutral-600">
                            <li><strong>Scheduled pa lang:</strong> Kailangan munang i-encode at i-submit ang physical counts sa ibaba.</li>
                            <li><strong>Assigned Counter ka:</strong> Bawal aprubahan ng nagbilang ang sarili niyang bilang (SoD rule).</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-neutral-200/80 bg-white p-2.5">
                        <span class="font-semibold text-neutral-900 block mb-1 text-indigo-700">Dual-Tier Variance Thresholds</span>
                        <ul class="list-disc list-inside space-y-1 text-neutral-600">
                            <li><strong>&gt; 2% o &gt; ₱5,000:</strong> Auto-flagged for recount verification bago i-reconcile.</li>
                            <li><strong>&gt; ₱25,000:</strong> Strict requirement para sa Plant Controller / Administrator authorization.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- IF COUNTING IN PROGRESS: BLIND INPUT FORM --}}
            @if(in_array($cycleCountDoc->status, ['scheduled', 'in_progress', 'generated']))
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-neutral-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Blind Physical Count Sheet</h3>
                            <p class="text-xs text-neutral-500">Count physical units on shelves/bins and enter actual quantities. Expected system totals are hidden to prevent bias.</p>
                        </div>
                        <div class="flex items-center gap-2.5">
                            @can(\App\Enums\Permission::PerformCycleCount->value)
                            <x-ui.camera-scanner
                                id="camera-scanner-cycle-count"
                                button-text="Scan Shelf Item"
                                button-size="sm"
                                button-variant="secondary"
                                event-name="cycle-count-scanned"
                                title="Scan Shelf Item Barcode / QR"
                                hint="Scan item barcode, SKU, or location QR to jump to its count line."
                            />
                            @endcan
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 border border-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Blind Count Active
                            </span>
                        </div>
                    </div>

                    @can(\App\Enums\Permission::PerformCycleCount->value)
                    <form
                        action="{{ route('inventory.cycle-counts.submit', $cycleCountDoc) }}"
                        method="POST"
                        @cycle-count-scanned.window="
                            const scanned = ($event.detail.code || '').trim().toLowerCase();
                            const rows = $el.querySelectorAll('tr[data-scan-target]');
                            let matched = false;
                            rows.forEach(r => {
                                const targets = (r.dataset.scanTarget || '').toLowerCase().split('|');
                                if (targets.some(t => t && (t === scanned || scanned.includes(t) || t.includes(scanned)))) {
                                    r.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    r.classList.add('bg-emerald-100');
                                    setTimeout(() => r.classList.remove('bg-emerald-100'), 3000);
                                    const input = r.querySelector('input[type=number]');
                                    if (input) { input.focus(); input.select(); }
                                    matched = true;
                                }
                            });
                            if (!matched) {
                                alert('Scanned item/location [' + $event.detail.code + '] is not in this cycle count schedule.');
                            }
                        "
                    >
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-neutral-600">
                                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                                    <tr>
                                        <th class="px-6 py-3 font-medium">Item &amp; SKU</th>
                                        <th class="px-6 py-3 font-medium">Storage Location</th>
                                        <th class="px-6 py-3 font-medium">Batch / Lot</th>
                                        <th class="px-6 py-3 font-medium text-right w-44">Physical Count (Units)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200">
                                    @foreach($cycleCountDoc->lines as $line)
                                        <tr
                                            class="hover:bg-neutral-50 transition-colors duration-300"
                                            data-scan-target="{{ $line->item?->barcode_value }}|{{ $line->item?->sku }}|{{ $line->item?->gtin }}|{{ $line->location?->barcode_value }}|{{ $line->location?->code }}|{{ $line->batch?->batch_number }}"
                                        >
                                            <td class="px-6 py-4">
                                                <p class="font-semibold text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                                <p class="text-xs text-neutral-500">SKU: {{ $line->item->sku ?? 'N/A' }} | Class: {{ $line->item->abc_class ?? 'C' }}</p>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-medium text-neutral-800">
                                                {{ $line->location->name ?? 'Main Storage' }}
                                                <span class="text-neutral-400">({{ $line->location->code ?? '' }})</span>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-mono text-neutral-600">
                                                {{ $line->batch->batch_number ?? 'No Batch' }}
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <input type="number" name="counts[{{ $line->id }}]" min="0" value="{{ $line->counted_quantity_blind ?? '' }}" required placeholder="0"
                                                       class="w-32 rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm font-semibold text-right">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-neutral-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                Submit Blind Counts for Analysis
                            </button>
                        </div>
                    </form>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-600">
                            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                                <tr>
                                    <th class="px-6 py-3 font-medium">Item &amp; SKU</th>
                                    <th class="px-6 py-3 font-medium">Storage Location</th>
                                    <th class="px-6 py-3 font-medium">Batch / Lot</th>
                                    <th class="px-6 py-3 font-medium text-right">Physical Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @foreach($cycleCountDoc->lines as $line)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                            <p class="text-xs text-neutral-500">SKU: {{ $line->item->sku ?? 'N/A' }} | Class: {{ $line->item->abc_class ?? 'C' }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-medium text-neutral-800">
                                            {{ $line->location->name ?? 'Main Storage' }}
                                            <span class="text-neutral-400">({{ $line->location->code ?? '' }})</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-mono text-neutral-600">
                                            {{ $line->batch->batch_number ?? 'No Batch' }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex items-center rounded-md bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600">
                                                Pending Count
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endcan
                </div>
            @endif

            {{-- IF COMPLETED, RECOUNT PENDING, OR POSTED: VARIANCE REVELATION TABLE --}}
            @if(in_array($cycleCountDoc->status, ['completed', 'recount_pending', 'posted', 'approved']))
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-neutral-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Variance Analysis &amp; Reconciliation Matrix</h3>
                            <p class="text-xs text-neutral-500">Comparison of frozen system snapshot versus verified physical count.</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-600">
                            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                                <tr>
                                    <th class="px-6 py-3 font-medium">Item &amp; ABC Class</th>
                                    <th class="px-6 py-3 font-medium">Location</th>
                                    <th class="px-6 py-3 font-medium text-right">Snapshot System Qty</th>
                                    <th class="px-6 py-3 font-medium text-right">Counted Qty</th>
                                    <th class="px-6 py-3 font-medium text-right">Variance Qty</th>
                                    <th class="px-6 py-3 font-medium text-right">Variance Value</th>
                                    <th class="px-6 py-3 font-medium text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @foreach($cycleCountDoc->lines as $line)
                                    <tr class="hover:bg-neutral-50 {{ $line->recount_required ? 'bg-amber-50/40' : '' }}">
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                            <p class="text-xs text-neutral-500">SKU: {{ $line->item->sku ?? 'N/A' }} | Class: <span class="font-bold text-neutral-700">{{ $line->item->abc_class ?? 'C' }}</span></p>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-neutral-700">
                                            {{ $line->location->name ?? 'Main Storage' }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono font-medium text-neutral-700">
                                            {{ number_format($line->book_quantity_snapshot) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono font-semibold text-neutral-900">
                                            {{ number_format($line->counted_quantity_blind ?? 0) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono font-bold {{ $line->variance_quantity < 0 ? 'text-rose-600' : ($line->variance_quantity > 0 ? 'text-primary-600' : 'text-emerald-600') }}">
                                            {{ $line->variance_quantity > 0 ? '+' : '' }}{{ number_format($line->variance_quantity) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-mono font-medium {{ $line->variance_value < 0 ? 'text-rose-600' : ($line->variance_value > 0 ? 'text-primary-600' : 'text-neutral-500') }}">
                                            ₱{{ number_format($line->variance_value, 2) }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if($line->recount_required)
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                                                    Recount Triggered
                                                </span>
                                            @elseif($line->variance_quantity === 0)
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                    Exact Match
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700">
                                                    Variance Within Spec
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
