<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded-md bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-800">Inbound Receiving</span>
                <span class="text-xs text-neutral-500">Three-Way Matching &amp; DOH GSDP Quarantine Protocol</span>
            </div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Dock Receiving &amp; Goods Receipt Notes</h2>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="{
        showReceiveModal: false,
        selectedPo: null,
        actualSupplierId: '',
        poLines: [],
        openOrders: {{ Js::from($openPurchaseOrders) }},
        selectPo(poId) {
            this.selectedPo = this.openOrders.find(p => p.id == poId);
            this.actualSupplierId = '';
            if (!this.selectedPo) {
                this.poLines = [];
                return;
            }
            let raw = [];
            if (Array.isArray(this.selectedPo.lines) && this.selectedPo.lines.length > 0) {
                raw = this.selectedPo.lines;
            } else if (this.selectedPo.item_id) {
                raw = [{
                    id: this.selectedPo.id,
                    po_line_id: this.selectedPo.id,
                    item_id: this.selectedPo.item_id,
                    item: this.selectedPo.item || null,
                    ordered_quantity: this.selectedPo.quantity,
                    received_quantity: 0,
                    unit_price: this.selectedPo.unit_cost,
                    total_line_amount: this.selectedPo.total_amount,
                    purchase_unit: this.selectedPo.purchase_unit,
                    conversion_factor: this.selectedPo.conversion_factor,
                }];
            }
            this.poLines = raw.filter(l => ((l.ordered_quantity || 0) - (l.received_quantity || 0) + (l.rejected_quantity || 0)) > 0).map(l => {
                const open = Math.max(0, (l.ordered_quantity || 0) - (l.received_quantity || 0) + (l.rejected_quantity || 0));
                return {
                    ...l,
                    open_quantity: open,
                    received_quantity: open,
                    item_condition: 'good',
                    discrepancy_type: '',
                    discrepancy_action: 'quarantine',
                    discrepancy_notes: '',
                    batch_number: '',
                    lot_number: '',
                    expiry_date: '',
                    manufactured_date: '',
                    serial_number: '',
                    actual_sku: '',
                    actual_purchase_unit: '',
                };
            });
        }
    }" x-init="if ({{ Js::from(request()->query('purchase_order_id')) }}) { selectPo({{ Js::from(request()->query('purchase_order_id')) }}); showReceiveModal = !!selectedPo; }">

            {{-- SWS Consolidated Workflow Navigation --}}
            @include('inventory.warehousing.partials.workflow_nav')

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
                        <span>Receiving Validation Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Summary Metrics --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Open Purchase Orders</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ count($openPurchaseOrders) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Awaiting dock fulfillment</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Goods Receipts (Total)</p>
                    <p class="mt-2 text-2xl font-bold text-primary-600">{{ $goodsReceipts->total() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Documented delivery intake</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Receipt Limit</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">PO balance</p>
                    <p class="mt-1 text-xs text-neutral-500">Replacements permitted after QC rejection</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Default Inbound Route</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600">Quarantine</p>
                    <p class="mt-1 text-xs text-neutral-500">Mandatory QA assay before use</p>
                </div>
            </div>

            {{-- Open Orders Awaiting Receipt Section --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Approved Purchase Orders Awaiting Delivery</h3>
                        <p class="text-xs text-neutral-500">Select an authorized order to record physical dock arrival and generate a Goods Receipt Note.</p>
                    </div>
                </div>
                <div class="overflow-x-auto hims-table-scroll">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">PO Number</th>
                                <th class="px-6 py-3 font-medium">Supplier</th>
                                <th class="px-6 py-3 font-medium">Order Items</th>
                                <th class="px-6 py-3 font-medium">Total Encumbered</th>
                                <th class="px-6 py-3 font-medium">Fulfillment Status</th>
                                <th class="px-6 py-3 font-medium text-right hims-sticky-actions min-w-[170px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($openPurchaseOrders as $po)
                                <tr class="hover:bg-neutral-50/75 transition-colors">
                                    <td class="px-6 py-4 font-mono font-medium text-primary-700">
                                        {{ $po->po_number }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-neutral-900">
                                        {{ $po->supplier?->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <div class="space-y-1.5">
                                            @forelse($po->lines as $l)
                                                @php
                                                    $lineUnit = $l->purchase_unit ?: ($l->item?->unit ?: 'unit');
                                                @endphp
                                                <div class="rounded border border-neutral-100 bg-neutral-50/70 p-2">
                                                    <div class="font-medium text-neutral-900">{{ $l->item?->name ?? 'Item' }}</div>
                                                    <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-neutral-600 mt-1">
                                                        <span>Qty: <strong class="text-neutral-800">{{ $l->ordered_quantity }} {{ \Illuminate\Support\Str::plural($lineUnit, $l->ordered_quantity) }}</strong></span>
                                                        <span>&bull;</span>
                                                        <span>Price: <strong class="font-mono text-neutral-800">₱{{ number_format((float) $l->unit_price, 2) }}/{{ $lineUnit }}</strong></span>
                                                        <span>&bull;</span>
                                                        <span>Total: <strong class="font-mono text-primary-700">₱{{ number_format($l->lineTotal(), 2) }}</strong></span>
                                                    </div>
                                                    @if($l->conversionFactor() > 1)
                                                        <div class="text-[10px] text-neutral-500 font-mono mt-0.5">({{ $l->conversionDisplay() }} · ≈ {{ number_format($l->orderedBaseQuantity()) }} base units)</div>
                                                    @endif
                                                </div>
                                            @empty
                                                @php
                                                    $singleUnit = $po->purchase_unit ?: ($po->item?->unit ?: 'unit');
                                                    $singleUnitPrice = $po->quantity > 0 ? ($po->unit_cost ?: round($po->total_amount / $po->quantity, 2)) : 0;
                                                @endphp
                                                <div class="rounded border border-neutral-100 bg-neutral-50/70 p-2">
                                                    <div class="font-medium text-neutral-900">{{ $po->item?->name ?? 'Item' }}</div>
                                                    <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-neutral-600 mt-1">
                                                        <span>Qty: <strong class="text-neutral-800">{{ $po->quantity }} {{ \Illuminate\Support\Str::plural($singleUnit, $po->quantity) }}</strong></span>
                                                        <span>&bull;</span>
                                                        <span>Price: <strong class="font-mono text-neutral-800">₱{{ number_format($singleUnitPrice, 2) }}/{{ $singleUnit }}</strong></span>
                                                        <span>&bull;</span>
                                                        <span>Total: <strong class="font-mono text-primary-700">₱{{ number_format((float) $po->total_amount, 2) }}</strong></span>
                                                    </div>
                                                </div>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-medium text-neutral-900">
                                        ₱{{ number_format((float) $po->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $po->statusEnum() === \App\Enums\PurchaseOrderStatus::PartiallyFulfilled ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $po->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right hims-sticky-actions min-w-[170px]">
                                        @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                                            <button
                                                type="button"
                                                @click="selectPo('{{ $po->id }}'); showReceiveModal = true"
                                                class="inline-flex whitespace-nowrap items-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                Receive Shipment
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-neutral-500">
                                        No unreceived purchase orders found. All deliveries are up to date.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Goods Receipt Notes History --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Historical Goods Receipt Notes (GRN)</h3>
                    <p class="text-xs text-neutral-500">Authoritative audit evidence of physical stock deliveries and quarantine routing.</p>
                </div>
                <div class="overflow-x-auto hims-table-scroll">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">GRN Number</th>
                                <th class="px-6 py-3 font-medium">PO Reference</th>
                                <th class="px-6 py-3 font-medium">Supplier</th>
                                <th class="px-6 py-3 font-medium">Waybill / Tracking</th>
                                <th class="px-6 py-3 font-medium">Receipt Date</th>
                                <th class="px-6 py-3 font-medium">Received By</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium text-right hims-sticky-actions min-w-[130px]">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($goodsReceipts as $grn)
                                <tr class="hover:bg-neutral-50/75 transition-colors">
                                    <td class="px-6 py-4 font-mono font-medium text-primary-700">
                                        {{ $grn->grn_number }}
                                    </td>
                                    <td class="px-6 py-4 font-mono text-neutral-800">
                                        {{ $grn->purchaseOrder?->po_number ?? 'Direct Receipt' }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-neutral-900">
                                        {{ $grn->supplier?->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-xs font-mono">
                                        {{ $grn->waybill_number ?? $grn->packing_slip_number ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-neutral-500">
                                        {{ $grn->received_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-neutral-700">
                                        {{ $grn->receivedBy?->name ?? 'Staff' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ in_array($grn->receipt_status, ['stored', 'posted']) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $grn->receipt_status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right hims-sticky-actions min-w-[130px]">
                                        <a href="{{ route('inventory.receiving.show', $grn) }}" class="inline-block whitespace-nowrap text-xs font-semibold text-primary-600 hover:text-primary-800">
                                            View Note &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-neutral-500">
                                        No Goods Receipt Notes generated yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($goodsReceipts->hasPages())
                    <div class="border-t border-neutral-200 px-4 py-3 sm:px-5 dark:border-neutral-800">
                        {{ $goodsReceipts->links() }}
                    </div>
                @endif
            </div>

        {{-- Receive Shipment Modal --}}
        @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
        <div
            x-show="showReceiveModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/70 p-3 sm:p-5 md:p-6 lg:p-8 flex items-center justify-center min-h-screen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div
                class="w-full max-w-6xl xl:max-w-7xl my-auto rounded-2xl bg-white dark:bg-neutral-900 shadow-2xl ring-1 ring-neutral-900/10 dark:ring-neutral-800 overflow-hidden border border-neutral-200 dark:border-neutral-800"
                @click.away="showReceiveModal = false"
            >
                <form action="{{ route('inventory.receiving.store') }}" method="POST" class="space-y-6"
                      data-confirm-title="Submit goods receipt (GRN)"
                      data-confirm-message="Are you sure you want to finalize this receiving intake? Received items will be placed into the inspection buffer."
                      data-confirm-label="Finalize Receipt">
                    @csrf
                    <input type="hidden" name="receipt_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="purchase_order_id" :value="selectedPo ? selectedPo.id : ''">

                    <div class="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/80 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">Dock Receiving &amp; Goods Receipt Note</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Verify supplier, identifiers, and PO line balances before placing the delivery in quarantine.</p>
                        </div>
                        <button type="button" @click="showReceiveModal = false" class="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="px-6 space-y-6">
                        {{-- PO & Supplier Header --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-primary-50/50 dark:bg-primary-950/40 p-4 rounded-xl border border-primary-100 dark:border-primary-900/50">
                            <div>
                                <label class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Purchase Order</label>
                                <p class="text-base font-bold font-mono text-primary-800 dark:text-primary-300" x-text="selectedPo ? selectedPo.po_number : ''"></p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Authorized Supplier</label>
                                <p class="text-base font-semibold text-neutral-800 dark:text-neutral-200" x-text="selectedPo && selectedPo.supplier ? selectedPo.supplier.name : ''"></p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Total Commitment Value</label>
                                <p class="text-base font-mono font-semibold text-neutral-800 dark:text-neutral-200" x-text="selectedPo ? '₱' + Number(selectedPo.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''"></p>
                            </div>
                        </div>

                        {{-- Carrier & Logistics Inputs --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="actual_supplier_id" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Supplier shown on the delivery documents</label>
                                <select id="actual_supplier_id" name="actual_supplier_id" x-model="actualSupplierId" required class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                    <option value="">Select the delivering supplier</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="self-end text-xs text-neutral-600 dark:text-neutral-300">Check the supplier, SKU and UOM on the delivered goods against the approved PO before submission.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Carrier / Logistics Provider</label>
                                <input type="text" name="carrier_name" placeholder="e.g. LBC Express, 2GO" class="mt-1 block w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 dark:placeholder:text-neutral-500 shadow-xs focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Waybill / Airway Bill No.</label>
                                <input type="text" name="waybill_number" placeholder="e.g. AWB-982341" class="mt-1 block w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 dark:placeholder:text-neutral-500 shadow-xs focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Packing Slip / Delivery Receipt</label>
                                <input type="text" name="packing_slip_number" placeholder="e.g. DR-2026-881" class="mt-1 block w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 dark:placeholder:text-neutral-500 shadow-xs focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Target Storage Destination</label>
                                <select name="destination_location_id" class="mt-1 block w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-xs focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="">-- Select at QC --</option>
                                    @foreach($storageLocations as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Line Items Table with Expected PO vs Received Comparison --}}
                        <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
                            <table class="min-w-[76rem] w-full text-left text-xs text-neutral-700 dark:text-neutral-300 divide-y divide-neutral-200 dark:divide-neutral-800">
                                <thead class="bg-neutral-100/90 dark:bg-neutral-800/90 text-[11px] font-bold uppercase tracking-wider text-neutral-600 dark:text-neutral-300 border-b border-neutral-200 dark:border-neutral-700">
                                    <tr>
                                        <th class="px-4 py-3.5 min-w-[14rem]">Item &amp; PO Expectation</th>
                                        <th class="px-3.5 py-3.5 min-w-[11rem]">Actual Delivered Qty</th>
                                        <th class="px-3.5 py-3.5 min-w-[11rem]">Condition &amp; Discrepancy</th>
                                        <th class="px-3.5 py-3.5 min-w-[12rem]">Batch / Lot &amp; Serial</th>
                                        <th class="px-3.5 py-3.5 min-w-[11rem]">Shelf Life / Expiry</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                                    <template x-for="(line, index) in poLines" :key="line.id">
                                        <tr class="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40 transition-colors align-top">
                                            {{-- Expected PO Info --}}
                                            <td class="px-4 py-3.5">
                                                <input type="hidden" :name="`lines[${index}][po_line_id]`" :value="line.id">
                                                <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 leading-snug" x-text="line.item ? line.item.name : 'Item'"></p>
                                                <p class="text-xs font-mono text-neutral-500 dark:text-neutral-400 mt-0.5" x-text="line.item ? line.item.sku : ''"></p>
                                                <label class="mt-2 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Actual delivered SKU</label>
                                                <input type="text" :name="`lines[${index}][actual_sku]`" x-model="line.actual_sku" required class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                                <label class="mt-2 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Actual delivered UOM</label>
                                                <input type="text" :name="`lines[${index}][actual_purchase_unit]`" x-model="line.actual_purchase_unit" required class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                                <div class="mt-2 space-y-1 text-[11px] text-neutral-600 dark:text-neutral-400">
                                                    <div>Ordered: <strong class="font-mono text-neutral-900 dark:text-neutral-200" x-text="line.ordered_quantity"></strong> <span x-text="line.purchase_unit || (line.item ? line.item.unit : 'unit')"></span></div>
                                                    <div>Open to Receive: <strong class="font-mono text-primary-700 dark:text-primary-300" x-text="line.open_quantity"></strong></div>
                                                    <div>Price: <strong class="font-mono" x-text="'₱' + Number(line.unit_price || 0).toFixed(2)"></strong></div>
                                                    <template x-if="line.conversion_factor && Number(line.conversion_factor) > 1">
                                                        <span class="inline-block mt-1 rounded bg-primary-50 dark:bg-primary-950/60 px-2 py-0.5 text-[10px] font-mono font-medium text-primary-700 dark:text-primary-300 ring-1 ring-inset ring-primary-200 dark:ring-primary-800" x-text="`1 ${line.purchase_unit || 'pack'} = ${Number(line.conversion_factor)} ${line.item ? line.item.unit : 'units'}`"></span>
                                                    </template>
                                                </div>
                                            </td>

                                            {{-- Delivered Qty with Live Conversion & Tolerance Alert --}}
                                            <td class="px-3.5 py-3.5">
                                                <div class="space-y-1.5">
                                                    <div class="flex items-center gap-1.5">
                                                        <input
                                                            type="number"
                                                            :name="`lines[${index}][received_quantity]`"
                                                            x-model.number="line.received_quantity"
                                                            :max="line.open_quantity"
                                                            min="1"
                                                            step="1"
                                                            required
                                                            class="w-24 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-xs font-mono font-semibold py-2 px-3 focus:ring-primary-500 focus:border-primary-500 shadow-xs"
                                                        >
                                                        <span class="text-xs font-medium text-neutral-600 dark:text-neutral-400 capitalize" x-text="line.purchase_unit || (line.item ? line.item.unit : 'unit')"></span>
                                                    </div>

                                                    {{-- Pack to Base Unit Conversion Display --}}
                                                    <div class="rounded-md bg-neutral-50 dark:bg-neutral-800/80 p-1.5 text-[11px] font-mono text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                                                        <span>= <strong class="text-primary-700 dark:text-primary-400" x-text="Math.round((line.received_quantity || 0) * (line.conversion_factor || 1))"></strong> <span x-text="line.item ? line.item.unit : 'units'"></span> stock</span>
                                                    </div>

                                                    {{-- Discrepancy Badges --}}
                                                    <template x-if="line.received_quantity < line.open_quantity">
                                                        <span class="inline-flex items-center gap-1 rounded bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                            Short Delivery (<span x-text="line.open_quantity - line.received_quantity"></span> remaining)
                                                        </span>
                                                    </template>
                                                </div>
                                            </td>

                                            {{-- Condition & Discrepancy Resolution --}}
                                            <td class="px-3.5 py-3.5">
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Condition</label>
                                                        <select
                                                            :name="`lines[${index}][item_condition]`"
                                                            x-model="line.item_condition"
                                                            class="mt-0.5 block w-full rounded-md border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs py-1.5 px-2 text-neutral-900 dark:text-neutral-100 focus:border-primary-500 focus:ring-primary-500"
                                                        >
                                                            <option value="good">Good / Pristine</option>
                                                            <option value="damaged">Damaged Package</option>
                                                            <option value="compromised">Compromised / Seal Broken</option>
                                                            <option value="wrong_item">Wrong Item / Spec</option>
                                                            <option value="expired">Expired / Spoiled</option>
                                                        </select>
                                                    </div>

                                                    {{-- Discrepancy Resolution Action (revealed if condition != good or quantity differs) --}}
                                                    <template x-if="line.item_condition !== 'good' || line.received_quantity !== line.open_quantity">
                                                        <div class="p-2 rounded-lg bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 space-y-1.5">
                                                            <label class="block text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">Discrepancy Action</label>
                                                            <select
                                                                :name="`lines[${index}][discrepancy_action]`"
                                                                x-model="line.discrepancy_action"
                                                                class="block w-full rounded border border-amber-300 dark:border-amber-700 bg-white dark:bg-neutral-800 text-[11px] py-1 px-1.5 text-neutral-900 dark:text-neutral-100"
                                                            >
                                                                <option value="quarantine">Hold in Quarantine for QA Assay</option>
                                                                <option value="reject">Reject &amp; Mark for Return</option>
                                                                <option value="return_to_supplier">Return to Supplier</option>
                                                                <option value="hold">Hold on Dock for Buyer Review</option>
                                                            </select>
                                                            <input
                                                                type="text"
                                                                :name="`lines[${index}][discrepancy_notes]`"
                                                                placeholder="Note discrepancy details..."
                                                                class="block w-full rounded border border-amber-300 dark:border-amber-700 bg-white dark:bg-neutral-800 text-[11px] py-1 px-1.5 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400"
                                                            >
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>

                                            {{-- Batch, Lot & Serial Numbers --}}
                                            <td class="px-3.5 py-3.5">
                                                <div class="space-y-1.5">
                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Batch / Lot No.</label>
                                                        <input
                                                            type="text"
                                                            :name="`lines[${index}][batch_number]`"
                                                            placeholder="e.g. LOT-2026-X1"
                                                            class="mt-0.5 w-full rounded-md border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 text-xs font-mono py-1.5 px-2 focus:ring-primary-500 focus:border-primary-500"
                                                            :required="line.item && line.item.is_batch_tracked"
                                                        >
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Serial No. (Optional)</label>
                                                        <input
                                                            type="text"
                                                            :name="`lines[${index}][serial_number]`"
                                                            placeholder="e.g. SN-098234"
                                                            class="mt-0.5 w-full rounded-md border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 text-xs font-mono py-1.5 px-2 focus:ring-primary-500 focus:border-primary-500"
                                                        >
                                                    </div>
                                                </div>
                                            </td>

                                            {{-- Expiry & Manufacturing Dates --}}
                                            <td class="px-3.5 py-3.5">
                                                <div class="space-y-1.5">
                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Expiration Date</label>
                                                        <input
                                                            type="date"
                                                            :name="`lines[${index}][expiry_date]`"
                                                            class="mt-0.5 w-full rounded-md border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-xs font-mono py-1.5 px-2 focus:ring-primary-500 focus:border-primary-500"
                                                            :min="new Date().toISOString().split('T')[0]"
                                                            :required="line.item && line.item.expiry_alert_days > 0"
                                                        >
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Manufacturing Date</label>
                                                        <input
                                                            type="date"
                                                            :name="`lines[${index}][manufactured_date]`"
                                                            class="mt-0.5 w-full rounded-md border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-xs font-mono py-1.5 px-2 focus:ring-primary-500 focus:border-primary-500"
                                                            :max="new Date().toISOString().split('T')[0]"
                                                        >
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/70 font-semibold text-neutral-900 dark:text-neutral-100">
                                    <tr>
                                        <td class="px-4 py-3.5 text-xs text-neutral-500">Total Purchase Order Commitment:</td>
                                        <td class="px-4 py-3.5 font-mono font-bold text-sm text-primary-700 dark:text-primary-400" colspan="4" x-text="selectedPo ? '₱' + Number(selectedPo.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '₱0.00'"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Delivery Notes --}}
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Dock Intake Notes / Packaging Observations</label>
                            <textarea name="notes" rows="2" placeholder="Document packaging integrity, seal numbers, cold-chain indicators, or delivery notes..." class="mt-1 block w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 dark:placeholder:text-neutral-500 shadow-xs focus:border-primary-500 focus:ring-primary-500 text-xs"></textarea>
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/80 px-6 py-4 flex items-center justify-end gap-3">
                        <button type="button" @click="showReceiveModal = false" class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 transition-colors">
                            Confirm Dock Receipt &amp; Quarantine Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>
</x-app-layout>
