{{-- Reusable Skeleton Loader for AI Forecast Insight Panel --}}
<div {{ $attributes->merge(['class' => 'space-y-2.5 animate-pulse motion-reduce:animate-none select-none']) }} aria-hidden="true">
    {{-- 1. Key Forecast Indicators Skeleton (4-Col Grid matching loaded card) --}}
    <div class="grid grid-cols-4 gap-1.5 rounded-lg border border-neutral-200/90 bg-neutral-50/70 p-1.5 text-center dark:border-neutral-800 dark:bg-neutral-800/40">
        @for ($i = 0; $i < 4; $i++)
            <div class="min-w-0 p-1 space-y-1.5">
                <div class="h-2 w-10 mx-auto bg-neutral-200 dark:bg-neutral-700/60 rounded"></div>
                <div class="h-5 w-6 mx-auto bg-neutral-200 dark:bg-neutral-700 rounded"></div>
            </div>
        @endfor
    </div>

    {{-- 2. Confidence & Demand Risk Breakdown Skeleton --}}
    <div class="space-y-1 text-[11px]">
        <div class="flex items-center justify-between">
            <div class="h-3 w-36 bg-neutral-200 dark:bg-neutral-700/60 rounded"></div>
            <div class="h-3 w-28 bg-neutral-200 dark:bg-neutral-700/60 rounded"></div>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-neutral-200/80 dark:bg-neutral-700/80"></div>
    </div>

    {{-- 3. Top Forecast Risks List Skeleton --}}
    <div class="space-y-1.5 pt-0.5">
        <div class="flex items-center justify-between">
            <div class="h-3.5 w-28 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
            <div class="h-2.5 w-24 bg-neutral-200 dark:bg-neutral-700/60 rounded"></div>
        </div>
        <div class="space-y-1.5">
            @for ($i = 0; $i < 2; $i++)
                <div class="rounded-md border border-neutral-200/80 bg-neutral-50/50 p-2 dark:border-neutral-800 dark:bg-neutral-800/30 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <div class="h-3 w-36 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                        <div class="h-3.5 w-16 bg-neutral-200 dark:bg-neutral-700/80 rounded-full"></div>
                    </div>
                    {{-- Dual micro-bars skeleton matching Demand & Stock rows --}}
                    <div class="space-y-1 text-[10px]">
                        <div class="flex items-center gap-1.5">
                            <div class="h-2 w-10 shrink-0 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                            <div class="h-1.5 flex-1 rounded-full bg-neutral-200/80 dark:bg-neutral-700/80"></div>
                            <div class="h-2 w-6 shrink-0 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="h-2 w-10 shrink-0 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                            <div class="h-1.5 flex-1 rounded-full bg-neutral-200/80 dark:bg-neutral-700/80"></div>
                            <div class="h-2 w-6 shrink-0 rounded bg-neutral-200 dark:bg-neutral-700/60"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>
    </div>

    {{-- 4. Compact Action Bar Skeleton --}}
    <div class="flex items-center gap-1.5 pt-0.5">
        <div class="h-7.5 flex-1 rounded-md bg-neutral-200/80 dark:bg-neutral-700/80"></div>
        <div class="h-7.5 flex-1 rounded-md border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800"></div>
    </div>

    {{-- 5. Preserved Advisory Footer Skeleton --}}
    <div class="border-t border-neutral-100 pt-1.5 dark:border-neutral-800/80 flex items-center justify-between">
        <div class="h-2.5 w-48 bg-neutral-200/80 dark:bg-neutral-700/60 rounded"></div>
    </div>
</div>
