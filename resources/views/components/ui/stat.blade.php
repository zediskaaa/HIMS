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
        'neutral' => 'bg-neutral-100 text-neutral-600',
        'primary' => 'bg-primary-50 text-primary-600',
        'success' => 'bg-success-50 text-success-600',
        'warning' => 'bg-warning-50 text-warning-600',
        'danger' => 'bg-danger-50 text-danger-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'block bg-white border border-neutral-200 rounded-lg shadow-sm transition-colors '
            .($compact ? 'p-3' : 'p-5')
            .($href ? ' hover:border-primary-300 hover:bg-primary-50/30' : ''),
    ]) }}
>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</p>
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
        'font-semibold tabular-nums text-neutral-900',
                'mt-1.5 text-xl' => $compact,
        'mt-3 text-2xl' => ! $compact,
    ])>{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs text-neutral-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
