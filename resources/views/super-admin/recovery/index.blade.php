<x-app-layout>
    <x-ui.page-header
        title="System Recovery Center"
        subtitle="Recorded system failures, their recovery attempts, and the evidence behind each outcome."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Recovery Center' => null]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2 flex-wrap">
                <a
                    href="{{ route('super-admin.recovery.health') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    <x-ui.icon name="shield-check" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                    <span>Health Diagnostics</span>
                </a>

                <form method="POST" action="{{ route('super-admin.recovery.rebuild-cache') }}"
                      data-confirm-title="Clear application cache"
                      data-confirm-message="This clears the application cache and then verifies the cache store still responds. Continue?"
                      data-confirm-label="Clear Cache"
                      data-confirm-variant="warning">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        <x-ui.icon name="arrow-path" class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                        <span>Clear Cache</span>
                    </button>
                </form>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Every figure below is counted from the incident table itself, one row per
         incident, so an incident retried five times is still counted once. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat
            label="Total incidents"
            :value="number_format($metrics['total'])"
            icon="document-text"
            tone="neutral"
            :hint="$metrics['new_last_24h'] . ' in the last 24 hours'"
        />

        <x-ui.stat
            label="Open incidents"
            :value="number_format($metrics['open'])"
            icon="exclamation-triangle"
            tone="warning"
            :hint="$metrics['awaiting_worker'] . ' awaiting a worker outcome'"
        />

        <x-ui.stat
            label="Recovered"
            :value="number_format($metrics['recovered'])"
            icon="check-circle"
            tone="success"
            :hint="$metrics['resolved'] . ' closed manually'"
        />

        <x-ui.stat
            label="Failed recovery attempts"
            :value="number_format($metrics['failed_attempts'])"
            icon="arrow-path"
            tone="danger"
            :hint="$metrics['success_rate'] === null
                ? 'No incident has reached a recovery verdict yet'
                : $metrics['success_rate'] . '% of incidents with a verdict recovered'"
        />
    </div>

    <x-ui.card title="Filter incidents" subtitle="Narrow the list by recovery status, subsystem module, keyword, or occurrence date.">
        <form method="GET" action="{{ route('super-admin.recovery.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 items-end">
            <div>
                <label for="filter-search" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Search</label>
                <input
                    id="filter-search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Incident ID, operation, or summary"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                />
            </div>

            <div>
                <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Recovery status</label>
                <select
                    id="filter-status"
                    name="status"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                >
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-module" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Module</label>
                <select
                    id="filter-module"
                    name="module"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                >
                    <option value="">All modules</option>
                    @foreach ($availableModules as $module)
                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-date-from" class="block text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Occurred from</label>
                <input
                    id="filter-date-from"
                    name="date_from"
                    type="date"
                    value="{{ $filters['date_from'] ?? '' }}"
                    class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                />
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-700 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-600 transition"
                >
                    <x-ui.icon name="funnel" class="h-3.5 w-3.5" />
                    <span>Apply</span>
                </button>
                <a
                    href="{{ route('super-admin.recovery.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    Reset
                </a>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padding="false">
        <x-slot:header>
            <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Recorded incidents</h2>
            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                {{ number_format($records->total()) }} {{ \Illuminate\Support\Str::plural('incident', $records->total()) }} matching the current filters.
            </p>
        </x-slot:header>

        {{-- Desktop table --}}
        <div class="hidden lg:block w-full overflow-x-auto">
            <table class="w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-xs">
                <thead class="bg-neutral-50 dark:bg-neutral-800/60 font-semibold text-neutral-700 dark:text-neutral-300">
                    <tr>
                        <th scope="col" class="px-4 py-3">Incident</th>
                        <th scope="col" class="px-4 py-3">Failure</th>
                        <th scope="col" class="px-4 py-3">Responsible</th>
                        <th scope="col" class="px-4 py-3">Recovery</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                    @forelse ($records as $record)
                        <tr class="hover:bg-primary-50/20 dark:hover:bg-primary-950/20 transition-colors">
                            <td class="px-4 py-3.5 align-top">
                                <a href="{{ route('super-admin.recovery.show', $record) }}" class="font-mono font-semibold text-primary-700 hover:text-primary-800 hover:underline dark:text-primary-400">
                                    {{ $record->error_id }}
                                </a>
                                <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ $record->module }}
                                    </span>
                                    <span class="font-mono text-[11px] text-neutral-500 dark:text-neutral-400">{{ $record->operation }}</span>
                                </div>
                                @if ($record->reference_id)
                                    <div class="mt-1 font-mono text-[11px] text-neutral-400 dark:text-neutral-500 truncate" title="{{ $record->reference_id }}">
                                        Ref: {{ $record->reference_id }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3.5 align-top">
                                <div class="font-medium text-neutral-900 dark:text-neutral-100 leading-snug line-clamp-2" title="{{ $record->error_summary }}">
                                    {{ $record->error_summary }}
                                </div>
                                <div class="mt-1 flex items-center gap-1.5 text-[11px] text-neutral-500 dark:text-neutral-400">
                                    <x-ui.badge :status="$record->failure_type->value">{{ $record->failure_type->label() }}</x-ui.badge>
                                </div>
                            </td>

                            <td class="px-4 py-3.5 align-top">
                                <div class="font-medium text-neutral-800 dark:text-neutral-200 truncate" title="{{ $record->user_snapshot ?? 'System / Automated' }}">
                                    {{ $record->user_snapshot ? \Illuminate\Support\Str::before($record->user_snapshot, ' (') : 'System / Automated' }}
                                </div>
                                <time class="mt-1 block text-[11px] text-neutral-500 dark:text-neutral-400" datetime="{{ $record->created_at->toIso8601String() }}">
                                    {{ $record->created_at->setTimezone(config('app.timezone'))->format('M d, Y H:i') }}
                                </time>
                            </td>

                            <td class="px-4 py-3.5 align-top">
                                <x-ui.badge :status="$record->status->value" dot>{{ $record->status->label() }}</x-ui.badge>
                                <div class="mt-1.5 text-[11px] text-neutral-500 dark:text-neutral-400">
                                    {{ $record->retry_count }} of {{ \App\Models\SystemRecoveryRecord::MAX_RECOVERY_ATTEMPTS }} attempts used
                                </div>
                                @if ($record->status === \App\Enums\RecoveryStatus::RecoveryFailed && $record->last_attempt_error)
                                    <div class="mt-1 text-[11px] text-danger-700 dark:text-rose-400 line-clamp-2" title="{{ $record->last_attempt_error }}">
                                        {{ $record->last_attempt_error }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3.5 align-top">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <a
                                        href="{{ route('super-admin.recovery.show', $record) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-neutral-700 hover:bg-neutral-50 transition dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                    >
                                        <x-ui.icon name="eye" class="h-3 w-3" />
                                        <span>View</span>
                                    </a>

                                    @if ($record->canRetry())
                                        <form method="POST" action="{{ route('super-admin.recovery.retry', $record) }}"
                                              data-confirm-title="Run recovery attempt"
                                              data-confirm-message="HIMS will re-run the failed operation for incident {{ $record->error_id }} and report what it actually observed. Continue?"
                                              data-confirm-label="Run Recovery">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1 rounded-lg bg-success-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-success-500 transition"
                                            >
                                                <x-ui.icon name="arrow-path" class="h-3 w-3" />
                                                <span>Retry</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                                    <x-ui.icon name="check-circle" class="h-6 w-6" />
                                </div>
                                <div class="mt-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No incidents recorded</div>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 max-w-md mx-auto">
                                    No failed operation has been recorded. An incident appears here only when an import, export, queued job, transaction, or integration genuinely fails.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile and tablet card stream --}}
        <div class="block lg:hidden divide-y divide-neutral-100 dark:divide-neutral-800">
            @forelse ($records as $record)
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('super-admin.recovery.show', $record) }}" class="font-mono text-xs font-semibold text-primary-700 hover:underline dark:text-primary-400">
                                {{ $record->error_id }}
                            </a>
                            <div class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                {{ $record->module }} &middot; <span class="font-mono">{{ $record->operation }}</span>
                            </div>
                        </div>
                        <x-ui.badge :status="$record->status->value" dot>{{ $record->status->label() }}</x-ui.badge>
                    </div>

                    <div class="rounded-lg border border-neutral-200 bg-neutral-50/80 p-3 dark:border-neutral-800 dark:bg-neutral-800/40">
                        <div class="text-xs font-medium text-neutral-900 dark:text-neutral-100">{{ $record->error_summary }}</div>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <x-ui.badge :status="$record->failure_type->value">{{ $record->failure_type->label() }}</x-ui.badge>
                            <span class="text-[11px] text-neutral-500 dark:text-neutral-400">
                                {{ $record->retry_count }} of {{ \App\Models\SystemRecoveryRecord::MAX_RECOVERY_ATTEMPTS }} attempts used
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-[11px]">
                        <div>
                            <span class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Responsible</span>
                            <div class="mt-0.5 text-neutral-700 dark:text-neutral-300 truncate">
                                {{ $record->user_snapshot ? \Illuminate\Support\Str::before($record->user_snapshot, ' (') : 'System / Automated' }}
                            </div>
                        </div>
                        <div>
                            <span class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Occurred</span>
                            <div class="mt-0.5 text-neutral-700 dark:text-neutral-300">
                                {{ $record->created_at->setTimezone(config('app.timezone'))->format('M d, Y H:i') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <a
                            href="{{ route('super-admin.recovery.show', $record) }}"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg border border-neutral-300 bg-white py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        >
                            <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                            <span>View</span>
                        </a>

                        @if ($record->canRetry())
                            <form method="POST" action="{{ route('super-admin.recovery.retry', $record) }}" class="flex-1"
                                  data-confirm-title="Run recovery attempt"
                                  data-confirm-message="HIMS will re-run the failed operation for incident {{ $record->error_id }} and report what it actually observed. Continue?"
                                  data-confirm-label="Run Recovery">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-success-600 py-2 text-xs font-semibold text-white hover:bg-success-500 transition"
                                >
                                    <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                                    <span>Retry</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                        <x-ui.icon name="check-circle" class="h-5 w-5" />
                    </div>
                    <div class="mt-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No incidents recorded</div>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Nothing has failed. Incidents appear here only for genuine recoverable failures.
                    </p>
                </div>
            @endforelse
        </div>

        @if ($records->hasPages())
            <div class="border-t border-neutral-200 dark:border-neutral-800 px-4 py-3">
                {{ $records->links() }}
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
