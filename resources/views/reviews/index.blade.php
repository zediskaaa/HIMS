<x-app-layout>
    @include('inventory.logistics.partials.nav')

    {{-- Header with Action Buttons --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-200 dark:border-neutral-800 pb-5">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="rounded-md bg-indigo-100 dark:bg-indigo-950/80 px-2.5 py-0.5 text-xs font-semibold text-indigo-800 dark:text-indigo-300">Operational Analytics</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">• Evidence-Based Supply Chain Governance</span>
            </div>
            <h2 class="mt-1 text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">Evidence-Based Process Reviews</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
            <a href="{{ route('reviews.dpri') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-200 shadow-2xs hover:bg-neutral-50 dark:hover:bg-neutral-750 transition w-full sm:w-auto">
                <svg class="h-4 w-4 text-neutral-500 dark:text-neutral-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>DOH DPRI Reference Prices</span>
            </a>
            @can(\App\Enums\Permission::CreateProcessReview->value)
                <a href="{{ route('reviews.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-2xs hover:bg-indigo-700 dark:hover:bg-indigo-500 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 w-full sm:w-auto">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>New Process Review</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Metric Highlights (Standard 3-Zone KPI Pattern) --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 sm:gap-4">
        {{-- Card 1: Total Reviews --}}
        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900/95 p-4 sm:p-5 shadow-2xs flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total Reviews</span>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 ring-1 ring-neutral-200 dark:ring-neutral-700/60">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </span>
            </div>
            <div class="mt-3 text-2xl sm:text-3xl font-black tracking-tight tabular-nums text-neutral-900 dark:text-white">
                {{ number_format($stats['total_reviews']) }}
            </div>
            <div class="mt-3 flex items-center border-t border-neutral-100 dark:border-neutral-800/80 pt-2.5">
                <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 truncate">Historical review audits</span>
            </div>
        </div>

        {{-- Card 2: Under BAC Review --}}
        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900/95 p-4 sm:p-5 shadow-2xs flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Under BAC Review</span>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/70 text-amber-700 dark:text-amber-400 ring-1 ring-amber-200 dark:ring-amber-800/60">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <div class="mt-3 text-2xl sm:text-3xl font-black tracking-tight tabular-nums text-amber-600 dark:text-amber-400">
                {{ number_format($stats['pending_approval']) }}
            </div>
            <div class="mt-3 flex items-center border-t border-neutral-100 dark:border-neutral-800/80 pt-2.5">
                <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 truncate">Awaiting Maker-Checker sign-off</span>
            </div>
        </div>

        {{-- Card 3: Approved & Audited --}}
        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900/95 p-4 sm:p-5 shadow-2xs flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Approved &amp; Audited</span>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-800/60">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <div class="mt-3 text-2xl sm:text-3xl font-black tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400">
                {{ number_format($stats['approved_count']) }}
            </div>
            <div class="mt-3 flex items-center border-t border-neutral-100 dark:border-neutral-800/80 pt-2.5">
                <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 truncate">Officially verified reviews</span>
            </div>
        </div>

        {{-- Card 4: Active Interventions --}}
        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900/95 p-4 sm:p-5 shadow-2xs flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">Active Interventions</span>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-200 dark:ring-indigo-800/60">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                </span>
            </div>
            <div class="mt-3 text-2xl sm:text-3xl font-black tracking-tight tabular-nums text-indigo-600 dark:text-indigo-400">
                {{ number_format($stats['active_recommendations']) }}
            </div>
            <div class="mt-3 flex items-center border-t border-neutral-100 dark:border-neutral-800/80 pt-2.5">
                <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 truncate">Pending corrective actions</span>
            </div>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('reviews.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full">
            <div class="relative w-full sm:w-72 lg:w-80">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search by # or title..."
                    class="w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 pl-9 pr-4 py-2 text-xs sm:text-sm text-neutral-800 dark:text-neutral-200 placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs"
                >
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-neutral-400 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <select
                name="status"
                onchange="this.form.submit()"
                class="w-full sm:w-auto rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 py-2 pl-3 pr-10 text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs"
            >
                <option value="">All Review Statuses</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                <option value="submitted" @selected(request('status') === 'submitted')>Submitted (Pending BAC)</option>
                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                <option value="implemented" @selected(request('status') === 'implemented')>Implemented</option>
            </select>

            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('reviews.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 underline whitespace-nowrap self-start sm:self-center">
                    Clear filters
                </a>
            @endif
        </form>
    </div>

    {{-- DESKTOP / TABLET REVIEWS TABLE (>= md) --}}
    <div class="hidden md:block overflow-hidden rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-2xs">
        <div class="overflow-x-auto min-w-full">
            <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-xs">
                <thead class="bg-neutral-50/90 dark:bg-neutral-800/80 text-[11px] font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-400">
                    <tr>
                        <th scope="col" class="px-4 lg:px-5 py-3.5">Review Identifier</th>
                        <th scope="col" class="px-4 lg:px-5 py-3.5">Evaluation Period</th>
                        <th scope="col" class="px-4 lg:px-5 py-3.5">Evaluator &amp; Sign-off</th>
                        <th scope="col" class="px-4 lg:px-5 py-3.5">Synthesized Metrics</th>
                        <th scope="col" class="px-4 lg:px-5 py-3.5">Governance Status</th>
                        <th scope="col" class="px-4 lg:px-5 py-3.5 pr-5 text-right min-w-[7rem]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 text-sm bg-white dark:bg-neutral-900">
                    @forelse($reviews as $review)
                        <tr class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50 transition-colors">
                            {{-- Review Identifier --}}
                            <td class="px-4 lg:px-5 py-4 min-w-[13rem] max-w-[18rem]">
                                <a href="{{ route('reviews.show', $review) }}" class="font-bold text-neutral-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                    {{ $review->review_number }}
                                </a>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400 truncate mt-0.5" title="{{ $review->title }}">
                                    {{ $review->title }}
                                </div>
                            </td>

                            {{-- Evaluation Period --}}
                            <td class="px-4 lg:px-5 py-4 whitespace-nowrap min-w-[11rem]">
                                <div class="font-medium text-neutral-800 dark:text-neutral-200">
                                    {{ $review->period_start->format('M d, Y') }} — {{ $review->period_end->format('M d, Y') }}
                                </div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                    {{ $review->period_start->diffInDays($review->period_end) }} days interval
                                </div>
                            </td>

                            {{-- Evaluator & Sign-off --}}
                            <td class="px-4 lg:px-5 py-4 whitespace-nowrap min-w-[10.5rem]">
                                <div class="text-neutral-800 dark:text-neutral-200 font-medium">
                                    {{ $review->evaluator->name }}
                                </div>
                                @if($review->approver)
                                    <div class="text-xs text-emerald-700 dark:text-emerald-400 font-medium flex items-center gap-1 mt-0.5">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span>Approved by {{ $review->approver->name }}</span>
                                    </div>
                                @elseif($review->isSubmitted())
                                    <div class="text-xs text-amber-600 dark:text-amber-400 font-medium mt-0.5">
                                        Under BAC Review
                                    </div>
                                @else
                                    <div class="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">
                                        Not yet approved
                                    </div>
                                @endif
                            </td>

                            {{-- Synthesized Metrics --}}
                            <td class="px-4 lg:px-5 py-4 min-w-[13rem]">
                                @php
                                    $savings = $review->net_savings_amount;
                                @endphp
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <span title="Vendors Evaluated" class="inline-flex items-center gap-1 rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-neutral-700 dark:text-neutral-300">
                                        <svg class="h-3 w-3 text-neutral-500 dark:text-neutral-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                        {{ $review->supplier_scorecards_count }} Vendors
                                    </span>
                                    @if($savings > 0)
                                        <span title="DPRI Price Savings: ₱ {{ number_format($savings, 2) }}" class="inline-flex items-center gap-1 rounded bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-medium">
                                            ₱ {{ number_format($savings, 0) }}
                                        </span>
                                    @elseif($savings < 0)
                                        <span title="DPRI Price Exceedance: ₱ {{ number_format(abs($savings), 2) }}" class="inline-flex items-center gap-1 rounded bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800 font-medium">
                                            -₱ {{ number_format(abs($savings), 0) }}
                                        </span>
                                    @else
                                        <span title="No DPRI price savings recorded" class="inline-flex items-center gap-1 rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700">
                                            ₱ 0
                                        </span>
                                    @endif
                                    <span title="Actionable Interventions" class="inline-flex items-center gap-1 rounded bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                        {{ $review->process_recommendations_count }} Actions
                                    </span>
                                </div>
                            </td>

                            {{-- Governance Status --}}
                            <td class="px-4 lg:px-5 py-4 whitespace-nowrap min-w-[7.5rem]">
                                @if($review->status === 'approved')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-950/70 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400"></span> Approved
                                    </span>
                                @elseif($review->status === 'submitted')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 dark:bg-amber-950/70 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-600 dark:bg-amber-400 animate-pulse"></span> Submitted
                                    </span>
                                @elseif($review->status === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 dark:bg-rose-950/70 px-2.5 py-1 text-xs font-semibold text-rose-800 dark:text-rose-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-600 dark:bg-rose-400"></span> Rejected
                                    </span>
                                @elseif($review->status === 'implemented')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 dark:bg-blue-950/70 px-2.5 py-1 text-xs font-semibold text-blue-800 dark:text-blue-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-600 dark:bg-blue-400"></span> Implemented
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 dark:bg-neutral-800 px-2.5 py-1 text-xs font-semibold text-neutral-800 dark:text-neutral-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span> Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 lg:px-5 py-4 pr-5 whitespace-nowrap text-right text-sm min-w-[7rem]">
                                <a href="{{ route('reviews.show', $review) }}" class="inline-flex items-center gap-1.5 font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition group">
                                    <span>View Review</span>
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-neutral-500 dark:text-neutral-400">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                </div>
                                <div class="mt-3 font-semibold text-neutral-900 dark:text-white">No Process Reviews Found</div>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">
                                    Synthesize transactional datasets across procurement, receiving, and storage into evidence-based audit reviews.
                                </p>
                                @can(\App\Enums\Permission::CreateProcessReview->value)
                                    <div class="mt-4">
                                        <a href="{{ route('reviews.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-indigo-700 dark:hover:bg-indigo-500 transition">
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
    </div>

    {{-- MOBILE REVIEWS CARD LIST (< md) --}}
    <div class="divide-y divide-neutral-200 dark:divide-neutral-800 md:hidden rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-2xs overflow-hidden">
        @forelse($reviews as $review)
            @php
                $savings = $review->net_savings_amount;
            @endphp
            <div class="p-4 sm:p-5 space-y-3.5 hover:bg-neutral-50/50 dark:hover:bg-neutral-800/40 transition">
                {{-- Top Header Row: Review Number + Status Badge --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('reviews.show', $review) }}" class="font-bold text-neutral-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition text-base leading-snug">
                            {{ $review->review_number }}
                        </a>
                        <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400 leading-normal line-clamp-2" title="{{ $review->title }}">
                            {{ $review->title }}
                        </p>
                    </div>
                    <div class="shrink-0">
                        @if($review->status === 'approved')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-950/70 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400"></span> Approved
                            </span>
                        @elseif($review->status === 'submitted')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 dark:bg-amber-950/70 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-600 dark:bg-amber-400 animate-pulse"></span> Submitted
                            </span>
                        @elseif($review->status === 'rejected')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 dark:bg-rose-950/70 px-2.5 py-1 text-xs font-semibold text-rose-800 dark:text-rose-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-600 dark:bg-rose-400"></span> Rejected
                            </span>
                        @elseif($review->status === 'implemented')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 dark:bg-blue-950/70 px-2.5 py-1 text-xs font-semibold text-blue-800 dark:text-blue-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-600 dark:bg-blue-400"></span> Implemented
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 dark:bg-neutral-800 px-2.5 py-1 text-xs font-semibold text-neutral-800 dark:text-neutral-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span> Draft
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Detail Grid: Period & Evaluator --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs rounded-lg bg-neutral-50 dark:bg-neutral-800/60 p-3 border border-neutral-100 dark:border-neutral-800">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Evaluation Period</span>
                        <span class="mt-0.5 block font-medium text-neutral-800 dark:text-neutral-200">
                            {{ $review->period_start->format('M d, Y') }} — {{ $review->period_end->format('M d, Y') }}
                        </span>
                        <span class="text-[11px] text-neutral-500 dark:text-neutral-400">
                            {{ $review->period_start->diffInDays($review->period_end) }} days interval
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Evaluator &amp; Sign-off</span>
                        <span class="mt-0.5 block font-medium text-neutral-800 dark:text-neutral-200">
                            {{ $review->evaluator->name }}
                        </span>
                        @if($review->approver)
                            <span class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium flex items-center gap-1 mt-0.5">
                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span>Approved by {{ $review->approver->name }}</span>
                            </span>
                        @elseif($review->isSubmitted())
                            <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium block mt-0.5">
                                Under BAC Review
                            </span>
                        @else
                            <span class="text-[11px] text-neutral-400 dark:text-neutral-500 block mt-0.5">
                                Not yet approved
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Synthesized Metrics Pills Row --}}
                <div class="space-y-1">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Synthesized Metrics</span>
                    <div class="flex flex-wrap items-center gap-1.5 text-xs pt-0.5">
                        <span title="Vendors Evaluated" class="inline-flex items-center gap-1 rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-neutral-700 dark:text-neutral-300">
                            <svg class="h-3 w-3 text-neutral-500 dark:text-neutral-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            {{ $review->supplier_scorecards_count }} Vendors
                        </span>
                        @if($savings > 0)
                            <span title="DPRI Price Savings: ₱ {{ number_format($savings, 2) }}" class="inline-flex items-center gap-1 rounded bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-medium">
                                ₱ {{ number_format($savings, 0) }}
                            </span>
                        @elseif($savings < 0)
                            <span title="DPRI Price Exceedance: ₱ {{ number_format(abs($savings), 2) }}" class="inline-flex items-center gap-1 rounded bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800 font-medium">
                                -₱ {{ number_format(abs($savings), 0) }}
                            </span>
                        @else
                            <span title="No DPRI price savings recorded" class="inline-flex items-center gap-1 rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700">
                                ₱ 0
                            </span>
                        @endif
                        <span title="Actionable Interventions" class="inline-flex items-center gap-1 rounded bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            {{ $review->process_recommendations_count }} Actions
                        </span>
                    </div>
                </div>

                {{-- Action Tap Target --}}
                <div class="pt-1">
                    <a href="{{ route('reviews.show', $review) }}" class="flex items-center justify-between w-full rounded-lg bg-neutral-100 hover:bg-indigo-50 dark:bg-neutral-800 dark:hover:bg-indigo-950/60 px-3.5 py-2.5 text-xs font-semibold text-neutral-700 hover:text-indigo-600 dark:text-neutral-200 dark:hover:text-indigo-300 border border-neutral-200/80 dark:border-neutral-700 transition">
                        <span>Open Process Review</span>
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-neutral-500 dark:text-neutral-400">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <div class="mt-3 font-semibold text-neutral-900 dark:text-white">No Process Reviews Found</div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 max-w-xs mx-auto">
                    Synthesize transactional datasets across procurement, receiving, and storage into evidence-based audit reviews.
                </p>
                @can(\App\Enums\Permission::CreateProcessReview->value)
                    <div class="mt-4">
                        <a href="{{ route('reviews.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700">
                            Initiate First Process Review
                        </a>
                    </div>
                @endcan
            </div>
        @endforelse
    </div>

    @if ($reviews->hasPages())
        <div class="mt-4 border-t border-neutral-200 dark:border-neutral-800 pt-3">
            {{ $reviews->links() }}
        </div>
    @endif
</x-app-layout>
