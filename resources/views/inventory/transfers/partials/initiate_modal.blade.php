{{-- INITIATE TRANSFER MODAL (Unified Single Transfer Form) --}}
@can(\App\Enums\Permission::TransferStock->value)
<div x-show="newTransferModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="flex min-h-screen items-center justify-center p-4 text-center">
        <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="newTransferModal = false"></div>

        <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-auto z-10">
            <form action="{{ route('inventory.transfers.store') }}" method="POST"
                  data-confirm-title="Dispatch stock transfer"
                  data-confirm-message="Are you sure you want to dispatch this stock transfer? Items will immediately be deducted from the origin location and placed in-transit."
                  data-confirm-label="Dispatch Transfer">
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
                            <select name="source_location_id" x-model="sourceLocationId" @change="onSourceLocationChange()" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- Select Origin Location --</option>
                                @foreach($sourceLocations ?? $locations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})@if($loc->status === 'inactive') [INACTIVE - Outbound Only]@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Destination Location (Target)</label>
                            <select name="destination_location_id" x-model="destinationLocationId" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- Select Target Location --</option>
                                @foreach($destinationLocations ?? $locations as $loc)
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
                            <button type="button" @click="addLine" :disabled="!sourceLocationId || getAvailableItems().length === 0" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-40 disabled:cursor-not-allowed">
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
                                            <select :name="'lines[' + idx + '][item_id]'" x-model="line.item_id" :disabled="!sourceLocationId" required
                                                    class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs disabled:bg-neutral-100 disabled:text-neutral-400">
                                                <template x-if="!sourceLocationId">
                                                    <option value="">-- Select an Origin Location first --</option>
                                                </template>
                                                <template x-if="sourceLocationId && getAvailableItems().length === 0">
                                                    <option value="">-- No available stock at selected Origin --</option>
                                                </template>
                                                <template x-if="sourceLocationId && getAvailableItems().length > 0">
                                                    <option value="">-- Select Item to Transfer --</option>
                                                </template>
                                                <template x-for="itm in getAvailableItems()" :key="itm.id">
                                                    <option :value="itm.id" 
                                                            x-text="itm.name + ' (Available: ' + getAvailable(sourceLocationId, itm.id) + ')'">
                                                    </option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="w-36">
                                            <label class="block text-[11px] font-semibold text-neutral-600 mb-1">Quantity</label>
                                            <input type="number" :name="'lines[' + idx + '][quantity]'" x-model="line.quantity" min="1" max="999999"
                                                    maxlength="6"
                                                    @keydown="handleLineQtyKeydown($event, line)"
                                                    @input="handleLineQtyInput($event, line)"
                                                    :disabled="!line.item_id"
                                                    required placeholder="Qty"
                                                    :class="isOverStock(line) ? 'border-rose-500 ring-1 ring-rose-500 bg-rose-50 text-rose-900' : 'border-neutral-300 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-neutral-100'"
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
                                                Select a Source Location (Origin) above to view available items.
                                            </span>
                                        </template>
                                        <template x-if="sourceLocationId && getAvailableItems().length === 0">
                                            <div class="text-rose-600 font-semibold flex items-center gap-1.5">
                                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                <span>No stock available for any items at the selected origin location.</span>
                                            </div>
                                        </template>
                                        <template x-if="sourceLocationId && line.item_id">
                                            <div>
                                                <template x-if="parseInt(line.quantity || 0) > 999999">
                                                    <div class="text-rose-600 font-semibold flex items-center gap-1.5">
                                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                        <span>Unrealistic quantity! Maximum limit is <strong>999,999</strong> units.</span>
                                                    </div>
                                                </template>
                                                <template x-if="parseInt(line.quantity || 0) <= 999999 && getAvailable(sourceLocationId, line.item_id) > 0 && parseInt(line.quantity || 0) > getAvailable(sourceLocationId, line.item_id)">
                                                    <div class="text-rose-600 font-semibold flex items-center gap-1.5">
                                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                        <span>Insufficient stock! Only <strong class="underline" x-text="getAvailable(sourceLocationId, line.item_id)"></strong> units available at origin, but you requested <strong x-text="line.quantity"></strong> units.</span>
                                                    </div>
                                                </template>
                                                <template x-if="parseInt(line.quantity || 0) <= 999999 && getAvailable(sourceLocationId, line.item_id) > 0 && parseInt(line.quantity || 0) <= getAvailable(sourceLocationId, line.item_id)">
                                                    <div class="text-emerald-700 font-medium flex items-center gap-1.5">
                                                        <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                        <span>Stock available: <strong x-text="getAvailable(sourceLocationId, line.item_id)"></strong> units at origin.</span>
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
                                Please correct the quantity: An item exceeds available stock or has zero inventory at the Origin.
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
