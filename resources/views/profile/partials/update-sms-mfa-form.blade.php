@php($smsMobileRequired = ! $user->sms_mfa_enabled && preg_match('/^09[0-9]{9}$/D', (string) $user->phone) !== 1)
<section class="py-4 first:pt-0" x-data="{ enabled: @js((bool) $user->sms_mfa_enabled), original: @js((bool) $user->sms_mfa_enabled), password: '' }"
         x-on:open-modal.window="if ($event.detail === 'configure-sms-mfa') { enabled = original; password = ''; }"
         x-on:close-modal.window="if ($event.detail === 'configure-sms-mfa') { enabled = original; password = ''; }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">SMS Authentication</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">Require a one-time SMS code during sign-in.</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @if ($smsMobileRequired)
                <x-ui.badge status="action_required" dot>Setup required</x-ui.badge>
            @else
                <x-ui.badge :status="$user->sms_mfa_enabled ? 'active' : 'inactive'" dot>{{ $user->sms_mfa_enabled ? 'Enabled' : 'Disabled' }}</x-ui.badge>
            @endif
            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'configure-sms-mfa')">{{ $user->sms_mfa_enabled ? __('Manage') : __('Configure') }}</x-ui.button>
        </div>
    </div>
    @if ($smsMobileRequired)
        <p class="mt-2 text-xs leading-5 text-danger-700 dark:text-danger-300">A valid registered mobile number is required. Ask an administrator to update your contact number.</p>
    @endif

    <div x-data @if ($errors->smsMfa->any()) x-init="$nextTick(() => $dispatch('open-modal', 'configure-sms-mfa'))" @endif>
    <x-ui.modal name="configure-sms-mfa" :title="__('SMS Authentication')" maxWidth="md">
        @if ($errors->smsMfa->any())
            <x-ui.alert variant="danger" title="Could not update SMS authentication" class="mb-4">{{ $errors->smsMfa->first() }}</x-ui.alert>
        @endif

        <form method="post" action="{{ route('profile.sms-mfa.update') }}" class="space-y-4"
              data-confirm-sms-mfa data-original-sms-mfa="{{ $user->sms_mfa_enabled ? '1' : '0' }}">
            @csrf
            @method('patch')
            <input type="hidden" name="sms_mfa_enabled" value="0">
            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/60">
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Require SMS verification</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-600 dark:text-neutral-300">SMS codes are sent to your registered mobile number. Keep that number current to receive sign-in codes.</span>
                </span>
                <input type="checkbox" name="sms_mfa_enabled" value="1" x-model="enabled" @checked($user->sms_mfa_enabled)
                       class="shrink-0 rounded border-neutral-400 text-primary-600 focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-900">
            </label>
            <div>
                <x-input-label for="sms-current-password" value="Current password" class="text-neutral-700 dark:text-neutral-300" />
                <x-text-input id="sms-current-password" name="current_password" type="password" required autocomplete="off"
                              x-model="password"
                              data-lpignore="true" data-1p-ignore="true" data-bwignore="true"
                              style="-webkit-text-security: disc; text-security: disc;" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->smsMfa->get('current_password')" class="mt-2" />
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'configure-sms-mfa')">Cancel</x-ui.button>
                <x-ui.button type="submit" x-bind:disabled="enabled === original || password.trim() === ''" data-loading-text="Saving...">Save SMS setting</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    </div>
</section>
