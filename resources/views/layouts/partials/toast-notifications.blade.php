@php
    $initialToasts = [];
    if (session('status') || session('success')) {
        $initialToasts[] = [
            'id' => 'toast-'.uniqid(),
            'type' => 'success',
            'title' => 'Success',
            'message' => (string) (session('status') ?? session('success')),
        ];
    }
    if (session('error')) {
        $initialToasts[] = [
            'id' => 'toast-'.uniqid(),
            'type' => 'error',
            'title' => 'Error',
            'message' => (string) session('error'),
        ];
    }
    if (session('info')) {
        $initialToasts[] = [
            'id' => 'toast-'.uniqid(),
            'type' => 'info',
            'title' => 'Notice',
            'message' => (string) session('info'),
        ];
    }
    if (session('warning')) {
        $initialToasts[] = [
            'id' => 'toast-'.uniqid(),
            'type' => 'warning',
            'title' => 'Warning',
            'message' => (string) session('warning'),
        ];
    }
@endphp

{{-- Screen-reader and automated test visibility fallback --}}
<div class="sr-only" aria-live="polite">
    @foreach($initialToasts as $initial)
        <div>{{ $initial['message'] }}</div>
    @endforeach
</div>

{{-- Modern Floating Toast Notification HUD --}}
<div
    x-data="himsToastNotifications({{ Js::from($initialToasts) }})"
    x-on:notify.window="addToast($event.detail)"
    x-on:toast.window="addToast($event.detail)"
    class="pointer-events-none fixed top-16 right-0 z-[80] flex max-h-screen w-full flex-col items-end gap-3 p-4 sm:p-6 sm:max-w-md"
    aria-live="assertive"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4 scale-95"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95 translate-y-1"
            x-on:mouseenter="pauseTimer(toast)"
            x-on:mouseleave="resumeTimer(toast)"
            class="pointer-events-auto relative w-full overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/95 p-4 shadow-xl backdrop-blur-md transition-all dark:border-neutral-700 dark:bg-neutral-900/95"
            role="status"
        >
            <div class="flex items-start gap-3.5">
                {{-- Status Icon Badge --}}
                <div class="shrink-0 mt-0.5">
                    <template x-if="toast.type === 'success'">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200/80">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                    </template>
                    <template x-if="toast.type === 'error' || toast.type === 'danger'">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-50 text-rose-600 ring-1 ring-rose-200/80">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-1 ring-amber-200/80">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </span>
                    </template>
                    <template x-if="toast.type === 'info' || !['success', 'error', 'danger', 'warning'].includes(toast.type)">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-200/80">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                    </template>
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-neutral-400" x-text="toast.title || (toast.type === 'success' ? 'Success' : 'Notice')"></p>
                        <span class="text-[10px] text-neutral-400">Just now</span>
                    </div>
                    <p class="mt-0.5 text-xs sm:text-sm font-medium leading-snug text-neutral-800 dark:text-neutral-200" x-text="toast.message"></p>
                </div>

                {{-- Dismiss Button --}}
                <button
                    type="button"
                    x-on:click="dismiss(toast)"
                    class="shrink-0 -mr-1 -mt-1 rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:bg-neutral-800 dark:text-neutral-500"
                    aria-label="Dismiss notification"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Auto-dismiss countdown bar --}}
            <div class="absolute bottom-0 inset-x-0 h-0.5 bg-neutral-100 dark:bg-neutral-800">
                <div
                    class="h-full transition-all ease-linear"
                    :class="{
                        'bg-emerald-500': toast.type === 'success',
                        'bg-rose-500': toast.type === 'error' || toast.type === 'danger',
                        'bg-amber-500': toast.type === 'warning',
                        'bg-primary-500': toast.type === 'info' || !['success', 'error', 'danger', 'warning'].includes(toast.type),
                    }"
                    :style="`width: ${toast.progress}%; transition-duration: 50ms;`"
                ></div>
            </div>
        </div>
    </template>
</div>
