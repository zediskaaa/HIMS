<x-app-layout>
    <div class="py-6" x-data="{ newModal: false }">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Breadcrumbs & Header --}}
            <div class="border-b border-neutral-200 pb-4">
                <nav class="flex text-xs text-neutral-500 mb-2" aria-label="Breadcrumb">
                    <a href="{{ route('reviews.index') }}" class="hover:text-indigo-600 transition">Process Reviews</a>
                    <span class="mx-2 text-neutral-400">/</span>
                    <span class="text-neutral-900 font-medium">DPRI Reference Catalog</span>
                </nav>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">DOH AO No. 2019-0040</span>
                            <span class="text-xs text-neutral-500">• Philippine National Drug Formulary (PNDF)</span>
                        </div>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Drug Price Reference Index (DPRI)</h2>
                        <p class="text-sm text-neutral-600">
                            Statutory government price ceilings established to ensure transparent, economical, and standardized public hospital procurement.
                        </p>
                    </div>

                    @can(\App\Enums\Permission::CreateProcessReview->value)
                        <div>
                            <button type="button" @click="newModal = true" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Register DPRI Reference Price
                            </button>
                        </div>
                    @endcan
                </div>
            </div>

            {{-- Flash Alerts --}}
            @if(session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('status') }}</span>
                    </div>
                </div>
            @endif

            {{-- Search & Year Filters --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('reviews.dpri') }}" class="flex flex-wrap items-center gap-3">
                    <div class="relative w-full sm:w-72">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by drug name or PNDF code..." class="w-full rounded-lg border-neutral-300 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>

                    <select name="edition_year" onchange="this.form.submit()" class="rounded-lg border-neutral-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All DPRI Editions</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}" @selected(request('edition_year') == $year)>Edition {{ $year }}</option>
                        @endforeach
                    </select>

                    @if(request()->hasAny(['search', 'edition_year']))
                        <a href="{{ route('reviews.dpri') }}" class="text-xs text-neutral-500 hover:text-neutral-700 underline">Clear filters</a>
                    @endif
                </form>
            </div>

            {{-- DPRI Catalog Table --}}
            <div class="max-w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-3.5">PNDF Code</th>
                            <th scope="col" class="px-6 py-3.5">Generic Drug Description</th>
                            <th scope="col" class="px-6 py-3.5">Dosage / Strength</th>
                            <th scope="col" class="px-6 py-3.5">Unit of Measure</th>
                            <th scope="col" class="px-6 py-3.5">Ceiling Price (PHP)</th>
                            <th scope="col" class="px-6 py-3.5">Edition Year</th>
                            <th scope="col" class="px-6 py-3.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($referencePrices as $price)
                            <tr class="hover:bg-neutral-50/70 transition">
                                <td class="px-6 py-4 font-mono text-xs font-semibold text-neutral-900">{{ $price->pndf_code }}</td>
                                <td class="px-6 py-4 font-medium text-neutral-900">{{ $price->drug_name }}</td>
                                <td class="px-6 py-4 text-neutral-600 text-xs">{{ $price->dosage_form_strength ?? 'Standard Formulation' }}</td>
                                <td class="px-6 py-4 text-neutral-700 font-mono text-xs">{{ $price->unit_of_measure }}</td>
                                <td class="px-6 py-4 font-mono font-bold text-emerald-700">₱ {{ number_format($price->ceiling_price, 2) }}</td>
                                <td class="px-6 py-4 text-neutral-600 text-xs font-medium">{{ $price->edition_year }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                        Active Benchmark
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-neutral-500 text-xs">
                                    No DPRI reference prices registered for this search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $referencePrices->links() }}
            </div>

            {{-- New Reference Price Modal --}}
            <div x-show="newModal" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-neutral-900">Register DPRI Reference Benchmark</h3>
                    <p class="text-xs text-neutral-600">Register or update official government ceiling price under DOH AO 2019-0040.</p>
                    <form action="{{ route('reviews.dpri.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-neutral-700">PNDF Code <span class="text-rose-500">*</span></label>
                            <input type="text" name="pndf_code" required placeholder="e.g. PNDF-AMOX-500" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-neutral-700">Generic Drug Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="drug_name" required placeholder="e.g. Amoxicillin" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold text-neutral-700">Dosage Form &amp; Strength</label>
                                <input type="text" name="dosage_form_strength" placeholder="e.g. 500mg capsule" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-neutral-700">Unit of Measure <span class="text-rose-500">*</span></label>
                                <input type="text" name="unit_of_measure" required placeholder="e.g. capsule" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold text-neutral-700">DPRI Ceiling Price (₱) <span class="text-rose-500">*</span></label>
                                <input type="number" step="0.0001" min="0" name="ceiling_price" required placeholder="e.g. 4.50" class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-neutral-700">Edition Year <span class="text-rose-500">*</span></label>
                                <input type="number" name="edition_year" value="2026" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-neutral-700">Regulatory Notes</label>
                            <textarea name="notes" rows="2" placeholder="e.g. Sourced from 2026 DOH DPRI 11th Edition." class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="newModal = false" class="px-4 py-2 text-xs font-medium text-neutral-600 hover:text-neutral-900">Cancel</button>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Save Benchmark</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
