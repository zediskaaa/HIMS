@php
    use App\Enums\RecoveryAttemptOutcome;
    use App\Models\SystemRecoveryRecord;

    $tz = config('app.timezone');
    $tzLabel = $tz === 'Asia/Manila' ? 'PHT' : $tz;
    $details = $record->technical_details ?? [];
    $context = is_array($details['context'] ?? null) ? $details['context'] : [];
    $incidentRef = $record->reference_id ?: $record->error_id;
@endphp

<x-app-layout>
    <x-ui.page-header
        title="Incident {{ $record->error_id }}"
        :subtitle="$record->error_summary"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Recovery Center' => route('super-admin.recovery.index'),
            $record->error_id => null,
        ]"
    >
        <x-slot:actions>
            <a
                href="{{ route('super-admin.recovery.index') }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
            >
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                <span>Back to Incidents</span>
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Current state, stated plainly. The badge is the record's own status; the
         sentence underneath explains what that status means for this incident. --}}
    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-ui.badge :status="$record->status->value" dot>{{ $record->status->label() }}</x-ui.badge>
                    <x-ui.badge :status="$record->failure_type->value">{{ $record->failure_type->label() }}</x-ui.badge>
                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                        {{ $record->module }}
                    </span>
                    <span class="font-mono text-[11px] text-neutral-500 dark:text-neutral-400">{{ $record->operation }}</span>
                </div>

                <p class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">
                    {{ $record->retry_count }} of {{ SystemRecoveryRecord::MAX_RECOVERY_ATTEMPTS }} recovery attempts used.
                    Recorded {{ $record->created_at->setTimezone($tz)->format('M d, Y g:i A') }} {{ $tzLabel }}.
                </p>

                @if ($record->retryBlockedReason())
                    <p class="mt-2 flex items-start gap-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                        <x-ui.icon name="information-circle" class="mt-px h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-500" />
                        <span>{{ $record->retryBlockedReason() }}</span>
                    </p>
                @endif
            </div>

            @if ($record->status === \App\Enums\RecoveryStatus::RecoveryFailed && $record->last_attempt_error)
                <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-xs text-danger-800 sm:max-w-sm dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                    <p class="font-semibold">Most recent attempt did not succeed</p>
                    <p class="mt-1 break-words">{{ $record->last_attempt_error }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- The failure exactly as recorded when it happened. Nothing on this
                 card is ever rewritten by a recovery attempt. --}}
            <x-ui.card title="Original failure" subtitle="Captured at the moment the operation failed and preserved unchanged for audit.">
                <dl class="space-y-3 text-xs">
                    <div>
                        <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Failure reason</dt>
                        <dd class="mt-1 break-words text-neutral-800 dark:text-neutral-200">{{ $record->error_summary }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Failure type</dt>
                            <dd class="mt-1 text-neutral-800 dark:text-neutral-200">{{ $record->failure_type->label() }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Module</dt>
                            <dd class="mt-1 text-neutral-800 dark:text-neutral-200">{{ $record->module }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Operation</dt>
                            <dd class="mt-1 font-mono text-neutral-800 dark:text-neutral-200">{{ $record->operation }}</dd>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Affected resource</dt>
                            <dd class="mt-1 break-words text-neutral-800 dark:text-neutral-200">{{ $record->affected_resource ?: 'Not identified' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Reference / operation ID</dt>
                            <dd class="mt-1 break-all font-mono text-neutral-800 dark:text-neutral-200">{{ $incidentRef }}</dd>
                        </div>
                    </div>

                    @if ($record->exception_class || $details)
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-800/40">
                            <p class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Diagnostics</p>
                            <dl class="mt-2 space-y-1.5">
                                @if ($record->exception_class)
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-neutral-500 dark:text-neutral-400">Exception</dt>
                                        <dd class="break-all font-mono text-neutral-800 dark:text-neutral-200">{{ $record->exception_class }}</dd>
                                    </div>
                                @endif

                                @if (! empty($details['file']))
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-neutral-500 dark:text-neutral-400">Location</dt>
                                        <dd class="break-all font-mono text-neutral-800 dark:text-neutral-200">
                                            {{ $details['file'] }}@if (! empty($details['line'])):{{ $details['line'] }}@endif
                                        </dd>
                                    </div>
                                @endif

                                @if (array_key_exists('code', $details) && $details['code'] !== null && $details['code'] !== '' && $details['code'] !== 0)
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-neutral-500 dark:text-neutral-400">Driver code</dt>
                                        <dd class="break-all font-mono text-neutral-800 dark:text-neutral-200">{{ $details['code'] }}</dd>
                                    </div>
                                @endif

                                @if (! empty($details['method']) || ! empty($details['url']))
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-neutral-500 dark:text-neutral-400">Request</dt>
                                        <dd class="break-all font-mono text-neutral-800 dark:text-neutral-200">
                                            {{ trim(($details['method'] ?? '') . ' ' . ($details['url'] ?? '')) }}
                                        </dd>
                                    </div>
                                @endif

                                @if ($record->ip_address)
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-neutral-500 dark:text-neutral-400">Source IP</dt>
                                        <dd class="break-all font-mono text-neutral-800 dark:text-neutral-200">{{ $record->ip_address }}</dd>
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-x-2">
                                    <dt class="text-neutral-500 dark:text-neutral-400">Auto-handling</dt>
                                    <dd class="text-neutral-800 dark:text-neutral-200">
                                        {{ $record->strategy_applied }}
                                        @if ($record->is_retryable)
                                            &middot; a safe automated retry exists
                                        @else
                                            &middot; no automated retry exists
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            {{-- Stack traces are never stored; the full trace lives in the
                                 server log, which is where an engineer should read it. --}}
                            <p class="mt-2 text-[11px] text-neutral-400 dark:text-neutral-500">
                                Values shown here are redacted and stored without stack traces. Full technical detail is in the application log under this incident ID.
                            </p>
                        </div>
                    @endif

                    @if ($context !== [])
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Operation context</dt>
                            <dd class="mt-1">
                                <dl class="divide-y divide-neutral-100 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                                    @foreach ($context as $key => $value)
                                        <div class="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:items-baseline sm:gap-3">
                                            <dt class="font-mono text-[11px] text-neutral-500 sm:w-48 sm:shrink-0 dark:text-neutral-400">{{ $key }}</dt>
                                            <dd class="break-words font-mono text-[11px] text-neutral-800 dark:text-neutral-200">
                                                {{ is_scalar($value) ? $value : json_encode($value) }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            {{-- Append-only. Every attempt is kept, including the ones that failed. --}}
            <x-ui.card :padding="false">
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Recovery attempt history</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Every recovery action taken against this incident, in order. Earlier attempts are never overwritten.
                    </p>
                </x-slot:header>

                @if ($record->attempts->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                            <x-ui.icon name="clock" class="h-5 w-5" />
                        </div>
                        <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No recovery attempt yet</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            @if ($record->canRetry())
                                This incident failed once and has not been retried. Run a recovery attempt to see whether the operation succeeds now.
                            @else
                                Nothing has been run against this incident. It is recorded here for review and audit.
                            @endif
                        </p>
                    </div>
                @else
                    <ol class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($record->attempts as $attempt)
                            @php
                                $tone = match ($attempt->outcome) {
                                    RecoveryAttemptOutcome::Succeeded => 'success',
                                    RecoveryAttemptOutcome::Failed => 'danger',
                                    RecoveryAttemptOutcome::Dispatched => 'primary',
                                    RecoveryAttemptOutcome::Skipped => 'neutral',
                                };
                            @endphp
                            <li class="flex gap-3 px-4 py-4 sm:px-5">
                                <div class="flex flex-col items-center">
                                    <span @class([
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                                        'bg-success-50 text-success-600 dark:bg-emerald-950/60 dark:text-emerald-400' => $tone === 'success',
                                        'bg-danger-50 text-danger-600 dark:bg-rose-950/60 dark:text-rose-400' => $tone === 'danger',
                                        'bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400' => $tone === 'primary',
                                        'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400' => $tone === 'neutral',
                                    ])>
                                        <x-ui.icon :name="match ($attempt->outcome) {
                                            RecoveryAttemptOutcome::Succeeded => 'check',
                                            RecoveryAttemptOutcome::Failed => 'x-mark',
                                            RecoveryAttemptOutcome::Dispatched => 'arrow-path',
                                            RecoveryAttemptOutcome::Skipped => 'minus',
                                        }" class="h-4 w-4" />
                                    </span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                                            Attempt #{{ $attempt->attempt_number }}
                                        </span>
                                        <x-ui.badge :variant="$tone">{{ $attempt->outcome->label() }}</x-ui.badge>
                                        @if ($attempt->handler)
                                            <span class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ $attempt->handler->label() }}</span>
                                        @endif
                                        @if ($attempt->duration_ms !== null)
                                            <span class="text-[11px] text-neutral-400 dark:text-neutral-500">{{ number_format($attempt->duration_ms) }} ms</span>
                                        @endif
                                    </div>

                                    <p class="mt-1 break-words text-xs text-neutral-700 dark:text-neutral-300">{{ $attempt->message }}</p>

                                    <p class="mt-1.5 text-[11px] text-neutral-500 dark:text-neutral-400">
                                        {{ $attempt->actor_snapshot ?: 'System / Automated' }}
                                        &middot;
                                        <time datetime="{{ $attempt->created_at->toIso8601String() }}">
                                            {{ $attempt->created_at->setTimezone($tz)->format('M d, Y g:i:s A') }} {{ $tzLabel }}
                                        </time>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            {{-- Exactly the actions that are valid for this record's current state. --}}
            <x-ui.card title="Recovery actions">
                @if ($errors->any())
                    <div class="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-xs text-danger-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        @if ($record->canRetry())
                            <form
                                method="POST"
                                action="{{ route('super-admin.recovery.retry', $record) }}"
                                data-confirm-title="Run recovery attempt"
                                data-confirm-message="HIMS will re-run the failed operation for incident {{ $record->error_id }} and record what it actually observed. This does not mark the incident recovered unless the operation genuinely succeeds. Continue?"
                                data-confirm-label="Run Recovery"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-success-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-success-500"
                                >
                                    <x-ui.icon name="arrow-path" class="h-4 w-4" />
                                    <span>Retry failed operation</span>
                                </button>
                            </form>
                            <p class="mt-2 text-[11px] text-neutral-500 dark:text-neutral-400">
                                Re-runs {{ $record->retry_handler->label() }}. {{ $record->attemptsRemaining() }}
                                {{ \Illuminate\Support\Str::plural('attempt', $record->attemptsRemaining()) }} remaining.
                            </p>
                        @else
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-800/40">
                                <p class="flex items-start gap-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    <x-ui.icon name="lock-closed" class="mt-px h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-500" />
                                    <span>{{ $record->retryBlockedReason() }}</span>
                                </p>
                            </div>
                        @endif
                    </div>

                    @if ($record->canBeClosed())
                        <form
                            method="POST"
                            action="{{ route('super-admin.recovery.resolve', $record) }}"
                            class="space-y-2 border-t border-neutral-200 pt-4 dark:border-neutral-800"
                            data-confirm-title="Close incident"
                            data-confirm-message="This closes incident {{ $record->error_id }} as resolved without running a retry. Use it only when you have verified the underlying problem was fixed. Continue?"
                            data-confirm-label="Close Incident"
                            data-confirm-variant="warning"
                        >
                            @csrf
                            <label for="resolution-notes" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                Close without retrying
                            </label>
                            <p class="text-[11px] text-neutral-500 dark:text-neutral-400">
                                Records that you verified the problem outside the automated path. No retry is executed and the original failure stays on the record.
                            </p>
                            <textarea
                                id="resolution-notes"
                                name="notes"
                                rows="3"
                                required
                                minlength="10"
                                maxlength="1000"
                                placeholder="What did you verify, and how?"
                                class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                            >{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="text-[11px] font-medium text-danger-700 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                            <button
                                type="submit"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                            >
                                <x-ui.icon name="check-badge" class="h-4 w-4" />
                                <span>Close as resolved</span>
                            </button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card title="Record">
                <dl class="space-y-3 text-xs">
                    <div>
                        <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Incident ID</dt>
                        <dd class="mt-0.5 break-all font-mono text-neutral-800 dark:text-neutral-200">{{ $record->error_id }}</dd>
                    </div>

                    <div>
                        <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Current status</dt>
                        <dd class="mt-0.5">
                            <x-ui.badge :status="$record->status->value" dot>{{ $record->status->label() }}</x-ui.badge>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Responsible</dt>
                        <dd class="mt-0.5 text-neutral-800 dark:text-neutral-200">{{ $record->user_snapshot ?: 'System / Automated' }}</dd>
                    </div>

                    <div>
                        <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Occurred</dt>
                        <dd class="mt-0.5 text-neutral-800 dark:text-neutral-200">
                            {{ $record->created_at->setTimezone($tz)->format('M d, Y g:i:s A') }} {{ $tzLabel }}
                        </dd>
                    </div>

                    @if ($record->last_retried_at)
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Last retried</dt>
                            <dd class="mt-0.5 text-neutral-800 dark:text-neutral-200">
                                {{ $record->last_retried_at->setTimezone($tz)->format('M d, Y g:i:s A') }} {{ $tzLabel }}
                            </dd>
                        </div>
                    @endif

                    @if ($record->last_attempt_outcome)
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Last attempt outcome</dt>
                            <dd class="mt-0.5 text-neutral-800 dark:text-neutral-200">{{ $record->last_attempt_outcome->label() }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($record->status->isTerminal())
                <x-ui.card title="Closure">
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Closed as</dt>
                            <dd class="mt-0.5">
                                <x-ui.badge :status="$record->status->value">{{ $record->status->label() }}</x-ui.badge>
                            </dd>
                        </div>

                        @if ($record->resolved_at)
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Closed at</dt>
                                <dd class="mt-0.5 text-neutral-800 dark:text-neutral-200">
                                    {{ $record->resolved_at->setTimezone($tz)->format('M d, Y g:i:s A') }} {{ $tzLabel }}
                                </dd>
                            </div>
                        @endif

                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Closed by</dt>
                            <dd class="mt-0.5 text-neutral-800 dark:text-neutral-200">
                                {{ $record->resolvedBy?->name ?? ($record->status === \App\Enums\RecoveryStatus::Recovered ? 'Recovery handler (automated)' : 'System / Automated') }}
                            </dd>
                        </div>

                        @if ($record->resolution_notes)
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Notes</dt>
                                <dd class="mt-0.5 break-words text-neutral-800 dark:text-neutral-200">{{ $record->resolution_notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
