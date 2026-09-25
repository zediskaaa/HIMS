<x-guest-layout :title="$panel->label().' Password Reset Verification'">
    <div class="space-y-7">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">Enter verification code</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500">
                Enter the 6-digit code sent to <span class="font-medium text-neutral-700">{{ $email }}</span>.
                It expires in {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }}.
            </p>
        </header>

        <x-auth.wrong-panel-alert />

        <x-auth-session-status
            class="rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700"
            :status="session('status')"
        />

        <form
            method="POST"
            action="{{ route($panel->passwordOtpVerifyRoute()) }}"
            class="space-y-5"
            autocomplete="off"
            x-data="himsOtpVerification({
                length: 6,
                initial: @js(old('otp', '')),
                initialError: @js($errors->first('otp')),
            })"
            x-on:submit.prevent="verify()"
        >
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <x-input-label for="password-reset-otp-0" :value="__('Verification code')" class="text-neutral-700 dark:text-neutral-300" />
                <div class="mt-2">
                    <x-auth.otp-input
                        id="password-reset-otp"
                        :value="old('otp', '')"
                        :error="$errors->first('otp')"
                    />
                </div>
            </div>

            <x-ui.button
                type="submit"
                size="lg"
                data-loading-text="Verifying..."
                class="w-full"
                x-bind:disabled="state !== 'ready'"
                x-bind:aria-busy="state === 'verifying' || validating ? 'true' : 'false'"
            >
                <span x-show="state === 'verifying' || validating" x-cloak class="loader loader--sm" aria-hidden="true"></span>
                <span x-show="state === 'verifying' || validating" x-cloak>{{ __('Verifying...') }}</span>
                <span x-show="state === 'idle' || state === 'ready' || state === 'error'">{{ __('Verify code') }}</span>
                <span x-show="state === 'verified' || state === 'success'" x-cloak>{{ __('Verified') }}</span>
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route($panel->passwordEmailRoute()) }}" class="text-center">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" data-loading-text="Sending..." class="inline-flex items-center justify-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
                Send a new code
            </button>
        </form>

        <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700" href="{{ route($panel->passwordRequestRoute()) }}">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Use a different email address
        </a>
    </div>
</x-guest-layout>
