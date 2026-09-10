<x-app-layout>
    @php($displayTime = $log->displayTimestamp())

    <x-ui.page-header
        :title="$log->action->label()"
        subtitle="Read-only audit event details."
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Audit Trail' => route('admin.audit-logs.index'),
            'Event Details' => null,
        ]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.audit-logs.index')" icon="arrow-left">
                Back to Audit Trail
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid min-w-0 gap-6 lg:grid-cols-3">
        <x-ui.card title="Event" class="min-w-0 lg:col-span-2">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Description</dt>
                    <dd class="mt-1 break-words text-sm text-neutral-900">{{ $log->description }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Outcome</dt>
                    <dd class="mt-1"><x-ui.badge :variant="($log->outcome ?? 'success') === 'success' ? 'success' : 'danger'">{{ \Illuminate\Support\Str::headline($log->outcome ?? 'success') }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Category</dt>
                    <dd class="mt-1 text-sm text-neutral-900">{{ $log->event_category ?? $log->action->category() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Module</dt>
                    <dd class="mt-1 text-sm text-neutral-900">{{ $log->module ?? $log->action->module() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Local date and time</dt>
                    <dd class="mt-1 text-sm text-neutral-900">{{ $displayTime->format('F j, Y, g:i:s A') }} {{ $log->displayTimezoneLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Authoritative UTC time</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-neutral-700">{{ $log->authoritativeTimestamp()->format('Y-m-d H:i:s.u') }} UTC</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Event ID</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-neutral-700">{{ $log->event_id ?? 'Legacy event' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Correlation ID</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-neutral-700">{{ $log->correlation_id ?? 'Not recorded for legacy event' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Context" class="min-w-0">
            <dl class="space-y-4">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Performed by</dt><dd class="mt-1 break-words text-sm text-neutral-900">{{ $log->actor_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Role snapshot</dt><dd class="mt-1 text-sm text-neutral-900">{{ $log->actor_role ? (\App\Enums\UserRole::tryFrom($log->actor_role)?->label() ?? \Illuminate\Support\Str::headline($log->actor_role)) : 'Not recorded' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Source</dt><dd class="mt-1 text-sm text-neutral-900">{{ \Illuminate\Support\Str::headline($log->source ?? 'legacy') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Target</dt><dd class="mt-1 break-words text-sm text-neutral-900">{{ $log->target_reference ?? $log->target_name ?? 'None' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">IP address</dt><dd class="mt-1 break-all font-mono text-xs text-neutral-700">{{ $log->ip_address ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Device context</dt><dd class="mt-1 break-words text-xs text-neutral-700">{{ $log->user_agent ?? 'Not recorded' }}</dd></div>
            </dl>
        </x-ui.card>
    </div>

    @if ($log->business_reason)
        <x-ui.card title="Business Reason">
            <p class="whitespace-pre-wrap break-words text-sm text-neutral-700">{{ $log->business_reason }}</p>
        </x-ui.card>
    @endif

    @if ($log->old_values || $log->new_values)
        <div class="grid min-w-0 gap-6 lg:grid-cols-2">
            <x-ui.card title="Before" class="min-w-0">
                <pre class="max-w-full overflow-x-auto whitespace-pre-wrap break-words text-xs text-neutral-700">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No prior values recorded.' }}</pre>
            </x-ui.card>
            <x-ui.card title="After" class="min-w-0">
                <pre class="max-w-full overflow-x-auto whitespace-pre-wrap break-words text-xs text-neutral-700">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No new values recorded.' }}</pre>
            </x-ui.card>
        </div>
    @endif
</x-app-layout>
