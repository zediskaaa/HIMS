@props([
    'category',
    'size' => 'md',
    'surface' => true,
])

@php
    $artwork = [
        'compliance' => 'empty-compliance.png',
        'finance' => 'empty-finance.png',
        'governance' => 'empty-governance.png',
        'inventory' => 'empty-inventory.png',
        'logistics' => 'empty-logistics.png',
        'narcotics' => 'empty-narcotics.png',
        'procurement' => 'empty-procurement.png',
        'receiving-discrepancies' => 'empty-receiving-discrepancies.png',
        'receiving' => 'empty-receiving.png',
        'reports' => 'empty-reports.png',
        'suppliers' => 'empty-suppliers.png',
        'users' => 'empty-users.png',
        'warehouse' => 'empty-warehouse.png',
    ];

    $sizes = [
        'sm' => 'h-32 w-32 sm:h-36 sm:w-36',
        'md' => 'h-32 w-32 sm:h-36 sm:w-36',
    ];

    $source = $artwork[$category] ?? null;
@endphp

@if ($source)
    <img
        src="{{ asset('img/'.$source) }}"
        alt=""
        aria-hidden="true"
        loading="lazy"
        decoding="async"
        {{ $attributes->class(['mx-auto block', $sizes[$size] ?? $sizes['md'], 'object-contain drop-shadow-sm']) }}
        data-empty-artwork="{{ $category }}"
        @if ($surface) data-empty-surface-artwork @endif
    >
@endif
