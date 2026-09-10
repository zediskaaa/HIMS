@props([
    'stickyHeader' => true,
    'zebra' => false,
])

{{--
    Scrollable, semantic table shell.
    Compose with <x-ui.table.head>, <x-ui.table.row>, <x-ui.table.th>, <x-ui.table.td>.
--}}
<div class="relative max-w-full overscroll-x-contain overflow-x-auto {{ $stickyHeader ? 'max-h-[70vh] overflow-y-auto' : '' }}" tabindex="0" role="region" aria-label="Scrollable data table">
    <table {{ $attributes->merge(['class' => 'min-w-full text-sm border-collapse']) }}
           data-zebra="{{ $zebra ? '1' : '0' }}">
        {{ $slot }}
    </table>
</div>
