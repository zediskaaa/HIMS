@php
    // GuestLayout is a class-based component with no properties, so any
    // title="..." passed by a page arrives as a plain attribute.
    $pageTitle = trim((string) $attributes->get('title'));
    $portal = (string) $attributes->get('portal', 'staff');
    $isAdminPortal = $portal === 'admin';
    $isSuperAdminPortal = $portal === 'super-admin';
    $isThemeAwarePortal = $isSuperAdminPortal || $portal === 'staff';
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

        {{-- Early zero-flicker theme script --}}
        @include('layouts.partials.theme-script')

        {{-- Inter is pulled in by app.css; this just warms the connection. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans text-neutral-800 antialiased {{ $isThemeAwarePortal ? 'bg-neutral-50 dark:bg-neutral-950 dark:text-neutral-100' : 'bg-neutral-950' }}">
        <div class="relative min-h-screen overflow-hidden {{ $isThemeAwarePortal ? 'bg-neutral-50 dark:bg-neutral-950' : '' }}">
            {{-- One continuous backdrop keeps authentication visually connected
                 to the public landing page without exposing application data. --}}
            <div class="absolute inset-0" aria-hidden="true">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full animate-slow-zoom object-cover object-center will-change-transform {{ $isThemeAwarePortal ? 'grayscale opacity-[0.06] dark:opacity-[0.14]' : '' }}" />
                @if ($isThemeAwarePortal)
                    <div class="absolute inset-0 bg-neutral-50/35 dark:bg-neutral-950/40"></div>
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
                <header class="flex animate-fade-in items-center justify-between border-b py-5 {{ $isThemeAwarePortal ? 'border-neutral-200 dark:border-neutral-800' : 'border-white/10' }}">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded-lg focus-visible:ring-offset-2 {{ $isThemeAwarePortal ? 'focus-visible:ring-offset-neutral-50 dark:focus-visible:ring-offset-neutral-950' : 'focus-visible:ring-offset-neutral-950' }}">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="hims-keep-light h-10 w-10 rounded-lg bg-white object-cover ring-1 ring-inset ring-white/20 transition duration-300 group-hover:scale-105 group-hover:ring-primary-300/40" />
                        <span>
                            <span class="block text-base font-semibold tracking-tight {{ $isThemeAwarePortal ? 'text-neutral-900 dark:text-neutral-100' : 'text-white' }}">HIMS</span>
                            <span class="hidden text-[10px] font-medium uppercase tracking-[0.16em] {{ $isThemeAwarePortal ? 'text-neutral-500 dark:text-neutral-400' : 'text-neutral-400' }} sm:block">
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

                    <div class="flex items-center gap-3">
                        @if ($isThemeAwarePortal)
                            <x-ui.theme-toggle />
                        @else
                            <x-ui.theme-toggle class="text-neutral-300 hover:text-white hover:bg-white/10" />
                        @endif

                        <a href="{{ url('/') }}" class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition duration-200 focus-visible:ring-offset-2 {{ $isThemeAwarePortal ? 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 focus-visible:ring-offset-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 dark:focus-visible:ring-offset-neutral-950' : 'text-neutral-300 hover:bg-white/10 hover:text-white focus-visible:ring-offset-neutral-950' }}">
                            <x-ui.icon name="chevron-left" class="h-3.5 w-3.5 transition-transform duration-300 group-hover:-translate-x-0.5" />
                            Back to home
                        </a>
                    </div>
                </header>

                <main class="grid flex-1 items-center gap-10 py-8 lg:grid-cols-[minmax(0,1fr)_28rem] lg:gap-16 lg:py-12">
                    <section class="hidden max-w-xl lg:block">
                        @if ($isSuperAdminPortal)
                            <h1 class="animate-fade-up text-balance text-4xl font-semibold leading-tight tracking-tight text-neutral-900 [animation-delay:240ms] dark:text-neutral-100 xl:text-5xl">
                                System-wide governance, secured at the highest level.
                            </h1>
                            <p class="mt-5 max-w-lg animate-fade-up text-base leading-7 text-neutral-600 [animation-delay:360ms] dark:text-neutral-400">
                                Control administrative boundaries, protect privileged access, and maintain accountability across HIMS.
                            </p>
                            <div class="mt-8 grid max-w-lg grid-cols-3 gap-3 animate-fade-up [animation-delay:460ms]">
                                <div class="border-l-2 border-warning-600/60 pl-3 dark:border-warning-500/60">
                                    <p class="text-xs font-semibold text-neutral-800 dark:text-neutral-100">Access governance</p>
                                    <p class="mt-1 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">System roles</p>
                                </div>
                                <div class="border-l-2 border-warning-600/40 pl-3 dark:border-warning-500/40">
                                    <p class="text-xs font-semibold text-neutral-800 dark:text-neutral-100">Security control</p>
                                    <p class="mt-1 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Protected settings</p>
                                </div>
                                <div class="border-l-2 border-warning-600/25 pl-3 dark:border-warning-500/25">
                                    <p class="text-xs font-semibold text-neutral-800 dark:text-neutral-100">Audit oversight</p>
                                    <p class="mt-1 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Accountability</p>
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
                            <h1 class="animate-fade-up text-balance text-4xl font-semibold leading-tight tracking-tight {{ $isThemeAwarePortal ? 'text-neutral-900 dark:text-neutral-100' : 'text-white' }} [animation-delay:240ms] xl:text-5xl">
                                Hospital supply operations, on one secure record.
                            </h1>
                            <p class="mt-5 max-w-lg animate-fade-up text-base leading-7 {{ $isThemeAwarePortal ? 'text-neutral-600 dark:text-neutral-400' : 'text-neutral-300' }} [animation-delay:360ms]">
                                Sign in to continue managing procurement, inventory, and replenishment across the hospital.
                            </p>
                        @endif
                    </section>

                    <div class="mx-auto w-full max-w-md animate-fade-in-scale overflow-hidden rounded-xl border {{ $isThemeAwarePortal ? 'border-neutral-200 dark:border-neutral-800' : 'border-primary-200 dark:border-primary-300/30' }} {{ $isThemeAwarePortal ? 'bg-neutral-100/95 dark:bg-neutral-900' : 'bg-neutral-50/95 dark:bg-neutral-900' }} shadow-lg shadow-neutral-900/10 backdrop-blur-xs [animation-delay:200ms] dark:shadow-xl dark:shadow-black/35">
                        <div class="p-6 sm:p-8">
                            {{ $slot }}
                        </div>
                    </div>
                </main>

                <footer class="animate-fade-in flex flex-col gap-2 border-t py-5 text-xs [animation-delay:700ms] sm:flex-row sm:items-center sm:justify-between {{ $isThemeAwarePortal ? 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400' : 'border-white/10 text-neutral-500' }}">
                    <div>
                        &copy; {{ date('Y') }} HIMS
                        @if ($isSuperAdminPortal)
                            <span class="ml-2 text-neutral-500 dark:text-neutral-500">&middot; Privileged system access</span>
                        @elseif ($isAdminPortal)
                            <span class="ml-2 text-neutral-600">&middot; Administration access</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 {{ $isThemeAwarePortal ? 'text-neutral-600 dark:text-neutral-400' : 'text-neutral-400' }}">
                        <a href="{{ route('privacy.notice') }}" class="transition-colors {{ $isThemeAwarePortal ? 'hover:text-neutral-900 dark:hover:text-neutral-100' : 'hover:text-neutral-200' }}">Privacy Notice</a>
                        <a href="{{ route('terms') }}" class="transition-colors {{ $isThemeAwarePortal ? 'hover:text-neutral-900 dark:hover:text-neutral-100' : 'hover:text-neutral-200' }}">Terms of Use</a>
                    </div>
                </footer>
            </div>
        </div>

        @include('layouts.partials.loading-overlay')
        @include('layouts.partials.decision-confirmation')
    </body>
</html>
