<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" x-data="{ ingestModal: false }">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Good Storage Practice (DOH AO 2014-0034)</p>
                <h2 class="text-2xl font-bold text-neutral-900">Cold Chain & IoT Telemetry Monitor</h2>
                <p class="text-sm text-neutral-600">Continuous environmental sensing, automated excursion locking, and rolling Haynes Mean Kinetic Temperature (MKT).</p>
            </div>
            <div class="flex items-center gap-2">
                @can(\App\Enums\Permission::ManageTelemetryExcursions->value)
                    <button type="button" @click="$dispatch('open-ingest-modal')" class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        Log Sensor Reading
                    </button>
                @endcan
                <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    &larr; Warehouse Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ showIngestModal: false, showReleaseModal: false, releaseLocationId: null, releaseLocationCode: '' }"
         @open-ingest-modal.window="showIngestModal = true"
         @open-release-modal.window="showReleaseModal = true; releaseLocationId = $event.detail.id; releaseLocationCode = $event.detail.code">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            @if(session('success'))
                <x-ui.alert variant="success" :message="session('success')" />
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" :message="session('error')" />
            @endif
            @if($errors->any())
                <x-ui.alert variant="danger" title="Validation errors occurred">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            {{-- Active Excursion Holds Banner --}}
            @if($activeHolds->isNotEmpty())
                <div class="rounded-xl border border-red-300 bg-red-50 p-6 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="rounded-full bg-red-600 p-2 text-white shrink-0">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-red-900">Active Temperature Excursion Locks Active</h3>
                            <p class="text-xs text-red-700 mt-1">The following storage bins breached stability criteria and are locked from warehouse picking to protect clinical safety. Releasing an excursion hold requires written stability justification.</p>
                            
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($activeHolds as $hold)
                                    <div class="rounded-lg bg-white p-3.5 border border-red-200 shadow-sm flex items-center justify-between">
                                        <div>
                                            <span class="font-mono text-xs font-bold text-red-800">{{ $hold->code }}</span>
                                            <p class="text-xs text-neutral-600">{{ $hold->name }}</p>
                                            <p class="text-[11px] text-red-600 font-semibold">{{ $hold->stockLevels->count() }} batches held</p>
                                        </div>
                                        @can(\App\Enums\Permission::ManageTelemetryExcursions->value)
                                            <button type="button" @click="$dispatch('open-release-modal', { id: {{ $hold->id }}, code: '{{ $hold->code }}' })" class="rounded bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700">
                                                Release Hold
                                            </button>
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Sensor Status Grid --}}
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($sensorStats as $stat)
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-sm font-bold text-neutral-800">{{ $stat['location']->code }}</span>
                            @if($stat['location']->excursion_hold)
                                <span class="rounded bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">EXCURSION HOLD</span>
                            @elseif($stat['latest']?->excursion_status === 'warning')
                                <span class="rounded bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">WARNING</span>
                            @else
                                <span class="rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">NORMAL</span>
                            @endif
                        </div>

                        <div class="mt-4 flex items-baseline justify-between">
                            <div>
                                <span class="text-3xl font-black text-neutral-900">
                                    {{ $stat['latest'] ? number_format($stat['latest']->temperature_celsius, 1).'°C' : '--' }}
                                </span>
                                <p class="text-xs text-neutral-500 capitalize mt-1">{{ $stat['location']->temperature_classification }} Zone</p>
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-bold text-neutral-700">
                                    MKT: {{ $stat['mkt'] !== null ? number_format($stat['mkt'], 1).'°C' : 'N/A' }}
                                </div>
                                <div class="text-[11px] text-neutral-500">
                                    Humidity: {{ $stat['latest']?->relative_humidity_pct ? number_format($stat['latest']->relative_humidity_pct, 1).'%' : 'N/A' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-neutral-100 pt-3 text-[11px] text-neutral-400 flex justify-between">
                            <span>Sensor: {{ $stat['latest']?->sensor_id ?? 'Enrolled' }}</span>
                            <span>{{ $stat['latest']?->recorded_at ? $stat['latest']->recorded_at->diffForHumans() : 'No data' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-sm text-neutral-500">
                        No monitored storage locations enrolled.
                    </div>
                @endforelse
            </div>

            {{-- Recent Telemetry Stream Log Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                    <h3 class="text-base font-bold text-neutral-900">IoT Telemetry Time-Series Stream</h3>
                    <span class="text-xs text-neutral-500">Append-only continuous sensor log</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-6 py-3">Timestamp (UTC / PHT)</th>
                                <th class="px-6 py-3">Sensor Identifier</th>
                                <th class="px-6 py-3">Storage Location</th>
                                <th class="px-6 py-3">Temperature</th>
                                <th class="px-6 py-3">Relative Humidity</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Resulting Event</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 font-mono text-xs">
                            @forelse($logs as $log)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-3 text-neutral-500">{{ $log->recorded_at?->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-6 py-3 font-bold text-neutral-800">{{ $log->sensor_id }}</td>
                                    <td class="px-6 py-3 text-primary-700">{{ $log->location?->code }}</td>
                                    <td class="px-6 py-3 font-bold @if($log->excursion_status === 'excursion') text-red-600 @elseif($log->excursion_status === 'warning') text-amber-600 @else text-neutral-900 @endif">
                                        {{ number_format($log->temperature_celsius, 2) }}°C
                                    </td>
                                    <td class="px-6 py-3 text-neutral-600">{{ $log->relative_humidity_pct ? number_format($log->relative_humidity_pct, 1).'%' : '--' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex rounded px-2 py-0.5 text-[10px] font-bold uppercase
                                            @if($log->excursion_status === 'excursion') bg-red-100 text-red-800
                                            @elseif($log->excursion_status === 'warning') bg-amber-100 text-amber-800
                                            @else bg-emerald-100 text-emerald-800 @endif">
                                            {{ $log->excursion_status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 font-sans text-xs text-neutral-500">{{ $log->resulting_event ?? 'Steady-state stream' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-neutral-500 font-sans text-sm">
                                        No telemetry readings logged yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-200 px-6 py-4">
                    {{ $logs->links() }}
                </div>
            </div>

        </div>

        {{-- Ingest Telemetry Modal --}}
        <div x-show="showIngestModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center" x-cloak style="display: none;">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @click.away="showIngestModal = false">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                    <h3 class="text-base font-bold text-neutral-900">Ingest IoT Sensor Reading</h3>
                    <button type="button" @click="showIngestModal = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                </div>

                <form method="POST" action="{{ route('inventory.warehousing.telemetry.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="sensor_id" class="text-xs font-semibold uppercase text-neutral-500">Sensor Identifier *</label>
                        <input type="text" id="sensor_id" name="sensor_id" required value="IOT-TMP-COLD01" class="mt-1 w-full rounded-lg border-neutral-300 font-mono text-sm">
                    </div>
                    <div>
                        <label for="storage_location_id" class="text-xs font-semibold uppercase text-neutral-500">Monitored Storage Location *</label>
                        <select id="storage_location_id" name="storage_location_id" required class="mt-1 w-full rounded-lg border-neutral-300 text-sm">
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->code }} ({{ $loc->name }} - {{ $loc->temperature_classification }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-3 grid-cols-2">
                        <div>
                            <label for="temperature_celsius" class="text-xs font-semibold uppercase text-neutral-500">Temperature (°C) *</label>
                            <input type="number" step="0.01" id="temperature_celsius" name="temperature_celsius" required placeholder="e.g. 4.50" class="mt-1 w-full rounded-lg border-neutral-300 text-sm font-bold">
                        </div>
                        <div>
                            <label for="relative_humidity_pct" class="text-xs font-semibold uppercase text-neutral-500">Humidity (%)</label>
                            <input type="number" step="0.01" id="relative_humidity_pct" name="relative_humidity_pct" placeholder="e.g. 52.0" class="mt-1 w-full rounded-lg border-neutral-300 text-sm font-bold">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-neutral-100 pt-4">
                        <button type="button" @click="showIngestModal = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700">Cancel</button>
                        <button type="submit" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Ingest Telemetry</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Release Excursion Hold Modal --}}
        <div x-show="showReleaseModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-neutral-900/60 p-4 sm:items-center" x-cloak style="display: none;">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.away="showReleaseModal = false">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                    <h3 class="text-base font-bold text-red-900">Release Temperature Excursion Hold</h3>
                    <button type="button" @click="showReleaseModal = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                </div>

                <form :action="'/inventory/warehousing/telemetry/' + releaseLocationId + '/release'" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <p class="text-xs text-neutral-600 mb-2">You are releasing the excursion hold on location <span class="font-mono font-bold text-neutral-900" x-text="releaseLocationCode"></span>. This action restores held batches to active picking inventory.</p>
                        <label for="justification" class="text-xs font-semibold uppercase text-neutral-500">Stability Evaluation / Variance Justification *</label>
                        <textarea id="justification" name="justification" required rows="4" placeholder="Document the clinical pharmacist stability review or manufacturer thermal variance approval..." class="mt-1 w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-neutral-100 pt-4">
                        <button type="button" @click="showReleaseModal = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700">Cancel</button>
                        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Authorize Release</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
