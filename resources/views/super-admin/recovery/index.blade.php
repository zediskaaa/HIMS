<x-app-layout>
    <x-ui.page-header
        title="System Recovery Center"
        subtitle="Multi-Layer Error Recovery, Safe Transaction Rollbacks, Idempotent Retries & System Health Diagnostics."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Recovery Center' => null]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2 flex-wrap">
                <a
                    href="{{ route('admin.recovery.health') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                >
                    <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Full Health Diagnostics</span>
                </a>

                <form method="POST" action="{{ route('admin.recovery.rebuild-cache') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                        onclick="return confirm('Rebuild system application cache? Stale cache will be flushed and warmed.');"
                    >
                        <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Rebuild Cache</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.recovery.retry-all-jobs') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                        onclick="return confirm('Retry all recorded failed background queue jobs?');"
                    >
                        <svg class="h-4 w-4 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span>Retry All Failed Jobs</span>
                    </button>
                </form>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- 1. Metric Stat Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Total Incidents</span>
                <span class="rounded-lg bg-neutral-100 p-2 text-neutral-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold text-neutral-900">{{ number_format($metrics['total']) }}</div>
            <p class="mt-1 text-xs text-neutral-500">Recorded system failures &amp; exceptions</p>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-800">Pending Incidents</span>
                <span class="rounded-lg bg-amber-100 p-2 text-amber-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold text-amber-900">{{ number_format($metrics['pending']) }}</div>
            <p class="mt-1 text-xs text-amber-700">Awaiting review, retry or sign-off</p>
        </div>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Recovered &amp; Resolved</span>
                <span class="rounded-lg bg-emerald-100 p-2 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold text-emerald-900">{{ number_format($metrics['resolved']) }}</div>
            <p class="mt-1 text-xs text-emerald-700">Successfully retried or resolved</p>
        </div>

        <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-rose-800">Failed Queue Jobs</span>
                <span class="rounded-lg bg-rose-100 p-2 text-rose-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold text-rose-900">{{ number_format($metrics['failed_jobs']) }}</div>
            <p class="mt-1 text-xs text-rose-700">Asynchronous background job failures</p>
        </div>
    </div>

    {{-- 2. Real-Time Health Snapshot Bar --}}
    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3 mb-4">
            <div class="flex items-center gap-2">
                <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 class="text-sm font-bold text-neutral-900">System Health Telemetry</h3>
            </div>
            <span class="text-xs text-neutral-400">Checked just now</span>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
            {{-- Database --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5 flex items-start gap-3">
                <span class="mt-0.5 flex h-3 w-3 shrink-0 rounded-full {{ $quickHealth['database']['status'] === 'healthy' ? 'bg-emerald-500' : ($quickHealth['database']['status'] === 'degraded' ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                <div class="min-w-0">
                    <div class="font-bold text-neutral-800">Database Engine</div>
                    <div class="text-neutral-500 mt-0.5 truncate">{{ $quickHealth['database']['message'] }}</div>
                </div>
            </div>

            {{-- Queue Worker --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5 flex items-start gap-3">
                <span class="mt-0.5 flex h-3 w-3 shrink-0 rounded-full {{ $quickHealth['queue']['status'] === 'healthy' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                <div class="min-w-0">
                    <div class="font-bold text-neutral-800">Queue Pipeline</div>
                    <div class="text-neutral-500 mt-0.5 truncate">{{ $quickHealth['queue']['message'] }}</div>
                </div>
            </div>

            {{-- Storage Subsystem --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5 flex items-start gap-3">
                <span class="mt-0.5 flex h-3 w-3 shrink-0 rounded-full {{ $quickHealth['storage']['status'] === 'healthy' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <div class="min-w-0">
                    <div class="font-bold text-neutral-800">Storage / Disks</div>
                    <div class="text-neutral-500 mt-0.5 truncate">{{ $quickHealth['storage']['message'] }}</div>
                </div>
            </div>

            {{-- Cache Store --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5 flex items-start gap-3">
                <span class="mt-0.5 flex h-3 w-3 shrink-0 rounded-full {{ $quickHealth['cache']['status'] === 'healthy' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <div class="min-w-0">
                    <div class="font-bold text-neutral-800">Application Cache</div>
                    <div class="text-neutral-500 mt-0.5 truncate">{{ $quickHealth['cache']['message'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Filter Toolbar --}}
    <x-ui.card title="Filter Incidents" subtitle="Filter by recovery status, subsystem module, keyword, or occurrence date.">
        <form method="GET" action="{{ route('admin.recovery.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 items-end">
            <div>
                <label for="filter-search" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500">Search</label>
                <input
                    id="filter-search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="REC-ID, operation, or error summary..."
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                />
            </div>

            <div>
                <label for="filter-module" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500">Module</label>
                <select
                    id="filter-module"
                    name="module"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Modules</option>
                    @foreach ($availableModules as $mod)
                        <option value="{{ $mod }}" @selected(($filters['module'] ?? '') === $mod)>{{ ucfirst($mod) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500">Recovery Status</label>
                <select
                    id="filter-status"
                    name="status"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending Review</option>
                    <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>In Progress</option>
                    <option value="retried" @selected(($filters['status'] ?? '') === 'retried')>Retried / Recovered</option>
                    <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Manually Resolved</option>
                    <option value="ignored" @selected(($filters['status'] ?? '') === 'ignored')>Ignored / Dismissed</option>
                    <option value="failed_permanently" @selected(($filters['status'] ?? '') === 'failed_permanently')>Failed Permanently</option>
                </select>
            </div>

            <div>
                <label for="filter-date-from" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500">Date From</label>
                <input
                    id="filter-date-from"
                    name="date_from"
                    type="date"
                    value="{{ $filters['date_from'] ?? '' }}"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                />
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 inline-flex items-center justify-center rounded-lg bg-primary-700 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-600 transition"
                >
                    Apply Filters
                </button>
                <a
                    href="{{ route('admin.recovery.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition"
                >
                    Reset
                </a>
            </div>
        </form>
    </x-ui.card>

    {{-- 4. Main Incidents Table with Alpine modal state --}}
    <div x-data="{
        selectedRecord: null,
        showDetailsModal: false,
        copiedId: false,
        openDetails(rec) {
            this.selectedRecord = rec;
            this.showDetailsModal = true;
            this.copiedId = false;
        },
        copyErrorId() {
            if (this.selectedRecord) {
                navigator.clipboard.writeText(this.selectedRecord.error_id);
                this.copiedId = true;
                setTimeout(() => this.copiedId = false, 2000);
            }
        }
    }">
        <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                    <thead class="bg-neutral-50 font-semibold text-neutral-700">
                        <tr>
                            <th class="px-4 py-3">Incident ID</th>
                            <th class="px-4 py-3">Module / Op</th>
                            <th class="px-4 py-3">Error Summary</th>
                            <th class="px-4 py-3">Triggered By</th>
                            <th class="px-4 py-3">Recovery Strategy</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Occurred</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white">
                        @forelse ($records as $record)
                            <tr class="hover:bg-neutral-50/60 transition">
                                <td class="px-4 py-3 font-mono font-bold text-neutral-900 whitespace-nowrap">
                                    <span class="rounded bg-neutral-100 px-2 py-0.5 text-[11px]">{{ $record->error_id }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-neutral-900 capitalize">{{ $record->module }}</div>
                                    <div class="text-[11px] text-neutral-500 font-mono">{{ $record->operation }}</div>
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $record->error_summary }}">
                                    <div class="font-medium text-neutral-900 truncate">{{ $record->error_summary }}</div>
                                    @if ($record->exception_class)
                                        <div class="text-[10px] text-neutral-400 font-mono truncate">{{ class_basename($record->exception_class) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-600">
                                    {{ $record->user_snapshot ?? 'System' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-neutral-100 text-neutral-700">
                                        {{ str_replace('_', ' ', $record->strategy_applied ?? 'rollback') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($record->status === 'pending')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 border border-amber-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Pending
                                        </span>
                                    @elseif (in_array($record->status, ['retried', 'resolved'], true))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ ucfirst($record->status) }}
                                        </span>
                                    @elseif ($record->status === 'in_progress')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700 border border-sky-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-500 animate-spin"></span>
                                            Retrying...
                                        </span>
                                    @elseif ($record->status === 'ignored')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-[11px] font-semibold text-neutral-600">
                                            Ignored
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 border border-rose-200">
                                            Failed Permanently
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-500">
                                    {{ $record->created_at->setTimezone(config('app.timezone'))->format('M d, Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button
                                            type="button"
                                            @click="openDetails({{ Js::from($record) }})"
                                            class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-neutral-700 hover:bg-neutral-50 transition shadow-sm"
                                        >
                                            Diagnostics
                                        </button>

                                        @if ($record->canRetry())
                                            <form method="POST" action="{{ route('admin.recovery.retry', $record) }}" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-500 transition shadow-sm"
                                                    onclick="return confirm('Initiate smart retry for incident #{{ $record->error_id }}?');"
                                                >
                                                    Smart Retry
                                                </button>
                                            </form>
                                        @endif

                                        @if ($record->isPending())
                                            <form method="POST" action="{{ route('admin.recovery.resolve', $record) }}" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1 text-[11px] font-medium text-neutral-600 hover:bg-neutral-100 transition"
                                                    onclick="return confirm('Mark incident #{{ $record->error_id }} as resolved?');"
                                                >
                                                    Resolve
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-neutral-500">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="mt-3 text-sm font-semibold text-neutral-900">Zero System Incidents</div>
                                    <p class="mt-1 text-xs text-neutral-500 max-w-sm mx-auto">
                                        All subsystems, transaction pipelines, and background jobs are operating cleanly without recorded exceptions.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="border-t border-neutral-200 px-4 py-3">
                    {{ $records->links() }}
                </div>
            @endif
        </div>

        {{-- Interactive Diagnostics Modal --}}
        <div
            x-show="showDetailsModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            @keydown.escape.window="showDetailsModal = false"
        >
            <div
                x-show="showDetailsModal"
                x-transition.opacity
                @click="showDetailsModal = false"
                class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity"
            ></div>

            <div class="flex min-h-full items-center justify-center p-3 sm:p-6 text-center">
                <div
                    x-show="showDetailsModal"
                    x-transition
                    @click.stop
                    class="relative flex flex-col w-full max-w-4xl max-h-[90vh] text-left bg-white rounded-2xl shadow-2xl border border-neutral-200 overflow-hidden transform transition-all"
                >
                    <div class="flex items-start justify-between border-b border-neutral-100 px-6 py-4 bg-white">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600 shadow-sm">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-neutral-900">Technical Diagnostics</h3>
                                    <span class="rounded bg-neutral-100 px-2 py-0.5 font-mono text-xs font-semibold text-neutral-700" x-text="selectedRecord ? selectedRecord.error_id : ''"></span>
                                    <button
                                        type="button"
                                        @click="copyErrorId()"
                                        class="text-xs text-primary-600 hover:text-primary-800 font-medium"
                                        x-text="copiedId ? 'Copied!' : 'Copy ID'"
                                    ></button>
                                </div>
                                <p class="text-xs text-neutral-500 mt-0.5">
                                    Module: <span class="font-semibold text-neutral-700 capitalize" x-text="selectedRecord ? selectedRecord.module : ''"></span> &middot;
                                    Operation: <span class="font-mono text-neutral-700" x-text="selectedRecord ? selectedRecord.operation : ''"></span>
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="showDetailsModal = false"
                            class="rounded-xl p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 transition"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-4 text-xs">
                        <div>
                            <label class="font-semibold uppercase tracking-wider text-neutral-500 text-[10px]">Error Summary</label>
                            <div class="mt-1 rounded-xl border border-rose-200 bg-rose-50/50 p-3 text-rose-950 font-medium leading-relaxed" x-text="selectedRecord ? selectedRecord.error_summary : ''"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                                <span class="font-semibold uppercase tracking-wider text-neutral-500 text-[10px]">Exception Class</span>
                                <div class="font-mono text-neutral-900 mt-0.5 break-all font-semibold" x-text="selectedRecord ? selectedRecord.exception_class : 'None'"></div>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                                <span class="font-semibold uppercase tracking-wider text-neutral-500 text-[10px]">Source Location</span>
                                <div class="font-mono text-neutral-900 mt-0.5 break-all" x-text="selectedRecord && selectedRecord.technical_details ? (selectedRecord.technical_details.file + ':' + selectedRecord.technical_details.line) : 'N/A'"></div>
                            </div>
                        </div>

                        <div x-show="selectedRecord && selectedRecord.technical_details && selectedRecord.technical_details.trace">
                            <label class="font-semibold uppercase tracking-wider text-neutral-500 text-[10px]">Sanitized Stack Trace</label>
                            <div class="mt-1 rounded-xl bg-neutral-900 p-4 font-mono text-[11px] text-neutral-200 overflow-x-auto max-h-56 space-y-1">
                                <template x-for="(line, idx) in (selectedRecord && selectedRecord.technical_details ? selectedRecord.technical_details.trace : [])" :key="idx">
                                    <div class="whitespace-pre" x-text="line"></div>
                                </template>
                            </div>
                        </div>

                        <div x-show="selectedRecord && selectedRecord.resolution_notes">
                            <label class="font-semibold uppercase tracking-wider text-neutral-500 text-[10px]">Resolution History</label>
                            <div class="mt-1 rounded-xl border border-emerald-200 bg-emerald-50/40 p-3 text-emerald-900" x-text="selectedRecord ? selectedRecord.resolution_notes : ''"></div>
                        </div>
                    </div>

                    <div class="border-t border-neutral-100 bg-neutral-50 px-6 py-3.5 flex justify-end">
                        <button
                            type="button"
                            @click="showDetailsModal = false"
                            class="rounded-xl border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition shadow-sm"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Recent Failed Queue Jobs Panel --}}
    @if ($recentFailedJobs->isNotEmpty())
        <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="rounded-lg bg-rose-100 p-1.5 text-rose-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-neutral-900">Recorded Failed Background Jobs</h4>
                        <p class="text-xs text-neutral-500">Unprocessed background task exceptions requiring queue retry.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                    <thead class="bg-neutral-50 font-semibold text-neutral-700">
                        <tr>
                            <th class="px-3 py-2">Job Name</th>
                            <th class="px-3 py-2">Queue</th>
                            <th class="px-3 py-2">Failure Exception</th>
                            <th class="px-3 py-2">Failed At</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white">
                        @foreach ($recentFailedJobs as $job)
                            <tr>
                                <td class="px-3 py-2 font-semibold text-neutral-900">{{ $job->name }}</td>
                                <td class="px-3 py-2 font-mono text-neutral-600">{{ $job->queue }}</td>
                                <td class="px-3 py-2 text-rose-700 max-w-sm truncate" title="{{ $job->exception_preview }}">{{ $job->exception_preview }}</td>
                                <td class="px-3 py-2 text-neutral-500">{{ $job->failed_at }}</td>
                                <td class="px-3 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.recovery.retry-job', $job->uuid) }}" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="rounded-lg bg-neutral-900 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-neutral-800 transition"
                                            onclick="return confirm('Retry this queue job now?');"
                                        >
                                            Retry Job
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
