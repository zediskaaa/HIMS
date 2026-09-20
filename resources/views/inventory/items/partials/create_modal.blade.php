{{-- CREATE INVENTORY ITEM MODAL (Compact, Multi-Column & Space-Efficient) --}}
@can(\App\Enums\Permission::ManageItems->value)
<div x-show="createItemModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     role="dialog"
     aria-modal="true"
     aria-labelledby="create-item-modal-title"
>
    <div class="flex min-h-screen items-center justify-center p-2 sm:p-4 text-center">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="createItemModal = false"></div>

        {{-- Modal Panel (Max-w-4xl for wide, compact multi-column arrangement) --}}
        <div class="relative w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-auto z-10 border border-neutral-200 max-h-[92vh] flex flex-col"
             x-data="{
                 initialQty: '{{ old('initial_quantity', 0) }}',
                 isBatchTracked: '{{ old('is_batch_tracked', '1') }}',
                 isSerialTracked: '{{ old('is_serial_tracked', '0') }}',
                 isExpiryTracked: '{{ old('is_expiry_tracked', '1') }}',
                 defaultLocationId: '{{ old('default_location_id', '') }}',
                 isSubmitting: false,

                 get parsedQty() {
                     return parseInt(this.initialQty || 0) || 0;
                 },
                 get hasOpeningStock() {
                     return this.parsedQty > 0;
                 },
                 get requiresBatchNumber() {
                     return this.hasOpeningStock && this.isBatchTracked === '1';
                 },
                 get requiresExpiryDate() {
                     return this.hasOpeningStock && this.isExpiryTracked === '1';
                 },
                 get hasSerialConflict() {
                     return this.isSerialTracked === '1' && this.hasOpeningStock;
                 }
             }">

            {{-- FIXED MODAL HEADER --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-neutral-200 bg-neutral-50/80 shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 id="create-item-modal-title" class="text-sm font-bold text-neutral-900 truncate">
                            Create Inventory Item
                        </h3>
                        <p class="text-[11px] text-neutral-500 truncate">
                            Add a new catalog item with tracking and storage settings.
                        </p>
                    </div>
                </div>
                <button type="button"
                        @click="createItemModal = false"
                        class="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 transition"
                        title="Close dialog (Esc)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Server-Level Errors Alert (if any) --}}
            @if ($errors->any())
                <div class="mx-5 mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-800 shadow-2xs">
                    <div class="flex items-center gap-1.5 font-bold text-rose-900">
                        <svg class="h-3.5 w-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Please correct the errors below:</span>
                    </div>
                    <ul class="mt-1 list-disc list-inside space-y-0.5 text-[11px] text-rose-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- SCROLLABLE FORM BODY (COMPACT SPACING & MULTI-COLUMN) --}}
            <form id="create-inventory-item-form"
                  method="POST"
                  action="{{ route('inventory.items.store') }}"
                  data-confirm-title="Create inventory item"
                  data-confirm-message="Are you sure you want to add this item to the catalog? Any initial stock will be posted to the inventory ledger."
                  data-confirm-label="Create item"
                  @submit="isSubmitting = true"
                  class="overflow-y-auto px-5 py-4 flex-1 space-y-4 text-left text-xs">
                @csrf

                {{-- SECTION 1: BASIC ITEM INFORMATION --}}
                <div class="space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1 border-b border-neutral-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">1. Basic Information</span>
                    </div>
                    <div class="grid grid-cols-12 gap-2.5 sm:gap-3 items-start">
                        {{-- Item Name (7 cols) --}}
                        <div class="col-span-12 sm:col-span-7">
                            <label for="field-name" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Item name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   id="field-name"
                                   value="{{ old('name') }}"
                                   required
                                   placeholder="e.g. Paracetamol 500mg Tablet"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('name') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('name')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- SKU (5 cols) --}}
                        <div class="col-span-12 sm:col-span-5">
                            <label for="field-sku" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                SKU <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   name="sku"
                                   id="field-sku"
                                   value="{{ old('sku') }}"
                                   required
                                   placeholder="e.g. MED-PARA-500"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-mono font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('sku') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('sku')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Category (3 cols) --}}
                        <div class="col-span-12 sm:col-span-3">
                            <label for="field-category_id" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Category
                            </label>
                            <select name="category_id"
                                    id="field-category_id"
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('category_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}">
                                <option value="">-- Select category --</option>
                                @foreach ($categories as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('category_id') === (string) $id)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Unit of Measure (3 cols) --}}
                        <div class="col-span-12 sm:col-span-3">
                            <label for="field-unit" class="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                Unit of measure <span class="text-rose-500">*</span>
                            </label>
                            <select name="unit"
                                    id="field-unit"
                                    required
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('unit') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}">
                                <option value="">Select unit</option>
                                @foreach ($unitOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('unit') === (string) $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('unit')
                                <p class="mt-0.5 text-[11px] text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Internal Barcode (3 cols) --}}
                        <div class="col-span-12 sm:col-span-3">
                            <label for="field-barcode_value" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Internal barcode
                            </label>
                            <input type="text"
                                   name="barcode_value"
                                   id="field-barcode_value"
                                   value="{{ old('barcode_value') }}"
                                   maxlength="100"
                                   placeholder="Auto if blank"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-mono font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('barcode_value') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('barcode_value')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- GTIN (3 cols) --}}
                        <div class="col-span-12 sm:col-span-3">
                            <label for="field-gtin" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                GTIN / Barcode (8-14 digits)
                            </label>
                            <input type="text"
                                   name="gtin"
                                   id="field-gtin"
                                   value="{{ old('gtin') }}"
                                   inputmode="numeric"
                                   pattern="[0-9]{8,14}"
                                   maxlength="14"
                                   placeholder="e.g. 04800123456789"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-mono font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('gtin') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('gtin')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- SECTION 2: TRACKING & CLASSIFICATIONS --}}
                <div class="space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1 border-b border-neutral-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">2. Tracking &amp; Classifications</span>
                    </div>
                    <div class="grid grid-cols-12 gap-2.5 sm:gap-3 items-start">
                        {{-- Batch / Lot Tracking (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-is_batch_tracked" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Batch / Lot Tracking <span class="text-rose-500">*</span>
                            </label>
                            <select name="is_batch_tracked"
                                    id="field-is_batch_tracked"
                                    x-model="isBatchTracked"
                                    required
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="1">Yes — track batches and expiry</option>
                                <option value="0">No — quantity by location only</option>
                            </select>
                        </div>

                        {{-- Serial Tracking (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-is_serial_tracked" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Serial Tracking <span class="text-rose-500">*</span>
                            </label>
                            <select name="is_serial_tracked"
                                    id="field-is_serial_tracked"
                                    x-model="isSerialTracked"
                                    required
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="0">No</option>
                                <option value="1">Yes — one serial per received unit</option>
                            </select>
                        </div>

                        {{-- Expiry Tracking (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-is_expiry_tracked" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Expiry Tracking <span class="text-rose-500">*</span>
                            </label>
                            <select name="is_expiry_tracked"
                                    id="field-is_expiry_tracked"
                                    x-model="isExpiryTracked"
                                    required
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="1">Yes — expiry is required on receipt</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        {{-- Storage Classification (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-storage_classification" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Storage classification
                            </label>
                            <select name="storage_classification"
                                    id="field-storage_classification"
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('storage_classification') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}">
                                <option value="">General / not restricted</option>
                                <option value="general" @selected(old('storage_classification', 'general') === 'general')>General</option>
                                <option value="medical_supply" @selected(old('storage_classification') === 'medical_supply')>Medical supply</option>
                                <option value="pharmaceutical" @selected(old('storage_classification') === 'pharmaceutical')>Pharmaceutical</option>
                                <option value="sterile" @selected(old('storage_classification') === 'sterile')>Sterile</option>
                                <option value="cold_chain" @selected(old('storage_classification') === 'cold_chain')>Cold chain</option>
                                <option value="controlled" @selected(old('storage_classification') === 'controlled')>Controlled / restricted</option>
                                <option value="flammable" @selected(old('storage_classification') === 'flammable')>Flammable</option>
                                <option value="hazardous" @selected(old('storage_classification') === 'hazardous')>Hazardous</option>
                            </select>
                            @error('storage_classification')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Temperature Classification (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-temperature_classification" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Temperature classification
                            </label>
                            <select name="temperature_classification"
                                    id="field-temperature_classification"
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('temperature_classification') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}">
                                <option value="">Not specified</option>
                                <option value="ambient" @selected(old('temperature_classification') === 'ambient')>Ambient</option>
                                <option value="controlled_room" @selected(old('temperature_classification') === 'controlled_room')>Controlled room temperature</option>
                                <option value="refrigerated" @selected(old('temperature_classification') === 'refrigerated')>Refrigerated</option>
                                <option value="frozen" @selected(old('temperature_classification') === 'frozen')>Frozen</option>
                                <option value="deep_frozen" @selected(old('temperature_classification') === 'deep_frozen')>Deep frozen</option>
                            </select>
                            @error('temperature_classification')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Expiry Alert Days (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-expiry_alert_days" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Expiry alert lead time (days)
                            </label>
                            <input type="number"
                                   name="expiry_alert_days"
                                   id="field-expiry_alert_days"
                                   value="{{ old('expiry_alert_days', 30) }}"
                                   min="0"
                                   max="3650"
                                   step="1"
                                   inputmode="numeric"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('expiry_alert_days') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('expiry_alert_days')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: STORAGE & PROCUREMENT --}}
                <div class="space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1 border-b border-neutral-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">3. Storage &amp; Procurement</span>
                    </div>
                    <div class="grid grid-cols-12 gap-2.5 sm:gap-3 items-start">
                        {{-- Storage Location (6 cols) --}}
                        <div class="col-span-12 sm:col-span-6">
                            <label for="field-default_location_id" class="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                Storage location <span x-show="hasOpeningStock" class="text-rose-500">*</span>
                            </label>

                            <div class="relative"
                                 x-data="{
                                     open: false,
                                     search: '',
                                     locations: {{ Js::from($locationOptions) }},
                                     get selectedLoc() {
                                         return this.locations.find(l => String(l.id) === String(defaultLocationId)) || null;
                                     },
                                     get filteredLocations() {
                                         if (!this.search.trim()) return this.locations;
                                         const q = this.search.toLowerCase();
                                         return this.locations.filter(l =>
                                             (l.name && l.name.toLowerCase().includes(q)) ||
                                             (l.code && l.code.toLowerCase().includes(q)) ||
                                             (l.type && l.type.toLowerCase().includes(q)) ||
                                             (l.parent_name && l.parent_name.toLowerCase().includes(q))
                                         );
                                     },
                                     select(loc) {
                                         if (!loc.is_active) return;
                                         defaultLocationId = String(loc.id);
                                         this.open = false;
                                         this.search = '';
                                     },
                                     clear() {
                                         defaultLocationId = '';
                                         this.search = '';
                                     }
                                 }"
                                 @click.outside="open = false"
                                 @keydown.escape.stop="open = false">

                                {{-- Synchronized Native Select for Standard Form Submission & Accessibility --}}
                                <select name="default_location_id"
                                        id="field-default_location_id"
                                        x-model="defaultLocationId"
                                        class="sr-only"
                                        tabindex="-1"
                                        aria-hidden="true">
                                    <option value="">Select storage location</option>
                                    @foreach ($locationOptions as $loc)
                                        <option value="{{ $loc['id'] }}"
                                                @disabled(! $loc['is_active'])
                                                @selected((string) old('default_location_id') === (string) $loc['id'])>
                                            {{ $loc['display_label'] }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Combobox Trigger Button --}}
                                <button type="button"
                                        id="field-default_location_id-trigger"
                                        @click="open = !open; if(open) { $nextTick(() => $refs.locSearchInput?.focus()); }"
                                        aria-haspopup="listbox"
                                        :aria-expanded="open"
                                        class="flex items-center justify-between w-full h-8.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-2.5 py-1 text-xs text-left shadow-2xs focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('default_location_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}">
                                    <div class="truncate mr-2 flex items-center gap-1.5 min-w-0">
                                        <template x-if="selectedLoc">
                                            <span class="flex items-center gap-1.5 truncate">
                                                <span class="font-medium text-neutral-900 dark:text-neutral-100 truncate" x-text="selectedLoc.name"></span>
                                                <span class="font-mono text-[10px] text-neutral-400 shrink-0" x-text="'(' + selectedLoc.code + ' • ' + selectedLoc.type + ')'"></span>
                                            </span>
                                        </template>
                                        <template x-if="!selectedLoc">
                                            <span class="text-neutral-400 dark:text-neutral-500">Select storage location</span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <template x-if="selectedLoc">
                                            <span @click.stop="clear()"
                                                  role="button"
                                                  tabindex="0"
                                                  class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 p-0.5 rounded cursor-pointer"
                                                  title="Clear location">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </span>
                                        </template>
                                        <svg class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-150"
                                             :class="{ 'rotate-180': open }"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </button>

                                {{-- Dropdown Search & Options Panel --}}
                                <div x-show="open"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute left-0 top-full mt-1 w-full z-40 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 p-2 shadow-xl">

                                    {{-- Search Input --}}
                                    <div class="relative mb-2">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input type="text"
                                               x-ref="locSearchInput"
                                               x-model="search"
                                               placeholder="Search code, name, type, warehouse..."
                                               class="w-full h-8 pl-8 pr-2.5 py-1 text-xs rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 focus:bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
                                    </div>

                                    {{-- Scrollable List of Locations --}}
                                    <div class="max-h-48 overflow-y-auto space-y-1 divide-y divide-neutral-100 dark:divide-neutral-700/50">
                                        <template x-for="loc in filteredLocations" :key="loc.id">
                                            <div>
                                                {{-- Active Location Option --}}
                                                <template x-if="loc.is_active">
                                                    <button type="button"
                                                            @click="select(loc)"
                                                            class="w-full text-left px-2 py-1.5 rounded-lg text-xs transition flex items-center justify-between group"
                                                            :class="String(defaultLocationId) === String(loc.id) ? 'bg-primary-50 text-primary-900 dark:bg-primary-950/40 dark:text-primary-200 font-semibold' : 'text-neutral-800 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700/60'">
                                                        <div class="min-w-0 pr-2">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="truncate" x-text="loc.name"></span>
                                                                <span class="font-mono text-[10px] text-neutral-400 dark:text-neutral-500" x-text="loc.code"></span>
                                                            </div>
                                                            <div class="text-[10px] text-neutral-400 dark:text-neutral-500 truncate" x-text="loc.type + (loc.parent_name ? ' • ' + loc.parent_name : '')"></div>
                                                        </div>
                                                        <template x-if="String(defaultLocationId) === String(loc.id)">
                                                            <svg class="h-4 w-4 text-primary-600 dark:text-primary-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </template>
                                                    </button>
                                                </template>

                                                {{-- Inactive Location Option (Disabled & Clearly Marked) --}}
                                                <template x-if="!loc.is_active">
                                                    <div class="w-full text-left px-2 py-1.5 rounded-lg text-xs opacity-55 bg-neutral-50/70 dark:bg-neutral-800/60 flex items-center justify-between cursor-not-allowed select-none"
                                                         title="Inactive storage locations cannot receive or store new inventory">
                                                        <div class="min-w-0 pr-2">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="line-through text-neutral-500 dark:text-neutral-400 truncate" x-text="loc.name"></span>
                                                                <span class="font-mono text-[10px] text-neutral-400" x-text="loc.code"></span>
                                                            </div>
                                                            <div class="text-[10px] text-neutral-400" x-text="loc.type + (loc.parent_name ? ' • ' + loc.parent_name : '')"></div>
                                                        </div>
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-900 shrink-0">
                                                            [Inactive]
                                                        </span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <template x-if="filteredLocations.length === 0">
                                            <div class="py-3 px-2 text-center text-[11px] text-neutral-400">
                                                No matching storage locations found
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <p class="mt-0.5 text-[10px] text-neutral-400">Required when recording opening quantity.</p>
                            @error('default_location_id')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Primary Supplier (6 cols) --}}
                        <div class="col-span-12 sm:col-span-6">
                            <label for="field-supplier_id" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Primary Supplier <span class="text-[10px] font-normal text-neutral-400">(Optional)</span>
                            </label>
                            <select name="supplier_id"
                                    id="field-supplier_id"
                                    class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('supplier_id') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}">
                                <option value="">No supplier / select later</option>
                                @foreach ($eligibleSuppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                                        {{ $supplier->name }} — Accredited
                                    </option>
                                @endforeach
                                @if ($unavailableSuppliers->isNotEmpty())
                                    <optgroup label="Unavailable for procurement">
                                        @foreach ($unavailableSuppliers as $supplier)
                                            <option value="{{ $supplier->id }}" disabled>
                                                {{ $supplier->name }} — {{ $supplier->eligibility_reason }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <p class="mt-0.5 text-[10px] text-neutral-400">
                                Only active, approved, and compliant suppliers can be selected.
                                @can(\App\Enums\Permission::ManageSuppliers->value)
                                    <a href="{{ route('inventory.suppliers') }}" class="font-medium text-primary-700 hover:underline">Review supplier accreditation</a>.
                                @endcan
                            </p>
                            @if ($eligibleSuppliers->isEmpty())
                                <x-ui.alert variant="warning" title="No accredited supplier is selectable yet" class="mt-1.5 text-xs">
                                    Existing supplier records are shown above as unavailable until their accreditation and compliance review is completed. You may save the item without a supplier and link one later.
                                </x-ui.alert>
                            @endif
                            @error('supplier_id')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- SECTION 4: INVENTORY SETTINGS & QUANTITIES --}}
                <div class="space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1 border-b border-neutral-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">4. Inventory Settings &amp; Quantities</span>
                    </div>
                    <div class="grid grid-cols-12 gap-2.5 sm:gap-3 items-start">
                        {{-- Opening Quantity (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-initial_quantity" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Opening quantity
                            </label>
                            <input type="number"
                                   name="initial_quantity"
                                   id="field-initial_quantity"
                                   x-model="initialQty"
                                   min="0"
                                   step="1"
                                   inputmode="numeric"
                                   placeholder="0"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-semibold py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('initial_quantity') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            <p class="mt-0.5 text-[10px] text-neutral-400">Recorded as Stock In ledger entry.</p>
                            @error('initial_quantity')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Reorder Level (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-reorder_level" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Reorder level
                            </label>
                            <input type="number"
                                   name="reorder_level"
                                   id="field-reorder_level"
                                   value="{{ old('reorder_level', 0) }}"
                                   min="0"
                                   step="1"
                                   inputmode="numeric"
                                   placeholder="0"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('reorder_level') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('reorder_level')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Unit Cost (4 cols) --}}
                        <div class="col-span-12 sm:col-span-4">
                            <label for="field-unit_cost" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Unit cost (PHP)
                            </label>
                            <input type="number"
                                   name="unit_cost"
                                   id="field-unit_cost"
                                   value="{{ old('unit_cost', '0.00') }}"
                                   min="0"
                                   max="9999999999.99"
                                   step="0.01"
                                   inputmode="decimal"
                                   placeholder="0.00"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('unit_cost') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('unit_cost')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Pick-face Minimum (6 cols) --}}
                        <div class="col-span-12 sm:col-span-6">
                            <label for="field-pick_face_minimum" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Pick-face minimum
                            </label>
                            <input type="number"
                                   name="pick_face_minimum"
                                   id="field-pick_face_minimum"
                                   value="{{ old('pick_face_minimum', 0) }}"
                                   min="0"
                                   step="1"
                                   inputmode="numeric"
                                   placeholder="0"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('pick_face_minimum') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            @error('pick_face_minimum')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Pick-face Maximum (6 cols) --}}
                        <div class="col-span-12 sm:col-span-6">
                            <label for="field-pick_face_maximum" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Pick-face maximum
                            </label>
                            <input type="number"
                                   name="pick_face_maximum"
                                   id="field-pick_face_maximum"
                                   value="{{ old('pick_face_maximum', 0) }}"
                                   min="0"
                                   step="1"
                                   inputmode="numeric"
                                   placeholder="0"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('pick_face_maximum') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            <p class="mt-0.5 text-[10px] text-neutral-400">Use 0 when no pick-face limit is configured.</p>
                            @error('pick_face_maximum')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- SECTION 5: OPENING BATCH INFORMATION (PROGRESSIVE DISCLOSURE) --}}
                <div x-show="hasOpeningStock" x-cloak class="space-y-2.5 rounded-xl bg-amber-50/60 p-3 border border-amber-200/80 transition-all">
                    <div class="flex items-center gap-1.5 pb-1 border-b border-amber-200/60">
                        <svg class="h-3.5 w-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs font-bold text-amber-900">5. Opening Stock Batch Details</span>
                    </div>

                    <div class="grid grid-cols-12 gap-2.5 sm:gap-3 items-start">
                        {{-- Batch Number (6 cols) --}}
                        <div class="col-span-12 sm:col-span-6" x-show="isBatchTracked === '1'">
                            <label for="field-batch_number" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Opening batch / lot number <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   name="batch_number"
                                   id="field-batch_number"
                                   value="{{ old('batch_number') }}"
                                   :required="requiresBatchNumber"
                                   placeholder="e.g. LOT-2026-001"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('batch_number') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            <p class="mt-0.5 text-[10px] text-neutral-500">Required when opening quantity is > 0 and batch tracking is enabled.</p>
                            @error('batch_number')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Expiry Date (6 cols) --}}
                        <div class="col-span-12 sm:col-span-6" :class="isBatchTracked === '1' ? 'sm:col-span-6' : 'sm:col-span-12'">
                            <label for="field-expiry_date" class="block text-[11px] font-semibold text-neutral-700 mb-1">
                                Opening batch expiry date <span x-show="requiresExpiryDate" class="text-rose-500">*</span>
                            </label>
                            <input type="date"
                                   name="expiry_date"
                                   id="field-expiry_date"
                                   value="{{ old('expiry_date') }}"
                                   min="{{ today()->toDateString() }}"
                                   :required="requiresExpiryDate"
                                   class="block w-full h-8.5 rounded-lg border-neutral-300 shadow-2xs text-xs font-medium py-1 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 {{ $errors->has('expiry_date') ? 'border-rose-500 ring-1 ring-rose-500' : '' }}" />
                            <p class="mt-0.5 text-[10px] text-neutral-500">Required if expiry tracking is enabled.</p>
                            @error('expiry_date')
                                <p class="mt-0.5 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </form>

            {{-- FIXED MODAL FOOTER --}}
            <div class="bg-neutral-50 px-5 py-3 border-t border-neutral-200 flex flex-col sm:flex-row items-center justify-between gap-2.5 shrink-0">
                <div class="text-left">
                    <template x-if="hasSerialConflict">
                        <span class="text-xs text-rose-600 font-semibold flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            Serialized items must be received via receiving goods, not opening balance.
                        </span>
                    </template>
                    <template x-if="!hasSerialConflict && hasOpeningStock && !defaultLocationId">
                        <span class="text-xs text-amber-700 font-medium flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Default storage location is required when setting opening stock.
                        </span>
                    </template>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button"
                            @click="createItemModal = false"
                            :disabled="isSubmitting"
                            class="rounded-lg border border-neutral-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition shadow-2xs disabled:opacity-50">
                        Cancel
                    </button>
                    <button type="submit"
                            form="create-inventory-item-form"
                            :disabled="isSubmitting || hasSerialConflict"
                            :class="isSubmitting || hasSerialConflict ? 'opacity-50 cursor-not-allowed bg-neutral-400' : 'bg-primary-600 hover:bg-primary-700 shadow-xs'"
                            id="btn-save-inventory-item"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold text-white transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <template x-if="isSubmitting">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </template>
                        <span x-text="isSubmitting ? 'Saving Item...' : 'Save Item'">Save Item</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endcan
