<header class="sticky top-0 z-30 flex min-w-0 max-w-full items-center gap-3 h-16 px-4 sm:px-6 lg:px-8
               bg-white/95 backdrop-blur border-b border-neutral-200
               dark:bg-neutral-900/95 dark:border-neutral-800">
    {{-- Sidebar toggle --}}
    <button
        type="button"
        x-on:click="sidebarOpen = !sidebarOpen"
        class="p-2 -ml-2 rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900
               dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        :aria-expanded="sidebarOpen ? 'true' : 'false'"
        title="Toggle navigation"
    >
        <span class="sr-only">Toggle navigation</span>
        <x-ui.icon name="bars-3" class="w-5 h-5" />
    </button>

    @isset($topbarTitle)
        <div class="min-w-0 hidden xl:block">
            <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{{ $topbarTitle }}</p>
        </div>
    @endisset

    {{-- Global HIMS Multi-Entity Live Search & Autocomplete --}}
    <div
        class="relative flex-1 min-w-0 max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg"
        x-data="himsGlobalSearch({
            endpoint: @js(route('global-search')),
            initialQuery: @js(request('search', ''))
        })"
        x-on:click.outside="close()"
        x-on:keydown.escape.stop="close()"
    >
        <form method="GET" action="{{ route('inventory.items') }}" role="search" x-on:submit="selectActive($event)">
            {{-- Search Input Icon --}}
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-neutral-400 dark:text-neutral-500">
                <x-ui.icon name="magnifying-glass" class="w-4 h-4" />
            </span>
            <label for="global-search" class="sr-only">Search HIMS records</label>
            <input
                id="global-search"
                name="search"
                type="search"
                autocomplete="off"
                x-model="query"
                x-on:input="queue($event.target.value)"
                x-on:focus="onFocus()"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter="selectActive($event)"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="global-search-dropdown"
                x-bind:aria-expanded="open"
                x-bind:aria-activedescendant="activeIndex >= 0 ? `global-search-item-${activeIndex}` : null"
                placeholder="Search items, SKU, barcode..."
                class="w-full pl-9 pr-8 py-2 text-sm bg-neutral-50 border border-neutral-300 rounded-md
                       placeholder:text-neutral-400 focus:bg-white focus:border-primary-500
                       focus:ring-2 focus:ring-primary-500/30
                       dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:focus:bg-neutral-900
                       [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden"
            />
            {{-- Clear button --}}
            <div class="absolute inset-y-0 right-0 flex items-center pr-2.5">
                <button
                    x-show="query.length > 0"
                    x-cloak
                    type="button"
                    x-on:click="clear()"
                    class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition-colors p-0.5 rounded"
                    title="Clear search"
                >
                    <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                </button>
            </div>
        </form>

        {{-- Autocomplete Results Dropdown Panel --}}
        <div
            id="global-search-dropdown"
            x-show="open"
            x-cloak
            x-transition.opacity.duration.150ms
            class="absolute left-0 right-0 top-full mt-1.5 w-full z-50
                   rounded-xl border border-neutral-200 dark:border-neutral-800
                   bg-white dark:bg-neutral-900 shadow-2xl overflow-hidden"
            role="listbox"
        >
            {{-- Loading State --}}
            <div x-show="loading && categories.length === 0" class="p-6 text-center">
                <div class="inline-flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                    <svg class="animate-spin h-4 w-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Searching records...</span>
                </div>
            </div>

            {{-- Failed / Network Error State --}}
            <div x-show="!loading && failed" x-cloak class="p-5 text-center text-sm text-rose-600 dark:text-rose-400">
                <p x-text="errorMessage || 'Search request could not be completed.'"></p>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Press Enter to search catalog directly.</p>
            </div>

            {{-- Empty Results State --}}
            <div
                x-show="!loading && !failed && loaded && categories.length === 0"
                x-cloak
                class="p-6 text-center"
            >
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500 mb-2">
                    <x-ui.icon name="magnifying-glass" class="w-5 h-5" />
                </div>
                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">No records found</p>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    No matching items, suppliers, documents, or POs found for <span class="font-semibold text-neutral-700 dark:text-neutral-300" x-text="`&ldquo;${query}&rdquo;`"></span>
                </p>
            </div>

            {{-- Grouped Search Results --}}
            <div
                x-show="categories.length > 0"
                class="max-h-[min(70vh,520px)] overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800/70"
            >
                <template x-for="category in categories" :key="category.key">
                    <div class="py-2">
                        {{-- Category Header --}}
                        <div class="flex items-center justify-between px-3.5 py-1 text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                            <span x-text="`${category.label} (${category.total})`"></span>
                            <template x-if="category.view_all_url && category.total > 0">
                                <a
                                    :href="category.view_all_url"
                                    class="text-[11px] font-medium text-primary-600 dark:text-primary-400 hover:underline normal-case tracking-normal"
                                >
                                    View all &rarr;
                                </a>
                            </template>
                        </div>

                        {{-- Items in Category --}}
                        <div class="mt-1 space-y-0.5 px-1.5">
                            <template x-for="item in category.items" :key="item.id">
                                <button
                                    type="button"
                                    class="w-full flex items-start gap-3 px-2.5 py-2 rounded-lg text-left transition-colors"
                                    :id="`global-search-item-${flatItems.findIndex(f => f.id === item.id)}`"
                                    :class="activeIndex === flatItems.findIndex(f => f.id === item.id)
                                        ? 'bg-primary-50 dark:bg-primary-950/70 text-primary-950 dark:text-primary-100 ring-1 ring-primary-500/20'
                                        : 'text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100/80 dark:hover:bg-neutral-800/70'"
                                    x-on:mouseenter="activeIndex = flatItems.findIndex(f => f.id === item.id)"
                                    x-on:click="navigate(item.url)"
                                    role="option"
                                    :aria-selected="activeIndex === flatItems.findIndex(f => f.id === item.id)"
                                >
                                    {{-- Leading Record Type Icon --}}
                                    <span
                                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400"
                                        aria-hidden="true"
                                    >
                                        <template x-if="item.icon === 'cube'">
                                            <x-ui.icon name="cube" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'building-office-2'">
                                            <x-ui.icon name="building-office-2" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'user-circle'">
                                            <x-ui.icon name="user-circle" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'clipboard-document-check'">
                                            <x-ui.icon name="clipboard-document-check" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'document-text'">
                                            <x-ui.icon name="document-text" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'arrows-right-left'">
                                            <x-ui.icon name="arrows-right-left" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'truck'">
                                            <x-ui.icon name="truck" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'document-duplicate'">
                                            <x-ui.icon name="document-duplicate" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'archive-box'">
                                            <x-ui.icon name="archive-box" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'clipboard-document-list'">
                                            <x-ui.icon name="clipboard-document-list" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'table-cells'">
                                            <x-ui.icon name="table-cells" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'tag'">
                                            <x-ui.icon name="tag" class="w-4 h-4" />
                                        </template>
                                        <template x-if="item.icon === 'map-pin'">
                                            <x-ui.icon name="map-pin" class="w-4 h-4" />
                                        </template>
                                    </span>

                                    {{-- Information & Subtitle --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-semibold truncate text-neutral-900 dark:text-neutral-100" x-text="item.title"></span>
                                            <template x-if="item.badge">
                                                <span
                                                    class="shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': item.badge_variant === 'success',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': item.badge_variant === 'warning',
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': item.badge_variant === 'danger',
                                                        'bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300': item.badge_variant === 'primary',
                                                        'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300': !item.badge_variant || item.badge_variant === 'neutral'
                                                    }"
                                                    x-text="item.badge"
                                                ></span>
                                            </template>
                                        </div>
                                        <p class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate mt-0.5" x-text="item.subtitle"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer Keyboard Navigation Hints --}}
            <div class="flex items-center justify-between gap-2 px-3.5 py-2 bg-neutral-50 dark:bg-neutral-950/50 border-t border-neutral-100 dark:border-neutral-800 text-[11px] text-neutral-400 dark:text-neutral-500">
                <div class="flex items-center gap-2.5 truncate">
                    <span class="shrink-0"><kbd class="font-mono bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5 text-[10px]">↑</kbd> <kbd class="font-mono bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5 text-[10px]">↓</kbd> navigate</span>
                    <span class="hidden sm:inline shrink-0"><kbd class="font-mono bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5 text-[10px]">↵</kbd> select</span>
                    <span class="hidden md:inline shrink-0"><kbd class="font-mono bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5 text-[10px]">esc</kbd> close</span>
                </div>
                <template x-if="flatItems.length > 0">
                    <span class="shrink-0 font-medium" x-text="`${flatItems.length} suggestions`"></span>
                </template>
            </div>
        </div>
    </div>

    {{-- Spacer to push controls to the right --}}
    <div class="flex-1 min-w-0"></div>

    {{-- Dark / Light theme quick toggle --}}
    <x-ui.theme-toggle />

    {{-- Persistent, role-aware notifications --}}
    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
        <button
            type="button"
            x-on:click="open = !open"
            class="relative flex h-9 w-9 items-center justify-center rounded-md text-neutral-500 transition
                   hover:bg-neutral-100 hover:text-neutral-900
                   dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="notification-panel"
            aria-haspopup="dialog"
        >
            <span class="sr-only">Open notifications{{ $topbarUnreadCount > 0 ? ', '.$topbarUnreadCount.' unread' : '' }}</span>
            <x-ui.icon name="bell-alert" class="h-5 w-5" />
            @if($topbarUnreadCount > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full
                             border-2 border-white dark:border-neutral-900 bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
                      aria-hidden="true">
                    {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                </span>
            @endif
        </button>

        <div
            id="notification-panel"
            x-show="open"
            x-cloak
            x-on:click.outside="open = false"
            x-transition.origin.top.right
            class="fixed left-3 right-3 top-[4.25rem] z-50 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl
                   dark:border-neutral-800 dark:bg-neutral-900
                   sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-[26rem]"
            role="dialog"
            aria-modal="false"
            aria-label="Notifications"
        >
            <div class="flex items-center justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Notifications</h2>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $topbarUnreadCount > 0 ? $topbarUnreadCount.' unread' : 'You are all caught up' }}
                    </p>
                </div>
                @if($topbarUnreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-50
                                                       dark:text-primary-400 dark:hover:bg-primary-950/50
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                            Mark all as read
                        </button>
                    </form>
                @endif
            </div>

            <div class="max-h-[min(70vh,32rem)] overflow-y-auto overscroll-contain">
                @forelse($topbarNotifications as $notification)
                    @php
                        $priority = \App\Enums\NotificationPriority::tryFrom((string) ($notification->data['priority'] ?? ''))
                            ?? \App\Enums\NotificationPriority::Info;
                        $isUnread = $notification->read_at === null;
                        $accent = match($priority) {
                            \App\Enums\NotificationPriority::Critical => 'bg-rose-500',
                            \App\Enums\NotificationPriority::Warning => 'bg-amber-400',
                            default => 'bg-primary-400',
                        };
                        $timestamp = $notification->created_at->diffInSeconds(now()) < 45
                            ? 'Just now'
                            : $notification->created_at->diffForHumans();
                    @endphp
                    <div class="grid grid-cols-[3px_minmax(0,1fr)_2.25rem] border-b border-neutral-100 dark:border-neutral-800/80 last:border-b-0
                                {{ $isUnread ? 'bg-primary-50/55 dark:bg-primary-950/20' : 'bg-white dark:bg-neutral-900' }}">
                        <span class="{{ $accent }}" aria-hidden="true"></span>
                        <a href="{{ route('notifications.open', $notification->id) }}"
                           class="min-w-0 px-3 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/60 focus-visible:outline-none focus-visible:ring-2
                                  focus-visible:ring-inset focus-visible:ring-primary-500">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm {{ $isUnread ? 'font-semibold text-neutral-950 dark:text-neutral-50' : 'font-medium text-neutral-800 dark:text-neutral-200' }}">
                                    {{ $notification->data['title'] ?? 'HIMS notification' }}
                                </p>
                                @if($priority === \App\Enums\NotificationPriority::Critical)
                                    <span class="shrink-0 rounded-full bg-rose-100 dark:bg-rose-950/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                                        Critical
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-neutral-600 dark:text-neutral-400">
                                {{ $notification->data['message'] ?? '' }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-2 text-[11px] text-neutral-500 dark:text-neutral-400">
                                <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $timestamp }}</time>
                                @if($isUnread)
                                    <span class="inline-flex items-center gap-1 font-medium text-primary-700 dark:text-primary-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-500" aria-hidden="true"></span>
                                        Unread
                                    </span>
                                @else
                                    <span>Read</span>
                                @endif
                            </div>
                        </a>
                        <div class="flex items-start justify-center pt-3">
                            @if($isUnread)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="rounded-md p-1.5 text-neutral-400 hover:bg-white hover:text-primary-700 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-primary-400
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                            title="Mark as read">
                                        <span class="sr-only">Mark {{ $notification->data['title'] ?? 'notification' }} as read</span>
                                        <x-ui.icon name="check" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center px-6 py-10 text-center">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                            <x-ui.icon name="bell-alert" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-neutral-800 dark:text-neutral-200">No notifications</p>
                        <p class="mt-1 max-w-xs text-xs leading-5 text-neutral-500 dark:text-neutral-400">
                            Important updates that need your attention will appear here.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- User menu --}}
    <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
        <button
            type="button"
            x-on:click="open = !open"
            class="flex items-center gap-2.5 p-1.5 pr-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
        >
            <x-ui.avatar :user="Auth::user()" size="sm" />
            <div class="hidden sm:flex flex-col text-left leading-tight">
                <span class="text-xs font-semibold text-neutral-900 dark:text-neutral-100 whitespace-nowrap">
                    {{ Auth::user()?->name }}
                </span>
                <span class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                    {{ Auth::user()?->role?->label() ?? 'Staff' }}
                </span>
            </div>
            <x-ui.icon name="chevron-down" class="w-4 h-4 text-neutral-400 shrink-0" />
        </button>

        <div
            x-show="open"
            x-cloak
            x-on:click.outside="open = false"
            x-transition.origin.top.right
            class="absolute right-0 mt-1.5 w-60 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl shadow-lg py-1.5 z-50"
            role="menu"
        >
            <div class="px-3.5 py-2.5 border-b border-neutral-100 dark:border-neutral-800">
                <p class="text-xs font-bold text-neutral-900 dark:text-neutral-100">{{ Auth::user()?->name }}</p>
                <span class="inline-block mt-0.5 text-[10px] font-semibold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/60 px-1.5 py-0.5 rounded">
                    {{ Auth::user()?->role?->label() ?? 'Staff' }}
                </span>
                <p class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate mt-1">{{ Auth::user()?->email }}</p>
            </div>

            <a href="{{ route('profile.edit') }}" role="menuitem"
               class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                <x-ui.icon name="user-circle" class="w-4 h-4 text-neutral-400" />
                {{ Auth::user()?->isAdministrator() ? 'Account settings' : 'Profile settings' }}
            </a>

            <form method="POST" action="{{ route(\App\Support\AuthenticationContext::logoutRoute()) }}"
                  data-manual-logout
                  data-confirm-title="Confirm logout"
                  data-confirm-message="Are you sure you want to log out?"
                  data-confirm-label="Log Out">
                @csrf
                <button type="submit" role="menuitem"
                        class="flex items-center gap-2 w-full px-3.5 py-2 text-xs font-medium text-left text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40">
                    <x-ui.icon name="arrow-right-on-rectangle" class="w-4 h-4 text-rose-500 dark:text-rose-400" />
                    Log out
                </button>
            </form>
        </div>
    </div>
</header>
