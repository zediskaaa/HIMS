<section
    x-data="{
        {{-- State --}}
        enabled: {{ $user->authenticatorMfaEnabled() ? 'true' : 'false' }},
        recoveryRequired: {{ $authenticatorRecoveryRequired ? 'true' : 'false' }},
        successMessage: '',

        {{-- Password Confirmation Modal --}}
        showPasswordModal: false,
        password: '',
        passwordError: '',
        passwordLoading: false,

        {{-- Authenticator Setup Modal --}}
        showSetup: {{ !empty($authenticatorSetup) ? 'true' : 'false' }},
        qrCode: @js($authenticatorSetup['qr_code'] ?? ''),
        secret: @js($authenticatorSetup['secret'] ?? ''),
        code: '',
        codeError: '',
        verifyLoading: false,

        openPasswordModal() {
            this.password = '';
            this.passwordError = '';
            this.showPasswordModal = true;
            this.updateScrollLock();
            setTimeout(() => {
                const input = document.getElementById('authenticator-modal-password');
                if (input) input.focus();
            }, 150);
        },

        closePasswordModal() {
            this.showPasswordModal = false;
            this.password = '';
            this.passwordError = '';
            this.updateScrollLock();
        },

        async submitPassword() {
            this.passwordError = '';
            this.passwordLoading = true;

            try {
                const res = await fetch('{{ route('profile.authenticator.setup.json') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ current_password: this.password }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.passwordError = data.errors?.current_password?.[0]
                        || data.errors?.authenticator?.[0]
                        || 'The provided password is incorrect.';
                    return;
                }

                {{-- Password verified: close password modal & automatically open setup modal --}}
                this.showPasswordModal = false;
                this.qrCode = data.qr_code;
                this.secret = data.secret;
                this.password = '';
                this.code = '';
                this.codeError = '';
                this.showSetup = true;
                this.updateScrollLock();

                setTimeout(() => {
                    const input = document.getElementById('modal-authenticator-code');
                    if (input) input.focus();
                }, 150);
            } catch (e) {
                this.passwordError = 'Network error. Please check your connection and try again.';
            } finally {
                this.passwordLoading = false;
            }
        },

        async submitCode() {
            this.codeError = '';
            this.verifyLoading = true;

            try {
                const res = await fetch('{{ route('profile.authenticator.enable.json') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ code: this.code }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.codeError = data.errors?.code?.[0]
                        || data.errors?.authenticator?.[0]
                        || 'Invalid authenticator code. Please try again.';
                    this.code = '';
                    setTimeout(() => {
                        const input = document.getElementById('modal-authenticator-code');
                        if (input) input.focus();
                    }, 150);
                    return;
                }

                {{-- Verification succeeded: enable MFA and close setup modal --}}
                this.showSetup = false;
                this.enabled = true;
                this.recoveryRequired = false;
                this.successMessage = data.message || 'Authenticator app enabled successfully.';
                this.code = '';
                this.qrCode = '';
                this.secret = '';
                this.updateScrollLock();
            } catch (e) {
                this.codeError = 'Network error. Please check your connection and try again.';
            } finally {
                this.verifyLoading = false;
            }
        },

        async cancelSetup() {
            this.showSetup = false;
            this.code = '';
            this.codeError = '';
            this.qrCode = '';
            this.secret = '';
            this.updateScrollLock();

            fetch('{{ route('profile.authenticator.cancel') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });
        },

        updateScrollLock() {
            if (this.showPasswordModal || this.showSetup) {
                document.body.classList.add('overflow-y-hidden');
            } else {
                document.body.classList.remove('overflow-y-hidden');
            }
        }
    }"
    x-init="if (showSetup) updateScrollLock()"
    x-on:keydown.escape.window="if (showSetup) { cancelSetup() } else if (showPasswordModal) { closePasswordModal() }"
>
    @php($authenticatorSuccess = session()->pull('authenticator_success'))

    <header>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-gray-900">{{ __('Authenticator App') }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Use Google Authenticator or another standards-compatible TOTP app.') }}
                </p>
            </div>
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="recoveryRequired ? 'bg-warning-50 text-warning-800' : (enabled ? 'bg-success-50 text-success-700' : 'bg-neutral-100 text-neutral-600')"
                x-text="recoveryRequired ? 'REPAIR REQUIRED' : (enabled ? 'ON' : 'OFF')"
            >{{ $authenticatorRecoveryRequired ? 'REPAIR REQUIRED' : ($user->authenticatorMfaEnabled() ? 'ON' : 'OFF') }}</span>
        </div>
    </header>

    {{-- Server-rendered success (e.g. from disable flow or page reload) --}}
    @if ($authenticatorSuccess)
        <x-ui.alert variant="success" title="Authenticator setting updated" dismissible class="mt-6">
            {{ $authenticatorSuccess }}
        </x-ui.alert>
    @endif

    {{-- Dynamic success alert (from modal enable flow) --}}
    <template x-if="successMessage">
        <div class="mt-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-800 flex items-center justify-between">
            <span x-text="successMessage"></span>
            <button type="button" @click="successMessage = ''" class="text-success-600 hover:text-success-800">
                <x-ui.icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
    </template>

    @if ($errors->authenticatorEnable->has('authenticator') || $errors->authenticatorDisable->has('authenticator'))
        <x-ui.alert variant="danger" title="Authenticator error" class="mt-6">
            {{ $errors->authenticatorEnable->first('authenticator') ?: $errors->authenticatorDisable->first('authenticator') }}
        </x-ui.alert>
    @endif

    {{-- ── Enabled state: Turn OFF form ────────────────────────────────── --}}
    <template x-if="enabled && !recoveryRequired">
        <div>
            <div class="mt-6 rounded-lg border border-success-200 bg-success-50 p-4 text-sm leading-6 text-success-800">
                Authenticator verification is active. A current 6-digit code is required after your password is accepted.
            </div>

            <form
                method="POST"
                action="{{ route('profile.authenticator.disable') }}"
                class="mt-6 space-y-5"
                data-confirm-title="Disable authenticator app?"
                data-confirm-message="This removes authenticator verification from future sign-ins. Your password and current authenticator code will be verified first."
                data-confirm-label="Disable authenticator"
            >
                @csrf
                @method('delete')

                <div>
                    <x-input-label for="authenticator-disable-password" :value="__('Current password')" />
                    <x-text-input id="authenticator-disable-password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                    <x-input-error :messages="$errors->authenticatorDisable->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="authenticator-disable-code" :value="__('6-digit authenticator code')" />
                    <x-text-input id="authenticator-disable-code" name="code" type="text" class="mt-1 block w-full font-mono tracking-[0.35em]" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required />
                    <x-input-error :messages="$errors->authenticatorDisable->get('code')" class="mt-2" />
                </div>

                <x-ui.button type="submit" variant="danger" data-loading-text="Disabling authenticator...">
                    {{ __('Turn OFF Authenticator App') }}
                </x-ui.button>
            </form>
        </div>
    </template>

    <template x-if="recoveryRequired">
        <div>
            <div class="mt-6 rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm leading-6 text-warning-900">
                Your saved authenticator setup failed its encryption integrity check. It is still enforced for account safety. Confirm your password, scan a replacement QR code, and verify its current code to repair the setup.
            </div>

            <div class="mt-5">
                <x-ui.button type="button" icon="shield-check" @click="openPasswordModal()">
                    {{ __('Reconfigure Authenticator App') }}
                </x-ui.button>
            </div>
        </div>
    </template>

    {{-- ── Disabled state: Turn ON action ──────────────────────────────── --}}
    <template x-if="!enabled">
        <div>
            <p class="mt-6 text-sm leading-6 text-neutral-600">
                Turning this on starts a secure setup. It will remain OFF until a code from your authenticator app is verified.
            </p>

            <div class="mt-5">
                <x-ui.button
                    type="button"
                    icon="shield-check"
                    @click="openPasswordModal()"
                >
                    {{ __('Turn ON Authenticator App') }}
                </x-ui.button>
            </div>
        </div>
    </template>

    {{-- ── Step 1: Current Password Confirmation Modal ────────────────── --}}
    <div
        x-show="showPasswordModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center px-4 py-8 sm:py-16 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-label="Confirm Password"
        @click.self="closePasswordModal()"
    >
        {{-- Backdrop --}}
        <div
            x-show="showPasswordModal"
            x-transition.opacity
            class="fixed inset-0 bg-neutral-900/50"
            aria-hidden="true"
            @click="closePasswordModal()"
        ></div>

        {{-- Panel --}}
        <div
            x-show="showPasswordModal"
            x-transition
            class="relative w-full sm:max-w-md bg-white rounded-lg shadow-lg border border-neutral-200"
        >
            <header class="flex items-start justify-between gap-4 px-5 py-4 border-b border-neutral-200">
                <div>
                    <h2 class="text-base font-semibold text-neutral-900" x-text="recoveryRequired ? 'Confirm Password to Reconfigure' : 'Confirm Current Password'">Confirm Current Password</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">Please verify your identity to continue.</p>
                </div>
                <button
                    type="button"
                    @click="closePasswordModal()"
                    class="p-1 -m-1 rounded text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    <span class="sr-only">Close</span>
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </header>

            <form @submit.prevent="submitPassword" class="p-5 space-y-4">
                <div>
                    <x-input-label for="authenticator-modal-password" :value="__('Current password')" />
                    <x-text-input
                        id="authenticator-modal-password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        required
                        x-model="password"
                        ::class="passwordError ? '!border-danger-500 focus:!border-danger-500 focus:!ring-danger-500' : ''"
                    />
                    <p x-show="passwordError" x-text="passwordError" class="mt-2 text-sm text-danger-600" x-cloak></p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="closePasswordModal()"
                        :disabled="passwordLoading"
                        class="inline-flex items-center justify-center font-medium rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm text-neutral-700 hover:bg-neutral-50 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <x-ui.button
                        type="submit"
                        icon="shield-check"
                        ::disabled="passwordLoading"
                    >
                        <span x-show="!passwordLoading">{{ __('Confirm') }}</span>
                        <span x-show="passwordLoading" x-cloak>Verifying…</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Step 2: Authenticator Setup Modal ───────────────────────────── --}}
    <div
        x-show="showSetup"
        x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center px-4 py-8 sm:py-16 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-label="Authenticator App Setup"
        @click.self="cancelSetup()"
    >
        {{-- Backdrop --}}
        <div
            x-show="showSetup"
            x-transition.opacity
            class="fixed inset-0 bg-neutral-900/50"
            aria-hidden="true"
            @click="cancelSetup()"
        ></div>

        {{-- Panel --}}
        <div
            x-show="showSetup"
            x-transition
            class="relative w-full sm:max-w-lg bg-white rounded-lg shadow-lg border border-neutral-200 my-auto"
        >
            {{-- Header --}}
            <header class="flex items-start justify-between gap-4 px-5 py-4 border-b border-neutral-200">
                <h2 class="text-base font-semibold text-neutral-900" x-text="recoveryRequired ? 'Authenticator App Reconfiguration' : 'Authenticator App Setup'">Authenticator App Setup</h2>
                <button
                    type="button"
                    @click="cancelSetup()"
                    class="p-1 -m-1 rounded text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    <span class="sr-only">Close</span>
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </header>

            {{-- Body --}}
            <div class="p-5 space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Scan this QR code using your authenticator app.</h3>
                    <p class="mt-1 text-xs leading-5 text-neutral-600">
                        In Google Authenticator, tap the plus button, choose <span class="font-medium">Scan a QR code</span>, and scan the image below.
                    </p>
                </div>

                {{-- QR Code --}}
                <div class="flex justify-center rounded-lg border border-neutral-200 bg-white p-3">
                    <img
                        :src="qrCode"
                        @if(!empty($authenticatorSetup['qr_code'])) src="{{ $authenticatorSetup['qr_code'] }}" @endif
                        alt="Authenticator app setup QR code"
                        width="240"
                        height="240"
                        class="h-[240px] w-[240px] max-w-full object-contain"
                    />
                </div>

                {{-- Manual key --}}
                <div>
                    <p class="text-xs font-medium text-neutral-800">Can't scan? Enter this setup key manually:</p>
                    <code
                        class="mt-1.5 block break-all rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-center font-mono text-sm font-semibold tracking-[0.18em] text-neutral-900"
                        x-text="secret"
                    >{{ $authenticatorSetup['secret'] ?? '' }}</code>
                    <p class="mt-1 text-[11px] text-neutral-500">Type: Time based &middot; Digits: 6 &middot; Period: 30 seconds</p>
                </div>

                {{-- TOTP verification form --}}
                <form @submit.prevent="submitCode" class="space-y-4 pt-1 border-t border-neutral-100">
                    <div>
                        <x-input-label for="modal-authenticator-code" :value="__('Enter the 6-digit code from your authenticator app')" />
                        <input
                            id="modal-authenticator-code"
                            type="text"
                            x-model="code"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-center font-mono text-xl tracking-[0.45em] shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            :class="codeError ? '!border-danger-500 focus:!border-danger-500 focus:!ring-danger-500' : ''"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                        />
                        <p x-show="codeError" x-text="codeError" class="mt-2 text-sm text-danger-600" x-cloak></p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <button
                            type="button"
                            @click="cancelSetup()"
                            class="text-sm font-medium text-neutral-600 hover:text-neutral-900"
                        >
                            Cancel setup
                        </button>
                        <x-ui.button
                            type="submit"
                            icon="shield-check"
                            ::disabled="verifyLoading"
                        >
                            <span x-show="!verifyLoading" x-text="recoveryRequired ? 'Verify & Reconfigure' : 'Verify & Enable'">{{ __('Verify & Enable') }}</span>
                            <span x-show="verifyLoading" x-cloak>Verifying…</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
