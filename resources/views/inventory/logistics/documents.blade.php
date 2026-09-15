<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200">
                    <svg class="h-3 w-3 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                    Digital Archive &amp; Audit Trail
                </span>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">Document Tracking Registry</h1>
                <p class="mt-0.5 text-sm text-neutral-600">Secure SHA-256 cryptographic verification, National Archives (NAP) retention compliance, and non-destructive versioning.</p>
            </div>
            <div class="flex items-center gap-2" x-data>
                @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                <button @click="$dispatch('open-upload-modal')" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-primary-700 transition active:scale-[0.98]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                    <span>Upload Document</span>
                </button>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="py-5" x-data="{
        uploadModalOpen: false,
        verifyModalOpen: false,
        verifyDocId: null,
        verifyDocTracking: '',
        supersedeModalOpen: false,
        supersedeDocId: null,
        supersedeDocTracking: '',
        detailsModalOpen: false,
        selectedDoc: null,
        copiedHash: false,
        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                this.copiedHash = true;
                setTimeout(() => { this.copiedHash = false; }, 2000);
            }
        }
    }"
    @open-upload-modal.window="uploadModalOpen = true">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

            {{-- Flash Notifications --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-3.5 text-sm text-emerald-800 shadow-2xs">
                    <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <div class="font-medium">{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50/90 p-3.5 text-sm text-red-800 shadow-2xs">
                    <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                    <div class="font-medium">{{ session('error') }}</div>
                </div>
            @endif

            {{-- Compact Search & Filter Toolbar --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-3 shadow-2xs">
                <form method="GET" action="{{ route('inventory.logistics.documents') }}" class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                    {{-- Search Input (High Priority Width) --}}
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        </div>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                               placeholder="Search tracking #, reference #, title, or filename..."
                               class="block w-full rounded-lg border-neutral-300 pl-9 pr-3 py-1.5 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                    </div>

                    {{-- Document Type Filter --}}
                    <div class="w-full sm:w-52">
                        <select name="document_type" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            <option value="">All Document Types</option>
                            @foreach($documentTypes as $type)
                                <option value="{{ $type->value }}" {{ request('document_type') === $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }} ({{ $type->abbreviation() }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div class="w-full sm:w-44">
                        <select name="status" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            <option value="">All Statuses</option>
                            <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Pending Audit</option>
                            <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Superseded</option>
                        </select>
                    </div>

                    {{-- Filter Action Buttons --}}
                    <div class="flex items-center gap-1.5">
                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-neutral-900 px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 transition">
                            <svg class="h-3.5 w-3.5 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
                            <span>Filter</span>
                        </button>
                        @if(request()->hasAny(['search', 'document_type', 'status']))
                            <a href="{{ route('inventory.logistics.documents') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white p-1.5 text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 transition" title="Reset Filters">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Documents Registry Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200/90 bg-white shadow-2xs">
                <div class="overflow-x-auto min-w-full">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                        <thead class="bg-neutral-50/90 text-[11px] font-semibold uppercase tracking-wider text-neutral-600">
                            <tr>
                                <th class="px-5 py-3">Document Details</th>
                                <th class="px-5 py-3">Commercial / Reference</th>
                                <th class="px-5 py-3">Archival &amp; Integrity</th>
                                <th class="px-5 py-3">Status &amp; Audit</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white">
                            @forelse($documents as $doc)
                                @php
                                    $abbr = $doc->document_type->abbreviation();
                                    $badgeColor = match($abbr) {
                                        'SI' => 'bg-slate-100 text-slate-800 ring-slate-200',
                                        'DR' => 'bg-blue-50 text-blue-700 ring-blue-200',
                                        'IAR' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                        'COA' => 'bg-purple-50 text-purple-700 ring-purple-200',
                                        'PO' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                        default => 'bg-neutral-100 text-neutral-700 ring-neutral-200',
                                    };
                                    $docData = [
                                        'id' => $doc->id,
                                        'title' => $doc->title,
                                        'tracking_number' => $doc->tracking_number,
                                        'version_number' => $doc->version_number,
                                        'type_label' => $doc->document_type->label(),
                                        'type_abbr' => $abbr,
                                        'reference_number' => $doc->reference_number,
                                        'po_number' => $doc->purchaseOrder?->po_number,
                                        'grn_number' => $doc->goodsReceiptNote?->grn_number,
                                        'dr_number' => $doc->goodsReceiptNote?->dr_number,
                                        'sha256' => $doc->sha256_checksum,
                                        'file_name' => $doc->original_name ?: $doc->file_name,
                                        'file_size' => number_format($doc->file_size_bytes / 1024, 1) . ' KB',
                                        'file_ext' => strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION)),
                                        'retention' => $doc->retention_until?->format('M d, Y') ?? 'Permanent',
                                        'uploaded_by' => $doc->uploadedBy->name ?? 'System',
                                        'uploaded_at' => $doc->created_at->format('M d, Y h:i A'),
                                        'status' => $doc->status,
                                        'verified_by' => $doc->verifiedBy->name ?? null,
                                        'verified_at' => $doc->verified_at?->format('M d, Y h:i A'),
                                        'verification_notes' => $doc->verification_notes,
                                        'remarks' => $doc->remarks,
                                        'download_url' => route('inventory.logistics.documents.download', $doc),
                                    ];
                                @endphp
                                <tr class="hover:bg-neutral-50/80 transition-colors">
                                    {{-- Document Details --}}
                                    <td class="px-5 py-3.5 align-top">
                                        <div class="flex items-start gap-2.5">
                                            <span class="inline-flex shrink-0 items-center justify-center rounded-md px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset {{ $badgeColor }}" title="{{ $doc->document_type->label() }}">
                                                {{ $abbr }}
                                            </span>
                                            <div class="min-w-0">
                                                <button
                                                    type="button"
                                                    @click="selectedDoc = {{ json_encode($docData) }}; detailsModalOpen = true"
                                                    class="text-left font-bold text-neutral-900 hover:text-primary-600 transition"
                                                >
                                                    {{ $doc->title }}
                                                </button>
                                                <div class="mt-0.5 flex items-center gap-1.5 font-mono text-[11px] text-neutral-500">
                                                    <span>{{ $doc->tracking_number }}</span>
                                                    @if($doc->version_number > 1)
                                                        <span class="rounded bg-indigo-50 px-1 py-0.2 text-[10px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200">v{{ $doc->version_number }}</span>
                                                    @endif
                                                </div>
                                                <div class="mt-0.5 text-[11px] text-neutral-400">
                                                    Uploaded by <span class="text-neutral-600 font-medium">{{ $doc->uploadedBy->name ?? 'System' }}</span> • {{ $doc->created_at->format('M d, Y h:i A') }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Commercial / Reference --}}
                                    <td class="px-5 py-3.5 align-top text-xs">
                                        <div class="space-y-0.5">
                                            @if($doc->reference_number)
                                                <div class="text-neutral-700">Ref #: <span class="font-mono font-bold text-neutral-900">{{ $doc->reference_number }}</span></div>
                                            @endif
                                            @if($doc->purchaseOrder)
                                                <div class="text-neutral-600">PO: <span class="font-semibold text-primary-700">{{ $doc->purchaseOrder->po_number }}</span></div>
                                            @endif
                                            @if($doc->goodsReceiptNote)
                                                <div class="text-neutral-600">GRN: <span class="font-mono font-medium text-neutral-800">{{ $doc->goodsReceiptNote->grn_number }}</span></div>
                                            @endif
                                            @if(!$doc->reference_number && !$doc->purchaseOrder && !$doc->goodsReceiptNote)
                                                <span class="inline-block text-neutral-400 italic">General Record</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Archival & Integrity --}}
                                    <td class="px-5 py-3.5 align-top text-xs">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 font-mono text-neutral-700">
                                                <svg class="h-3.5 w-3.5 text-neutral-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                                <span class="tracking-tight">{{ substr($doc->sha256_checksum, 0, 12) }}...</span>
                                                <button
                                                    type="button"
                                                    @click="copyToClipboard('{{ $doc->sha256_checksum }}')"
                                                    class="rounded p-0.5 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 transition"
                                                    title="Copy full SHA-256 hash"
                                                >
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H8.25m7.5 10.5h3.375c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H9.375c-.621 0-1.125.504-1.125 1.125v1.875m7.5 10.5h-6.375A1.125 1.125 0 0 1 8.25 16.125V6.75"/></svg>
                                                </button>
                                            </div>
                                            <div class="text-[11px] text-neutral-500 font-medium">
                                                {{ number_format($doc->file_size_bytes / 1024, 1) }} KB • {{ strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION)) }}
                                            </div>
                                            <div class="text-[10px] text-neutral-400">
                                                NAP: Retain until <span class="font-medium text-neutral-600">{{ $doc->retention_until?->format('Y-m-d') ?? 'Permanent' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Status & Audit --}}
                                    <td class="px-5 py-3.5 align-top">
                                        @if($doc->status === 'verified')
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                    <svg class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    <span>Verified</span>
                                                </span>
                                                <div class="mt-0.5 text-[11px] text-neutral-500">By <span class="font-medium text-neutral-700">{{ $doc->verifiedBy->name ?? 'Auditor' }}</span></div>
                                            </div>
                                        @elseif($doc->status === 'rejected')
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-600/20">
                                                    <svg class="h-3 w-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                                    <span>Rejected</span>
                                                </span>
                                                @if($doc->verification_notes)
                                                    <div class="mt-0.5 max-w-xs truncate text-[11px] text-red-600" title="{{ $doc->verification_notes }}">{{ $doc->verification_notes }}</div>
                                                @endif
                                            </div>
                                        @elseif($doc->status === 'archived')
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-[11px] font-semibold text-neutral-600 ring-1 ring-inset ring-neutral-200">
                                                    <span>Superseded</span>
                                                </span>
                                            </div>
                                        @else
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                    <svg class="h-3 w-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    <span>Pending Audit</span>
                                                </span>
                                                <div class="mt-0.5 text-[11px] text-neutral-400">Awaiting verification</div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Actions Group --}}
                                    <td class="px-5 py-3.5 align-top text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            {{-- Details Inspector Trigger --}}
                                            <button
                                                type="button"
                                                @click="selectedDoc = {{ json_encode($docData) }}; detailsModalOpen = true"
                                                class="rounded-lg border border-neutral-200 bg-white p-1.5 text-neutral-600 shadow-2xs hover:bg-neutral-50 hover:text-neutral-900 transition"
                                                title="View Document Details"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                            </button>

                                            {{-- Download Button --}}
                                            <a href="{{ route('inventory.logistics.documents.download', $doc) }}"
                                               class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 transition"
                                               data-hims-download
                                               data-loading-text="Preparing document..."
                                               data-download-name="{{ $doc->original_name ?: ($doc->file_name ?: 'document') }}"
                                               title="Download verified binary">
                                                <svg class="h-3.5 w-3.5 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-4.5-6L12 15m0 0-4.5-4.5M12 15V3"/></svg>
                                                <span>Download</span>
                                            </a>

                                            {{-- Verify Action (Gated) --}}
                                            @can(\App\Enums\Permission::VerifyLogisticsDocuments->value)
                                                @if($doc->status === 'submitted')
                                                    <button @click="verifyDocId = {{ $doc->id }}; verifyDocTracking = '{{ $doc->tracking_number }}'; verifyModalOpen = true"
                                                            class="rounded-lg bg-neutral-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 transition">
                                                        Verify
                                                    </button>
                                                @endif
                                            @endcan

                                            {{-- Revise Action (Gated) --}}
                                            @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                                                @if($doc->status !== 'archived')
                                                    <button @click="supersedeDocId = {{ $doc->id }}; supersedeDocTracking = '{{ $doc->tracking_number }}'; supersedeModalOpen = true"
                                                            class="rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-xs font-medium text-neutral-600 shadow-2xs hover:bg-neutral-50 hover:text-neutral-900 transition"
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
                                    <td colspan="5" class="px-6 py-12 text-center text-xs">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                        </div>
                                        <div class="mt-3 font-semibold text-neutral-800">No documents found</div>
                                        <p class="mt-1 text-neutral-500">No logistics documents match your current filter criteria.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($documents->hasPages())
                    <div class="border-t border-neutral-200/90 bg-neutral-50/50 px-5 py-3">
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>

            {{-- Progressive Disclosure: Document Details Modal --}}
            <div x-show="detailsModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="details-modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="detailsModalOpen"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="detailsModalOpen = false" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-2xs transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="detailsModalOpen"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">

                        <div class="flex items-start justify-between border-b border-neutral-200 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-800" x-text="selectedDoc?.type_abbr"></span>
                                    <span class="text-xs font-mono font-medium text-neutral-500" x-text="selectedDoc?.tracking_number"></span>
                                    <template x-if="selectedDoc?.version_number > 1">
                                        <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200" x-text="'v' + selectedDoc?.version_number"></span>
                                    </template>
                                </div>
                                <h3 class="mt-1 text-lg font-bold text-neutral-900" x-text="selectedDoc?.title"></h3>
                            </div>
                            <button @click="detailsModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="mt-4 space-y-4 text-xs">
                            {{-- Cryptographic Integrity Section --}}
                            <div class="rounded-xl border border-neutral-200/90 bg-neutral-50/60 p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500">SHA-256 Cryptographic Checksum</span>
                                    <span class="text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded ring-1 ring-emerald-600/20">Immutable Hash</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 bg-white p-2 font-mono text-[11px] text-neutral-800">
                                    <span class="break-all select-all" x-text="selectedDoc?.sha256"></span>
                                    <button
                                        type="button"
                                        @click="copyToClipboard(selectedDoc?.sha256)"
                                        class="shrink-0 inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-200 transition"
                                    >
                                        <span x-text="copiedHash ? 'Copied!' : 'Copy'"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Commercial & File Specifications --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-neutral-200/90 p-3.5 space-y-2">
                                    <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500">Commercial Links</span>
                                    <div class="space-y-1 text-neutral-700">
                                        <div>Reference: <span class="font-mono font-bold text-neutral-900" x-text="selectedDoc?.reference_number || 'None'"></span></div>
                                        <div>Purchase Order: <span class="font-semibold text-primary-700" x-text="selectedDoc?.po_number || 'Unlinked'"></span></div>
                                        <div>Goods Receipt: <span class="font-mono font-medium text-neutral-800" x-text="selectedDoc?.grn_number || 'Unlinked'"></span></div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-neutral-200/90 p-3.5 space-y-2">
                                    <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500">File &amp; Retention</span>
                                    <div class="space-y-1 text-neutral-700">
                                        <div>Filename: <span class="font-mono text-neutral-900 truncate block" x-text="selectedDoc?.file_name"></span></div>
                                        <div>Specs: <span class="font-semibold text-neutral-800" x-text="selectedDoc?.file_size + ' • ' + selectedDoc?.file_ext"></span></div>
                                        <div>NAP Retention: <span class="font-medium text-neutral-800" x-text="selectedDoc?.retention"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Audit Trail & Verification --}}
                            <div class="rounded-xl border border-neutral-200/90 p-3.5 space-y-2">
                                <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500">Audit &amp; Verification Trail</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-neutral-700">
                                    <div>Uploaded by: <span class="font-semibold text-neutral-900" x-text="selectedDoc?.uploaded_by"></span></div>
                                    <div>Timestamp: <span class="text-neutral-600" x-text="selectedDoc?.uploaded_at"></span></div>
                                    <div>Audit Status: <span class="font-bold capitalize" x-text="selectedDoc?.status"></span></div>
                                    <div>Verified by: <span class="font-semibold text-neutral-900" x-text="selectedDoc?.verified_by || 'Awaiting verification'"></span></div>
                                </div>
                                <template x-if="selectedDoc?.verification_notes">
                                    <div class="mt-2 rounded-lg bg-neutral-50 p-2 text-neutral-700 border border-neutral-200">
                                        <span class="font-semibold">Audit Notes:</span>
                                        <p class="mt-0.5" x-text="selectedDoc?.verification_notes"></p>
                                    </div>
                                </template>
                                <template x-if="selectedDoc?.remarks">
                                    <div class="mt-2 rounded-lg bg-neutral-50 p-2 text-neutral-700 border border-neutral-200">
                                        <span class="font-semibold">Remarks:</span>
                                        <p class="mt-0.5" x-text="selectedDoc?.remarks"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2 border-t border-neutral-200 pt-4">
                            <button type="button" @click="detailsModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                Close
                            </button>
                            <a :href="selectedDoc?.download_url"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-primary-700"
                               data-hims-download
                               data-loading-text="Preparing document...">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-4.5-6L12 15m0 0-4.5-4.5M12 15V3"/></svg>
                                <span>Download Verified Binary</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Upload Modal --}}
            @can(\App\Enums\Permission::ManageLogisticsRecords->value)
            <div x-show="uploadModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="upload-modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="uploadModalOpen"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="uploadModalOpen = false" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-2xs transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="uploadModalOpen"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="inline-block w-full max-w-xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <div>
                                <h3 class="text-base font-bold text-neutral-900">Upload Logistics Document</h3>
                                <p class="text-xs text-neutral-500">Registers cryptographic record with automatic SHA-256 calculation.</p>
                            </div>
                            <button @click="uploadModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('inventory.logistics.documents.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-3.5">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Document Type *</label>
                                <select name="document_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }} ({{ $type->abbreviation() }}) - {{ $type->napRetentionYears() }}yr retention</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Document Title *</label>
                                <input type="text" name="title" required placeholder="e.g. Zuellig Pharma Delivery Receipt DR-99482"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Document / Reference #</label>
                                    <input type="text" name="document_number" placeholder="e.g. SI-2026-00441"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Linked Purchase Order</label>
                                    <select name="purchase_order_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                        <option value="">None / Unlinked</option>
                                        @foreach($purchaseOrders as $po)
                                            <option value="{{ $po->id }}">PO: {{ $po->po_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Linked Goods Receipt (GRN)</label>
                                <select name="goods_receipt_note_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                    <option value="">None / Unlinked</option>
                                    @foreach($goodsReceiptNotes as $grn)
                                        <option value="{{ $grn->id }}">GRN: {{ $grn->grn_number }} (DR: {{ $grn->dr_number ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">File Attachment (PDF or Image, max 15MB) *</label>
                                    <span class="text-[11px] text-primary-700 font-medium">Camera Photo Supported</span>
                                </div>
                                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.webp" capture="environment"
                                       class="mt-1 block w-full text-xs text-neutral-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100 cursor-pointer">
                                <p class="mt-1 text-[11px] text-neutral-400">On mobile or tablet devices, tap to take a photo directly with the camera.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Remarks / Notes</label>
                                <textarea name="remarks" rows="2" placeholder="Originating courier, inspection stamps, or commercial terms..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-5 flex justify-end gap-2 border-t border-neutral-200 pt-3.5">
                                <button type="button" @click="uploadModalOpen = false" class="rounded-lg border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-primary-700 transition">
                                    Upload &amp; Hash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan

            {{-- Verify Document Modal --}}
            @can(\App\Enums\Permission::VerifyLogisticsDocuments->value)
            <div x-show="verifyModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="verify-modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="verifyModalOpen" @click="verifyModalOpen = false" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-2xs transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="verifyModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Audit &amp; Verify Document</h3>
                            <button @click="verifyModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/documents/' + verifyDocId + '/verify'" method="POST" class="mt-4 space-y-3.5">
                            @csrf
                            <p class="text-xs text-neutral-600">Reviewing cryptographic record <span class="font-mono font-bold text-neutral-900" x-text="verifyDocTracking"></span>.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Verification Decision *</label>
                                <select name="status" required class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                    <option value="verified">Verified (Approved &amp; Authentic)</option>
                                    <option value="rejected">Rejected (Non-conforming / Illegible)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Verification / Audit Notes</label>
                                <textarea name="verification_notes" rows="3" placeholder="Confirm invoice authenticity, BIR stamps, or explain reason for rejection..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-5 flex justify-end gap-2 border-t border-neutral-200 pt-3.5">
                                <button type="button" @click="verifyModalOpen = false" class="rounded-lg border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 transition">Submit Verification</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan

            {{-- Supersede Version Modal --}}
            @can(\App\Enums\Permission::ManageLogisticsRecords->value)
            <div x-show="supersedeModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="supersede-modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="supersedeModalOpen" @click="supersedeModalOpen = false" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-2xs transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="supersedeModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Upload New Revision</h3>
                            <button @click="supersedeModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/documents/' + supersedeDocId + '/supersede'" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3.5">
                            @csrf
                            <p class="text-xs text-neutral-600">The current version of <span class="font-mono font-bold text-neutral-900" x-text="supersedeDocTracking"></span> will be marked as Superseded. Historical audits remain immutable.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">New File Revision *</label>
                                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.webp"
                                       class="mt-1 block w-full text-xs text-neutral-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100 cursor-pointer">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Revision Reason (Required for Audit) *</label>
                                <textarea name="reason" required rows="3" placeholder="e.g. Supplier issued corrected Sales Invoice with amended VAT amount..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                            </div>

                            <div class="mt-5 flex justify-end gap-2 border-t border-neutral-200 pt-3.5">
                                <button type="button" @click="supersedeModalOpen = false" class="rounded-lg border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 transition">Publish New Version</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan

        </div>
    </div>
</x-app-layout>
