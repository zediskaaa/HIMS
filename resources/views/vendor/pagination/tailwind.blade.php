@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-neutral-600 dark:text-neutral-400">
        {{-- Results Summary --}}
        <div>
            <p>
                Showing
                <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $paginator->lastItem() }}</span>
                of
                <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ number_format($paginator->total()) }}</span>
                items
            </p>
        </div>

        {{-- Navigation Controls --}}
        <div class="flex items-center gap-1">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                          class="inline-flex items-center justify-center gap-1 rounded-lg border border-neutral-200 bg-neutral-100/60 px-2.5 py-1.5 text-xs font-medium text-neutral-400 cursor-not-allowed dark:border-neutral-800 dark:bg-neutral-800/40 dark:text-neutral-600">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        <span class="sr-only sm:not-sr-only">Previous</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                       class="inline-flex items-center justify-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        <span class="sr-only sm:not-sr-only">Previous</span>
                    </a>
                @endif

                {{-- Pagination Page Elements (Capped at maximum 20 buttons) --}}
                @php $renderedButtons = 0; @endphp
                <div class="hidden sm:flex sm:items-center sm:gap-1">
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true" class="inline-flex items-center justify-center px-2 py-1.5 text-xs font-medium text-neutral-400 dark:text-neutral-500">
                                {{ $element }}
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @php
                                    if (++$renderedButtons > 20) {
                                        break 2;
                                    }
                                @endphp
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page"
                                          class="inline-flex min-w-[2rem] items-center justify-center rounded-lg bg-neutral-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-2xs dark:bg-primary-600 dark:text-white">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                       class="inline-flex min-w-[2rem] items-center justify-center rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 transition">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                       class="inline-flex items-center justify-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 transition">
                        <span class="sr-only sm:not-sr-only">Next</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                          class="inline-flex items-center justify-center gap-1 rounded-lg border border-neutral-200 bg-neutral-100/60 px-2.5 py-1.5 text-xs font-medium text-neutral-400 cursor-not-allowed dark:border-neutral-800 dark:bg-neutral-800/40 dark:text-neutral-600">
                        <span class="sr-only sm:not-sr-only">Next</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                @endif
            </div>
    </nav>
@endif
