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
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center" />
                <div class="absolute inset-0 bg-neutral-950/70"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/85 to-neutral-950/55"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/55 via-transparent to-neutral-950/80"></div>
                <div class="absolute -left-24 top-20 h-80 w-80 rounded-full bg-primary-600/20 blur-3xl"></div>
                <div class="absolute right-12 top-1/3 h-72 w-72 rounded-full bg-primary-400/10 blur-3xl"></div>
            </div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 sm:px-8 lg:px-10">
                <header class="flex items-center justify-between border-b border-white/10 py-5">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-lg focus-visible:ring-offset-neutral-950">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 font-bold text-primary-200 ring-1 ring-inset ring-white/15">H</span>
                        <span>
                            <span class="block text-base font-semibold tracking-tight text-white">HIMS</span>
                            <span class="hidden text-[10px] font-medium uppercase tracking-[0.16em] text-neutral-400 sm:block">Hospital operations</span>
                        </span>
                    </a>

                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-neutral-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-offset-neutral-950">
                        Back to home
                    </a>
                </header>

                <main class="grid flex-1 items-center gap-10 py-8 lg:grid-cols-[minmax(0,1fr)_28rem] lg:gap-16 lg:py-12">
                    <section class="hidden max-w-xl lg:block">
                        <p class="inline-flex items-center gap-2 rounded-full border border-primary-300/20 bg-primary-400/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.14em] text-primary-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary-400"></span>
                            Hospital Inventory Management System
                        </p>
                        <h1 class="mt-6 text-balance text-4xl font-semibold leading-tight tracking-tight text-white xl:text-5xl">
                            Hospital supply operations, on one secure record.
                        </h1>
                        <p class="mt-5 max-w-lg text-base leading-7 text-neutral-300">
                            Sign in to continue managing procurement, inventory, and replenishment across the hospital.
                        </p>
                    </section>

                    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-white/20 bg-white shadow-2xl shadow-neutral-950/60">
                        <div class="h-1 bg-gradient-to-r from-primary-700 via-primary-500 to-primary-300"></div>
                        <div class="p-6 sm:p-8">
                            {{ $slot }}
                        </div>
                    </div>
                </main>

                <footer class="border-t border-white/10 py-5 text-xs text-neutral-500">
                    &copy; {{ date('Y') }} HIMS
                </footer>
            </div>
        </div>
    </body>
</html>
