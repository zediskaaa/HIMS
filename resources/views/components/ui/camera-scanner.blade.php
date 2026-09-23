@props([
    'id',
    'targetInputId' => null,
    'buttonText' => 'Scan with Camera',
    'buttonVariant' => 'secondary',
    'buttonSize' => 'md',
    'autoSubmit' => false,
    'autoClose' => true,
    'eventName' => 'hims-code-scanned',
    'validateFormat' => null,
    'title' => 'Barcode & 2D QR Scanner',
    'hint' => 'Point camera at a barcode label (Bin, Shelf, SKU, or GS1 DataMatrix).',
    'showTrigger' => true,
    'autostart' => false,
])

<div
    x-data="himsCameraScanner({
        containerId: '{{ $id }}-viewport',
        targetInputId: '{{ $targetInputId }}',
        autoSubmit: {{ $autoSubmit ? 'true' : 'false' }},
        autoClose: {{ $autoClose ? 'true' : 'false' }},
        eventName: '{{ $eventName }}',
        validateFormat: '{{ $validateFormat }}'
    })"
    @open-camera-scanner-{{ $id }}.window="open()"
    x-init="if ({{ $autostart ? 'true' : 'false' }} || ((new URLSearchParams(window.location.search).has('camera') || new URLSearchParams(window.location.search).has('scan')) && '{{ $id }}' === 'camera-scanner-standby')) { $nextTick(() => open()); }"
    class="inline-block"
>
    @if($showTrigger)
        <button
            type="button"
            @click="open()"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-750 transition"
        >
            <x-ui.icon name="camera" class="h-3.5 w-3.5 text-neutral-600 dark:text-neutral-400" />
            <span>{{ $buttonText }}</span>
        </button>
    @endif

    {{-- Scanner Modal Dialog --}}
    <div
        x-show="isOpen"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-neutral-950/80 p-4 backdrop-blur-xs"
        @keydown.escape.window="close()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
    >
        <div
            class="relative flex max-h-[88vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900"
            @click.away="close()"
        >
            {{-- Modal Header --}}
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-neutral-200 bg-neutral-50 px-5 py-3.5 dark:border-neutral-800 dark:bg-neutral-850">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-400">
                        <x-ui.icon name="camera" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h3 id="{{ $id }}-title" class="text-sm font-bold leading-tight text-neutral-900 dark:text-neutral-100">{{ $title }}</h3>
                        <p class="truncate text-[11px] text-neutral-500 dark:text-neutral-400">{{ $hint }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    x-ref="dialogClose"
                    @click="close()"
                    class="shrink-0 rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-200 hover:text-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                    aria-label="Stop camera and close scanner"
                >
                    <x-ui.icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            {{-- Scanner Body (scrolls independently on short viewports) --}}
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                {{-- Live Camera Viewfinder --}}
                <div class="relative flex min-h-[240px] items-center justify-center overflow-hidden rounded-xl border border-neutral-800 bg-neutral-950 shadow-inner sm:min-h-[300px]">
                    {{-- html5-qrcode mounts its surface inside this element --}}
                    <div id="{{ $id }}-viewport" class="w-full"></div>

                    {{-- Idle / starting: covers the gap before the first frame arrives --}}
                    <div
                        x-show="!isScanning && !errorMessage"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-neutral-950/90 p-4 text-center text-white"
                    >
                        <div class="mb-3 h-8 w-8 animate-spin rounded-full border-2 border-primary-500 border-t-transparent"></div>
                        <p class="text-xs font-medium">Starting camera&hellip;</p>
                        <p class="mt-1 text-[11px] text-neutral-400">Allow camera access if your browser asks</p>
                    </div>

                    {{-- Scanning: reticle + laser --}}
                    <div x-show="isScanning && !errorMessage" class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center">
                        <div class="relative h-[60%] w-[78%] rounded-lg border-2 border-dashed border-primary-400/80 shadow-2xl">
                            <div class="absolute -left-1 -top-1 h-4 w-4 border-l-3 border-t-3 border-primary-500"></div>
                            <div class="absolute -right-1 -top-1 h-4 w-4 border-r-3 border-t-3 border-primary-500"></div>
                            <div class="absolute -bottom-1 -left-1 h-4 w-4 border-b-3 border-l-3 border-primary-500"></div>
                            <div class="absolute -bottom-1 -right-1 h-4 w-4 border-b-3 border-r-3 border-primary-500"></div>

                            <div class="hims-scanner-laser absolute left-1 right-1 h-0.5 bg-gradient-to-r from-transparent via-red-500 to-transparent shadow-[0_0_8px_rgba(239,68,68,0.8)]"></div>
                        </div>
                    </div>

                    {{-- Camera stopped / failed overlay with quick retry & upload buttons --}}
                    <div
                        x-show="errorMessage"
                        x-cloak
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-neutral-950/95 p-6 text-center text-white"
                    >
                        <span class="rounded-full border border-red-800/60 bg-red-950/80 p-3 text-red-400 shadow-sm">
                            <x-ui.icon name="camera" class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-neutral-100" x-text="errorTitle || 'Camera Unavailable'"></p>
                            <p class="mt-1 text-xs text-neutral-400 max-w-xs mx-auto">Check the alert below, or scan an image file / enter code manually.</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-2 mt-1">
                            <button
                                type="button"
                                @click="retryCamera()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-500 transition"
                            >
                                <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                                <span>Retry Camera</span>
                            </button>
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-1.5 text-xs font-semibold text-neutral-200 shadow-sm hover:bg-neutral-700 transition">
                                <x-ui.icon name="arrow-up-tray" class="h-3.5 w-3.5" />
                                <span>Scan Barcode Image</span>
                                <input type="file" accept="image/*" class="sr-only" @change="onFileSelected($event)" :disabled="isProcessingFile">
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Scanning status (quiet normal state) --}}
                <div
                    x-show="isScanning && !errorMessage"
                    class="flex items-center justify-between gap-3 px-1 text-xs text-neutral-600 dark:text-neutral-400"
                >
                    <span class="flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400" role="status" aria-live="polite">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Scanning&hellip; point the camera at a barcode
                    </span>
                    <span class="shrink-0 font-mono text-[11px] text-neutral-400 dark:text-neutral-500">1D &bull; 2D &bull; QR</span>
                </div>

                {{-- Camera chooser: only when there is more than one labelled device --}}
                <div x-show="showCameraSelector" x-cloak class="flex items-center gap-2">
                    <label for="{{ $id }}-camera" class="shrink-0 text-[11px] font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-400">
                        Camera
                    </label>
                    <select
                        id="{{ $id }}-camera"
                        x-model="selectedCameraId"
                        @change="selectCamera($event.target.value)"
                        class="min-w-0 flex-1 rounded-lg border-neutral-300 py-1.5 pl-2.5 pr-8 text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                    >
                        <template x-for="camera in labelledCameras" :key="camera.id">
                            <option :value="camera.id" x-text="camera.label"></option>
                        </template>
                    </select>
                </div>

                {{-- Contextual error: rendered only when the camera actually failed --}}
                <div
                    x-show="errorMessage"
                    x-cloak
                    role="alert"
                    aria-live="assertive"
                    class="space-y-2 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs text-red-800 shadow-2xs dark:border-red-800/80 dark:bg-red-950/50 dark:text-red-300"
                >
                    <div class="flex items-start gap-2">
                        <x-ui.icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
                        <p class="font-bold text-red-900 dark:text-red-200" x-text="errorTitle"></p>
                    </div>
                    <p x-text="errorMessage" class="leading-relaxed"></p>

                    {{-- Raw browser report, so a failure can be diagnosed without guessing --}}
                    <p x-show="errorDetail" x-cloak class="break-words font-mono text-[10px] leading-relaxed text-red-700/80 dark:text-red-400/80" x-text="errorDetail"></p>

                    {{-- Browser walkthrough: only when the site is genuinely blocked --}}
                    <div
                        x-show="showPermissionInstructions"
                        x-cloak
                        class="space-y-1 rounded-lg bg-red-100/70 p-2.5 text-[11px] text-red-900 dark:bg-red-900/40 dark:text-red-200"
                    >
                        <p class="font-bold">To allow camera access for this site:</p>
                        <ol class="list-decimal space-y-0.5 pl-4">
                            <li>Click the <strong>camera</strong> or <strong>lock / site settings</strong> icon at the left of your address bar (<code class="rounded bg-red-200/60 px-1 font-mono dark:bg-red-950">{{ request()->getHttpHost() }}</code>).</li>
                            <li>Set <strong>Camera</strong> to <strong>Allow</strong>.</li>
                            <li>Choose <strong>Retry Camera</strong> below.</li>
                        </ol>
                    </div>

                    <button
                        type="button"
                        @click="retryCamera()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-800 transition hover:bg-red-200 dark:bg-red-900/60 dark:text-red-200 dark:hover:bg-red-900"
                    >
                        <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                        <span>Retry Camera</span>
                    </button>
                </div>

                {{-- Non-blocking advisory: the camera started, but something is off --}}
                <div
                    x-show="notice"
                    x-cloak
                    role="status"
                    class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-800/70 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    <x-ui.icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <p x-text="notice" class="leading-relaxed"></p>
                </div>

                {{-- Fallbacks: image upload and manual entry --}}
                <div class="space-y-3 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">Have a barcode photo or QR screenshot?</span>
                        <label class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-2xs hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700">
                            <x-ui.icon name="arrow-up-tray" class="h-3 w-3" />
                            <span x-show="!isProcessingFile">Upload Image</span>
                            <span x-show="isProcessingFile" x-cloak>Reading&hellip;</span>
                            <input type="file" accept="image/*" class="sr-only" @change="onFileSelected($event)" :disabled="isProcessingFile">
                        </label>
                    </div>

                    {{-- Manual entry stays a fallback: collapsed by default so the
                         camera remains the primary workflow. --}}
                    <div x-data="{ manualOpen: false }">
                        <button
                            type="button"
                            @click="manualOpen = ! manualOpen"
                            class="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-200"
                            :aria-expanded="manualOpen ? 'true' : 'false'"
                            aria-controls="{{ $id }}-manual"
                        >
                            <span class="inline-flex transition-transform" :class="manualOpen ? 'rotate-180' : ''">
                                <x-ui.icon name="chevron-down" class="h-3 w-3" />
                            </span>
                            <span>Or type / paste identifier</span>
                        </button>

                        <div x-show="manualOpen" x-cloak id="{{ $id }}-manual" class="mt-2 flex gap-2">
                            <input
                                type="text"
                                x-model="manualCode"
                                placeholder="Barcode, SKU, or location code"
                                aria-label="Identifier to look up"
                                class="min-w-0 flex-1 rounded-lg border-neutral-300 font-mono text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500"
                                @keydown.enter.prevent="applyManualCode()"
                            >
                            <x-ui.button
                                type="button"
                                variant="primary"
                                size="sm"
                                @click="applyManualCode()"
                            >
                                Apply
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex shrink-0 items-center justify-between gap-3 border-t border-neutral-200 bg-neutral-50 px-5 py-3 dark:border-neutral-800 dark:bg-neutral-850">
                <span
                    class="text-[11px] text-neutral-500 dark:text-neutral-400"
                    x-text="isScanning ? 'Camera in use' : 'Camera released'"
                ></span>

                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="close()"
                >
                    <span x-text="isScanning ? 'Stop Camera & Close' : 'Close'"></span>
                </x-ui.button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes himsLaserScan {
    0% { top: 8%; opacity: 0.8; }
    50% { top: 88%; opacity: 1; }
    100% { top: 8%; opacity: 0.8; }
}
.hims-scanner-laser {
    animation: himsLaserScan 2s ease-in-out infinite;
}
@media (prefers-reduced-motion: reduce) {
    .hims-scanner-laser { animation: none; }
}
/* html5-qrcode sizes its own surface; this keeps the live picture undistorted
   and inside the modal on every viewport. */
#{{ $id }}-viewport video {
    width: 100% !important;
    height: auto !important;
    max-height: 52vh !important;
    object-fit: contain !important;
    background-color: #0a0a0a;
    border-radius: 0.75rem !important;
}
#{{ $id }}-viewport img {
    display: none !important;
}
</style>
