<dialog
    data-decision-confirmation
    class="m-auto w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-lg border border-neutral-200 bg-white p-0 text-neutral-800 shadow-xl backdrop:bg-neutral-900/50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="decision-confirmation-title"
    aria-describedby="decision-confirmation-message"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-50 text-warning-700" aria-hidden="true">
                <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <h2 id="decision-confirmation-title" data-decision-title class="text-base font-semibold text-neutral-900">
                    {{ __('Confirm action') }}
                </h2>
                <p id="decision-confirmation-message" data-decision-message class="mt-1 text-sm leading-6 text-neutral-600"></p>
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
