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
    data-session-activity-url="{{ route('session.activity') }}"
    data-session-expired-url="{{ Illuminate\Support\Facades\URL::signedRoute('session.expired', absolute: false) }}"
>
    <div x-data="{ sidebarOpen: false }" class="min-h-full">

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

        <div class="lg:pl-64">
            @include('layouts.partials.topbar')

            <main class="px-4 py-6 lg:px-8 lg:py-8">
                <div class="max-w-7xl mx-auto space-y-6">
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
</body>
</html>
