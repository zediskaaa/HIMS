@props([
    'id',
    'targetInputId' => null,
    'buttonText' => 'Scan with Camera',
    'buttonVariant' => 'secondary',
    'buttonSize' => 'md',
    'autoSubmit' => false,
    'autoClose' => true,
    'eventName' => 'hims-code-scanned',
    'title' => 'Barcode & 2D QR Scanner',
    'hint' => 'Align barcode, GS1 DataMatrix, or QR code within the viewfinder frame.',
    'showTrigger' => true,
])

<div
    x-data="himsCameraScanner({
        containerId: '{{ $id }}-viewport',
        targetInputId: '{{ $targetInputId }}',
        autoSubmit: {{ $autoSubmit ? 'true' : 'false' }},
        autoClose: {{ $autoClose ? 'true' : 'false' }},
        eventName: '{{ $eventName }}'
    })"
    class="inline-block"
>
    @if($showTrigger)
        <button
            type="button"
            @click="open()"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50 hover:text-neutral-900 transition"
        >
            <x-ui.icon name="camera" class="h-3.5 w-3.5 text-neutral-600" />
            <span>{{ $buttonText }}</span>
        </button>
    @endif

    {{-- Scanner Modal Dialog --}}
    <div
        x-show="isOpen"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-neutral-900/75 p-4 backdrop-blur-xs"
        @keydown.escape.window="close()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
    >
        <div
            class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl border border-neutral-200"
            @click.away="close()"
        >
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-5 py-3.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-700">
                        <x-ui.icon name="camera" class="h-4 w-4" />
                    </span>
                    <div>
                        <h3 id="{{ $id }}-title" class="text-sm font-bold text-neutral-900 leading-tight">{{ $title }}</h3>
                        <p class="text-[11px] text-neutral-500">Live hardware camera capture</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-show="isScanning"
                        @click="switchCamera()"
                        class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-xs hover:bg-neutral-50"
                        title="Switch camera device or orientation"
                    >
                        <x-ui.icon name="arrows-right-left" class="h-3 w-3" />
                        <span>Flip</span>
                    </button>
                    <button
                        type="button"
                        @click="close()"
                        class="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 transition"
                        aria-label="Close scanner"
                    >
                        <x-ui.icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            {{-- Scanner Viewport Body --}}
            <div class="p-5 space-y-4">
                {{-- Live Camera Viewfinder --}}
                <div class="relative overflow-hidden rounded-xl bg-neutral-950 border border-neutral-800 shadow-inner flex items-center justify-center min-h-[280px]">
                    {{-- html5-qrcode mounts inside this element --}}
                    <div id="{{ $id }}-viewport" class="w-full"></div>

                    {{-- Camera Initializing State --}}
                    <div x-show="isStarting" class="absolute inset-0 flex flex-col items-center justify-center bg-neutral-950/90 text-white p-4 text-center z-10">
                        <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-500 border-t-transparent mb-3"></div>
                        <p class="text-xs font-medium">Requesting camera access...</p>
                        <p class="text-[11px] text-neutral-400 mt-1">Please confirm permission in your browser dialog</p>
                    </div>

                    {{-- Scanner Active Overlay Reticle --}}
                    <div x-show="isScanning" class="pointer-events-none absolute inset-0 flex items-center justify-center z-10">
                        <div class="relative w-[78%] h-[60%] border-2 border-dashed border-primary-400/80 rounded-lg shadow-2xl">
                            {{-- Viewfinder Corner Brackets --}}
                            <div class="absolute -top-1 -left-1 h-4 w-4 border-t-3 border-l-3 border-primary-500"></div>
                            <div class="absolute -top-1 -right-1 h-4 w-4 border-t-3 border-r-3 border-primary-500"></div>
                            <div class="absolute -bottom-1 -left-1 h-4 w-4 border-b-3 border-l-3 border-primary-500"></div>
                            <div class="absolute -bottom-1 -right-1 h-4 w-4 border-b-3 border-r-3 border-primary-500"></div>

                            {{-- Animated Laser Scan Line --}}
                            <div class="hims-scanner-laser absolute left-1 right-1 h-0.5 bg-gradient-to-r from-transparent via-red-500 to-transparent shadow-[0_0_8px_rgba(239,68,68,0.8)]"></div>
                        </div>
                    </div>
                </div>

                {{-- Status / Error Feedback Banner --}}
                <template x-if="errorMessage">
                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 space-y-1">
                        <div class="flex items-center gap-2 font-bold text-red-900">
                            <x-ui.icon name="exclamation-triangle" class="h-4 w-4 text-red-600 shrink-0" />
                            <span>Scanner Alert</span>
                        </div>
                        <p x-text="errorMessage" class="pl-6"></p>
                    </div>
                </template>

                {{-- Camera Active Indicator --}}
                <div x-show="isScanning && !errorMessage" class="flex items-center justify-between text-xs px-1 text-neutral-600">
                    <span class="flex items-center gap-1.5 font-medium text-emerald-700">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        Camera Active &amp; Ready
                    </span>
                    <span class="text-[11px] text-neutral-400 font-mono">1D &bull; 2D &bull; DataMatrix &bull; QR</span>
                </div>

                <p class="text-xs text-neutral-500 text-center leading-relaxed">
                    {{ $hint }}
                </p>

                {{-- Manual Fallback Entry in Modal --}}
                @if($targetInputId)
                    <div class="border-t border-neutral-200 pt-3">
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                            Or Type / Paste Identifier Directly:
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                placeholder="Enter code manually..."
                                class="min-w-0 flex-1 rounded-lg border-neutral-300 font-mono text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500"
                                @keydown.enter.prevent="
                                    if ($el.value.trim()) {
                                        const target = document.getElementById('{{ $targetInputId }}');
                                        if (target) {
                                            target.value = $el.value.trim();
                                            target.dispatchEvent(new Event('input', { bubbles: true }));
                                            target.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                        close();
                                        @if($autoSubmit)
                                            target?.closest('form')?.requestSubmit();
                                        @endif
                                    }
                                "
                            >
                            <x-ui.button
                                type="button"
                                variant="primary"
                                size="sm"
                                @click="
                                    const input = $el.previousElementSibling;
                                    if (input && input.value.trim()) {
                                        const target = document.getElementById('{{ $targetInputId }}');
                                        if (target) {
                                            target.value = input.value.trim();
                                            target.dispatchEvent(new Event('input', { bubbles: true }));
                                            target.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                        close();
                                        @if($autoSubmit)
                                            target?.closest('form')?.requestSubmit();
                                        @endif
                                    }
                                "
                            >
                                Apply
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-end border-t border-neutral-200 bg-neutral-50 px-5 py-3 gap-2">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="close()"
                >
                    Cancel / Stop Camera
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
#{{ $id }}-viewport video {
    width: 100% !important;
    height: auto !important;
    max-height: 340px !important;
    object-fit: cover !important;
    border-radius: 0.75rem !important;
}
#{{ $id }}-viewport img {
    display: none !important;
}
</style>
