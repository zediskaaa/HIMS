<section x-data="{ enabled: {{ $user->mfa_enabled ? 'true' : 'false' }} }">
    @php($mfaSuccess = session()->pull('mfa_success'))

    <header>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-gray-900">
                    {{ __('Multi-Factor Authentication') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Require a one-time email code after your password is accepted.') }}
                </p>
            </div>
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                x-bind:class="enabled ? 'bg-success-50 text-success-700' : 'bg-neutral-100 text-neutral-600'"
                x-text="enabled ? 'ON' : 'OFF'"
            >{{ $user->mfa_enabled ? 'ON' : 'OFF' }}</span>
        </div>
    </header>

    @if ($mfaSuccess)
        <x-ui.alert variant="success" title="MFA setting updated" dismissible class="mt-6">
            {{ $mfaSuccess }}
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('profile.mfa.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')
        <input type="hidden" name="mfa_enabled" value="0">

        <label class="flex cursor-pointer items-center justify-between gap-5 rounded-lg border border-neutral-200 p-4">
            <span>
                <span class="block text-sm font-medium text-neutral-900">Email verification code</span>
                <span class="mt-1 block text-sm leading-5 text-neutral-500">
                    Codes expire quickly, can be used only once, and are sent to your registered email address.
                </span>
            </span>
            <span class="relative inline-flex shrink-0 items-center">
                <input
                    type="checkbox"
                    name="mfa_enabled"
                    value="1"
                    class="peer sr-only"
                    x-model="enabled"
                    @checked($user->mfa_enabled)
                >
                <span class="h-6 w-11 rounded-full bg-neutral-300 transition peer-checked:bg-primary-600 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2"></span>
                <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
            </span>
        </label>

        <x-input-error :messages="$errors->get('mfa_enabled')" class="text-danger-600" />

        <div class="flex items-center">
            <x-primary-button>{{ __('Save MFA setting') }}</x-primary-button>
        </div>
    </form>
</section>
