@props([
    'href' => '#',
    'icon' => null,
    'active' => false,
    'badge' => null,
    'disabled' => false,
    'sub' => false,
])

@php
    $classes = $sub
        ? 'group flex min-h-8 items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 '
        : 'group flex min-h-11 items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 ';

    $state = match (true) {
        $disabled => 'text-neutral-400 dark:text-neutral-600 cursor-not-allowed',
        (bool) $active => $sub
            ? 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 font-semibold shadow-2xs'
            : 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 font-medium',
        default => 'text-neutral-600 dark:text-neutral-400 font-medium hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-neutral-100',
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
            class="{{ $sub ? 'w-4 h-4' : 'w-[18px] h-[18px]' }} shrink-0 {{ $active ? 'text-primary-600 dark:text-primary-400' : 'text-neutral-400 dark:text-neutral-500 group-hover:text-neutral-600 dark:group-hover:text-neutral-300' }}"
        />
    @elseif ($sub)
        <span class="w-1.5 h-1.5 rounded-full shrink-0 transition-colors {{ $active ? 'bg-primary-600 dark:bg-primary-400 ring-2 ring-primary-200 dark:ring-primary-900' : 'bg-neutral-300 dark:bg-neutral-600 group-hover:bg-neutral-400 dark:group-hover:bg-neutral-500' }}"></span>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($disabled)
        <span class="text-[10px] font-medium uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Soon</span>
    @elseif ($badge)
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold tabular-nums
                     {{ $active ? 'bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400' }}">
            {{ $badge }}
        </span>
    @endif
</a>
