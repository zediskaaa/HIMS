<section>
    @php($passwordSuccess = session()->pull('password_success'))

    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ \App\Rules\PasswordStandard::REQUIREMENTS }}
        </p>
    </header>

    @if ($passwordSuccess)
        <x-ui.alert variant="success" title="Password updated" dismissible class="mt-6">
            {{ $passwordSuccess }}
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6" x-data="{ password: '', passwordConfirmation: '' }">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full {{ $errors->updatePassword->has('current_password') ? '!border-danger-500 focus:!border-danger-500 focus:!ring-danger-500' : '' }}" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}" title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}" autocomplete="new-password" x-model="password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" x-model="passwordConfirmation" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <x-auth.password-requirements />

        <div class="flex items-center">
            <x-primary-button data-loading-text="Updating password...">{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
