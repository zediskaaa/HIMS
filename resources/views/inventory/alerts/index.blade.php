<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
                Low Stock & Alerts
            </h2>
            <x-ui.button variant="secondary" :href="route('inventory.items')" icon="arrow-left">Back to Inventory</x-ui.button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-[var(--text)]">Reorder Alerts & Inventory Notices</h3>
                        <p class="text-sm text-[var(--muted)]">Live warnings for low stock, out of stock, and near-expiry inventory items.</p>
                    </div>
                    <x-ui.loader id="alerts-api-status" size="sm" label="Loading alerts from API..." class="text-sm text-[var(--muted)]" />
                </div>
                <div id="alerts-list" class="mt-6 grid gap-4 lg:grid-cols-2"></div>
            </div>
        </div>
    </div>

    <script>
        async function loadAlertsFromApi() {
            const status = document.getElementById('alerts-api-status');
            const container = document.getElementById('alerts-list');

            try {
                await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                const response = await fetch('/api/v1/inventory-items?per_page=100', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const payload = await response.json();
                const items = payload.data || [];
                const lowStock = items.filter(item => ['low_stock', 'out_of_stock'].includes(item.status));
                const expiringSoon = items.filter(item => {
                    if (!item.expiry_date) return false;
                    const expiry = new Date(item.expiry_date);
                    const cutoff = new Date();
                    cutoff.setDate(cutoff.getDate() + 30);
                    return expiry <= cutoff;
                });

                if (lowStock.length === 0 && expiringSoon.length === 0) {
                    container.innerHTML = `<div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--background)] p-6 text-sm text-[var(--muted)]">No critical alerts found. Inventory levels are currently stable.</div>`;
                } else {
                    const cards = [];

                    if (lowStock.length > 0) {
                        cards.push(`
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                                <h4 class="text-base font-semibold text-rose-800">Low stock and out-of-stock items</h4>
                                <p class="mt-2 text-sm text-rose-700">${lowStock.length} item(s) need attention.</p>
                                <div class="mt-4 space-y-3">
                                    ${lowStock.slice(0, 5).map(item => `
                                        <div class="rounded-xl bg-white p-3 shadow-sm">
                                            <div class="flex items-center justify-between gap-2 text-sm">
                                                <span class="font-semibold text-[var(--text)]">${item.name}</span>
                                                <span class="text-rose-600 uppercase">${item.status.replace('_', ' ')}</span>
                                            </div>
                                            <p class="mt-1 text-[var(--muted)]">Qty: ${item.quantity_on_hand} • Reorder: ${item.reorder_level}</p>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `);
                    }

                    if (expiringSoon.length > 0) {
                        cards.push(`
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                                <h4 class="text-base font-semibold text-amber-900">Near-expiry inventory</h4>
                                <p class="mt-2 text-sm text-amber-800">${expiringSoon.length} item(s) expire within 30 days.</p>
                                <div class="mt-4 space-y-3">
                                    ${expiringSoon.slice(0, 5).map(item => `
                                        <div class="rounded-xl bg-white p-3 shadow-sm">
                                            <div class="flex items-center justify-between gap-2 text-sm">
                                                <span class="font-semibold text-[var(--text)]">${item.name}</span>
                                                <span class="text-amber-700">${item.expiry_date}</span>
                                            </div>
                                            <p class="mt-1 text-[var(--muted)]">Qty: ${item.quantity_on_hand} • Status: ${item.status || 'normal'}</p>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `);
                    }

                    container.innerHTML = cards.join('');
                }

                status.textContent = 'Alerts loaded from API.';
            } catch (error) {
                console.error(error);
                container.innerHTML = `<div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--background)] p-6 text-sm text-[var(--muted)]">Unable to load alerts from API.</div>`;
                status.textContent = 'Unable to load alerts from API. Check console for details.';
            }
        }

        document.addEventListener('DOMContentLoaded', loadAlertsFromApi);
    </script>
</x-app-layout>
