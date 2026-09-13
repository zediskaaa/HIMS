@props(['supplier' => null, 'size' => 'md'])

@php
    $sizeClasses = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-9 w-9 text-xs',
        'lg' => 'h-10 w-10 text-xs',
        'xl' => 'h-14 w-14 text-base',
        '2xl' => 'h-12 w-12 text-sm',
    ];
    $dimension = $sizeClasses[$size] ?? $sizeClasses['md'];

    $logoUrl = $supplier?->logoUrl();
    $name = $supplier?->name ?? '';
    $initials = str($name)->explode(' ')->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: '?';
@endphp

{{--
    The supplier's name is always rendered as adjacent text (directory row, mobile
    list, page title), so the mark itself is decorative: the image carries an empty
    alt and the initials fallback is hidden from assistive tech.
--}}
@if ($logoUrl)
    <img
        src="{{ $logoUrl }}"
        alt=""
        {{ $attributes->merge(['class' => "shrink-0 rounded-md border border-neutral-200 bg-white object-contain {$dimension}"]) }}
        loading="lazy"
    />
@else
    <span
        aria-hidden="true"
        {{ $attributes->merge(['class' => "flex shrink-0 items-center justify-center rounded-md bg-primary-100 font-bold text-primary-700 {$dimension}"]) }}
    >{{ $initials }}</span>
@endif
