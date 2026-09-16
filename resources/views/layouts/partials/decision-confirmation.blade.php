<dialog
    data-decision-confirmation
    class="m-auto w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-0 text-neutral-800 dark:text-neutral-200 shadow-xl backdrop:bg-neutral-900/50 dark:backdrop:bg-black/70"
    role="dialog"
    aria-modal="true"
    aria-labelledby="decision-confirmation-title"
    aria-describedby="decision-confirmation-message"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <span data-decision-icon-container class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-50 dark:bg-amber-950/60 text-warning-700 dark:text-amber-300" aria-hidden="true">
                <span data-decision-icon-warning>
                    <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                </span>
                <span data-decision-icon-danger class="hidden">
                    <x-ui.icon name="exclamation-circle" class="h-5 w-5" />
                </span>
                <span data-decision-icon-info class="hidden">
                    <x-ui.icon name="information-circle" class="h-5 w-5" />
                </span>
            </span>

            <div class="min-w-0 flex-1">
                <h2 id="decision-confirmation-title" data-decision-title class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Confirm action') }}
                </h2>
                <p id="decision-confirmation-message" data-decision-message class="mt-1 text-sm leading-6 text-neutral-600 dark:text-neutral-400"></p>
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button type="button" variant="secondary" data-decision-cancel>
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button type="button" data-decision-confirm>
                {{ __('Confirm') }}
            </x-ui.button>
        </div>
    </div>
</dialog>
