<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-800">Inbound Receiving</span>
                    <span class="text-xs text-neutral-500">• Three-Way Matching &amp; DOH GSDP Quarantine Protocol</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Dock Receiving &amp; Goods Receipt Notes</h2>
                <p class="text-sm text-neutral-600">Physical carrier intake, PO line tolerance verification (+5% max), lot/batch registration, and quarantine placement.</p>
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
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        showReceiveModal: false,
        selectedPo: null,
        poLines: [],
        openOrders: {{ Js::from($openPurchaseOrders) }},
        selectPo(poId) {
            this.selectedPo = this.openOrders.find(p => p.id == poId);
            this.poLines = this.selectedPo ? this.selectedPo.lines : [];
        }
    }">
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
                    <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Tolerance Ceiling</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">+5.0%</p>
                    <p class="mt-1 text-xs text-neutral-500">Contractual over-delivery cap</p>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">PO Number</th>
                                <th class="px-6 py-3 font-medium">Supplier</th>
                                <th class="px-6 py-3 font-medium">Order Items</th>
                                <th class="px-6 py-3 font-medium">Total Encumbered</th>
                                <th class="px-6 py-3 font-medium">Fulfillment Status</th>
                                <th class="px-6 py-3 font-medium text-right">Actions</th>
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
                                        @foreach($po->lines as $l)
                                            <div>{{ $l->item?->name ?? 'Item' }} ({{ $l->remainingQuantity() }} open / {{ $l->ordered_quantity }} ord)</div>
                                        @endforeach
                                    </td>
                                    <td class="px-6 py-4 font-mono font-medium text-neutral-900">
                                        ₱{{ number_format((float) $po->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $po->status === 'partially_received' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $po->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                                            <button
                                                type="button"
                                                @click="selectPo('{{ $po->id }}'); showReceiveModal = true"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
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
                <div class="overflow-x-auto">
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
                                <th class="px-6 py-3 font-medium text-right">Details</th>
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
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $grn->receipt_status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $grn->receipt_status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('inventory.receiving.show', $grn) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
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
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $goodsReceipts->links() }}
                    </div>
                @endif
            </div>

        </div>

        {{-- Receive Shipment Modal --}}
        <div
            x-show="showReceiveModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 p-4 sm:p-6 md:p-20"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div
                class="mx-auto max-w-4xl rounded-2xl bg-white shadow-2xl ring-1 ring-neutral-900/10 overflow-hidden"
                @click.away="showReceiveModal = false"
            >
                <form action="{{ route('inventory.receiving.store') }}" method="POST" class="space-y-6">
                    @csrf
                    <input type="hidden" name="purchase_order_id" :value="selectedPo ? selectedPo.id : ''">

                    <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Dock Receiving &amp; Goods Receipt Note</h3>
                            <p class="text-xs text-neutral-500">Capture carrier bill of lading, verify line quantities (+5% max tolerance), and place in Quarantine.</p>
                        </div>
                        <button type="button" @click="showReceiveModal = false" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="px-6 space-y-6">
                        {{-- PO & Supplier Header --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-primary-50/50 p-4 rounded-xl border border-primary-100">
                            <div>
                                <label class="text-xs font-semibold text-neutral-500">Purchase Order</label>
                                <p class="text-sm font-bold font-mono text-primary-800" x-text="selectedPo ? selectedPo.po_number : ''"></p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-neutral-500">Authorized Supplier</label>
                                <p class="text-sm font-semibold text-neutral-800" x-text="selectedPo && selectedPo.supplier ? selectedPo.supplier.name : ''"></p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-neutral-500">Total Commitment Value</label>
                                <p class="text-sm font-mono font-semibold text-neutral-800" x-text="selectedPo ? '₱' + Number(selectedPo.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) : ''"></p>
                            </div>
                        </div>

                        {{-- Carrier & Logistics Inputs --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-neutral-700">Carrier / Logistics Provider</label>
                                <input type="text" name="carrier_name" placeholder="e.g. LBC Express, 2GO" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-neutral-700">Waybill / Airway Bill No.</label>
                                <input type="text" name="waybill_number" placeholder="e.g. AWB-982341" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-neutral-700">Packing Slip / Delivery Receipt</label>
                                <input type="text" name="packing_slip_number" placeholder="e.g. DR-2026-881" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                        </div>

                        {{-- Line Items Table --}}
                        <div class="max-w-full overflow-x-auto rounded-xl border border-neutral-200">
                            <table class="min-w-[48rem] w-full text-left text-xs text-neutral-600">
                                <thead class="bg-neutral-100 uppercase text-neutral-600 font-semibold border-b">
                                    <tr>
                                        <th class="px-4 py-2.5">Item Description</th>
                                        <th class="px-4 py-2.5">Ordered / Open</th>
                                        <th class="px-4 py-2.5">Receiving Qty</th>
                                        <th class="px-4 py-2.5">Batch / Lot No.</th>
                                        <th class="px-4 py-2.5">Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200">
                                    <template x-for="(line, index) in poLines" :key="line.id">
                                        <tr class="hover:bg-neutral-50">
                                            <td class="px-4 py-3">
                                                <input type="hidden" :name="`lines[${index}][po_line_id]`" :value="line.id">
                                                <p class="font-semibold text-neutral-900" x-text="line.item ? line.item.name : 'Item'"></p>
                                                <p class="text-[11px] font-mono text-neutral-500" x-text="line.item ? line.item.sku : ''"></p>
                                            </td>
                                            <td class="px-4 py-3 font-mono">
                                                <span x-text="line.ordered_quantity"></span> /
                                                <span class="font-semibold text-primary-700" x-text="Math.max(0, line.ordered_quantity - line.received_quantity)"></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    :name="`lines[${index}][received_quantity]`"
                                                    :value="Math.max(0, line.ordered_quantity - line.received_quantity)"
                                                    :max="Math.ceil(Math.max(1, line.ordered_quantity - line.received_quantity) * 1.05)"
                                                    min="1"
                                                    step="1"
                                                    required
                                                    class="w-24 rounded border-neutral-300 text-xs font-mono focus:ring-primary-500"
                                                >
                                                <span class="block text-[10px] text-neutral-400 mt-0.5">Max +5%: <span x-text="Math.ceil(Math.max(1, line.ordered_quantity - line.received_quantity) * 1.05)"></span></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="text"
                                                    :name="`lines[${index}][batch_number]`"
                                                    placeholder="e.g. LOT-2026-X1"
                                                    class="w-32 rounded border-neutral-300 text-xs font-mono focus:ring-primary-500"
                                                    :required="line.item && line.item.is_batch_tracked"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="date"
                                                    :name="`lines[${index}][expiry_date]`"
                                                    class="w-32 rounded border-neutral-300 text-xs focus:ring-primary-500"
                                                    :min="new Date().toISOString().split('T')[0]"
                                                    :required="line.item && line.item.expiry_alert_days > 0"
                                                >
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Delivery Notes --}}
                        <div>
                            <label class="block text-xs font-medium text-neutral-700">Dock Intake Notes / Packaging Observations</label>
                            <textarea name="notes" rows="2" placeholder="Document packaging integrity, seal numbers, or delivery notes..." class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-xs"></textarea>
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 bg-neutral-50 px-6 py-4 flex items-center justify-end gap-3">
                        <button type="button" @click="showReceiveModal = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            Confirm Dock Receipt &amp; Quarantine Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
