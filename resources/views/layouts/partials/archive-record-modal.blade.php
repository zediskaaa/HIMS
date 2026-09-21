<div
    x-data="{
        open: false,
        actionUrl: '',
        recordTitle: '',
        recordIdentifier: '',
        recordContext: '',
        recordType: 'Record',
        reason: '',
        presets: [],
        isSubmitting: false,
        init() {
            window.addEventListener('open-archive-modal', (event) => {
                const detail = event.detail || {};
                this.actionUrl = detail.actionUrl || '';
                this.recordTitle = detail.title || '';
                this.recordIdentifier = detail.identifier || '';
                this.recordContext = detail.context || '';
                this.recordType = detail.type || 'Record';
                this.presets = Array.isArray(detail.presets) ? detail.presets : [];
                this.reason = '';
                this.isSubmitting = false;
                this.open = true;
                this.$nextTick(() => {
                    this.$refs.reasonInput?.focus();
                });
            });
        },
        closeModal() {
            if (this.isSubmitting) return;
            this.open = false;
        },
        setPreset(preset) {
            this.reason = preset;
            this.$nextTick(() => {
                this.$refs.reasonInput?.focus();
            });
        },
        handleSubmit() {
            if (!this.actionUrl || this.reason.trim().length < 3 || this.isSubmitting) return;
            this.isSubmitting = true;
            this.$refs.archiveForm.submit();
        }
    }"
    x-on:keydown.escape.window="closeModal()"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex min-h-full items-center justify-center overflow-y-auto p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="archive-modal-title"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="closeModal()"
        class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
        aria-hidden="true"
    ></div>

    {{-- Modal Dialog Panel --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative flex max-h-[calc(100dvh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-2xl"
    >
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <x-ui.icon name="archive-box" class="h-5 w-5" />
                </span>
                <div>
                    <h2 id="archive-modal-title" class="text-base font-semibold text-neutral-900 dark:text-neutral-100" x-text="'Archive ' + recordType">
                        Archive Record
                    </h2>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Document justification for archiving this master record
                    </p>
                </div>
            </div>
            <button
                type="button"
                @click="closeModal()"
                :disabled="isSubmitting"
                class="rounded-md p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
            >
                <span class="sr-only">Close</span>
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>

        {{-- Body Content --}}
        <form x-ref="archiveForm" method="POST" :action="actionUrl" @submit.prevent="handleSubmit()" class="flex flex-col overflow-y-auto p-5 space-y-4">
            @csrf

            {{-- Target Record Card --}}
            <div class="rounded-lg border border-neutral-200 dark:border-neutral-700/80 bg-neutral-50 dark:bg-neutral-800/50 p-3.5 space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400" x-text="recordType"></span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300" x-text="recordIdentifier" x-show="recordIdentifier"></span>
                </div>
                <p class="font-semibold text-neutral-900 dark:text-neutral-100 text-sm truncate" x-text="recordTitle"></p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400" x-text="recordContext" x-show="recordContext"></p>
            </div>

            {{-- Informational Safety Notice --}}
            <div class="flex items-start gap-2.5 rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/70 dark:bg-amber-950/20 p-3 text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                <x-ui.icon name="information-circle" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                <span>
                    This record will be removed from active operational lists. All historical transactions, audit trails, and ledgers remain intact and can be restored at any time from the Archive Hub.
                </span>
            </div>

            {{-- Reason Input with Presets --}}
            <div class="space-y-2">
                <label for="archive_modal_reason" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                    Reason for Archiving <span class="text-rose-600 dark:text-rose-400">*</span>
                </label>

                {{-- Quick Presets Chips --}}
                <template x-if="presets && presets.length > 0">
                    <div class="space-y-1.5">
                        <span class="text-[11px] text-neutral-500 dark:text-neutral-400">Quick suggestions (click to apply):</span>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(preset, index) in presets" :key="index">
                                <button
                                    type="button"
                                    @click="setPreset(preset)"
                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition shadow-2xs text-left"
                                    :class="reason === preset
                                        ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 font-semibold'
                                        : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:border-neutral-300 dark:hover:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-750'"
                                >
                                    <span x-text="preset"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <textarea
                    id="archive_modal_reason"
                    x-ref="reasonInput"
                    name="reason"
                    x-model="reason"
                    required
                    minlength="3"
                    maxlength="500"
                    rows="3"
                    class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-2 text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 shadow-xs focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20"
                    placeholder="Document the operational justification for archiving this record (required for audit logging and historical tracking)..."
                ></textarea>

                <div class="flex items-center justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
                    <span>Minimum 3 characters required.</span>
                    <span x-bind:class="reason.length > 450 ? 'text-amber-600 font-semibold' : ''" x-text="reason.length + '/500'"></span>
                </div>
            </div>

            {{-- Footer Buttons --}}
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    @click="closeModal()"
                    x-bind:disabled="isSubmitting"
                >
                    Cancel
                </x-ui.button>
                <x-ui.button
                    type="submit"
                    variant="danger"
                    x-bind:disabled="reason.trim().length < 3 || isSubmitting"
                >
                    <span x-show="!isSubmitting" class="flex items-center gap-1.5">
                        <x-ui.icon name="archive-box" class="h-4 w-4" />
                        <span x-text="'Archive ' + recordType"></span>
                    </span>
                    <span x-show="isSubmitting" x-cloak class="flex items-center gap-1.5">
                        <span class="loader loader--sm" aria-hidden="true"></span>
                        <span>Archiving record...</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </div>
</div>
