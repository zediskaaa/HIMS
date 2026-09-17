@php
    use Illuminate\Support\Str;

    // Diagnostics report their own observed state, so the panel only maps that
    // state onto a palette rather than deciding on a colour of its own.
    $statusStyles = [
        'healthy' => ['badge' => 'success', 'tone' => 'text-success-700 dark:text-emerald-400'],
        'warning' => ['badge' => 'warning', 'tone' => 'text-warning-700 dark:text-amber-400'],
        'degraded' => ['badge' => 'warning', 'tone' => 'text-warning-700 dark:text-amber-400'],
        'unhealthy' => ['badge' => 'danger', 'tone' => 'text-danger-700 dark:text-rose-400'],
        'info' => ['badge' => 'neutral', 'tone' => 'text-neutral-600 dark:text-neutral-400'],
    ];

    $overall = $diagnostics['overall_status'];
    $overallStyles = match ($overall) {
        'healthy' => ['border-success-200 bg-success-50/60 dark:border-emerald-900/60 dark:bg-emerald-950/30', 'text-success-700 dark:text-emerald-400', 'health', 'All monitored subsystems are reporting healthy.'],
        'warning' => ['border-warning-200 bg-warning-50/60 dark:border-amber-900/60 dark:bg-amber-950/30', 'text-warning-700 dark:text-amber-400', 'exclamation-triangle', 'At least one subsystem needs attention. Details are below.'],
        default => ['border-danger-200 bg-danger-50/60 dark:border-rose-900/60 dark:bg-rose-950/30', 'text-danger-700 dark:text-rose-400', 'exclamation-triangle', 'A subsystem is not responding. Review the failing check below.'],
    };

    // Only checks that actually report a status are listed; every value below is
    // read straight off the service's own return array.
    $checks = [
        [
            'title' => 'Database engine',
            'icon' => 'server',
            'span' => 'lg:col-span-2',
            'data' => $diagnostics['database'],
            'rows' => [
                ['Driver', $diagnostics['database']['driver'] ?? 'Unknown', true],
                ['Database', $diagnostics['database']['database'] ?? 'Unknown', true],
                ['Query latency', ($diagnostics['database']['latency_ms'] ?? 0) . ' ms', false],
            ],
        ],
        [
            'title' => 'Queue and background pipeline',
            'icon' => 'inbox',
            'span' => 'lg:col-span-2',
            'data' => $diagnostics['queue'],
            'rows' => [
                ['Driver', $diagnostics['queue']['driver'] ?? 'Unknown', true],
                ['Pending jobs', (string) ($diagnostics['queue']['pending_count'] ?? 0), false],
                ['Failed jobs', (string) ($diagnostics['queue']['failed_count'] ?? 0), false],
            ],
        ],
        [
            'title' => 'Storage',
            'icon' => 'folder',
            'span' => 'lg:col-span-2',
            'data' => $diagnostics['storage'],
            'rows' => [
                ['Write access', ($diagnostics['storage']['write_permission'] ?? false) ? 'Verified' : 'Not writable', false],
                ['Free space', isset($diagnostics['storage']['free_space_gb']) ? $diagnostics['storage']['free_space_gb'] . ' GB' : 'Not reported', false],
            ],
        ],
        [
            'title' => 'Application cache',
            'icon' => 'bolt',
            'span' => 'lg:col-span-3',
            'data' => $diagnostics['cache'],
            'rows' => [
                ['Store', $diagnostics['cache']['store'] ?? 'Unknown', true],
                ['Round-trip latency', ($diagnostics['cache']['latency_ms'] ?? 0) . ' ms', false],
            ],
        ],
        [
            'title' => 'Local backups',
            'icon' => 'archive-box',
            'span' => 'md:col-span-2 lg:col-span-3',
            'data' => $diagnostics['backups'],
            'rows' => [
                ['Archives found', (string) ($diagnostics['backups']['backup_count'] ?? 0), false],
                ['Most recent', $diagnostics['backups']['last_backup_at'] ?? 'None recorded', false],
            ],
        ],
    ];
    $healthyCount = collect([
        $diagnostics['database']['status'] ?? null,
        $diagnostics['queue']['status'] ?? null,
        $diagnostics['storage']['status'] ?? null,
        $diagnostics['cache']['status'] ?? null,
        $diagnostics['backups']['status'] ?? null,
    ])->filter(fn ($s) => $s === 'healthy')->count();

    $telemetryTone = match ($overall) {
        'healthy' => [
            'beacon_bg' => 'bg-emerald-500/10 dark:bg-emerald-500/15',
            'beacon_ring' => 'ring-emerald-500/25 dark:ring-emerald-500/30',
            'ping_dot' => 'bg-emerald-400',
            'core_dot' => 'bg-emerald-500 shadow-emerald-500/50',
            'chip_bg' => 'bg-emerald-500/10 dark:bg-emerald-500/15',
            'chip_text' => 'text-emerald-700 dark:text-emerald-400',
            'chip_border' => 'border-emerald-500/30 dark:border-emerald-500/30',
            'chip_pulse' => 'bg-emerald-500 dark:bg-emerald-400',
            'label' => 'Operational',
        ],
        'warning', 'degraded' => [
            'beacon_bg' => 'bg-amber-500/10 dark:bg-amber-500/15',
            'beacon_ring' => 'ring-amber-500/25 dark:ring-amber-500/30',
            'ping_dot' => 'bg-amber-400',
            'core_dot' => 'bg-amber-500 shadow-amber-500/50',
            'chip_bg' => 'bg-amber-500/10 dark:bg-amber-500/15',
            'chip_text' => 'text-amber-700 dark:text-amber-400',
            'chip_border' => 'border-amber-500/30 dark:border-amber-500/30',
            'chip_pulse' => 'bg-amber-500 dark:bg-amber-400',
            'label' => 'Degraded',
        ],
        default => [
            'beacon_bg' => 'bg-rose-500/10 dark:bg-rose-500/15',
            'beacon_ring' => 'ring-rose-500/25 dark:ring-rose-500/30',
            'ping_dot' => 'bg-rose-400',
            'core_dot' => 'bg-rose-500 shadow-rose-500/50',
            'chip_bg' => 'bg-rose-500/10 dark:bg-rose-500/15',
            'chip_text' => 'text-rose-700 dark:text-rose-400',
            'chip_border' => 'border-rose-500/30 dark:border-rose-500/30',
            'chip_pulse' => 'bg-rose-500 dark:bg-rose-400',
            'label' => 'Attention Required',
        ],
    };
@endphp

<x-app-layout>
    <x-ui.page-header
        title="System Health Diagnostics"
        subtitle="Live checks against the database, queue, storage, cache, and local backups."
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Recovery Center' => route('super-admin.recovery.index'),
            'Health Diagnostics' => null,
        ]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2 flex-wrap">
                <a
                    href="{{ route('super-admin.recovery.index') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                    <span>Back to Incidents</span>
                </a>

                {{-- A plain navigation re-runs every check for real; there is no
                     cached or previously computed result to refresh. --}}
                <a
                    href="{{ route('super-admin.recovery.health') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs transition hover:bg-primary-600"
                >
                    <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                    <span>Re-run Diagnostics</span>
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- System Telemetry Console: Not a banner, Not a generic badge --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900 transition-colors">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {{-- Left: Heartbeat Beacon & Overall State --}}
            <div class="flex items-center gap-3 min-w-0">
                <div class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $telemetryTone['beacon_bg'] }} ring-1 ring-inset {{ $telemetryTone['beacon_ring'] }}">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $telemetryTone['ping_dot'] }} opacity-75"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full {{ $telemetryTone['core_dot'] }} shadow-xs"></span>
                    </span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                            Overall System Health
                        </h2>

                        {{-- Unique Custom Status Chip --}}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $telemetryTone['chip_bg'] }} {{ $telemetryTone['chip_text'] }} border {{ $telemetryTone['chip_border'] }} shadow-2xs">
                            <span class="h-1.5 w-1.5 rounded-full {{ $telemetryTone['chip_pulse'] }} animate-pulse"></span>
                            {{ Str::headline($overall) }} &middot; {{ $telemetryTone['label'] }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400 truncate">
                        {{ $overallStyles[3] }}
                    </p>
                </div>
            </div>

            {{-- Right: Coverage and Live Evaluation --}}
            <div class="flex flex-wrap items-center gap-3.5 border-t border-neutral-100 pt-2.5 sm:border-t-0 sm:pt-0 dark:border-neutral-800/80 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Subsystems</span>
                    <span class="font-semibold text-neutral-800 dark:text-neutral-200 tabular-nums">{{ $healthyCount }} of 5 Healthy</span>
                </div>
                <span class="text-neutral-300 dark:text-neutral-700 hidden sm:inline">&bull;</span>
                <div class="flex items-center gap-1.5">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Evaluated</span>
                    <span class="font-mono text-[11px] text-neutral-700 dark:text-neutral-300">{{ $diagnostics['timestamp'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Balanced 6-Column Grid Layout (3 on top row, 2 on bottom row) --}}
    <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-6">
        @foreach ($checks as $check)
            @php
                $status = $check['data']['status'] ?? 'info';
                $style = $statusStyles[$status] ?? $statusStyles['info'];
            @endphp

            <div class="{{ $check['span'] ?? '' }} flex flex-col justify-between rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900 transition-colors">
                <div class="min-w-0">
                    {{-- Card Header: Icon, Subsystem Name, Status Badge --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                <x-ui.icon :name="$check['icon']" class="h-3.5 w-3.5" />
                            </span>
                            <h3 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100 truncate">
                                {{ $check['title'] }}
                            </h3>
                        </div>

                        <x-ui.badge :variant="$style['badge']" dot>{{ Str::headline($status) }}</x-ui.badge>
                    </div>

                    {{-- Card Metrics --}}
                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-3 text-xs">
                        @foreach ($check['rows'] as [$label, $value, $mono])
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 truncate">{{ $label }}</dt>
                                <dd @class([
                                    'mt-0.5 text-xs font-medium text-neutral-800 dark:text-neutral-200 break-words',
                                    'font-mono' => $mono,
                                ])>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Subsystem Observation Message --}}
                <p class="mt-3 border-t border-neutral-100 pt-2 text-[11px] text-neutral-500 dark:border-neutral-800/80 dark:text-neutral-400 leading-relaxed truncate" title="{{ $check['data']['message'] ?? '' }}">
                    {{ $check['data']['message'] ?? 'No detail reported.' }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- Queue failures warning (if any recorded in failed_jobs) --}}
    @if (($diagnostics['queue']['failed_count'] ?? 0) > 0)
        <div class="rounded-xl border border-warning-200 bg-warning-50/60 p-3.5 text-xs dark:border-amber-900/60 dark:bg-amber-950/30">
            <p class="flex items-start gap-2 text-warning-800 dark:text-amber-200">
                <x-ui.icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    {{ $diagnostics['queue']['failed_count'] }}
                    {{ Str::plural('job', $diagnostics['queue']['failed_count']) }}
                    {{ $diagnostics['queue']['failed_count'] === 1 ? 'is' : 'are' }} recorded in the failed jobs table.
                    Each of those failures raises an incident in the
                    <a href="{{ route('super-admin.recovery.index') }}" class="font-semibold underline">Recovery Center</a>,
                    which is where a retry can be run with a full audit record.
                </span>
            </p>
        </div>
    @endif
</x-app-layout>
