<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" x-data="{ recordModal: false }">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-purple-700">Republic Act No. 9165 & DDB Reg. 1 (2014)</p>
                <h2 class="text-2xl font-bold text-neutral-900">Dangerous Drugs Vault & Electronic DDRB</h2>
                <p class="text-sm text-neutral-600">Dual-custody access governance, Yellow Prescription tracking, physician S-2 license validation, and 5-year retention.</p>
            </div>
            <div class="flex items-center gap-2">
                @can(\App\Enums\Permission::AccessNarcoticsVault->value)
                    <button type="button" @click="$dispatch('open-vault-modal')" class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Record Vault Movement
                    </button>
                @endcan
                <a href="{{ route('inventory.warehousing.narcotics.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export PDEA Report (CSV)
                </a>
                <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    &larr; Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ showVaultModal: false }" @open-vault-modal.window="showVaultModal = true">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            @if(session('success'))
                <x-ui.alert variant="success" :message="session('success')" />
            @endif
            @if($errors->any())
                <x-ui.alert variant="danger" title="Validation errors occurred">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            {{-- Current Vault Inventory Balances --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-100 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900">Vault On-Hand Controlled Substances</h3>
                        <p class="text-xs text-neutral-500">Live verified balances stored in reinforced narcotics vault coordinates.</p>
                    </div>
                    <span class="rounded bg-purple-100 px-2.5 py-1 text-xs font-bold uppercase text-purple-800">Continuous Dual-Custody</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-6 py-3">Generic Clinical Name</th>
                                <th class="px-6 py-3">Formulation & Strength</th>
                                <th class="px-6 py-3">Vault Coordinate</th>
                                <th class="px-6 py-3">Batch / Lot</th>
                                <th class="px-6 py-3 text-right">Physical On-Hand</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse($vaultBalances as $bal)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-3.5 font-semibold text-neutral-900">
                                        {{ $bal->item?->generic_name ?? $bal->item?->name }}
                                        <div class="text-[11px] text-neutral-500 font-mono">{{ $bal->item?->sku }}</div>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-neutral-600">
                                        {{ $bal->item?->dosage_form_strength ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-3.5 font-mono text-xs font-bold text-purple-700">
                                        {{ $bal->location?->code }}
                                    </td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-neutral-600">
                                        {{ $bal->batch?->batch_number ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-bold text-neutral-900">
                                        {{ $bal->quantity }} {{ $bal->item?->unit ?? 'units' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-sm text-neutral-500">
                                        No controlled substances currently registered in narcotics vaults.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Electronic DDRB (Dangerous Drugs Record Book) Registry --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-100 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900">Electronic Dangerous Drugs Record Book (DDRB)</h3>
                        <p class="text-xs text-neutral-500">Append-only statutory register retained for at least five (5) years in compliance with DDB regulations.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Register No & Date</th>
                                <th class="px-4 py-3">Clinical Item</th>
                                <th class="px-4 py-3">SPF Yellow Rx / Inbound</th>
                                <th class="px-4 py-3">Physician & S-2 License</th>
                                <th class="px-4 py-3">Patient Encounter</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Running Bal</th>
                                <th class="px-4 py-3">Dual Signatures</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 text-xs">
                            @forelse($entries as $entry)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-purple-800">{{ $entry->register_number }}</div>
                                        <div class="text-[10px] text-neutral-400 font-mono">{{ $entry->recorded_at?->format('Y-m-d H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-neutral-900">{{ $entry->item?->generic_name ?? $entry->item?->name }}</div>
                                        <div class="text-[11px] text-neutral-500">{{ $entry->item?->dosage_form_strength }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono">
                                        @if($entry->pdea_spf_number)
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900 font-bold">{{ $entry->pdea_spf_number }}</span>
                                        @else
                                            <span class="text-neutral-400">INBOUND RECEIPT</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-neutral-800">{{ $entry->prescriber_name ?? 'N/A' }}</div>
                                        <div class="font-mono text-[11px] text-neutral-500">S-2: {{ $entry->physician_s2_license ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-neutral-700">
                                        {{ $entry->patient_encounter_id ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-neutral-900">
                                        {{ $entry->quantity }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-purple-900">
                                        {{ $entry->running_balance }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-neutral-700">Cust: {{ $entry->custodian?->name }}</div>
                                        <div class="text-neutral-500">Wit: {{ $entry->witnessPharmacist?->name }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-sm text-neutral-500">
                                        No DDRB ledger entries recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-200 px-6 py-4">
                    {{ $entries->links() }}
                </div>
            </div>

        </div>

        {{-- Record Vault Movement Modal with Dual-Custody Verification --}}
        <div x-show="showVaultModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center" x-cloak style="display: none;">
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl" @click.away="showVaultModal = false" x-data="{ isInbound: false }">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                    <h3 class="text-lg font-bold text-purple-900">Record Dangerous Drugs Vault Movement</h3>
                    <button type="button" @click="showVaultModal = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                </div>

                <form method="POST" action="{{ route('inventory.warehousing.narcotics.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs font-semibold uppercase text-neutral-500">Movement Direction *</label>
                        <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button type="button" @click="isInbound = false" :class="!isInbound ? 'bg-purple-600 text-white font-bold' : 'bg-neutral-100 text-neutral-700'" class="rounded-lg py-2 text-xs transition">
                                Outbound Dispensing (Requires Yellow Rx)
                            </button>
                            <button type="button" @click="isInbound = true" :class="isInbound ? 'bg-purple-600 text-white font-bold' : 'bg-neutral-100 text-neutral-700'" class="rounded-lg py-2 text-xs transition">
                                Inbound Vault Intake
                            </button>
                        </div>
                        <input type="hidden" name="is_inbound" :value="isInbound ? '1' : '0'">
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="vault_item_id" class="text-xs font-semibold uppercase text-neutral-500">Controlled Substance *</label>
                            <select id="vault_item_id" name="item_id" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                                <option value="">Select Dangerous Drug</option>
                                @foreach($dangerousDrugItems as $dItem)
                                    <option value="{{ $dItem->id }}">{{ $dItem->generic_name ?? $dItem->name }} ({{ $dItem->dosage_form_strength }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="vault_loc_id" class="text-xs font-semibold uppercase text-neutral-500">Vault Location *</label>
                            <select id="vault_loc_id" name="storage_location_id" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                                @foreach($vaultLocations as $vLoc)
                                    <option value="{{ $vLoc->id }}">{{ $vLoc->code }} ({{ $vLoc->name }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="vault_qty" class="text-xs font-semibold uppercase text-neutral-500">Quantity (Units) *</label>
                        <input type="number" id="vault_qty" name="quantity" min="1" required placeholder="e.g. 10" class="mt-1 w-full rounded-lg border-neutral-300 text-sm font-bold">
                    </div>

                    {{-- Outbound Mandated Fields (PDEA Yellow Rx) --}}
                    <div x-show="!isInbound" class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800">Statutory Dispensing Credentials</div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="pdea_spf_number" class="text-[11px] font-semibold text-neutral-600">Yellow Rx Serial No (SPF) *</label>
                                <input type="text" id="pdea_spf_number" name="pdea_spf_number" placeholder="SPF-2026-XXXXX" class="mt-1 w-full rounded-lg border-neutral-300 text-xs font-mono">
                            </div>
                            <div>
                                <label for="physician_s2_license" class="text-[11px] font-semibold text-neutral-600">Prescriber S-2 License No *</label>
                                <input type="text" id="physician_s2_license" name="physician_s2_license" placeholder="S2-XXXX-XXXXX" class="mt-1 w-full rounded-lg border-neutral-300 text-xs font-mono">
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="prescriber_name" class="text-[11px] font-semibold text-neutral-600">Prescribing Physician Name</label>
                                <input type="text" id="prescriber_name" name="prescriber_name" placeholder="Dr. Juan dela Cruz" class="mt-1 w-full rounded-lg border-neutral-300 text-xs">
                            </div>
                            <div>
                                <label for="patient_encounter_id" class="text-[11px] font-semibold text-neutral-600">Patient Hospital / Encounter ID</label>
                                <input type="text" id="patient_encounter_id" name="patient_encounter_id" placeholder="ENC-2026-XXXXX" class="mt-1 w-full rounded-lg border-neutral-300 text-xs font-mono">
                            </div>
                        </div>
                    </div>

                    {{-- Dual-Custody Witness Re-Authentication --}}
                    <div class="rounded-xl border border-purple-200 bg-purple-50/50 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-purple-900">Dual-Custody Witness Re-Authentication</span>
                            <span class="text-[10px] text-purple-700">Licensed Pharmacist / Supervisor</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="witness_email" class="text-[11px] font-semibold text-neutral-600">Witness Email *</label>
                                <input type="email" id="witness_email" name="witness_email" required placeholder="pharmacist@hospital.ph" class="mt-1 w-full rounded-lg border-neutral-300 text-xs">
                            </div>
                            <div>
                                <label for="witness_password" class="text-[11px] font-semibold text-neutral-600">Witness Password / PIN *</label>
                                <input type="password" id="witness_password" name="witness_password" required placeholder="Enter password to sign" class="mt-1 w-full rounded-lg border-neutral-300 text-xs">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-neutral-100 pt-4">
                        <button type="button" @click="showVaultModal = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700">Cancel</button>
                        <button type="submit" class="rounded-lg bg-purple-600 px-5 py-2 text-sm font-semibold text-white hover:bg-purple-700 shadow-sm">
                            Execute & Seal DDRB Record
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
