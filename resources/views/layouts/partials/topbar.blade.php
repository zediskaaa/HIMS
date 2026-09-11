<header class="sticky top-0 z-30 flex min-w-0 max-w-full items-center gap-3 h-16 px-4 sm:px-6 lg:px-8
               bg-white/95 backdrop-blur border-b border-neutral-200">
    {{-- Sidebar toggle (small screens only) --}}
    <button
        type="button"
        x-on:click="sidebarOpen = !sidebarOpen"
        class="p-2 -ml-2 rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 lg:hidden
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        :aria-expanded="sidebarOpen ? 'true' : 'false'"
    >
        <span class="sr-only">Toggle navigation</span>
        <x-ui.icon name="bars-3" class="w-5 h-5" />
    </button>

    <div class="flex-1 min-w-0">
        @isset($topbarTitle)
            <p class="text-sm font-semibold text-neutral-900 truncate">{{ $topbarTitle }}</p>
        @endisset
    </div>

    {{-- Search: visual affordance for the demo; wiring lands with global search. --}}
    <div class="relative hidden sm:block">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-neutral-400">
            <x-ui.icon name="magnifying-glass" class="w-4 h-4" />
        </span>
        <label for="global-search" class="sr-only">Search</label>
        <input
            id="global-search"
            type="search"
            placeholder="Search items, POs, suppliers"
            class="w-56 lg:w-72 pl-9 pr-3 py-2 text-sm bg-neutral-50 border border-neutral-300 rounded-md
                   placeholder:text-neutral-400 focus:bg-white focus:border-primary-500
                   focus:ring-2 focus:ring-primary-500/30"
        />
    </div>

    {{-- Persistent, role-aware notifications --}}
    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
        <button
            type="button"
            x-on:click="open = !open"
            class="relative flex h-9 w-9 items-center justify-center rounded-md text-neutral-500 transition
                   hover:bg-neutral-100 hover:text-neutral-900 focus-visible:outline-none
                   focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="notification-panel"
            aria-haspopup="dialog"
        >
            <span class="sr-only">Open notifications{{ $topbarUnreadCount > 0 ? ', '.$topbarUnreadCount.' unread' : '' }}</span>
            <x-ui.icon name="bell-alert" class="h-5 w-5" />
            @if($topbarUnreadCount > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full
                             border-2 border-white bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
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
                   sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-[26rem]"
            role="dialog"
            aria-modal="false"
            aria-label="Notifications"
        >
            <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900">Notifications</h2>
                    <p class="text-xs text-neutral-500">
                        {{ $topbarUnreadCount > 0 ? $topbarUnreadCount.' unread' : 'You are all caught up' }}
                    </p>
                </div>
                @if($topbarUnreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-50
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
                    <div class="grid grid-cols-[3px_minmax(0,1fr)_2.25rem] border-b border-neutral-100 last:border-b-0
                                {{ $isUnread ? 'bg-primary-50/55' : 'bg-white' }}">
                        <span class="{{ $accent }}" aria-hidden="true"></span>
                        <a href="{{ route('notifications.open', $notification->id) }}"
                           class="min-w-0 px-3 py-3 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2
                                  focus-visible:ring-inset focus-visible:ring-primary-500">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm {{ $isUnread ? 'font-semibold text-neutral-950' : 'font-medium text-neutral-800' }}">
                                    {{ $notification->data['title'] ?? 'HIMS notification' }}
                                </p>
                                @if($priority === \App\Enums\NotificationPriority::Critical)
                                    <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700">
                                        Critical
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-neutral-600">
                                {{ $notification->data['message'] ?? '' }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-2 text-[11px] text-neutral-500">
                                <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $timestamp }}</time>
                                @if($isUnread)
                                    <span class="inline-flex items-center gap-1 font-medium text-primary-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
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
                                            class="rounded-md p-1.5 text-neutral-400 hover:bg-white hover:text-primary-700
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
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                            <x-ui.icon name="bell-alert" class="h-5 w-5" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-neutral-800">No notifications</p>
                        <p class="mt-1 max-w-xs text-xs leading-5 text-neutral-500">
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
            class="flex items-center gap-2 p-1 pr-2 rounded-md hover:bg-neutral-100
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
        >
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100
                         text-primary-700 text-xs font-semibold shrink-0">
                {{ Str::upper(Str::substr(Auth::user()?->name ?? '?', 0, 1)) }}
            </span>
            <span class="hidden sm:block text-sm font-medium text-neutral-700 max-w-32 truncate">
                {{ Auth::user()?->name }}
            </span>
            <x-ui.icon name="chevron-down" class="w-4 h-4 text-neutral-400" />
        </button>

        <div
            x-show="open"
            x-cloak
            x-on:click.outside="open = false"
            x-transition.origin.top.right
            class="absolute right-0 mt-1 w-56 bg-white border border-neutral-200 rounded-md shadow-lg py-1"
            role="menu"
        >
            <div class="px-3 py-2 border-b border-neutral-100">
                <p class="text-sm font-medium text-neutral-900 truncate">{{ Auth::user()?->name }}</p>
                <p class="text-xs text-neutral-500 truncate">{{ Auth::user()?->email }}</p>
            </div>

            <a href="{{ route('profile.edit') }}" role="menuitem"
               class="flex items-center gap-2 px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-50">
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
                        class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-neutral-700 hover:bg-neutral-50">
                    <x-ui.icon name="arrow-right-on-rectangle" class="w-4 h-4 text-neutral-400" />
                    Log out
                </button>
            </form>
        </div>
    </div>
</header>
