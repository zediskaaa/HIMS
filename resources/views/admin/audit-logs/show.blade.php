<x-app-layout>
    @php
        $displayTime = $log->displayTimestamp();
        $hasOld = !empty($log->old_values);
        $hasNew = !empty($log->new_values);
        $hasBoth = $hasOld && $hasNew;
        $singleValues = $hasBoth ? [] : ($hasNew ? $log->new_values : ($hasOld ? $log->old_values : []));
    @endphp

    <x-ui.page-header
        :title="$log->action->label()"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Audit Trail' => route('admin.audit-logs.index'),
            'Event Details' => null,
        ]">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" :href="route('admin.audit-logs.index')" icon="arrow-left">
                Back to Audit Trail
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-6">
        {{-- Top Section: Balanced 2-Column Grid (Event Details & Security Origin) --}}
        <div class="grid min-w-0 gap-6 lg:grid-cols-2 items-start">
            {{-- Event Details Card --}}
            <x-ui.card title="Event Details" class="min-w-0">
                <div class="space-y-4">
                    {{-- Highlighted Description --}}
                    <div class="rounded-lg border border-neutral-200/80 bg-neutral-50/70 p-3.5">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Description</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900 leading-relaxed">{{ $log->description }}</dd>
                    </div>

                    {{-- Event Attributes Grid --}}
                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-3.5 text-xs">
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Outcome</dt>
                            <dd class="mt-1">
                                <x-ui.badge :variant="($log->outcome ?? 'success') === 'success' ? 'success' : 'danger'">
                                    {{ \Illuminate\Support\Str::headline($log->outcome ?? 'success') }}
                                </x-ui.badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Category</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $log->event_category ?? $log->action->category() }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Module</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $log->module ?? $log->action->module() }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Local date and time</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $displayTime->format('F j, Y, g:i:s A') }} {{ $log->displayTimezoneLabel() }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Authoritative UTC time</dt>
                            <dd class="mt-1 break-all font-mono text-[11px] text-neutral-700">{{ $log->authoritativeTimestamp()->format('Y-m-d H:i:s.u') }} UTC</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Event ID</dt>
                            <dd class="mt-1 break-all font-mono text-[11px] text-neutral-700">{{ $log->event_id ?? 'Legacy event' }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Correlation ID</dt>
                            <dd class="mt-1 break-all font-mono text-[11px] text-neutral-700">{{ $log->correlation_id ?? 'Not recorded for legacy event' }}</dd>
                        </div>
                    </dl>

                    @if ($log->business_reason)
                        <div class="mt-4 pt-3 border-t border-neutral-200/70">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Business Reason</dt>
                            <dd class="mt-1 whitespace-pre-wrap break-words text-xs text-neutral-700 leading-relaxed">{{ $log->business_reason }}</dd>
                        </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Context & Origin Card --}}
            <x-ui.card title="Context & Security Origin" class="min-w-0">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3.5 text-xs">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Performed by</dt>
                        <dd class="mt-1 font-semibold text-neutral-900 break-words">{{ $log->displayActorName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Role snapshot</dt>
                        <dd class="mt-1 font-medium text-neutral-800">{{ $log->actor_role ? (\App\Enums\UserRole::tryFrom($log->actor_role)?->label() ?? \Illuminate\Support\Str::headline($log->actor_role)) : 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Source</dt>
                        <dd class="mt-1 font-medium text-neutral-800">{{ \Illuminate\Support\Str::headline($log->source ?? 'legacy') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Target</dt>
                        <dd class="mt-1 break-words font-medium text-neutral-800">{{ $log->target_reference ?? $log->target_name ?? 'None' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">IP address</dt>
                        <dd class="mt-1 font-mono text-xs text-neutral-800">{{ $log->ip_address ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Device</dt>
                        <dd class="mt-1 font-medium text-neutral-800 break-words">{{ $log->deviceSummary() ?? 'Unavailable' }}</dd>
                    </div>
                    @php
                        $hasCoordinates = $log->location_latitude !== null && $log->location_longitude !== null;
                        $resolvedPlace = $log->placeName();
                    @endphp
                    <div class="sm:col-span-2 pt-2 border-t border-neutral-100"
                        @if ($hasCoordinates)
                            x-data="{
                                placeName: @js($resolvedPlace ?? $log->locationSummary()),
                                async init() {
                                    const lat = {{ (float) $log->location_latitude }};
                                    const lng = {{ (float) $log->location_longitude }};
                                    const cacheKey = `geo_place_${lat}_${lng}`;
                                    const cached = sessionStorage.getItem(cacheKey);
                                    if (cached) {
                                        this.placeName = cached;
                                        return;
                                    }
                                    try {
                                        const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14`, {
                                            headers: { 'Accept': 'application/json' }
                                        });
                                        if (res.ok) {
                                            const data = await res.json();
                                            const addr = data.address || {};
                                            const parts = [
                                                addr.quarter || addr.suburb || addr.neighbourhood || addr.city_district || addr.district,
                                                addr.city || addr.town || addr.municipality,
                                                addr.region || addr.state || addr.province,
                                                addr.country
                                            ].filter(Boolean);
                                            const unique = [...new Set(parts)];
                                            if (unique.length > 0) {
                                                this.placeName = unique.join(', ');
                                                sessionStorage.setItem(cacheKey, this.placeName);
                                            }
                                        }
                                    } catch (e) {}
                                }
                            }"
                        @endif>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Approximate location</dt>
                        <dd class="mt-1 font-medium text-neutral-900 break-words">
                            @if ($hasCoordinates)
                                <span x-text="placeName" class="font-semibold text-neutral-900">{{ $resolvedPlace ?? $log->locationSummary() ?? 'Unavailable' }}</span>
                                <span class="text-xs font-normal text-neutral-500 block mt-0.5">
                                    Coordinates: {{ $log->location_latitude }}, {{ $log->location_longitude }}
                                </span>
                            @else
                                <span>{{ $log->locationSummary() ?? 'Unavailable' }}</span>
                            @endif
                        </dd>
                        @if ($hasCoordinates)
                            <dd class="mt-1.5">
                                <a href="https://www.google.com/maps?q={{ $log->location_latitude }},{{ $log->location_longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-700 hover:text-primary-800 hover:underline">
                                    <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-primary-600" />
                                    <span>View on Google Maps</span>
                                </a>
                            </dd>
                        @endif
                        @if ($log->locationSourceLabel())
                            <dd class="mt-0.5 text-[11px] text-neutral-500">
                                {{ $log->locationSourceLabel() }}
                                @if ($log->location_accuracy_meters !== null)
                                    &middot; reported accuracy &plusmn;{{ number_format($log->location_accuracy_meters) }} m
                                @endif
                            </dd>
                        @endif
                    </div>
                    <div class="sm:col-span-2 pt-2 border-t border-neutral-100">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Raw user agent</dt>
                        <dd class="mt-1 font-mono text-[11px] text-neutral-600 break-all bg-neutral-50 p-2 rounded border border-neutral-200/70">{{ $log->user_agent ?? 'Not recorded' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        {{-- Bottom Section: Recorded Attributes & Changes --}}
        @if ($hasBoth)
            {{-- Comparison View: Before and After --}}
            <div class="grid min-w-0 gap-6 lg:grid-cols-2 items-start">
                <x-ui.card title="Before (Previous Values)" class="min-w-0">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        @foreach ($log->old_values as $field => $value)
                            <div class="rounded-md bg-neutral-50/70 p-3 border border-neutral-200 shadow-2xs">
                                <dt class="text-[11px] font-medium text-neutral-500">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                <dd class="mt-1 font-mono text-xs font-semibold text-neutral-900 break-words">
                                    {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <details class="mt-4 pt-3 border-t border-neutral-200">
                        <summary class="cursor-pointer text-xs font-medium text-neutral-500 hover:text-neutral-700">View Raw JSON (Before)</summary>
                        <pre class="mt-2 max-w-full overflow-x-auto whitespace-pre-wrap break-words rounded bg-neutral-900 p-3 font-mono text-xs text-neutral-200">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </x-ui.card>

                <x-ui.card title="After (Updated Values)" class="min-w-0">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        @foreach ($log->new_values as $field => $value)
                            <div class="rounded-md bg-primary-50/30 p-3 border border-primary-200 shadow-2xs">
                                <dt class="text-[11px] font-medium text-primary-700">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                <dd class="mt-1 font-mono text-xs font-semibold text-neutral-900 break-words">
                                    {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <details class="mt-4 pt-3 border-t border-neutral-200">
                        <summary class="cursor-pointer text-xs font-medium text-neutral-500 hover:text-neutral-700">View Raw JSON (After)</summary>
                        <pre class="mt-2 max-w-full overflow-x-auto whitespace-pre-wrap break-words rounded bg-neutral-900 p-3 font-mono text-xs text-neutral-200">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </x-ui.card>
            </div>
        @elseif ($hasNew || $hasOld)
            {{-- Single Set of Values: Maximize Screen with Multi-Column Card Grid --}}
            <x-ui.card :title="$hasNew ? 'Recorded Attributes Snapshot' : 'Prior Attributes Snapshot (Before Deletion)'" class="min-w-0">
                <div class="mb-3.5 flex items-center justify-between">
                    <span class="text-xs text-neutral-500 font-medium">
                        {{ $hasNew ? 'Recorded event parameters and payload' : 'Preserved record attributes prior to removal' }}
                    </span>
                    <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">
                        {{ count($singleValues) }} {{ \Illuminate\Support\Str::plural('field', count($singleValues)) }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 text-xs">
                    @foreach ($singleValues as $field => $value)
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50/70 p-3.5 transition hover:border-neutral-300 hover:bg-white hover:shadow-xs">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 truncate" title="{{ \Illuminate\Support\Str::headline($field) }}">
                                {{ \Illuminate\Support\Str::headline($field) }}
                            </dt>
                            <dd class="mt-1.5 font-mono text-xs font-semibold text-neutral-900 break-words">
                                @if ($field === 'filesize' && is_numeric($value))
                                    {{ number_format($value) }} bytes <span class="text-neutral-500 font-normal">({{ round($value / 1024, 1) }} KB)</span>
                                @else
                                    {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <details class="mt-4 pt-3 border-t border-neutral-200">
                    <summary class="cursor-pointer text-xs font-medium text-neutral-500 hover:text-neutral-700">View Raw Payload JSON</summary>
                    <pre class="mt-2 max-w-full overflow-x-auto whitespace-pre-wrap break-words rounded bg-neutral-900 p-3.5 font-mono text-xs text-neutral-200">{{ json_encode($singleValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
