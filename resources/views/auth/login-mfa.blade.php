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
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">
                @if ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY)
                    Reconfigure Authenticator App
                @elseif ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR)
                    Authenticator Verification
                @else
                    Verify your sign-in
                @endif
            </h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500">
                @if ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY)
                    Your saved authenticator setup can no longer be verified. Scan the new setup code below, then enter its current 6-digit code. Your existing setup remains enforced until verification succeeds.
                @elseif ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR)
                    Enter the 6-digit code from your authenticator app.
                @else
                    Enter the 6-digit code sent to <span class="font-medium text-neutral-700">{{ $maskedEmail }}</span>.
                    Each code expires in {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }}.
                @endif
            </p>
        </header>

        @if ($expired)
            <x-ui.alert variant="warning" title="Code expired">
                {{ $method === \App\Services\LoginMfaService::METHOD_EMAIL
                    ? 'This code has expired. Request a new code to continue this sign-in.'
                    : 'This authenticator verification session has expired. Return to login and sign in again.' }}
            </x-ui.alert>
        @endif

        <x-auth-session-status
            class="rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700"
            :status="session('status')"
        />

        @if ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY && $authenticatorSetup)
            <div class="space-y-4 rounded-lg border border-warning-200 bg-warning-50 p-4">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900">Set up a replacement authenticator</h2>
                    <p class="mt-1 text-xs leading-5 text-neutral-600">
                        In Google Authenticator, add an account and scan this QR code. You can enter the setup key manually if scanning is unavailable.
                    </p>
                </div>

                <div class="flex justify-center rounded-lg border border-neutral-200 bg-white p-3">
                    <img
                        src="{{ $authenticatorSetup['qr_code'] }}"
                        alt="Replacement authenticator app setup QR code"
                        width="220"
                        height="220"
                        class="h-[220px] w-[220px] max-w-full object-contain"
                    />
                </div>

                <div>
                    <p class="text-xs font-medium text-neutral-800">Manual setup key</p>
                    <code class="mt-1.5 block break-all rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-center font-mono text-sm font-semibold tracking-[0.18em] text-neutral-900">{{ $authenticatorSetup['secret'] }}</code>
                    <p class="mt-1 text-[11px] text-neutral-500">Type: Time based &middot; Digits: 6 &middot; Period: 30 seconds</p>
                </div>
            </div>
        @endif

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

            <x-ui.button type="submit" size="lg" icon="shield-check" data-loading-text="Verifying..." class="w-full">
                {{ $method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY
                    ? __('Reconfigure and sign in')
                    : __('Verify and sign in') }}
            </x-ui.button>
        </form>

        @if ($method === \App\Services\LoginMfaService::METHOD_EMAIL)
            <form method="POST" action="{{ route($panel->loginMfaResendRoute()) }}" class="text-center">
                @csrf
                <button type="submit" data-loading-text="Sending..." class="inline-flex items-center justify-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
                    Send a new code
                </button>
                @if ($resendAvailableIn > 0)
                    <p class="mt-1 text-xs text-neutral-500">
                        A new code can be requested after the {{ $resendAvailableIn }}-second cooldown.
                    </p>
                @endif
            </form>
        @endif

        <p class="border-t border-neutral-200 pt-5 text-xs leading-5 text-neutral-500">
            Never share this code. HIMS support will not ask you for it.
        </p>

        <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700" href="{{ route($panel->loginRoute()) }}">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Return to {{ $panel->label() }} login
        </a>
    </div>
</x-guest-layout>
