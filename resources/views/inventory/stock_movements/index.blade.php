<x-app-layout>
    <div x-data="{
        newTransferModal: false,
        sourceLocationId: '{{ old('source_location_id', '') }}',
        destinationLocationId: '{{ old('destination_location_id', '') }}',
        itemsList: {{ Js::from($availableTransferItems ?? $items) }},
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
        <x-ui.page-header
            title="Stock Movements & Ledger"
            subtitle="Record quick stock in, stock out, departmental issuances, and returns, or review the complete movements ledger."
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory' => route('inventory.items'), 'Stock Movements & Transfers' => null]">
            <x-slot:actions>
                @can(\App\Enums\Permission::TransferStock->value)
                    <button type="button"
                            @click="newTransferModal = true"
                            id="btn-initiate-transfer-modal"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Initiate Stock Transfer
                    </button>
                @endcan
                <x-ui.button variant="secondary" :href="route('inventory.items')" icon="arrow-left">Back to Inventory</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Server Error Alert (Only rendered after failed server submission) --}}
        @if ($errors->any())
            <x-ui.alert variant="danger" title="This movement was not recorded" class="mb-4">
                <ul class="space-y-0.5 list-disc list-inside text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        {{-- Flash Feedback --}}
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs sm:text-sm text-emerald-800 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- RECORD QUICK MOVEMENT CARD --}}
        @if (! empty($movementTypes))
        <x-ui.card
            title="Record Quick Movement"
            subtitle="Record individual stock adjustments (Stock In, Stock Out, Departmental Issuances, and Returns). Balances update immediately."
            x-data="{
                type: '{{ old('movement_type', $movementTypes[0]->value ?? 'stock_out') }}',
                itemId: '{{ old('item_id', $items->first()?->id ?? '') }}',
                unitFilter: 'all',
                fromLocationId: '{{ old('from_location_id', '') }}',
                toLocationId: '{{ old('to_location_id', '') }}',
                issuedToLocationId: '{{ old('issued_to_location_id', '') }}',
                returnSupplierId: '{{ old('return_supplier_id', '') }}',
                quantity: '{{ old('quantity', '') }}',
                remarks: '{{ old('remarks', '') }}',
                confirmModalOpen: false,
                isSubmitting: false,

                itemsData: {{ Js::from($itemsData ?? []) }},
                locationsMap: {{ Js::from($locations->keyBy('id')) }},
                suppliersMap: {{ Js::from($suppliers->keyBy('id')) }},

                init() {
                    this.$watch('fromLocationId', () => {
                        if (this.isZeroStockAtSource) {
                            this.quantity = '';
                        }
                    });
                    this.$watch('itemId', () => {
                        if (this.isZeroStockAtSource) {
                            this.quantity = '';
                        }
                    });
                    this.$watch('type', () => {
                        if (this.isZeroStockAtSource) {
                            this.quantity = '';
                        }
                    });
                    this.$watch('unitFilter', (unit) => {
                        const select = document.getElementById('item_id');
                        if (!select) return;
                        let firstVisibleId = null;
                        let currentStillVisible = false;
                        Array.from(select.options).forEach(opt => {
                            if (!opt.value) return;
                            const itemUnit = (opt.getAttribute('data-unit') || '').toLowerCase();
                            const visible = (unit === 'all' || itemUnit === unit.toLowerCase());
                            opt.hidden = !visible;
                            opt.disabled = !visible;
                            if (visible) {
                                if (!firstVisibleId) firstVisibleId = opt.value;
                                if (opt.value == this.itemId) currentStillVisible = true;
                            }
                        });
                        if (!currentStillVisible && firstVisibleId) {
                            this.itemId = firstVisibleId;
                        }
                    });
                },

                get currentItem() {
                    return this.itemsData[this.itemId] || null;
                },
                get itemUnit() {
                    return this.currentItem ? this.currentItem.unit : 'unit';
                },
                get unitPlural() {
                    const u = (this.itemUnit || 'unit').toLowerCase();
                    if (u === 'box') return 'boxes';
                    if (u === 'glass') return 'glasses';
                    if (u.endsWith('s')) return u;
                    if (u.endsWith('ch') || u.endsWith('sh') || u.endsWith('x') || u.endsWith('z')) return u + 'es';
                    return u + 's';
                },
                get needsSource() {
                    return ['stock_out', 'issuance', 'return_to_supplier'].includes(this.type);
                },
                get needsDestination() {
                    return ['stock_in'].includes(this.type);
                },
                get needsRecipient() {
                    return this.type === 'issuance';
                },
                get needsSupplier() {
                    return this.type === 'return_to_supplier';
                },
                get availableStockAtSource() {
                    if (!this.currentItem || !this.fromLocationId) return 0;
                    return (this.currentItem.location_stocks && this.currentItem.location_stocks[this.fromLocationId])
                        ? parseInt(this.currentItem.location_stocks[this.fromLocationId])
                        : 0;
                },
                get currentStockAtDest() {
                    if (!this.currentItem || !this.toLocationId) return 0;
                    return (this.currentItem.location_stocks && this.currentItem.location_stocks[this.toLocationId])
                        ? parseInt(this.currentItem.location_stocks[this.toLocationId])
                        : 0;
                },
                get parsedQty() {
                    return parseInt(this.quantity) || 0;
                },
                maxRealisticQty: 999999,
                get isUnrealisticQuantity() {
                    return this.parsedQty > this.maxRealisticQty;
                },
                get isOverStock() {
                    if (!this.needsSource || !this.fromLocationId) return false;
                    return this.parsedQty > this.availableStockAtSource;
                },
                get isZeroStockAtSource() {
                    if (!this.needsSource || !this.fromLocationId) return false;
                    return this.availableStockAtSource <= 0;
                },
                get remainingStockAfterMovement() {
                    return Math.max(0, this.availableStockAtSource - this.parsedQty);
                },
                get projectedDestStock() {
                    return this.currentStockAtDest + this.parsedQty;
                },

                formatUnit(qty, unitName) {
                    const u = (unitName || this.itemUnit || 'unit').toLowerCase();
                    const count = parseInt(qty) || 0;
                    if (count === 1) return `1 ${u}`;
                    if (u === 'box') return `${count} boxes`;
                    if (u === 'glass') return `${count} glasses`;
                    if (u.endsWith('s')) return `${count} ${u}`;
                    if (u.endsWith('ch') || u.endsWith('sh') || u.endsWith('x') || u.endsWith('z')) return `${count} ${u}es`;
                    return `${count} ${u}s`;
                },

                get validationError() {
                    if (!this.itemId) return 'Please select an item.';
                    if (!this.type) return 'Please select a movement type.';
                    if (this.needsSource && !this.fromLocationId) return 'Please select a source location.';
                    if (this.needsSource && this.isZeroStockAtSource) return 'Cannot record movement: No available stock at the selected origin location.';
                    if (this.needsDestination && !this.toLocationId) return 'Please select a destination location.';
                    if (this.needsRecipient && !this.issuedToLocationId) return 'Please select the receiving department or ward.';
                    if (this.needsSupplier && !this.returnSupplierId) return 'Please select the return supplier.';
                    if (!this.quantity || this.parsedQty <= 0) return 'Please enter a valid quantity of at least 1.';
                    if (this.isUnrealisticQuantity) return `Quantity is unrealistically high (maximum ${this.maxRealisticQty.toLocaleString()} ${this.unitPlural}).`;
                    if (this.needsSource && this.isOverStock) {
                        const shortBy = this.parsedQty - this.availableStockAtSource;
                        return `Insufficient stock: Requested ${this.formatUnit(this.parsedQty)}, but only ${this.formatUnit(this.availableStockAtSource)} available (Short by ${this.formatUnit(shortBy)}).`;
                    }
                    return null;
                },

                get isValid() {
                    return this.validationError === null;
                },

                handleQuantityKeydown(e) {
                    if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Home', 'End'].includes(e.key)) return;
                    if (e.ctrlKey || e.metaKey || e.altKey) return;
                    if (!/^\d$/.test(e.key)) {
                        e.preventDefault();
                        return;
                    }
                    const currentVal = String(this.quantity || '');
                    const hasSelection = e.target.selectionStart !== e.target.selectionEnd;
                    if (currentVal.length >= 6 && !hasSelection) {
                        e.preventDefault();
                    }
                },

                handleQuantityInput(e) {
                    let val = e.target.value;
                    if (!val) {
                        this.quantity = '';
                        return;
                    }
                    val = val.replace(/\D/g, '');
                    if (val.length > 6) {
                        val = val.slice(0, 6);
                    }
                    if (parseInt(val, 10) > 999999) {
                        val = '999999';
                    }
                    this.quantity = val;
                    e.target.value = val;
                },

                openConfirmation() {
                    if (!this.isValid) {
                        if (this.needsSource && (!this.fromLocationId || this.isOverStock || this.parsedQty <= 0)) {
                            document.getElementById('movement_quantity')?.focus();
                        } else if (!this.quantity || this.parsedQty <= 0) {
                            document.getElementById('movement_quantity')?.focus();
                        }
                        return;
                    }
                    this.confirmModalOpen = true;
                },

                submitForm() {
                    if (!this.isValid || this.isSubmitting) return;
                    this.isSubmitting = true;
                    this.$refs.movementForm.submit();
                }
            }">
            <form method="POST" action="{{ route('inventory.stock-movements.store') }}" x-ref="movementForm" class="space-y-3.5">
                @csrf

                {{-- Row 1: Item with Filter Unit & Movement Type --}}
                <div class="grid gap-3.5 md:grid-cols-12 items-start">
                    {{-- Item & Optional Unit Filter Strictly Side-by-Side --}}
                    <div class="col-span-12 md:col-span-7 lg:col-span-8">
                        <label for="item_id" class="block text-xs font-semibold text-neutral-700 mb-1">
                            Item <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <select
                                    name="item_id"
                                    id="item_id"
                                    x-model="itemId"
                                    required
                                    :title="currentItem ? (currentItem.name + ' (' + currentItem.sku + ') — [' + currentItem.unit + ']') : 'Select item'"
                                    class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 truncate {{ $errors->has('item_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}"
                                >
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}"
                                                data-unit="{{ strtolower($item->unit ?: 'unit') }}"
                                                title="{{ $item->name }} ({{ $item->sku }}) — [{{ $item->unit ?: 'unit' }}]"
                                                @selected(old('item_id', $items->first()?->id) == $item->id)>
                                            {{ $item->name }} ({{ $item->sku }}) — [{{ $item->unit ?: 'unit' }}]
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if (!empty($availableUnits) && count($availableUnits) > 1)
                            <div class="w-28 sm:w-32 shrink-0">
                                <select
                                    id="unit_filter"
                                    x-model="unitFilter"
                                    title="Filter items by unit"
                                    class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-neutral-50 cursor-pointer"
                                >
                                    <option value="all">All Units</option>
                                    @foreach ($availableUnits as $u)
                                        <option value="{{ strtolower($u) }}">{{ ucfirst($u) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>
                        @error('item_id')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Movement Type Selector (Proportional ~35-40% width on desktop) --}}
                    <div class="col-span-12 md:col-span-5 lg:col-span-4">
                        <label for="movement_type" class="block text-xs font-semibold text-neutral-700 mb-1">
                            Movement Type <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="movement_type"
                            id="movement_type"
                            x-model="type"
                            required
                            class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('movement_type') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}"
                        >
                            @foreach ($movementTypes as $mType)
                                <option value="{{ $mType->value }}" @selected(old('movement_type', $movementTypes[0]->value) == $mType->value)>
                                    {{ $mType->label() }} — {{ $mType->hint() }}
                                </option>
                            @endforeach
                        </select>
                        @error('movement_type')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Row 2: Balanced Contextual Fields (Locations, Quantity, Remarks) --}}
                <div class="grid gap-3.5 md:grid-cols-12 items-start">
                    {{-- Source Location (For Outbound: stock_out, issuance, return_to_supplier) --}}
                    <div x-show="needsSource" x-cloak
                         :class="{
                             'col-span-12 md:col-span-5 lg:col-span-5': type === 'stock_out',
                             'col-span-12 md:col-span-4 lg:col-span-4': type === 'issuance' || type === 'return_to_supplier'
                         }">
                        <label for="from_location_id" class="block text-xs font-semibold text-neutral-700 mb-1">
                            From Location (Source) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="from_location_id"
                            id="from_location_id"
                            x-model="fromLocationId"
                            x-bind:required="needsSource"
                            class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 truncate {{ $errors->has('from_location_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}"
                        >
                            <option value="">-- Select Source Location --</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}"
                                        title="{{ $location->name }} ({{ $location->code }})"
                                        @selected(old('from_location_id') == $location->id)>
                                    {{ $location->name }} ({{ $location->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('from_location_id')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Destination Location (For Inbound: stock_in) --}}
                    <div x-show="needsDestination" x-cloak class="col-span-12 md:col-span-5 lg:col-span-5">
                        <label for="to_location_id" class="block text-xs font-semibold text-neutral-700 mb-1">
                            To Location (Destination) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="to_location_id"
                            id="to_location_id"
                            x-model="toLocationId"
                            x-bind:required="needsDestination"
                            class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 truncate {{ $errors->has('to_location_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}"
                        >
                            <option value="">-- Select Destination Location --</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}"
                                        title="{{ $location->name }} ({{ $location->code }})"
                                        @selected(old('to_location_id') == $location->id)>
                                    {{ $location->name }} ({{ $location->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('to_location_id')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Issued To Department/Ward (For issuance) --}}
                    <div x-show="needsRecipient" x-cloak class="col-span-12 md:col-span-4 lg:col-span-4">
                        <label for="issued_to_location_id" class="block text-xs font-semibold text-neutral-700 mb-1">
                            Issued To (Ward / Dept) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="issued_to_location_id"
                            id="issued_to_location_id"
                            x-model="issuedToLocationId"
                            x-bind:required="needsRecipient"
                            class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 truncate {{ $errors->has('issued_to_location_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}"
                        >
                            <option value="">-- Select Receiving Ward / Dept --</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}"
                                        title="{{ $location->name }} ({{ $location->code }})"
                                        @selected(old('issued_to_location_id') == $location->id)>
                                    {{ $location->name }} ({{ $location->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('issued_to_location_id')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Return Supplier (For return_to_supplier) --}}
                    <div x-show="needsSupplier" x-cloak class="col-span-12 md:col-span-4 lg:col-span-4">
                        <label for="return_supplier_id" class="block text-xs font-semibold text-neutral-700 mb-1">
                            Return To Supplier <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="return_supplier_id"
                            id="return_supplier_id"
                            x-model="returnSupplierId"
                            x-bind:required="needsSupplier"
                            class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 truncate {{ $errors->has('return_supplier_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}"
                        >
                            <option value="">-- Select Supplier --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" title="{{ $supplier->name }}" @selected(old('return_supplier_id') == $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('return_supplier_id')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Quantity with Compact Attached Unit Badge --}}
                    <div :class="{
                        'col-span-12 sm:col-span-5 md:col-span-3 lg:col-span-3': type === 'stock_out' || type === 'stock_in',
                        'col-span-12 md:col-span-4 lg:col-span-4': type === 'issuance' || type === 'return_to_supplier'
                    }">
                        <label for="movement_quantity" class="block text-xs font-semibold text-neutral-700 mb-1">
                            Quantity <span class="text-rose-500">*</span>
                            <span class="text-[11px] font-normal text-neutral-500" x-text="'(in ' + unitPlural + ')'"></span>
                        </label>
                        <div class="relative flex items-center">
                            <input
                                type="number"
                                name="quantity"
                                id="movement_quantity"
                                x-model="quantity"
                                min="1"
                                max="999999"
                                maxlength="6"
                                @keydown="handleQuantityKeydown($event)"
                                @input="handleQuantityInput($event)"
                                :disabled="isZeroStockAtSource"
                                required
                                :placeholder="isZeroStockAtSource ? '0' : '0'"
                                :class="{
                                    'opacity-60 bg-neutral-100 cursor-not-allowed border-neutral-300 text-neutral-400': isZeroStockAtSource,
                                    'border-rose-500 ring-1 ring-rose-500 bg-rose-50/30 text-rose-900': !isZeroStockAtSource && (isOverStock || isUnrealisticQuantity || {{ $errors->has('quantity') ? 'true' : 'false' }}),
                                    'border-emerald-500 focus:border-emerald-500 focus:ring-emerald-500': !isZeroStockAtSource && parsedQty > 0 && !isOverStock && !isUnrealisticQuantity && !{{ $errors->has('quantity') ? 'true' : 'false' }},
                                    'border-neutral-300 focus:border-primary-500 focus:ring-primary-500': !isZeroStockAtSource && parsedQty === 0
                                }"
                                class="block w-full rounded-lg shadow-2xs text-xs font-semibold py-2 pl-3 pr-16 transition"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none">
                                <span class="rounded bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-600 border border-neutral-200" x-text="itemUnit"></span>
                            </div>
                        </div>

                        {{-- Realistic Limit Warning Feedback --}}
                        <template x-if="isUnrealisticQuantity">
                            <div class="mt-1.5 rounded-md border border-rose-300 bg-rose-50 p-2 text-[11px] text-rose-800 space-y-0.5">
                                <div class="flex items-center gap-1 font-bold text-rose-900">
                                    <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <span>Unrealistic Quantity Limit</span>
                                </div>
                                <div class="text-rose-700">
                                    <span>Quantity cannot exceed <strong>999,999 <span x-text="unitPlural"></span></strong> per transaction.</span>
                                </div>
                            </div>
                        </template>

                        {{-- Server Error on Quantity --}}
                        @error('quantity')
                            <p class="mt-1 text-xs text-rose-600 font-semibold flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror

                        {{-- Real-Time Client-Side Stock Availability Feedback for Outbound --}}
                        <template x-if="needsSource && fromLocationId">
                            <div class="mt-1.5">
                                <template x-if="isZeroStockAtSource">
                                    <div class="rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[11px] text-rose-800 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        <span><strong>No available stock</strong> at origin location. Input disabled.</span>
                                    </div>
                                </template>

                                <template x-if="!isZeroStockAtSource && !isOverStock && parsedQty <= 0">
                                    <div class="flex items-center justify-between text-[11px] text-neutral-600 bg-neutral-50 rounded-md px-2 py-1 border border-neutral-200">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Available:</span>
                                            <strong class="font-semibold text-neutral-900" x-text="formatUnit(availableStockAtSource)"></strong>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!isZeroStockAtSource && !isOverStock && parsedQty > 0">
                                    <div class="flex items-center justify-between text-[11px] bg-emerald-50/90 text-emerald-800 rounded-md px-2 py-1 border border-emerald-200">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <span>Avail: <strong x-text="formatUnit(availableStockAtSource)"></strong></span>
                                        </div>
                                        <div>
                                            <span>Rem: <strong class="font-bold text-emerald-900" x-text="formatUnit(remainingStockAfterMovement)"></strong></span>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!isZeroStockAtSource && isOverStock">
                                    <div class="rounded-md border border-rose-300 bg-rose-50 p-2 text-[11px] text-rose-800 space-y-0.5">
                                        <div class="flex items-center gap-1 font-bold text-rose-900">
                                            <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                            <span>Insufficient stock!</span>
                                        </div>
                                        <div class="text-rose-700">
                                            <span><strong x-text="formatUnit(availableStockAtSource)"></strong> available; requested <strong x-text="formatUnit(parsedQty)"></strong> (Short by <strong x-text="formatUnit(parsedQty - availableStockAtSource)"></strong>).</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Stock In Destination Impact --}}
                        <template x-if="needsDestination && toLocationId">
                            <div class="mt-1.5 flex items-center justify-between text-[11px] bg-indigo-50/80 text-indigo-800 rounded-md px-2 py-1 border border-indigo-200">
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                                    <span>Current: <strong x-text="formatUnit(currentStockAtDest)"></strong></span>
                                </div>
                                <template x-if="parsedQty > 0">
                                    <div>
                                        <span>New: <strong class="font-bold text-indigo-900" x-text="formatUnit(projectedDestStock)"></strong></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Remarks / Notes (Spans appropriately) --}}
                    <div :class="{
                        'col-span-12 sm:col-span-7 md:col-span-4 lg:col-span-4': type === 'stock_out' || type === 'stock_in',
                        'col-span-12': type === 'issuance' || type === 'return_to_supplier'
                    }">
                        <label for="remarks" class="block text-xs font-semibold text-neutral-700 mb-1">
                            Remarks / Notes <span class="text-[11px] font-normal text-neutral-400">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            name="remarks"
                            id="remarks"
                            x-model="remarks"
                            value="{{ old('remarks') }}"
                            :placeholder="needsSupplier
                                ? 'e.g. Defective batch return'
                                : (needsRecipient ? 'e.g. Ward replenishment, night shift issue' : 'e.g. Routine issuance, stock adjustment')"
                            class="block w-full rounded-lg border-neutral-300 shadow-2xs text-xs font-medium text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 py-2 px-3"
                        />
                        <p class="mt-1 text-[11px] text-neutral-400">Contextual notes recorded in ledger.</p>
                    </div>
                </div>

                {{-- Submit Action Row --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-neutral-100">
                    <div>
                        <template x-if="!isValid && validationError && !isZeroStockAtSource">
                            <p class="text-xs text-rose-600 font-medium flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span x-text="validationError"></span>
                            </p>
                        </template>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <button
                            type="button"
                            @click="openConfirmation()"
                            :disabled="!isValid"
                            :class="!isValid ? 'opacity-50 cursor-not-allowed bg-neutral-400' : 'bg-primary-600 hover:bg-primary-700 shadow-xs'"
                            id="btn-save-movement"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Save Movement</span>
                        </button>
                    </div>
                </div>
            </form>

            {{-- COMPACT CONFIRMATION MODAL --}}
            <div x-show="confirmModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="flex min-h-screen items-center justify-center p-4 text-center">
                    <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="if (!isSubmitting) confirmModalOpen = false"></div>

                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-auto z-10 border border-neutral-200">
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3 pb-3 border-b border-neutral-100">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-neutral-900">Confirm Stock Movement</h3>
                                    <p class="text-xs text-neutral-500">Are you sure you want to record this stock movement? Please review the details below.</p>
                                </div>
                            </div>

                            {{-- Structured Transaction Summary --}}
                            <div class="mt-4 space-y-2.5 text-xs">
                                <div class="flex justify-between items-start py-1.5 border-b border-neutral-100">
                                    <span class="text-neutral-500 font-medium">Item:</span>
                                    <div class="text-right">
                                        <span class="font-bold text-neutral-900" x-text="currentItem ? currentItem.name : '—'"></span>
                                        <span class="block text-[11px] text-neutral-400" x-text="currentItem ? currentItem.sku : ''"></span>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center py-1.5 border-b border-neutral-100">
                                    <span class="text-neutral-500 font-medium">Movement Type:</span>
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wider"
                                          :class="{
                                              'bg-emerald-100 text-emerald-800': type === 'stock_in',
                                              'bg-amber-100 text-amber-800': type === 'stock_out',
                                              'bg-blue-100 text-blue-800': type === 'issuance',
                                              'bg-rose-100 text-rose-800': type === 'return_to_supplier'
                                          }"
                                          x-text="type.replace(/_/g, ' ')">
                                    </span>
                                </div>

                                <template x-if="needsSource && fromLocationId">
                                    <div class="flex justify-between items-center py-1.5 border-b border-neutral-100">
                                        <span class="text-neutral-500 font-medium">From Location:</span>
                                        <span class="font-semibold text-neutral-900" x-text="locationsMap[fromLocationId] ? locationsMap[fromLocationId].name + ' (' + locationsMap[fromLocationId].code + ')' : '—'"></span>
                                    </div>
                                </template>

                                <template x-if="needsDestination && toLocationId">
                                    <div class="flex justify-between items-center py-1.5 border-b border-neutral-100">
                                        <span class="text-neutral-500 font-medium">To Location:</span>
                                        <span class="font-semibold text-neutral-900" x-text="locationsMap[toLocationId] ? locationsMap[toLocationId].name + ' (' + locationsMap[toLocationId].code + ')' : '—'"></span>
                                    </div>
                                </template>

                                <template x-if="needsRecipient && issuedToLocationId">
                                    <div class="flex justify-between items-center py-1.5 border-b border-neutral-100">
                                        <span class="text-neutral-500 font-medium">Issued To (Ward/Dept):</span>
                                        <span class="font-semibold text-neutral-900" x-text="locationsMap[issuedToLocationId] ? locationsMap[issuedToLocationId].name : '—'"></span>
                                    </div>
                                </template>

                                <template x-if="needsSupplier && returnSupplierId">
                                    <div class="flex justify-between items-center py-1.5 border-b border-neutral-100">
                                        <span class="text-neutral-500 font-medium">Return Supplier:</span>
                                        <span class="font-semibold text-neutral-900" x-text="suppliersMap[returnSupplierId] ? suppliersMap[returnSupplierId].name : '—'"></span>
                                    </div>
                                </template>

                                <div class="flex justify-between items-center py-1.5 border-b border-neutral-100">
                                    <span class="text-neutral-500 font-medium">Quantity to Move:</span>
                                    <span class="font-bold text-sm text-neutral-900" x-text="formatUnit(parsedQty)"></span>
                                </div>

                                <template x-if="needsSource && fromLocationId">
                                    <div class="rounded-lg bg-neutral-50 p-2.5 border border-neutral-200 flex justify-between items-center text-xs">
                                        <span class="text-neutral-600">Stock Impact:</span>
                                        <span class="font-medium text-neutral-800">
                                            <span x-text="formatUnit(availableStockAtSource)"></span>
                                            &rarr;
                                            <strong class="text-emerald-700 font-bold" x-text="formatUnit(remainingStockAfterMovement)"></strong> remaining
                                        </span>
                                    </div>
                                </template>

                                <template x-if="needsDestination && toLocationId">
                                    <div class="rounded-lg bg-neutral-50 p-2.5 border border-neutral-200 flex justify-between items-center text-xs">
                                        <span class="text-neutral-600">Stock Impact:</span>
                                        <span class="font-medium text-neutral-800">
                                            <span x-text="formatUnit(currentStockAtDest)"></span>
                                            &rarr;
                                            <strong class="text-indigo-700 font-bold" x-text="formatUnit(projectedDestStock)"></strong> new balance
                                        </span>
                                    </div>
                                </template>

                                <template x-if="remarks && remarks.trim().length > 0">
                                    <div class="flex justify-between items-start py-1.5">
                                        <span class="text-neutral-500 font-medium">Remarks:</span>
                                        <span class="text-neutral-700 italic max-w-xs text-right" x-text="remarks"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" 
                                    @click="confirmModalOpen = false" 
                                    :disabled="isSubmitting"
                                    class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" 
                                    @click="submitForm()" 
                                    :disabled="isSubmitting"
                                    id="btn-confirm-movement-submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </template>
                                <span x-text="isSubmitting ? 'Recording Movement...' : 'Confirm Movement'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>
        @endif

        {{-- MOVEMENT HISTORY (FULLY RESPONSIVE - ZERO HORIZONTAL SCROLL) --}}
        <x-ui.card title="Movement History" :subtitle="$movements->count().' recorded'" :padding="false">
            @if ($movements->isEmpty())
                <div class="p-8 text-center text-neutral-500">
                    <svg class="mx-auto h-8 w-8 text-neutral-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <p class="text-sm font-semibold text-neutral-800">No movements yet</p>
                    <p class="text-xs text-neutral-400 mt-0.5">Recorded stock in, stock out and transfers will appear here.</p>
                </div>
            @else
                {{-- Desktop & Tablet View (Proportional Columns, No Horizontal Scrollbar) --}}
                <div class="hidden md:block w-full overflow-hidden">
                    <table class="w-full table-fixed divide-y divide-neutral-200 text-xs">
                        <thead class="bg-neutral-50 text-neutral-600 font-semibold border-b border-neutral-200">
                            <tr>
                                <th scope="col" class="w-[30%] px-4 py-3 text-left">Item &amp; Type</th>
                                <th scope="col" class="w-[12%] px-3 py-3 text-right">Quantity</th>
                                <th scope="col" class="w-[38%] px-4 py-3 text-left">Logistics Route &amp; Remarks</th>
                                <th scope="col" class="w-[20%] px-4 py-3 text-right">Recorded</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            @foreach ($movements as $movement)
                                @php
                                    $destinationName = null;
                                    if ($movement->toLocation) {
                                        $destinationName = $movement->toLocation->name;
                                    } elseif ($movement->reference) {
                                        $destinationName = $movement->reference->name ?? class_basename($movement->reference);
                                    }
                                @endphp
                                <tr class="hover:bg-neutral-50/70 transition-colors">
                                    {{-- Item & Movement Type --}}
                                    <td class="px-4 py-3 align-top">
                                        <div class="min-w-0">
                                            <span class="font-semibold text-neutral-900 truncate block" title="{{ $movement->item?->name ?? '—' }}">
                                                {{ $movement->item?->name ?? '—' }}
                                            </span>
                                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                <x-ui.badge :status="$movement->movement_type->value">
                                                    {{ $movement->movement_type->label() }}
                                                </x-ui.badge>
                                                @if ($movement->item?->sku)
                                                    <span class="text-[11px] font-mono text-neutral-400 truncate">{{ $movement->item->sku }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Quantity --}}
                                    <td class="px-3 py-3 text-right align-top">
                                        <div class="font-bold tabular-nums text-neutral-900 text-sm">
                                            {{ number_format($movement->quantity) }}
                                        </div>
                                        <div class="text-[11px] text-neutral-400 font-medium truncate">
                                            {{ $movement->item?->unit ?: 'unit' }}(s)
                                        </div>
                                    </td>

                                    {{-- Logistics Route (From -> To) & Remarks --}}
                                    <td class="px-4 py-3 align-top">
                                        <div class="min-w-0 space-y-1">
                                            {{-- Route Indicator --}}
                                            @if ($movement->fromLocation && $destinationName)
                                                <div class="flex items-center gap-1.5 text-xs text-neutral-700">
                                                    <span class="font-medium text-neutral-800 truncate" title="{{ $movement->fromLocation->name }}">
                                                        {{ $movement->fromLocation->name }}
                                                    </span>
                                                    <svg class="w-3.5 h-3.5 text-neutral-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                                    <span class="font-semibold text-neutral-900 truncate" title="{{ $destinationName }}">
                                                        {{ $destinationName }}
                                                    </span>
                                                </div>
                                            @elseif ($destinationName)
                                                <div class="flex items-center gap-1 text-xs text-indigo-700">
                                                    <svg class="w-3.5 h-3.5 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                                    <span class="font-semibold truncate" title="{{ $destinationName }}">{{ $destinationName }}</span>
                                                </div>
                                            @elseif ($movement->fromLocation)
                                                <div class="flex items-center gap-1 text-xs text-rose-700">
                                                    <svg class="w-3.5 h-3.5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                                    <span class="font-semibold truncate" title="{{ $movement->fromLocation->name }}">{{ $movement->fromLocation->name }}</span>
                                                </div>
                                            @else
                                                <span class="text-neutral-400">—</span>
                                            @endif

                                            {{-- Remarks Context --}}
                                            @if ($movement->remarks)
                                                <p class="text-[11px] text-neutral-500 truncate italic" title="{{ $movement->remarks }}">
                                                    {{ $movement->remarks }}
                                                </p>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Recorded Timestamp & User --}}
                                    <td class="px-4 py-3 text-right align-top">
                                        <span class="text-xs font-medium text-neutral-800 block whitespace-nowrap">
                                            {{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}
                                        </span>
                                        @if ($movement->user)
                                            <span class="text-[11px] text-neutral-400 truncate block mt-0.5" title="{{ $movement->user->name }}">
                                                {{ $movement->user->name }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Feed View (Fluid Cards - Zero Horizontal Scroll) --}}
                <div class="block md:hidden divide-y divide-neutral-100">
                    @foreach ($movements as $movement)
                        @php
                            $destinationName = null;
                            if ($movement->toLocation) {
                                $destinationName = $movement->toLocation->name;
                            } elseif ($movement->reference) {
                                $destinationName = $movement->reference->name ?? class_basename($movement->reference);
                            }
                        @endphp
                        <div class="p-4 space-y-2 hover:bg-neutral-50/70 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-neutral-900 text-xs truncate" title="{{ $movement->item?->name }}">
                                        {{ $movement->item?->name ?? '—' }}
                                    </h4>
                                    @if ($movement->item?->sku)
                                        <span class="text-[10px] font-mono text-neutral-400">{{ $movement->item->sku }}</span>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-bold text-xs text-neutral-900 tabular-nums">{{ number_format($movement->quantity) }}</span>
                                    <span class="text-[10px] text-neutral-500">{{ $movement->item?->unit ?: 'unit' }}(s)</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap text-xs">
                                <x-ui.badge :status="$movement->movement_type->value">
                                    {{ $movement->movement_type->label() }}
                                </x-ui.badge>

                                @if ($movement->fromLocation && $destinationName)
                                    <span class="text-[11px] text-neutral-600 truncate flex items-center gap-1">
                                        <span>{{ $movement->fromLocation->name }}</span>
                                        <span>&rarr;</span>
                                        <strong>{{ $destinationName }}</strong>
                                    </span>
                                @elseif ($destinationName)
                                    <span class="text-[11px] text-indigo-700 truncate">
                                        &rarr; <strong>{{ $destinationName }}</strong>
                                    </span>
                                @elseif ($movement->fromLocation)
                                    <span class="text-[11px] text-rose-700 truncate">
                                        <strong>{{ $movement->fromLocation->name }}</strong> &rarr;
                                    </span>
                                @endif
                            </div>

                            @if ($movement->remarks)
                                <p class="text-[11px] text-neutral-500 italic truncate" title="{{ $movement->remarks }}">
                                    {{ $movement->remarks }}
                                </p>
                            @endif

                            <div class="flex items-center justify-between text-[10px] text-neutral-400 pt-1 border-t border-neutral-100">
                                <span>{{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}</span>
                                <span>{{ $movement->user?->name ?? 'System' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        @include('inventory.transfers.partials.initiate_modal', ['locations' => $transferLocations ?? $locations])
    </div>
</x-app-layout>
