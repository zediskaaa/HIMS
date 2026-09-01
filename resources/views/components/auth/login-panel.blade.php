@props([
    'action',
    'badge' => 'Secure staff portal',
    'heading' => 'Sign in to HIMS',
    'description' => 'Access procurement, inventory, and hospital supply operations from one workspace.',
    'submitLabel' => 'Sign in',
    'forgotPasswordUrl',
    'variant' => 'staff',
])

@php
    $isAdmin = $variant === 'admin';
    $isSuperAdmin = $variant === 'super-admin';
@endphp

<div class="space-y-8">
    @if ($isSuperAdmin)
        <header class="-mx-6 -mt-6 animate-fade-up border-b border-neutral-800 bg-neutral-950 px-6 py-7 text-white [animation-delay:320ms] sm:-mx-8 sm:-mt-8 sm:px-8">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-warning-500/30 bg-warning-500/10 text-warning-500 shadow-sm shadow-black/30">
                    <x-ui.icon name="shield-check" class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-warning-500">{{ $badge }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">{{ $heading }}</h1>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-300">{{ $description }}</p>
                </div>
            </div>
            <div class="mt-5 flex items-center gap-2 border-t border-white/10 pt-4 text-xs text-neutral-400">
                <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                Highest privilege tier &middot; Activity is audited
            </div>
        </header>
    @elseif ($isAdmin)
        <header class="animate-fade-up [animation-delay:320ms]">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-primary-100 bg-primary-50 text-primary-700 shadow-sm">
                    <x-ui.icon name="users" class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary-700">{{ $badge }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">{{ $heading }}</h1>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-500">{{ $description }}</p>
                </div>
            </div>
            <div class="mt-5 rounded-lg border border-primary-100 bg-primary-50/70 px-3.5 py-3 text-xs leading-5 text-primary-900">
                Access is limited to the administration modules assigned to your account.
            </div>
        </header>
    @else
        <header class="animate-fade-up [animation-delay:320ms]">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">
                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                {{ $badge }}
            </div>

            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">{{ $heading }}</h1>
            <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-500">{{ $description }}</p>
        </header>
    @endif

    <x-auth-session-status
        class="animate-fade-up rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700 [animation-delay:380ms]"
        :status="session('status')"
    />

    <x-auth.wrong-panel-alert />

    @if (session('session_timeout'))
        <x-ui.alert
            variant="warning"
            title="Session Timeout"
            dismissible
            class="animate-fade-up [animation-delay:400ms]"
        >
            Your session has expired due to inactivity. Please log in again.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ $action }}" class="animate-fade-up space-y-5 [animation-delay:440ms]" x-data="{ showPassword: false }">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email address')" class="text-neutral-700" />
            <x-text-input
                id="email"
                class="mt-2 block h-11 w-full rounded-lg bg-white px-3.5 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:ring-primary-500 {{ $errors->has('email') ? '!border-danger-500 focus:!border-danger-500' : 'border-neutral-300 focus:border-primary-500' }}"
                type="email"
                name="email"
                :value="old('email')"
                placeholder="name@hospital.org"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-danger-600" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-4">
                <x-input-label for="password" :value="__('Password')" class="text-neutral-700" />

                <a
                    class="rounded text-xs font-medium text-primary-600 transition-colors hover:text-primary-700"
                    href="{{ $forgotPasswordUrl }}"
                >
                    {{ __('Forgot password?') }}
                </a>
            </div>

            <div class="relative mt-2">
                <x-text-input
                    id="password"
                    class="block h-11 w-full rounded-lg bg-white px-3.5 pr-11 text-sm text-neutral-900 shadow-sm {{ $errors->has('email') || $errors->has('password') ? '!border-danger-500 focus:!border-danger-500' : 'border-neutral-300 focus:border-primary-500' }} focus:ring-primary-500"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                />

                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-neutral-400 transition-colors hover:text-neutral-700 focus-visible:ring-inset"
                    x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                    x-bind:aria-pressed="showPassword"
                    aria-controls="password"
                >
                    <x-ui.icon name="eye" class="h-5 w-5" x-show="!showPassword" />
                    <x-ui.icon name="eye-slash" class="h-5 w-5" x-show="showPassword" x-cloak />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-danger-600" />
        </div>

        <label for="remember_me" class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-neutral-600">
            <input
                id="remember_me"
                type="checkbox"
                class="rounded border-neutral-300 text-primary-600 shadow-sm focus:ring-primary-500"
                name="remember"
            >
            <span>{{ __('Keep me signed in') }}</span>
        </label>

        <x-ui.button
            type="submit"
            size="lg"
            icon="arrow-right-on-rectangle"
            class="w-full {{ $isSuperAdmin ? '!border-neutral-900 !bg-neutral-900 hover:!border-primary-950 hover:!bg-primary-950 focus-visible:!ring-warning-500' : '' }}"
        >
            {{ $submitLabel }}
        </x-ui.button>
    </form>

    <div class="flex items-center gap-2.5 border-t border-neutral-200 pt-6 text-xs leading-5 text-neutral-500">
        <x-ui.icon name="shield-check" class="h-4 w-4 shrink-0 {{ $isSuperAdmin ? 'text-warning-600' : 'text-primary-600' }}" />
        <p>
            @if ($isSuperAdmin)
                Privileged access is monitored, time-limited, and recorded.
            @elseif ($isAdmin)
                Administrative actions are role-scoped and auditable.
            @else
                For authorized hospital personnel only. Your session is securely protected.
            @endif
        </p>
    </div>
</div>
