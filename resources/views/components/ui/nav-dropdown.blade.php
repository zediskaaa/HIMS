@props([
    'id' => null,
    'title',
    'icon' => null,
    'active' => false,
    'badge' => null,
])

@php
    $dropdownId = $id ?? \Illuminate\Support\Str::slug($title);
@endphp

<div
    x-data="typeof activeDropdown !== 'undefined' ? {} : { activeDropdown: {{ $active ? "'{$dropdownId}'" : 'null' }} }"
    class="space-y-1"
>
    <button
        type="button"
        @click="activeDropdown = (activeDropdown === '{{ $dropdownId }}' ? null : '{{ $dropdownId }}')"
        class="w-full group flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ $active ? 'text-primary-800 bg-primary-50/70' : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100/70' }}"
        :aria-expanded="activeDropdown === '{{ $dropdownId }}' ? 'true' : 'false'"
    >
        <span class="flex items-center gap-2.5 truncate">
            @if ($icon)
                <x-ui.icon
                    :name="$icon"
                    class="w-[18px] h-[18px] shrink-0 {{ $active ? 'text-primary-600' : 'text-neutral-400 group-hover:text-neutral-600' }}"
                />
            @endif
            <span class="truncate">{{ $title }}</span>
        </span>

        <span class="flex items-center gap-1.5 shrink-0">
            @if ($badge)
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold tabular-nums {{ $active ? 'bg-primary-100 text-primary-800' : 'bg-neutral-200/80 text-neutral-700' }}">
                    {{ $badge }}
                </span>
            @endif
            <svg
                class="w-3.5 h-3.5 text-neutral-400 group-hover:text-neutral-600 transition-transform duration-300 ease-in-out"
                :class="activeDropdown === '{{ $dropdownId }}' ? 'rotate-180 text-neutral-700' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </span>
    </button>

    <div
        class="grid transition-[grid-template-rows,opacity] duration-300 ease-in-out overflow-hidden"
        :class="activeDropdown === '{{ $dropdownId }}' ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'"
        :aria-hidden="activeDropdown === '{{ $dropdownId }}' ? 'false' : 'true'"
    >
        <div class="overflow-hidden min-h-0">
            <div
                class="pl-3 pr-1 pt-1 pb-1 space-y-0.5 border-l-2 border-neutral-200 ml-4 transition-transform duration-300 ease-in-out"
                :class="activeDropdown === '{{ $dropdownId }}' ? 'translate-y-0' : '-translate-y-1.5'"
            >
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
