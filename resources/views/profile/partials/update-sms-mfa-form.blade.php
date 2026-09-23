<section x-data="{ enabled: @js((bool) $user->sms_mfa_enabled), original: @js((bool) $user->sms_mfa_enabled) }">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">SMS Authentication</h2>
            <p class="mt-1 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                Require a one-time SMS code on future sign-ins. Your registered mobile number must remain current.
            </p>
        </div>
        <span class="rounded-full border border-neutral-200 px-2.5 py-1 text-xs font-semibold text-neutral-700 dark:border-neutral-700 dark:text-neutral-300"
              x-text="enabled ? 'Enabled' : 'Disabled'">{{ $user->sms_mfa_enabled ? 'Enabled' : 'Disabled' }}</span>
    </div>

    @if (session('sms_mfa_success'))
        <x-ui.alert variant="success" title="SMS setting updated" class="mt-5">{{ session('sms_mfa_success') }}</x-ui.alert>
    @endif

    @if ($errors->smsMfa->any())
        <x-ui.alert variant="danger" title="Could not update SMS authentication" class="mt-5">
            {{ $errors->smsMfa->first() }}
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('profile.sms-mfa.update') }}" class="mt-5 space-y-4"
          data-confirm-sms-mfa data-original-sms-mfa="{{ $user->sms_mfa_enabled ? '1' : '0' }}">
        @csrf
        @method('patch')
        <input type="hidden" name="sms_mfa_enabled" value="0">
        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/60">
            <span class="text-sm font-medium text-neutral-800 dark:text-neutral-200">Require SMS verification</span>
            <input type="checkbox" name="sms_mfa_enabled" value="1" x-model="enabled" @checked($user->sms_mfa_enabled)
                   class="rounded border-neutral-400 text-primary-600 focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-900">
        </label>
        <div>
            <x-input-label for="sms-current-password" value="Current password" class="text-neutral-700 dark:text-neutral-300" />
            <x-text-input id="sms-current-password" name="current_password" type="password" required autocomplete="off"
                          data-lpignore="true" data-1p-ignore="true" data-bwignore="true"
                          style="-webkit-text-security: disc; text-security: disc;" class="mt-1 block w-full" />
        </div>
        <x-ui.button type="submit" data-loading-text="Saving...">Save SMS setting</x-ui.button>
    </form>
</section>
