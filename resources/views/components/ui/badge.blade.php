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
        'resolved' => 'success', 'delivered' => 'success', 'healthy' => 'success',

        // amber — needs attention, in flight
        'low_stock' => 'warning', 'pending' => 'warning', 'submitted' => 'warning',
        'expiring_soon' => 'warning', 'partially_fulfilled' => 'warning',
        'acknowledged' => 'warning', 'under_review' => 'warning',
        'pending_review' => 'warning', 'action_required' => 'danger',
        'warning' => 'warning', 'degraded' => 'warning',

        // red — blocked, failed, critical
        'out_of_stock' => 'danger', 'expired' => 'danger', 'rejected' => 'danger',
        'cancelled' => 'danger', 'critical' => 'danger', 'open' => 'danger',
        'unhealthy' => 'danger',

        // recovery incidents — a failure is red, work in flight is amber, a
        // verified recovery is green, and "no retry exists" is simply inert
        'recovery_failed' => 'danger', 'failed' => 'danger',
        'recovery_pending' => 'warning', 'retrying' => 'warning',
        'recovered' => 'success',
        'not_recoverable' => 'neutral',

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

    // Contrast checked against white and dark backgrounds:
    $styles = [
        'success' => 'bg-success-50 dark:bg-emerald-950/60 text-success-700 dark:text-emerald-300 ring-success-600/20 dark:ring-emerald-500/30',
        'warning' => 'bg-warning-50 dark:bg-amber-950/60 text-warning-700 dark:text-amber-300 ring-warning-600/20 dark:ring-amber-500/30',
        'danger' => 'bg-danger-50 dark:bg-rose-950/60 text-danger-700 dark:text-rose-300 ring-danger-600/20 dark:ring-rose-500/30',
        'primary' => 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 ring-primary-600/20 dark:ring-primary-500/30',
        'neutral' => 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 ring-neutral-500/20 dark:ring-neutral-700',
    ];

    $dots = [
        'success' => 'bg-success-500 dark:bg-emerald-400',
        'warning' => 'bg-warning-500 dark:bg-amber-400',
        'danger' => 'bg-danger-500 dark:bg-rose-400',
        'primary' => 'bg-primary-500 dark:bg-primary-400',
        'neutral' => 'bg-neutral-400 dark:bg-neutral-500',
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
