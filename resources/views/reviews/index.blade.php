<x-app-layout>
    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Header with Action Buttons --}}
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-neutral-200 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">Operational Analytics</span>
                        <span class="text-xs text-neutral-500">• Evidence-Based Supply Chain Governance</span>
                    </div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Evidence-Based Process Reviews</h2>
                    <p class="text-sm text-neutral-600">
                        Multi-criteria supplier scorecards, DOH DPRI price ceiling analytics, lead time variance ($\sigma$), and COA physical inventory shrinkage reviews.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('reviews.dpri') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50 transition">
                        <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        DOH DPRI Reference Prices
                    </a>
                    @can(\App\Enums\Permission::CreateProcessReview->value)
                        <a href="{{ route('reviews.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Process Review
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Flash Notification --}}
            @if(session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('status') }}</span>
                    </div>
                </div>
            @endif

            {{-- Metric Highlights --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">Total Reviews</span>
                        <span class="rounded-full bg-neutral-100 p-2 text-neutral-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-neutral-900">{{ number_format($stats['total_reviews']) }}</div>
                    <div class="mt-1 text-xs text-neutral-500">Historical review audits</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">Under BAC Review</span>
                        <span class="rounded-full bg-amber-100 p-2 text-amber-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($stats['pending_approval']) }}</div>
                    <div class="mt-1 text-xs text-neutral-500">Awaiting Maker-Checker sign-off</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">Approved &amp; Audited</span>
                        <span class="rounded-full bg-emerald-100 p-2 text-emerald-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($stats['approved_count']) }}</div>
                    <div class="mt-1 text-xs text-neutral-500">Officially verified reviews</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">Active Interventions</span>
                        <span class="rounded-full bg-indigo-100 p-2 text-indigo-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-indigo-600">{{ number_format($stats['active_recommendations']) }}</div>
                    <div class="mt-1 text-xs text-neutral-500">Pending corrective actions</div>
                </div>
            </div>

            {{-- Filters & Search --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('reviews.index') }}" class="flex flex-wrap items-center gap-3">
                    <div class="relative w-full sm:w-64">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by # or title..." class="w-full rounded-lg border-neutral-300 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>

                    <select name="status" onchange="this.form.submit()" class="rounded-lg border-neutral-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Review Statuses</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="submitted" @selected(request('status') === 'submitted')>Submitted (Pending BAC)</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                        <option value="implemented" @selected(request('status') === 'implemented')>Implemented</option>
                    </select>

                    @if(request()->hasAny(['search', 'status']))
                        <a href="{{ route('reviews.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700 underline">Clear filters</a>
                    @endif
                </form>
            </div>

            {{-- Reviews Table --}}
            <div class="max-w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-3.5">Review Identifier</th>
                            <th scope="col" class="px-6 py-3.5">Evaluation Period</th>
                            <th scope="col" class="px-6 py-3.5">Evaluator &amp; Sign-off</th>
                            <th scope="col" class="px-6 py-3.5">Synthesized Metrics</th>
                            <th scope="col" class="px-6 py-3.5">Governance Status</th>
                            <th scope="col" class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 text-sm">
                        @forelse($reviews as $review)
                            <tr class="hover:bg-neutral-50/70 transition">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-neutral-900">{{ $review->review_number }}</div>
                                    <div class="text-xs text-neutral-500 max-w-xs truncate">{{ $review->title }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-neutral-800">{{ $review->period_start->format('M d, Y') }} — {{ $review->period_end->format('M d, Y') }}</div>
                                    <div class="text-xs text-neutral-500">{{ $review->period_start->diffInDays($review->period_end) }} days interval</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-neutral-800 font-medium">{{ $review->evaluator->name }}</div>
                                    @if($review->approver)
                                        <div class="text-xs text-emerald-700 font-medium flex items-center gap-1 mt-0.5">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            Approved by {{ $review->approver->name }}
                                        </div>
                                    @elseif($review->isSubmitted())
                                        <div class="text-xs text-amber-600 font-medium">Under BAC Review</div>
                                    @else
                                        <div class="text-xs text-neutral-400">Not yet approved</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3 text-xs">
                                        <span title="Vendors Evaluated" class="inline-flex items-center gap-1 rounded bg-neutral-100 px-2 py-0.5 text-neutral-700">
                                            <svg class="h-3 w-3 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                            {{ $review->supplier_scorecards_count }} Vendors
                                        </span>
                                        <span title="DPRI Price Savings Logs" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-emerald-800 border border-emerald-200">
                                            ₱ {{ number_format($review->metrics_summary['net_savings_amount'] ?? 0, 0) }}
                                        </span>
                                        <span title="Actionable Interventions" class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-indigo-700 border border-indigo-200">
                                            {{ $review->process_recommendations_count }} Actions
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($review->status === 'approved')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span> Approved
                                        </span>
                                    @elseif($review->status === 'submitted')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600 animate-pulse"></span> Submitted
                                        </span>
                                    @elseif($review->status === 'rejected')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-600"></span> Rejected
                                        </span>
                                    @elseif($review->status === 'implemented')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span> Implemented
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span> Draft
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('reviews.show', $review) }}" class="inline-flex items-center gap-1 font-semibold text-indigo-600 hover:text-indigo-900 transition">
                                        View Review &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-neutral-500">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    </div>
                                    <div class="mt-3 font-medium text-neutral-900">No Process Reviews Found</div>
                                    <p class="mt-1 text-xs text-neutral-500 max-w-sm mx-auto">
                                        Synthesize transactional datasets across procurement, receiving, and storage into evidence-based audit reviews.
                                    </p>
                                    @can(\App\Enums\Permission::CreateProcessReview->value)
                                        <div class="mt-4">
                                            <a href="{{ route('reviews.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                                                Initiate First Process Review
                                            </a>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $reviews->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
