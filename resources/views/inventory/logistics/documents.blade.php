<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Digital Archive & Audit Trail</p>
                <h2 class="text-2xl font-bold text-neutral-900">Document Tracking Registry</h2>
                <p class="text-sm text-neutral-600">Secure SHA-256 cryptographic verification, National Archives (NAP) retention compliance, and non-destructive versioning.</p>
            </div>
            <div class="flex items-center gap-2" x-data>
                <button @click="$dispatch('open-upload-modal')" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Document
                </button>
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-6" x-data="{ uploadModalOpen: false, verifyModalOpen: false, verifyDocId: null, verifyDocTracking: '', supersedeModalOpen: false, supersedeDocId: null, supersedeDocTracking: '' }"
         @open-upload-modal.window="uploadModalOpen = true">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Flash Notifications --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- Filter & Search Bar --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('inventory.logistics.documents') }}" class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-5">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search tracking #, reference #, title, or filename..."
                                   class="block w-full rounded-lg border-neutral-300 pl-10 text-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <select name="document_type" class="block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Document Types</option>
                            @foreach($documentTypes as $type)
                                <option value="{{ $type->value }}" {{ request('document_type') === $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }} ({{ $type->abbreviation() }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <select name="status" class="block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All Statuses</option>
                            <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted (Pending Audit)</option>
                            <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified (SHA-256)</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived / Superseded</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <button type="submit" class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800">
                            Filter
                        </button>
                        @if(request()->hasAny(['search', 'document_type', 'status']))
                            <a href="{{ route('inventory.logistics.documents') }}" class="rounded-lg border border-neutral-300 p-2 text-neutral-600 hover:bg-neutral-50" title="Reset Filters">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Documents Registry Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <tr>
                                <th class="px-6 py-3.5">Document Details</th>
                                <th class="px-6 py-3.5">Commercial / Reference</th>
                                <th class="px-6 py-3.5">Archival & Integrity</th>
                                <th class="px-6 py-3.5">Status & Audit</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white">
                            @forelse($documents as $doc)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <span class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-700">
                                                {{ $doc->document_type->abbreviation() }}
                                            </span>
                                            <div>
                                                <div class="font-bold text-neutral-900">{{ $doc->title }}</div>
                                                <div class="mt-0.5 font-mono text-xs text-neutral-500">
                                                    {{ $doc->tracking_number }}
                                                    @if($doc->version_number > 1)
                                                        <span class="rounded bg-indigo-50 px-1 text-[10px] font-semibold text-indigo-700">v{{ $doc->version_number }}</span>
                                                    @endif
                                                </div>
                                                <div class="mt-1 text-xs text-neutral-400">
                                                    Uploaded by {{ $doc->uploadedBy->name ?? 'System' }} on {{ $doc->created_at->format('M d, Y h:i A') }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        @if($doc->reference_number)
                                            <div>Ref #: <span class="font-mono font-bold text-neutral-800">{{ $doc->reference_number }}</span></div>
                                        @endif
                                        @if($doc->purchaseOrder)
                                            <div class="mt-0.5 text-neutral-600">PO: <span class="font-semibold text-primary-700">{{ $doc->purchaseOrder->po_number }}</span></div>
                                        @endif
                                        @if($doc->goodsReceiptNote)
                                            <div class="mt-0.5 text-neutral-600">GRN: <span class="font-mono font-medium">{{ $doc->goodsReceiptNote->grn_number }}</span></div>
                                        @endif
                                        @if(!$doc->reference_number && !$doc->purchaseOrder && !$doc->goodsReceiptNote)
                                            <span class="text-neutral-400">General Record</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-xs">
                                        <div class="flex items-center gap-1 font-mono text-neutral-700" title="Full SHA-256: {{ $doc->sha256_checksum }}">
                                            <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            <span>{{ substr($doc->sha256_checksum, 0, 12) }}...</span>
                                        </div>
                                        <div class="mt-1 text-neutral-500">
                                            {{ number_format($doc->file_size_bytes / 1024, 1) }} KB • {{ strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION)) }}
                                        </div>
                                        <div class="mt-1 text-[10px] font-medium text-neutral-400">
                                            NAP: Retain until {{ $doc->retention_until?->format('Y-m-d') ?? 'Permanent' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        @if($doc->status === 'verified')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Verified
                                            </span>
                                            <div class="mt-1 text-[10px] text-neutral-500">By {{ $doc->verifiedBy->name ?? 'Auditor' }}</div>
                                        @elseif($doc->status === 'rejected')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">
                                                Rejected
                                            </span>
                                            @if($doc->verification_notes)
                                                <div class="mt-1 text-[10px] text-red-600 max-w-xs truncate">{{ $doc->verification_notes }}</div>
                                            @endif
                                        @elseif($doc->status === 'archived')
                                            <span class="inline-flex rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-600">
                                                Superseded
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                ⏳ Pending Audit
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('inventory.logistics.documents.download', $doc) }}"
                                               class="inline-flex items-center gap-1 rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 shadow-sm hover:bg-neutral-50"
                                               title="Download verified binary">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Download
                                            </a>

                                            @can(\App\Enums\Permission::VerifyLogisticsDocuments->value)
                                                @if($doc->status === 'submitted')
                                                    <button @click="verifyDocId = {{ $doc->id }}; verifyDocTracking = '{{ $doc->tracking_number }}'; verifyModalOpen = true"
                                                            class="rounded-md bg-neutral-900 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
                                                        Verify
                                                    </button>
                                                @endif
                                            @endcan

                                            @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                                                @if($doc->status !== 'archived')
                                                    <button @click="supersedeDocId = {{ $doc->id }}; supersedeDocTracking = '{{ $doc->tracking_number }}'; supersedeModalOpen = true"
                                                            class="rounded-md border border-neutral-300 px-2 py-1.5 text-xs text-neutral-600 hover:bg-neutral-50"
                                                            title="Upload Superseding Version">
                                                        Revise
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-neutral-500">
                                        No logistics documents found matching your criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($documents->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>

            {{-- Upload Modal --}}
            <div x-show="uploadModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="uploadModalOpen" @click="uploadModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="uploadModalOpen" class="inline-block w-full max-w-xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-lg font-bold text-neutral-900">Upload Logistics Document</h3>
                            <button @click="uploadModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('inventory.logistics.documents.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Document Type *</label>
                                <select name="document_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }} ({{ $type->abbreviation() }}) - {{ $type->napRetentionYears() }}yr retention</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Document Title *</label>
                                <input type="text" name="title" required placeholder="e.g. Zuellig Pharma Delivery Receipt DR-99482"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Document / Reference #</label>
                                    <input type="text" name="document_number" placeholder="e.g. SI-2026-00441"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-neutral-600">Linked Purchase Order</label>
                                    <select name="purchase_order_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">None / Unlinked</option>
                                        @foreach($purchaseOrders as $po)
                                            <option value="{{ $po->id }}">PO: {{ $po->po_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Linked Goods Receipt (GRN)</label>
                                <select name="goods_receipt_note_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="">None / Unlinked</option>
                                    @foreach($goodsReceiptNotes as $grn)
                                        <option value="{{ $grn->id }}">GRN: {{ $grn->grn_number }} (DR: {{ $grn->dr_number ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">File Attachment (PDF or Image, max 15MB) *</label>
                                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.webp"
                                       class="mt-1 block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Remarks / Notes</label>
                                <textarea name="remarks" rows="2" placeholder="Originating courier, inspection stamps, or commercial terms..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="uploadModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                    Upload & Hash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Verify Document Modal --}}
            <div x-show="verifyModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="verifyModalOpen" @click="verifyModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="verifyModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Audit & Verify Document</h3>
                            <button @click="verifyModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/documents/' + verifyDocId + '/verify'" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">Reviewing cryptographic record <span class="font-mono font-bold text-neutral-900" x-text="verifyDocTracking"></span>.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Verification Decision *</label>
                                <select name="status" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="verified">Verified (Approved & Authentic)</option>
                                    <option value="rejected">Rejected (Non-conforming / Illegible)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Verification / Audit Notes</label>
                                <textarea name="verification_notes" rows="3" placeholder="Confirm invoice authenticity, BIR stamps, or explain reason for rejection..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="verifyModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800">Submit Verification</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Supersede Version Modal --}}
            <div x-show="supersedeModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="supersedeModalOpen" @click="supersedeModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="supersedeModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Upload New Revision</h3>
                            <button @click="supersedeModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/documents/' + supersedeDocId + '/supersede'" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-neutral-600">The current version of <span class="font-mono font-bold text-neutral-900" x-text="supersedeDocTracking"></span> will be marked as Superseded. Historical audits remain immutable.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">New File Revision *</label>
                                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.webp"
                                       class="mt-1 block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Revision Reason (Required for Audit) *</label>
                                <textarea name="reason" required rows="3" placeholder="e.g. Supplier issued corrected Sales Invoice with amended VAT amount..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="supersedeModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Publish New Version</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
