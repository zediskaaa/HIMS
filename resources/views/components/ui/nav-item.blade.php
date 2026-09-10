@props([
    'href' => '#',
    'icon' => null,
    'active' => false,
    'badge' => null,
    'disabled' => false,
])

@php
    $classes = 'group flex min-h-11 items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors '
        .'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';

    $state = match (true) {
        $disabled => 'text-neutral-400 cursor-not-allowed',
        (bool) $active => 'bg-primary-50 text-primary-700 font-medium',
        default => 'text-neutral-600 font-medium hover:bg-neutral-100 hover:text-neutral-900',
    };
@endphp

<a
    href="{{ $disabled ? '#' : $href }}"
    @if ($disabled) aria-disabled="true" tabindex="-1" @endif
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => $classes.' '.$state]) }}
>
    @if ($icon)
        <x-ui.icon
            :name="$icon"
            class="w-[18px] h-[18px] shrink-0 {{ $active ? 'text-primary-600' : 'text-neutral-400 group-hover:text-neutral-600' }}"
        />
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($disabled)
        <span class="text-[10px] font-medium uppercase tracking-wide text-neutral-400">Soon</span>
    @elseif ($badge)
        <span class="px-1.5 py-0.5 rounded-full text-[11px] font-semibold tabular-nums
                     {{ $active ? 'bg-primary-100 text-primary-700' : 'bg-neutral-100 text-neutral-600' }}">
            {{ $badge }}
        </span>
    @endif
</a>
