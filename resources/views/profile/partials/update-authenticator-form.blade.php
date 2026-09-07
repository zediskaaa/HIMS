<section>
    @php($authenticatorSuccess = session()->pull('authenticator_success'))

    <header>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-gray-900">{{ __('Authenticator App') }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Use Google Authenticator or another standards-compatible TOTP app.') }}
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $user->authenticatorMfaEnabled() ? 'bg-success-50 text-success-700' : 'bg-neutral-100 text-neutral-600' }}">
                {{ $user->authenticatorMfaEnabled() ? 'ON' : 'OFF' }}
            </span>
        </div>
    </header>

    @if ($authenticatorSuccess)
        <x-ui.alert variant="success" title="Authenticator setting updated" dismissible class="mt-6">
            {{ $authenticatorSuccess }}
        </x-ui.alert>
    @endif

    @if ($errors->authenticatorEnable->has('authenticator') || $errors->authenticatorDisable->has('authenticator'))
        <x-ui.alert variant="danger" title="Authenticator error" class="mt-6">
            {{ $errors->authenticatorEnable->first('authenticator') ?: $errors->authenticatorDisable->first('authenticator') }}
        </x-ui.alert>
    @endif

    @if ($user->authenticatorMfaEnabled())
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
    @elseif ($authenticatorSetup)
        <div class="mt-6 space-y-6">
            <div>
                <h3 class="text-base font-semibold text-neutral-900">Scan this QR code using your authenticator app.</h3>
                <p class="mt-1 text-sm leading-6 text-neutral-600">
                    In Google Authenticator, tap the plus button, choose <span class="font-medium">Scan a QR code</span>, and scan the image below.
                </p>
            </div>

            <div class="flex justify-center rounded-lg border border-neutral-200 bg-white p-4">
                <img src="{{ $authenticatorSetup['qr_code'] }}" alt="Authenticator app setup QR code" width="280" height="280" class="h-[280px] w-[280px] max-w-full" />
            </div>

            <div>
                <p class="text-sm font-medium text-neutral-800">Can't scan? Enter this setup key manually:</p>
                <code class="mt-2 block break-all rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-center font-mono text-base font-semibold tracking-[0.18em] text-neutral-900">{{ $authenticatorSetup['secret'] }}</code>
                <p class="mt-2 text-xs text-neutral-500">Type: Time based &middot; Digits: 6 &middot; Period: 30 seconds</p>
            </div>

            <form method="POST" action="{{ route('profile.authenticator.enable') }}" class="space-y-5">
                @csrf
                <div>
                    <x-input-label for="authenticator-enable-code" :value="__('Enter the 6-digit code from your authenticator app')" />
                    <x-text-input id="authenticator-enable-code" name="code" type="text" class="mt-1 block w-full text-center font-mono text-xl tracking-[0.45em]" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required />
                    <x-input-error :messages="$errors->authenticatorEnable->get('code')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button type="submit" icon="shield-check" data-loading-text="Verifying authenticator...">
                        {{ __('Verify & Enable') }}
                    </x-ui.button>
                </div>
            </form>

            <form method="POST" action="{{ route('profile.authenticator.cancel') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-neutral-600 hover:text-neutral-900">Cancel setup</button>
            </form>
        </div>
    @else
        <p class="mt-6 text-sm leading-6 text-neutral-600">
            Turning this on starts a secure setup. It will remain OFF until a code from your authenticator app is verified.
        </p>

        <form method="POST" action="{{ route('profile.authenticator.setup') }}" class="mt-5 space-y-5">
            @csrf
            <div>
                <x-input-label for="authenticator-setup-password" :value="__('Current password')" />
                <x-text-input id="authenticator-setup-password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                <x-input-error :messages="$errors->authenticatorSetup->get('current_password')" class="mt-2" />
            </div>

            <x-ui.button type="submit" icon="shield-check" data-loading-text="Preparing authenticator setup...">
                {{ __('Turn ON Authenticator App') }}
            </x-ui.button>
        </form>
    @endif
</section>
