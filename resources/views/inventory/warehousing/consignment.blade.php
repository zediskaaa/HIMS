<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" x-data="{ consumeModal: false }">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Point-of-Care Material Custody</p>
                <h2 class="text-2xl font-bold text-neutral-900">Surgical Consignment & Bill-Only Implants</h2>
                <p class="text-sm text-neutral-600">Vendor-owned high-value surgical implants (stents, prostheses, lenses). Automated Bill-Only PR generation upon OR consumption.</p>
            </div>
            <div class="flex items-center gap-2">
                @can(\App\Enums\Permission::RecordConsignments->value)
                    <button type="button" @click="$dispatch('open-consume-modal')" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Record OR Consumption
                    </button>
                @endcan
                <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    &larr; Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ showConsumeModal: false }" @open-consume-modal.window="showConsumeModal = true">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            @if(session('success'))
                <x-ui.alert variant="success" :message="session('success')" />
            @endif
            @if($errors->any())
                <x-ui.alert variant="danger" title="Validation errors occurred">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            {{-- Consignment Status Banner --}}
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-5 flex items-start gap-4">
                <div class="rounded-full bg-amber-500 p-2 text-white shrink-0">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-amber-900">Zero-Liability Consignment Accounting</h4>
                    <p class="text-xs text-amber-800 mt-1">
                        High-value implants housed in surgical suite coordinates remain vendor property until implanted in a patient. When a serial number is scanned during surgery with the patient encounter ID, the system automatically posts the patient charge capture and dispatches an automated <strong>Bill-Only Purchase Request</strong> to Procurement to process the supplier invoice.
                    </p>
                </div>
            </div>

            {{-- Consignment Inventory Balances --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-100 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900">Consignment Implants On-Hand</h3>
                        <p class="text-xs text-neutral-500">Active stock held in hospital operating suites and catheterization laboratories.</p>
                    </div>
                    <span class="rounded bg-amber-100 px-2.5 py-1 text-xs font-bold uppercase text-amber-800">{{ $consignmentItems->count() }} Registered Implants</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-6 py-3">Implant SKU & Name</th>
                                <th class="px-6 py-3">Storage Location</th>
                                <th class="px-6 py-3">FDA CPR No</th>
                                <th class="px-6 py-3">Unit Cost (PHP)</th>
                                <th class="px-6 py-3 text-right">Available Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse($balances as $b)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-3.5 font-semibold text-neutral-900">
                                        {{ $b->item?->name }}
                                        <div class="text-[11px] text-neutral-500 font-mono">{{ $b->item?->sku }}</div>
                                    </td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-neutral-700">
                                        {{ $b->location?->code }} ({{ $b->location?->name }})
                                    </td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-neutral-600">
                                        {{ $b->item?->fda_cpr_number ?? 'MDR-Registered' }}
                                    </td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-neutral-900">
                                        ₱{{ number_format($b->item?->unit_cost ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-bold text-neutral-900">
                                        {{ $b->quantity }} {{ $b->item?->unit ?? 'ea' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-sm text-neutral-500">
                                        No consignment items currently registered in stock.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- OR Consumption & Bill-Only PR History --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-100 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900">Operating Room Consumption &amp; Bill-Only Requisitions</h3>
                        <p class="text-xs text-neutral-500">Audit trail linking patient encounters, operating rooms, attending surgeons, and procurement PRs.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Tracking No & Date</th>
                                <th class="px-4 py-3">Implant Details</th>
                                <th class="px-4 py-3">Serial / Lot No</th>
                                <th class="px-4 py-3">Patient Encounter</th>
                                <th class="px-4 py-3">OR Suite & Surgeon</th>
                                <th class="px-4 py-3">Bill-Only PR</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 text-xs">
                            @forelse($records as $rec)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-neutral-900">{{ $rec->request_number }}</div>
                                        <div class="text-[10px] text-neutral-400 font-mono">{{ $rec->implanted_at?->format('Y-m-d H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-neutral-800">
                                        {{ $rec->item?->name }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-neutral-700">
                                        {{ $rec->serial?->serial_number ?? ($rec->batch?->batch_number ?? 'N/A') }}
                                    </td>
                                    <td class="px-4 py-3 font-mono font-bold text-primary-700">
                                        {{ $rec->patient_encounter_id }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-neutral-800">{{ $rec->operating_suite }}</div>
                                        <div class="text-[11px] text-neutral-500">Dr. {{ $rec->surgeon_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono">
                                        @if($rec->purchaseRequest)
                                            <span class="rounded bg-blue-100 px-1.5 py-0.5 text-blue-900 font-bold">
                                                {{ $rec->purchaseRequest->pr_number }}
                                            </span>
                                        @else
                                            <span class="text-neutral-400">PR-Auto</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded px-2 py-0.5 text-[10px] font-bold uppercase
                                            @if($rec->status === 'closed') bg-emerald-100 text-emerald-800
                                            @elseif($rec->status === 'po_generated') bg-blue-100 text-blue-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ str_replace('_', ' ', $rec->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-sm text-neutral-500">
                                        No consignment implant consumptions recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-200 px-6 py-4">
                    {{ $records->links() }}
                </div>
            </div>

        </div>

        @can(\App\Enums\Permission::RecordConsignments->value)
        {{-- Record OR Consumption Modal --}}
        <div x-show="showConsumeModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center" x-cloak style="display: none;">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.away="showConsumeModal = false">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                    <h3 class="text-lg font-bold text-amber-900">Record OR Consignment Consumption</h3>
                    <button type="button" @click="showConsumeModal = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                </div>

                <form method="POST" action="{{ route('inventory.warehousing.consignment.consume') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="cons_item_id" class="text-xs font-semibold uppercase text-neutral-500">Surgical Consignment Implant *</label>
                        <select id="cons_item_id" name="inventory_item_id" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                            <option value="">Select Implant</option>
                            @foreach($consignmentItems as $cItem)
                                <option value="{{ $cItem->id }}">{{ $cItem->name }} (Cost: ₱{{ number_format($cItem->unit_cost, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="serial_number" class="text-xs font-semibold uppercase text-neutral-500">Serial Number / DataMatrix</label>
                            <input type="text" id="serial_number" name="serial_number" placeholder="SN-99401" class="mt-1 w-full rounded-lg border-neutral-300 font-mono text-sm">
                        </div>
                        <div>
                            <label for="cons_loc_id" class="text-xs font-semibold uppercase text-neutral-500">Storage Coordinate *</label>
                            <select id="cons_loc_id" name="storage_location_id" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                                @foreach($consignmentLocations as $cLoc)
                                    <option value="{{ $cLoc->id }}">{{ $cLoc->code }} ({{ $cLoc->name }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="patient_encounter_id" class="text-xs font-semibold uppercase text-neutral-500">Patient Encounter / Hospital No *</label>
                            <input type="text" id="patient_encounter_id" name="patient_encounter_id" required placeholder="ENC-2026-00812" class="mt-1 w-full rounded-lg border-neutral-300 text-sm font-mono">
                        </div>
                        <div>
                            <label for="implanted_quantity" class="text-xs font-semibold uppercase text-neutral-500">Quantity (Units) *</label>
                            <input type="number" id="implanted_quantity" name="implanted_quantity" min="1" value="1" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm font-bold">
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="operating_suite" class="text-xs font-semibold uppercase text-neutral-500">Operating Suite / Cath Lab *</label>
                            <input type="text" id="operating_suite" name="operating_suite" required placeholder="Operating Suite 3 Cleanroom" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                        <div>
                            <label for="surgeon_name" class="text-xs font-semibold uppercase text-neutral-500">Attending Surgeon *</label>
                            <input type="text" id="surgeon_name" name="surgeon_name" required placeholder="Dr. Roberto Gomez, FPCS" class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="cons_notes" class="text-xs font-semibold uppercase text-neutral-500">Clinical Notes / Procedure Ref</label>
                        <textarea id="cons_notes" name="notes" rows="2" placeholder="e.g. Coronary Angioplasty; stent successfully deployed in LAD." class="mt-1 w-full rounded-lg border-neutral-300 text-sm"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-neutral-100 pt-4">
                        <button type="button" @click="showConsumeModal = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700">Cancel</button>
                        <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700 shadow-sm">
                            Confirm Implant & Dispatch Bill-Only PR
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

    </div>
</x-app-layout>
