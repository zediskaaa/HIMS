@props([
    'name',
    'title' => null,
    'maxWidth' => 'lg',
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
@endphp

{{--
    Alpine-driven dialog. Open it from anywhere with:
        $dispatch('open-modal', 'create-item')
    Closes on Escape and on backdrop click.
--}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') { open = true }"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') { open = false }"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex min-h-full items-center justify-center overflow-y-auto p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div
        x-show="open"
        x-transition.opacity
        x-on:click="open = false"
        class="fixed inset-0 bg-neutral-900/50"
        aria-hidden="true"
    ></div>

    <div
        x-show="open"
        x-transition
        class="relative flex max-h-[calc(100dvh-2rem)] w-full flex-col overflow-hidden {{ $widths[$maxWidth] ?? $widths['lg'] }} bg-white rounded-lg shadow-lg border border-neutral-200"
    >
        @if ($title || isset($header))
            <header class="flex items-start justify-between gap-4 px-5 py-4 border-b border-neutral-200">
                @isset($header)
                    {{ $header }}
                @else
                    <h2 class="text-sm font-semibold text-neutral-900">{{ $title }}</h2>
                @endisset

                <button type="button" x-on:click="open = false"
                        class="p-1 -m-1 rounded text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                    <span class="sr-only">Close</span>
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </header>
        @endif

        <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-4 py-4 sm:px-5">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
