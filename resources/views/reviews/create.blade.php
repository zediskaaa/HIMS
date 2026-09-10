<x-app-layout>
    <div class="py-6" x-data="reviewCreator()">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">

            {{-- Breadcrumbs & Header --}}
            <div class="border-b border-neutral-200 pb-4">
                <nav class="flex text-xs text-neutral-500 mb-2" aria-label="Breadcrumb">
                    <a href="{{ route('reviews.index') }}" class="hover:text-indigo-600 transition">Process Reviews</a>
                    <span class="mx-2 text-neutral-400">/</span>
                    <span class="text-neutral-900 font-medium">New Evidence-Based Review</span>
                </nav>
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-neutral-900">Initiate Operational Process Review</h2>
                        <p class="text-sm text-neutral-600">
                            Synthesizes real transaction logs, purchase orders, IAR quality assay findings, and COA physical audits within the selected date window.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Error Alerts --}}
            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Validation Exception:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Review Generation Form --}}
            <form action="{{ route('reviews.store') }}" method="POST" class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm space-y-6">
                @csrf

                {{-- Review Title --}}
                <div>
                    <label for="title" class="block text-sm font-semibold text-neutral-800">Review Title / Designation <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" id="title" required
                           value="{{ old('title', 'Quarterly Supply Chain Performance & DPRI Compliance Audit') }}"
                           class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-neutral-500">A clear descriptor designating the operational scope and focus of this evaluation.</p>
                </div>

                {{-- Date Window Selection --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="period_start" class="block text-sm font-semibold text-neutral-800">Evaluation Period Start <span class="text-rose-500">*</span></label>
                        <input type="date" name="period_start" id="period_start" required
                               x-model="periodStart" @change="checkAvailability()"
                               class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="period_end" class="block text-sm font-semibold text-neutral-800">Evaluation Period End <span class="text-rose-500">*</span></label>
                        <input type="date" name="period_end" id="period_end" required
                               x-model="periodEnd" @change="checkAvailability()"
                               class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Live Operational Data Availability Checker --}}
                <div class="rounded-lg p-4 transition border"
                     :class="availability.has_sufficient_data ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900'">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5">
                            <template x-if="loading">
                                <svg class="h-5 w-5 animate-spin text-neutral-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </template>
                            <template x-if="!loading && availability.has_sufficient_data">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </template>
                            <template x-if="!loading && !availability.has_sufficient_data">
                                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            </template>
                        </div>
                        <div>
                            <div class="text-sm font-semibold" x-text="loading ? 'Validating operational database records...' : (availability.has_sufficient_data ? 'Operational Data Verified' : 'Insufficient Operational Data')"></div>
                            <div class="mt-1 text-xs text-neutral-600" x-text="availability.message"></div>
                            <div class="mt-2 flex items-center gap-4 text-xs font-medium" x-show="!loading && availability.has_sufficient_data">
                                <span class="rounded bg-white/70 px-2 py-0.5 border border-neutral-300">POs: <strong x-text="availability.pos_count"></strong></span>
                                <span class="rounded bg-white/70 px-2 py-0.5 border border-neutral-300">IARs: <strong x-text="availability.iars_count"></strong></span>
                                <span class="rounded bg-white/70 px-2 py-0.5 border border-neutral-300">Physical Audits: <strong x-text="availability.cycle_counts_count"></strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Qualitative Environmental Context --}}
                <div>
                    <label for="qualitative_context" class="block text-sm font-semibold text-neutral-800">Operational &amp; Environmental Context</label>
                    <textarea name="qualitative_context" id="qualitative_context" rows="3"
                              placeholder="e.g. Typhoon disruption in Central Luzon affected regional transport lead times; Surge in respiratory ward admissions increased IV fluid consumption."
                              class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('qualitative_context') }}</textarea>
                    <p class="mt-1 text-xs text-neutral-500">Document extraordinary external conditions that influenced hospital logistics or supplier fulfillment during this period.</p>
                </div>

                {{-- Executive Summary Narrative --}}
                <div>
                    <label for="executive_summary" class="block text-sm font-semibold text-neutral-800">Initial Executive Notes</label>
                    <textarea name="executive_summary" id="executive_summary" rows="3"
                              placeholder="Initial commentary on inventory trends, compliance bottlenecks, or supplier reliability observations..."
                              class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('executive_summary') }}</textarea>
                </div>

                {{-- Action Bar --}}
                <div class="flex items-center justify-between pt-4 border-t border-neutral-200">
                    <a href="{{ route('reviews.index') }}" class="text-sm font-medium text-neutral-600 hover:text-neutral-900 transition">
                        Cancel
                    </a>
                    <button type="submit"
                            :disabled="loading || !availability.has_sufficient_data"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Synthesize Evidence-Based Review
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function reviewCreator() {
            return {
                periodStart: '{{ old('period_start', $defaultStart) }}',
                periodEnd: '{{ old('period_end', $defaultEnd) }}',
                loading: false,
                availability: {
                    has_sufficient_data: true,
                    pos_count: 0,
                    iars_count: 0,
                    cycle_counts_count: 0,
                    message: 'Checking operational database...',
                },
                init() {
                    this.checkAvailability();
                },
                checkAvailability() {
                    if (!this.periodStart || !this.periodEnd) return;
                    this.loading = true;
                    fetch('{{ route('reviews.check-availability') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            period_start: this.periodStart,
                            period_end: this.periodEnd,
                        }),
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.availability = data;
                        this.loading = false;
                    })
                    .catch(() => {
                        this.loading = false;
                    });
                }
            };
        }
    </script>
</x-app-layout>
