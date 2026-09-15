<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
                Stock Movement & Transfers
            </h2>
            <x-ui.button variant="secondary" :href="route('inventory.items')" icon="arrow-left">Back to Inventory</x-ui.button>
        </div>
    </x-slot>

    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
        <p class="text-sm text-[var(--muted)]">This module will handle stock entries, transfers, stock-outs, and warehouse movements.</p>
    </div>
</x-app-layout>
