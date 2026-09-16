<x-app-layout>
    <div class="space-y-4">
        {{-- Page Header --}}
        <x-ui.page-header
            title="Cycle Counts & Physical Audits"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory' => route('inventory.items'), 'Cycle Counts' => null]"
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

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs sm:text-sm text-rose-800 shadow-2xs dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300">
                <div class="flex items-center gap-2 font-semibold">
                    <svg class="h-4 w-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
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
        <div class="grid gap-3.5 grid-cols-2 sm:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Audit Documents</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-neutral-900 dark:text-neutral-100 tabular-nums">{{ $cycleCounts->total() }}</p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Physical count sessions</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Active / In-Progress</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">
                    {{ $cycleCounts->whereIn('status', ['scheduled', 'in_progress'])->count() }}
                </p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Awaiting blind counts</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Pending Authorization</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-primary-600 dark:text-primary-400 tabular-nums">
                    {{ $cycleCounts->where('status', 'completed')->count() }}
                </p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Variances awaiting review</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Pareto Stratification</p>
                <p class="mt-1.5 text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">80 / 15 / 5%</p>
                <p class="mt-0.5 text-[11px] text-neutral-400">Class A (Top 80% annual spend)</p>
            </div>
        </div>

        {{-- Cycle Counts Table Card --}}
        <x-ui.card :padding="false">
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Physical Audit Documents</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Historical count registers with frozen inventory snapshots.</p>
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-neutral-600 dark:text-neutral-300 divide-y divide-neutral-200 dark:divide-neutral-800">
                    <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Document #</th>
                            <th class="px-5 py-3 font-semibold">Count Scope</th>
                            <th class="px-5 py-3 font-semibold">Location</th>
                            <th class="px-5 py-3 font-semibold">Assigned Counter</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Snapshot Date</th>
                            <th class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                        @forelse($cycleCounts as $doc)
                            <tr class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('inventory.cycle-counts.show', $doc) }}" class="font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                        {{ $doc->document_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5 text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                                    @if($doc->count_type === 'ABC')
                                        <span class="rounded bg-emerald-100 dark:bg-emerald-950/60 px-2 py-0.5 text-emerald-800 dark:text-emerald-300 font-bold text-[11px]">ABC Pareto</span>
                                    @else
                                        <span class="rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-neutral-800 dark:text-neutral-200 text-[11px]">{{ $doc->count_type }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $doc->location->name ?? 'All Facility Locations' }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-xs">
                                    <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ $doc->assignedCounter->name ?? 'Unassigned' }}</p>
                                    <p class="text-[11px] text-neutral-400">{{ $doc->assignedCounter->email ?? '' }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if(in_array($doc->status, ['scheduled', 'generated']))
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Scheduled (Pending Count)
                                        </span>
                                    @elseif($doc->status === 'in_progress')
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300">
                                            Counting in Progress
                                        </span>
                                    @elseif($doc->status === 'completed')
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                            Counted (Pending Approval)
                                        </span>
                                    @elseif($doc->status === 'recount_pending')
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Recount Flagged (Pending Review)
                                        </span>
                                    @elseif(in_array($doc->status, ['posted', 'approved']))
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Approved &amp; Reconciled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                            {{ ucfirst($doc->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $doc->snapshot_timestamp ? $doc->snapshot_timestamp->format('M d, Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('inventory.cycle-counts.show', $doc) }}" class="inline-block whitespace-nowrap rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-1.5 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 shadow-2xs transition">
                                        {{ in_array($doc->status, ['scheduled', 'in_progress', 'generated']) ? 'Enter Blind Counts' : 'Review Audit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-xs text-neutral-500 dark:text-neutral-400">
                                    <div class="max-w-md mx-auto space-y-1.5">
                                        <p class="text-neutral-800 dark:text-neutral-200 font-semibold">No cycle count documents found.</p>
                                        <p class="text-[11px] text-neutral-400">Schedule a blind physical inventory verification using the button in the workflow banner above to audit shelf stock against book balance.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($cycleCounts->hasPages())
                <div class="border-t border-neutral-200 dark:border-neutral-800 px-5 py-3">
                    {{ $cycleCounts->links() }}
                </div>
            @endif
        </x-ui.card>

        @can(\App\Enums\Permission::PerformCycleCount->value)
        {{-- SCHEDULE COUNT MODAL (CENTERED VIEWPORT DIALOG) --}}
        <x-ui.modal name="schedule-cycle-count" maxWidth="lg">
            <x-slot:header>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Schedule Physical Cycle Count</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Capture an immutable inventory snapshot for blind counting.</p>
                </div>
            </x-slot:header>

            <form action="{{ route('inventory.cycle-counts.schedule') }}" method="POST" class="space-y-4"
                  data-confirm-title="Schedule cycle count session"
                  data-confirm-message="Are you sure you want to generate a new cycle count session and generate count sheets?"
                  data-confirm-label="Schedule Count">
                @csrf
                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Audit Scope / Sampling Model <span class="text-rose-500">*</span>
                        </label>
                        <select name="count_type" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-xs font-medium">
                            <option value="ABC">ABC Stratified (Focus on High-Value Class A &amp; B Items)</option>
                            <option value="location">Specific Storage Location</option>
                            <option value="random">Random 20% Sampling</option>
                            <option value="all">Wall-to-Wall Physical Count (All Items)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Target Storage Location <span class="text-neutral-400 font-normal">(Optional)</span>
                        </label>
                        <select name="storage_location_id" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-xs font-medium">
                            <option value="">-- All Active Storage Locations --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }}) &bull; {{ $loc->zone ?? 'Unrestricted' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Assign Blind Counter <span class="text-neutral-400 font-normal">(Authorized Personnel Only)</span>
                        </label>
                        <select name="assigned_counter_id" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-xs font-medium">
                            <option value="">-- Assign Later / Unassigned --</option>
                            @foreach($counters as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }}{{ $user->employee_id ? ' • ' . $user->employee_id : '' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rounded-lg bg-neutral-50 dark:bg-neutral-800/60 p-3 text-xs text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700">
                        <p class="font-semibold text-neutral-800 dark:text-neutral-200">Blind Count Protocol Guarantee:</p>
                        <p class="mt-1">The expected system quantities are concealed from counting personnel to ensure impartial, fraud-resistant physical audit counts.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'schedule-cycle-count')">
                        Cancel
                    </x-ui.button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        Freeze Snapshot &amp; Schedule
                    </button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any())
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'schedule-cycle-count'))"></div>
        @endif
        @endcan
    </div>
</x-app-layout>
