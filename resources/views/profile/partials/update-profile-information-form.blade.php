<section>
    @php
        $nameComponents = $user->nameComponents();
        $profileSuccess = session()->pull('profile_success');
    @endphp

    <header class="flex items-center gap-4 pb-4 border-b border-neutral-100">
        {{-- Interactive Avatar Trigger with hover overlay and pencil-square badge --}}
        <div
            x-data
            x-on:click="$dispatch('open-modal', 'update-profile-picture')"
            class="relative group cursor-pointer shrink-0 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            title="{{ __('Click to change profile picture') }}"
            role="button"
            tabindex="0"
            x-on:keydown.enter="$dispatch('open-modal', 'update-profile-picture')"
            x-on:keydown.space.prevent="$dispatch('open-modal', 'update-profile-picture')"
        >
            <x-ui.avatar :user="$user" size="lg" class="ring-2 ring-primary-500/20 shadow-xs group-hover:ring-primary-500 transition-all" />

            {{-- Hover overlay on avatar image --}}
            <div class="absolute inset-0 rounded-full bg-neutral-900/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity" aria-hidden="true">
                <x-ui.icon name="camera" class="h-5 w-5 text-white drop-shadow-xs" />
            </div>

            {{-- Floating pencil-square badge at bottom-right corner --}}
            <span class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white text-neutral-600 shadow-sm ring-1 ring-neutral-300 group-hover:bg-primary-50 group-hover:text-primary-600 group-hover:ring-primary-400 transition-all">
                <x-ui.icon name="pencil-square" class="h-3.5 w-3.5" />
            </span>
        </div>

        <div class="min-w-0">
            <h2 class="text-base font-bold text-neutral-900">
                {{ __('Profile Information') }}
            </h2>

            <p class="mt-0.5 text-xs text-neutral-500">
                {{ __("Update your account's profile information and email address.") }}
            </p>

            <button
                type="button"
                x-data
                x-on:click="$dispatch('open-modal', 'update-profile-picture')"
                class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700 hover:underline"
            >
                <x-ui.icon name="camera" class="w-3.5 h-3.5" />
                <span>{{ $user->hasAvatar() ? __('Change photo') : __('Upload photo') }}</span>
            </button>
        </div>
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

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6"
          autocomplete="off"
          data-confirm-email-change
          data-original-email="{{ $user->email }}">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
                    autocomplete="off"
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
                    autocomplete="off"
                />
                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            </div>

            <div class="sm:col-span-2 xl:col-span-1">
                <x-input-label for="middle_name" :value="__('Middle Name')" />
                <x-text-input
                    id="middle_name"
                    name="middle_name"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('middle_name', $nameComponents['middle_name'])"
                    placeholder="e.g. Santos (optional)"
                    maxlength="80"
                    autocomplete="off"
                />
                <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
            </div>
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                          :value="old('email', $user->email)"
                          required autocomplete="off" />
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
                autocomplete="new-password"
                x-data
                x-init="$nextTick(() => { $el.value = '' })"
                value=""
            />
            <p class="mt-2 text-sm text-gray-600">
                {{ __('Required only when changing your email address.') }}
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
        </div>

        <div class="flex items-center pt-2">
            <x-ui.button type="submit" data-loading-text="Saving profile...">{{ __('Save') }}</x-ui.button>
        </div>
    </form>
</section>
