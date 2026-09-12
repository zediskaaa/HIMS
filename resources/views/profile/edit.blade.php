<x-app-layout>
    <x-ui.page-header
        :title="$user->isAdministrator() ? __('Account Settings') : __('Profile')"
        :subtitle="__('Manage your account credentials, multi-factor authentication, and session preferences.')"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            ($user->isAdministrator() ? __('Account Settings') : __('Profile')) => null,
        ]"
    />

    @if (session('avatar_success'))
        <x-ui.alert variant="success" title="{{ __('Profile picture updated') }}" dismissible class="mb-6">
            {{ session('avatar_success') }}
        </x-ui.alert>
    @endif

    {{-- Account Profile Summary Card --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6 mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
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
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold text-neutral-900 truncate">{{ $user->name }}</h2>
                        <x-ui.badge :variant="$user->isAdministrator() ? 'primary' : 'neutral'">
                            {{ $user->role->label() }}
                        </x-ui.badge>
                        <x-ui.badge :status="$user->status->value" dot>{{ $user->status->label() }}</x-ui.badge>
                    </div>
                    <p class="mt-0.5 text-sm text-neutral-500 truncate">{{ $user->email }}</p>
                </div>
            </div>
            @if ($user->department || $user->employee_id)
                <div class="flex flex-wrap items-center gap-4 border-t border-neutral-100 pt-3 sm:border-t-0 sm:pt-0 text-xs text-neutral-500">
                    @if ($user->employee_id)
                        <div>
                            <span class="font-medium uppercase tracking-wider text-neutral-400 text-[10px]">Employee ID</span>
                            <span class="ml-1 font-mono font-semibold text-neutral-800">{{ $user->employee_id }}</span>
                        </div>
                    @endif
                    @if ($user->department)
                        <div>
                            <span class="font-medium uppercase tracking-wider text-neutral-400 text-[10px]">Department</span>
                            <span class="ml-1 font-semibold text-neutral-800">{{ $user->department }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Two-column responsive layout for settings --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 items-start">
        {{-- Column 1: Personal Details & Password --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- Column 2: Security & Session Preferences --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-authenticator-form')
            </div>

            @if ($user->isAdministrator())
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                    @include('profile.partials.update-mfa-form')
                </div>
            @endif

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-session-timeout-reminder-form')
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                @include('profile.partials.account-retention-notice')
            </div>
        </div>
    </div>

    {{-- Profile Picture Modal --}}
    @include('profile.partials.update-profile-picture-modal')
</x-app-layout>
