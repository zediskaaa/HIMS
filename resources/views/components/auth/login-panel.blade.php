@props([
    'action',
    'badge' => null,
    'heading' => 'Sign in to HIMS',
    'description' => 'Access procurement, inventory, and hospital supply operations from one workspace.',
    'submitLabel' => 'Sign in',
    'forgotPasswordUrl',
    'variant' => 'staff',
    'loginRestriction' => null,
])

@php
    $isAdmin = $variant === 'admin';
    $isSuperAdmin = $variant === 'super-admin';
    $panelGuard = $isSuperAdmin ? 'super_admin' : ($isAdmin ? 'admin' : 'web');
    $timeoutContext = session('session_timeout_context');
    $hasSessionTimeout = session()->has('session_timeout')
        && (!$timeoutContext || $timeoutContext['guard'] === $panelGuard);
    $loginEmail = old('email', $loginRestriction['email'] ?? null);
    $restrictionSeconds = $loginRestriction
        ? max(0, (int) $loginRestriction['expires_at'] - now()->getTimestamp())
        : 0;
    if ($hasSessionTimeout && !request()->ajax()
        && !request()->expectsJson()
        && in_array(request()->header('Sec-Fetch-Mode'), [null, 'navigate'], true)) {
        session()->forget(['session_timeout', 'session_timeout_context']);
    }
@endphp

<div class="space-y-8">
    @if ($isSuperAdmin)
        <header class="-mx-6 -mt-6 animate-fade-up border-b border-neutral-800 bg-neutral-950 px-6 py-7 text-white [animation-delay:320ms] sm:-mx-8 sm:-mt-8 sm:px-8">
            <div>
                @if ($badge)
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-warning-500">{{ $badge }}</p>
                @endif
                <h1 class="{{ $badge ? 'mt-2' : '' }} text-3xl font-semibold tracking-tight text-white">{{ $heading }}</h1>
                <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-300">{{ $description }}</p>
            </div>
        </header>
    @elseif ($isAdmin)
        <header class="animate-fade-up [animation-delay:320ms]">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-primary-100 bg-primary-50 text-primary-700 shadow-sm dark:border-primary-900/60 dark:bg-primary-950/60 dark:text-primary-300">
                    <x-ui.icon name="users" class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary-700 dark:text-primary-300">{{ $badge }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">{{ $heading }}</h1>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ $description }}</p>
                </div>
            </div>
            @unless ($hasSessionTimeout)
                <div class="mt-5 rounded-lg border border-primary-100 bg-primary-50/70 px-3.5 py-3 text-xs leading-5 text-primary-900 dark:border-primary-900/60 dark:bg-primary-950/50 dark:text-primary-200">
                    Access is limited to the administration modules assigned to your account.
                </div>
            @endunless
        </header>
    @else
        <header class="animate-fade-up [animation-delay:320ms]">
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">{{ $heading }}</h1>
            <p class="mt-3 max-w-sm text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ $description }}</p>
        </header>
    @endif

    <x-auth-session-status
        class="animate-fade-up rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700 dark:border-success-900/60 dark:bg-success-950/40 dark:text-success-300 [animation-delay:380ms]"
        :status="session('status')"
    />

    <x-auth.wrong-panel-alert />

    @if ($loginRestriction)
        <x-ui.alert
            :variant="$loginRestriction['status'] === \App\Services\LoginLockoutService::LOCKED ? 'danger' : 'warning'"
            :title="$loginRestriction['status'] === \App\Services\LoginLockoutService::LOCKED ? 'Account temporarily locked' : 'Sign-in temporarily paused'"
            class="animate-fade-up [animation-delay:400ms]"
            data-login-cooldown
            data-login-cooldown-email="{{ $loginRestriction['email'] }}"
            data-login-cooldown-expires-at="{{ $loginRestriction['expires_at'] }}"
            data-login-cooldown-server-now="{{ now()->getTimestamp() }}"
        >
            @if ($loginRestriction['status'] === \App\Services\LoginLockoutService::WAITING
                && $loginRestriction['attempts_remaining'] !== null)
                <p>You have {{ $loginRestriction['attempts_remaining'] }} {{ str('attempt')->plural($loginRestriction['attempts_remaining']) }} remaining.</p>
            @endif
            <p>
                Try again in
                <span class="font-mono font-semibold tabular-nums" data-login-cooldown-value aria-hidden="true">
                    {{ sprintf('%02d:%02d', intdiv($restrictionSeconds, 60), $restrictionSeconds % 60) }}
                </span>
            </p>
            <p class="sr-only" data-login-cooldown-live aria-live="polite" aria-atomic="true"></p>
        </x-ui.alert>
    @endif

    @if ($hasSessionTimeout)
        <x-ui.alert
            variant="warning"
            title="Session Timeout"
            dismissible
            class="animate-fade-up [animation-delay:400ms]"
        >
            Your session has expired due to inactivity. Please log in again.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ $action }}" class="animate-fade-up space-y-5 [animation-delay:440ms]" x-data="{ showPassword: false }" autocomplete="off" data-login-form>
        @csrf
        <input type="hidden" name="latitude" data-login-latitude>
        <input type="hidden" name="longitude" data-login-longitude>
        <input type="hidden" name="accuracy" data-login-accuracy>

        <div>
            <x-input-label for="email" :value="__('Email address')" class="text-neutral-700 dark:text-neutral-300" />
            <x-text-input
                id="email"
                class="mt-2 block h-11 w-full rounded-lg bg-white px-3.5 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500 {{ $errors->has('email') ? '!border-danger-500 focus:!border-danger-500' : 'border-neutral-300 focus:border-primary-500' }}"
                type="email"
                name="email"
                :value="$loginEmail"
                placeholder="name@hospital.org"
                required
                autofocus
                autocomplete="off"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-danger-600" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" class="text-neutral-700 dark:text-neutral-300" />

            <div class="relative mt-2">
                <style>
                    #password::-ms-reveal,
                    #password::-ms-clear {
                        display: none;
                    }
                </style>
                <x-text-input
                    id="password"
                    class="block h-11 w-full rounded-lg bg-white px-3.5 pr-11 text-sm text-neutral-900 shadow-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 {{ $errors->has('email') || $errors->has('password') ? '!border-danger-500 focus:!border-danger-500' : 'border-neutral-300 focus:border-primary-500' }} focus:ring-primary-500"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="new-password"
                />

                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-neutral-400 transition-colors hover:text-neutral-700 focus-visible:ring-inset dark:text-neutral-500 dark:hover:text-neutral-200"
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

            <div class="mt-2">
                <a
                    class="rounded text-xs font-medium text-primary-600 transition-colors hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    href="{{ $forgotPasswordUrl }}"
                >
                    {{ __('Forgot password?') }}
                </a>
            </div>
        </div>

        <x-ui.button
            type="submit"
            size="lg"
            data-loading-text="Signing in..."
            class="w-full {{ $isSuperAdmin ? '!border-neutral-900 !bg-neutral-900 hover:!border-primary-950 hover:!bg-primary-950 focus-visible:!ring-warning-500 dark:!border-neutral-100 dark:!bg-neutral-100 dark:!text-neutral-900 dark:hover:!border-white dark:hover:!bg-white' : '' }}"
        >
            {{ $submitLabel }}
        </x-ui.button>
    </form>
</div>
