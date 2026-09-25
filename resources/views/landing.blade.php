<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="HIMS connects hospital procurement, central warehouse inventory, and ward replenishment on one operational record.">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <title>HIMS | Supply Chain &amp; Inventory Management</title>
        @include('layouts.partials.theme-script')
        @include('layouts.partials.navigation-loading-state')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $hasLogin = Route::has('login');
        $dashboardUrl = $hasLogin && \App\Support\AuthenticationContext::authenticatedGuard() !== null
            ? route(\App\Support\AuthenticationContext::dashboardRoute())
            : null;
    @endphp
    <body class="min-h-screen bg-neutral-50 text-neutral-800 antialiased dark:bg-neutral-950 dark:text-neutral-100">
        @include('layouts.partials.loading-overlay')

        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0" aria-hidden="true">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full animate-slow-zoom object-cover object-center will-change-transform grayscale opacity-[0.06] dark:opacity-[0.14]" />
                <div class="absolute inset-0 bg-neutral-50/35 dark:bg-neutral-950/40"></div>
            </div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-[1680px] flex-col px-5 sm:px-8 xl:px-12">
            <header class="flex items-center justify-between gap-4 border-b border-neutral-200 py-4 dark:border-neutral-800">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-2.5 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-50 dark:focus-visible:ring-offset-neutral-950">
                    <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-10 w-10 shrink-0 rounded-lg bg-white object-cover ring-1 ring-inset ring-neutral-200 dark:ring-neutral-700">
                    <span class="min-w-0">
                        <span class="block text-base font-semibold leading-5 tracking-tight text-neutral-950 dark:text-neutral-50">HIMS</span>
                        <span class="hidden text-[10px] font-medium uppercase tracking-[0.14em] text-neutral-500 dark:text-neutral-400 sm:block">Hospital Operations</span>
                    </span>
                </a>

                <nav aria-label="Site navigation" class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <x-ui.theme-toggle size="sm" />
                    @if ($dashboardUrl)
                        <x-ui.button variant="secondary" size="sm" :href="$dashboardUrl">Dashboard</x-ui.button>
                    @endif
                </nav>
            </header>

            <main class="flex flex-1 flex-col justify-center py-8 sm:py-10 lg:py-12">
                <section aria-labelledby="landing-title" class="grid border-b border-neutral-200 dark:border-neutral-800 sm:grid-cols-2 xl:grid-cols-12">
                    <div class="pb-7 pt-2 sm:col-span-2 xl:col-span-6 xl:pb-10 xl:pr-10 xl:pt-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.13em] text-primary-700 dark:text-primary-300">Supply Chain &amp; Inventory Management</p>
                        <h1 id="landing-title" class="mt-5 max-w-3xl text-balance text-[2rem] font-semibold leading-[1.13] tracking-tight text-neutral-950 dark:text-neutral-50 sm:text-5xl sm:leading-[1.1] xl:text-[3.25rem]">
                            From supply request to ward, every handoff has a record.
                        </h1>
                    </div>

                    <div class="border-t border-neutral-200 py-6 dark:border-neutral-800 sm:pr-8 xl:col-span-3 xl:border-l xl:border-t-0 xl:px-8 xl:py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.13em] text-neutral-500 dark:text-neutral-400">Hospital Operations</p>
                        <p class="mt-4 max-w-md text-sm leading-6 text-neutral-700 dark:text-neutral-300 sm:text-base sm:leading-7">
                            HIMS connects procurement, central warehouse stock, inventory movement, and ward replenishment in one accountable workflow.
                        </p>
                        <p class="mt-5 inline-flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                            <x-ui.icon name="shield-check" class="h-4 w-4 shrink-0 text-primary-700 dark:text-primary-300" />
                            Secure access for authorized staff
                        </p>
                    </div>

                    <div class="flex flex-col justify-between border-t border-neutral-200 bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-900 sm:border-l xl:col-span-3 xl:border-t-0">
                        <div class="px-5 py-4 lg:px-6">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.13em] text-neutral-500 dark:text-neutral-400">System access</p>
                            <p class="mt-4 text-lg font-semibold leading-6 text-neutral-900 dark:text-neutral-100">Continue to HIMS</p>
                            <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-400">Use your assigned hospital account.</p>
                        </div>
                        @if ($hasLogin)
                            <a href="{{ $dashboardUrl ?? route('login') }}" class="group flex min-h-14 items-center justify-between gap-4 border-l-4 border-primary-600 bg-neutral-900 px-5 py-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 active:bg-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-100 dark:border-primary-400 dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700 dark:active:bg-neutral-600 dark:focus-visible:ring-offset-neutral-900 lg:px-6">
                                <span>{{ $dashboardUrl ? 'Open dashboard' : 'Log in to HIMS' }}</span>
                                <x-ui.icon name="arrow-right" class="h-4 w-4 shrink-0 text-primary-300 transition-transform group-hover:translate-x-0.5 dark:text-primary-400" />
                            </a>
                        @endif
                    </div>
                </section>

                <section aria-labelledby="route-title" class="mt-6 sm:mt-10">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.13em] text-primary-700 dark:text-primary-300">Operational scope</p>
                            <h2 id="route-title" class="mt-1 text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-2xl">The hospital supply route</h2>
                        </div>
                    </div>

                    <ol class="mt-4 grid grid-cols-3 border-y border-neutral-200 dark:border-neutral-800 md:grid-cols-10" aria-label="Hospital supply route stages">
                        <li class="min-w-0 border-r border-neutral-200 py-5 pl-2 pr-2 dark:border-neutral-800 sm:px-5 md:col-span-3 lg:px-7">
                            <div class="flex items-center gap-2 text-primary-700 dark:text-primary-300">
                                <span class="font-mono text-xs font-semibold">01</span>
                                <span class="h-px min-w-0 flex-1 bg-primary-300 dark:bg-primary-800" aria-hidden="true"></span>
                            </div>
                            <p class="mt-5 text-[10px] font-semibold uppercase tracking-[0.1em] text-neutral-500 dark:text-neutral-400">Request</p>
                            <h3 class="mt-1 text-xs font-semibold leading-5 text-neutral-900 dark:text-neutral-100 sm:text-base lg:text-lg">Procurement</h3>
                            <p class="mt-2 hidden max-w-xs text-sm leading-6 text-neutral-600 dark:text-neutral-400 sm:block">Supply requests, supplier quotes, and purchase orders.</p>
                        </li>
                        <li class="min-w-0 border-r border-neutral-200 bg-neutral-100 py-5 pl-2 pr-2 dark:border-neutral-800 dark:bg-neutral-900 sm:px-5 md:col-span-4 lg:px-7">
                            <div class="flex items-center gap-2 text-primary-700 dark:text-primary-300">
                                <span class="font-mono text-xs font-semibold">02</span>
                                <span class="h-px min-w-0 flex-1 bg-primary-300 dark:bg-primary-800" aria-hidden="true"></span>
                            </div>
                            <p class="mt-5 text-[10px] font-semibold uppercase tracking-[0.1em] text-neutral-500 dark:text-neutral-400">Store &amp; control</p>
                            <h3 class="mt-1 text-xs font-semibold leading-5 text-neutral-900 dark:text-neutral-100 sm:text-base lg:text-lg">Central warehouse</h3>
                            <p class="mt-2 hidden max-w-sm text-sm leading-6 text-neutral-600 dark:text-neutral-400 sm:block">Receiving, storage locations, batches, and stock visibility.</p>
                        </li>
                        <li class="min-w-0 py-5 pl-2 pr-2 sm:px-5 md:col-span-3 lg:px-7">
                            <div class="flex items-center gap-2 text-primary-700 dark:text-primary-300">
                                <span class="font-mono text-xs font-semibold">03</span>
                                <span class="h-px min-w-0 flex-1 bg-primary-300 dark:bg-primary-800" aria-hidden="true"></span>
                            </div>
                            <p class="mt-5 text-[10px] font-semibold uppercase tracking-[0.1em] text-neutral-500 dark:text-neutral-400">Move &amp; replenish</p>
                            <h3 class="mt-1 text-xs font-semibold leading-5 text-neutral-900 dark:text-neutral-100 sm:text-base lg:text-lg">Ward supply</h3>
                            <p class="mt-2 hidden max-w-xs text-sm leading-6 text-neutral-600 dark:text-neutral-400 sm:block">Stock movement from central stores to care units.</p>
                        </li>
                    </ol>

                    <div class="grid gap-2 border-b border-neutral-200 py-4 dark:border-neutral-800 sm:grid-cols-[minmax(0,13rem)_minmax(0,1fr)] sm:gap-6">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-neutral-500 dark:text-neutral-400">Reporting &amp; accountability</p>
                        <p class="text-sm leading-6 text-neutral-700 dark:text-neutral-300">Warehouse activity and inventory movement support planning and audit-ready reports.</p>
                    </div>
                </section>
            </main>

            <footer class="flex flex-col gap-3 py-5 text-xs text-neutral-500 dark:text-neutral-400 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} HIMS &middot; Hospital Operations</p>
                <div class="flex items-center gap-5">
                    <a href="{{ route('privacy.notice') }}" class="rounded-sm transition-colors hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:text-neutral-100">Privacy Notice</a>
                    <a href="{{ route('terms') }}" class="rounded-sm transition-colors hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:text-neutral-100">Terms of Use</a>
                </div>
            </footer>
        </div>
        </div>

    </body>
</html>
