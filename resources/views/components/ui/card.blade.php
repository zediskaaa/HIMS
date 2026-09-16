@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
])

<section {{ $attributes->merge([
    'class' => 'min-w-0 max-w-full bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-lg shadow-sm overflow-hidden',
]) }}>
    @if ($title || isset($header) || isset($actions))
        <header class="flex flex-col items-start justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 px-4 py-4 sm:flex-row sm:gap-4 sm:px-5">
            <div class="min-w-0">
                @isset($header)
                    {{ $header }}
                @else
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $subtitle }}</p>
                    @endif
                @endisset
            </div>

            @isset($actions)
                <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="min-w-0 {{ $padding ? 'p-4 sm:p-5' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="min-w-0 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/60 px-4 py-3 sm:px-5">
            {{ $footer }}
        </footer>
    @endisset
</section>
