{{-- Reusable Skeleton Loader for Inventory Attention Card --}}
<div {{ $attributes->merge(['class' => 'divide-y divide-neutral-100 dark:divide-neutral-800 animate-pulse motion-reduce:animate-none select-none']) }} aria-hidden="true">
    <div class="flex items-center justify-between gap-3 border-b border-neutral-200 bg-neutral-50 px-4 py-2.5 text-xs dark:border-neutral-800 dark:bg-neutral-900/60">
        <div class="h-3.5 w-16 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
        <div class="h-3.5 w-24 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
    </div>
    @for ($i = 0; $i < 3; $i++)
        <div class="flex items-start gap-3 px-4 py-2.5">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-neutral-300 dark:bg-neutral-700"></span>
            <div class="min-w-0 flex-1 space-y-1.5">
                <div class="h-3 w-40 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                <div class="h-2.5 w-28 bg-neutral-200/80 dark:bg-neutral-800 rounded"></div>
            </div>
            <div class="h-4 w-14 rounded-full bg-neutral-200/80 dark:bg-neutral-800"></div>
        </div>
    @endfor
</div>
