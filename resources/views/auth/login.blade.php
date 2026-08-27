<x-guest-layout title="Sign in">
    <div class="space-y-8">
        {{-- Delays start after the card itself has settled (see layouts/guest.blade.php). --}}
        <header class="animate-fade-up [animation-delay:320ms]">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                Secure staff portal
            </div>

            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">Sign in to HIMS</h1>
            <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-500">
                Access procurement, inventory, and hospital supply operations from one workspace.
            </p>
        </header>

        <x-auth-session-status
            class="animate-fade-up rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700 [animation-delay:380ms]"
            :status="session('status')"
        />

        <form method="POST" action="{{ route('login') }}" class="animate-fade-up space-y-5 [animation-delay:440ms]" x-data="{ showPassword: false }">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email address')" class="text-neutral-700" />
                <x-text-input
                    id="email"
                    class="mt-2 block h-11 w-full rounded-lg bg-white px-3.5 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:ring-primary-500 {{ $errors->has('email') ? '!border-danger-500 focus:!border-danger-500' : 'border-neutral-300 focus:border-primary-500' }}"
                    type="email"
                    name="email"
                    :value="old('email')"
                    placeholder="name@hospital.org"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-danger-600" />
            </div>

            <div>
                <div class="flex items-center justify-between gap-4">
                    <x-input-label for="password" :value="__('Password')" class="text-neutral-700" />

                    @if (Route::has('password.request'))
                        <a
                            class="rounded text-xs font-medium text-primary-600 transition-colors hover:text-primary-700"
                            href="{{ asset('email-verification/email-verify.html') }}"
                        >
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>

                <div class="relative mt-2">
                    <x-text-input
                        id="password"
                        class="block h-11 w-full rounded-lg bg-white px-3.5 pr-11 text-sm text-neutral-900 shadow-sm {{ $errors->has('email') || $errors->has('password') ? '!border-danger-500 focus:!border-danger-500' : 'border-neutral-300 focus:border-primary-500' }} focus:ring-primary-500"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="current-password"
                    />

                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-neutral-400 transition-colors hover:text-neutral-700 focus-visible:ring-inset"
                        x-on:click="showPassword = !showPassword"
                        x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                        x-bind:aria-pressed="showPassword"
                        aria-controls="password"
                    >
                        <x-ui.icon name="eye" class="h-5 w-5" x-show="!showPassword" />
                        <x-ui.icon name="eye-slash" class="h-5 w-5" x-show="showPassword" x-cloak />
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-danger-600" />
            </div>

            <label for="remember_me" class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-neutral-600">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-neutral-300 text-primary-600 shadow-sm focus:ring-primary-500"
                    name="remember"
                >
                <span>{{ __('Keep me signed in') }}</span>
            </label>

            <x-ui.button type="submit" size="lg" icon="arrow-right-on-rectangle" class="w-full">
                {{ __('Sign in') }}
            </x-ui.button>
        </form>

        <div class="flex items-center gap-2.5 border-t border-neutral-200 pt-6 text-xs leading-5 text-neutral-500">
            <x-ui.icon name="shield-check" class="h-4 w-4 shrink-0 text-primary-600" />
            <p>For authorized hospital personnel only. Your session is securely protected.</p>
        </div>
    </div>
</x-guest-layout>
