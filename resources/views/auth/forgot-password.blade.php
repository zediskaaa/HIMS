<x-guest-layout :title="$panel->label().' Password Reset'">
    <div class="space-y-7">
        <header>
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                {{ $panel->label() }} password recovery
            </div>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">Reset your password</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500">
                Enter your {{ strtolower($panel->label()) }} account email. If the account is eligible, we will send a secure, time-limited verification code.
            </p>
        </header>

        <x-auth.wrong-panel-alert />

        <x-auth-session-status
            class="rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700"
            :status="session('status')"
        />

        <form method="POST" action="{{ route($panel->passwordEmailRoute()) }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email address')" class="text-neutral-700" />
                <x-text-input
                    id="email"
                    class="mt-2 block h-11 w-full rounded-lg border-neutral-300 bg-white px-3.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    type="email"
                    name="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-danger-600" />
            </div>

            <x-ui.button type="submit" size="lg" class="w-full">
                {{ __('Email password reset code') }}
            </x-ui.button>
        </form>

        <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700" href="{{ route($panel->loginRoute()) }}">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Back to {{ $panel->label() }} Login
        </a>
    </div>
</x-guest-layout>
