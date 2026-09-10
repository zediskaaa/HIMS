@php
    $sessionActivityRoute = \App\Support\AuthenticationContext::activityRoute();
    $sessionExpiredRoute = \App\Support\AuthenticationContext::expiredRoute();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'HIMS') }}</title>

    {{-- Inter is loaded once, from resources/css/app.css --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="h-full font-sans antialiased bg-neutral-50 text-neutral-800"
    data-session-timeout-seconds="{{ (int) config('session.lifetime') * 60 }}"
    data-session-warning-seconds="{{ (int) config('session.warning_seconds') }}"
    data-session-activity-url="{{ route($sessionActivityRoute) }}"
    data-session-expired-url="{{ Illuminate\Support\Facades\URL::signedRoute($sessionExpiredRoute, absolute: false) }}"
>
    <div x-data="{ sidebarOpen: false }" class="hims-app-shell min-h-full overflow-x-clip">

        @include('layouts.partials.sidebar')

        {{-- Backdrop for the off-canvas sidebar on small screens --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            x-on:click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-neutral-900/40 lg:hidden"
            aria-hidden="true"
        ></div>

        <div class="w-full min-w-0 max-w-full lg:pl-64">
            @include('layouts.partials.topbar')

            <main class="hims-app-content overflow-x-clip px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mx-auto w-full min-w-0 max-w-7xl space-y-6">
                    {{-- Legacy pages pass a $header slot; new pages use <x-ui.page-header>. --}}
                    @isset($header)
                        <div>{{ $header }}</div>
                    @endisset

                    @if (session('status') || session('success'))
                        <x-ui.alert variant="success" dismissible>
                            {{ session('status') ?? session('success') }}
                        </x-ui.alert>
                    @endif

                    @if (session('error'))
                        <x-ui.alert variant="danger" dismissible>{{ session('error') }}</x-ui.alert>
                    @endif

                    {{-- Controllers that redirect with a neutral notice — an
                         already-received purchase order, say — used to flash
                         into nothing, because only success and error rendered. --}}
                    @if (session('info'))
                        <x-ui.alert variant="info" dismissible>{{ session('info') }}</x-ui.alert>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @include('layouts.partials.loading-overlay')
    @include('layouts.partials.decision-confirmation')

    <dialog
        data-session-warning
        class="m-auto w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-lg border border-warning-200 bg-white p-0 text-neutral-800 shadow-xl backdrop:bg-neutral-900/50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="session-warning-title"
        aria-describedby="session-warning-description"
    >
        <audio
            data-session-warning-audio
            src="{{ asset('audio/session_sound.mp3') }}"
            preload="auto"
            hidden
        ></audio>

        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-50 text-warning-600" aria-hidden="true">
                    <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <h2 id="session-warning-title" class="text-base font-semibold text-neutral-900">
                        {{ __('Your session is about to expire') }}
                    </h2>
                    <p id="session-warning-description" class="mt-1 text-sm text-neutral-600">
                        {{ __('For your security, HIMS will sign you out when the inactivity timer reaches zero.') }}
                    </p>
                </div>
            </div>

            <p class="mt-5 text-center text-sm text-neutral-600">
                {{ __('Time remaining') }}
                <span data-session-countdown class="mt-1 block font-mono text-3xl font-semibold tabular-nums text-warning-700" aria-hidden="true">--:--</span>
            </p>
            <p data-session-warning-live class="sr-only" aria-live="polite" aria-atomic="true"></p>
            <p data-session-warning-error class="mt-3 hidden text-sm text-danger-700" role="alert"></p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button type="button" variant="secondary" data-session-warning-dismiss>
                    {{ __('Dismiss') }}
                </x-ui.button>
                <x-ui.button type="button" data-session-continue>
                    {{ __('Continue Session') }}
                </x-ui.button>
            </div>
        </div>
    </dialog>
</body>
</html>
