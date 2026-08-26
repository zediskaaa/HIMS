<x-guest-layout title="Reset Password">
    <div class="space-y-8">
        <header class="animate-fade-up [animation-delay:320ms]">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                Account recovery
            </div>

            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">Set new password</h1>
            <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-500">
                Your identity has been verified. Choose a new password for your account.
            </p>
        </header>

        <form method="POST" action="{{ route('password.store.otp') }}" class="animate-fade-up space-y-5 [animation-delay:440ms]" x-data="{ showPassword: false, showConfirm: false }">
            @csrf

            <!-- Email (read-only, pre-filled) -->
            <div>
                <x-input-label for="email" :value="__('Email address')" class="text-neutral-700" />
                <x-text-input
                    id="email"
                    class="mt-2 block h-11 w-full rounded-lg border-neutral-300 bg-neutral-50 px-3.5 text-sm text-neutral-600 shadow-sm"
                    type="email"
                    name="email"
                    :value="$email"
                    readonly
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-danger-600" />
            </div>

            <!-- New Password -->
            <div>
                <x-input-label for="password" :value="__('New password')" class="text-neutral-700" />
                <div class="relative mt-2">
                    <x-text-input
                        id="password"
                        class="block h-11 w-full rounded-lg border-neutral-300 bg-white px-3.5 pr-11 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Min. 8 characters"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-neutral-400 transition-colors hover:text-neutral-700"
                        x-on:click="showPassword = !showPassword"
                    >
                        <x-ui.icon name="eye" class="h-5 w-5" x-show="!showPassword" />
                        <x-ui.icon name="eye-slash" class="h-5 w-5" x-show="showPassword" x-cloak />
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-danger-600" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm new password')" class="text-neutral-700" />
                <div class="relative mt-2">
                    <x-text-input
                        id="password_confirmation"
                        class="block h-11 w-full rounded-lg border-neutral-300 bg-white px-3.5 pr-11 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        x-bind:type="showConfirm ? 'text' : 'password'"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Re-enter password"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-neutral-400 transition-colors hover:text-neutral-700"
                        x-on:click="showConfirm = !showConfirm"
                    >
                        <x-ui.icon name="eye" class="h-5 w-5" x-show="!showConfirm" />
                        <x-ui.icon name="eye-slash" class="h-5 w-5" x-show="showConfirm" x-cloak />
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-danger-600" />
            </div>

            <x-ui.button type="submit" size="lg" icon="lock-closed" class="w-full">
                {{ __('Reset Password') }}
            </x-ui.button>
        </form>

        <div class="flex items-center gap-2.5 border-t border-neutral-200 pt-6 text-xs leading-5 text-neutral-500">
            <x-ui.icon name="shield-check" class="h-4 w-4 shrink-0 text-primary-600" />
            <p>Your password will be updated securely. You'll be redirected to sign in.</p>
        </div>
    </div>
</x-guest-layout>

