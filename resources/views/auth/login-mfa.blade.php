<x-guest-layout
    :title="$panel->label().' Login Verification'"
    portal="{{ $panel === \App\Support\AuthenticationPanel::SuperAdmin ? 'super-admin' : 'admin' }}"
>
    <div class="space-y-7">
        <header>
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                <x-ui.icon name="shield-check" class="h-3.5 w-3.5" />
                {{ $panel->label() }} login security
            </div>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">Verify your sign-in</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500">
                Enter the 6-digit code sent to <span class="font-medium text-neutral-700">{{ $maskedEmail }}</span>.
                Each code expires in {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }}.
            </p>
        </header>

        @if ($expired)
            <x-ui.alert variant="warning" title="Code expired">
                This code has expired. Request a new code to continue this sign-in.
            </x-ui.alert>
        @endif

        <x-auth-session-status
            class="rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700"
            :status="session('status')"
        />

        <form method="POST" action="{{ route($panel->loginMfaVerifyRoute()) }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="otp" :value="__('Verification code')" class="text-neutral-700" />
                <x-text-input
                    id="otp"
                    class="mt-2 block h-12 w-full rounded-lg border-neutral-300 bg-white px-3.5 text-center font-mono text-xl tracking-[0.45em] shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    type="text"
                    name="otp"
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
                {{ __('Verify and sign in') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route($panel->loginMfaResendRoute()) }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                Send a new code
            </button>
            @if ($resendAvailableIn > 0)
                <p class="mt-1 text-xs text-neutral-500">
                    A new code can be requested after the {{ $resendAvailableIn }}-second cooldown.
                </p>
            @endif
        </form>

        <p class="border-t border-neutral-200 pt-5 text-xs leading-5 text-neutral-500">
            Never share this code. HIMS support will not ask you for it.
        </p>

        <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700" href="{{ route($panel->loginRoute()) }}">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Return to {{ $panel->label() }} login
        </a>
    </div>
</x-guest-layout>
