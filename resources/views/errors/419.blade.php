<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <title>419 | Page Expired &middot; HIMS</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        @vite('resources/css/app.css')
    </head>
    <body class="h-full bg-neutral-50 font-sans text-neutral-800 antialiased">
        <main class="flex min-h-full items-center justify-center px-4 py-10 sm:px-6">
            <section class="w-full max-w-lg" aria-labelledby="page-expired-title">
                <div class="mb-6 flex items-center justify-center gap-3">
                    <img
                        src="{{ asset('img/hims-logo.png') }}"
                        alt=""
                        class="h-11 w-11 rounded-lg border border-neutral-200 bg-white object-cover shadow-sm"
                    >
                    <div>
                        <p class="text-base font-semibold leading-5 tracking-tight text-neutral-900">HIMS</p>
                        <p class="text-xs text-neutral-500">Hospital Inventory Management System</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <div class="h-1 bg-primary-600" aria-hidden="true"></div>

                    <div class="px-6 py-9 text-center sm:px-10 sm:py-11">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-primary-100 bg-primary-50 text-primary-700">
                            <x-ui.icon name="shield-check" class="h-7 w-7" />
                        </div>

                        <p class="mt-6 text-5xl font-semibold tracking-tight text-primary-700" aria-hidden="true">419</p>
                        <h1 id="page-expired-title" class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">
                            <span class="sr-only">Error 419: </span>Page Expired
                        </h1>

                        <p class="mx-auto mt-4 max-w-sm text-sm leading-6 text-neutral-600 sm:text-base sm:leading-7">
                            Your session has expired or the page is no longer available. Please refresh the page and try again.
                        </p>

                        <x-ui.button
                            :href="request()->fullUrl()"
                            size="lg"
                            class="mt-7 w-full sm:w-auto sm:min-w-40"
                        >
                            Refresh Page
                        </x-ui.button>
                    </div>
                </div>

                <p class="mt-5 text-center text-xs leading-5 text-neutral-500">
                    For your security, expired forms are not submitted automatically.
                </p>
            </section>
        </main>
    </body>
</html>
