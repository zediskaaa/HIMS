@props([
    'user' => null,
    'size' => 'md',
])

@php
    $sizeClasses = [
        'xs' => 'h-6 w-6 text-[10px]',
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-14 w-14 text-base font-bold',
        'xl' => 'h-20 w-20 text-xl font-bold',
    ];

    $dimension = $sizeClasses[$size] ?? $sizeClasses['md'];
    $avatarUrl = $user?->avatarUrl();
    $initials = $user?->initials() ?? '?';
@endphp

@if ($avatarUrl)
    <img
        src="{{ $avatarUrl }}"
        alt="{{ $user?->name ?? 'User avatar' }}"
        {{ $attributes->merge(['class' => "rounded-full object-cover shrink-0 ring-1 ring-neutral-200/80 {$dimension}"]) }}
        loading="lazy"
    />
@else
    <span {{ $attributes->merge(['class' => "flex items-center justify-center rounded-full shrink-0 font-semibold bg-primary-100 text-primary-800 {$dimension}"]) }}>
        {{ $initials }}
    </span>
@endif
