@props([
    'title' => '',
    'subtitle' => null,
    'breadcrumbs' => [],
])

{{--
    `media` is an optional leading slot (a supplier logo, an avatar). It is not
    declared in @props — matching card.blade.php's `actions`/`header` slots — and
    every class below is guarded by @isset, so a caller that passes no media block
    renders exactly the markup it did before.
--}}
<div class="flex flex-col gap-4 sm:flex-row @isset($media) sm:items-center @else sm:items-end sm:justify-between @endisset">
    @isset($media)
        <div class="shrink-0">{{ $media }}</div>
    @endisset

    <div class="min-w-0 @isset($media) flex-1 @endisset">
        @if (! empty($breadcrumbs))
            <nav aria-label="Breadcrumb" class="mb-1.5">
                <ol class="flex items-center flex-wrap gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1">
                            @if (! $loop->first)
                                <x-ui.icon name="chevron-right" class="w-3 h-3 text-neutral-300 dark:text-neutral-600" />
                            @endif

                            @if ($url && ! $loop->last)
                                <a href="{{ $url }}" class="hover:text-primary-700 dark:hover:text-primary-400 hover:underline">{{ $label }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" class="text-neutral-700 dark:text-neutral-300 font-medium" @endif>
                                    {{ $label }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <h1 class="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-1 max-w-3xl text-sm text-neutral-500 dark:text-neutral-400">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0">{{ $actions }}</div>
    @endisset
</div>
