<x-app-layout>
    <x-ui.page-header
        title="System Health Diagnostics"
        subtitle="Infrastructure health telemetry, subsystem latency metrics, and operational readiness audits."
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Recovery Center' => route('admin.recovery.index'),
            'Health Diagnostics' => null
        ]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('admin.recovery.index') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50 transition"
                >
                    <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to Incidents</span>
                </a>

                <button
                    type="button"
                    onclick="window.location.reload();"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-600 transition"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Re-Run Diagnostics</span>
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Overall Status Banner --}}
    <div class="rounded-2xl border p-5 shadow-sm {{ $diagnostics['overall_status'] === 'healthy' ? 'border-emerald-200 bg-emerald-50/60' : ($diagnostics['overall_status'] === 'warning' ? 'border-amber-200 bg-amber-50/60' : 'border-rose-200 bg-rose-50/60') }}">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $diagnostics['overall_status'] === 'healthy' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    @if ($diagnostics['overall_status'] === 'healthy')
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    @else
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @endif
                </span>
                <div>
                    <h3 class="text-base font-bold text-neutral-900">
                        {{ $diagnostics['overall_status'] === 'healthy' ? 'All Systems Operational' : 'Subsystem Warnings Detected' }}
                    </h3>
                    <p class="text-xs text-neutral-600 mt-0.5">Telemetry evaluated at {{ $diagnostics['timestamp'] }}</p>
                </div>
            </div>

            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider {{ $diagnostics['overall_status'] === 'healthy' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                {{ strtoupper($diagnostics['overall_status']) }}
            </span>
        </div>
    </div>

    {{-- Subsystems Detailed Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        {{-- Database Subsystem --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-primary-50 p-2 text-primary-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    </span>
                    <h4 class="font-bold text-neutral-900">Database Engine</h4>
                </div>
                <span class="rounded px-2 py-0.5 text-xs font-bold uppercase {{ $diagnostics['database']['status'] === 'healthy' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $diagnostics['database']['status'] }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Driver</dt>
                    <dd class="font-mono text-neutral-900 font-bold mt-0.5">{{ $diagnostics['database']['driver'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Database Name</dt>
                    <dd class="font-mono text-neutral-900 font-bold mt-0.5 truncate">{{ $diagnostics['database']['database'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Query Latency</dt>
                    <dd class="font-bold text-neutral-900 mt-0.5">{{ $diagnostics['database']['latency_ms'] }} ms</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Connection Check</dt>
                    <dd class="font-semibold text-emerald-700 mt-0.5">Read/Write Verified</dd>
                </div>
            </dl>
            <p class="text-xs text-neutral-500 border-t border-neutral-100 pt-3">{{ $diagnostics['database']['message'] }}</p>
        </div>

        {{-- Queue Subsystem --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-indigo-50 p-2 text-indigo-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </span>
                    <h4 class="font-bold text-neutral-900">Queue &amp; Background Pipeline</h4>
                </div>
                <span class="rounded px-2 py-0.5 text-xs font-bold uppercase {{ $diagnostics['queue']['status'] === 'healthy' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $diagnostics['queue']['status'] }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Driver</dt>
                    <dd class="font-mono text-neutral-900 font-bold mt-0.5">{{ $diagnostics['queue']['driver'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Pending Tasks</dt>
                    <dd class="font-bold text-neutral-900 mt-0.5">{{ $diagnostics['queue']['pending_count'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Failed Tasks</dt>
                    <dd class="font-bold text-rose-700 mt-0.5">{{ $diagnostics['queue']['failed_count'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Status</dt>
                    <dd class="font-semibold {{ $diagnostics['queue']['failed_count'] > 0 ? 'text-amber-700' : 'text-emerald-700' }} mt-0.5">
                        {{ $diagnostics['queue']['failed_count'] > 0 ? 'Action Required' : 'Operational' }}
                    </dd>
                </div>
            </dl>
            <p class="text-xs text-neutral-500 border-t border-neutral-100 pt-3">{{ $diagnostics['queue']['message'] }}</p>
        </div>

        {{-- Storage Subsystem --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-teal-50 p-2 text-teal-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    </span>
                    <h4 class="font-bold text-neutral-900">Storage / Disks</h4>
                </div>
                <span class="rounded px-2 py-0.5 text-xs font-bold uppercase {{ $diagnostics['storage']['status'] === 'healthy' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $diagnostics['storage']['status'] }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Free Space</dt>
                    <dd class="font-bold text-neutral-900 mt-0.5">{{ $diagnostics['storage']['free_space_gb'] ?? 'N/A' }} GB</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Write Access</dt>
                    <dd class="font-semibold text-emerald-700 mt-0.5">Verified / Writable</dd>
                </div>
            </dl>
            <p class="text-xs text-neutral-500 border-t border-neutral-100 pt-3">{{ $diagnostics['storage']['message'] }}</p>
        </div>

        {{-- Cache Subsystem --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-amber-50 p-2 text-amber-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <h4 class="font-bold text-neutral-900">Application Cache</h4>
                </div>
                <span class="rounded px-2 py-0.5 text-xs font-bold uppercase {{ $diagnostics['cache']['status'] === 'healthy' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $diagnostics['cache']['status'] }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Active Store</dt>
                    <dd class="font-mono text-neutral-900 font-bold mt-0.5">{{ $diagnostics['cache']['store'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">Round-Trip Latency</dt>
                    <dd class="font-bold text-neutral-900 mt-0.5">{{ $diagnostics['cache']['latency_ms'] }} ms</dd>
                </div>
            </dl>
            <p class="text-xs text-neutral-500 border-t border-neutral-100 pt-3">{{ $diagnostics['cache']['message'] }}</p>
        </div>
    </div>
</x-app-layout>
