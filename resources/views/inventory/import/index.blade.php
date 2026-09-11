<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-sky-700">
                    <a href="{{ route('inventory.items') }}" class="hover:underline">Inventory</a>
                    <span>/</span>
                    <span>Data Import Center</span>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ __('Data Ingress & System Import') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Bulk-load master catalogues, storage topology, and vendor records using CSV, Excel (.xlsx/.xls), or JSON with strict pre-commit validation.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.items') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Back to Catalogue</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="dataImporter({
        previewUrl: '{{ route('inventory.import.preview') }}',
        commitUrl: '{{ route('inventory.import.commit') }}',
        templateUrl: '{{ route('inventory.import.template') }}',
        csrfToken: '{{ csrf_token() }}',
        initialTarget: '{{ $canItems ? 'items' : ($canLocations ? 'locations' : 'suppliers') }}'
    })">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Flash Messages / Toast Feedback --}}
            <div x-show="errorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm" role="alert">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div class="flex-1">
                        <h4 class="font-semibold text-rose-900">Validation Notice</h4>
                        <div class="mt-1" x-text="errorMessage"></div>
                    </div>
                    <button type="button" @click="errorMessage = ''" class="text-rose-500 hover:text-rose-700">
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div x-show="successMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm" role="alert">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div class="flex-1">
                        <h4 class="font-semibold text-emerald-900">Import Complete</h4>
                        <div class="mt-1" x-text="successMessage"></div>
                        <div class="mt-3 flex items-center gap-3">
                            <template x-if="lastCommittedTarget === 'items'">
                                <a href="{{ route('inventory.items') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 underline hover:text-emerald-900">
                                    <span>View Inventory Items</span>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </template>
                            <template x-if="lastCommittedTarget === 'locations'">
                                <a href="{{ route('inventory.storage-locations') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 underline hover:text-emerald-900">
                                    <span>View Storage Locations</span>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </template>
                            <template x-if="lastCommittedTarget === 'suppliers'">
                                <a href="{{ route('inventory.suppliers') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 underline hover:text-emerald-900">
                                    <span>View Suppliers Directory</span>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </template>
                        </div>
                    </div>
                    <button type="button" @click="successMessage = ''" class="text-emerald-500 hover:text-emerald-700">
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Step 1: Configuration & Upload Box --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5">
                    <div>
                        <span class="inline-flex items-center rounded-md bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Step 1</span>
                        <h3 class="mt-1.5 text-lg font-bold text-slate-900">Select Dataset & Upload Source File</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">Formats:</span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">CSV</span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Excel (.xlsx / .xls)</span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">JSON</span>
                    </div>
                </div>

                {{-- Target Module Cards --}}
                <div class="mt-6">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Target Inventory Module</label>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        @if ($canItems)
                        <button
                            type="button"
                            @click="selectTarget('items')"
                            :class="target === 'items' ? 'border-sky-500 ring-2 ring-sky-500/20 bg-sky-50/50' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                            class="relative flex flex-col rounded-xl border p-4 text-left transition"
                        >
                            <div class="flex items-center justify-between">
                                <span class="rounded-lg bg-sky-100 p-2 text-sky-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </span>
                                <span class="text-xs font-medium text-slate-500">{{ number_format($totalItems) }} recorded</span>
                            </div>
                            <h4 class="mt-3 font-semibold text-slate-900">Inventory Items</h4>
                            <p class="mt-1 text-xs text-slate-500 leading-relaxed">SKU, Description, Category, Unit, Unit Cost, Reorder Levels, and Cold Chain specs.</p>
                        </button>
                        @endif

                        @if ($canLocations)
                        <button
                            type="button"
                            @click="selectTarget('locations')"
                            :class="target === 'locations' ? 'border-sky-500 ring-2 ring-sky-500/20 bg-sky-50/50' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                            class="relative flex flex-col rounded-xl border p-4 text-left transition"
                        >
                            <div class="flex items-center justify-between">
                                <span class="rounded-lg bg-indigo-100 p-2 text-indigo-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </span>
                                <span class="text-xs font-medium text-slate-500">{{ number_format($totalLocations) }} recorded</span>
                            </div>
                            <h4 class="mt-3 font-semibold text-slate-900">Storage Locations</h4>
                            <p class="mt-1 text-xs text-slate-500 leading-relaxed">Warehouses, Zones, Racks, Shelves, Cold Vaults, and volumetric capacity limits.</p>
                        </button>
                        @endif

                        @if ($canSuppliers)
                        <button
                            type="button"
                            @click="selectTarget('suppliers')"
                            :class="target === 'suppliers' ? 'border-sky-500 ring-2 ring-sky-500/20 bg-sky-50/50' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                            class="relative flex flex-col rounded-xl border p-4 text-left transition"
                        >
                            <div class="flex items-center justify-between">
                                <span class="rounded-lg bg-teal-100 p-2 text-teal-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </span>
                                <span class="text-xs font-medium text-slate-500">{{ number_format($totalSuppliers) }} recorded</span>
                            </div>
                            <h4 class="mt-3 font-semibold text-slate-900">Suppliers &amp; Vendors</h4>
                            <p class="mt-1 text-xs text-slate-500 leading-relaxed">Vendor Profiles, Tax Identification Numbers (TIN), Payment Terms, and Contacts.</p>
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Import Mode & Templates Grid --}}
                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {{-- Mode Selector --}}
                    <div class="lg:col-span-6">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Import Mode</label>
                        <div class="mt-2 space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition" :class="mode === 'create_only' ? 'border-sky-500 bg-sky-50/40' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" x-model="mode" value="create_only" class="mt-0.5 text-sky-600 focus:ring-sky-500">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">Create Only (Strict Anti-Overwrite)</div>
                                    <div class="text-xs text-slate-500">Rejects rows if their unique key (SKU, Code, or Name) already exists in HIMS.</div>
                                </div>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition" :class="mode === 'update_or_create' ? 'border-sky-500 bg-sky-50/40' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" x-model="mode" value="update_or_create" class="mt-0.5 text-sky-600 focus:ring-sky-500">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">Update or Create (Sync Mode)</div>
                                    <div class="text-xs text-slate-500">Updates existing records if matched by unique key, and inserts new records.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Download Sample Templates --}}
                    <div class="lg:col-span-6">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Download Template Files</label>
                        <p class="mt-1 text-xs text-slate-500">Use official hospital templates pre-filled with correct column headers and valid demo rows:</p>
                        <div class="mt-3 flex flex-wrap gap-2.5">
                            <a :href="templateUrl + '?target=' + target + '&format=csv'" download class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Download CSV Template</span>
                            </a>
                            <a :href="templateUrl + '?target=' + target + '&format=xlsx'" download class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                <svg class="h-4 w-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Download Excel (.xlsx)</span>
                            </a>
                            <a :href="templateUrl + '?target=' + target + '&format=json'" download class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                <svg class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                <span>Download JSON</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- File Dropzone --}}
                <div class="mt-6">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Source File Upload</label>
                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleFileDrop($event)"
                        :class="isDragging ? 'border-sky-500 bg-sky-50/50 ring-2 ring-sky-500/20' : 'border-slate-300 hover:border-slate-400 bg-slate-50/40'"
                        class="mt-2 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
                    >
                        <input
                            type="file"
                            id="file-upload"
                            class="hidden"
                            accept=".csv,.xlsx,.xls,.json,text/csv,application/json,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            @change="handleFileSelect($event)"
                        >

                        <template x-if="!selectedFile">
                            <div class="flex flex-col items-center">
                                <div class="rounded-full bg-sky-100 p-3 text-sky-600">
                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                </div>
                                <div class="mt-4 flex text-sm text-slate-600">
                                    <label for="file-upload" class="cursor-pointer font-semibold text-sky-600 hover:text-sky-500 focus-within:outline-none">
                                        <span>Click to browse file</span>
                                    </label>
                                    <span class="pl-1">or drag and drop here</span>
                                </div>
                                <p class="mt-1.5 text-xs text-slate-500">Supported: CSV (.csv), Excel (.xlsx, .xls), JSON (.json) up to 10MB</p>
                            </div>
                        </template>

                        <template x-if="selectedFile">
                            <div class="flex w-full max-w-md items-center justify-between rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                                <div class="flex items-center gap-3 overflow-hidden text-left">
                                    <div class="rounded-lg bg-sky-50 p-2 text-sky-700">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="truncate">
                                        <div class="truncate text-sm font-semibold text-slate-900" x-text="selectedFile.name"></div>
                                        <div class="text-xs text-slate-500" x-text="formatFileSize(selectedFile.size)"></div>
                                    </div>
                                </div>
                                <button type="button" @click="clearFile()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                    <span class="sr-only">Remove file</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Validate Button --}}
                <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <button
                        type="button"
                        @click="validateFile()"
                        :disabled="!selectedFile || isValidating"
                        class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <template x-if="isValidating">
                            <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <template x-if="!isValidating">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </template>
                        <span x-text="isValidating ? 'Parsing & Validating...' : 'Inspect & Validate File'"></span>
                    </button>
                </div>
            </div>

            {{-- Step 2: Interactive Validation & Preview Console --}}
            <div x-show="validationResult" x-cloak class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="inline-flex items-center rounded-md bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Step 2</span>
                        <h3 class="mt-1.5 text-lg font-bold text-slate-900">Pre-Commit Validation &amp; Record Preview</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="validationResult && validationResult.is_valid">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span>Passed All Integrity Checks</span>
                            </span>
                        </template>
                        <template x-if="validationResult && !validationResult.is_valid">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 border border-rose-200">
                                <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                <span>Errors Detected (Cannot Commit)</span>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Summary Metrics Cards --}}
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Rows</div>
                        <div class="mt-1 text-2xl font-bold text-slate-900" x-text="validationResult ? validationResult.total_rows : 0"></div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Valid Rows</div>
                        <div class="mt-1 text-2xl font-bold text-emerald-700" x-text="validationResult ? validationResult.valid_count : 0"></div>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-rose-700">Errors</div>
                        <div class="mt-1 text-2xl font-bold text-rose-700" x-text="validationResult ? validationResult.invalid_count : 0"></div>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50/40 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-sky-700">Planned Updates</div>
                        <div class="mt-1 text-2xl font-bold text-sky-700" x-text="validationResult ? validationResult.update_count : 0"></div>
                    </div>
                </div>

                {{-- Error Diagnostics Accordion --}}
                <template x-if="validationResult && !validationResult.is_valid && validationResult.errors.length > 0">
                    <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50/30 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-rose-900 font-semibold text-sm">
                                <svg class="h-5 w-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <span>Validation Issues Found (<span x-text="validationResult.errors.length"></span>)</span>
                            </div>
                            <span class="text-xs text-rose-700">Fix these rows in your source file and re-upload.</span>
                        </div>

                        <div class="mt-3 max-h-60 overflow-y-auto rounded-lg border border-rose-200 bg-white">
                            <table class="min-w-full divide-y divide-rose-100 text-xs text-left">
                                <thead class="bg-rose-50 text-rose-900 font-semibold uppercase tracking-wider">
                                    <tr>
                                        <th class="px-3 py-2">Row #</th>
                                        <th class="px-3 py-2">Issue Type</th>
                                        <th class="px-3 py-2">Field</th>
                                        <th class="px-3 py-2">Uploaded Value</th>
                                        <th class="px-3 py-2">Error Explanation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(err, idx) in validationResult.errors" :key="idx">
                                        <tr class="hover:bg-rose-50/20">
                                            <td class="px-3 py-2 font-bold text-slate-800 font-mono" x-text="err.row"></td>
                                            <td class="px-3 py-2 font-sans">
                                                <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                                                      :class="{
                                                          'bg-red-100 text-red-800': err.type === 'missing_header',
                                                          'bg-amber-100 text-amber-800': err.type === 'empty_required',
                                                          'bg-purple-100 text-purple-800': err.type === 'invalid_value',
                                                          'bg-orange-100 text-orange-800': err.type === 'duplicate_record',
                                                          'bg-blue-100 text-blue-800': err.type === 'referential_integrity',
                                                          'bg-rose-100 text-rose-800': err.type === 'invalid_structure',
                                                          'bg-slate-100 text-slate-700': !err.type
                                                      }"
                                                      x-text="err.type ? err.type.replace('_', ' ') : 'issue'">
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 font-semibold text-slate-700 font-mono" x-text="err.field"></td>
                                            <td class="px-3 py-2 text-rose-600 truncate max-w-xs font-mono" x-text="err.value || '-'"></td>
                                            <td class="px-3 py-2 text-slate-700 font-sans leading-relaxed" x-text="err.message"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                {{-- Record Preview Table --}}
                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Record Preview (First 25 Rows)</h4>
                        <span class="text-xs text-slate-400">Zero data is saved until confirmed below.</span>
                    </div>

                    <div class="mt-2.5 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead class="bg-slate-50 font-semibold text-slate-700">
                                <tr>
                                    <th class="px-3.5 py-2.5">Row</th>
                                    <th class="px-3.5 py-2.5">Status</th>
                                    <template x-if="target === 'items'">
                                        <th class="px-3.5 py-2.5">SKU</th>
                                    </template>
                                    <template x-if="target === 'locations'">
                                        <th class="px-3.5 py-2.5">Location Code</th>
                                    </template>
                                    <th class="px-3.5 py-2.5">Name / Description</th>
                                    <template x-if="target === 'items'">
                                        <th class="px-3.5 py-2.5">Category</th>
                                    </template>
                                    <template x-if="target === 'items'">
                                        <th class="px-3.5 py-2.5">Unit Cost</th>
                                    </template>
                                    <template x-if="target === 'locations'">
                                        <th class="px-3.5 py-2.5">Zone &amp; Capacity</th>
                                    </template>
                                    <template x-if="target === 'suppliers'">
                                        <th class="px-3.5 py-2.5">Contact / Email</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white" x-show="validationResult && validationResult.preview_rows.length > 0">
                                <template x-for="(row, idx) in validationResult ? validationResult.preview_rows : []" :key="idx">
                                    <tr class="hover:bg-slate-50/50" :class="row.status === 'invalid' ? 'bg-rose-50/30' : ''">
                                        <td class="px-3.5 py-2.5 font-semibold text-slate-800" x-text="row.row"></td>
                                        <td class="px-3.5 py-2.5">
                                            <template x-if="row.status === 'valid'">
                                                <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                                    Valid
                                                </span>
                                            </template>
                                            <template x-if="row.status === 'update'">
                                                <span class="inline-flex items-center gap-1 rounded bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">
                                                    Update
                                                </span>
                                            </template>
                                            <template x-if="row.status === 'invalid'">
                                                <span class="inline-flex items-center gap-1 rounded bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-800" :title="row.errors.join('; ')">
                                                    Error
                                                </span>
                                            </template>
                                        </td>
                                        <template x-if="target === 'items'">
                                            <td class="px-3.5 py-2.5 font-mono text-slate-700" x-text="row.sku"></td>
                                        </template>
                                        <template x-if="target === 'locations'">
                                            <td class="px-3.5 py-2.5 font-mono text-slate-700" x-text="row.code"></td>
                                        </template>
                                        <td class="px-3.5 py-2.5 font-medium text-slate-900" x-text="row.name"></td>
                                        <template x-if="target === 'items'">
                                            <td class="px-3.5 py-2.5 text-slate-600" x-text="row.category"></td>
                                        </template>
                                        <template x-if="target === 'items'">
                                            <td class="px-3.5 py-2.5 text-slate-700 font-semibold" x-text="row.unit_cost"></td>
                                        </template>
                                        <template x-if="target === 'locations'">
                                            <td class="px-3.5 py-2.5 text-slate-600" x-text="row.zone + ' (' + row.capacity + ')'"></td>
                                        </template>
                                        <template x-if="target === 'suppliers'">
                                            <td class="px-3.5 py-2.5 text-slate-600" x-text="(row.contact_person || '') + ' ' + (row.email || '')"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Action Footer --}}
                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        @click="resetPreview()"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                    >
                        Cancel / Re-Upload
                    </button>

                    <button
                        type="button"
                        @click="commitImport()"
                        :disabled="!validationResult || !validationResult.is_valid || isCommitting"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <template x-if="isCommitting">
                            <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <template x-if="!isCommitting">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <span x-text="isCommitting ? 'Committing Transaction...' : 'Confirm & Import ' + (validationResult ? validationResult.valid_count : 0) + ' Records'"></span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        function dataImporter(config) {
            return {
                target: config.initialTarget,
                mode: 'create_only',
                selectedFile: null,
                isDragging: false,
                isValidating: false,
                isCommitting: false,
                validationResult: null,
                errorMessage: '',
                successMessage: '',
                lastCommittedTarget: '',
                templateUrl: config.templateUrl,

                selectTarget(newTarget) {
                    if (this.target !== newTarget) {
                        this.target = newTarget;
                        this.resetPreview();
                    }
                },

                handleFileSelect(event) {
                    const files = event.target.files;
                    if (files && files.length > 0) {
                        this.selectedFile = files[0];
                        this.validationResult = null;
                        this.errorMessage = '';
                    }
                },

                handleFileDrop(event) {
                    this.isDragging = false;
                    const files = event.dataTransfer.files;
                    if (files && files.length > 0) {
                        this.selectedFile = files[0];
                        this.validationResult = null;
                        this.errorMessage = '';
                    }
                },

                clearFile() {
                    this.selectedFile = null;
                    this.validationResult = null;
                    const input = document.getElementById('file-upload');
                    if (input) input.value = '';
                },

                resetPreview() {
                    this.clearFile();
                    this.errorMessage = '';
                    this.successMessage = '';
                },

                formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                },

                async validateFile() {
                    if (!this.selectedFile) return;

                    this.isValidating = true;
                    this.errorMessage = '';
                    this.successMessage = '';

                    const formData = new FormData();
                    formData.append('file', this.selectedFile);
                    formData.append('target', this.target);
                    formData.append('mode', this.mode);

                    try {
                        const response = await fetch(config.previewUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json',
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            let msg = data.message || 'Validation failed.';
                            if (data.errors && typeof data.errors === 'object') {
                                if (Array.isArray(data.errors) && data.errors[0]?.message) {
                                    msg = data.errors[0].message;
                                } else {
                                    msg = Object.values(data.errors).flat().join(' ');
                                }
                            }
                            throw new Error(msg);
                        }

                        this.validationResult = data;
                        if (!data.is_valid) {
                            this.errorMessage = `Found ${data.invalid_count} row(s) with validation errors. Review the issues below before proceeding.`;
                        }
                    } catch (err) {
                        this.errorMessage = err.message || 'An unexpected error occurred while parsing the file.';
                        this.validationResult = null;
                    } finally {
                        this.isValidating = false;
                    }
                },

                async commitImport() {
                    if (!this.validationResult || !this.validationResult.is_valid || !this.validationResult.import_token) {
                        this.errorMessage = 'Cannot commit import: Missing or invalid staging token.';
                        return;
                    }

                    this.isCommitting = true;
                    this.errorMessage = '';

                    try {
                        const response = await fetch(config.commitUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                import_token: this.validationResult.import_token,
                                target: this.target
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Import transaction failed.');
                        }

                        this.lastCommittedTarget = this.target;
                        this.successMessage = data.message;
                        this.validationResult = null;
                        this.clearFile();
                    } catch (err) {
                        this.errorMessage = err.message || 'Failed to complete database transaction.';
                    } finally {
                        this.isCommitting = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>
