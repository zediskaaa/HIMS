@props([
    'label' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'loader--sm',
        'md' => 'loader--md',
        'lg' => 'loader--lg',
    ];
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}
    @if ($label) role="status" aria-live="polite" aria-atomic="true" @endif
>
    <span class="loader {{ $sizes[$size] ?? $sizes['md'] }}" aria-hidden="true"></span>
    @if ($label)
        <span>{{ $label }}</span>
    @endif
</span>
