<x-app-layout>
    <div class="space-y-3.5">
        {{-- Page Header --}}
        <x-ui.page-header
            title="Reorder Alerts & Inventory Notices"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory' => route('inventory.items'), 'Stock Alerts' => null]"
        />

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

        {{-- Top Summary Metric Cards --}}
        <div class="grid gap-3 grid-cols-1 sm:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Out of Stock</p>
                    <span class="flex h-2 w-2 rounded-full bg-rose-500"></span>
                </div>
                <p id="stat-out-of-stock" class="mt-1 text-xl sm:text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums">—</p>
                <p class="mt-0.5 text-[10px] text-neutral-400">Zero on hand available</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Low Stock Warnings</p>
                    <span class="flex h-2 w-2 rounded-full bg-amber-500"></span>
                </div>
                <p id="stat-low-stock" class="mt-1 text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">—</p>
                <p class="mt-0.5 text-[10px] text-neutral-400">Below reorder threshold</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Near-Expiry Batches</p>
                    <span class="flex h-2 w-2 rounded-full bg-indigo-500"></span>
                </div>
                <p id="stat-expiring" class="mt-1 text-xl sm:text-2xl font-bold text-indigo-600 dark:text-indigo-400 tabular-nums">—</p>
                <p class="mt-0.5 text-[10px] text-neutral-400">Expiring in &le; 30 days</p>
            </div>
        </div>

        {{-- Main Alerts Section (Viewport-Fitting with Internal Scroll) --}}
        <x-ui.card :padding="false">
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Live Inventory Attention Queue</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Automated reorder triggers and clinical stock surveillance.</p>
                </div>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.loader id="alerts-api-status" size="sm" label="Scanning stock levels..." class="text-xs text-neutral-500 dark:text-neutral-400" />
            </x-slot:actions>

            {{-- Viewport-Constrained Scrollable Body --}}
            <div class="p-4 sm:p-5 overflow-y-auto max-h-[calc(100vh-25.5rem)] min-h-[220px]">
                <div id="alerts-list" class="grid gap-4 md:grid-cols-2">
                    {{-- Skeleton Loading Placeholder --}}
                    <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/30 p-4 space-y-3 animate-pulse">
                        <div class="h-4 bg-neutral-200 dark:bg-neutral-700 rounded w-1/3"></div>
                        <div class="h-14 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700"></div>
                        <div class="h-14 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700"></div>
                    </div>
                    <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/30 p-4 space-y-3 animate-pulse">
                        <div class="h-4 bg-neutral-200 dark:bg-neutral-700 rounded w-1/3"></div>
                        <div class="h-14 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700"></div>
                        <div class="h-14 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700"></div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>

    <script>
        function escapeHtml(value) {
            if (value === null || value === undefined) {
                return '';
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function fetchAllInventoryItems() {
            const items = [];
            let page = 1;
            let lastPage = 1;

            do {
                const response = await fetch(`/api/v1/inventory-items?per_page=100&page=${page}`, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Session-Activity': 'passive'
                    }
                });

                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const payload = await response.json();
                items.push(...(payload.data || []));
                lastPage = payload.meta?.last_page ?? 1;
                page += 1;
            } while (page <= lastPage);

            return items;
        }

        async function loadAlertsFromApi() {
            const status = document.getElementById('alerts-api-status');
            const container = document.getElementById('alerts-list');
            const statOutOfStock = document.getElementById('stat-out-of-stock');
            const statLowStock = document.getElementById('stat-low-stock');
            const statExpiring = document.getElementById('stat-expiring');

            try {
                await fetch('/sanctum/csrf-cookie', {
                    credentials: 'same-origin',
                    headers: {
                        'X-Session-Activity': 'passive'
                    }
                });
                const items = await fetchAllInventoryItems();

                const outOfStock = items.filter(item => item.stock_status === 'out_of_stock' || (parseInt(item.quantity_on_hand) || 0) <= 0);
                const lowStockOnly = items.filter(item => item.stock_status === 'low_stock' && (parseInt(item.quantity_on_hand) || 0) > 0);
                const allLowOrOut = items.filter(item => ['low_stock', 'out_of_stock'].includes(item.stock_status) || (parseInt(item.quantity_on_hand) || 0) <= (parseInt(item.reorder_level) || 0));

                // Sort ascending by quantity on hand (0 / lowest stock items appear first)
                allLowOrOut.sort((a, b) => {
                    const qtyA = parseInt(a.quantity_on_hand, 10) || 0;
                    const qtyB = parseInt(b.quantity_on_hand, 10) || 0;
                    if (qtyA !== qtyB) {
                        return qtyA - qtyB;
                    }
                    return (a.name || '').localeCompare(b.name || '');
                });

                const expiringSoon = items.filter(item => {
                    if (!item.expiry_date) return false;
                    const expiry = new Date(item.expiry_date);
                    const cutoff = new Date();
                    cutoff.setDate(cutoff.getDate() + 30);
                    return expiry <= cutoff;
                });

                // Sort ascending by expiry date (earliest expiring items appear first)
                expiringSoon.sort((a, b) => {
                    const dateA = a.expiry_date ? new Date(a.expiry_date).getTime() : 0;
                    const dateB = b.expiry_date ? new Date(b.expiry_date).getTime() : 0;
                    if (dateA !== dateB) {
                        return dateA - dateB;
                    }
                    const qtyA = parseInt(a.quantity_on_hand, 10) || 0;
                    const qtyB = parseInt(b.quantity_on_hand, 10) || 0;
                    return qtyA - qtyB;
                });

                // Update Stat Cards
                if (statOutOfStock) statOutOfStock.textContent = outOfStock.length.toLocaleString();
                if (statLowStock) statLowStock.textContent = lowStockOnly.length.toLocaleString();
                if (statExpiring) statExpiring.textContent = expiringSoon.length.toLocaleString();

                if (allLowOrOut.length === 0 && expiringSoon.length === 0) {
                    container.className = 'w-full';
                    container.innerHTML = `
                        <div class="rounded-xl border border-dashed border-neutral-300 dark:border-neutral-700 bg-neutral-50/60 dark:bg-neutral-800/30 p-8 text-center">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mb-3">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">All Stock Levels Optimal</h3>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">No items are currently below reorder levels or expiring within the 30-day monitoring window.</p>
                        </div>
                    `;
                } else {
                    const cards = [];

                    // 1. Low Stock & Depleted Inventory Card
                    if (allLowOrOut.length > 0) {
                        const isSingleCategory = expiringSoon.length === 0;
                        cards.push(`
                            <div class="${isSingleCategory ? 'col-span-1 md:col-span-2' : ''} rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 p-4 sm:p-5 shadow-2xs">
                                <div class="flex items-center justify-between pb-3 border-b border-rose-200/70 dark:border-rose-900/40">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <h3 class="text-xs sm:text-sm font-bold text-rose-900 dark:text-rose-200">Reorder &amp; Low Stock Triggers</h3>
                                            <p class="text-[11px] text-rose-700 dark:text-rose-300">${allLowOrOut.length} item(s) require replenishment</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-rose-100 dark:bg-rose-900/70 px-2.5 py-0.5 text-xs font-bold text-rose-800 dark:text-rose-200 tabular-nums">${allLowOrOut.length}</span>
                                </div>
                                <div class="mt-3.5 space-y-2.5 ${isSingleCategory ? 'grid sm:grid-cols-2 gap-3 space-y-0' : ''}">
                                    ${allLowOrOut.map(item => {
                                        const isOut = (parseInt(item.quantity_on_hand) || 0) <= 0;
                                        const badgeClass = isOut 
                                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200 border-rose-300 dark:border-rose-800' 
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border-amber-300 dark:border-amber-800';
                                        const badgeLabel = isOut ? 'OUT OF STOCK' : 'LOW STOCK';

                                        return `
                                            <div class="rounded-lg bg-white dark:bg-neutral-900 p-3 shadow-2xs border border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 transition">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <h4 class="font-semibold text-xs text-neutral-900 dark:text-neutral-100 truncate" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h4>
                                                        <p class="text-[10px] font-mono text-neutral-400 mt-0.5">${escapeHtml(item.sku || 'No SKU')}</p>
                                                    </div>
                                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold border shrink-0 ${badgeClass}">
                                                        ${badgeLabel}
                                                    </span>
                                                </div>
                                                <div class="mt-2 pt-2 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-[11px]">
                                                    <span class="text-neutral-500 dark:text-neutral-400">
                                                        On Hand: <strong class="text-neutral-900 dark:text-neutral-100 tabular-nums">${escapeHtml(item.quantity_on_hand)}</strong>
                                                    </span>
                                                    <span class="text-neutral-500 dark:text-neutral-400">
                                                        Reorder At: <strong class="text-neutral-800 dark:text-neutral-200 tabular-nums">${escapeHtml(item.reorder_level || item.reorder_point || '0')}</strong>
                                                    </span>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `);
                    }

                    // 2. Near-Expiry Inventory Card
                    if (expiringSoon.length > 0) {
                        const isSingleCategory = allLowOrOut.length === 0;
                        cards.push(`
                            <div class="${isSingleCategory ? 'col-span-1 md:col-span-2' : ''} rounded-xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/40 dark:bg-indigo-950/20 p-4 sm:p-5 shadow-2xs">
                                <div class="flex items-center justify-between pb-3 border-b border-indigo-200/70 dark:border-indigo-900/40">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-300">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <h3 class="text-xs sm:text-sm font-bold text-indigo-900 dark:text-indigo-200">Near-Expiry Inventory</h3>
                                            <p class="text-[11px] text-indigo-700 dark:text-indigo-300">${expiringSoon.length} item(s) expiring within 30 days</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-indigo-100 dark:bg-indigo-900/70 px-2.5 py-0.5 text-xs font-bold text-indigo-800 dark:text-indigo-200 tabular-nums">${expiringSoon.length}</span>
                                </div>
                                <div class="mt-3.5 space-y-2.5 ${isSingleCategory ? 'grid sm:grid-cols-2 gap-3 space-y-0' : ''}">
                                    ${expiringSoon.map(item => `
                                        <div class="rounded-lg bg-white dark:bg-neutral-900 p-3 shadow-2xs border border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 transition">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <h4 class="font-semibold text-xs text-neutral-900 dark:text-neutral-100 truncate" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h4>
                                                    <p class="text-[10px] font-mono text-neutral-400 mt-0.5">${escapeHtml(item.sku || 'No SKU')}</p>
                                                </div>
                                                <span class="inline-flex items-center rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 px-1.5 py-0.5 text-[10px] font-bold shrink-0">
                                                    EXPIRING SOON
                                                </span>
                                            </div>
                                            <div class="mt-2 pt-2 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-[11px]">
                                                <span class="text-neutral-500 dark:text-neutral-400">
                                                    On Hand: <strong class="text-neutral-900 dark:text-neutral-100 tabular-nums">${escapeHtml(item.quantity_on_hand)}</strong>
                                                </span>
                                                <span class="text-indigo-700 dark:text-indigo-400 font-medium">
                                                    Exp: <strong class="font-bold">${escapeHtml(item.expiry_date)}</strong>
                                                </span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `);
                    }

                    container.className = 'grid gap-4 md:grid-cols-2';
                    container.innerHTML = cards.join('');
                }

                // Hide loader cleanly without showing 'Alerts loaded from API.' text
                if (status) {
                    status.classList.add('hidden');
                }
            } catch (error) {
                console.error(error);
                if (statOutOfStock) statOutOfStock.textContent = '0';
                if (statLowStock) statLowStock.textContent = '0';
                if (statExpiring) statExpiring.textContent = '0';

                container.className = 'w-full';
                container.innerHTML = `<div class="rounded-xl border border-dashed border-rose-300 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20 p-6 text-xs text-rose-700 dark:text-rose-300 text-center">Unable to load active stock alerts from API. Please refresh the page.</div>`;
                if (status) {
                    status.classList.add('hidden');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', loadAlertsFromApi);
    </script>
</x-app-layout>
