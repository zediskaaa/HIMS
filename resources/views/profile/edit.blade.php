<x-app-layout>
    <x-ui.page-header
        :title="$user->isAdministrator() ? __('Account Settings') : __('Profile')"
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


    {{-- Two-column responsive layout for settings --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 items-start">
        {{-- Column 1: Personal Details & Password --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-password-form')
            </div>

            @include('profile.partials.update-theme-form')
        </div>

        {{-- Column 2: Security & Session Preferences --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-authenticator-form')
            </div>

            @if ($user->isAdministrator())
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm sm:p-6">
                    @include('profile.partials.update-mfa-form')
                </div>
            @endif

            <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm sm:p-6">
                @include('profile.partials.update-session-timeout-reminder-form')
            </div>

            <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm sm:p-6">
                @include('profile.partials.account-retention-notice')
            </div>
        </div>
    </div>

    {{-- Profile Picture Modal --}}
    @include('profile.partials.update-profile-picture-modal')

    {{-- Privacy Data Subject Request Modal --}}
    @include('profile.partials.privacy-request-modal')
</x-app-layout>
