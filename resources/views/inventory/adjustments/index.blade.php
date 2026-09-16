<x-app-layout>
    <div class="space-y-4">
        <x-ui.page-header
            title="Stock Adjustments"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory' => route('inventory.items'), 'Stock Adjustments' => null]"
        />

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs sm:text-sm text-emerald-800 flex items-center justify-between shadow-2xs dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span class="font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs sm:text-sm text-sky-800 flex items-center justify-between shadow-2xs dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-300">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ session('info') }}</span>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs sm:text-sm text-rose-800 shadow-2xs dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300">
                <div class="flex items-center gap-2 font-semibold">
                    <svg class="h-4 w-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
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
        <div class="grid gap-3.5 grid-cols-2 sm:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total Adjustments</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-neutral-900 dark:text-neutral-100 tabular-nums">{{ $adjustments->total() }}</p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Documented adjustments</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Pending Review</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">
                    {{ $adjustments->where('status', 'pending_approval')->count() }}
                </p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Tier 1 supervisor review</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Dual-Tier Required</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                    {{ $adjustments->where('status', 'pending_second_approval')->count() }}
                </p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Over ₱25,000 threshold</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Posted &amp; Reconciled</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                    {{ $adjustments->where('status', 'posted')->count() }}
                </p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Written to ledger</p>
            </div>
        </div>

        @can('adjust_stock')
        {{-- REQUEST STOCK ADJUSTMENT MODAL (CENTERED VIEWPORT DIALOG) --}}
        <x-ui.modal name="request-stock-adjustment" maxWidth="2xl">
            <x-slot:header>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Request Stock Adjustment</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Adjustments create formal requests and update the immutable stock movement ledger once authorized.
                    </p>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="space-y-4"
                  data-confirm-title="Confirm stock adjustment"
                  data-confirm-message="Are you sure you want to apply this stock adjustment?"
                  data-confirm-label="Apply Adjustment">
                @csrf
                <div class="grid gap-3.5 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Inventory Item <span class="text-rose-500">*</span>
                        </label>
                        <select name="item_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-medium">
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                    {{ $item->name }} ({{ $item->sku ?? 'No SKU' }}) &bull; {{ number_format((int) $item->quantity_on_hand) }} on hand &bull; ₱{{ number_format($item->unit_cost ?? 0, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Storage Location <span class="text-rose-500">*</span>
                        </label>
                        <select name="location_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-medium">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                                    {{ $location->name }}{{ $location->code ? ' ('.$location->code.')' : '' }} &bull; {{ $location->zone ?? 'Unrestricted' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Adjustment Type <span class="text-rose-500">*</span>
                        </label>
                        <select name="adjustment_type" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-medium">
                            <option value="increase" @selected(old('adjustment_type') === 'increase')>Increase (+)</option>
                            <option value="decrease" @selected(old('adjustment_type') === 'decrease')>Decrease (-)</option>
                            <option value="correction" @selected(old('adjustment_type') === 'correction')>Count Reconciliation / Set Exact</option>
                            <option value="damage" @selected(old('adjustment_type') === 'damage')>Damage Write-off (-)</option>
                            <option value="loss" @selected(old('adjustment_type') === 'loss')>Loss / Theft (-)</option>
                            <option value="expiry" @selected(old('adjustment_type') === 'expiry')>Expired Stock Disposal (-)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Quantity <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="quantity" min="0" value="{{ old('quantity') }}" required
                               class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-semibold py-2 px-3" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Detailed Reason &amp; Clinical Justification <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="reason" value="{{ old('reason') }}" required placeholder="e.g. Annual physical count variance, cold chain excursion write-off, bottle breakage in Ward 2"
                               class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs py-2 px-3" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'request-stock-adjustment')">
                        Cancel
                    </x-ui.button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 hover:bg-amber-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        Submit Adjustment for Authorization
                    </button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any())
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'request-stock-adjustment'))"></div>
        @endif
        @endcan

        {{-- Adjustments Registry Table --}}
        <x-ui.card :padding="false">
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Stock Adjustment Registry</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">History of requested, authorized, and posted inventory balance corrections.</p>
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-neutral-600 dark:text-neutral-300 divide-y divide-neutral-200 dark:divide-neutral-800">
                    <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Adjustment #</th>
                            <th class="px-5 py-3 font-semibold">Item &amp; Location</th>
                            <th class="px-5 py-3 font-semibold text-right">Qty Delta</th>
                            <th class="px-5 py-3 font-semibold text-right">Financial Impact</th>
                            <th class="px-5 py-3 font-semibold">Requested By</th>
                            <th class="px-5 py-3 font-semibold">Status &amp; Approvals</th>
                            <th class="px-5 py-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                        @forelse($adjustments as $adj)
                            <tr class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50 transition-colors">
                                <td class="px-5 py-3.5 font-mono font-bold text-neutral-900 dark:text-neutral-100">
                                    {{ $adj->adjustment_number }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $adj->item->name ?? 'Item #' . $adj->inventory_item_id }}</p>
                                    <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ $adj->location->name ?? 'Main Storage' }} &bull; {{ ucfirst($adj->adjustment_type) }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono font-bold {{ $adj->quantity_adjusted < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $adj->quantity_adjusted > 0 ? '+' : '' }}{{ number_format($adj->quantity_adjusted) }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono font-medium text-neutral-900 dark:text-neutral-100">
                                    ₱{{ number_format(abs($adj->total_cost), 2) }}
                                    @if(abs($adj->total_cost) > 25000)
                                        <span class="block text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wide">&gt; ₱25k Dual Threshold</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-xs">
                                    <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ $adj->requestedBy->name ?? 'System' }}</p>
                                    <p class="text-[11px] text-neutral-400">{{ $adj->created_at ? $adj->created_at->format('M d, Y') : '' }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($adj->status === 'pending_approval')
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Tier 1 Review Pending
                                        </span>
                                    @elseif($adj->status === 'pending_second_approval')
                                        <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800 dark:bg-purple-950/60 dark:text-purple-300">
                                            Tier 2 Executive Sign-Off Required
                                        </span>
                                    @elseif($adj->status === 'posted')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Posted to Ledger
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                            {{ ucfirst($adj->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if(in_array($adj->status, ['pending_approval', 'pending_second_approval']) && auth()->user()->can(\App\Enums\Permission::ApproveAdjustment->value))
                                        @if($adj->status === 'pending_second_approval' && auth()->id() === $adj->approved_by_id)
                                            <span class="text-xs text-neutral-400 italic">Signed (Tier 1)</span>
                                        @else
                                            <form action="{{ route('inventory.adjustments.approve', $adj) }}" method="POST" class="inline"
                                                  data-confirm-title="Approve stock adjustment"
                                                  data-confirm-message="Are you sure you want to authorize this stock adjustment? On-hand inventory will be adjusted immediately."
                                                  data-confirm-label="Authorize Adjustment">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition">
                                                    {{ $adj->status === 'pending_second_approval' ? 'Tier 2 Authorize' : 'Authorize Adjustment' }}
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
                                <td colspan="7" class="px-6 py-8 text-center text-xs text-neutral-500 dark:text-neutral-400">
                                    No inventory adjustments recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($adjustments->hasPages())
                <div class="border-t border-neutral-200 dark:border-neutral-800 px-5 py-3">
                    {{ $adjustments->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
