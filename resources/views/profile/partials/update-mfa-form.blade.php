<section class="py-4" x-data="{ enabled: @js((bool) $user->mfa_enabled), original: @js((bool) $user->mfa_enabled) }"
         x-on:open-modal.window="if ($event.detail === 'configure-email-mfa') enabled = original">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Email Multi-Factor Authentication') }}</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ __('Require a one-time email code after your password is accepted.') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <x-ui.badge :status="$user->mfa_enabled ? 'active' : 'inactive'" dot>{{ $user->mfa_enabled ? 'Enabled' : 'Disabled' }}</x-ui.badge>
            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'configure-email-mfa')">{{ $user->mfa_enabled ? __('Manage') : __('Configure') }}</x-ui.button>
        </div>
    </div>

    <div x-data @if ($errors->has('mfa_enabled')) x-init="$nextTick(() => $dispatch('open-modal', 'configure-email-mfa'))" @endif>
    <x-ui.modal name="configure-email-mfa" :title="__('Email Multi-Factor Authentication')" maxWidth="md">
        <form method="post" action="{{ route('profile.mfa.update') }}" class="space-y-4"
              data-confirm-mfa data-original-mfa="{{ $user->mfa_enabled ? '1' : '0' }}">
            @csrf
            @method('patch')
            <input type="hidden" name="mfa_enabled" value="0">

            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/60">
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Email verification code</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-600 dark:text-neutral-300">Codes expire quickly, can be used only once, and are sent to your registered email address.</span>
                </span>
                <input type="checkbox" name="mfa_enabled" value="1" x-model="enabled" @checked($user->mfa_enabled)
                       class="shrink-0 rounded border-neutral-400 text-primary-600 focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-900">
            </label>

            <x-input-error :messages="$errors->get('mfa_enabled')" class="mt-1" />

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'configure-email-mfa')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" data-loading-text="Saving MFA setting...">{{ __('Save MFA setting') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    </div>
</section>
