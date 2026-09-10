<div class="border-b border-neutral-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="-mb-px flex space-x-8 overflow-x-auto py-2 text-sm font-medium" aria-label="Logistics Submodule Navigation">
            <a href="{{ route('inventory.logistics') }}"
               class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-semibold transition-colors whitespace-nowrap {{ request()->routeIs('inventory.logistics') && !request()->routeIs('inventory.logistics.*') ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Overview Dashboard
            </a>

            <a href="{{ route('inventory.logistics.documents') }}"
               class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-semibold transition-colors whitespace-nowrap {{ request()->routeIs('inventory.logistics.documents*') ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Documents Registry
            </a>

            <a href="{{ route('inventory.logistics.shipments') }}"
               class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-semibold transition-colors whitespace-nowrap {{ request()->routeIs('inventory.logistics.shipments*') ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Shipments & 3PL Logistics
            </a>

            <a href="{{ route('inventory.logistics.iar.index') }}"
               class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-semibold transition-colors whitespace-nowrap {{ request()->routeIs('inventory.logistics.iar*') ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                COA IAR Reports (App. 50)
            </a>

            <a href="{{ route('inventory.logistics.chain-of-custody') }}"
               class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-semibold transition-colors whitespace-nowrap {{ request()->routeIs('inventory.logistics.chain-of-custody*') ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Chain of Custody Ledger
            </a>
        </nav>
    </div>
</div>
