<x-ui.card
    :title="__('Data Retention & Privacy Rights')"
    :subtitle="__('Employee user records and transactional ledgers are retained in accordance with Philippine statutory standards.')"
>
    <details class="rounded-lg border border-neutral-200 dark:border-neutral-700">
        <summary class="cursor-pointer px-3 py-2.5 text-xs leading-normal text-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-neutral-200">
            <span class="font-semibold">{{ __('Statutory Audit Retention') }}</span>
            <span class="ml-1 text-neutral-600 dark:text-neutral-300">{{ __('Clinical supply and inventory records are preserved; staff accounts are deactivated during offboarding.') }}</span>
        </summary>
        <p class="border-t border-neutral-200 px-3 py-2.5 text-xs leading-relaxed text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
            {{ __('Under National Archives of the Philippines (NAP) General Records Schedules and Commission on Audit (COA) rules, clinical supply and inventory transaction records are legally preserved. During staff offboarding, user accounts are deactivated rather than deleted.') }}
        </p>
    </details>

    <p class="mt-2 text-xs leading-normal text-neutral-600 dark:text-neutral-300">
        <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('Account Retention') }}:</span>
        {{ __('Your account cannot be permanently deleted.') }}
    </p>

    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="min-w-0 text-xs leading-normal text-neutral-600 dark:text-neutral-300">
            {{ __('Under Republic Act No. 10173, you may request an export copy of your data or petition for rectification/restriction.') }}
        </p>
        <x-ui.button
            variant="secondary"
            size="sm"
            icon="document-text"
            x-data
            x-on:click="$dispatch('open-modal', 'submit-privacy-request')"
            class="w-full shrink-0 sm:w-auto"
        >
            {{ __('Exercise Privacy Rights') }}
        </x-ui.button>
    </div>
</x-ui.card>
