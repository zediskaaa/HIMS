@props([
    'chartOnly' => false,
])

{{-- Structurally accurate Skeleton Loader for AI-Based Stock Demand Forecasting --}}
<div {{ $attributes->merge(['class' => 'space-y-3 select-none animate-pulse motion-reduce:animate-none']) }} aria-hidden="true">
    @if (! $chartOnly)
        {{-- 1. Filter Controls Skeleton: Matches 5-col responsive grid (Select Item, Forecast Period, Filter button) --}}
        <div class="grid min-w-0 gap-2.5 rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 sm:grid-cols-2 lg:grid-cols-5 lg:items-end dark:border-neutral-800 dark:bg-neutral-800/40">
            {{-- Item Selection Placeholder (sm:col-span-2 lg:col-span-3) --}}
            <div class="min-w-0 space-y-1.5 sm:col-span-2 lg:col-span-3">
                <div class="flex items-center justify-between">
                    <div class="h-3 w-16 rounded bg-neutral-200 dark:bg-neutral-700/70"></div>
                </div>
                <div class="flex h-10 w-full items-center justify-between rounded-md border border-neutral-200 bg-white px-3 dark:border-neutral-700/80 dark:bg-neutral-800/80">
                    <div class="h-3.5 w-60 max-w-[80%] rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="h-3.5 w-3.5 shrink-0 rounded bg-neutral-300 dark:bg-neutral-600"></div>
                </div>
            </div>

            {{-- Forecast Period Placeholder (lg:col-span-1) --}}
            <div class="min-w-0 space-y-1.5">
                <div class="h-3 w-24 rounded bg-neutral-200 dark:bg-neutral-700/70"></div>
                <div class="flex h-10 w-full items-center justify-between rounded-md border border-neutral-200 bg-white px-3 dark:border-neutral-700/80 dark:bg-neutral-800/80">
                    <div class="h-3.5 w-24 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="h-3.5 w-3.5 shrink-0 rounded bg-neutral-300 dark:bg-neutral-600"></div>
                </div>
            </div>

            {{-- More Filters Button Placeholder (lg:col-span-1) --}}
            <div class="flex min-w-0 items-end">
                <div class="flex h-10 w-full items-center justify-center gap-2 rounded-md border border-neutral-200 bg-white px-3 dark:border-neutral-700/80 dark:bg-neutral-800/80">
                    <div class="h-3.5 w-3.5 rounded bg-neutral-300 dark:bg-neutral-600"></div>
                    <div class="h-3.5 w-16 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                </div>
            </div>
        </div>

        {{-- 2. 6-Column KPI Summary Strip Skeleton: Matches loaded dl grid exactly --}}
        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200 sm:grid-cols-3 xl:grid-cols-6 dark:border-neutral-800 dark:bg-neutral-800">
            {{-- Slot 1: Forecast period --}}
            <div class="bg-neutral-50 p-2.5 dark:bg-neutral-900/90">
                <div class="h-2.5 w-20 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                <div class="mt-2 h-4 w-24 rounded bg-neutral-300 dark:bg-neutral-700"></div>
            </div>

            {{-- Slot 2: Predicted demand --}}
            <div class="bg-neutral-50 p-2.5 dark:bg-neutral-900/90">
                <div class="h-2.5 w-24 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <div class="h-4 w-16 rounded bg-neutral-300 dark:bg-neutral-700"></div>
                    <div class="h-2.5 w-8 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                </div>
            </div>

            {{-- Slot 3: Daily forecast --}}
            <div class="bg-neutral-50 p-2.5 dark:bg-neutral-900/90">
                <div class="h-2.5 w-20 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <div class="h-4 w-14 rounded bg-neutral-300 dark:bg-neutral-700"></div>
                    <div class="h-2.5 w-12 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                </div>
            </div>

            {{-- Slot 4: Current Stock --}}
            <div class="bg-neutral-50 p-2.5 dark:bg-neutral-900/90">
                <div class="h-2.5 w-20 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <div class="h-4 w-16 rounded bg-neutral-300 dark:bg-neutral-700"></div>
                    <div class="h-2.5 w-8 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                </div>
            </div>

            {{-- Slot 5: Estimated Stock Risk Badge --}}
            <div class="bg-neutral-50 p-2.5 dark:bg-neutral-900/90">
                <div class="h-2.5 w-28 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                <div class="mt-1.5">
                    <div class="h-5 w-20 rounded-full bg-neutral-300 dark:bg-neutral-700/80"></div>
                </div>
            </div>

            {{-- Slot 6: Reorder units --}}
            <div class="bg-neutral-50 p-2.5 dark:bg-neutral-900/90">
                <div class="h-2.5 w-20 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <div class="h-4 w-14 rounded bg-neutral-300 dark:bg-neutral-700"></div>
                    <div class="h-2.5 w-8 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                </div>
            </div>
        </dl>

        {{-- 3. Demand vs Forecast Chart Section Skeleton --}}
        <section class="min-w-0 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            {{-- Header with title and legend indicators --}}
            <header class="flex flex-col gap-2 border-b border-neutral-200 px-3 py-2 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                <div class="flex items-center gap-2">
                    <div class="h-4 w-36 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px] sm:gap-3">
                    <div class="inline-flex items-center gap-2 rounded-md px-1.5 py-1">
                        <span class="h-1 w-4 rounded-full bg-primary-600/40 dark:bg-primary-500/50"></span>
                        <span class="h-3 w-24 rounded bg-neutral-200 dark:bg-neutral-700/60"></span>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-md px-1.5 py-1">
                        <span class="h-0.5 w-4 border-t-2 border-dashed border-violet-600/40 dark:border-violet-400/50"></span>
                        <span class="h-3 w-16 rounded bg-neutral-200 dark:bg-neutral-700/60"></span>
                    </div>
                </div>
            </header>

            {{-- Plotting Area & Timeline Scrubber Skeleton --}}
            <div class="min-w-0 px-3 py-2.5">
                <div class="relative min-w-0 select-none">
                    {{-- Y-Axis Units/Day Label & Tick Placeholders --}}
                    <div class="pointer-events-none absolute left-0.5 top-0 z-10 select-none">
                        <span class="text-[9px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Units/day</span>
                    </div>
                    <div class="pointer-events-none absolute left-0.5 top-[16.6%] -translate-y-1/2 select-none">
                        <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    </div>
                    <div class="pointer-events-none absolute left-0.5 top-[33.8%] -translate-y-1/2 select-none">
                        <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    </div>
                    <div class="pointer-events-none absolute left-0.5 top-[51%] -translate-y-1/2 select-none">
                        <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    </div>
                    <div class="pointer-events-none absolute left-0.5 top-[68.2%] -translate-y-1/2 select-none">
                        <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    </div>
                    <div class="pointer-events-none absolute left-0.5 top-[85.4%] -translate-y-1/2 select-none">
                        <div class="h-2.5 w-4 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    </div>

                    {{-- SVG Plot Area matching viewBox 0 0 760 240 and responsive height --}}
                    <svg
                        class="h-48 w-full select-none touch-none sm:h-52 lg:h-72 xl:h-80"
                        viewBox="0 0 760 240"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        {{-- Horizontal Grid Lines --}}
                        <line x1="50" y1="40" x2="725" y2="40" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                        <line x1="50" y1="81.25" x2="725" y2="81.25" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                        <line x1="50" y1="122.5" x2="725" y2="122.5" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                        <line x1="50" y1="163.75" x2="725" y2="163.75" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                        <line x1="50" y1="205" x2="725" y2="205" class="stroke-neutral-200 dark:stroke-neutral-700" stroke-width="1" vector-effect="non-scaling-stroke"></line>

                        {{-- Forecast Horizon Area Tint --}}
                        <rect x="475" y="20" width="250" height="185" rx="4" fill="#8b5cf6" fill-opacity="0.025"></rect>

                        {{-- Boundary Dividing Line --}}
                        <line x1="475" y1="20" x2="475" y2="205" class="stroke-neutral-300 dark:stroke-neutral-600" stroke-width="1.5" stroke-dasharray="2 3" vector-effect="non-scaling-stroke"></line>

                        {{-- Structural Line Paths (Non-fake data, low-contrast guide curves) --}}
                        <path d="M 50 175 Q 160 160, 240 180 T 360 145 T 475 205" fill="none" stroke="#1c75f5" stroke-opacity="0.18" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
                        <path d="M 475 205 L 485 130 Q 520 165, 600 165 T 725 165" fill="none" stroke="#8b5cf6" stroke-opacity="0.22" stroke-width="2.5" stroke-dasharray="6 4" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
                    </svg>
                </div>

                {{-- Chronological X-axis Labels Skeleton --}}
                <div class="mt-2 grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-neutral-100 pt-2 text-xs text-neutral-500 dark:border-neutral-800">
                    <div class="flex items-center justify-between gap-2">
                        <div class="h-3 w-12 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                        <div class="hidden sm:block h-2.5 w-24 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    </div>
                    <div class="flex items-center gap-1.5 px-3">
                        <span class="h-2 w-2 rounded-full bg-neutral-400 dark:bg-neutral-500"></span>
                        <div class="h-3 w-32 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="hidden sm:block h-2.5 w-28 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                        <div class="h-3 w-12 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    </div>
                </div>
            </div>
        </section>
    @else
        {{-- Chart-Only Skeleton Mode for Sub-views (e.g. Demand Forecast Details Index) --}}
        <div class="min-w-0">
            <div class="relative min-w-0 select-none">
                {{-- Y-Axis Units/Day Label & Tick Placeholders --}}
                <div class="pointer-events-none absolute left-0.5 top-0 z-10 select-none">
                    <span class="text-[9px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Units/day</span>
                </div>
                <div class="pointer-events-none absolute left-0.5 top-[16.6%] -translate-y-1/2 select-none">
                    <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                </div>
                <div class="pointer-events-none absolute left-0.5 top-[33.8%] -translate-y-1/2 select-none">
                    <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                </div>
                <div class="pointer-events-none absolute left-0.5 top-[51%] -translate-y-1/2 select-none">
                    <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                </div>
                <div class="pointer-events-none absolute left-0.5 top-[68.2%] -translate-y-1/2 select-none">
                    <div class="h-2.5 w-5 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                </div>
                <div class="pointer-events-none absolute left-0.5 top-[85.4%] -translate-y-1/2 select-none">
                    <div class="h-2.5 w-4 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                </div>

                {{-- SVG Plot Area matching viewBox 0 0 760 240 and responsive height --}}
                <svg
                    class="h-48 w-full select-none touch-none sm:h-52 lg:h-72 xl:h-80"
                    viewBox="0 0 760 240"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    {{-- Horizontal Grid Lines --}}
                    <line x1="50" y1="40" x2="725" y2="40" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                    <line x1="50" y1="81.25" x2="725" y2="81.25" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                    <line x1="50" y1="122.5" x2="725" y2="122.5" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                    <line x1="50" y1="163.75" x2="725" y2="163.75" class="stroke-neutral-100 dark:stroke-neutral-800" stroke-width="1" vector-effect="non-scaling-stroke"></line>
                    <line x1="50" y1="205" x2="725" y2="205" class="stroke-neutral-200 dark:stroke-neutral-700" stroke-width="1" vector-effect="non-scaling-stroke"></line>

                    {{-- Forecast Horizon Area Tint --}}
                    <rect x="475" y="20" width="250" height="185" rx="4" fill="#8b5cf6" fill-opacity="0.025"></rect>

                    {{-- Boundary Dividing Line --}}
                    <line x1="475" y1="20" x2="475" y2="205" class="stroke-neutral-300 dark:stroke-neutral-600" stroke-width="1.5" stroke-dasharray="2 3" vector-effect="non-scaling-stroke"></line>

                    {{-- Structural Line Paths --}}
                    <path d="M 50 175 Q 160 160, 240 180 T 360 145 T 475 205" fill="none" stroke="#1c75f5" stroke-opacity="0.18" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
                    <path d="M 475 205 L 485 130 Q 520 165, 600 165 T 725 165" fill="none" stroke="#8b5cf6" stroke-opacity="0.22" stroke-width="2.5" stroke-dasharray="6 4" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
                </svg>
            </div>

            {{-- Chronological X-axis Labels Skeleton --}}
            <div class="mt-2 grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-neutral-100 pt-2 text-xs text-neutral-500 dark:border-neutral-800">
                <div class="flex items-center justify-between gap-2">
                    <div class="h-3 w-12 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="hidden sm:block h-2.5 w-24 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                </div>
                <div class="flex items-center gap-1.5 px-3">
                    <span class="h-2 w-2 rounded-full bg-neutral-400 dark:bg-neutral-500"></span>
                    <div class="h-3 w-32 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <div class="hidden sm:block h-2.5 w-28 rounded bg-neutral-200 dark:bg-neutral-800"></div>
                    <div class="h-3 w-12 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                </div>
            </div>
        </div>
    @endif
</div>
