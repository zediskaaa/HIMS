@props([
    'label' => '',
    'value' => '—',
    'icon' => null,
    'tone' => 'neutral',
    'hint' => null,
    'href' => null,
    'compact' => false,
])

@php
    $tones = [
        'neutral' => 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
        'primary' => 'bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400',
        'success' => 'bg-success-50 dark:bg-emerald-950/60 text-success-600 dark:text-emerald-400',
        'warning' => 'bg-warning-50 dark:bg-amber-950/60 text-warning-600 dark:text-amber-400',
        'danger' => 'bg-danger-50 dark:bg-rose-950/60 text-danger-600 dark:text-rose-400',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'block bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-lg shadow-sm transition-colors '
            .($compact ? 'p-3' : 'p-5')
            .($href ? ' hover:border-primary-300 dark:hover:border-primary-700 hover:bg-primary-50/30 dark:hover:bg-primary-950/30' : ''),
    ]) }}
>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</p>
        @if ($icon)
            <span @class([
                'flex items-center justify-center rounded-md shrink-0',
                'w-7 h-7' => $compact,
                'w-8 h-8' => ! $compact,
                $tones[$tone],
            ])>
                <x-ui.icon :name="$icon" class="w-4 h-4" />
            </span>
        @endif
    </div>

    <p @class([
        'font-semibold tabular-nums text-neutral-900 dark:text-neutral-100',
        'mt-1.5 text-xl' => $compact,
        'mt-3 text-2xl' => ! $compact,
    ])>{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $hint }}</p>
    @endif
</{{ $tag }}>
