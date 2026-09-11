<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Quality Control</span>
                    <span class="text-xs text-neutral-500">• Quarantine Assay &amp; Partitioning Protocol</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">QC Inspection &amp; Release Queue</h2>
                <p class="text-sm text-neutral-600">
                    Verify physical condition, package integrity, cold-chain temperature logs, and laboratory certificates before releasing stock from quarantine to unrestricted inventory.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.button variant="secondary" :href="route('inventory.warehousing.dashboard')" icon="arrow-left">Back to Smart Warehousing</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        releaseModalOpen: false,
        rejectModalOpen: false,
        selectedInspection: null,
        openReleaseModal(inspection) {
            this.selectedInspection = inspection;
            this.releaseModalOpen = true;
        },
        openRejectModal(inspection) {
            this.selectedInspection = inspection;
            this.rejectModalOpen = true;
        }
    }">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Flash Alerts --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Quality Control Processing Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Metric Cards --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Quarantine Assay Queue</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600">{{ $inspections->total() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Pending clinical or packaging release</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Target Unrestricted Zones</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">{{ count($storageLocations) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Active distribution racks &amp; pharmacy shelves</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Regulatory Compliance</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">DOH AO 2019-0041</p>
                    <p class="mt-1 text-xs text-neutral-500">Philippine Good Storage and Distribution Practices</p>
                </div>
            </div>

            {{-- Inspection Queue Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Quarantined Stock Awaiting Quality Disposition</h3>
                        <p class="text-xs text-neutral-500">Perform physical sampling, cold-chain checks, and assay before releasing to active stock or transferring to blocked disposal.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Item Name &amp; SKU</th>
                                <th class="px-6 py-3 font-medium">GRN # &amp; Supplier</th>
                                <th class="px-6 py-3 font-medium">Batch / Lot / Expiry</th>
                                <th class="px-6 py-3 font-medium text-right">Quarantined Qty</th>
                                <th class="px-6 py-3 font-medium">Received Date</th>
                                <th class="px-6 py-3 font-medium text-center">Quality Disposition</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($inspections as $insp)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-neutral-900">{{ $insp->item->name ?? 'Item #' . $insp->inventory_item_id }}</p>
                                        <p class="text-xs text-neutral-500">SKU: {{ $insp->item->sku ?? 'N/A' }} | Class: {{ $insp->item->abc_class ?? 'Standard' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs font-semibold text-neutral-800">{{ $insp->grnLine->goodsReceiptNote->grn_number ?? 'Direct Intake' }}</p>
                                        <p class="text-xs text-neutral-500">{{ $insp->grnLine->goodsReceiptNote->supplier->name ?? 'Supplier Direct' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <div class="space-y-0.5">
                                            @if($insp->batch)
                                                <p><span class="font-medium text-neutral-700">Batch:</span> {{ $insp->batch->batch_number }}</p>
                                                <p><span class="font-medium text-neutral-700">Expiry:</span> {{ $insp->batch->expiry_date ? $insp->batch->expiry_date->format('M d, Y') : 'N/A' }}</p>
                                            @elseif($insp->grnLine)
                                                <p><span class="font-medium text-neutral-700">Lot:</span> {{ $insp->grnLine->lot_number ?? $insp->grnLine->batch_number ?? 'N/A' }}</p>
                                                <p><span class="font-medium text-neutral-700">Expiry:</span> {{ $insp->grnLine->expiry_date ? $insp->grnLine->expiry_date->format('M d, Y') : 'N/A' }}</p>
                                            @else
                                                <span class="text-neutral-400 italic">No batch metadata</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-amber-600">
                                        {{ number_format($insp->sample_size) }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-neutral-500">
                                        {{ $insp->inspection_date ? $insp->inspection_date->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @can(\App\Enums\Permission::InspectStock->value)
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button"
                                                @click="openReleaseModal({{ Js::from([
                                                    'id' => $insp->id,
                                                    'item_name' => $insp->item->name ?? 'Item',
                                                    'sku' => $insp->item->sku ?? '',
                                                    'quantity' => $insp->sample_size,
                                                    'batch_number' => $insp->batch->batch_number ?? $insp->grnLine->batch_number ?? 'N/A',
                                                ]) }})"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Release to Stock
                                            </button>
                                            <button type="button"
                                                @click="openRejectModal({{ Js::from([
                                                    'id' => $insp->id,
                                                    'item_name' => $insp->item->name ?? 'Item',
                                                    'quantity' => $insp->sample_size,
                                                ]) }})"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm hover:bg-rose-100 transition">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Reject / Block
                                            </button>
                                        </div>
                                        @else
                                        <span class="inline-flex items-center rounded-md bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600">
                                            Under Review
                                        </span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500">
                                        <svg class="mx-auto h-10 w-10 text-emerald-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p class="font-semibold text-neutral-800">Quarantine queue is clear!</p>
                                        <p class="text-xs text-neutral-500 mt-1">All received inbound goods have undergone QA assay and disposition.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($inspections->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $inspections->links() }}
                    </div>
                @endif
            </div>

        </div>

        @can(\App\Enums\Permission::InspectStock->value)
        {{-- RELEASE MODAL --}}
        <div x-show="releaseModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="releaseModalOpen = false"></div>

                <div class="inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form :action="'{{ url('/inventory/qc') }}/' + (selectedInspection ? selectedInspection.id : '') + '/release'" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">QC Release: Quarantine to Unrestricted Stock</h3>
                                    <p class="text-xs text-neutral-500">Select the compatible final destination; configured warehouses route released stock through receiving staging first.</p>
                                </div>
                            </div>

                            <div class="mt-4 rounded-lg bg-neutral-50 p-3 text-xs text-neutral-600">
                                <p><span class="font-semibold text-neutral-800">Item:</span> <span x-text="selectedInspection?.item_name"></span> (<span x-text="selectedInspection?.sku"></span>)</p>
                                <p class="mt-1"><span class="font-semibold text-neutral-800">Batch / Lot:</span> <span x-text="selectedInspection?.batch_number"></span></p>
                                <p class="mt-1"><span class="font-semibold text-neutral-800">Quarantined Quantity:</span> <span class="font-bold text-amber-600" x-text="selectedInspection?.quantity"></span> units</p>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Accepted Quantity to Release</label>
                                    <input type="number" name="accepted_quantity" min="1" :max="selectedInspection?.quantity" :value="selectedInspection?.quantity" required
                                           class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Target Unrestricted Storage Location</label>
                                    <select name="target_location_id" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                        <option value="">-- Select Destination Storage Location --</option>
                                        @foreach($storageLocations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }}) &bull; {{ $loc->zone ?? 'Unrestricted' }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-neutral-500">The selected location is the put-away destination. The system creates a warehouse task when physical staging is enabled.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Assay Findings &amp; Quality Notes</label>
                                    <textarea name="findings" rows="3" placeholder="Cold-chain temperature logs verified, package seals intact, CoA matches specifications."
                                              class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="releaseModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Confirm Stock Release
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- REJECT MODAL --}}
        <div x-show="rejectModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="rejectModalOpen = false"></div>

                <div class="inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form :action="'{{ url('/inventory/qc') }}/' + (selectedInspection ? selectedInspection.id : '') + '/reject'" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-rose-900">QC Rejection: Quarantine to Blocked Stock</h3>
                                    <p class="text-xs text-neutral-500">Isolate non-conforming or compromised goods for return to vendor (RTV) or destruction.</p>
                                </div>
                            </div>

                            <div class="mt-4 rounded-lg bg-rose-50 p-3 text-xs text-rose-800 border border-rose-100">
                                <p><span class="font-semibold">Item:</span> <span x-text="selectedInspection?.item_name"></span></p>
                                <p class="mt-1"><span class="font-semibold">Quarantined Quantity:</span> <span class="font-bold" x-text="selectedInspection?.quantity"></span> units</p>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Rejected Quantity</label>
                                    <input type="number" name="rejected_quantity" min="1" :max="selectedInspection?.quantity" :value="selectedInspection?.quantity" required
                                           class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Mandatory Failure Cause / Reason</label>
                                    <textarea name="rejection_reason" rows="3" required placeholder="Broken cold-chain (temperature excursion > 8C), packaging compromised, expired lot delivered, or certificate of analysis failed."
                                              class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="rejectModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">
                                Reject &amp; Block Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan

    </div>
</x-app-layout>
