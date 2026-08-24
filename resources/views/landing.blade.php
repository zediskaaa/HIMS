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
        <div class="relative flex min-h-screen flex-col overflow-hidden">
            {{-- Decorative backdrop. The page reads the same with images off. --}}
            <div class="absolute inset-0 z-0" aria-hidden="true">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center" />
                {{-- Layered scrims: the text column stays dark enough for contrast,
                     the right side keeps the photograph visible. --}}
                <div class="absolute inset-0 bg-neutral-950/55"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/85 via-45% to-neutral-950/20"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/60 via-neutral-950/20 to-neutral-950/85"></div>
                <div class="absolute -left-24 top-24 h-80 w-80 rounded-full bg-primary-600/20 blur-3xl"></div>
            </div>

            <div class="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col px-6 lg:px-8">
                <header class="flex items-center justify-between py-6">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-lg focus-visible:ring-offset-neutral-950">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 font-bold text-primary-200 ring-1 ring-inset ring-white/15">H</span>
                        <span class="text-base font-semibold tracking-tight text-white">HIMS</span>
                    </a>

                    @if (Route::has('login'))
                        <nav>
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-200 transition hover:bg-white/10 hover:text-white focus-visible:ring-offset-neutral-950">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-200 transition hover:bg-white/10 hover:text-white focus-visible:ring-offset-neutral-950">
                                    Log in
                                </a>
                            @endauth
                        </nav>
                    @endif
                </header>

                <main class="flex flex-1 flex-col justify-center py-16 sm:py-24">
                    {{-- max-w-full keeps this pill from forcing horizontal overflow
                         on narrow screens; the tracking is what makes it wide. --}}
                    <p class="inline-flex w-fit max-w-full items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.14em] text-neutral-300 sm:text-xs sm:tracking-widest">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-400"></span>
                        <span>Hospital Inventory Management System</span>
                    </p>

                    {{-- Keep "Supply Chain" and "Inventory Management" as unbroken
                         text nodes — tests/Feature/LandingPageTest.php asserts them. --}}
                    <h1 class="mt-6 max-w-3xl text-balance text-4xl font-semibold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Supply Chain &amp; Inventory Management
                    </h1>

                    <p class="mt-6 max-w-xl text-pretty text-lg leading-8 text-neutral-300">
                        Track suppliers, monitor stock movement, and keep replenishment on schedule across every department.
                    </p>

                    @if (Route::has('login'))
                        <div class="mt-10">
                            @auth
                                <a href="{{ route('dashboard') }}" class="group inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-neutral-900 shadow-lg shadow-neutral-950/40 transition hover:bg-neutral-200 focus-visible:ring-offset-neutral-950">
                                    Go to dashboard
                                    <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="group inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-neutral-900 shadow-lg shadow-neutral-950/40 transition hover:bg-neutral-200 focus-visible:ring-offset-neutral-950">
                                    Log in to HIMS
                                    <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            @endauth
                        </div>
                    @endif
                </main>

                @php
                    $features = [
                        ['icon' => 'truck', 'title' => 'Procurement', 'body' => 'Raise requests, compare supplier quotes, and move approvals along.'],
                        ['icon' => 'cube', 'title' => 'Inventory', 'body' => 'Track batches and stock levels across warehouses, wards, and pharmacies.'],
                        ['icon' => 'chart-bar', 'title' => 'Reporting', 'body' => 'Turn warehouse activity into reports for planning and audits.'],
                    ];
                @endphp

                <section class="grid gap-4 sm:grid-cols-3">
                    @foreach ($features as $feature)
                        <div class="rounded-xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm transition duration-200 hover:border-white/20 hover:bg-white/[0.07]">
                            <x-ui.icon :name="$feature['icon']" class="h-5 w-5 text-primary-300" />
                            <h2 class="mt-4 font-semibold text-white">{{ $feature['title'] }}</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-400">{{ $feature['body'] }}</p>
                        </div>
                    @endforeach
                </section>

                <footer class="mt-10 border-t border-white/10 py-6 text-xs text-neutral-500">
                    &copy; {{ date('Y') }} HIMS — hospital supply chain operations.
                </footer>
            </div>
        </div>
    </body>
</html>
