<x-guest-layout
    :title="$panel->label().' Login Verification'"
    portal="{{ $panel->value }}"
>
    <div
        class="space-y-6"
        x-data="{
            isAuthenticator: {{ $isAuthenticator ? 'true' : 'false' }},
            expiresAt: {{ (int) $expiresAt }},
            serverNow: {{ (int) $serverNow }},
            clockSkewMs: ({{ (int) $serverNow }} * 1000) - Date.now(),
            totalDuration: {{ (int) $totalDurationSeconds }},
            warningThreshold: {{ (int) $warningSeconds }},
            remainingSeconds: {{ (int) $remainingSeconds }},
            isWarning: {{ ($isAuthenticator && $remainingSeconds <= $warningSeconds && $remainingSeconds > 0 && ! $expired) ? 'true' : 'false' }},
            isExpired: {{ ($isAuthenticator && ($expired || $remainingSeconds <= 0)) ? 'true' : 'false' }},
            isExtending: false,
            isEnding: false,
            extensionError: '',
            extensionsRemaining: 3,
            continueUrl: '{{ $continueUrl }}',
            cancelUrl: '{{ $cancelUrl }}',
            loginUrl: '{{ $loginUrl }}',
            timer: null,
            announcedWarning: false,
            announcedExpired: false,

            init() {
                if (!this.isAuthenticator) return;
                this.tick();
                this.timer = setInterval(() => this.tick(), 1000);

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden && this.isAuthenticator) {
                        this.tick();
                    }
                });

                window.addEventListener('storage', (e) => {
                    if (!this.isAuthenticator) return;
                    if (e.key === 'hims:mfa:expires_at') {
                        const newExpiry = Number(e.newValue);
                        if (Number.isFinite(newExpiry) && newExpiry > 0) {
                            this.expiresAt = newExpiry;
                            this.isExpired = false;
                            this.tick();
                        }
                    } else if (e.key === 'hims:mfa:cancelled') {
                        window.himsNavigate(this.loginUrl);
                    }
                });
            },

            tick() {
                if (!this.isAuthenticator || this.isExpired) {
                    if (this.isExpired) {
                        this.remainingSeconds = 0;
                        this.isWarning = false;
                    }
                    return;
                }

                const clientNowAdjustedMs = Date.now() + this.clockSkewMs;
                const remaining = Math.max(0, Math.floor((this.expiresAt * 1000 - clientNowAdjustedMs) / 1000));
                this.remainingSeconds = remaining;

                if (remaining <= 0) {
                    this.isExpired = true;
                    this.isWarning = false;
                    if (!this.announcedExpired) {
                        this.announce('Verification session has expired. Please sign in again.');
                        this.announcedExpired = true;
                    }
                } else if (remaining <= this.warningThreshold) {
                    this.isWarning = true;
                    if (!this.announcedWarning) {
                        this.announce('Warning: Verification session expires in 30 seconds. Choose Continue Session or End Session.');
                        this.announcedWarning = true;
                    }
                } else {
                    this.isWarning = false;
                    this.announcedWarning = false;
                }
            },

            get formattedTime() {
                const minutes = Math.floor(this.remainingSeconds / 60);
                const seconds = this.remainingSeconds % 60;
                return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            },

            get progressPercent() {
                if (this.isExpired || this.totalDuration <= 0) return 0;
                return Math.max(0, Math.min(100, Math.round((this.remainingSeconds / this.totalDuration) * 100)));
            },

            announce(message) {
                const liveEl = this.$refs.liveRegion;
                if (liveEl) {
                    liveEl.textContent = '';
                    setTimeout(() => { liveEl.textContent = message; }, 50);
                }
            },

            async continueSession() {
                if (this.isExtending || this.isExpired) return;
                this.isExtending = true;
                this.extensionError = '';

                try {
                    const response = await fetch(this.continueUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                        },
                        body: JSON.stringify({})
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        this.expiresAt = data.expires_at;
                        this.clockSkewMs = (Date.now() - (data.expires_at - data.remaining_seconds) * 1000);
                        this.tick();
                        this.extensionError = '';
                        if (data.extensions_remaining !== undefined) {
                            this.extensionsRemaining = data.extensions_remaining;
                        }
                        try {
                            localStorage.setItem('hims:mfa:expires_at', String(data.expires_at));
                        } catch {}
                        this.announce('Verification session extended.');
                    } else if (data.redirect_url) {
                        window.himsNavigate(data.redirect_url);
                    } else {
                        this.extensionError = data.message || 'Unable to extend session. Please complete verification.';
                        if (data.status === 'expired' || data.status === 'missing') {
                            this.isExpired = true;
                        }
                    }
                } catch (err) {
                    this.extensionError = 'Network error while attempting to extend session.';
                } finally {
                    this.isExtending = false;
                }
            },

            async endSession() {
                if (this.isEnding) return;
                this.isEnding = true;

                try {
                    localStorage.setItem('hims:mfa:cancelled', String(Date.now()));
                } catch {}

                try {
                    const response = await fetch(this.cancelUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                        },
                        body: JSON.stringify({})
                    });
                    const data = await response.json();
                    window.himsNavigate(data.redirect_url || this.loginUrl);
                } catch {
                    window.himsNavigate(this.loginUrl);
                }
            }
        }"
    >
        <header>
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-300">
                <x-ui.icon name="shield-check" class="h-3.5 w-3.5" />
                {{ $panel->label() }} login security
            </div>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                @if ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY)
                    Reconfigure Authenticator App
                @elseif ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR)
                    Authenticator Verification
                @elseif ($method === \App\Services\LoginMfaService::METHOD_SMS)
                    Verification Code
                @else
                    Verify your sign-in
                @endif
            </h1>
            <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                @if ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY)
                    Your saved authenticator setup can no longer be verified. Scan the new setup code below, then enter its current 6-digit code. Your existing setup remains enforced until verification succeeds.
                @elseif ($method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR)
                    Enter the 6-digit code from your authenticator app.
                @elseif ($method === \App\Services\LoginMfaService::METHOD_SMS)
                    Enter the 6-digit code sent to <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ $maskedPhone }}</span>.
                    It expires in {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }}.
                @else
                    Enter the 6-digit code sent to <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ $maskedEmail }}</span>.
                    Each code expires in {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }}.
                @endif
            </p>
        </header>

        {{-- Accessible polite live region for screen-reader announcements --}}
        @if ($isAuthenticator)
            <div x-ref="liveRegion" class="sr-only" aria-live="polite" aria-atomic="true"></div>
        @endif

        {{-- Authenticator Dedicated 2-Minute Session Countdown & Warning State --}}
        @if ($isAuthenticator)
            {{-- 1. Normal State (> 30s) and Warning State (<= 30s) --}}
            <div
                x-show="!isExpired"
                x-cloak
                class="overflow-hidden rounded-xl border transition-colors duration-300"
                :class="isWarning
                    ? 'border-warning-300 dark:border-warning-700/80 bg-warning-50/80 dark:bg-warning-950/40 shadow-xs'
                    : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/90 shadow-2xs'"
            >
                {{-- Progress Bar indicator --}}
                <div class="h-1.5 w-full bg-neutral-100 dark:bg-neutral-800/80">
                    <div
                        class="h-full transition-[width,background-color] duration-1000 ease-linear"
                        :class="isWarning ? 'bg-warning-500 dark:bg-warning-400' : 'bg-primary-600 dark:bg-primary-500'"
                        :style="'width: ' + progressPercent + '%'"
                        role="progressbar"
                        :aria-valuenow="remainingSeconds"
                        aria-valuemin="0"
                        :aria-valuemax="totalDuration"
                        :aria-label="'Verification time remaining: ' + formattedTime"
                    ></div>
                </div>

                <div class="p-4 sm:p-4.5">
                    {{-- Normal inline timer layout (> 30s) --}}
                    <div x-show="!isWarning" class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300" aria-hidden="true">
                                <x-ui.icon name="clock" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Session time remaining</p>
                                <p class="text-xs text-neutral-400 dark:text-neutral-500">2-minute verification window</p>
                            </div>
                        </div>

                        <span
                            class="font-mono text-xl font-bold tracking-tight tabular-nums text-neutral-800 dark:text-neutral-200"
                            x-text="formattedTime"
                            aria-hidden="true"
                        >
                            {{ sprintf('%02d:%02d', (int) floor($remainingSeconds / 60), $remainingSeconds % 60) }}
                        </span>
                    </div>

                    {{-- Warning prompt layout (<= 30s) --}}
                    <div x-show="isWarning" class="space-y-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning-100 dark:bg-warning-900/60 text-warning-700 dark:text-warning-300" aria-hidden="true">
                                    <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold text-warning-900 dark:text-warning-200">
                                        Verification session expiring soon
                                    </h2>
                                    <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        For your security, this MFA attempt will expire unless extended.
                                    </p>
                                </div>
                            </div>

                            <span
                                class="font-mono text-2xl sm:text-3xl font-bold tracking-tight tabular-nums text-warning-700 dark:text-warning-400"
                                x-text="formattedTime"
                                aria-hidden="true"
                            >
                                {{ sprintf('%02d:%02d', (int) floor($remainingSeconds / 60), $remainingSeconds % 60) }}
                            </span>
                        </div>

                        {{-- Action buttons for session continuation --}}
                        <div class="border-t border-warning-200/80 dark:border-warning-800/60 pt-3">
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <button
                                    type="button"
                                    @click="endSession"
                                    :disabled="isEnding || isExtending"
                                    class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-2xs hover:bg-neutral-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                                >
                                    <span x-show="!isEnding">{{ __('End Session') }}</span>
                                    <span x-show="isEnding">{{ __('Ending...') }}</span>
                                </button>
                                <button
                                    type="button"
                                    @click="continueSession"
                                    :disabled="isExtending || isEnding"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-warning-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-warning-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-600 disabled:opacity-50 dark:bg-warning-600 dark:hover:bg-warning-500"
                                >
                                    <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" x-show="isExtending" />
                                    <span x-show="!isExtending">{{ __('Continue Session') }}</span>
                                    <span x-show="isExtending">{{ __('Extending...') }}</span>
                                </button>
                            </div>

                            <p x-show="extensionError" x-text="extensionError" class="mt-2 text-xs font-medium text-danger-600 dark:text-danger-400" role="alert"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Expired State (at 00:00 or server expired) --}}
            <div
                x-show="isExpired"
                x-cloak
                class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/90"
            >
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-700 dark:bg-warning-950/60 dark:text-warning-300" aria-hidden="true">
                        <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                            Verification session expired
                        </h2>
                        <p class="mt-1 text-xs leading-5 text-neutral-600 dark:text-neutral-400">
                            Your 2-minute verification window has elapsed. For your security, this attempt has been ended and the code input is disabled.
                        </p>
                        <div class="mt-3.5">
                            <a
                                href="{{ $loginUrl }}"
                                @click.prevent="endSession"
                                :class="{ 'opacity-50 pointer-events-none': isEnding }"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-neutral-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900 dark:bg-primary-600 dark:hover:bg-primary-500"
                            >
                                <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" x-show="!isEnding" />
                                <x-ui.icon name="arrow-path" class="h-3.5 w-3.5 animate-spin" x-show="isEnding" x-cloak />
                                <span x-show="!isEnding">Return to login and sign in again</span>
                                <span x-show="isEnding" x-cloak>Returning to login...</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Non-Authenticator Expired Alert (Email / SMS) --}}
            @if ($expired)
                <x-ui.alert variant="warning" title="Code expired">
                    {{ in_array($method, [\App\Services\LoginMfaService::METHOD_EMAIL, \App\Services\LoginMfaService::METHOD_SMS], true)
                        ? 'This code has expired. Request a new code to continue this sign-in.'
                        : 'This authenticator verification session has expired. Return to login and sign in again.' }}
                </x-ui.alert>
            @endif
        @endif

        @if ($exhausted)
            <x-ui.alert variant="warning" title="Verification attempts used">
                Too many incorrect attempts. Return to login and sign in again.
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

                <div class="flex justify-center rounded-lg border border-neutral-200 bg-white p-3 hims-keep-light">
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

        <form
            method="POST"
            action="{{ route($panel->loginMfaVerifyRoute()) }}"
            class="space-y-5"
            x-show="!isAuthenticator || !isExpired"
            x-data="himsOtpVerification({
                length: 6,
                initial: @js(old('otp', '')),
                initialError: @js($errors->first('otp')),
            })"
            x-on:submit.prevent="verify()"
        >
            @csrf

            <div>
                <x-input-label for="login-otp-0" :value="__('Verification code')" class="text-neutral-700 dark:text-neutral-300" />
                <div class="mt-2">
                    <x-auth.otp-input
                        id="login-otp"
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
                x-bind:disabled="(isAuthenticator && isExpired) || validating || state === 'success'"
                x-bind:aria-busy="validating ? 'true' : 'false'"
            >
                <span x-show="validating" x-cloak class="loader loader--sm" aria-hidden="true"></span>
                <span x-show="validating" x-cloak>{{ __('Verifying...') }}</span>
                <span x-show="!validating && state !== 'success'">
                    {{ $method === \App\Services\LoginMfaService::METHOD_AUTHENTICATOR_RECOVERY
                        ? __('Reconfigure and sign in')
                        : __('Verify and sign in') }}
                </span>
                <span x-show="state === 'success'" x-cloak>{{ __('Verified') }}</span>
            </x-ui.button>
        </form>

        @if (! $exhausted && in_array($method, [\App\Services\LoginMfaService::METHOD_EMAIL, \App\Services\LoginMfaService::METHOD_SMS], true))
            <form method="POST" action="{{ route($panel->loginMfaResendRoute()) }}" class="text-center"
                  x-data="{ remaining: {{ $resendAvailableIn }} }"
                  x-init="const timer = setInterval(() => { if (remaining > 0) remaining--; else clearInterval(timer) }, 1000)">
                @csrf
                <button type="submit" data-loading-text="Sending..." :disabled="remaining > 0"
                        class="inline-flex items-center justify-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:text-primary-400 dark:hover:text-primary-300">
                    Resend code
                </button>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400" x-show="remaining > 0" x-text="'Available in ' + remaining + ' seconds'"></p>
            </form>
        @endif

        {{-- Single clear return action when active; hidden when verification session is expired --}}
        <div
            x-show="!isAuthenticator || !isExpired"
            @style(['display: none' => ($isAuthenticator && ($expired || $remainingSeconds <= 0))])
            class="space-y-4"
        >
            <p class="border-t border-neutral-200 pt-5 text-xs leading-5 text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                Never share this code. HIMS support will not ask you for it.
            </p>

            <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" href="{{ route($panel->loginRoute()) }}">
                <x-ui.icon name="chevron-left" class="h-4 w-4" />
                Return to {{ $panel->label() }} login
            </a>
        </div>
    </div>
</x-guest-layout>
