<section class="py-4" x-data="{ enabled: @js((bool) $user->session_timeout_reminder_enabled), original: @js((bool) $user->session_timeout_reminder_enabled) }"
         x-on:open-modal.window="if ($event.detail === 'configure-session-reminder') enabled = original">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Session Timeout Reminder') }}</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ __('Warn before an inactive session expires.') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <x-ui.badge :status="$user->session_timeout_reminder_enabled ? 'active' : 'inactive'" dot>{{ $user->session_timeout_reminder_enabled ? 'Enabled' : 'Disabled' }}</x-ui.badge>
            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'configure-session-reminder')">{{ $user->session_timeout_reminder_enabled ? __('Manage') : __('Configure') }}</x-ui.button>
        </div>
    </div>

    <div x-data @if ($errors->has('session_timeout_reminder_enabled')) x-init="$nextTick(() => $dispatch('open-modal', 'configure-session-reminder'))" @endif>
    <x-ui.modal name="configure-session-reminder" :title="__('Session Timeout Reminder')" maxWidth="md">
        <form method="post" action="{{ route('profile.session-timeout-reminder.update') }}" class="space-y-4">
            @csrf
            @method('patch')
            <input type="hidden" name="session_timeout_reminder_enabled" value="0">

            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/60">
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Show inactivity warning</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-600 dark:text-neutral-300">Show the countdown dialog and play its alert sound before the session expires. Turning this off does not disable the secure automatic logout.</span>
                </span>
                <input type="checkbox" name="session_timeout_reminder_enabled" value="1" x-model="enabled" @checked($user->session_timeout_reminder_enabled)
                       class="shrink-0 rounded border-neutral-400 text-primary-600 focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-900">
            </label>

            <x-input-error :messages="$errors->get('session_timeout_reminder_enabled')" class="mt-1" />

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'configure-session-reminder')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" data-loading-text="Saving reminder setting...">{{ __('Save reminder setting') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    </div>
</section>
