<section
    class="py-4 first:pt-0"
    x-data="{
        {{-- State --}}
        enabled: {{ $user->authenticatorMfaEnabled() ? 'true' : 'false' }},
        recoveryRequired: {{ $authenticatorRecoveryRequired ? 'true' : 'false' }},

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
            if (this.passwordLoading) return;
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
            if (this.verifyLoading) return;
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
                this.code = '';
                this.qrCode = '';
                this.secret = '';
                this.updateScrollLock();
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        type: 'success',
                        title: 'Success',
                        message: data.message || 'Authenticator app enabled successfully.',
                    }
                }));
            } catch (e) {
                this.codeError = 'Network error. Please check your connection and try again.';
            } finally {
                this.verifyLoading = false;
            }
        },

        async cancelSetup() {
            if (this.verifyLoading) return;
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
    x-init="if (showSetup) updateScrollLock(); @if ($errors->authenticatorDisable->any()) $nextTick(() => $dispatch('open-modal', 'manage-authenticator')); @endif"
    x-on:keydown.escape.window="if (showSetup) { cancelSetup() } else if (showPasswordModal) { closePasswordModal() }"
>
    @php($authenticatorSuccess = session()->pull('authenticator_success'))

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Authenticator App') }}</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ __('Use a standards-compatible authenticator app for secure sign-in.') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <span x-show="recoveryRequired" x-cloak><x-ui.badge status="action_required" dot>Repair required</x-ui.badge></span>
            <span x-show="!recoveryRequired && enabled" x-cloak><x-ui.badge status="active" dot>Enabled</x-ui.badge></span>
            <span x-show="!recoveryRequired && !enabled && showSetup" x-cloak><x-ui.badge status="pending" dot>Verification pending</x-ui.badge></span>
            <span x-show="!recoveryRequired && !enabled && !showSetup" x-cloak><x-ui.badge status="inactive" dot>Disabled</x-ui.badge></span>

            <x-ui.button type="button" size="sm" variant="secondary" x-show="recoveryRequired" x-cloak @click="openPasswordModal()">Reconfigure</x-ui.button>
            <x-ui.button type="button" size="sm" variant="secondary" x-show="!recoveryRequired && enabled" x-cloak @click="$dispatch('open-modal', 'manage-authenticator')">Manage</x-ui.button>
            <x-ui.button type="button" size="sm" variant="secondary" x-show="!recoveryRequired && !enabled && !showSetup" x-cloak @click="openPasswordModal()">Configure</x-ui.button>
        </div>
    </div>

    {{-- Server-rendered success (e.g. from disable flow or page reload) dispatched to global toast HUD --}}
    @if ($authenticatorSuccess)
        <div x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'success', title: 'Success', message: @js($authenticatorSuccess) } })))"></div>
    @endif

    @if ($errors->authenticatorEnable->any() || $errors->authenticatorSetup->any())
        <x-ui.alert variant="danger" title="Authenticator error" class="mt-3">
            {{ $errors->authenticatorEnable->first() ?: $errors->authenticatorSetup->first() }}
        </x-ui.alert>
    @endif

    {{-- ── Enabled state: Turn OFF form ────────────────────────────────── --}}
    <x-ui.modal name="manage-authenticator" :title="__('Manage Authenticator App')" maxWidth="md">
        @if ($errors->authenticatorDisable->any())
            <x-ui.alert variant="danger" title="Could not disable authenticator" class="mb-4">{{ $errors->authenticatorDisable->first() }}</x-ui.alert>
        @endif
        <p class="mb-4 text-sm leading-5 text-neutral-600 dark:text-neutral-300">Authenticator verification is active. To disable it, confirm your current password and a code from your app.</p>

            <form
                method="POST"
                action="{{ route('profile.authenticator.disable') }}"
                class="space-y-4"
                data-confirm-title="Disable authenticator app?"
                autocomplete="off"
                data-confirm-message="This removes authenticator verification from future sign-ins. Your password and current authenticator code will be verified first."
                data-confirm-label="Disable authenticator"
            >
                @csrf
                @method('delete')

                <div>
                    <x-input-label for="authenticator-disable-password" :value="__('Current password')" />
                    <x-text-input
                        id="authenticator-disable-password"
                        name="current_password"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        style="-webkit-text-security: disc; text-security: disc;"
                        required
                    />
                    <x-input-error :messages="$errors->authenticatorDisable->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="authenticator-disable-code" :value="__('6-digit authenticator code')" />
                    <x-text-input id="authenticator-disable-code" name="code" type="text" class="mt-1 block w-full font-mono tracking-[0.35em]" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required />
                    <x-input-error :messages="$errors->authenticatorDisable->get('code')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'manage-authenticator')">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="danger" data-loading-text="Disabling authenticator...">Disable authenticator</x-ui.button>
                </div>
            </form>
    </x-ui.modal>

    <template x-if="recoveryRequired">
        <p class="mt-2 text-xs leading-5 text-danger-700 dark:text-danger-300">
            Your saved authenticator setup cannot be verified. It remains enforced for account safety. Reconfigure the app to repair it.
        </p>
    </template>

    {{-- ── Disabled state: Turn ON action ──────────────────────────────── --}}
    <p x-show="!enabled && !recoveryRequired" x-cloak class="mt-2 text-xs leading-5 text-neutral-600 dark:text-neutral-300">
        Setup remains disabled until you verify a code from your authenticator app.
    </p>

    {{-- ── Step 1: Current Password Confirmation Modal ────────────────── --}}
    <div
        x-show="showPasswordModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Confirm Password"
        @click.self="closePasswordModal()"
    >
        {{-- Backdrop --}}
        <div
            x-show="showPasswordModal"
            x-transition.opacity
            class="fixed inset-0 bg-neutral-900/60 dark:bg-black/70"
            aria-hidden="true"
            @click="closePasswordModal()"
        ></div>

        {{-- Panel --}}
        <div
            x-show="showPasswordModal"
            x-transition
            class="relative my-auto flex max-h-[calc(100dvh-2rem)] w-full flex-col overflow-y-auto rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900 sm:max-w-md"
        >
            <header class="flex items-start justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100" x-text="recoveryRequired ? 'Confirm Password to Reconfigure' : 'Confirm Current Password'">Confirm Current Password</h2>
                    <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-300">Please verify your identity to continue.</p>
                </div>
                <button
                    type="button"
                    @click="closePasswordModal()"
                    class="-m-1 rounded p-1 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                >
                    <span class="sr-only">Close</span>
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </header>

            <form @submit.prevent="submitPassword" autocomplete="off" class="p-5 space-y-4">
                <div>
                    <x-input-label for="authenticator-modal-password" :value="__('Current password')" />
                    <x-text-input
                        id="authenticator-modal-password"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        style="-webkit-text-security: disc; text-security: disc;"
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
                        class="inline-flex items-center justify-center rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
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
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Authenticator App Setup"
        @click.self="cancelSetup()"
    >
        {{-- Backdrop --}}
        <div
            x-show="showSetup"
            x-transition.opacity
            class="fixed inset-0 bg-neutral-900/60 dark:bg-black/70"
            aria-hidden="true"
            @click="cancelSetup()"
        ></div>

        {{-- Panel --}}
        <div
            x-show="showSetup"
            x-transition
            class="relative my-auto flex max-h-[calc(100dvh-2rem)] w-full flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900 sm:max-w-lg"
        >
            {{-- Header --}}
            <header class="flex items-start justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-800">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100" x-text="recoveryRequired ? 'Authenticator App Reconfiguration' : 'Authenticator App Setup'">Authenticator App Setup</h2>
                <button
                    type="button"
                    @click="cancelSetup()"
                    class="-m-1 rounded p-1 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                >
                    <span class="sr-only">Close</span>
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </header>

            {{-- Body --}}
            <div class="min-h-0 space-y-5 overflow-y-auto p-5">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Scan this QR code using your authenticator app.</h3>
                    <p class="mt-1 text-xs leading-5 text-neutral-600 dark:text-neutral-300">
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
                    <p class="text-xs font-medium text-neutral-800 dark:text-neutral-200">Can't scan? Enter this setup key manually:</p>
                    <code
                        class="mt-1.5 block break-all rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-center font-mono text-sm font-semibold tracking-[0.18em] text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                        x-text="secret"
                    >{{ $authenticatorSetup['secret'] ?? '' }}</code>
                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-300">Type: Time based &middot; Digits: 6 &middot; Period: 30 seconds</p>
                </div>

                {{-- TOTP verification form --}}
                <form @submit.prevent="submitCode" class="space-y-4 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <div>
                        <x-input-label for="modal-authenticator-code" :value="__('Enter the 6-digit code from your authenticator app')" />
                        <input
                            id="modal-authenticator-code"
                            type="text"
                            x-model="code"
                            class="mt-1 block w-full rounded-md border-neutral-300 bg-white text-center font-mono text-xl tracking-[0.45em] text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
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
                            class="text-sm font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white"
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
