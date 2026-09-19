<section class="space-y-4">
    <header>
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
            {{ __('Data Retention & Privacy Rights') }}
        </h2>

        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
            {{ __('Employee user records and transactional ledgers are retained in accordance with Philippine statutory standards.') }}
        </p>
    </header>

    <x-ui.alert variant="info" title="Statutory Audit Retention">
        {{ __('Under National Archives of the Philippines (NAP) General Records Schedules and Commission on Audit (COA) rules, clinical supply and inventory transaction records are legally preserved. During staff offboarding, user accounts are deactivated rather than deleted.') }}
    </x-ui.alert>

    <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
            {{ __('Under Republic Act No. 10173, you may request an export copy of your data or petition for rectification/restriction.') }}
        </p>
        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'submit-privacy-request')"
            class="inline-flex items-center justify-center px-3.5 py-2 text-xs font-semibold text-neutral-700 dark:text-neutral-200 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-300 dark:border-neutral-700 rounded-lg shadow-sm transition shrink-0"
        >
            <x-ui.icon name="document-text" class="w-4 h-4 mr-1.5 text-neutral-500" />
            {{ __('Exercise Privacy Rights') }}
        </button>
    </div>
</section>
