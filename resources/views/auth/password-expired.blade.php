<x-guest-layout :title="$panel->label().' Password Expired'">
    <div class="space-y-7">
        <header>
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-warning-100 bg-warning-50 px-3 py-1 text-xs font-medium text-warning-700">
                <x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5" />
                Security update required
            </div>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">Password Expired</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-600">
                Your password has expired. Please create a new password to continue.
            </p>
        </header>

        <form
            method="POST"
            action="{{ route($panel->expiredPasswordUpdateRoute()) }}"
            class="space-y-5"
            x-data="{ password: '', passwordConfirmation: '' }"
            data-confirm-title="Confirm security change"
            data-confirm-message="Are you sure you want to change your password?"
            data-confirm-label="Change Password"
        >
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="expired_password" value="New Password" class="text-neutral-700" />
                <x-text-input
                    id="expired_password"
                    class="mt-2 block h-11 w-full rounded-lg border-neutral-300 bg-white px-3.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                    title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}"
                    autocomplete="new-password"
                    x-model="password"
                    autofocus
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-danger-600" />
            </div>

            <div>
                <x-input-label for="expired_password_confirmation" value="Confirm New Password" class="text-neutral-700" />
                <x-text-input
                    id="expired_password_confirmation"
                    class="mt-2 block h-11 w-full rounded-lg border-neutral-300 bg-white px-3.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    x-model="passwordConfirmation"
                />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-danger-600" />
            </div>

            <x-auth.password-requirements />

            <x-ui.button type="submit" size="lg" data-loading-text="Updating password..." class="w-full">
                Update Password
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route($panel->expiredPasswordCancelRoute()) }}" class="text-center"
              data-confirm-title="Confirm logout"
              data-confirm-message="Are you sure you want to log out?"
              data-confirm-label="Log Out">
            @csrf
            <button type="submit" data-loading-text="Returning to login..." class="text-sm font-medium text-primary-600 hover:text-primary-700">
                Return to {{ $panel->label() }} Login
            </button>
        </form>
    </div>
</x-guest-layout>
