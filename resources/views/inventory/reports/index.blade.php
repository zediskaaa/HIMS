<x-app-layout>
    <x-ui.page-header
        title="Reports & Analytics"
        :subtitle="'Inventory valuation, stock status, procurement spend and movement history for the '.$period['days'].'-day window ending '.$period['to']->format('M d, Y').'.'"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Reports' => null]">
        <x-slot name="actions">
            {{-- Print rather than a CSV export: the panel and the hospital both
                 want a page that can be signed, and printing needs no new route
                 and no new dependency. --}}
            <x-ui.button variant="secondary" icon="document-text"
                         onclick="window.print()" class="print:hidden">
                Print
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{--
        One window drives every dated section on the page — spend, movement
        activity, consumption. The valuation and stock-status figures below are
        a position as of now and deliberately ignore it: "what we hold" is not
        a date range.
    --}}
    <x-ui.card class="print:hidden">
        <form method="GET" action="{{ route('inventory.reports') }}"
              class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="w-full sm:max-w-xs">
                <x-ui.field
                    name="days"
                    label="Reporting Period"
                    type="select"
                    :value="$period['days']"
                    :options="$periodOptions"
                    :hint="$period['from']->format('M d, Y').' — '.$period['to']->format('M d, Y')" />
            </div>

            <div class="flex items-center gap-2 pb-0.5">
                <x-ui.button type="submit" icon="magnifying-glass">Apply</x-ui.button>
                @if ($period['days'] !== \App\Services\InventoryReportService::DEFAULT_PERIOD_DAYS)
                    <x-ui.button variant="secondary" :href="route('inventory.reports')">Reset</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    {{-- ------------------------------------------------ 1. inventory summary --}}

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat
            label="Items in catalogue"
            :value="number_format($summary['items'])"
            icon="cube"
            tone="primary"
            :hint="number_format($summary['units_on_hand']).' units on hand'" />

        <x-ui.stat
            label="Stock valuation"
            :value="'₱'.number_format($summary['stock_value'], 2)"
            icon="chart-bar"
            tone="neutral"
            hint="Units on hand × unit cost." />

        <x-ui.stat
            label="Needs attention"
            :value="number_format($summary['needs_attention'])"
            icon="exclamation-triangle"
            :tone="$summary['needs_attention'] > 0 ? 'warning' : 'success'"
            hint="At or below reorder level, or out of stock."
            :href="route('inventory.alerts')" />

        <x-ui.stat
            label="Reserved units"
            :value="number_format($summary['reserved_units'])"
            icon="clipboard-document-list"
            tone="neutral"
            hint="Committed elsewhere — not available to issue." />
    </div>

    {{-- --------------------------------------------------- 2. stock status --}}

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Stock Status" subtitle="Every item, bucketed by how much is left.">
            @php
                // Written out rather than interpolated: Tailwind scans the
                // source for whole class names, so bg-{{ $tone }}-500 would
                // compile to nothing at all.
                $bars = [
                    'in_stock' => ['label' => 'In stock', 'variant' => 'success', 'bar' => 'bg-success-500'],
                    'low_stock' => ['label' => 'Low stock', 'variant' => 'warning', 'bar' => 'bg-warning-500'],
                    'out_of_stock' => ['label' => 'Out of stock', 'variant' => 'danger', 'bar' => 'bg-danger-500'],
                ];
            @endphp

            <dl class="space-y-4">
                @foreach ($bars as $key => $bar)
                    @php
                        $bucket = $stockStatus[$key];
                        $share = $summary['items'] > 0
                            ? round(($bucket['items'] / $summary['items']) * 100)
                            : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>
                                <x-ui.badge :variant="$bar['variant']" dot>{{ $bar['label'] }}</x-ui.badge>
                            </dt>
                            <dd class="text-sm font-semibold tabular-nums text-neutral-900">
                                {{ number_format($bucket['items']) }}
                                <span class="text-xs font-normal text-neutral-500">({{ $share }}%)</span>
                            </dd>
                        </div>

                        {{-- A bar rather than a chart library: one dependency
                             fewer, and it survives printing. --}}
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full {{ $bar['bar'] }}" style="width: {{ $share }}%"></div>
                        </div>

                        <p class="mt-1 text-xs text-neutral-500">
                            {{ number_format($bucket['units']) }} units &middot; ₱{{ number_format($bucket['value'], 2) }}
                        </p>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Expiry Exposure" subtitle="Batches still holding stock, valued at risk.">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-md border border-danger-200 bg-danger-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-danger-700">Expired</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-danger-800">
                        {{ number_format($expiry['expired']['units']) }}
                        <span class="text-xs font-normal">units</span>
                    </p>
                    <p class="text-xs text-danger-700">
                        {{ $expiry['expired']['batches'] }} batches &middot; ₱{{ number_format($expiry['expired']['value'], 2) }}
                    </p>
                </div>

                <div class="rounded-md border border-warning-200 bg-warning-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-warning-700">Expiring soon</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-warning-800">
                        {{ number_format($expiry['expiring_soon']['units']) }}
                        <span class="text-xs font-normal">units</span>
                    </p>
                    <p class="text-xs text-warning-700">
                        {{ $expiry['expiring_soon']['batches'] }} batches &middot; ₱{{ number_format($expiry['expiring_soon']['value'], 2) }}
                    </p>
                </div>
            </div>

            @if ($expiry['rows']->isNotEmpty())
                <ul class="mt-4 space-y-2 border-t border-neutral-200 pt-3">
                    @foreach ($expiry['rows'] as $batch)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-neutral-900">
                                    {{ $batch->item?->name ?? 'Unknown item' }}
                                </span>
                                <span class="block text-xs text-neutral-500">
                                    {{ $batch->batch_number }} &middot; {{ number_format((int) $batch->units_on_hand) }} units
                                </span>
                            </span>
                            <x-ui.badge :status="$batch->isExpired() ? 'expired' : 'expiring_soon'">
                                {{ $batch->expiry_date?->format('M d, Y') }}
                            </x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 border-t border-neutral-200 pt-3 text-xs text-neutral-500">
                    No dated batch is expired or inside its warning window.
                </p>
            @endif
        </x-ui.card>

        <x-ui.card title="Movement Summary" :subtitle="'Recorded in the last '.$period['days'].' days.'">
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Movements recorded</dt>
                    <dd class="font-semibold tabular-nums text-neutral-900">{{ number_format($movementTotals['movements']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Units received in</dt>
                    <dd class="font-semibold tabular-nums text-success-700">+{{ number_format($movementTotals['units_in']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Units consumed</dt>
                    <dd class="font-semibold tabular-nums text-neutral-900">&minus;{{ number_format($movementTotals['units_out']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Consumption value</dt>
                    <dd class="font-semibold tabular-nums text-neutral-900">₱{{ number_format($movementTotals['consumption_value'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-neutral-200 pt-3">
                    <dt class="text-neutral-600">Transfers</dt>
                    <dd class="tabular-nums text-neutral-700">{{ number_format($movementTotals['transfers']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-neutral-600">Units disposed</dt>
                    <dd class="tabular-nums text-neutral-700">{{ number_format($movementTotals['disposals']) }}</dd>
                </div>
            </dl>

            {{-- Stated on the screen because the numbers above will not add up
                 otherwise, and a panel will ask why. --}}
            <p class="mt-4 border-t border-neutral-200 pt-3 text-xs text-neutral-500">
                Consumed counts stock out and issuance only. A transfer moves stock between locations
                without anyone using it, so counting it on either side would report stock arriving or
                leaving when none did.
            </p>
        </x-ui.card>
    </div>

    {{-- ----------------------------------------------------- 3. valuation --}}

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Valuation by Category" subtitle="Where the money is tied up." :padding="false">
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>Category</x-ui.table.th>
                    <x-ui.table.th numeric>Items</x-ui.table.th>
                    <x-ui.table.th numeric>Units</x-ui.table.th>
                    <x-ui.table.th numeric>Value</x-ui.table.th>
                    <x-ui.table.th numeric>Share</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($valuationByCategory as $row)
                        <x-ui.table.row>
                            <x-ui.table.td>{{ $row->category }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row->items) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row->units) }}</x-ui.table.td>
                            <x-ui.table.td numeric>₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>
                                {{ $summary['stock_value'] > 0
                                    ? round(($row->value / $summary['stock_value']) * 100).'%'
                                    : '—' }}
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="5"
                            icon="cube"
                            title="No items yet"
                            message="Add inventory items and the valuation fills in." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="Stock by Location" subtitle="What each storage location is holding." :padding="false">
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>Location</x-ui.table.th>
                    <x-ui.table.th numeric>Items</x-ui.table.th>
                    <x-ui.table.th numeric>Units</x-ui.table.th>
                    <x-ui.table.th numeric>Value</x-ui.table.th>
                    <x-ui.table.th numeric>Utilisation</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($stockByLocation as $row)
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <span class="font-medium text-neutral-900">{{ $row['location'] }}</span>
                                @if ($row['code'])
                                    <span class="block text-xs text-neutral-500">{{ $row['code'] }}</span>
                                @endif
                            </x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row['items']) }}</x-ui.table.td>
                            <x-ui.table.td numeric>{{ number_format($row['units']) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>₱{{ number_format($row['value'], 2) }}</x-ui.table.td>
                            <x-ui.table.td numeric>
                                {{-- No capacity configured reads as unknown, not
                                     as an empty shelf. --}}
                                @if ($row['utilisation'] === null)
                                    <span class="text-neutral-400">—</span>
                                @else
                                    <span @class([
                                        'font-semibold',
                                        'text-danger-700' => $row['utilisation'] >= 90,
                                        'text-warning-700' => $row['utilisation'] >= 75 && $row['utilisation'] < 90,
                                        'text-neutral-800' => $row['utilisation'] < 75,
                                    ])>{{ $row['utilisation'] }}%</span>
                                    <span class="block text-xs text-neutral-400">
                                        of {{ number_format($row['capacity']) }}
                                    </span>
                                @endif
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="5"
                            icon="building-storefront"
                            title="No stock in any location"
                            message="Record a stock in and the location balances appear here." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>

    {{-- ------------------------------------------------ 4. expense reports --}}

    <x-ui.card
        title="Procurement Expense"
        :subtitle="'Purchase orders raised in the last '.$period['days'].' days, plus everything still outstanding.'">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ordered</p>
                <p class="mt-1.5 text-xl font-semibold tabular-nums text-neutral-900">
                    ₱{{ number_format($spend['ordered']['value'], 2) }}
                </p>
                <p class="mt-0.5 text-xs text-neutral-500">{{ $spend['ordered']['orders'] }} purchase orders</p>
            </div>

            <div class="rounded-md border border-success-200 bg-success-50 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-success-700">Received</p>
                <p class="mt-1.5 text-xl font-semibold tabular-nums text-success-800">
                    ₱{{ number_format($spend['received']['value'], 2) }}
                </p>
                <p class="mt-0.5 text-xs text-success-700">{{ $spend['received']['orders'] }} booked into stock</p>
            </div>

            <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-warning-700">Outstanding</p>
                <p class="mt-1.5 text-xl font-semibold tabular-nums text-warning-800">
                    ₱{{ number_format($spend['outstanding']['value'], 2) }}
                </p>
                <p class="mt-0.5 text-xs text-warning-700">
                    {{ $spend['outstanding']['orders'] }} awaiting delivery, all time
                </p>
            </div>

            <div class="rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Average order</p>
                <p class="mt-1.5 text-xl font-semibold tabular-nums text-neutral-900">
                    ₱{{ number_format($spend['average_order_value'], 2) }}
                </p>
                <p class="mt-0.5 text-xs text-neutral-500">Across orders raised in the window</p>
            </div>
        </div>

        <p class="mt-4 text-xs text-neutral-500">
            Ordered is dated by when the order was raised, Received by when the delivery was booked in —
            they describe different events, so they are not meant to reconcile inside one window.
            Outstanding covers every order not yet received or cancelled, regardless of date, because an
            order left open eight months ago is exactly the one a report should surface.
        </p>
    </x-ui.card>

    <x-ui.card title="Spend by Supplier" :subtitle="'Top vendors in the last '.$period['days'].' days.'" :padding="false">
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Supplier</x-ui.table.th>
                <x-ui.table.th numeric>Orders</x-ui.table.th>
                <x-ui.table.th numeric>Received</x-ui.table.th>
                <x-ui.table.th numeric>Fulfilment</x-ui.table.th>
                <x-ui.table.th numeric>Value</x-ui.table.th>
            </x-ui.table.head>
            <tbody>
                @forelse ($spendBySupplier as $row)
                    @php $rate = $row->orders > 0 ? round(($row->received_orders / $row->orders) * 100) : 0; @endphp
                    <x-ui.table.row>
                        <x-ui.table.td>
                            <span class="font-medium text-neutral-900">{{ $row->supplier }}</span>
                        </x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format($row->orders) }}</x-ui.table.td>
                        <x-ui.table.td numeric muted>{{ number_format($row->received_orders) }}</x-ui.table.td>
                        <x-ui.table.td numeric>
                            <x-ui.badge :variant="$rate >= 80 ? 'success' : ($rate >= 40 ? 'warning' : 'neutral')">
                                {{ $rate }}%
                            </x-ui.badge>
                        </x-ui.table.td>
                        <x-ui.table.td numeric>₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty
                        :colspan="5"
                        icon="truck"
                        title="No purchase orders in this window"
                        message="Raise one under Requisitions &amp; POs and the spend appears here." />
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    {{-- --------------------------------------------- 5. movement history --}}

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Activity by Movement Type" :subtitle="'Last '.$period['days'].' days.'" :padding="false">
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>Type</x-ui.table.th>
                    <x-ui.table.th numeric>Movements</x-ui.table.th>
                    <x-ui.table.th numeric>Units</x-ui.table.th>
                    <x-ui.table.th numeric>Value</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    {{-- Every type is listed even at zero. A type that vanishes
                         from the table reads as "not tracked"; a zero reads as
                         "nothing happened", which is the true statement. --}}
                    @foreach ($movementsByType as $row)
                        <x-ui.table.row :class="$row['movements'] === 0 ? 'opacity-60' : ''">
                            <x-ui.table.td>
                                <x-ui.badge :status="$row['type']->value">{{ $row['type']->label() }}</x-ui.badge>
                            </x-ui.table.td>
                            <x-ui.table.td numeric>{{ number_format($row['movements']) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row['units']) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>₱{{ number_format($row['value'], 2) }}</x-ui.table.td>
                        </x-ui.table.row>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="Most Consumed Items" subtitle="By units issued or taken out." :padding="false">
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.th>Item</x-ui.table.th>
                    <x-ui.table.th numeric>Consumed</x-ui.table.th>
                    <x-ui.table.th numeric>On Hand</x-ui.table.th>
                    <x-ui.table.th numeric>Value</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($topConsumedItems as $row)
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <span class="font-medium text-neutral-900">{{ $row->item }}</span>
                                <span class="block text-xs text-neutral-500">
                                    {{ $row->sku }} &middot; {{ $row->movements }} movements
                                </span>
                            </x-ui.table.td>
                            <x-ui.table.td numeric>
                                {{ number_format($row->units) }}
                                <span class="block text-xs font-normal text-neutral-400">{{ $row->unit ?? 'units' }}</span>
                            </x-ui.table.td>
                            <x-ui.table.td numeric muted>{{ number_format($row->on_hand) }}</x-ui.table.td>
                            <x-ui.table.td numeric muted>₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="4"
                            icon="arrows-right-left"
                            title="Nothing consumed in this window"
                            message="Stock out and issuance movements are what this counts." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>

    <x-ui.card
        title="Movement History"
        :subtitle="$recentMovements->count().' most recent in this window'"
        :padding="false">
        <x-slot name="actions">
            <x-ui.button variant="ghost" size="sm" :href="route('inventory.stock-movements')" class="print:hidden">
                View all
            </x-ui.button>
        </x-slot>

        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Item</x-ui.table.th>
                <x-ui.table.th>Type</x-ui.table.th>
                <x-ui.table.th numeric>Qty</x-ui.table.th>
                <x-ui.table.th>From</x-ui.table.th>
                <x-ui.table.th>To / Reference</x-ui.table.th>
                <x-ui.table.th>Recorded</x-ui.table.th>
            </x-ui.table.head>
            <tbody>
                @forelse ($recentMovements as $movement)
                    <x-ui.table.row>
                        <x-ui.table.td>
                            <span class="font-medium text-neutral-900">{{ $movement->item?->name ?? '—' }}</span>
                            @if ($movement->item?->sku)
                                <span class="block text-xs text-neutral-500">{{ $movement->item->sku }}</span>
                            @endif
                        </x-ui.table.td>
                        <x-ui.table.td>
                            <x-ui.badge :status="$movement->movement_type->value">
                                {{ $movement->movement_type->label() }}
                            </x-ui.badge>
                        </x-ui.table.td>
                        <x-ui.table.td numeric>{{ number_format($movement->quantity) }}</x-ui.table.td>
                        <x-ui.table.td muted>{{ $movement->fromLocation?->name ?? '—' }}</x-ui.table.td>
                        <x-ui.table.td muted>
                            {{-- Issuances, returns and goods receipts have no
                                 destination balance; their counterparty lives
                                 on the reference morph. --}}
                            @if ($movement->toLocation)
                                {{ $movement->toLocation->name }}
                            @elseif ($movement->reference)
                                {{ $movement->reference->name ?? class_basename($movement->reference) }}
                            @else
                                —
                            @endif
                        </x-ui.table.td>
                        <x-ui.table.td muted>
                            {{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}
                            @if ($movement->user)
                                <span class="block text-xs text-neutral-400">{{ $movement->user->name }}</span>
                            @endif
                        </x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty
                        :colspan="6"
                        icon="arrows-right-left"
                        title="No movements in this window"
                        message="Widen the reporting period, or record a movement to start the history." />
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <p class="text-xs text-neutral-400">
        Generated {{ now()->format('M d, Y g:i A') }} for {{ auth()->user()?->name }}.
        Every figure is read live from the same records the operational screens use.
    </p>
</x-app-layout>
