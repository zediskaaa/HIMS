@php
    // GuestLayout is a class-based component with no properties, so any
    // title="..." passed by a page arrives as a plain attribute.
    $pageTitle = trim((string) $attributes->get('title'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <title>{{ $pageTitle !== '' ? $pageTitle.' · HIMS' : 'HIMS' }}</title>

        {{-- Inter is pulled in by app.css; this just warms the connection. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-950 font-sans text-neutral-800 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            {{-- One continuous backdrop keeps authentication visually connected
                 to the public landing page without exposing application data. --}}
            <div class="absolute inset-0" aria-hidden="true">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full animate-slow-zoom object-cover object-center will-change-transform" />
                <div class="absolute inset-0 bg-neutral-950/70"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/85 to-neutral-950/55"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/55 via-transparent to-neutral-950/80"></div>
                <div class="absolute -left-24 top-20 h-80 w-80 animate-float-slow rounded-full bg-primary-600/20 blur-3xl will-change-transform"></div>
                <div class="absolute right-12 top-1/3 h-72 w-72 animate-drift-slow rounded-full bg-primary-400/10 blur-3xl will-change-transform"></div>
            </div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 sm:px-8 lg:px-10">
                <header class="flex animate-fade-in items-center justify-between border-b border-white/10 py-5">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded-lg focus-visible:ring-offset-neutral-950">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-10 w-10 rounded-lg bg-white object-cover ring-1 ring-inset ring-white/20 transition duration-300 group-hover:scale-105 group-hover:ring-primary-300/40" />
                        <span>
                            <span class="block text-base font-semibold tracking-tight text-white">HIMS</span>
                            <span class="hidden text-[10px] font-medium uppercase tracking-[0.16em] text-neutral-400 sm:block">Hospital operations</span>
                        </span>
                    </a>

                    <a href="{{ url('/') }}" class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-neutral-300 transition duration-300 hover:bg-white/10 hover:text-white focus-visible:ring-offset-neutral-950">
                        <x-ui.icon name="chevron-left" class="h-3.5 w-3.5 transition-transform duration-300 group-hover:-translate-x-0.5" />
                        Back to home
                    </a>
                </header>

                <main class="grid flex-1 items-center gap-10 py-8 lg:grid-cols-[minmax(0,1fr)_28rem] lg:gap-16 lg:py-12">
                    <section class="hidden max-w-xl lg:block">
                        <p class="inline-flex animate-fade-up items-center gap-2 rounded-full border border-primary-300/20 bg-primary-400/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.14em] text-primary-200 [animation-delay:120ms]">
                            <span class="h-1.5 w-1.5 animate-ping-dot rounded-full bg-primary-400"></span>
                            Hospital Inventory Management System
                        </p>
                        <h1 class="mt-6 animate-fade-up text-balance text-4xl font-semibold leading-tight tracking-tight text-white [animation-delay:240ms] xl:text-5xl">
                            Hospital supply operations, on one secure record.
                        </h1>
                        <p class="mt-5 max-w-lg animate-fade-up text-base leading-7 text-neutral-300 [animation-delay:360ms]">
                            Sign in to continue managing procurement, inventory, and replenishment across the hospital.
                        </p>
                    </section>

                    <div class="mx-auto w-full max-w-md animate-fade-in-scale overflow-hidden rounded-2xl border border-white/20 bg-white shadow-2xl shadow-neutral-950/60 [animation-delay:200ms]">
                        {{-- A slow highlight travels the accent bar so the card reads as live. --}}
                        <div class="relative h-1 overflow-hidden bg-gradient-to-r from-primary-700 via-primary-500 to-primary-300">
                            <span class="absolute inset-0 -translate-x-full animate-sheen bg-gradient-to-r from-transparent via-white/70 to-transparent" aria-hidden="true"></span>
                        </div>
                        <div class="p-6 sm:p-8">
                            {{ $slot }}
                        </div>
                    </div>
                </main>

                <footer class="animate-fade-in border-t border-white/10 py-5 text-xs text-neutral-500 [animation-delay:700ms]">
                    &copy; {{ date('Y') }} HIMS
                </footer>
            </div>
        </div>
    </body>
</html>
