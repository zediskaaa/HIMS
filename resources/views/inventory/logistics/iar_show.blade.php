<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between print:hidden">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.logistics.iar.index') }}" class="text-xs font-semibold text-primary-700 hover:underline">&larr; Back to IAR Ledger</a>
                    <span class="text-neutral-300">•</span>
                    <span class="inline-flex items-center rounded-md bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-800">
                        COA GAM Appendix 50
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-bold text-neutral-900">{{ $iar->iar_number }}</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.logistics.iar.print', ['iar' => $iar, 'print' => 1]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print GAM App. 50
                </a>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="space-y-6 print:p-0 print:m-0 print:space-y-0" x-data="{ inspectModalOpen: false, acceptModalOpen: false, coaModalOpen: false }">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm print:hidden">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm print:hidden">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- Action Banner for Inspectors & Custodians --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-neutral-900">Current Lifecycle: {{ ucwords(str_replace('_', ' ', $iar->status)) }}</div>
                        <div class="text-xs text-neutral-500">
                            Segregation of Duties: Inspection and Custodial Acceptance must be executed by distinct authorized personnel.
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Technical Inspection Button --}}
                    @can(\App\Enums\Permission::PerformTechnicalInspection->value)
                        @if($iar->status === 'pending_inspection')
                            <button @click="inspectModalOpen = true" class="rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-blue-700 shadow-sm">
                                Perform Technical Inspection
                            </button>
                        @endif
                    @endcan

                    {{-- Custodial Acceptance Button --}}
                    @can(\App\Enums\Permission::ApproveIarAcceptance->value)
                        @if($iar->status === 'inspected_passed')
                            <button @click="acceptModalOpen = true" class="rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700 shadow-sm">
                                Approve Custodial Acceptance
                            </button>
                        @endif

                        @if($iar->isAccepted() && !$iar->coa_transmitted_at)
                            <button @click="coaModalOpen = true" class="rounded-lg bg-purple-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-purple-700 shadow-sm">
                                Transmit to Resident COA Auditor
                            </button>
                        @endif
                    @endcan
                </div>
            </div>

            {{-- FORMAL COA GAM VOLUME II APPENDIX 50 DOCUMENT CANVAS --}}
            @include('inventory.logistics.partials.iar_document')

            {{-- Linked Supporting Documents & Chain of Custody (Screen Only) --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm print:hidden space-y-4">
                <h3 class="text-sm font-bold text-neutral-900">Archived Supporting Records & Chain of Custody</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 p-3">
                        <div class="text-xs font-bold text-neutral-700">Delivery Receipts & Commercial Invoices</div>
                        <ul class="mt-2 space-y-1 text-xs">
                            @forelse($iar->documents as $doc)
                                <li class="flex items-center justify-between py-1">
                                    <span class="truncate font-medium text-neutral-900">{{ $doc->title }} ({{ $doc->tracking_number }})</span>
                                    <a href="{{ route('inventory.logistics.documents.download', $doc) }}" class="text-primary-600 hover:underline" data-hims-download data-loading-text="Preparing document..." data-download-name="{{ $doc->original_name ?: ($doc->file_name ?: 'document') }}">Download</a>
                                </li>
                            @empty
                                <li class="text-neutral-400 text-xs italic">No direct file attachments linked to this IAR.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 p-3">
                        <div class="text-xs font-bold text-neutral-700">Immutable Custody Handoffs</div>
                        <ul class="mt-2 space-y-1 text-xs">
                            @forelse($iar->custodyLogs as $log)
                                <li class="py-1">
                                    <div class="font-semibold text-neutral-800">{{ ucwords(str_replace('_', ' ', $log->event_type)) }}</div>
                                    <div class="text-[10px] text-neutral-500">{{ $log->releasing_party_name }} &rarr; {{ $log->receiving_party_name }} on {{ $log->transferred_at->format('M d, Y h:i A') }}</div>
                                </li>
                            @empty
                                <li class="text-neutral-400 text-xs italic">No custody transfers recorded for this IAR yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Perform Technical Inspection Modal --}}
            @can(\App\Enums\Permission::PerformTechnicalInspection->value)
            <div x-show="inspectModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="inspectModalOpen" @click="inspectModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="inspectModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Execute Technical Inspection</h3>
                            <button @click="inspectModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form action="{{ route('inventory.logistics.iar.technical-inspection', $iar) }}" method="POST" class="mt-4 space-y-4"
                              data-confirm-title="Submit technical inspection"
                              data-confirm-message="Are you sure you want to sign off on the technical inspection findings for this IAR?"
                              data-confirm-label="Submit Inspection">
                            @csrf
                            <p class="text-xs text-neutral-600">Signing as Technical Inspector. Buyer cannot sign inspection per Segregation of Duties.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Inspection Status *</label>
                                <select name="status" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="inspected">Inspected & Verified (Meets Technical Specs)</option>
                                    <option value="rejected">Rejected (Defective / Non-conforming)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Inspection Date *</label>
                                <input type="date" name="inspection_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Inspection Remarks / Observations *</label>
                                <textarea name="remarks" required rows="3" placeholder="Lot numbers verified against Certificate of Analysis. Packaging intact and free from defects..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="inspectModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Submit Inspection</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan

            {{-- Approve Custodial Acceptance Modal --}}
            @can(\App\Enums\Permission::ApproveIarAcceptance->value)
            <div x-show="acceptModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="acceptModalOpen" @click="acceptModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="acceptModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Approve Custodial Acceptance</h3>
                            <button @click="acceptModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form action="{{ route('inventory.logistics.iar.custodial-acceptance', $iar) }}" method="POST" class="mt-4 space-y-4"
                              data-confirm-title="Confirm custodial acceptance"
                              data-confirm-message="Are you sure you want to accept custodial responsibility for these received items? Stock will be posted to the hospital inventory."
                              data-confirm-label="Approve Acceptance">
                            @csrf
                            <p class="text-xs text-neutral-600">Signing as Property / Supply Custodian. Items will be formally posted to the hospital stock ledger.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Acceptance Type *</label>
                                <select name="acceptance_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="complete">Complete Acceptance</option>
                                    <option value="partial">Partial Acceptance</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Acceptance Date *</label>
                                <input type="date" name="acceptance_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Custodial Acceptance Remarks</label>
                                <textarea name="remarks" rows="3" placeholder="Stock posted to storage locations. Stock cards updated..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="acceptModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve Acceptance</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Transmit to Resident COA Auditor Modal --}}
            <div x-show="coaModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="coaModalOpen" @click="coaModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="coaModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Transmit to Resident COA Auditor</h3>
                            <button @click="coaModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form action="{{ route('inventory.logistics.iar.transmit-coa', $iar) }}" method="POST" class="mt-4 space-y-4"
                              data-confirm-title="Transmit to Commission on Audit (COA)"
                              data-confirm-message="Are you sure you want to officially transmit this completed IAR package to COA? This action will be permanently recorded in the audit log."
                              data-confirm-label="Transmit to COA"
                              data-confirm-variant="warning">
                            @csrf
                            <p class="text-xs text-neutral-600">Per COA Circular, a copy of the IAR must be transmitted to the resident auditor within 5 days of delivery acceptance.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">COA Auditor / Received By Staff Name *</label>
                                <input type="text" name="transmittal_reference" required placeholder="e.g. Maria Teresa Gomez, CPA (COA State Auditor IV)"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Transmittal Date *</label>
                                <input type="date" name="transmittal_date" required value="{{ date('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="coaModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">Record Transmittal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
        </div>
</x-app-layout>
