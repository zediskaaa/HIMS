<x-app-layout>
    <x-ui.page-header
        title="Audit Trail"
        subtitle="Append-only history of user administration and authentication activity."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Audit Trail' => null]" />

    <x-ui.card
        title="Find Activity"
        subtitle="Search by actor, employee ID, target, email, action, description, or IP address."
        class="relative z-20 !overflow-visible">
        <form id="audit-log-filters" method="GET" action="{{ route('admin.audit-logs.index') }}"
              class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
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
                    placeholder="e.g. Juan, EMP-0144, 127.0.0.1"
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

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" icon="magnifying-glass" data-loading-text="Loading activity...">Filter</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="secondary" :href="route('admin.audit-logs.index')">Clear</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card
        title="Activity"
        :subtitle="$logs->total().' '.\Illuminate\Support\Str::plural('record', $logs->total()).' - Asia/Manila (PHT)'"
        :padding="false">
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Performed By</x-ui.table.th>
                <x-ui.table.th>Action</x-ui.table.th>
                <x-ui.table.th>Target</x-ui.table.th>
                <x-ui.table.th>Description</x-ui.table.th>
                <x-ui.table.th>Date &amp; Time</x-ui.table.th>
                <x-ui.table.th>IP Address</x-ui.table.th>
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
                    @endphp
                    <x-ui.table.row>
                        <x-ui.table.td>
                            <span class="font-medium text-neutral-900">{{ $log->actor_name }}</span>
                            @if ($log->actor_employee_id)
                                <span class="block font-mono text-xs text-neutral-500">
                                    {{ $log->actor_employee_id }}
                                </span>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <x-ui.badge :variant="$variant">{{ $log->action->label() }}</x-ui.badge>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <span class="text-neutral-800">{{ $log->target_name ?? '-' }}</span>
                            @if ($log->target_id)
                                <span class="block text-xs text-neutral-400">ID {{ $log->target_id }}</span>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <p class="min-w-72 max-w-xl text-neutral-700">{{ $log->description }}</p>
                            @if ($log->old_values || $log->new_values)
                                <details class="mt-1.5 text-xs text-neutral-500">
                                    <summary class="cursor-pointer font-medium text-primary-700 hover:underline">
                                        View recorded details
                                    </summary>
                                    <div class="mt-2 grid gap-2 rounded-md border border-neutral-200 bg-neutral-50 p-2 sm:grid-cols-2">
                                        @if ($log->old_values)
                                            <div>
                                                <p class="font-semibold text-neutral-600">Before</p>
                                                @foreach ($log->old_values as $field => $value)
                                                    <p><span class="font-medium">{{ \Illuminate\Support\Str::headline($field) }}:</span>
                                                        {{ filled($value) ? $value : '-' }}</p>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($log->new_values)
                                            <div>
                                                <p class="font-semibold text-neutral-600">After</p>
                                                @foreach ($log->new_values as $field => $value)
                                                    <p><span class="font-medium">{{ \Illuminate\Support\Str::headline($field) }}:</span>
                                                        {{ filled($value) ? $value : '-' }}</p>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </x-ui.table.td>

                        <x-ui.table.td muted>
                            <time datetime="{{ $log->created_at->toIso8601String() }}" class="whitespace-nowrap">
                                {{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y, g:i A') }}
                                <span class="block text-xs text-neutral-400">PHT (UTC+8)</span>
                            </time>
                        </x-ui.table.td>

                        <x-ui.table.td muted>
                            <span class="font-mono text-xs">{{ $log->ip_address ?? '-' }}</span>
                        </x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty
                        :colspan="6"
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
