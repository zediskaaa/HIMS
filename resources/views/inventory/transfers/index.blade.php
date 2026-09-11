<x-app-layout>
    <div class="py-6" x-data="{
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
        isOverStock(line) {
            if (!this.sourceLocationId || !line.item_id) return false;
            const avail = this.getAvailable(this.sourceLocationId, line.item_id);
            const qty = parseInt(line.quantity || 0);
            return qty > avail || avail <= 0;
        },
        hasAnyOverStock() {
            return this.lines.some(l => this.isOverStock(l));
        },
        canSubmit() {
            if (!this.sourceLocationId || !this.destinationLocationId) return false;
            if (this.sourceLocationId === this.destinationLocationId) return false;
            if (!this.lines.length) return false;
            if (this.lines.some(l => !l.item_id || !l.quantity || parseInt(l.quantity) <= 0)) return false;
            return !this.hasAnyOverStock();
        }
    }"
    @open-new-transfer-modal.window="newTransferModal = true"
    @keydown.escape.window="newTransferModal = false"
    >
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Header with Action Button --}}
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-neutral-200 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">Internal Logistics</span>
                        <span class="text-xs text-neutral-500">• In-Transit Virtual Buffer &amp; Discrepancy Tracking</span>
                    </div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Stock Transfers</h2>
                    <p class="text-sm text-neutral-600">
                        Inter-facility and inter-department inventory movements with in-transit buffer accounting and transit damage/loss logging.
                    </p>
                </div>
                <div class="flex items-center gap-3">
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
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $transfers->links() }}
                    </div>
                @endif
            </div>

        </div>

        {{-- INITIATE TRANSFER MODAL --}}
        @can(\App\Enums\Permission::TransferStock->value)
        <div x-show="newTransferModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="newTransferModal = false"></div>

                <div class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.transfers.store') }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Initiate Inter-Location Stock Transfer</h3>
                                    <p class="text-xs text-neutral-500">Dispatch inventory into the virtual In-Transit location buffer.</p>
                                </div>
                            </div>

                            {{-- In-Modal Error Alert --}}
                            @if($errors->any())
                                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs text-rose-800 shadow-xs">
                                    <div class="flex items-center gap-2 font-bold text-rose-900">
                                        <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span>Transfer Initiation Error:</span>
                                    </div>
                                    <ul class="mt-1.5 list-disc list-inside space-y-1">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Source Location (Origin)</label>
                                    <select name="source_location_id" x-model="sourceLocationId" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Origin Location --</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Destination Location (Target)</label>
                                    <select name="destination_location_id" x-model="destinationLocationId" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Target Location --</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}" :disabled="sourceLocationId == '{{ $loc->id }}'">
                                                {{ $loc->name }} ({{ $loc->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Transfer Notes / Reason</label>
                                <input type="text" name="notes" value="{{ old('notes') }}" placeholder="e.g. Ward replenishment, emergency transfer to satellite clinic"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            {{-- Line Items --}}
                            <div class="mt-6 border-t border-neutral-200 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-800">Transfer Items</h4>
                                    <button type="button" @click="addLine" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Add Another Item
                                    </button>
                                </div>

                                <div class="space-y-3 max-h-64 overflow-y-auto p-1">
                                    <template x-for="(line, idx) in lines" :key="idx">
                                        <div class="rounded-lg bg-neutral-50 p-3 border transition-colors"
                                             :class="isOverStock(line) ? 'border-rose-300 bg-rose-50/40' : 'border-neutral-200'">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1">
                                                    <label class="block text-[11px] font-semibold text-neutral-600 mb-1">Item to Transfer</label>
                                                    <select :name="'lines[' + idx + '][item_id]'" x-model="line.item_id" required
                                                            class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs">
                                                        <option value="">-- Select Item to Transfer --</option>
                                                        <template x-for="itm in itemsList" :key="itm.id">
                                                            <option :value="itm.id" 
                                                                    x-text="itm.name + (sourceLocationId ? ' (Origin Available: ' + getAvailable(sourceLocationId, itm.id) + ')' : ' (Total: ' + itm.quantity_on_hand + ')')">
                                                            </option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="w-36">
                                                    <label class="block text-[11px] font-semibold text-neutral-600 mb-1">Quantity</label>
                                                    <input type="number" :name="'lines[' + idx + '][quantity]'" x-model="line.quantity" min="1" 
                                                           :max="sourceLocationId && line.item_id ? getAvailable(sourceLocationId, line.item_id) : null"
                                                           required placeholder="Qty"
                                                           :class="isOverStock(line) ? 'border-rose-500 ring-1 ring-rose-500 bg-rose-50 text-rose-900' : 'border-neutral-300 focus:border-indigo-500 focus:ring-indigo-500'"
                                                           class="block w-full rounded-md shadow-sm text-xs text-right font-medium">
                                                </div>
                                                <div class="pt-5">
                                                    <button type="button" @click="removeLine(idx)" :disabled="lines.length === 1"
                                                            class="rounded p-1.5 text-neutral-400 hover:text-rose-600 disabled:opacity-30" title="Remove line">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Instant Stock Availability & Over-quantity Validation Feedback --}}
                                            <div class="mt-2 text-xs">
                                                <template x-if="!sourceLocationId">
                                                    <span class="text-amber-600 flex items-center gap-1 font-medium">
                                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        Pumili muna ng Source Location (Origin) sa itaas para makita ang available stock.
                                                    </span>
                                                </template>
                                                <template x-if="sourceLocationId && line.item_id">
                                                    <div>
                                                        <template x-if="getAvailable(sourceLocationId, line.item_id) <= 0">
                                                            <div class="text-rose-600 font-semibold flex items-center gap-1.5">
                                                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                <span>Walang stock (0 units available) sa piniling origin location. Hindi ito pwedeng i-transfer.</span>
                                                            </div>
                                                        </template>
                                                        <template x-if="getAvailable(sourceLocationId, line.item_id) > 0 && parseInt(line.quantity || 0) > getAvailable(sourceLocationId, line.item_id)">
                                                            <div class="text-rose-600 font-semibold flex items-center gap-1.5">
                                                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                                <span>Kulang ang stock! Mayroon lamang <strong class="underline" x-text="getAvailable(sourceLocationId, line.item_id)"></strong> units sa origin, ngunit <strong x-text="line.quantity"></strong> units ang inilagay mo.</span>
                                                            </div>
                                                        </template>
                                                        <template x-if="getAvailable(sourceLocationId, line.item_id) > 0 && parseInt(line.quantity || 0) <= getAvailable(sourceLocationId, line.item_id)">
                                                            <div class="text-emerald-700 font-medium flex items-center gap-1.5">
                                                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                                <span>Sapat ang stock: <strong x-text="getAvailable(sourceLocationId, line.item_id)"></strong> units available sa origin.</span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-neutral-200">
                            <div>
                                <template x-if="hasAnyOverStock()">
                                    <p class="text-xs text-rose-600 font-semibold flex items-center gap-1.5">
                                        <svg class="h-4 w-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        Iwasto muna ang quantity: May item na lumagpas o walang stock sa Origin.
                                    </p>
                                </template>
                            </div>
                            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                                <button type="button" @click="newTransferModal = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        :disabled="!canSubmit()"
                                        :class="!canSubmit() ? 'opacity-50 cursor-not-allowed bg-neutral-400' : 'bg-indigo-600 hover:bg-indigo-700'"
                                        class="rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition">
                                    Dispatch to In-Transit Buffer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan

    </div>
</x-app-layout>
