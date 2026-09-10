<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.transfers.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline">
                        &larr; Stock Transfers
                    </a>
                    <span class="text-xs text-neutral-400">/</span>
                    <span class="text-xs text-neutral-500">{{ $stockTransfer->transfer_number }}</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">
                    Stock Transfer: {{ $stockTransfer->transfer_number }}
                </h2>
                <p class="text-sm text-neutral-600">
                    Virtual In-Transit buffer accounting, destination receipt verification, and loss/damage write-off tracking.
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if(in_array($stockTransfer->status, ['dispatched', 'in_transit']) && auth()->user()->can(\App\Enums\Permission::TransferStock->value))
                    <button type="button" @click="$dispatch('open-receive-modal')" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Receive Stock at Destination
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ receiveModalOpen: false }" @open-receive-modal.window="receiveModalOpen = true">
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
                        <span>Transfer Processing Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Discrepancy Warning if Applicable --}}
            @if($stockTransfer->status === 'discrepancy')
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 flex items-start gap-3 shadow-sm">
                    <svg class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="font-bold">Carrier / Transit Discrepancy Recorded</p>
                        <p class="text-xs text-rose-700 mt-0.5">
                            This transfer completed with transit discrepancies (damaged or lost items). Non-delivered quantities were written off from the virtual in-transit buffer and flagged for carrier investigation.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Transfer Details Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Transfer Status</p>
                        <div class="mt-2">
                            @if(in_array($stockTransfer->status, ['dispatched', 'in_transit']))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                    In-Transit Buffer Active
                                </span>
                            @elseif($stockTransfer->status === 'received')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Received in Full
                                </span>
                            @elseif($stockTransfer->status === 'discrepancy')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    Discrepancy Finalized
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800">
                                    {{ ucfirst($stockTransfer->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Logistical Route</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $stockTransfer->sourceLocation->name ?? 'Origin' }} &rarr; {{ $stockTransfer->destinationLocation->name ?? 'Destination' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            Virtual Buffer: {{ $stockTransfer->inTransitLocation->name ?? 'LOC-IN-TRANSIT' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Dispatch Logistics</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $stockTransfer->dispatchedBy->name ?? 'System' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            {{ $stockTransfer->dispatched_at ? $stockTransfer->dispatched_at->format('M d, Y h:i A') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Destination Intake</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">
                            {{ $stockTransfer->receivedBy->name ?? 'Pending Arrival' }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            {{ $stockTransfer->received_at ? $stockTransfer->received_at->format('M d, Y h:i A') : 'In transit' }}
                        </p>
                    </div>
                </div>

                @if($stockTransfer->notes)
                    <div class="mt-6 border-t border-neutral-100 pt-4">
                        <p class="text-xs font-medium text-neutral-500">Transfer Instructions / Notes:</p>
                        <p class="mt-1 text-sm text-neutral-700">{{ $stockTransfer->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Line Items Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Transferred Items &amp; Reconciliation</h3>
                    <p class="text-xs text-neutral-500">Comparison of dispatched quantities against physical intake at destination.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Item &amp; SKU</th>
                                <th class="px-6 py-3 font-medium text-right">Dispatched Qty</th>
                                <th class="px-6 py-3 font-medium text-right">Received Qty</th>
                                <th class="px-6 py-3 font-medium text-right">Damaged Qty</th>
                                <th class="px-6 py-3 font-medium text-right">Lost Qty</th>
                                <th class="px-6 py-3 font-medium">Reconciliation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @foreach($stockTransfer->lines as $line)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                        <p class="text-xs text-neutral-500">SKU: {{ $line->item->sku ?? 'N/A' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-neutral-900">
                                        {{ number_format($line->quantity) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-emerald-600">
                                        {{ number_format($line->received_quantity) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold {{ $line->damaged_quantity > 0 ? 'text-rose-600' : 'text-neutral-400' }}">
                                        {{ number_format($line->damaged_quantity) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold {{ $line->lost_quantity > 0 ? 'text-rose-600' : 'text-neutral-400' }}">
                                        {{ number_format($line->lost_quantity) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(in_array($stockTransfer->status, ['dispatched', 'in_transit']))
                                            <span class="inline-flex items-center rounded bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                                In-Transit
                                            </span>
                                        @elseif($line->damaged_quantity > 0 || $line->lost_quantity > 0)
                                            <span class="inline-flex items-center rounded bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-800 border border-rose-200">
                                                Discrepancy
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 border border-emerald-200">
                                                Reconciled 100%
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- RECEIVE STOCK AT DESTINATION MODAL --}}
        <div x-show="receiveModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="receiveModalOpen = false"></div>

                <div class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.transfers.receive', $stockTransfer) }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Destination Intake &amp; Discrepancy Recording</h3>
                                    <p class="text-xs text-neutral-500">Acknowledge delivery into destination location and write off any transit damages or losses.</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4">
                                @foreach($stockTransfer->lines as $idx => $line)
                                    <div class="rounded-lg bg-neutral-50 p-4 border border-neutral-200 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-semibold text-neutral-900">{{ $line->item->name }}</p>
                                            <p class="text-xs text-neutral-500 font-mono">Dispatched: {{ $line->quantity }} units</p>
                                            <input type="hidden" name="lines[{{ $idx }}][line_id]" value="{{ $line->id }}">
                                        </div>
                                        <div class="grid grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-emerald-700">Received Qty</label>
                                                <input type="number" name="lines[{{ $idx }}][received_quantity]" min="0" max="{{ $line->quantity }}" value="{{ $line->quantity }}" required
                                                       class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-xs text-right">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-rose-700">Damaged Qty</label>
                                                <input type="number" name="lines[{{ $idx }}][damaged_quantity]" min="0" max="{{ $line->quantity }}" value="0"
                                                       class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-xs text-right">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-neutral-600">Lost In-Transit</label>
                                                <input type="number" name="lines[{{ $idx }}][lost_quantity]" min="0" max="{{ $line->quantity }}" value="0"
                                                       class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 text-xs text-right">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Discrepancy Explanation (if damaged or lost)</label>
                                    <textarea name="discrepancy_reason" rows="2" placeholder="e.g. Carrier carton arrived crushed, vial leakage observed."
                                              class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="receiveModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Confirm Destination Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
