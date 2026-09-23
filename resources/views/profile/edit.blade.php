<x-app-layout>
    <x-ui.page-header
        :title="$user->isAdministrator() ? __('Account Settings') : __('Profile')"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            ($user->isAdministrator() ? __('Account Settings') : __('Profile')) => null,
        ]"
    />

    @php
        $settingsNotifications = collect([
            'avatar_success',
            'profile_success',
            'password_success',
            'sms_mfa_success',
            'mfa_success',
            'session_reminder_success',
        ])->map(fn ($key) => session()->pull($key))->filter();
    @endphp
    @foreach ($settingsNotifications as $message)
        <div x-data x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'success', title: 'Success', message: $el.textContent.trim() } })))" class="sr-only" role="status">{{ $message }}</div>
    @endforeach

    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <div class="min-w-0 space-y-4">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-theme-form')
            @include('profile.partials.account-retention-notice')
        </div>

        <x-ui.card
            title="Sign-in & Security"
            subtitle="Manage how you sign in and protect your account."
        >
            <div class="divide-y divide-neutral-200 dark:divide-neutral-800">
                @include('profile.partials.update-authenticator-form')
                @include('profile.partials.update-sms-mfa-form')
                @if ($user->isAdministrator())
                    @include('profile.partials.update-mfa-form')
                @endif
                @include('profile.partials.update-session-timeout-reminder-form')
                @include('profile.partials.update-password-form')
            </div>
        </x-ui.card>
    </div>

    @include('profile.partials.update-profile-picture-modal')
    @include('profile.partials.privacy-request-modal')
</x-app-layout>
