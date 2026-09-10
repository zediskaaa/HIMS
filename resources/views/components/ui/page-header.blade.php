@props([
    'title' => '',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if (! empty($breadcrumbs))
            <nav aria-label="Breadcrumb" class="mb-1.5">
                <ol class="flex items-center flex-wrap gap-1 text-xs text-neutral-500">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1">
                            @if (! $loop->first)
                                <x-ui.icon name="chevron-right" class="w-3 h-3 text-neutral-300" />
                            @endif

                            @if ($url && ! $loop->last)
                                <a href="{{ $url }}" class="hover:text-primary-700 hover:underline">{{ $label }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" class="text-neutral-700 font-medium" @endif>
                                    {{ $label }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-1 max-w-3xl text-sm text-neutral-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0">{{ $actions }}</div>
    @endisset
</div>
