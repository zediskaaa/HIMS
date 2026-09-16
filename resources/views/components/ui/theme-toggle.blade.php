@props([
    'size' => 'md',
    'showLabel' => false,
])

@php
    $sizeClasses = match($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-10 w-10 text-sm',
        default => 'h-9 w-9 text-sm',
    };
    $iconSize = match($size) {
        'sm' => 'w-4 h-4',
        'lg' => 'w-5 h-5',
        default => 'w-4.5 h-4.5',
    };
@endphp

<button
    type="button"
    data-theme-toggle
    x-data
    x-on:click="$store.theme.toggle()"
    {{ $attributes->merge([
        'class' => 'relative flex items-center justify-center rounded-lg text-neutral-500 hover:text-neutral-900 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:text-neutral-100 dark:hover:bg-neutral-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 ' . ($showLabel ? 'px-3 py-1.5 gap-2 w-auto' : $sizeClasses),
    ]) }}
    :aria-label="$store.theme?.isDark ? 'Switch to light theme' : 'Switch to dark theme'"
    :title="$store.theme?.isDark ? 'Switch to light theme' : 'Switch to dark theme'"
>
    {{-- Sun icon (shown when dark mode is active to switch to light) --}}
    <span x-show="$store.theme?.isDark" x-cloak class="flex items-center justify-center">
        <x-ui.icon name="sun" class="{{ $iconSize }} text-amber-400 transition-transform duration-200 rotate-0 hover:rotate-45" />
    </span>

    {{-- Moon icon (shown when light mode is active to switch to dark) --}}
    <span x-show="!$store.theme?.isDark" class="flex items-center justify-center">
        <x-ui.icon name="moon" class="{{ $iconSize }} text-neutral-600 dark:text-neutral-300 transition-transform duration-200 -rotate-12 hover:rotate-0" />
    </span>

    @if($showLabel)
        <span class="text-xs font-medium" x-text="$store.theme?.isDark ? 'Light mode' : 'Dark mode'">
            Theme
        </span>
    @endif
</button>
