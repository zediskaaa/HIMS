<x-app-layout>
    <div class="py-6" x-data="{ scheduleModal: {{ $errors->any() ? 'true' : 'false' }} }"
         @open-schedule-modal.window="scheduleModal = true"
         @keydown.escape.window="scheduleModal = false">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Header with Action Buttons --}}
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-neutral-200 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Physical Inventory</span>
                        <span class="text-xs text-neutral-500">• ABC Pareto Stratification &amp; Blind Count Protocol</span>
                    </div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Cycle Counts &amp; Physical Audits</h2>
                    <p class="text-sm text-neutral-600">
                        Systematic perpetual inventory counting, blind physical verification, statistical variance analysis, and multi-tier adjustment posting.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    @can(\App\Enums\Permission::PerformCycleCount->value)
                        <form action="{{ route('inventory.cycle-counts.abc') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50 transition">
                                <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Recalculate ABC Classes
                            </button>
                        </form>
                        <button type="button" @click="scheduleModal = true" id="btn-schedule-cycle-count" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Schedule Cycle Count
                        </button>
                    @endcan
                </div>
            </div>

            {{-- Flash Alerts --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Cycle Count Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Metric Cards --}}
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Audit Documents</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $cycleCounts->total() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Physical count sessions</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Active / In-Progress</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600">
                        {{ $cycleCounts->whereIn('status', ['scheduled', 'in_progress'])->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Awaiting blind counts</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Pending Authorization</p>
                    <p class="mt-2 text-2xl font-bold text-primary-600">
                        {{ $cycleCounts->where('status', 'completed')->count() }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Variances calculated, awaiting review</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Pareto Stratification</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">80 / 15 / 5%</p>
                    <p class="mt-1 text-xs text-neutral-500">Class A (Top 80% annual spend)</p>
                </div>
            </div>

            {{-- Cycle Counts Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Physical Audit Documents</h3>
                    <p class="text-xs text-neutral-500">Historical count registers with frozen inventory snapshots.</p>
                </div>
                <div class="overflow-x-auto hims-table-scroll">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Document #</th>
                                <th class="px-6 py-3 font-medium">Count Scope</th>
                                <th class="px-6 py-3 font-medium">Location</th>
                                <th class="px-6 py-3 font-medium">Assigned Counter</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Snapshot Date</th>
                                <th class="px-6 py-3 font-medium text-right hims-sticky-actions min-w-[170px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($cycleCounts as $doc)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('inventory.cycle-counts.show', $doc) }}" class="font-bold text-emerald-600 hover:underline">
                                            {{ $doc->document_number }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold uppercase text-neutral-700">
                                        @if($doc->count_type === 'ABC')
                                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800 font-bold">ABC Pareto</span>
                                        @else
                                            <span class="rounded bg-neutral-100 px-2 py-0.5 text-neutral-800">{{ $doc->count_type }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $doc->location->name ?? 'All Facility Locations' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <p class="font-medium text-neutral-800">{{ $doc->assignedCounter->name ?? 'Unassigned' }}</p>
                                        <p class="text-neutral-500">{{ $doc->assignedCounter->email ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(in_array($doc->status, ['scheduled', 'generated']))
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                Scheduled (Pending Count)
                                            </span>
                                        @elseif($doc->status === 'in_progress')
                                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">
                                                Counting in Progress
                                            </span>
                                        @elseif($doc->status === 'completed')
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                                                Counted (Pending Approval)
                                            </span>
                                        @elseif($doc->status === 'recount_pending')
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                Recount Flagged (Pending Review)
                                            </span>
                                        @elseif(in_array($doc->status, ['posted', 'approved']))
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Approved &amp; Reconciled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                                {{ ucfirst($doc->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-neutral-500">
                                        {{ $doc->snapshot_timestamp ? $doc->snapshot_timestamp->format('M d, Y h:i A') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-right hims-sticky-actions min-w-[170px]">
                                        <a href="{{ route('inventory.cycle-counts.show', $doc) }}" class="inline-block whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 transition">
                                            {{ in_array($doc->status, ['scheduled', 'in_progress', 'generated']) ? 'Enter Blind Counts' : 'Review Audit' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-neutral-500">
                                        <div class="max-w-md mx-auto space-y-3">
                                            <p class="text-neutral-700 font-medium">No cycle count documents found.</p>
                                            <p class="text-xs text-neutral-400">Schedule a blind physical inventory verification to audit shelf stock against book balance.</p>
                                            @can(\App\Enums\Permission::PerformCycleCount->value)
                                                <button type="button" @click="scheduleModal = true" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                    Schedule First Cycle Count
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($cycleCounts->hasPages())
                    <div class="border-t border-neutral-200 px-6 py-4">
                        {{ $cycleCounts->links() }}
                    </div>
                @endif
            </div>

        </div>

        {{-- SCHEDULE COUNT MODAL --}}
        <div x-show="scheduleModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="scheduleModal = false"></div>

                <div class="inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.cycle-counts.schedule') }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Schedule Physical Cycle Count</h3>
                                    <p class="text-xs text-neutral-500">Capture an immutable inventory snapshot for blind counting.</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Audit Scope / Sampling Model</label>
                                    <select name="count_type" required class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                        <option value="ABC">ABC Stratified (Focus on High-Value Class A &amp; B Items)</option>
                                        <option value="location">Specific Storage Location</option>
                                        <option value="random">Random 20% Sampling</option>
                                        <option value="all">Wall-to-Wall Physical Count (All Items)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Target Storage Location (Optional)</label>
                                    <select name="storage_location_id" class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                        <option value="">-- All Active Storage Locations --</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }}) &bull; {{ $loc->zone ?? 'Unrestricted' }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Assign Blind Counter (Authorized Personnel Only)</label>
                                    <select name="assigned_counter_id" class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                        <option value="">-- Assign Later / Unassigned --</option>
                                        @foreach($counters as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }}{{ $user->employee_id ? ' • ' . $user->employee_id : '' }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="rounded-lg bg-neutral-50 p-3 text-xs text-neutral-600 border border-neutral-200">
                                    <p class="font-semibold text-neutral-800">Blind Count Protocol Guarantee:</p>
                                    <p class="mt-1">The expected system quantities are concealed from counting personnel to ensure impartial, fraud-resistant physical audit counts.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="scheduleModal = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Freeze Snapshot &amp; Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
