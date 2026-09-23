<section class="py-4 last:pb-0">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Update Password') }}</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ \App\Rules\PasswordStandard::REQUIREMENTS }}</p>
        </div>
        <x-ui.button type="button" size="sm" variant="secondary" class="self-start shrink-0" x-data x-on:click="$dispatch('open-modal', 'change-password')">{{ __('Change password') }}</x-ui.button>
    </div>

    <div x-data @if ($errors->updatePassword->any()) x-init="$nextTick(() => $dispatch('open-modal', 'change-password'))" @endif>
    <x-ui.modal name="change-password" :title="__('Update Password')" maxWidth="md">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4"
          autocomplete="off"
          x-data="{ currentPassword: '', password: '', passwordConfirmation: '' }"
          data-confirm-title="Confirm security change"
          data-confirm-message="Are you sure you want to change your password?"
          data-confirm-label="Change Password">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input
                id="update_password_current_password"
                name="current_password"
                type="text"
                class="mt-1 block w-full {{ $errors->updatePassword->has('current_password') ? '!border-danger-500 focus:!border-danger-500 focus:!ring-danger-500' : '' }}"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                data-lpignore="true"
                data-1p-ignore="true"
                data-form-type="other"
                style="-webkit-text-security: disc; text-security: disc;"
                x-model="currentPassword"
                x-init="$el.value = ''; setTimeout(() => { $el.value = ''; currentPassword = ''; }, 50); setTimeout(() => { $el.value = ''; currentPassword = ''; }, 200)"
                value=""
                required
            />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input
                id="update_password_password"
                name="password"
                type="text"
                class="mt-1 block w-full"
                required
                minlength="8"
                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                data-lpignore="true"
                data-1p-ignore="true"
                data-form-type="other"
                style="-webkit-text-security: disc; text-security: disc;"
                x-model="password"
            />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-text-input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="text"
                class="mt-1 block w-full"
                required
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                data-lpignore="true"
                data-1p-ignore="true"
                data-form-type="other"
                style="-webkit-text-security: disc; text-security: disc;"
                x-model="passwordConfirmation"
            />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <x-auth.password-requirements />

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'change-password')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" data-loading-text="Updating password...">{{ __('Save') }}</x-ui.button>
        </div>
    </form>
    </x-ui.modal>
    </div>
</section>
