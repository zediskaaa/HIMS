@props([
    'variant' => 'info',
    'title' => null,
    'message' => null,
    'dismissible' => false,
])

@php
    $styles = [
        'info' => ['bg-primary-50 dark:bg-primary-950/50 border-primary-200 dark:border-primary-900/60 text-primary-900 dark:text-primary-200', 'text-primary-600 dark:text-primary-400', 'exclamation-triangle'],
        'success' => ['bg-success-50 dark:bg-emerald-950/50 border-success-200 dark:border-emerald-900/60 text-success-700 dark:text-emerald-200', 'text-success-600 dark:text-emerald-400', 'clipboard-document-list'],
        'warning' => ['bg-warning-50 dark:bg-amber-950/50 border-warning-200 dark:border-amber-900/60 text-warning-700 dark:text-amber-200', 'text-warning-600 dark:text-amber-400', 'exclamation-triangle'],
        'danger' => ['bg-danger-50 dark:bg-rose-950/50 border-danger-200 dark:border-rose-900/60 text-danger-700 dark:text-rose-200', 'text-danger-600 dark:text-rose-400', 'exclamation-triangle'],
    ];
    [$box, $iconTone, $icon] = $styles[$variant] ?? $styles['info'];
@endphp

<div
    @if ($dismissible) x-data="{ show: true }" x-show="show" @endif
    role="{{ in_array($variant, ['danger', 'warning']) ? 'alert' : 'status' }}"
    {{ $attributes->merge(['class' => 'flex items-start gap-3 p-4 border rounded-md text-sm '.$box]) }}
>
    <x-ui.icon :name="$icon" class="w-5 h-5 shrink-0 {{ $iconTone }}" />

    <div class="flex-1 min-w-0">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ trim($slot) !== '' ? $slot : $message }}</div>
    </div>

    @if ($dismissible)
        <button type="button" x-on:click="show = false"
                class="p-0.5 -m-0.5 rounded opacity-60 hover:opacity-100
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current">
            <span class="sr-only">Dismiss</span>
            <x-ui.icon name="x-mark" class="w-4 h-4" />
        </button>
    @endif
</div>
