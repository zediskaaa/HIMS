{{--
    Rendered both by the dashboard on first paint and by dashboard.live, which
    returns this same partial as HTML for the 30s poll to swap in. Keeping one
    template for both means the polled markup can never drift from the
    server-rendered markup.
--}}
<x-ui.table :sticky-header="false">
    <x-ui.table.head>
        <x-ui.table.th>Item</x-ui.table.th>
        <x-ui.table.th>Condition</x-ui.table.th>
        <x-ui.table.th numeric>Level</x-ui.table.th>
        <x-ui.table.th>Status</x-ui.table.th>
    </x-ui.table.head>

    <tbody>
        @forelse ($activeAlerts as $alert)
            <x-ui.table.row>
                <x-ui.table.td>
                    <span class="font-medium text-neutral-900">
                        {{ $alert->item?->name ?? 'Unknown item' }}
                    </span>
                    @if ($alert->item?->sku)
                        <span class="block text-xs text-neutral-500">{{ $alert->item->sku }}</span>
                    @endif
                </x-ui.table.td>

                <x-ui.table.td>
                    <x-ui.badge :status="$alert->type->value" dot>
                        {{ $alert->type === \App\Enums\AlertType::ExpiringSoon && $alert->severity === \App\Enums\AlertSeverity::Critical
                            ? 'Critical / Near Expiry'
                            : $alert->type->label() }}
                    </x-ui.badge>
                    @if ($alert->location)
                        <span class="block mt-1 text-xs text-neutral-500">{{ $alert->location->name }}</span>
                    @endif
                </x-ui.table.td>

                <x-ui.table.td numeric>
                    {{-- current_value is the snapshot taken when the alert was raised;
                         the item's own quantity is the live number. Prefer the live one. --}}
                    <span class="font-medium">
                        {{ number_format((int) ($alert->item?->quantity_on_hand ?? $alert->current_value)) }}
                    </span>
                    @if ($alert->threshold_value)
                        <span class="text-neutral-400">/ {{ number_format((int) $alert->threshold_value) }}</span>
                    @endif
                </x-ui.table.td>

                <x-ui.table.td>
                    <x-ui.badge :status="$alert->status->value" />
                </x-ui.table.td>
            </x-ui.table.row>
        @empty
            <x-ui.table.empty
                :colspan="4"
                icon="bell-alert"
                title="No active alerts"
                message="Stock levels and batch expiry dates are all within their thresholds."
            />
        @endforelse
    </tbody>
</x-ui.table>
