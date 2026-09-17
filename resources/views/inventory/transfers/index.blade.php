<x-app-layout>
    <div class="space-y-6" x-data="{
        newTransferModal: {{ $errors->any() ? 'true' : 'false' }},
        sourceLocationId: '{{ old('source_location_id', '') }}',
        destinationLocationId: '{{ old('destination_location_id', '') }}',
        itemsList: {{ Js::from($items) }},
        stockMap: {{ Js::from($locationStockMap ?? []) }},
        lines: {{ Js::from(old('lines', [['item_id' => '', 'quantity' => 1]])) }},
        addLine() {
            this.lines.push({ item_id: '', quantity: 1 });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },
        getAvailable(locId, itemId) {
            if (!locId || !itemId) return 0;
            return (this.stockMap[locId] && this.stockMap[locId][itemId]) ? parseInt(this.stockMap[locId][itemId]) : 0;
        },
        getAvailableItems() {
            if (!this.sourceLocationId) return [];
            return this.itemsList.filter(itm => this.getAvailable(this.sourceLocationId, itm.id) > 0);
        },
        onSourceLocationChange() {
            this.lines.forEach(line => {
                if (line.item_id && this.getAvailable(this.sourceLocationId, line.item_id) <= 0) {
                    line.item_id = '';
                    line.quantity = 1;
                }
            });
        },
        isOverStock(line) {
            if (!this.sourceLocationId || !line.item_id) return false;
            const avail = this.getAvailable(this.sourceLocationId, line.item_id);
            const qty = parseInt(line.quantity || 0);
            return qty > avail || avail <= 0 || qty > 999999;
        },
        hasAnyOverStock() {
            return this.lines.some(l => this.isOverStock(l));
        },
        handleLineQtyKeydown(e, line) {
            if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Home', 'End'].includes(e.key)) return;
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            if (!/^\d$/.test(e.key)) {
                e.preventDefault();
                return;
            }
            const currentVal = String(line.quantity || '');
            const hasSelection = e.target.selectionStart !== e.target.selectionEnd;
            if (currentVal.length >= 6 && !hasSelection) {
                e.preventDefault();
            }
        },
        handleLineQtyInput(e, line) {
            let val = e.target.value;
            if (!val) {
                line.quantity = '';
                return;
            }
            val = val.replace(/\D/g, '');
            if (val.length > 6) {
                val = val.slice(0, 6);
            }
            if (parseInt(val, 10) > 999999) {
                val = '999999';
            }
            line.quantity = val;
            e.target.value = val;
        },
        canSubmit() {
            if (!this.sourceLocationId || !this.destinationLocationId) return false;
            if (this.sourceLocationId === this.destinationLocationId) return false;
            if (!this.lines.length) return false;
            if (this.lines.some(l => !l.item_id || !l.quantity || parseInt(l.quantity) <= 0 || parseInt(l.quantity) > 999999)) return false;
            return !this.hasAnyOverStock();
        }
    }"
    @open-new-transfer-modal.window="newTransferModal = true"
    @keydown.escape.window="newTransferModal = false"
    >
        {{-- Header with Action Button --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-neutral-200 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">Internal Logistics</span>
                    <span class="text-xs text-neutral-500">• In-Transit Virtual Buffer &amp; Discrepancy Tracking</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Stock Transfers</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" :href="route('inventory.stock-movements')" icon="arrows-right-left">Movement Ledger</x-ui.button>
                <x-ui.button variant="secondary" :href="route('inventory.items')" icon="arrow-left">Back to Inventory</x-ui.button>
                @can(\App\Enums\Permission::TransferStock->value)
                    <button type="button"
                            @click="newTransferModal = true"
                            id="btn-initiate-stock-transfer"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Initiate Stock Transfer
                    </button>
                @endcan
            </div>
        </div>

            {{-- Consolidated Inventory Workflow Navigation --}}
            @include('inventory.partials.workflow_nav')

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
                        <span>Transfer Initiation Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Summary Cards --}}
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Total Transfers</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $transfers->total() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Documented logistical moves</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">In-Transit Active</p>
                    <p class="mt-2 text-2xl font-bold text-indigo-600">
                        {{ $transfers->whereIn('status', ['dispatched', 'in_transit'])->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Moving between physical facilities</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Transit Discrepancies</p>
                    <p class="mt-2 text-2xl font-bold text-rose-600">
                        {{ $transfers->where('status', 'discrepancy')->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Loss/damage during transit</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Completed Receipts</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">
                        {{ $transfers->where('status', 'received')->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Acknowledged at destination</p>
                </div>
            </div>

            {{-- Transfers Registry Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Stock Transfer Registry</h3>
                    <p class="text-xs text-neutral-500">Track dispatch, transit status, and destination receipt acknowledgment.</p>
                </div>
                <div class="overflow-x-auto hims-table-scroll">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Transfer #</th>
                                <th class="px-6 py-3 font-medium">Origin Location</th>
                                <th class="px-6 py-3 font-medium">Destination Location</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Dispatched</th>
                                <th class="px-6 py-3 font-medium">Received</th>
                                <th class="px-6 py-3 font-medium text-right hims-sticky-actions min-w-[150px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($transfers as $trans)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('inventory.transfers.show', $trans) }}" class="font-bold text-indigo-600 hover:underline">
                                            {{ $trans->transfer_number }}
                                        </a>
                                        <p class="text-xs text-neutral-500">{{ $trans->lines->count() }} item(s)</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $trans->sourceLocation->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-neutral-500">{{ $trans->sourceLocation->code ?? '' }} &bull; {{ $trans->sourceLocation->zone ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $trans->destinationLocation->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-neutral-500">{{ $trans->destinationLocation->code ?? '' }} &bull; {{ $trans->destinationLocation->zone ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(in_array($trans->status, ['dispatched', 'in_transit']))
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                                In-Transit
                                            </span>
                                        @elseif($trans->status === 'received')
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Received in Full
                                            </span>
                                        @elseif($trans->status === 'discrepancy')
                                            <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">
                                                Discrepancy (Damaged/Lost)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                                {{ ucfirst($trans->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <p class="font-medium text-neutral-800">{{ $trans->dispatchedBy->name ?? 'System' }}</p>
                                        <p class="text-neutral-500">{{ $trans->dispatched_at ? $trans->dispatched_at->format('M d, Y') : 'N/A' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        @if($trans->receivedBy)
                                            <p class="font-medium text-neutral-800">{{ $trans->receivedBy->name }}</p>
                                            <p class="text-neutral-500">{{ $trans->received_at ? $trans->received_at->format('M d, Y') : '' }}</p>
                                        @else
                                            <span class="text-neutral-400 italic">Pending destination intake</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right hims-sticky-actions min-w-[150px]">
                                        <a href="{{ route('inventory.transfers.show', $trans) }}" class="inline-block whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 transition">
                                            Manage Transfer
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 mb-3">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-semibold text-neutral-800">No stock transfers recorded yet</p>
                                        <p class="mt-1 text-xs text-neutral-500 max-w-sm mx-auto">Move inventory between storerooms, wards, and facilities with in-transit buffer accounting.</p>
                                        @can(\App\Enums\Permission::TransferStock->value)
                                            <button type="button" @click="newTransferModal = true" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                Initiate First Stock Transfer
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($transfers->hasPages())
                    <div class="border-t border-neutral-200 px-4 py-3 sm:px-5 dark:border-neutral-800">
                        {{ $transfers->links() }}
                    </div>
                @endif
            </div>

        @include('inventory.transfers.partials.initiate_modal')

    </div>
</x-app-layout>
