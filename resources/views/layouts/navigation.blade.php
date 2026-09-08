<nav x-data="{ open: false }" class="border-b border-gray-100 bg-white">
    <!-- Primary Navigation Menu -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <!-- Logo -->
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <div class="relative" x-data="{ inventoryOpen: false }" @click.away="inventoryOpen = false">
                        <button
                            @click="inventoryOpen = !inventoryOpen"
                            :class="request()->routeIs('inventory*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                            class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                        >
                            <span>{{ __('Inventory') }}</span>
                            <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="inventoryOpen" class="absolute left-0 z-50 mt-2 w-96 rounded-xl border border-gray-200 bg-white p-3 shadow-xl" style="display: none;">
                            <div class="space-y-3">
                                <div>
                                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Smart Warehousing System (SWS)</div>
                                    <a href="{{ route('inventory.storage-locations') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Storage Locations</a>
                                    <a href="{{ route('inventory.stock') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Stock Movement & Transfers</a>
                                </div>

                                <div>
                                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Inventory Management System</div>
                                    <a href="{{ route('inventory.items') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Inventory Items</a>
                                    <a href="{{ route('inventory.adjustments') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Stock Adjustments</a>
                                    <a href="{{ route('inventory.reports') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Inventory Reports</a>
                                </div>

                                <div>
                                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Procurement &amp; Sourcing Management (PSM)</div>
                                    <a href="{{ route('inventory.purchases') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Purchase Orders & Receiving</a>
                                </div>

                                <div>
                                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Supplier / Vendor Management</div>
                                    <a href="{{ route('inventory.suppliers') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Supplier Directory</a>
                                </div>

                                <div>
                                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Document Tracking &amp; Logistics Records System (DTRS)</div>
                                    <a href="{{ route('inventory.logistics') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Logistics & Records</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}"
                              data-manual-logout
                              data-confirm-title="Confirm logout"
                              data-confirm-message="Are you sure you want to log out?"
                              data-confirm-label="Log Out">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').requestSubmit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <div class="px-4 py-2">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Inventory Modules</div>
                <div class="mt-2 space-y-1">
                    <x-responsive-nav-link :href="route('inventory')" :active="request()->routeIs('inventory')">
                        {{ __('Inventory Dashboard') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('inventory.storage-locations')" :active="request()->routeIs('inventory.storage-locations')">
                        {{ __('Storage Locations') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('inventory.items')" :active="request()->routeIs('inventory.items')">
                        {{ __('Inventory Items') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('inventory.adjustments')" :active="request()->routeIs('inventory.adjustments')">
                        {{ __('Stock Adjustments') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('inventory.suppliers')" :active="request()->routeIs('inventory.suppliers')">
                        {{ __('Supplier / Vendor Management') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('inventory.purchases')" :active="request()->routeIs('inventory.purchases')">
                        {{ __('Purchase Orders') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('inventory.logistics')" :active="request()->routeIs('inventory.logistics')">
                        {{ __('Document Tracking & Logistics') }}
                    </x-responsive-nav-link>
                </div>
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-gray-200 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-medium text-gray-800">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}"
                      data-manual-logout
                      data-confirm-title="Confirm logout"
                      data-confirm-message="Are you sure you want to log out?"
                      data-confirm-label="Log Out">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').requestSubmit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
