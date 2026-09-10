<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Inventory Control</span>
                    <span class="text-xs text-neutral-500">• Dual-Tier Authorization &amp; Ledger Reconciliations</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Stock Adjustments</h2>
                <p class="text-sm text-neutral-600">
                    Documented quantity and value corrections. Adjustments exceeding ₱25,000 trigger mandatory second-tier Plant Controller authorization.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Flash Alerts --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('info'))
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ session('info') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Stock Adjustment Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Summary Cards --}}
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Total Adjustments</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $adjustments->total() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Documented adjustments</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Pending Review</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600">
                        {{ $adjustments->where('status', 'pending_approval')->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Tier 1 supervisor review</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Dual-Tier Required</p>
                    <p class="mt-2 text-2xl font-bold text-rose-600">
                        {{ $adjustments->where('status', 'second_approval_required')->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Over ₱25,000 threshold</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Posted &amp; Reconciled</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">
                        {{ $adjustments->where('status', 'posted')->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Written to stock movement ledger</p>
                </div>
            </div>

            {{-- Apply / Request Adjustment Form Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="border-b border-neutral-200 pb-4">
                    <h3 class="text-base font-semibold text-neutral-900">Request Stock Adjustment</h3>
                    <p class="text-xs text-neutral-500">
                        Adjustments create formal requests and update the immutable stock movement ledger once authorized.
                    </p>
                </div>

                <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="mt-4 grid gap-4 md:grid-cols-2"
                      data-confirm-title="Confirm stock adjustment"
                      data-confirm-message="Are you sure you want to apply this stock adjustment?"
                      data-confirm-label="Apply Adjustment">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Inventory Item</label>
                        <select name="item_id" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                    {{ $item->name }} ({{ $item->sku ?? 'No SKU' }}) &bull; {{ number_format((int) $item->quantity_on_hand) }} on hand &bull; ₱{{ number_format($item->unit_cost ?? 0, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Storage Location</label>
                        <select name="location_id" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                                    {{ $location->name }}{{ $location->code ? ' ('.$location->code.')' : '' }} &bull; {{ $location->zone ?? 'Unrestricted' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Adjustment Type</label>
                        <select name="adjustment_type" class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            <option value="increase" @selected(old('adjustment_type') === 'increase')>Increase (+)</option>
                            <option value="decrease" @selected(old('adjustment_type') === 'decrease')>Decrease (-)</option>
                            <option value="correction" @selected(old('adjustment_type') === 'correction')>Count Reconciliation / Set Exact</option>
                            <option value="damage" @selected(old('adjustment_type') === 'damage')>Damage Write-off (-)</option>
                            <option value="loss" @selected(old('adjustment_type') === 'loss')>Loss / Theft (-)</option>
                            <option value="expiry" @selected(old('adjustment_type') === 'expiry')>Expired Stock Disposal (-)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Quantity</label>
                        <input type="number" name="quantity" min="0" value="{{ old('quantity') }}" required
                               class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm font-semibold" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Detailed Reason &amp; Clinical Justification</label>
                        <input type="text" name="reason" value="{{ old('reason') }}" required placeholder="e.g. Annual physical count variance, cold chain excursion write-off, bottle breakage in Ward 2"
                               class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm" />
                    </div>

                    <div class="md:col-span-2 flex items-center justify-end">
                        <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">
                            Submit Adjustment for Authorization
                        </button>
                    </div>
                </form>
            </div>

            {{-- Adjustments Registry Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Stock Adjustment Registry</h3>
                        <p class="text-xs text-neutral-500">History of requested, authorized, and posted inventory balance corrections.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Adjustment #</th>
                                <th class="px-6 py-3 font-medium">Item &amp; Location</th>
                                <th class="px-6 py-3 font-medium text-right">Qty Delta</th>
                                <th class="px-6 py-3 font-medium text-right">Financial Impact</th>
                                <th class="px-6 py-3 font-medium">Requested By</th>
                                <th class="px-6 py-3 font-medium">Status &amp; Approvals</th>
                                <th class="px-6 py-3 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($adjustments as $adj)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4 font-mono font-bold text-neutral-900">
                                        {{ $adj->adjustment_number }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-neutral-900">{{ $adj->item->name ?? 'Item #' . $adj->inventory_item_id }}</p>
                                        <p class="text-xs text-neutral-500">{{ $adj->location->name ?? 'Main Storage' }} &bull; {{ ucfirst($adj->adjustment_type) }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono font-bold {{ $adj->quantity_adjusted < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        {{ $adj->quantity_adjusted > 0 ? '+' : '' }}{{ number_format($adj->quantity_adjusted) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono font-medium text-neutral-900">
                                        ₱{{ number_format(abs($adj->total_cost), 2) }}
                                        @if(abs($adj->total_cost) > 25000)
                                            <span class="block text-2xs font-bold text-rose-600 uppercase tracking-wide">&gt; ₱25k Dual Threshold</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <p class="font-medium text-neutral-800">{{ $adj->requestedBy->name ?? 'System' }}</p>
                                        <p class="text-neutral-500">{{ $adj->created_at ? $adj->created_at->format('M d, Y') : '' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($adj->status === 'pending_approval')
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                Tier 1 Review Pending
                                            </span>
                                        @elseif($adj->status === 'second_approval_required')
                                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800">
                                                Tier 2 Executive Sign-Off Required
                                            </span>
                                        @elseif($adj->status === 'posted')
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Posted to Ledger
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                                {{ ucfirst($adj->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if(in_array($adj->status, ['pending_approval', 'second_approval_required']) && auth()->user()->can(\App\Enums\Permission::ApproveAdjustment->value))
                                            @if($adj->status === 'second_approval_required' && auth()->id() === $adj->approved_by_id)
                                                <span class="text-xs text-neutral-400 italic">Signed (Tier 1)</span>
                                            @else
                                                <form action="{{ route('inventory.adjustments.approve', $adj) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                                        {{ $adj->status === 'second_approval_required' ? 'Tier 2 Authorize' : 'Authorize Adjustment' }}
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <span class="text-xs text-neutral-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-neutral-500">
                                        No inventory adjustments recorded.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($adjustments->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $adjustments->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
