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
        ['title' => 'Database engine', 'icon' => 'server', 'data' => $diagnostics['database'], 'rows' => [
            ['Driver', $diagnostics['database']['driver'] ?? 'Unknown', true],
            ['Database', $diagnostics['database']['database'] ?? 'Unknown', true],
            ['Query latency', ($diagnostics['database']['latency_ms'] ?? 0) . ' ms', false],
        ]],
        ['title' => 'Queue and background pipeline', 'icon' => 'inbox', 'data' => $diagnostics['queue'], 'rows' => [
            ['Driver', $diagnostics['queue']['driver'] ?? 'Unknown', true],
            ['Pending jobs', (string) ($diagnostics['queue']['pending_count'] ?? 0), false],
            ['Failed jobs', (string) ($diagnostics['queue']['failed_count'] ?? 0), false],
        ]],
        ['title' => 'Storage', 'icon' => 'folder', 'data' => $diagnostics['storage'], 'rows' => [
            ['Write access', ($diagnostics['storage']['write_permission'] ?? false) ? 'Verified' : 'Not writable', false],
            ['Free space', isset($diagnostics['storage']['free_space_gb']) ? $diagnostics['storage']['free_space_gb'] . ' GB' : 'Not reported', false],
        ]],
        ['title' => 'Application cache', 'icon' => 'bolt', 'data' => $diagnostics['cache'], 'rows' => [
            ['Store', $diagnostics['cache']['store'] ?? 'Unknown', true],
            ['Round-trip latency', ($diagnostics['cache']['latency_ms'] ?? 0) . ' ms', false],
        ]],
        ['title' => 'Local backups', 'icon' => 'archive-box', 'data' => $diagnostics['backups'], 'rows' => [
            ['Archives found', (string) ($diagnostics['backups']['backup_count'] ?? 0), false],
            ['Most recent', $diagnostics['backups']['last_backup_at'] ?? 'None recorded', false],
        ]],
    ];
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
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    <span>Back to Incidents</span>
                </a>

                {{-- A plain navigation re-runs every check for real; there is no
                     cached or previously computed result to refresh. --}}
                <a
                    href="{{ route('super-admin.recovery.health') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-600"
                >
                    <x-ui.icon name="arrow-path" class="h-4 w-4" />
                    <span>Re-run Diagnostics</span>
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-lg border p-4 shadow-sm sm:p-5 {{ $overallStyles[0] }}">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/70 dark:bg-neutral-900/60 {{ $overallStyles[1] }}">
                    <x-ui.icon :name="$overallStyles[2]" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ Str::headline($overall) }}
                    </h2>
                    <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">{{ $overallStyles[3] }}</p>
                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                        Evaluated {{ $diagnostics['timestamp'] }}
                    </p>
                </div>
            </div>

            <x-ui.badge :variant="$statusStyles[$overall]['badge'] ?? 'neutral'">{{ Str::headline($overall) }}</x-ui.badge>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($checks as $check)
            @php
                $status = $check['data']['status'] ?? 'info';
                $style = $statusStyles[$status] ?? $statusStyles['info'];
            @endphp

            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                            <x-ui.icon :name="$check['icon']" class="h-4 w-4" />
                        </span>
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $check['title'] }}</h3>
                    </div>
                </x-slot:header>

                <x-slot:actions>
                    <x-ui.badge :variant="$style['badge']" dot>{{ Str::headline($status) }}</x-ui.badge>
                </x-slot:actions>

                <dl class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
                    @foreach ($check['rows'] as [$label, $value, $mono])
                        <div class="min-w-0">
                            <dt class="font-semibold uppercase tracking-wider text-[10px] text-neutral-400 dark:text-neutral-500">{{ $label }}</dt>
                            <dd @class([
                                'mt-0.5 break-words text-neutral-800 dark:text-neutral-200',
                                'font-mono' => $mono,
                            ])>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-4 border-t border-neutral-100 pt-3 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    {{ $check['data']['message'] ?? 'No detail reported.' }}
                </p>
            </x-ui.card>
        @endforeach
    </div>

    {{-- Queue failures are counted above and recorded as incidents in the Recovery
         Center; listing the raw failed_jobs rows here as well would duplicate that
         data and invite a second, untraceable retry control. --}}
    @if (($diagnostics['queue']['failed_count'] ?? 0) > 0)
        <div class="rounded-lg border border-warning-200 bg-warning-50/60 p-4 text-xs dark:border-amber-900/60 dark:bg-amber-950/30">
            <p class="flex items-start gap-2 text-warning-800 dark:text-amber-200">
                <x-ui.icon name="information-circle" class="mt-px h-4 w-4 shrink-0" />
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
