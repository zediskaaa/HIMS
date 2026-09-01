<x-guest-layout :title="$panel->label().' Password Reset Verification'">
    <div class="space-y-7">
        <header>
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                {{ $panel->label() }} password recovery
            </div>
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

        <form method="POST" action="{{ route($panel->passwordOtpVerifyRoute()) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <x-input-label for="otp" :value="__('Verification code')" class="text-neutral-700" />
                <x-text-input
                    id="otp"
                    class="mt-2 block h-12 w-full rounded-lg border-neutral-300 bg-white px-3.5 text-center font-mono text-xl tracking-[0.45em] shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    type="text"
                    name="otp"
                    :value="old('otp')"
                    required
                    autofocus
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                />
                <x-input-error :messages="$errors->get('otp')" class="mt-2 text-danger-600" />
            </div>

            <x-ui.button type="submit" size="lg" icon="shield-check" class="w-full">
                {{ __('Verify code') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route($panel->passwordEmailRoute()) }}" class="text-center">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                Send a new code
            </button>
        </form>

        <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700" href="{{ route($panel->passwordRequestRoute()) }}">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Use a different email address
        </a>
    </div>
</x-guest-layout>
