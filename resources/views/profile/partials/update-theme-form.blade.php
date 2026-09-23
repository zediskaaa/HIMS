<x-ui.card
    title="Appearance & Theme"
    subtitle="Customize how HIMS displays on your screen. You can select Light, Dark, or sync with your device settings."
>
    <div x-data class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <button
            type="button"
            x-on:click="$store.theme.set('light')"
            :aria-pressed="$store.theme?.preference === 'light'"
            class="flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
            :class="$store.theme?.preference === 'light'
                ? 'border-primary-600 bg-primary-50 dark:border-primary-500 dark:bg-primary-950/30'
                : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800'"
        >
            <x-ui.icon name="sun" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-neutral-900 dark:text-neutral-100">Light</span>
                <span class="mt-0.5 block text-xs leading-normal text-neutral-600 dark:text-neutral-300">Bright, clinical surface for high-illumination environments.</span>
            </span>
            <template x-if="$store.theme?.preference === 'light'">
                <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0 text-primary-700 dark:text-primary-300" />
            </template>
        </button>

        <button
            type="button"
            x-on:click="$store.theme.set('dark')"
            :aria-pressed="$store.theme?.preference === 'dark'"
            class="flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
            :class="$store.theme?.preference === 'dark'
                ? 'border-primary-600 bg-primary-50 dark:border-primary-500 dark:bg-primary-950/30'
                : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800'"
        >
            <x-ui.icon name="moon" class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-neutral-900 dark:text-neutral-100">Dark</span>
                <span class="mt-0.5 block text-xs leading-normal text-neutral-600 dark:text-neutral-300">Low-glare dark surfaces reducing eye fatigue in dim lighting.</span>
            </span>
            <template x-if="$store.theme?.preference === 'dark'">
                <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0 text-primary-700 dark:text-primary-300" />
            </template>
        </button>

        <button
            type="button"
            x-on:click="$store.theme.set('system')"
            :aria-pressed="$store.theme?.preference === 'system'"
            class="flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
            :class="$store.theme?.preference === 'system'
                ? 'border-primary-600 bg-primary-50 dark:border-primary-500 dark:bg-primary-950/30'
                : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800'"
        >
            <x-ui.icon name="computer-desktop" class="mt-0.5 h-4 w-4 shrink-0 text-neutral-600 dark:text-neutral-300" />
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-neutral-900 dark:text-neutral-100">System</span>
                <span class="mt-0.5 block text-xs leading-normal text-neutral-600 dark:text-neutral-300">Automatically match your operating system's light or dark mode.</span>
            </span>
            <template x-if="$store.theme?.preference === 'system'">
                <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0 text-primary-700 dark:text-primary-300" />
            </template>
        </button>
    </div>
</x-ui.card>
