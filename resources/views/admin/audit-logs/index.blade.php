<x-app-layout>
    <x-ui.page-header
        title="Audit Trail"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Audit Trail' => null]" />

    @php
        $activeFilterCount = count(array_filter($filters ?? []));
        $hasActiveFilters = $activeFilterCount > 0;
    @endphp

    <div x-data="{ open: @js($hasActiveFilters) }" class="relative z-20">
        <x-ui.card
            title="Find Activity"
            subtitle="Use controlled filters for known values and search for descriptive activity."
            :padding="false"
            class="relative z-20 !overflow-visible">
            <x-slot:actions>
                <button
                    type="button"
                    x-on:click="open = !open"
                    class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 hover:border-neutral-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all cursor-pointer"
                    aria-controls="audit-filter-panel"
                    x-bind:aria-expanded="open"
                >
                    <x-ui.icon name="adjustments-horizontal" class="h-4 w-4 text-neutral-500" />
                    <span>Filters</span>
                    @if ($hasActiveFilters)
                        <span class="inline-flex items-center justify-center rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold text-primary-800">
                            {{ $activeFilterCount }}
                        </span>
                    @endif
                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''" />
                </button>
            </x-slot:actions>

            <div
                id="audit-filter-panel"
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="p-4 sm:p-5"
            >
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
            </div>
        </x-ui.card>
    </div>

    <x-ui.card
        class="audit-logs-table [&_.hims-table-scroll]:overflow-x-hidden [&_.hims-table-scroll]:[scrollbar-width:none] [&_.hims-table-scroll::-webkit-scrollbar]:hidden"
        title="Activity"
        :subtitle="$logs->total().' '.\Illuminate\Support\Str::plural('record', $logs->total()).' - '.config('app.timezone').' (PHT)'"
        :padding="false">
        <x-ui.table class="audit-logs-table">
            <x-ui.table.head>
                <x-ui.table.th>Performed By</x-ui.table.th>
                <x-ui.table.th>Action</x-ui.table.th>
                <x-ui.table.th>Module</x-ui.table.th>
                <x-ui.table.th>Target</x-ui.table.th>
                <x-ui.table.th>Description</x-ui.table.th>
                <x-ui.table.th>Date &amp; Time</x-ui.table.th>
                <x-ui.table.th class="text-right">Actions</x-ui.table.th>
            </x-ui.table.head>
            <tbody>
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
                    @endphp
                    <x-ui.table.row class="transition-colors">
                        <x-ui.table.td class="whitespace-nowrap">
                            <div class="font-medium text-neutral-900 dark:text-neutral-100 truncate max-w-[12rem]">{{ $log->displayActorName() }}</div>
                            <div class="mt-0.5 flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                @if ($log->actor_employee_id)
                                    <span class="font-mono">{{ $log->actor_employee_id }}</span>
                                @endif
                                @if ($log->actor_employee_id && $log->actor_role)
                                    <span class="text-neutral-300 dark:text-neutral-600">&bull;</span>
                                @endif
                                @if ($log->actor_role)
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ \Illuminate\Support\Str::headline($log->actor_role) }}
                                    </span>
                                @endif
                            </div>
                        </x-ui.table.td>

                        <x-ui.table.td class="whitespace-nowrap">
                            <x-ui.badge :variant="$variant">{{ $log->action->label() }}</x-ui.badge>
                            <span class="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">{{ $log->event_category ?? $log->action->category() }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td class="whitespace-nowrap">
                            <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $log->module ?? $log->action->module() }}</span>
                            <span class="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">{{ \Illuminate\Support\Str::headline($log->source ?? 'user') }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <span class="font-medium text-neutral-800 dark:text-neutral-200 truncate block max-w-[10rem]">{{ $log->target_name ?? '—' }}</span>
                            <span class="mt-0.5 block text-xs text-neutral-400 font-mono">{{ $log->target_id ? 'ID ' . $log->target_id : '—' }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <div class="max-w-xs sm:max-w-sm lg:max-w-md min-w-0">
                                <p class="text-xs text-neutral-700 dark:text-neutral-300 line-clamp-2 leading-relaxed" title="{{ $log->description }}">{{ $log->description }}</p>
                            </div>
                        </x-ui.table.td>

                        <x-ui.table.td muted class="whitespace-nowrap">
                            @php $displayTime = $log->displayTimestamp(); @endphp
                            <time datetime="{{ $log->authoritativeTimestamp()->toIso8601String() }}" class="block text-xs font-medium text-neutral-900 dark:text-neutral-100" title="{{ $displayTime->format('F j, Y, g:i:s A').' '.$log->displayTimezoneLabel() }}">
                                {{ $displayTime->format('M d, Y, g:i:s A') }}
                            </time>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span class="text-xs font-normal text-neutral-400">{{ $log->displayTimezoneLabel() }}</span>
                                <x-ui.badge :variant="($log->outcome ?? 'success') === 'success' ? 'success' : 'danger'">
                                    {{ \Illuminate\Support\Str::headline($log->outcome ?? 'success') }}
                                </x-ui.badge>
                            </div>
                        </x-ui.table.td>

                        <x-ui.table.td class="whitespace-nowrap text-right">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                @if ($hasDetails)
                                    <button
                                        type="button"
                                        x-on:click="$dispatch('open-modal', 'audit-log-details-{{ $log->id }}')"
                                        class="inline-flex items-center gap-1 rounded-md border border-neutral-200 bg-white px-2 py-1 text-xs font-medium text-neutral-700 shadow-2xs transition-all duration-150 hover:border-primary-300 hover:bg-primary-50/70 hover:text-primary-700 hover:shadow-xs dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                        title="View recorded diff details"
                                    >
                                        <x-ui.icon name="document-text" class="h-3.5 w-3.5 text-neutral-400" />
                                        <span>Changes</span>
                                    </button>
                                @endif

                                <a href="{{ route('admin.audit-logs.show', $log) }}"
                                   class="inline-flex items-center justify-center rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-2xs transition-all duration-150 hover:border-primary-300 hover:bg-primary-50/70 hover:text-primary-700 hover:shadow-xs dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                   title="View full audit log details">
                                    Details
                                </a>
                            </div>
                        </x-ui.table.td>
                    </x-ui.table.row>
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

    @foreach ($logs as $log)
        @if (!empty($log->old_values) || !empty($log->new_values))
            @php
                $hasBoth = !empty($log->old_values) && !empty($log->new_values);
                $singleValues = !empty($log->new_values) ? $log->new_values : ($log->old_values ?? []);
                $isSingleAfter = !empty($log->new_values);
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
            @endphp

            <x-ui.modal name="audit-log-details-{{ $log->id }}" title="Recorded Audit Details #{{ $log->id }}" maxWidth="3xl">
                <div class="space-y-5 pb-2">
                    {{-- Event Context Summary --}}
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/80 p-4 dark:border-neutral-800 dark:bg-neutral-800/50 shadow-2xs">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 text-xs">
                            <div>
                                <span class="block text-[11px] font-medium text-neutral-500 dark:text-neutral-400">Action</span>
                                <span class="mt-1 inline-block"><x-ui.badge :variant="$variant">{{ $log->action->label() }}</x-ui.badge></span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-medium text-neutral-500 dark:text-neutral-400">Module</span>
                                <span class="mt-1 block font-semibold text-neutral-800 dark:text-neutral-200">{{ $log->module ?? $log->action->module() }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-medium text-neutral-500 dark:text-neutral-400">Performed By</span>
                                <span class="mt-1 block font-semibold text-neutral-800 dark:text-neutral-200">{{ $log->displayActorName() }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-medium text-neutral-500 dark:text-neutral-400">Timestamp</span>
                                <span class="mt-1 block font-medium text-neutral-700 dark:text-neutral-300">{{ $log->displayTimestamp()->format('M d, Y, g:i:s A') }}</span>
                            </div>
                        </div>
                        @if ($log->description)
                            <div class="mt-3.5 pt-3 border-t border-neutral-200/70 dark:border-neutral-700/60 text-xs">
                                <span class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400">Description:</span>
                                <p class="mt-1 text-neutral-800 dark:text-neutral-200 leading-relaxed">{{ $log->description }}</p>
                            </div>
                        @endif
                        @if ($place = ($log->placeName() ?? $log->locationSummary()))
                            <div class="mt-3 pt-2.5 border-t border-neutral-200/70 dark:border-neutral-700/60 text-xs flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                                <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-primary-500 shrink-0" />
                                <span><strong class="text-neutral-700 dark:text-neutral-300">Location:</strong> {{ $place }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($hasBoth)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 shadow-2xs">
                                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 mb-3">Before (Previous Values)</p>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                                    @foreach ($log->old_values as $field => $value)
                                        <div class="rounded-lg bg-white dark:bg-neutral-900 p-4 border border-neutral-200 dark:border-neutral-700/80 shadow-2xs min-h-[5rem] flex flex-col justify-between">
                                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 truncate" title="{{ \Illuminate\Support\Str::headline($field) }}">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                            <dd class="mt-2 font-mono text-xs font-bold text-neutral-800 dark:text-neutral-200 break-words leading-relaxed">
                                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>

                            <div class="rounded-xl border border-primary-200 bg-primary-50/20 p-4 dark:border-primary-900/60 dark:bg-primary-950/20 shadow-2xs">
                                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400 mb-3">After (New Values)</p>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                                    @foreach ($log->new_values as $field => $value)
                                        <div class="rounded-lg bg-white dark:bg-neutral-900 p-4 border border-primary-100 dark:border-primary-900/50 shadow-2xs min-h-[5rem] flex flex-col justify-between">
                                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 truncate" title="{{ \Illuminate\Support\Str::headline($field) }}">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                            <dd class="mt-2 font-mono text-xs font-bold text-neutral-800 dark:text-neutral-200 break-words leading-relaxed">
                                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (filled($value) ? $value : '—') }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="mb-3.5 flex items-center justify-between">
                                <span class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    {{ $isSingleAfter ? 'Recorded attributes (After):' : 'Prior attributes snapshot (Before):' }}
                                </span>
                                <span class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400">{{ count($singleValues) }} {{ \Illuminate\Support\Str::plural('field', count($singleValues)) }}</span>
                            </div>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs">
                                @foreach ($singleValues as $field => $value)
                                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-700/80 dark:bg-neutral-800/70 shadow-2xs flex flex-col justify-between min-h-[5rem]">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 truncate" title="{{ \Illuminate\Support\Str::headline($field) }}">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                        <dd class="mt-2 font-mono text-xs font-bold text-neutral-900 dark:text-neutral-100 break-words leading-relaxed">
                                            @if ($field === 'filesize' && is_numeric($value))
                                                {{ number_format($value) }} bytes <span class="text-neutral-500 dark:text-neutral-400 font-normal">({{ round($value / 1024, 1) }} KB)</span>
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

                <x-slot:footer>
                    <div class="flex w-full items-center justify-between">
                        <a href="{{ route('admin.audit-logs.show', $log) }}"
                           class="group inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs transition-all duration-150 hover:border-primary-300 hover:bg-neutral-50 hover:text-primary-700 hover:shadow-xs dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800">
                            <x-ui.icon name="arrow-top-right-on-square" class="w-3.5 h-3.5 text-neutral-400 group-hover:text-primary-600 transition-colors" />
                            <span>Full Log Details</span>
                        </a>
                        <x-ui.button variant="secondary" size="sm" type="button" x-on:click="open = false">
                            Close
                        </x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    @endforeach
</x-app-layout>
