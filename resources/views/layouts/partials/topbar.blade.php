<header class="sticky top-0 z-30 flex items-center gap-3 h-16 px-4 lg:px-8
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
                Profile settings
            </a>

            <form method="POST" action="{{ route(\App\Support\AuthenticationContext::logoutRoute()) }}">
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
