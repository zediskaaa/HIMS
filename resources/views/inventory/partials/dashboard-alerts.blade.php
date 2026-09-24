{{-- Compact live-alert summary used by the dashboard's 30-second poll. --}}
<div data-dashboard-alert-list>
    <div class="flex items-center justify-between gap-3 border-b border-neutral-200 bg-neutral-50 px-4 py-2.5 text-xs">
        <span class="text-neutral-600">
            <strong class="font-semibold tabular-nums text-neutral-900">{{ number_format($openAlertCount) }}</strong> open
        </span>
        <span class="text-neutral-500">
            <strong class="font-semibold tabular-nums text-neutral-800">{{ number_format($lowStockItems) }}</strong> need reorder
        </span>
    </div>

    <div class="divide-y divide-neutral-100">
        @forelse ($attentionItems->take(3) as $item)
            @php
                $isOutOfStock = (int) $item->quantity_on_hand <= 0;
                $severityClasses = $isOutOfStock
                    ? 'bg-danger-50 text-danger-700 ring-danger-600/20'
                    : 'bg-warning-50 text-warning-700 ring-warning-600/20';
            @endphp
            <a href="{{ route('inventory.alerts') }}" class="flex items-start gap-3 px-4 py-2.5 transition-colors hover:bg-neutral-50">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $isOutOfStock ? 'bg-danger-500' : 'bg-warning-500' }}" aria-hidden="true"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-neutral-900">{{ $item->name }}</span>
                    <span class="mt-0.5 block truncate text-[11px] text-neutral-500">
                        {{ $isOutOfStock ? 'Out of stock' : 'Low stock' }}
                        &middot; {{ number_format((int) $item->quantity_on_hand) }} on hand
                        &middot; reorder at {{ number_format((int) $item->reorder_level) }}
                    </span>
                </span>
                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $severityClasses }}">
                    {{ $isOutOfStock ? 'Critical' : 'Warning' }}
                </span>
            </a>
        @empty
            <div class="px-4 py-7 text-center">
                <span class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-success-50 text-success-700">
                    <x-ui.icon name="check-circle" class="h-4 w-4" />
                </span>
                <p class="mt-2 text-xs font-semibold text-neutral-800">No active alerts</p>
                <p class="mt-0.5 text-[11px] text-neutral-500">Inventory conditions are within their alert thresholds.</p>
            </div>
        @endforelse
    </div>
</div>
