@props([
    'status' => null,
    'variant' => null,
    'dot' => false,
])

@php
    // Map domain statuses onto the semantic palette so a status string can be
    // passed straight from a model without the page deciding on a colour.
    $map = [
        // green — settled, healthy, done
        'in_stock' => 'success', 'active' => 'success', 'approved' => 'success',
        'fulfilled' => 'success', 'received' => 'success', 'completed' => 'success',
        'resolved' => 'success', 'delivered' => 'success',

        // amber — needs attention, in flight
        'low_stock' => 'warning', 'pending' => 'warning', 'submitted' => 'warning',
        'expiring_soon' => 'warning', 'partially_fulfilled' => 'warning',
        'acknowledged' => 'warning', 'under_review' => 'warning',
        'pending_review' => 'warning', 'action_required' => 'danger',
        'warning' => 'warning',

        // red — blocked, failed, critical
        'out_of_stock' => 'danger', 'expired' => 'danger', 'rejected' => 'danger',
        'cancelled' => 'danger', 'critical' => 'danger', 'open' => 'danger',

        // neutral — inert states
        'draft' => 'neutral', 'inactive' => 'neutral', 'archived' => 'neutral',
        'current' => 'success', 'suspended' => 'danger',
        'converted' => 'primary',
        'info' => 'primary',

        // movement types — direction of stock, not health
        'stock_in' => 'success', 'stock_out' => 'primary', 'transfer' => 'primary',
        'adjustment' => 'neutral', 'disposal' => 'danger',
        // issuance is ordinary consumption; a return is an exception worth spotting
        'issuance' => 'primary', 'return_to_supplier' => 'warning',
    ];

    $key = $variant ?? ($map[strtolower((string) $status)] ?? 'neutral');

    // Contrast checked against white: all text tones are 700-level on a 50-level fill.
    $styles = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20',
        'neutral' => 'bg-neutral-100 text-neutral-700 ring-neutral-500/20',
    ];

    $dots = [
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        'primary' => 'bg-primary-500',
        'neutral' => 'bg-neutral-400',
    ];

    $label = trim($slot) !== '' ? $slot : \Illuminate\Support\Str::headline((string) $status);
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium '
        .'ring-1 ring-inset whitespace-nowrap '.$styles[$key],
]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dots[$key] }}" aria-hidden="true"></span>
    @endif
    {{ $label }}
</span>
