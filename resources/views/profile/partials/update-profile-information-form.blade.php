<section>
    @php($nameComponents = $user->nameComponents())

    <x-ui.card :padding="false">
        <div class="p-4 sm:p-5">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'update-profile-picture')"
                        class="shrink-0 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        aria-label="{{ $user->hasAvatar() ? __('Change profile photo') : __('Upload profile photo') }}">
                    <x-ui.avatar :user="$user" size="lg" class="ring-2 ring-primary-500/20" />
                </button>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Profile Information') }}</h2>
                    <p class="mt-1 break-words text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ $user->name }}</p>
                    <p class="break-all text-xs text-neutral-600 dark:text-neutral-300">{{ $user->email }}</p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button type="button" size="sm" x-data x-on:click="$dispatch('open-modal', 'edit-profile')">{{ __('Edit profile') }}</x-ui.button>
                <x-ui.button type="button" size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'update-profile-picture')">
                    {{ $user->hasAvatar() ? __('Change photo') : __('Upload photo') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.card>

    <div x-data @if ($errors->hasAny(['surname', 'first_name', 'middle_name', 'email', 'current_password']))
        x-init="$nextTick(() => $dispatch('open-modal', 'edit-profile'))"
    @endif>
    <x-ui.modal name="edit-profile" :title="__('Edit profile')" maxWidth="xl">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-4"
          autocomplete="off"
          data-confirm-email-change
          data-original-email="{{ $user->email }}">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

            <div class="sm:col-span-2">
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
                    <p class="mt-2 text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="rounded-md text-sm text-primary-700 underline hover:text-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-primary-300">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-success-700 dark:text-success-300">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div x-data="{ currentPassword: '' }">
            <x-input-label for="profile_current_password" :value="__('Current Password')" />
            <x-text-input
                id="profile_current_password"
                name="current_password"
                type="text"
                class="mt-1 block w-full {{ $errors->has('current_password') ? '!border-danger-500 focus:!border-danger-500 focus:!ring-danger-500' : '' }}"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                data-lpignore="true"
                data-1p-ignore="true"
                data-form-type="other"
                style="-webkit-text-security: disc; text-security: disc;"
                x-model="currentPassword"
                x-init="$el.value = ''; setTimeout(() => { $el.value = ''; currentPassword = ''; }, 50); setTimeout(() => { $el.value = ''; currentPassword = ''; }, 200)"
                value=""
            />
            <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-300">
                {{ __('Required only when changing your email address.') }}
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'edit-profile')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" data-loading-text="Saving profile...">{{ __('Save') }}</x-ui.button>
        </div>
    </form>
    </x-ui.modal>
    </div>
</section>
