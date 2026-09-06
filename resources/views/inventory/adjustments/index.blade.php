<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Stock Adjustments
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('info'))
                <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">
                    {{ session('info') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">This adjustment was not applied</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Apply Adjustment</h3>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Adjustments are recorded as stock movements, so the balance and the audit trail stay in step.
                </p>
                <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="mt-4 grid gap-4 md:grid-cols-2"
                      data-confirm-title="Confirm stock adjustment"
                      data-confirm-message="Are you sure you want to apply this stock adjustment?"
                      data-confirm-label="Apply Adjustment">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Item</label>
                        <select name="item_id" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                    {{ $item->name }} ({{ $item->sku ?? 'no SKU' }}) — {{ number_format((int) $item->quantity_on_hand) }} on hand
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Storage Location</label>
                        <select name="location_id" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                                    {{ $location->name }}{{ $location->code ? ' ('.$location->code.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Adjustment Type</label>
                        <select name="adjustment_type" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="increase" @selected(old('adjustment_type') === 'increase')>Increase</option>
                            <option value="decrease" @selected(old('adjustment_type') === 'decrease')>Decrease</option>
                            <option value="correction" @selected(old('adjustment_type') === 'correction')>Correction / Set Value</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Quantity</label>
                        <input type="number" name="quantity" min="0" value="{{ old('quantity') }}" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[var(--muted)]">Reason</label>
                        <input type="text" name="reason" value="{{ old('reason') }}" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Apply Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
