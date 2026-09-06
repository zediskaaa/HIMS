@php
    // GuestLayout is a class-based component with no properties, so any
    // title="..." passed by a page arrives as a plain attribute.
    $pageTitle = trim((string) $attributes->get('title'));
    $portal = (string) $attributes->get('portal', 'staff');
    $isAdminPortal = $portal === 'admin';
    $isSuperAdminPortal = $portal === 'super-admin';
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
                @if ($isSuperAdminPortal)
                    <div class="absolute inset-0 bg-neutral-950/85"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-primary-950/95 to-neutral-950/80"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/70 via-transparent to-neutral-950"></div>
                    <div class="absolute -left-24 top-16 h-80 w-80 animate-float-slow rounded-full bg-primary-800/20 blur-3xl will-change-transform"></div>
                    <div class="absolute right-10 top-1/3 h-64 w-64 animate-drift-slow rounded-full bg-warning-500/10 blur-3xl will-change-transform"></div>
                @elseif ($isAdminPortal)
                    <div class="absolute inset-0 bg-primary-950/75"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-950 via-primary-950/90 to-neutral-950/60"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/40 via-transparent to-neutral-950/85"></div>
                    <div class="absolute -left-24 top-20 h-80 w-80 animate-float-slow rounded-full bg-primary-500/25 blur-3xl will-change-transform"></div>
                    <div class="absolute right-12 top-1/3 h-72 w-72 animate-drift-slow rounded-full bg-primary-300/15 blur-3xl will-change-transform"></div>
                @else
                    <div class="absolute inset-0 bg-neutral-950/70"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/85 to-neutral-950/55"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/55 via-transparent to-neutral-950/80"></div>
                    <div class="absolute -left-24 top-20 h-80 w-80 animate-float-slow rounded-full bg-primary-600/20 blur-3xl will-change-transform"></div>
                    <div class="absolute right-12 top-1/3 h-72 w-72 animate-drift-slow rounded-full bg-primary-400/10 blur-3xl will-change-transform"></div>
                @endif
            </div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 sm:px-8 lg:px-10">
                <header class="flex animate-fade-in items-center justify-between border-b border-white/10 py-5">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded-lg focus-visible:ring-offset-neutral-950">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-10 w-10 rounded-lg bg-white object-cover ring-1 ring-inset ring-white/20 transition duration-300 group-hover:scale-105 group-hover:ring-primary-300/40" />
                        <span>
                            <span class="block text-base font-semibold tracking-tight text-white">HIMS</span>
                            <span class="hidden text-[10px] font-medium uppercase tracking-[0.16em] text-neutral-400 sm:block">
                                @if ($isSuperAdminPortal)
                                    System authority
                                @elseif ($isAdminPortal)
                                    Administration portal
                                @else
                                    Hospital operations
                                @endif
                            </span>
                        </span>
                    </a>

                    <a href="{{ url('/') }}" class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-neutral-300 transition duration-300 hover:bg-white/10 hover:text-white focus-visible:ring-offset-neutral-950">
                        <x-ui.icon name="chevron-left" class="h-3.5 w-3.5 transition-transform duration-300 group-hover:-translate-x-0.5" />
                        Back to home
                    </a>
                </header>

                <main class="grid flex-1 items-center gap-10 py-8 lg:grid-cols-[minmax(0,1fr)_28rem] lg:gap-16 lg:py-12">
                    <section class="hidden max-w-xl lg:block">
                        @if ($isSuperAdminPortal)
                            <p class="inline-flex animate-fade-up items-center gap-2 rounded-full border border-warning-500/25 bg-warning-500/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.14em] text-warning-500 [animation-delay:120ms]">
                                <x-ui.icon name="shield-check" class="h-3.5 w-3.5" />
                                HIMS privileged access
                            </p>
                            <h1 class="mt-6 animate-fade-up text-balance text-4xl font-semibold leading-tight tracking-tight text-white [animation-delay:240ms] xl:text-5xl">
                                System-wide governance, secured at the highest level.
                            </h1>
                            <p class="mt-5 max-w-lg animate-fade-up text-base leading-7 text-neutral-300 [animation-delay:360ms]">
                                Control administrative boundaries, protect privileged access, and maintain accountability across HIMS.
                            </p>
                            <div class="mt-8 grid max-w-lg grid-cols-3 gap-3 animate-fade-up [animation-delay:460ms]">
                                <div class="border-l-2 border-warning-500/70 pl-3">
                                    <p class="text-xs font-semibold text-white">Access governance</p>
                                    <p class="mt-1 text-[11px] leading-4 text-neutral-400">System roles</p>
                                </div>
                                <div class="border-l-2 border-warning-500/40 pl-3">
                                    <p class="text-xs font-semibold text-white">Security control</p>
                                    <p class="mt-1 text-[11px] leading-4 text-neutral-400">Protected settings</p>
                                </div>
                                <div class="border-l-2 border-warning-500/25 pl-3">
                                    <p class="text-xs font-semibold text-white">Audit oversight</p>
                                    <p class="mt-1 text-[11px] leading-4 text-neutral-400">Accountability</p>
                                </div>
                            </div>
                        @elseif ($isAdminPortal)
                            <p class="inline-flex animate-fade-up items-center gap-2 rounded-full border border-primary-300/25 bg-primary-400/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.14em] text-primary-200 [animation-delay:120ms]">
                                <x-ui.icon name="users" class="h-3.5 w-3.5" />
                                HIMS administration
                            </p>
                            <h1 class="mt-6 animate-fade-up text-balance text-4xl font-semibold leading-tight tracking-tight text-white [animation-delay:240ms] xl:text-5xl">
                                Keep hospital operations organized and accountable.
                            </h1>
                            <p class="mt-5 max-w-lg animate-fade-up text-base leading-7 text-primary-100/85 [animation-delay:360ms]">
                                Manage authorized users and operational workflows from a focused administration workspace.
                            </p>
                            <div class="mt-8 grid max-w-lg grid-cols-3 gap-3 animate-fade-up [animation-delay:460ms]">
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                    <x-ui.icon name="users" class="h-4 w-4 text-primary-300" />
                                    <p class="mt-2 text-xs font-semibold text-white">User access</p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                    <x-ui.icon name="clipboard-document-list" class="h-4 w-4 text-primary-300" />
                                    <p class="mt-2 text-xs font-semibold text-white">Operations</p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                    <x-ui.icon name="document-text" class="h-4 w-4 text-primary-300" />
                                    <p class="mt-2 text-xs font-semibold text-white">Audit records</p>
                                </div>
                            </div>
                        @else
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
                        @endif
                    </section>

                    <div class="mx-auto w-full max-w-md animate-fade-in-scale overflow-hidden rounded-2xl border {{ $isSuperAdminPortal ? 'border-warning-500/25' : ($isAdminPortal ? 'border-primary-300/30' : 'border-white/20') }} bg-white shadow-2xl shadow-neutral-950/60 [animation-delay:200ms]">
                        {{-- A slow highlight travels the accent bar so the card reads as live. --}}
                        <div class="relative h-1 overflow-hidden {{ $isSuperAdminPortal ? 'bg-gradient-to-r from-neutral-950 via-warning-600 to-neutral-950' : 'bg-gradient-to-r from-primary-700 via-primary-500 to-primary-300' }}">
                            <span class="absolute inset-0 -translate-x-full animate-sheen bg-gradient-to-r from-transparent via-white/70 to-transparent" aria-hidden="true"></span>
                        </div>
                        <div class="p-6 sm:p-8">
                            {{ $slot }}
                        </div>
                    </div>
                </main>

                <footer class="animate-fade-in border-t border-white/10 py-5 text-xs text-neutral-500 [animation-delay:700ms]">
                    &copy; {{ date('Y') }} HIMS
                    @if ($isSuperAdminPortal)
                        <span class="ml-2 text-neutral-600">&middot; Privileged system access</span>
                    @elseif ($isAdminPortal)
                        <span class="ml-2 text-neutral-600">&middot; Administration access</span>
                    @endif
                </footer>
            </div>
        </div>

        @include('layouts.partials.loading-overlay')
    </body>
</html>
