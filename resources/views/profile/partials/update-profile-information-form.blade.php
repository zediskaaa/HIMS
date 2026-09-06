<section>
    @php
        $nameComponents = $user->nameComponents();
        $profileSuccess = session()->pull('profile_success');
    @endphp

    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    @if ($profileSuccess)
        <x-ui.alert
            variant="success"
            :title="$profileSuccess === 'Email updated successfully.' ? 'Email updated' : 'Profile updated'"
            dismissible
            class="mt-6"
        >
            {{ $profileSuccess }}
        </x-ui.alert>
    @endif

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label for="surname" :value="__('Last Name')" />
                <x-text-input
                    id="surname"
                    name="surname"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('surname', $nameComponents['surname'])"
                    placeholder="e.g. Dela Cruz"
                    maxlength="80"
                    required
                    autofocus
                    autocomplete="family-name"
                />
                <x-input-error class="mt-2" :messages="$errors->get('surname')" />
            </div>

            <div>
                <x-input-label for="first_name" :value="__('First Name')" />
                <x-text-input
                    id="first_name"
                    name="first_name"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('first_name', $nameComponents['first_name'])"
                    placeholder="e.g. Juan"
                    maxlength="80"
                    required
                    autocomplete="given-name"
                />
                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            </div>

            <div class="sm:col-span-2 lg:col-span-1">
                <x-input-label for="middle_name" :value="__('Middle Name')" />
                <x-text-input
                    id="middle_name"
                    name="middle_name"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('middle_name', $nameComponents['middle_name'])"
                    placeholder="e.g. Santos (optional)"
                    maxlength="80"
                    autocomplete="additional-name"
                />
                <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
            </div>
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                          :value="old('email', $user->email)"
                          required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="profile_current_password" :value="__('Current Password')" />
            <x-text-input
                id="profile_current_password"
                name="current_password"
                type="password"
                class="mt-1 block w-full {{ $errors->has('current_password') ? '!border-danger-500 focus:!border-danger-500 focus:!ring-danger-500' : '' }}"
                autocomplete="current-password"
            />
            <p class="mt-2 text-sm text-gray-600">
                {{ __('Required only when changing your email address.') }}
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
        </div>

        <div class="flex items-center">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
