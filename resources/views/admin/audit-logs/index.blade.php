<x-app-layout>
    <x-ui.page-header
        title="Audit Trail"
        subtitle="Append-only history of security, administrative, procurement, inventory, warehouse, logistics, and system activity."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Audit Trail' => null]" />

    <x-ui.card
        title="Find Activity"
        subtitle="Use controlled filters for known values and search for descriptive activity."
        class="relative z-20 !overflow-visible">
        <form id="audit-log-filters" method="GET" action="{{ route('admin.audit-logs.index') }}"
              class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 xl:items-end">
            <div
                class="relative space-y-1.5"
                x-data="auditSearchAutocomplete({
                    endpoint: @js(route('admin.audit-logs.suggestions')),
                    formId: 'audit-log-filters',
                    initialQuery: @js($filters['search'] ?? ''),
                })"
                x-on:click.outside="close()"
            >
                <label for="audit-search" class="block text-sm font-medium text-neutral-700">Search</label>
                <input
                    id="audit-search"
                    name="search"
                    type="search"
                    placeholder="e.g. Juan, EMP-0144, action or target"
                    autocomplete="off"
                    class="block w-full rounded-md border border-neutral-300 text-sm text-neutral-900 shadow-sm transition-colors placeholder:text-neutral-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 focus:ring-offset-0"
                    x-model="query"
                    x-on:input="queue($event.target.value)"
                    x-on:focus="if (query.trim() && loaded) open = true"
                    x-on:keydown.down.prevent="move(1)"
                    x-on:keydown.up.prevent="move(-1)"
                    x-on:keydown.enter="selectActive($event)"
                    x-on:keydown.escape.stop="close()"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="audit-search-suggestions"
                    x-bind:aria-expanded="open"
                    x-bind:aria-activedescendant="activeIndex >= 0 ? `audit-suggestion-${activeIndex}` : null"
                />

                <div
                    id="audit-search-suggestions"
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-md border border-neutral-200 bg-white py-1 shadow-lg"
                    role="listbox"
                >
                    <p x-show="loading" class="px-3 py-2 text-sm text-neutral-500">
                        <x-ui.loader size="sm" label="Finding suggestions..." />
                    </p>
                    <p x-show="!loading && failed" class="px-3 py-2 text-sm text-danger-600">
                        Suggestions could not be loaded. You can still submit the search normally.
                    </p>
                    <p
                        x-show="!loading && !failed && loaded && suggestions.length === 0"
                        class="px-3 py-2 text-sm text-neutral-500"
                    >
                        No matching audit data.
                    </p>

                    <template x-for="(suggestion, index) in suggestions" :key="`${suggestion.category}:${suggestion.value}`">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm text-neutral-700 hover:bg-primary-50 hover:text-primary-900"
                            x-bind:id="`audit-suggestion-${index}`"
                            x-bind:class="activeIndex === index ? 'bg-primary-50 text-primary-900' : ''"
                            x-bind:aria-selected="activeIndex === index"
                            x-on:mouseenter="activeIndex = index"
                            x-on:click="select(suggestion)"
                            role="option"
                        >
                            <span class="min-w-0 truncate" x-text="suggestion.value"></span>
                            <span class="shrink-0 text-xs text-neutral-400" x-text="suggestion.category"></span>
                        </button>
                    </template>
                </div>
            </div>

            <x-ui.field
                name="target"
                label="Target or reference"
                type="search"
                :value="$filters['target'] ?? null"
                placeholder="Reference, record name, or ID" />

            <x-ui.field
                name="actor_id"
                label="User"
                type="select"
                :value="$filters['actor_id'] ?? null"
                placeholder="All users"
                :options="$actors" />

            <x-ui.field
                name="actor_role"
                label="Role snapshot"
                type="select"
                :value="$filters['actor_role'] ?? null"
                placeholder="All roles"
                :options="$roles" />

            <x-ui.field
                name="category"
                label="Category"
                type="select"
                :value="$filters['category'] ?? null"
                placeholder="All categories"
                :options="$categories" />

            <x-ui.field
                name="module"
                label="Module"
                type="select"
                :value="$filters['module'] ?? null"
                placeholder="All modules"
                :options="$modules" />

            <x-ui.field
                name="action"
                label="Action"
                type="select"
                :value="$filters['action'] ?? null"
                placeholder="All actions"
                :options="$actions" />

            <x-ui.field
                name="date_from"
                label="From"
                type="date"
                :value="$filters['date_from'] ?? null" />

            <x-ui.field
                name="date_to"
                label="To"
                type="date"
                :value="$filters['date_to'] ?? null" />

            <x-ui.field
                name="outcome"
                label="Outcome"
                type="select"
                :value="$filters['outcome'] ?? null"
                placeholder="All outcomes"
                :options="$outcomes" />

            <x-ui.field
                name="source"
                label="Source"
                type="select"
                :value="$filters['source'] ?? null"
                placeholder="All sources"
                :options="$sources" />

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="submit" icon="magnifying-glass" data-loading-text="Loading activity...">Filter</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="secondary" :href="route('admin.audit-logs.index')">Clear</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card
        title="Activity"
        :subtitle="$logs->total().' '.\Illuminate\Support\Str::plural('record', $logs->total()).' - '.config('app.timezone').' (PHT)'"
        :padding="false">
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Performed By</x-ui.table.th>
                <x-ui.table.th>Action</x-ui.table.th>
                <x-ui.table.th>Module</x-ui.table.th>
                <x-ui.table.th>Target</x-ui.table.th>
                <x-ui.table.th>Description</x-ui.table.th>
                <x-ui.table.th>Date &amp; Time</x-ui.table.th>
                <x-ui.table.th>IP Address</x-ui.table.th>
            </x-ui.table.head>
            <tbody x-data="{ expanded: {} }">
                @forelse ($logs as $log)
                    @php
                        $variant = match ($log->action) {
                            \App\Enums\AuditAction::CreatedUser => 'success',
                            \App\Enums\AuditAction::DeletedUser => 'danger',
                            \App\Enums\AuditAction::LoggedIn => 'primary',
                            \App\Enums\AuditAction::LoggedOut => 'neutral',
                            \App\Enums\AuditAction::ChangedPassword => 'warning',
                            \App\Enums\AuditAction::TemporarilyLockedUser => 'danger',
                            \App\Enums\AuditAction::UnlockedUser => 'success',
                            default => 'neutral',
                        };
                        $hasDetails = !empty($log->old_values) || !empty($log->new_values);
                        $hasBoth = !empty($log->old_values) && !empty($log->new_values);
                        $singleValues = !empty($log->new_values) ? $log->new_values : ($log->old_values ?? []);
                        $isSingleAfter = !empty($log->new_values);
                    @endphp
                    <x-ui.table.row x-bind:class="expanded['{{ $log->id }}'] ? 'bg-primary-50/40' : ''">
                        <x-ui.table.td class="whitespace-nowrap">
                            <div class="font-medium text-neutral-900">{{ $log->displayActorName() }}</div>
                            @if ($log->actor_employee_id)
                                <div class="font-mono text-xs text-neutral-500">
                                    {{ $log->actor_employee_id }}
                                </div>
                            @endif
                            @if ($log->actor_role)
                                <div class="mt-0.5">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-neutral-100 text-neutral-600">
                                        {{ \Illuminate\Support\Str::headline($log->actor_role) }}
                                    </span>
                                </div>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td class="whitespace-nowrap">
                            <x-ui.badge :variant="$variant">{{ $log->action->label() }}</x-ui.badge>
                            <span class="mt-1 block text-xs text-neutral-500">{{ $log->event_category ?? $log->action->category() }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td class="whitespace-nowrap">
                            <span class="font-medium text-neutral-800">{{ $log->module ?? $log->action->module() }}</span>
                            <span class="mt-1 block text-xs text-neutral-500">{{ \Illuminate\Support\Str::headline($log->source ?? 'user') }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <span class="font-medium text-neutral-800">{{ $log->target_name ?? '—' }}</span>
                            @if ($log->target_id)
                                <span class="block text-xs text-neutral-400 font-mono">ID {{ $log->target_id }}</span>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <p class="text-neutral-700 leading-relaxed">{{ $log->description }}</p>
                            @if ($hasDetails)
                                <button
                                    type="button"
                                    x-on:click="expanded['{{ $log->id }}'] = !expanded['{{ $log->id }}']"
                                    class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-primary-700 hover:text-primary-800 hover:underline focus:outline-none"
                                >
                                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform duration-200" x-bind:class="expanded['{{ $log->id }}'] ? 'rotate-180 text-primary-800' : 'text-primary-600'" />
                                    <span x-text="expanded['{{ $log->id }}'] ? 'Hide recorded details' : 'View recorded details'">View recorded details</span>
                                </button>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td muted class="whitespace-nowrap">
                            @php($displayTime = $log->displayTimestamp())
                            <time datetime="{{ $log->authoritativeTimestamp()->toIso8601String() }}" class="block font-medium text-neutral-900" title="{{ $displayTime->format('F j, Y, g:i:s A').' '.$log->displayTimezoneLabel() }}">
                                {{ $displayTime->format('M d, Y, g:i:s A') }}
                                <span class="block text-xs font-normal text-neutral-400">{{ $log->displayTimezoneLabel() }}</span>
                            </time>
                            <x-ui.badge :variant="($log->outcome ?? 'success') === 'success' ? 'success' : 'danger'" class="mt-1">
                                {{ \Illuminate\Support\Str::headline($log->outcome ?? 'success') }}
                            </x-ui.badge>
                        </x-ui.table.td>

                        <x-ui.table.td muted class="whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono text-xs text-neutral-600">{{ $log->ip_address ?? '—' }}</span>
                                @if ($log->location_source === 'browser')
                                    <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200" title="{{ $log->locationSummary() }} (Browser GPS)">
                                        <x-ui.icon name="map-pin" class="h-3 w-3 text-emerald-600" />
                                        GPS
                                    </span>
                                @elseif ($log->location_source === 'ip')
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium bg-neutral-100 text-neutral-600" title="{{ $log->locationSummary() }}">
                                        IP
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1.5">
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="inline-flex items-center text-xs font-semibold text-primary-700 hover:text-primary-800 hover:underline">
                                    Details &rarr;
                                </a>
                            </div>
                        </x-ui.table.td>
                    </x-ui.table.row>

                    @if ($hasDetails)
                        <tr x-show="expanded['{{ $log->id }}']" x-cloak class="border-b border-neutral-200 bg-neutral-50/80">
                            <td colspan="7" class="px-6 py-3.5">
                                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs space-y-3">
                                    <div class="flex items-center justify-between border-b border-neutral-100 pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-primary-50 text-primary-700">
                                                <x-ui.icon name="clipboard-document-list" class="h-3.5 w-3.5" />
                                            </span>
                                            <h4 class="text-xs font-semibold text-neutral-900">Recorded Audit Details</h4>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-[11px] font-mono text-neutral-400">Log ID: #{{ $log->id }}</span>
                                            <button
                                                type="button"
                                                x-on:click="expanded['{{ $log->id }}'] = false"
                                                class="text-xs font-medium text-neutral-500 hover:text-neutral-800 focus:outline-none"
                                            >
                                                Close
                                            </button>
                                        </div>
                                    </div>

                                    @if ($hasBoth)
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="rounded-lg border border-neutral-200 bg-neutral-50/50 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-2.5">Before (Previous Values)</p>
                                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                    @foreach ($log->old_values as $field => $value)
                                                        <div class="rounded-md bg-white p-2 border border-neutral-100">
                                                            <dt class="font-medium text-neutral-500">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                                            <dd class="mt-1 font-mono text-neutral-800 break-words">
                                                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                                            </dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </div>

                                            <div class="rounded-lg border border-primary-200 bg-primary-50/20 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 mb-2.5">After (New Values)</p>
                                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                    @foreach ($log->new_values as $field => $value)
                                                        <div class="rounded-md bg-white p-2 border border-primary-100">
                                                            <dt class="font-medium text-neutral-500">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                                            <dd class="mt-1 font-mono text-neutral-800 break-words">
                                                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                                            </dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </div>
                                        </div>
                                    @else
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-xs font-medium text-neutral-500">
                                                    {{ $isSingleAfter ? 'Recorded attributes (After):' : 'Prior attributes snapshot (Before):' }}
                                                </span>
                                                <span class="text-[11px] text-neutral-400">{{ count($singleValues) }} fields</span>
                                            </div>
                                            <dl class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 text-xs">
                                                @foreach ($singleValues as $field => $value)
                                                    <div class="rounded-lg border border-neutral-100 bg-neutral-50/80 p-2.5">
                                                        <dt class="text-[11px] font-medium text-neutral-500">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                                        <dd class="mt-1 font-semibold text-neutral-900 break-words">
                                                            @if ($field === 'filesize' && is_numeric($value))
                                                                {{ number_format($value) }} bytes <span class="text-neutral-500 font-normal">({{ round($value / 1024, 1) }} KB)</span>
                                                            @else
                                                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                                            @endif
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <x-ui.table.empty
                        :colspan="7"
                        icon="clipboard-document-list"
                        title="No activity found"
                        message="Important user and authentication activity will appear here." />
                @endforelse
            </tbody>
        </x-ui.table>

        @if ($logs->hasPages())
            <x-slot:footer>
                {{ $logs->links() }}
            </x-slot:footer>
        @endif
    </x-ui.card>
</x-app-layout>
