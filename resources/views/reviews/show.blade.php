<x-app-layout>
    <div class="py-6" x-data="{
        activeTab: 'velocity',
        rejectModal: false,
        implementModal: false,
        implementUrl: '',
        implementTitle: '',
        rejectionReason: '',
        implementationNotes: ''
    }">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Breadcrumbs & Header --}}
            <div class="border-b border-neutral-200 pb-4">
                <nav class="flex text-xs text-neutral-500 mb-2" aria-label="Breadcrumb">
                    <a href="{{ route('reviews.index') }}" class="hover:text-indigo-600 transition">Process Reviews</a>
                    <span class="mx-2 text-neutral-400">/</span>
                    <span class="text-neutral-900 font-medium">{{ $review->review_number }}</span>
                </nav>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl font-bold tracking-tight text-neutral-900">{{ $review->review_number }}</h2>
                            @if($review->status === 'approved')
                                <span class="rounded-full bg-emerald-100 px-3 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                    Approved &amp; Signed Off
                                </span>
                            @elseif($review->status === 'submitted')
                                <span class="rounded-full bg-amber-100 px-3 py-0.5 text-xs font-semibold text-amber-800 border border-amber-200 animate-pulse">
                                    Pending BAC Review
                                </span>
                            @elseif($review->status === 'rejected')
                                <span class="rounded-full bg-rose-100 px-3 py-0.5 text-xs font-semibold text-rose-800 border border-rose-200">
                                    Returned / Rejected
                                </span>
                            @elseif($review->status === 'implemented')
                                <span class="rounded-full bg-blue-100 px-3 py-0.5 text-xs font-semibold text-blue-800 border border-blue-200">
                                    Recommendations Implemented
                                </span>
                            @else
                                <span class="rounded-full bg-neutral-100 px-3 py-0.5 text-xs font-semibold text-neutral-800 border border-neutral-200">
                                    Draft Evaluation
                                </span>
                            @endif
                        </div>
                        <h3 class="text-base font-semibold text-neutral-700 mt-1">{{ $review->title }}</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Period: <strong class="text-neutral-700">{{ $review->period_start->format('M d, Y') }}</strong> to <strong class="text-neutral-700">{{ $review->period_end->format('M d, Y') }}</strong>
                            • Evaluator: <span class="text-neutral-700 font-medium">{{ $review->evaluator->name }}</span>
                            @if($review->approver)
                                • Approved by: <span class="text-emerald-700 font-semibold">{{ $review->approver->name }}</span> ({{ $review->approved_at->format('M d, Y H:i') }})
                            @endif
                        </p>
                    </div>

                    {{-- Governance Action Bar --}}
                    <div class="flex items-center gap-2">
                        @if($review->isDraft())
                            @can(\App\Enums\Permission::CreateProcessReview->value)
                            <form action="{{ route('reviews.submit', $review) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    Submit for BAC Approval
                                </button>
                            </form>
                            @endcan
                        @elseif($review->isSubmitted())
                            @can(\App\Enums\Permission::ApproveProcessReview->value)
                            @if($review->canBeApprovedBy(auth()->user()))
                                <form action="{{ route('reviews.approve', $review) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Approve Review (BAC)
                                    </button>
                                </form>
                                <button type="button" @click="rejectModal = true" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-white px-3.5 py-2 text-xs font-semibold text-rose-700 shadow-sm hover:bg-rose-50 transition">
                                    Reject / Return
                                </button>
                            @elseif(auth()->id() === $review->evaluator_id)
                                <div class="rounded-md bg-amber-50 px-3 py-1.5 text-xs text-amber-800 border border-amber-200">
                                    Maker-Checker: Pending approval from BAC / Administrator.
                                </div>
                            @endif
                            @endcan
                        @endif
                    </div>
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

            {{-- Rejection Notice if applicable --}}
            @if($review->isRejected())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Review Returned by BAC / Administration:</span>
                    </div>
                    <p class="mt-1 text-xs text-rose-700">{{ $review->rejection_reason }}</p>
                </div>
            @endif

            {{-- Key Metrics KPI Summary Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Total Spend Evaluated</div>
                    <div class="mt-1.5 text-xl font-bold text-neutral-900">₱ {{ number_format($review->metrics_summary['total_spend'] ?? 0, 2) }}</div>
                    <div class="mt-0.5 text-[11px] text-neutral-500">{{ $review->procurementSavingsLogs->count() }} line items audited</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">DPRI Net Price Savings</div>
                    <div class="mt-1.5 text-xl font-bold {{ ($review->metrics_summary['net_savings_amount'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        ₱ {{ number_format($review->metrics_summary['net_savings_amount'] ?? 0, 2) }}
                    </div>
                    <div class="mt-0.5 text-[11px] text-neutral-500">
                        {{ $review->metrics_summary['aggregate_savings_pct'] ?? 0 }}% vs DOH ceiling
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Average Supplier Score</div>
                    <div class="mt-1.5 text-xl font-bold text-indigo-600">
                        {{ $review->metrics_summary['avg_supplier_score'] ? $review->metrics_summary['avg_supplier_score'].'%' : 'N/A' }}
                    </div>
                    <div class="mt-0.5 text-[11px] text-neutral-500">{{ $review->supplierScorecards->count() }} active suppliers</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Physical Stock Accuracy</div>
                    <div class="mt-1.5 text-xl font-bold text-neutral-900">
                        {{ $review->metrics_summary['overall_stock_accuracy'] ?? 100 }}%
                    </div>
                    <div class="mt-0.5 text-[11px] text-neutral-500">COA Circular 2020-006</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm col-span-2 lg:col-span-1">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Critical Bottleneck</div>
                    <div class="mt-1.5 text-sm font-bold text-amber-600 truncate">
                        {{ $review->metrics_summary['critical_bottleneck']['name'] ?? 'None Detected' }}
                    </div>
                    <div class="mt-0.5 text-[11px] text-neutral-500">
                        @if(isset($review->metrics_summary['critical_bottleneck']))
                            Mean: {{ $review->metrics_summary['critical_bottleneck']['mean_tat_days'] }}d (SLA: {{ $review->metrics_summary['critical_bottleneck']['sla_target_days'] }}d)
                        @else
                            Operations within SLA
                        @endif
                    </div>
                </div>
            </div>

            {{-- Navigation Tabs --}}
            <div class="border-b border-neutral-200">
                <nav class="-mb-px flex space-x-6 overflow-x-auto text-sm font-medium">
                    <button type="button" @click="activeTab = 'velocity'"
                            :class="activeTab === 'velocity' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 transition flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        Process Velocity &amp; Lead Time
                    </button>
                    <button type="button" @click="activeTab = 'scorecards'"
                            :class="activeTab === 'scorecards' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 transition flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Supplier Scorecards ({{ $review->supplierScorecards->count() }})
                    </button>
                    <button type="button" @click="activeTab = 'savings'"
                            :class="activeTab === 'savings' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 transition flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        DPRI Price Ceiling Savings ({{ $review->procurementSavingsLogs->count() }})
                    </button>
                    <button type="button" @click="activeTab = 'shrinkage'"
                            :class="activeTab === 'shrinkage' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 transition flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        COA Shrinkage &amp; Audits ({{ $review->inventoryShrinkageReports->count() }})
                    </button>
                    <button type="button" @click="activeTab = 'recommendations'"
                            :class="activeTab === 'recommendations' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 transition flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        Recommendations &amp; CAPAs ({{ $review->processRecommendations->count() }})
                    </button>
                    <button type="button" @click="activeTab = 'context'"
                            :class="activeTab === 'context' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 transition flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        Qualitative Narrative
                    </button>
                </nav>
            </div>

            {{-- TAB 1: PROCESS VELOCITY & BOTTLENECKS --}}
            <div x-show="activeTab === 'velocity'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">End-to-End Supply Chain Turnaround Time &amp; Lead Time Variance</h3>
                            <p class="text-xs text-neutral-500">Mathematical standard deviation ($\sigma$) and SLA compliance across 6 hospital logistics lifecycle phases.</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Workflow Lifecycle Stage</th>
                                    <th scope="col" class="px-4 py-3">Sample Count</th>
                                    <th scope="col" class="px-4 py-3">Mean TAT</th>
                                    <th scope="col" class="px-4 py-3">Min / Max</th>
                                    <th scope="col" class="px-4 py-3">Lead Time Variance ($\sigma$)</th>
                                    <th scope="col" class="px-4 py-3">SLA Threshold</th>
                                    <th scope="col" class="px-4 py-3">Breach Rate</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @foreach($review->metrics_summary['bottleneck_stages'] ?? [] as $key => $stage)
                                    <tr class="hover:bg-neutral-50/70 transition">
                                        <td class="px-4 py-3 font-semibold text-neutral-800">{{ $stage['name'] }}</td>
                                        <td class="px-4 py-3 text-neutral-600">{{ $stage['sample_count'] }} transactions</td>
                                        <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $stage['mean_tat_days'] }} days</td>
                                        <td class="px-4 py-3 font-mono text-xs text-neutral-500">{{ $stage['min_tat_days'] }}d / {{ $stage['max_tat_days'] }}d</td>
                                        <td class="px-4 py-3 font-mono">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $stage['variance_sigma'] > 3.0 ? 'bg-amber-100 text-amber-800' : 'bg-neutral-100 text-neutral-700' }}">
                                                &sigma; = {{ $stage['variance_sigma'] }}d
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $stage['sla_target_days'] }} days</td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs font-semibold {{ $stage['sla_breach_rate'] > 20 ? 'text-rose-600' : 'text-neutral-700' }}">
                                                {{ $stage['sla_breach_rate'] }}%
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($stage['status'] === 'critical')
                                                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Critical Bottleneck</span>
                                            @elseif($stage['status'] === 'elevated')
                                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Elevated Variance</span>
                                            @elseif($stage['status'] === 'healthy')
                                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Within SLA</span>
                                            @else
                                                <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs text-neutral-500">Insufficient Data</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 2: SUPPLIER SCORECARDS --}}
            <div x-show="activeTab === 'scorecards'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Multi-Criteria Supplier Performance Scorecards</h3>
                            <p class="text-xs text-neutral-500">Delivery Performance (40%), Quality &amp; Cold Chain Regulatory Conformance (40%), and Order Fill Completeness (20%).</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Supplier Name</th>
                                    <th scope="col" class="px-4 py-3">Delivery (40%)</th>
                                    <th scope="col" class="px-4 py-3">Quality (40%)</th>
                                    <th scope="col" class="px-4 py-3">Fill Rate (20%)</th>
                                    <th scope="col" class="px-4 py-3">Composite Score</th>
                                    <th scope="col" class="px-4 py-3">Lead Time (Real vs Promised)</th>
                                    <th scope="col" class="px-4 py-3">Regulatory (LTO/CPR)</th>
                                    <th scope="col" class="px-4 py-3">Recommendation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($review->supplierScorecards as $sc)
                                    <tr class="hover:bg-neutral-50/70 transition">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-neutral-900">{{ $sc->supplier->name }}</div>
                                            <div class="text-xs text-neutral-500">{{ $sc->total_pos_count }} POs ({{ $sc->late_deliveries_count }} late)</div>
                                        </td>
                                        <td class="px-4 py-3 font-mono font-medium text-neutral-800">{{ $sc->delivery_score }} / 40</td>
                                        <td class="px-4 py-3 font-mono font-medium text-neutral-800">{{ $sc->quality_score }} / 40</td>
                                        <td class="px-4 py-3 font-mono font-medium text-neutral-800">{{ $sc->fill_rate_score }} / 20</td>
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-base {{ $sc->total_score >= 85 ? 'text-emerald-600' : ($sc->total_score >= 70 ? 'text-amber-600' : 'text-rose-600') }}">
                                                {{ $sc->total_score }}%
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono">
                                            <span class="{{ $sc->avg_lead_time_days > $sc->promised_lead_time_days ? 'text-rose-600 font-semibold' : 'text-neutral-700' }}">
                                                {{ $sc->avg_lead_time_days }}d
                                            </span>
                                            <span class="text-neutral-400">/ promised {{ $sc->promised_lead_time_days }}d</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $sc->has_valid_lto ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                    LTO: {{ $sc->has_valid_lto ? 'Valid' : 'Expired' }}
                                                </span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $sc->has_valid_cpr ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                    CPR: {{ $sc->has_valid_cpr ? 'Valid' : 'Expired' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold border {{ $sc->recommendation_badge_class }}">
                                                {{ strtoupper(str_replace('_', ' ', $sc->recommendation)) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-neutral-500 text-xs">No supplier purchase orders within this review period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 3: DPRI PRICE SAVINGS --}}
            <div x-show="activeTab === 'savings'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Procurement Savings &amp; DOH DPRI Ceiling Price Compliance</h3>
                            <p class="text-xs text-neutral-500">Government reference pricing benchmarks established under Department of Health Administrative Order No. 2019-0040.</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="px-4 py-3">PO Number</th>
                                    <th scope="col" class="px-4 py-3">Drug / Medical Supply</th>
                                    <th scope="col" class="px-4 py-3">PNDF Code</th>
                                    <th scope="col" class="px-4 py-3">Quantity &amp; UOM</th>
                                    <th scope="col" class="px-4 py-3">Actual Unit Price</th>
                                    <th scope="col" class="px-4 py-3">DPRI Ceiling Price</th>
                                    <th scope="col" class="px-4 py-3">Net Variance</th>
                                    <th scope="col" class="px-4 py-3">Compliance Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($review->procurementSavingsLogs as $log)
                                    <tr class="hover:bg-neutral-50/70 transition">
                                        <td class="px-4 py-3 font-semibold text-neutral-900">{{ $log->purchaseOrder->po_number }}</td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-neutral-800">{{ $log->item_name }}</div>
                                            <div class="text-[11px] text-neutral-500 truncate max-w-xs">{{ $log->justification }}</div>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $log->pndf_code ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 font-mono text-neutral-800">{{ number_format($log->quantity_procured) }} {{ $log->uom }}</td>
                                        <td class="px-4 py-3 font-mono text-neutral-900">₱ {{ number_format($log->actual_unit_price, 2) }}</td>
                                        <td class="px-4 py-3 font-mono text-neutral-600">₱ {{ number_format($log->dpri_ceiling_price, 2) }}</td>
                                        <td class="px-4 py-3 font-mono font-semibold {{ $log->variance_amount >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $log->variance_amount >= 0 ? '+' : '' }}₱ {{ number_format($log->variance_amount, 2) }}
                                            <div class="text-[10px] font-normal {{ $log->savings_percentage >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                ({{ $log->savings_percentage }}%)
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($log->is_above_ceiling)
                                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800 border border-rose-200">
                                                    Ceiling Breach
                                                </span>
                                            @else
                                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                                    DPRI Compliant
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-neutral-500 text-xs">No PO lines with matched DPRI references during this review interval.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 4: COA SHRINKAGE & AUDITS --}}
            <div x-show="activeTab === 'shrinkage'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Physical Inventory Shrinkage &amp; Discrepancy Audits</h3>
                            <p class="text-xs text-neutral-500">Commission on Audit (COA) Circular No. 2020-006 &amp; Government Accounting Manual (GAM) Physical Count Discrepancy Reporting.</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Storage Location</th>
                                    <th scope="col" class="px-4 py-3">Inventory Item</th>
                                    <th scope="col" class="px-4 py-3">Ledger Book Qty</th>
                                    <th scope="col" class="px-4 py-3">Blind Count Qty</th>
                                    <th scope="col" class="px-4 py-3">Shrinkage Qty</th>
                                    <th scope="col" class="px-4 py-3">Shrinkage Rate (%)</th>
                                    <th scope="col" class="px-4 py-3">Loss Value (₱)</th>
                                    <th scope="col" class="px-4 py-3">COA Audit Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($review->inventoryShrinkageReports as $rep)
                                    <tr class="hover:bg-neutral-50/70 transition">
                                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $rep->storageLocation->name }}</td>
                                        <td class="px-4 py-3 font-semibold text-neutral-800">{{ $rep->inventoryItem->name }}</td>
                                        <td class="px-4 py-3 font-mono text-neutral-600">{{ number_format($rep->ledger_book_quantity) }}</td>
                                        <td class="px-4 py-3 font-mono text-neutral-900 font-medium">{{ number_format($rep->physical_counted_quantity) }}</td>
                                        <td class="px-4 py-3 font-mono font-semibold {{ $rep->shrinkage_quantity > 0 ? 'text-rose-600' : 'text-neutral-700' }}">
                                            {{ number_format($rep->shrinkage_quantity) }}
                                        </td>
                                        <td class="px-4 py-3 font-mono {{ $rep->shrinkage_rate_pct > 2.0 ? 'text-rose-600 font-bold' : 'text-neutral-700' }}">
                                            {{ $rep->shrinkage_rate_pct }}%
                                        </td>
                                        <td class="px-4 py-3 font-mono font-semibold text-rose-600">
                                            ₱ {{ number_format($rep->total_loss_value, 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($rep->requires_admin_escalation)
                                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800 border border-rose-200">
                                                    COA Rule 4 Escalation
                                                </span>
                                            @elseif($rep->shrinkage_quantity > 0)
                                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 border border-amber-200">
                                                    Minor Count Variance
                                                </span>
                                            @else
                                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                                    100% Reconciled
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-neutral-500 text-xs">No completed cycle count physical audits recorded in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 5: RECOMMENDATIONS & CAPAS --}}
            <div x-show="activeTab === 'recommendations'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Evidence-Backed Corrective &amp; Preventive Action (CAPA) Interventions</h3>
                            <p class="text-xs text-neutral-500">Actionable operational decisions algorithmically derived from verified transactional telemetry and audit findings.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        @forelse($review->processRecommendations as $rec)
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-5 shadow-sm transition hover:border-indigo-200">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-neutral-200/80 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold border {{ $rec->priority_badge_class }}">
                                            {{ strtoupper($rec->priority) }} PRIORITY
                                        </span>
                                        <span class="rounded bg-neutral-200/70 px-2 py-0.5 text-xs font-medium text-neutral-700">
                                            {{ ucwords(str_replace('_', ' ', $rec->category)) }}
                                        </span>
                                        <span class="text-xs text-neutral-500">• Target: <strong class="text-neutral-900">{{ $rec->target_name }}</strong></span>
                                    </div>
                                    <div>
                                        @if($rec->isImplemented())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                Implemented by {{ $rec->implementedBy?->name ?? 'User' }} ({{ $rec->implemented_at?->format('M d, Y') }})
                                            </span>
                                        @else
                                            @can(\App\Enums\Permission::ImplementProcessReview->value)
                                                <button type="button"
                                                        @click="implementModal = true; implementUrl = '{{ route('reviews.recommendations.implement', $rec) }}'; implementTitle = '{{ addslashes($rec->target_name) }}'"
                                                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                    Execute Intervention
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <div class="font-semibold text-neutral-800">Identified Problem / Evidence:</div>
                                        <p class="mt-1 text-neutral-600">{{ $rec->problem_detected }}</p>

                                        @if(!empty($rec->evidence_metrics))
                                            <div class="mt-2 rounded bg-neutral-100 p-2 font-mono text-[11px] text-neutral-700">
                                                {{ json_encode($rec->evidence_metrics) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <div class="font-semibold text-neutral-800">Recommended Action &amp; Benefit:</div>
                                        <p class="mt-1 font-medium text-indigo-900">{{ $rec->recommended_action }}</p>
                                        <p class="mt-1 text-neutral-500 italic">Expected Benefit: {{ $rec->expected_operational_benefit }}</p>

                                        @if($rec->implementation_notes)
                                            <div class="mt-2 rounded bg-emerald-50 border border-emerald-200 p-2 text-emerald-800">
                                                <strong>Execution Notes:</strong> {{ $rec->implementation_notes }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-neutral-500">
                                Zero process exceptions detected. Operations are currently performing within expected SLAs.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- TAB 6: QUALITATIVE CONTEXT & NARRATIVE --}}
            <div x-show="activeTab === 'context'" class="space-y-6">
                @can(\App\Enums\Permission::CreateProcessReview->value)
                <form action="{{ route('reviews.update', $review) }}" method="POST" class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="title" class="block text-sm font-semibold text-neutral-800">Review Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $review->title) }}"
                               @disabled(!$review->isDraft())
                               class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-neutral-100 disabled:text-neutral-500">
                    </div>

                    <div>
                        <label for="qualitative_context" class="block text-sm font-semibold text-neutral-800">Qualitative &amp; Environmental Factors</label>
                        <textarea name="qualitative_context" id="qualitative_context" rows="4"
                                  @disabled(!$review->isDraft())
                                  class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-neutral-100 disabled:text-neutral-500">{{ old('qualitative_context', $review->qualitative_context) }}</textarea>
                    </div>

                    <div>
                        <label for="executive_summary" class="block text-sm font-semibold text-neutral-800">Executive Summary Narrative</label>
                        <textarea name="executive_summary" id="executive_summary" rows="4"
                                  @disabled(!$review->isDraft())
                                  class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-neutral-100 disabled:text-neutral-500">{{ old('executive_summary', $review->executive_summary) }}</textarea>
                    </div>

                    @if($review->isDraft())
                        <div class="flex justify-end pt-3 border-t border-neutral-200">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-neutral-800 transition">
                                Save Narrative Changes
                            </button>
                        </div>
                    @endif
                </form>
                @else
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm space-y-5">
                        <div><p class="text-sm font-semibold text-neutral-800">Review Title</p><p class="mt-1 text-sm text-neutral-700">{{ $review->title }}</p></div>
                        <div><p class="text-sm font-semibold text-neutral-800">Qualitative &amp; Environmental Factors</p><p class="mt-1 whitespace-pre-line text-sm text-neutral-700">{{ $review->qualitative_context ?: 'No qualitative context recorded.' }}</p></div>
                        <div><p class="text-sm font-semibold text-neutral-800">Executive Summary Narrative</p><p class="mt-1 whitespace-pre-line text-sm text-neutral-700">{{ $review->executive_summary ?: 'No executive summary recorded.' }}</p></div>
                    </div>
                @endcan
            </div>

            {{-- Rejection Modal --}}
            @can(\App\Enums\Permission::ApproveProcessReview->value)
            <div x-show="rejectModal" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-neutral-900">Return Review to Draft (Rejection)</h3>
                    <p class="text-xs text-neutral-600">Provide an authoritative justification for returning this review to the evaluator.</p>
                    <form action="{{ route('reviews.reject', $review) }}" method="POST" class="space-y-4">
                        @csrf
                        <textarea name="rejection_reason" rows="3" required placeholder="Specify what operational parameters or findings require recalculation..."
                                  class="w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="rejectModal = false" class="px-4 py-2 text-xs font-medium text-neutral-600 hover:text-neutral-900">Cancel</button>
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-700">Confirm Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
            @endcan

            {{-- Implementation Execution Modal --}}
            @can(\App\Enums\Permission::ImplementProcessReview->value)
            <div x-show="implementModal" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-neutral-900">Execute Intervention: <span x-text="implementTitle"></span></h3>
                    <p class="text-xs text-neutral-600">Confirm execution of this corrective intervention. Operational parameters such as standard lead times will be synchronized.</p>
                    <form :action="implementUrl" method="POST" class="space-y-4">
                        @csrf
                        <textarea name="implementation_notes" rows="3" placeholder="Document implementation memo number, supplier communication, or change details..."
                                  class="w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="implementModal = false" class="px-4 py-2 text-xs font-medium text-neutral-600 hover:text-neutral-900">Cancel</button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">Mark as Implemented</button>
                        </div>
                    </form>
                </div>
            </div>
            @endcan

        </div>
    </div>
</x-app-layout>
