<x-ui.card
    title="Appearance & Theme"
    subtitle="Customize how HIMS displays on your screen. You can select Light, Dark, or sync with your device settings."
>
    <div x-data class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- 1. Light Mode Option --}}
            <button
                type="button"
                x-on:click="$store.theme.set('light')"
                class="relative flex flex-col items-start p-3.5 rounded-xl border-2 transition-all text-left group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :class="$store.theme?.preference === 'light'
                    ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-950/30 ring-1 ring-primary-600'
                    : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'"
            >
                <div class="w-full h-16 rounded-lg bg-neutral-100 border border-neutral-200 p-2 flex flex-col justify-between mb-3 shadow-2xs">
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 rounded-full bg-neutral-300"></div>
                        <div class="h-1.5 w-12 rounded bg-neutral-300"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="h-2 w-8 rounded bg-neutral-300"></div>
                        <div class="h-3 w-6 rounded bg-primary-600"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="sun" class="w-4 h-4 text-amber-500" />
                        <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100">Light</span>
                    </div>
                    <template x-if="$store.theme?.preference === 'light'">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </template>
                </div>
                <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-normal">
                    Bright, clinical surface for high-illumination environments.
                </p>
            </button>

            {{-- 2. Dark Mode Option --}}
            <button
                type="button"
                x-on:click="$store.theme.set('dark')"
                class="relative flex flex-col items-start p-3.5 rounded-xl border-2 transition-all text-left group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :class="$store.theme?.preference === 'dark'
                    ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-950/30 ring-1 ring-primary-600'
                    : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'"
            >
                <div class="w-full h-16 rounded-lg bg-neutral-950 border border-neutral-800 p-2 flex flex-col justify-between mb-3 shadow-2xs">
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 rounded-full bg-neutral-700"></div>
                        <div class="h-1.5 w-12 rounded bg-neutral-700"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="h-2 w-8 rounded bg-neutral-700"></div>
                        <div class="h-3 w-6 rounded bg-primary-500"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="moon" class="w-4 h-4 text-primary-400" />
                        <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100">Dark</span>
                    </div>
                    <template x-if="$store.theme?.preference === 'dark'">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </template>
                </div>
                <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-normal">
                    Low-glare dark surfaces reducing eye fatigue in dim lighting.
                </p>
            </button>

            {{-- 3. System Sync Option --}}
            <button
                type="button"
                x-on:click="$store.theme.set('system')"
                class="relative flex flex-col items-start p-3.5 rounded-xl border-2 transition-all text-left group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :class="$store.theme?.preference === 'system'
                    ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-950/30 ring-1 ring-primary-600'
                    : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-white dark:bg-neutral-900'"
            >
                <div class="w-full h-16 rounded-lg overflow-hidden border border-neutral-200 dark:border-neutral-800 flex mb-3 shadow-2xs">
                    <div class="w-1/2 h-full bg-neutral-100 p-2 flex flex-col justify-between">
                        <div class="h-1.5 w-6 rounded bg-neutral-300"></div>
                        <div class="h-2 w-5 rounded bg-primary-600"></div>
                    </div>
                    <div class="w-1/2 h-full bg-neutral-950 p-2 flex flex-col justify-between border-l border-neutral-700">
                        <div class="h-1.5 w-6 rounded bg-neutral-700"></div>
                        <div class="h-2 w-5 rounded bg-primary-500"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="computer-desktop" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
                        <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100">System</span>
                    </div>
                    <template x-if="$store.theme?.preference === 'system'">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </template>
                </div>
                <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-normal">
                    Automatically match your operating system's light or dark mode.
                </p>
            </button>
        </div>

        <div class="flex items-center justify-between pt-2 text-xs text-neutral-500 dark:text-neutral-400">
            <span class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full" :class="$store.theme?.isDark ? 'bg-indigo-400' : 'bg-amber-400'"></span>
                Active theme: <strong class="font-semibold text-neutral-800 dark:text-neutral-200" x-text="$store.theme?.isDark ? 'Dark Mode' : 'Light Mode'"></strong>
            </span>
            <span class="font-mono text-[11px]" x-text="'Mode: ' + $store.theme?.preference"></span>
        </div>
    </div>
</x-ui.card>
