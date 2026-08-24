<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="HIMS keeps hospital procurement, stock levels, and replenishment on one record — from warehouse to ward.">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <title>HIMS | Supply Chain &amp; Inventory Management</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-neutral-950 text-neutral-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            {{-- Decorative backdrop. Content and contrast remain intact without it. --}}
            <div class="absolute inset-0 z-0" aria-hidden="true">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center" />
                <div class="absolute inset-0 bg-neutral-950/65"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/90 via-50% to-neutral-950/45"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/70 via-transparent to-neutral-950"></div>
                <div class="absolute -left-24 top-24 h-80 w-80 rounded-full bg-primary-600/20 blur-3xl"></div>
                <div class="absolute right-0 top-1/3 h-96 w-96 rounded-full bg-primary-500/10 blur-3xl"></div>
            </div>

            <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 lg:px-8">
                <header class="flex items-center justify-between border-b border-white/10 py-5">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-lg focus-visible:ring-offset-neutral-950">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 font-bold text-primary-200 ring-1 ring-inset ring-white/15">H</span>
                        <span>
                            <span class="block text-base font-semibold tracking-tight text-white">HIMS</span>
                            <span class="hidden text-[10px] font-medium uppercase tracking-[0.16em] text-neutral-400 sm:block">Hospital operations</span>
                        </span>
                    </a>

                    @if (Route::has('login'))
                        <nav aria-label="Account navigation">
                            @auth
                                <a href="{{ route('dashboard') }}" class="group inline-flex items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:border-white/25 hover:bg-white/15 focus-visible:ring-offset-neutral-950">
                                    Dashboard
                                    <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="group inline-flex items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:border-white/25 hover:bg-white/15 focus-visible:ring-offset-neutral-950">
                                    Staff log in
                                    <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            @endauth
                        </nav>
                    @endif
                </header>

                <main class="flex flex-1 flex-col justify-center py-14 sm:py-16 lg:py-20">
                    <div class="max-w-3xl">
                        <section>
                            <p class="inline-flex w-fit max-w-full items-center gap-2 rounded-full border border-primary-300/20 bg-primary-400/10 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.14em] text-primary-200 sm:text-xs sm:tracking-widest">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-400 shadow-[0_0_0_4px_rgba(89,180,255,0.12)]"></span>
                                <span>Hospital Inventory Management System</span>
                            </p>

                            {{-- Keep both phrases as plain text for the landing-page contract. --}}
                            <h1 class="mt-6 max-w-2xl text-balance text-4xl font-semibold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl">
                                Supply Chain &amp; Inventory Management that keeps care moving.
                            </h1>

                            <p class="mt-6 max-w-xl text-pretty text-base leading-7 text-neutral-300 sm:text-lg sm:leading-8">
                                Connect procurement, stock visibility, and replenishment in one dependable workspace—from the central warehouse to every ward.
                            </p>

                            @if (Route::has('login'))
                                <div class="mt-9 flex flex-col gap-4 sm:flex-row sm:items-center">
                                    @auth
                                        <a href="{{ route('dashboard') }}" class="group inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-950/30 transition hover:bg-primary-500 focus-visible:ring-primary-400 focus-visible:ring-offset-neutral-950">
                                            Open dashboard
                                            <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                        </a>
                                    @else
                                        <a href="{{ route('login') }}" class="group inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-950/30 transition hover:bg-primary-500 focus-visible:ring-primary-400 focus-visible:ring-offset-neutral-950">
                                            Log in to HIMS
                                            <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                        </a>
                                    @endauth

                                    <span class="inline-flex items-center justify-center gap-2 text-xs text-neutral-400 sm:justify-start">
                                        <x-ui.icon name="shield-check" class="h-4 w-4 text-primary-300" />
                                        Secure access for authorized staff
                                    </span>
                                </div>
                            @endif

                        </section>
                    </div>
                </main>

                @php
                    $features = [
                        ['icon' => 'truck', 'title' => 'Procurement', 'body' => 'Move requests, supplier quotes, and purchase orders through one controlled process.'],
                        ['icon' => 'cube', 'title' => 'Inventory control', 'body' => 'See batches, stock levels, and movement across warehouses, wards, and pharmacies.'],
                        ['icon' => 'chart-bar', 'title' => 'Reporting', 'body' => 'Turn daily warehouse activity into clear planning and audit-ready reports.'],
                    ];
                @endphp

                <section class="grid border-y border-white/10 sm:grid-cols-3" aria-label="HIMS core capabilities">
                    @foreach ($features as $feature)
                        <article class="group py-6 sm:px-6 sm:first:pl-0 sm:last:pr-0 [&:not(:last-child)]:border-b [&:not(:last-child)]:border-white/10 sm:[&:not(:last-child)]:border-b-0 sm:[&:not(:last-child)]:border-r">
                            <div class="flex items-start gap-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-primary-300 ring-1 ring-inset ring-white/10 transition group-hover:bg-primary-400/10 group-hover:ring-primary-300/20">
                                    <x-ui.icon :name="$feature['icon']" class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-semibold text-white">{{ $feature['title'] }}</h2>
                                    <p class="mt-1.5 text-xs leading-5 text-neutral-400">{{ $feature['body'] }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </section>

                <footer class="flex flex-col gap-2 py-6 text-xs text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} HIMS — hospital supply chain operations.</p>
                    <p>From warehouse to ward, on one record.</p>
                </footer>
            </div>
        </div>
    </body>
</html>
