<x-app-layout>
    <x-ui.page-header
        :title="$user->isAdministrator() ? __('Account Settings') : __('Profile')"
        :subtitle="__('Manage your account credentials, multi-factor authentication, and session preferences.')"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            ($user->isAdministrator() ? __('Account Settings') : __('Profile')) => null,
        ]"
    />

    {{-- Account Profile Summary Card --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-800">
                    {{ $user->initials() }}
                </span>
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
</x-app-layout>
