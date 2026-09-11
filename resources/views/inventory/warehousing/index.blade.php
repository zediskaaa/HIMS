<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Hospital Supply Chain Execution</p>
                <h2 class="text-2xl font-bold text-neutral-900">Smart Warehousing System (SWS)</h2>
                <p class="text-sm text-neutral-600">Physical location topology, IoT cold-chain telemetry, scan verification, and regulatory compliance.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
                <a href="{{ route('inventory.warehousing.scan-station') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    Scan Workstation
                </a>
                @endcan
                @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                    <x-ui.button variant="secondary" :href="route('inventory.receiving.index')" icon="arrow-down-tray">Dock Receiving</x-ui.button>
                @endcan
                @can(\App\Enums\Permission::InspectStock->value)
                    <x-ui.button variant="secondary" :href="route('inventory.qc.index')" icon="shield-check">QC Inspection</x-ui.button>
                @endcan
                <a href="{{ route('inventory.warehouse-tasks.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    Warehouse Tasks
                </a>
                <a href="{{ route('inventory.warehousing.locations') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    Locations Explorer
                </a>
                <a href="{{ route('inventory.storage-locations') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    Location Registry
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- High-Level KPI Matrix --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Active Tasks</span>
                        <span class="rounded-full bg-blue-100 p-2 text-blue-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['open_tasks'] }}</span>
                        @if($metrics['overdue_tasks'] > 0)
                            <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ $metrics['overdue_tasks'] }} overdue</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">On track</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Put-away, pick, and replenishment work</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">IoT Cold Chain</span>
                        <span class="rounded-full bg-cyan-100 p-2 text-cyan-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['active_excursions'] }}</span>
                        @if($metrics['active_excursions'] > 0)
                            <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Excursion Holds</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Compliant</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">DOH AO 2014-0034 continuous thermal tracking</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Narcotics Vault (PDEA)</span>
                        <span class="rounded-full bg-purple-100 p-2 text-purple-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['narcotics_items'] }}</span>
                        <span class="inline-flex items-center rounded-md bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">Dual-Custody</span>
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">RA 9165 / DDB Reg 1 s. 2014 DDRB Ledger</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Consignment Implants</span>
                        <span class="rounded-full bg-amber-100 p-2 text-amber-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-bold text-neutral-900">{{ $metrics['pending_bill_onlys'] }}</span>
                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Bill-Only PRs</span>
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Operating room serial consumption tracking</p>
                </div>
            </div>

            {{-- Live Environmental Telemetry & MKT Status --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Cold Chain & Environmental Telemetry (Haynes MKT)</h3>
                        <p class="text-xs text-neutral-500">Calibrated real-time sensor streams and Mean Kinetic Temperature evaluations.</p>
                    </div>
                    <a href="{{ route('inventory.warehousing.telemetry') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
                        View Telemetry Console &rarr;
                    </a>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($criticalSensors as $sensor)
                        <div class="rounded-lg border @if($sensor['hold']) border-red-300 bg-red-50 @elseif($sensor['status'] === 'warning') border-amber-300 bg-amber-50 @else border-neutral-200 bg-neutral-50 @endif p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold text-neutral-700">{{ $sensor['location']->code }}</span>
                                @if($sensor['hold'])
                                    <span class="rounded bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">EXCURSION HOLD</span>
                                @elseif($sensor['status'] === 'warning')
                                    <span class="rounded bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">WARNING</span>
                                @else
                                    <span class="rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">COMPLIANT</span>
                                @endif
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <div>
                                    <p class="text-2xl font-black text-neutral-900">{{ $sensor['latest_temp'] !== null ? number_format($sensor['latest_temp'], 1).'°C' : 'N/A' }}</p>
                                    <p class="text-[11px] text-neutral-500 capitalize">{{ $sensor['location']->temperature_classification ?? 'Ambient' }} Storage</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-neutral-700">MKT: {{ $sensor['mkt'] !== null ? number_format($sensor['mkt'], 1).'°C' : '--' }}</p>
                                    <p class="text-[11px] text-neutral-500">24h Haynes Index</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-6 text-center text-sm text-neutral-500">
                            No temperature-classified storage zones currently configured.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Recent Warehouse Operational Tasks & Scans --}}
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Active Tasks Queue --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-neutral-900">Recent Warehouse Tasks</h3>
                        <a href="{{ route('inventory.warehouse-tasks.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
                            View All ({{ $metrics['open_tasks'] }}) &rarr;
                        </a>
                    </div>
                    <div class="divide-y divide-neutral-100 overflow-hidden">
                        @forelse($recentTasks as $task)
                            <div class="flex items-center justify-between px-6 py-3.5 hover:bg-neutral-50">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('inventory.warehouse-tasks.show', $task) }}" class="font-mono text-xs font-bold text-primary-700 hover:underline">
                                            {{ $task->task_number }}
                                        </a>
                                        <span class="rounded bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-neutral-700">
                                            {{ $task->task_type->label() }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-neutral-600">
                                        {{ $task->item?->name ?? 'General' }} &bull; {{ $task->requested_quantity }} units
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        @if($task->status->value === 'completed') bg-emerald-100 text-emerald-800
                                        @elseif($task->status->value === 'in_progress') bg-blue-100 text-blue-800
                                        @elseif($task->status->value === 'assigned') bg-amber-100 text-amber-800
                                        @else bg-neutral-100 text-neutral-700 @endif">
                                        {{ $task->status->label() }}
                                    </span>
                                    <p class="mt-0.5 text-[11px] text-neutral-500">
                                        {{ $task->assignedTo?->name ?? 'Unassigned' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-neutral-500">
                                No warehouse tasks generated.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Real-Time Scan Event Stream --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Real-Time Scan Log</h3>
                            <p class="text-xs text-neutral-500">GS1 DataMatrix and location barcode verification events.</p>
                        </div>
                        @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
                        <a href="{{ route('inventory.warehousing.scan-station') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
                            Launch Scanner &rarr;
                        </a>
                        @endcan
                    </div>
                    <div class="divide-y divide-neutral-100 overflow-hidden">
                        @forelse($recentScans as $scan)
                            <div class="flex items-center justify-between px-6 py-3 hover:bg-neutral-50">
                                <div class="truncate pr-4">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-2 w-2 rounded-full @if($scan->outcome === 'accepted') bg-emerald-500 @elseif($scan->outcome === 'identified') bg-amber-500 @else bg-red-500 @endif"></span>
                                        <span class="font-mono text-xs font-semibold text-neutral-800 truncate max-w-xs">{{ $scan->raw_value }}</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-500 truncate mt-0.5">
                                        {{ $scan->message ?? 'Scan processed' }} &bull; {{ $scan->scannedBy?->name ?? 'Operator' }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-mono text-[10px] text-neutral-400">
                                        {{ $scan->created_at?->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-neutral-500">
                                No barcode scan events recorded yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Smart Warehousing Submodules Quick Links --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @can(\App\Enums\Permission::AccessNarcoticsVault->value)
                <a href="{{ route('inventory.warehousing.narcotics') }}" class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-purple-300 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-purple-50 p-2.5 text-purple-700 group-hover:bg-purple-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-neutral-900 group-hover:text-purple-700">PDEA Narcotics Vault</h4>
                            <p class="text-xs text-neutral-500">Dual-custody DDRB registry</p>
                        </div>
                    </div>
                </a>
                @endcan

                @canany([\App\Enums\Permission::ManageTelemetryExcursions->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                <a href="{{ route('inventory.warehousing.telemetry') }}" class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-cyan-300 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-cyan-50 p-2.5 text-cyan-700 group-hover:bg-cyan-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-neutral-900 group-hover:text-cyan-700">Cold Chain Monitor</h4>
                            <p class="text-xs text-neutral-500">Haynes MKT & sensor feeds</p>
                        </div>
                    </div>
                </a>
                @endcanany

                @canany([\App\Enums\Permission::RecordConsignments->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                <a href="{{ route('inventory.warehousing.consignment') }}" class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-amber-50 p-2.5 text-amber-700 group-hover:bg-amber-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-neutral-900 group-hover:text-amber-700">Surgical Consignment</h4>
                            <p class="text-xs text-neutral-500">Bill-Only OR implant scans</p>
                        </div>
                    </div>
                </a>
                @endcanany

                @canany([\App\Enums\Permission::ManageWarehouseTopology->value, \App\Enums\Permission::ManageLocations->value, \App\Enums\Permission::ViewWarehouseTasks->value])
                <a href="{{ route('inventory.warehousing.locations') }}" class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-emerald-50 p-2.5 text-emerald-700 group-hover:bg-emerald-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-neutral-900 group-hover:text-emerald-700">Spatial Topology</h4>
                            <p class="text-xs text-neutral-500">Aisles, racks, and LASA rules</p>
                        </div>
                    </div>
                </a>
                @endcanany
            </div>

        </div>
    </div>
</x-app-layout>
