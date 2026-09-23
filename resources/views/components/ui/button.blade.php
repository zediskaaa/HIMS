@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-md border transition-colors shrink-0 whitespace-nowrap '
        .'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900 '
        .'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none';

    $variants = [
        'primary' => 'bg-primary-600 border-primary-600 text-white hover:bg-primary-700 hover:border-primary-700 '
            .'active:bg-primary-800 focus-visible:ring-primary-500 dark:bg-primary-600 dark:hover:bg-primary-500 dark:active:bg-primary-700',
        'secondary' => 'bg-white border-neutral-300 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 '
            .'active:bg-neutral-100 focus-visible:ring-primary-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700 dark:hover:text-white dark:active:bg-neutral-600',
        'danger' => 'bg-danger-600 border-danger-600 text-white hover:bg-danger-700 hover:border-danger-700 '
            .'active:bg-danger-700 focus-visible:ring-danger-500 dark:bg-danger-600 dark:hover:bg-danger-500',
        'ghost' => 'bg-transparent border-transparent text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 '
            .'focus-visible:ring-primary-500 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
    ];

    $sizes = [
        'sm' => 'min-h-9 px-2.5 py-1.5 text-xs',
        'md' => 'min-h-10 px-3.5 py-2 text-sm',
        'lg' => 'min-h-11 px-4 py-2.5 text-sm',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
        {{ $slot }}
    </button>
@endif
