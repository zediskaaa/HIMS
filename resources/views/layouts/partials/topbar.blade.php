@php($isSupplierPortalUser = auth()->user()?->role?->isSupplier())

<header class="hims-topbar sticky top-0 z-30 flex min-h-16 min-w-0 max-w-full flex-wrap items-center gap-2 border-b px-4 py-2 backdrop-blur-xl sm:px-6 lg:h-16 lg:flex-nowrap lg:gap-3 lg:px-8 lg:py-0">
    {{-- Sidebar toggle --}}
    <button
        type="button"
        x-show="!sidebarOpen"
        x-cloak
        x-on:click="sidebarOpen = !sidebarOpen"
        class="-ml-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 lg:h-9 lg:w-9
               dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        :aria-expanded="sidebarOpen ? 'true' : 'false'"
        aria-controls="primary-navigation"
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

    @if ($isSupplierPortalUser)
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-neutral-900 dark:text-neutral-100">Supplier workspace</p>
            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ auth()->user()?->supplier?->name }}</p>
        </div>
    @else
    {{-- Global HIMS Multi-Entity Live Search & Autocomplete --}}
    <div
        class="relative order-last w-full min-w-0 basis-full lg:order-none lg:w-auto lg:max-w-lg lg:flex-1 lg:basis-auto"
        x-data="himsGlobalSearch({
            endpoint: @js(route('global-search')),
            initialQuery: ''
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
                class="min-h-11 w-full rounded-md border border-neutral-300 bg-neutral-50 py-2 pl-9 pr-10 text-base lg:min-h-0 lg:text-sm
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
                    aria-label="Clear search"
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
            class="absolute left-0 right-0 top-full z-50 mt-1.5 w-full
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
                <x-ui.empty-artwork category="reports" size="sm" />
                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">No records found</p>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    No matching items, suppliers, documents, or POs found for <span class="font-semibold text-neutral-700 dark:text-neutral-300" x-text="`&ldquo;${query}&rdquo;`"></span>
                </p>
            </div>

            {{-- Grouped Search Results --}}
            <div
                x-show="categories.length > 0"
                class="max-h-[calc(100dvh-9rem)] divide-y divide-neutral-100 overflow-y-auto overscroll-contain dark:divide-neutral-800/70 sm:max-h-[min(70vh,520px)]"
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
                                    class="flex min-h-11 w-full items-start gap-3 rounded-lg px-2.5 py-2 text-left transition-colors"
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
                                                        'text-amber-800 bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300': item.badge_variant === 'warning',
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
            <div class="hidden items-center justify-between gap-2 border-t border-neutral-100 bg-neutral-50 px-3.5 py-2 text-[11px] text-neutral-400 dark:border-neutral-800 dark:bg-neutral-950/50 dark:text-neutral-500 sm:flex">
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

    @endif

    {{-- Spacer to push controls to the right --}}
    <div class="flex-1 min-w-0"></div>

    {{-- Dark / Light theme quick toggle --}}
    <x-ui.theme-toggle />

    {{-- Persistent, role-aware notifications --}}
    <div
        class="relative"
        x-data="{
            open: false,
            unreadCount: @js($topbarUnreadCount),
            nextUrl: @js($topbarNotificationsNextUrl),
            loading: false,
            loadError: '',
            statusMessage: '',
            init() {
                setInterval(() => {
                    if (!document.hidden) {
                        this.refresh();
                    }
                }, 60000);
            },
            async refresh() {
                try {
                    const response = await fetch(@js(route('notifications.index')), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (response.ok) {
                        const data = await response.json();
                        if (data.unread_count !== undefined) {
                            const countChanged = this.unreadCount !== data.unread_count;
                            this.unreadCount = data.unread_count;
                            if (countChanged && data.html) {
                                this.$refs.notificationItems.innerHTML = data.html;
                                this.nextUrl = data.next_url;
                            }
                        }
                    }
                } catch (_) {}
            },
            async loadMore() {
                if (!this.nextUrl || this.loading) return;

                this.loading = true;
                this.loadError = '';

                try {
                    const response = await fetch(this.nextUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) throw new Error('Notification request failed.');

                    const data = await response.json();
                    this.$refs.notificationItems.insertAdjacentHTML('beforeend', data.html);
                    this.nextUrl = data.next_url;
                    this.statusMessage = data.loaded_count > 0
                        ? `Loaded ${data.loaded_count} more notifications.`
                        : 'All notifications are loaded.';
                } catch (error) {
                    this.loadError = 'Could not load older notifications. Please try again.';
                } finally {
                    this.loading = false;
                }
            }
        }"
        x-on:keydown.escape.window="open = false"
    >
        <button
            type="button"
            x-on:click="open = !open; if (open) refresh();"
            class="relative flex h-11 w-11 items-center justify-center rounded-md text-neutral-500 transition sm:h-9 sm:w-9
                   hover:bg-neutral-100 hover:text-neutral-900
                   dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="notification-panel"
            aria-haspopup="dialog"
        >
            <span class="sr-only" x-text="unreadCount > 0 ? `Open notifications, ${unreadCount} unread` : 'Open notifications'">Open notifications</span>
            <x-ui.icon name="bell-alert" class="h-5 w-5" />
            <span x-show="unreadCount > 0"
                  x-cloak
                  class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full
                         border-2 border-white dark:border-neutral-900 bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
                  x-text="unreadCount > 99 ? '99+' : unreadCount"
                  aria-hidden="true">
                {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
            </span>
        </button>

        <div
            id="notification-panel"
            x-show="open"
            x-cloak
            x-on:click.outside="open = false"
            x-transition.origin.top.right
            class="fixed inset-x-3 top-[4.25rem] z-50 w-auto min-w-0 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl
                   dark:border-neutral-800 dark:bg-neutral-900
                   sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-[26rem] sm:max-w-[calc(100vw-2rem)]"
            role="dialog"
            aria-modal="false"
            aria-label="Notifications"
        >
            <div class="flex min-w-0 items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Notifications</h2>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400"
                       x-text="unreadCount > 0 ? `${unreadCount} unread` : 'You are all caught up'">
                        {{ $topbarUnreadCount > 0 ? $topbarUnreadCount.' unread' : 'You are all caught up' }}
                    </p>
                </div>
                @if($topbarUnreadCount > 0)
                    <div x-show="unreadCount > 0">
                        <form method="POST" action="{{ route('notifications.read-all') }}" class="shrink-0">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="whitespace-nowrap rounded-md px-2 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-50
                                                           dark:text-primary-400 dark:hover:bg-primary-950/50
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                Mark all as read
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="max-h-[min(70vh,32rem)] w-full min-w-0 overflow-x-hidden overflow-y-auto overscroll-contain">
                <div x-ref="notificationItems" :aria-busy="loading">
                    @include('layouts.partials.notification-items', ['notifications' => $topbarNotifications])
                </div>

                @if($topbarNotifications->isEmpty())
                    <div class="flex flex-col items-center px-6 py-10 text-center">
                        <x-ui.empty-artwork category="governance" size="sm" />
                        <p class="mt-3 text-sm font-semibold text-neutral-800 dark:text-neutral-200">No notifications</p>
                        <p class="mt-1 max-w-xs text-xs leading-5 text-neutral-500 dark:text-neutral-400">
                            Important updates that need your attention will appear here.
                        </p>
                    </div>
                @endif

                <div x-show="nextUrl || loadError" x-cloak class="border-t border-neutral-200 p-3 dark:border-neutral-800">
                    <p x-show="loadError" x-text="loadError" role="alert" class="mb-2 text-center text-xs text-danger-700 dark:text-danger-300"></p>
                    <button
                        x-show="nextUrl"
                        type="button"
                        x-on:click="loadMore()"
                        :disabled="loading"
                        :aria-busy="loading"
                        class="flex min-h-11 w-full items-center justify-center rounded-lg bg-neutral-100 px-4 py-2 text-sm font-semibold text-neutral-900 transition hover:bg-neutral-200 disabled:cursor-wait disabled:opacity-70 dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        <span x-text="loading ? 'Loading older notifications...' : (loadError ? 'Try again' : 'See previous notifications')"></span>
                    </button>
                    <p class="sr-only" aria-live="polite" x-text="statusMessage"></p>
                </div>
            </div>
        </div>
    </div>

    {{-- User menu --}}
    <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
        <button
            type="button"
            x-on:click="open = !open"
            class="flex min-h-11 items-center gap-2.5 rounded-lg p-1.5 pr-2 transition hover:bg-neutral-100 dark:hover:bg-neutral-800 sm:min-h-9
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
