<x-app-layout full-width>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-800/60">
                    <x-ui.icon name="shield-check" class="h-3 w-3 text-primary-600 dark:text-primary-400" />
                    Digital Archive &amp; Audit Trail
                </span>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-neutral-100">Document Tracking Registry</h1>
            </div>
            <div class="flex items-center gap-2" x-data>
                @can(\App\Enums\Permission::ManageLogisticsRecords->value)
                <button @click="$dispatch('open-upload-modal')" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-primary-700 dark:hover:bg-primary-500 transition active:scale-[0.98]">
                    <x-ui.icon name="arrow-up-tray" class="h-4 w-4" />
                    <span>Upload Document</span>
                </button>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="space-y-6" x-data="{
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
            <div class="rounded-xl border border-neutral-200/90 bg-white p-3 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <form method="GET" action="{{ route('inventory.logistics.documents') }}" class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                    {{-- Search Input (High Priority Width) --}}
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 dark:text-neutral-500">
                            <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                        </div>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                               placeholder="Search tracking #, reference #, title, or filename..."
                               class="block w-full rounded-lg border-neutral-300 pl-9 pr-3 py-1.5 text-xs text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500">
                    </div>

                    {{-- Document Type Filter --}}
                    <div class="w-full sm:w-52">
                        <select name="document_type" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
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
                        <select name="status" class="block w-full rounded-lg border-neutral-300 py-1.5 text-xs text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                            <option value="">All Statuses</option>
                            <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Pending Audit</option>
                            <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Superseded</option>
                        </select>
                    </div>

                    {{-- Filter Action Buttons --}}
                    <div class="flex items-center gap-1.5">
                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-neutral-900 px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 dark:bg-primary-600 dark:hover:bg-primary-500 transition">
                            <x-ui.icon name="funnel" class="h-3.5 w-3.5 text-neutral-300 dark:text-white" />
                            <span>Filter</span>
                        </button>
                        @if(request()->hasAny(['search', 'document_type', 'status']))
                            <a href="{{ route('inventory.logistics.documents') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white p-1.5 text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100 transition" title="Reset Filters">
                                <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @php
                $canVerify = auth()->user()?->can(\App\Enums\Permission::VerifyLogisticsDocuments->value);
                $canManage = auth()->user()?->can(\App\Enums\Permission::ManageLogisticsRecords->value);

                if ($canVerify && $canManage) {
                    $actionsGridCols = 'grid-cols-[2rem_6rem_4rem_4rem]';
                    $actionsColClass = 'w-80 min-w-80';
                } elseif ($canVerify || $canManage) {
                    $actionsGridCols = 'grid-cols-[2rem_6rem_4rem]';
                    $actionsColClass = 'w-64 min-w-64';
                } else {
                    $actionsGridCols = 'grid-cols-[2rem_6rem]';
                    $actionsColClass = 'w-48 min-w-48';
                }
            @endphp

            {{-- Documents Registry Table --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200/90 bg-white shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="overflow-x-auto min-w-full">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-xs dark:divide-neutral-800">
                        <thead class="bg-neutral-50/90 text-[11px] font-semibold uppercase tracking-wider text-neutral-600 dark:bg-neutral-800/80 dark:text-neutral-400">
                            <tr>
                                <th class="px-5 py-3">Document Details</th>
                                <th class="px-5 py-3">Commercial / Reference</th>
                                <th class="px-5 py-3">Archival &amp; Integrity</th>
                                <th class="px-5 py-3">Status &amp; Audit</th>
                                <th class="px-5 py-3 text-right {{ $actionsColClass }}">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                            @forelse($documents as $doc)
                                @php
                                    $abbr = $doc->document_type->abbreviation();
                                    $badgeColor = match($abbr) {
                                        'SI' => 'bg-slate-100 text-slate-800 ring-slate-200 dark:bg-slate-900/80 dark:text-slate-300 dark:ring-slate-700',
                                        'DR' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/70 dark:text-blue-300 dark:ring-blue-800',
                                        'IAR' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/70 dark:text-emerald-300 dark:ring-emerald-800',
                                        'COA' => 'bg-purple-50 text-purple-700 ring-purple-200 dark:bg-purple-950/70 dark:text-purple-300 dark:ring-purple-800',
                                        'PO' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/70 dark:text-amber-300 dark:ring-amber-800',
                                        default => 'bg-neutral-100 text-neutral-700 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
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
                                <tr class="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50 transition-colors">
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
                                                    class="text-left font-bold text-neutral-900 hover:text-primary-600 dark:text-neutral-100 dark:hover:text-primary-400 transition"
                                                >
                                                    {{ $doc->title }}
                                                </button>
                                                <div class="mt-0.5 flex items-center gap-1.5 font-mono text-[11px] text-neutral-500 dark:text-neutral-400">
                                                    <span>{{ $doc->tracking_number }}</span>
                                                    @if($doc->version_number > 1)
                                                        <span class="rounded bg-indigo-50 px-1 py-0.2 text-[10px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:ring-indigo-800/60">v{{ $doc->version_number }}</span>
                                                    @endif
                                                </div>
                                                <div class="mt-0.5 text-[11px] text-neutral-400 dark:text-neutral-500">
                                                    Uploaded by <span class="text-neutral-600 font-medium dark:text-neutral-300">{{ $doc->uploadedBy->name ?? 'System' }}</span> • {{ $doc->created_at->format('M d, Y h:i A') }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Commercial / Reference --}}
                                    <td class="px-5 py-3.5 align-top text-xs">
                                        <div class="space-y-0.5">
                                            @if($doc->reference_number)
                                                <div class="text-neutral-700 dark:text-neutral-300">Ref #: <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100">{{ $doc->reference_number }}</span></div>
                                            @endif
                                            @if($doc->purchaseOrder)
                                                <div class="text-neutral-600 dark:text-neutral-400">PO: <span class="font-semibold text-primary-700 dark:text-primary-400">{{ $doc->purchaseOrder->po_number }}</span></div>
                                            @endif
                                            @if($doc->goodsReceiptNote)
                                                <div class="text-neutral-600 dark:text-neutral-400">GRN: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-200">{{ $doc->goodsReceiptNote->grn_number }}</span></div>
                                            @endif
                                            @if(!$doc->reference_number && !$doc->purchaseOrder && !$doc->goodsReceiptNote)
                                                <span class="inline-block text-neutral-400 dark:text-neutral-500 italic">General Record</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Archival & Integrity --}}
                                    <td class="px-5 py-3.5 align-top text-xs">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 font-mono text-neutral-700 dark:text-neutral-300">
                                                <x-ui.icon name="lock-closed" class="h-3.5 w-3.5 text-neutral-400 dark:text-neutral-500 shrink-0" />
                                                <span class="tracking-tight">{{ substr($doc->sha256_checksum, 0, 12) }}...</span>
                                                <button
                                                    type="button"
                                                    @click="copyToClipboard('{{ $doc->sha256_checksum }}')"
                                                    class="rounded p-0.5 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition"
                                                    title="Copy full SHA-256 hash"
                                                >
                                                    <x-ui.icon name="document-duplicate" class="h-3 w-3" />
                                                </button>
                                            </div>
                                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 font-medium">
                                                {{ number_format($doc->file_size_bytes / 1024, 1) }} KB • {{ strtoupper(pathinfo($doc->file_name, PATHINFO_EXTENSION)) }}
                                            </div>
                                            <div class="text-[10px] text-neutral-400 dark:text-neutral-500">
                                                NAP: Retain until <span class="font-medium text-neutral-600 dark:text-neutral-300">{{ $doc->retention_until?->format('Y-m-d') ?? 'Permanent' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Status & Audit --}}
                                    <td class="px-5 py-3.5 align-top">
                                        @if($doc->status === 'verified')
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/40">
                                                    <x-ui.icon name="check-circle" class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                                    <span>Verified</span>
                                                </span>
                                                <div class="mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">By <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $doc->verifiedBy->name ?? 'Auditor' }}</span></div>
                                            </div>
                                        @elseif($doc->status === 'rejected')
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-rose-950/60 dark:text-rose-300 dark:ring-rose-800/40">
                                                    <x-ui.icon name="exclamation-circle" class="h-3 w-3 text-red-600 dark:text-rose-400" />
                                                    <span>Rejected</span>
                                                </span>
                                                @if($doc->verification_notes)
                                                    <div class="mt-0.5 max-w-xs truncate text-[11px] text-red-600 dark:text-rose-400" title="{{ $doc->verification_notes }}">{{ $doc->verification_notes }}</div>
                                                @endif
                                            </div>
                                        @elseif($doc->status === 'archived')
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-[11px] font-semibold text-neutral-600 ring-1 ring-inset ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700">
                                                    <span>Superseded</span>
                                                </span>
                                            </div>
                                        @else
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800/40">
                                                    <x-ui.icon name="clock" class="h-3 w-3 text-amber-600 dark:text-amber-400" />
                                                    <span>Pending Audit</span>
                                                </span>
                                                <div class="mt-0.5 text-[11px] text-neutral-400 dark:text-neutral-500">Awaiting verification</div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Actions Group --}}
                                    <td class="px-5 py-3.5 align-top text-right whitespace-nowrap {{ $actionsColClass }}">
                                        <div class="inline-grid {{ $actionsGridCols }} items-center gap-2 text-xs">
                                            {{-- Slot 1: Details Inspector Trigger --}}
                                            <div class="flex items-center justify-center">
                                                <button
                                                    type="button"
                                                    @click="selectedDoc = {{ json_encode($docData) }}; detailsModalOpen = true"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-300 bg-white text-neutral-600 shadow-2xs hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100 transition"
                                                    title="View Document Details"
                                                >
                                                    <x-ui.icon name="eye" class="h-4 w-4" />
                                                </button>
                                            </div>

                                            {{-- Slot 2: Download Button --}}
                                            <div class="flex items-center justify-center">
                                                <a href="{{ route('inventory.logistics.documents.download', $doc) }}"
                                                   class="inline-flex h-8 w-24 items-center justify-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-2 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700 dark:hover:text-neutral-100 transition"
                                                   data-hims-download
                                                   data-loading-text="Preparing document..."
                                                   data-download-name="{{ $doc->original_name ?: ($doc->file_name ?: 'document') }}"
                                                   title="Download verified binary">
                                                    <x-ui.icon name="arrow-down-tray" class="h-3.5 w-3.5 text-neutral-500 dark:text-neutral-400 shrink-0" />
                                                    <span>Download</span>
                                                </a>
                                            </div>

                                            {{-- Slot 3: Verify Action (Gated) --}}
                                            @if($canVerify)
                                                <div class="flex items-center justify-center">
                                                    @if($doc->status === 'submitted')
                                                        <button
                                                            type="button"
                                                            @click="verifyDocId = {{ $doc->id }}; verifyDocTracking = '{{ $doc->tracking_number }}'; verifyModalOpen = true"
                                                            class="inline-flex h-8 w-16 items-center justify-center rounded-lg bg-neutral-900 px-2 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 dark:bg-primary-600 dark:hover:bg-primary-500 transition"
                                                        >
                                                            Verify
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Slot 4: Revise Action (Gated) --}}
                                            @if($canManage)
                                                <div class="flex items-center justify-center">
                                                    @if($doc->status !== 'archived')
                                                        <button
                                                            type="button"
                                                            @click="supersedeDocId = {{ $doc->id }}; supersedeDocTracking = '{{ $doc->tracking_number }}'; supersedeModalOpen = true"
                                                            class="inline-flex h-8 w-16 items-center justify-center rounded-lg border border-neutral-300 bg-white px-2 text-xs font-medium text-neutral-600 shadow-2xs hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-neutral-100 transition"
                                                            title="Upload Superseding Version"
                                                        >
                                                            Revise
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-xs">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500">
                                            <x-ui.icon name="document-text" class="h-6 w-6" />
                                        </div>
                                        <div class="mt-3 font-semibold text-neutral-800 dark:text-neutral-200">No documents found</div>
                                        <p class="mt-1 text-neutral-500 dark:text-neutral-400">No logistics documents match your current filter criteria.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($documents->hasPages())
                    <div class="border-t border-neutral-200/90 bg-neutral-50/50 px-5 py-3 dark:border-neutral-800 dark:bg-neutral-900/50">
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
                         class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle dark:bg-neutral-900 dark:border dark:border-neutral-800">

                        <div class="flex items-start justify-between border-b border-neutral-200 pb-4 dark:border-neutral-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200" x-text="selectedDoc?.type_abbr"></span>
                                    <span class="text-xs font-mono font-medium text-neutral-500 dark:text-neutral-400" x-text="selectedDoc?.tracking_number"></span>
                                    <template x-if="selectedDoc?.version_number > 1">
                                        <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:ring-indigo-800/60" x-text="'v' + selectedDoc?.version_number"></span>
                                    </template>
                                </div>
                                <h3 class="mt-1 text-lg font-bold text-neutral-900 dark:text-neutral-100" x-text="selectedDoc?.title"></h3>
                            </div>
                            <button @click="detailsModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition">
                                <x-ui.icon name="x-mark" class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="mt-4 space-y-4 text-xs">
                            {{-- Cryptographic Integrity Section --}}
                            <div class="rounded-xl border border-neutral-200/90 bg-neutral-50/60 p-3.5 space-y-2 dark:border-neutral-800 dark:bg-neutral-850/50">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500 dark:text-neutral-400">SHA-256 Cryptographic Checksum</span>
                                    <span class="text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded ring-1 ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/40">Immutable Hash</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 bg-white p-2 font-mono text-[11px] text-neutral-800 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                                    <span class="break-all select-all" x-text="selectedDoc?.sha256"></span>
                                    <button
                                        type="button"
                                        @click="copyToClipboard(selectedDoc?.sha256)"
                                        class="shrink-0 inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 transition"
                                    >
                                        <span x-text="copiedHash ? 'Copied!' : 'Copy'"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Commercial & File Specifications --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-neutral-200/90 p-3.5 space-y-2 dark:border-neutral-800 dark:bg-neutral-850/40">
                                    <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500 dark:text-neutral-400">Commercial Links</span>
                                    <div class="space-y-1 text-neutral-700 dark:text-neutral-300">
                                        <div>Reference: <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="selectedDoc?.reference_number || 'None'"></span></div>
                                        <div>Purchase Order: <span class="font-semibold text-primary-700 dark:text-primary-400" x-text="selectedDoc?.po_number || 'Unlinked'"></span></div>
                                        <div>Goods Receipt: <span class="font-mono font-medium text-neutral-800 dark:text-neutral-200" x-text="selectedDoc?.grn_number || 'Unlinked'"></span></div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-neutral-200/90 p-3.5 space-y-2 dark:border-neutral-800 dark:bg-neutral-850/40">
                                    <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500 dark:text-neutral-400">File &amp; Retention</span>
                                    <div class="space-y-1 text-neutral-700 dark:text-neutral-300">
                                        <div>Filename: <span class="font-mono text-neutral-900 dark:text-neutral-100 truncate block" x-text="selectedDoc?.file_name"></span></div>
                                        <div>Specs: <span class="font-semibold text-neutral-800 dark:text-neutral-200" x-text="selectedDoc?.file_size + ' • ' + selectedDoc?.file_ext"></span></div>
                                        <div>NAP Retention: <span class="font-medium text-neutral-800 dark:text-neutral-200" x-text="selectedDoc?.retention"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Audit Trail & Verification --}}
                            <div class="rounded-xl border border-neutral-200/90 p-3.5 space-y-2 dark:border-neutral-800 dark:bg-neutral-850/40">
                                <span class="font-semibold uppercase tracking-wider text-[11px] text-neutral-500 dark:text-neutral-400">Audit &amp; Verification Trail</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-neutral-700 dark:text-neutral-300">
                                    <div>Uploaded by: <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="selectedDoc?.uploaded_by"></span></div>
                                    <div>Timestamp: <span class="text-neutral-600 dark:text-neutral-400" x-text="selectedDoc?.uploaded_at"></span></div>
                                    <div>Audit Status: <span class="font-bold capitalize" x-text="selectedDoc?.status"></span></div>
                                    <div>Verified by: <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="selectedDoc?.verified_by || 'Awaiting verification'"></span></div>
                                </div>
                                <template x-if="selectedDoc?.verification_notes">
                                    <div class="mt-2 rounded-lg bg-neutral-50 p-2 text-neutral-700 border border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        <span class="font-semibold">Audit Notes:</span>
                                        <p class="mt-0.5" x-text="selectedDoc?.verification_notes"></p>
                                    </div>
                                </template>
                                <template x-if="selectedDoc?.remarks">
                                    <div class="mt-2 rounded-lg bg-neutral-50 p-2 text-neutral-700 border border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        <span class="font-semibold">Remarks:</span>
                                        <p class="mt-0.5" x-text="selectedDoc?.remarks"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                            <button type="button" @click="detailsModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">
                                Close
                            </button>
                            <a :href="selectedDoc?.download_url"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-primary-700 dark:hover:bg-primary-500 transition"
                               data-hims-download
                               data-loading-text="Preparing document...">
                                <x-ui.icon name="arrow-down-tray" class="h-3.5 w-3.5" />
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
                         class="inline-block w-full max-w-xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle dark:bg-neutral-900 dark:border dark:border-neutral-800">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                            <div>
                                <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Upload Logistics Document</h3>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Registers cryptographic record with automatic SHA-256 calculation.</p>
                            </div>
                            <button @click="uploadModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('inventory.logistics.documents.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-3.5">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Document Type *</label>
                                <select name="document_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }} ({{ $type->abbreviation() }}) - {{ $type->napRetentionYears() }}yr retention</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Document Title *</label>
                                <input type="text" name="title" required placeholder="e.g. Zuellig Pharma Delivery Receipt DR-99482"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Document / Reference #</label>
                                    <input type="text" name="document_number" placeholder="e.g. SI-2026-00441"
                                           class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Linked Purchase Order</label>
                                    <select name="purchase_order_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        <option value="">None / Unlinked</option>
                                        @foreach($purchaseOrders as $po)
                                            <option value="{{ $po->id }}">PO: {{ $po->po_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Linked Goods Receipt (GRN)</label>
                                <select name="goods_receipt_note_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                    <option value="">None / Unlinked</option>
                                    @foreach($goodsReceiptNotes as $grn)
                                        <option value="{{ $grn->id }}">GRN: {{ $grn->grn_number }} (DR: {{ $grn->dr_number ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">File Attachment (PDF or Image, max 15MB) *</label>
                                    <span class="text-[11px] text-primary-700 dark:text-primary-400 font-medium">Camera Photo Supported</span>
                                </div>
                                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.webp" capture="environment"
                                       class="mt-1 block w-full text-xs text-neutral-500 dark:text-neutral-400 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/60 dark:file:text-primary-300 cursor-pointer">
                                <p class="mt-1 text-[11px] text-neutral-400 dark:text-neutral-500">On mobile or tablet devices, tap to take a photo directly with the camera.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Remarks / Notes</label>
                                <textarea name="remarks" rows="2" placeholder="Originating courier, inspection stamps, or commercial terms..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"></textarea>
                            </div>

                            <div class="mt-5 flex justify-end gap-2 border-t border-neutral-200 pt-3.5 dark:border-neutral-800">
                                <button type="button" @click="uploadModalOpen = false" class="rounded-lg border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-primary-700 dark:hover:bg-primary-500 transition">
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

                    <div x-show="verifyModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle dark:bg-neutral-900 dark:border dark:border-neutral-800">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                            <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Audit &amp; Verify Document</h3>
                            <button @click="verifyModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/documents/' + verifyDocId + '/verify'" method="POST" class="mt-4 space-y-3.5"
                              data-confirm-title="Verify logistics document"
                              data-confirm-message="Are you sure you want to record this document verification audit decision?"
                              data-confirm-label="Submit Verification">
                            @csrf
                            <p class="text-xs text-neutral-600 dark:text-neutral-400">Reviewing cryptographic record <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="verifyDocTracking"></span>.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Verification Decision *</label>
                                <select name="status" required class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                    <option value="verified">Verified (Approved &amp; Authentic)</option>
                                    <option value="rejected">Rejected (Non-conforming / Illegible)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Verification / Audit Notes</label>
                                <textarea name="verification_notes" rows="3" placeholder="Confirm invoice authenticity, BIR stamps, or explain reason for rejection..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"></textarea>
                            </div>

                            <div class="mt-5 flex justify-end gap-2 border-t border-neutral-200 pt-3.5 dark:border-neutral-800">
                                <button type="button" @click="verifyModalOpen = false" class="rounded-lg border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">Cancel</button>
                                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 dark:bg-primary-600 dark:hover:bg-primary-500 transition">Submit Verification</button>
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

                    <div x-show="supersedeModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle dark:bg-neutral-900 dark:border dark:border-neutral-800">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                            <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Upload New Revision</h3>
                            <button @click="supersedeModalOpen = false" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:text-neutral-500 dark:hover:text-neutral-300 dark:hover:bg-neutral-800 transition">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/documents/' + supersedeDocId + '/supersede'" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3.5"
                              data-confirm-title="Supersede logistics document"
                              data-confirm-message="Are you sure you want to supersede this document with a new revision? The current version will be archived as superseded."
                              data-confirm-label="Upload Revision"
                              data-confirm-variant="warning">
                            @csrf
                            <p class="text-xs text-neutral-600 dark:text-neutral-400">The current version of <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="supersedeDocTracking"></span> will be marked as Superseded. Historical audits remain immutable.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">New File Revision *</label>
                                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.webp"
                                       class="mt-1 block w-full text-xs text-neutral-500 dark:text-neutral-400 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/60 dark:file:text-primary-300 cursor-pointer">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Revision Reason (Required for Audit) *</label>
                                <textarea name="reason" required rows="3" placeholder="e.g. Supplier issued corrected Sales Invoice with amended VAT amount..."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 text-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"></textarea>
                            </div>

                            <div class="mt-5 flex justify-end gap-2 border-t border-neutral-200 pt-3.5 dark:border-neutral-800">
                                <button type="button" @click="supersedeModalOpen = false" class="rounded-lg border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition">Cancel</button>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 dark:hover:bg-indigo-500 transition">Publish New Version</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
        </div>
</x-app-layout>
