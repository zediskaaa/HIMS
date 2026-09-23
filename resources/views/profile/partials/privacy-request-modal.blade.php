<div
    x-data="{
        requestType: 'access',
        descriptions: {
            access: 'Request an export copy of your account profile, role assignments, and personal activity metadata held in HIMS under RA 10173 Sec. 16(c).',
            correction: 'Request rectification of inaccurate, outdated, or incomplete employee profile data under RA 10173 Sec. 16(d).',
            erasure: 'Request account deactivation and blocking of personal profile data. Note: Inventory ledgers, purchase records, and immutable audit logs are legally retained under National Archives of the Philippines (NAP) and COA statutory retention rules.',
            objection: 'Object to the processing of personal data for non-mandatory secondary purposes under RA 10173 Sec. 16(b).'
        }
    }"
    @if ($errors->has('request_type') || $errors->has('details'))
        x-init="$nextTick(() => { $dispatch('open-modal', 'submit-privacy-request') })"
    @endif
>
    <x-ui.modal name="submit-privacy-request" :title="__('Exercise Data Subject Rights (RA 10173)')" maxWidth="lg">
        @if ($errors->has('request_type') || $errors->has('details'))
            <x-ui.alert variant="danger" title="Submission Error" dismissible class="mb-4">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="space-y-4">
            <x-ui.alert variant="info" title="Republic Act No. 10173 — Data Privacy Act">
                As a hospital employee or authorized system user, you have statutory rights to access, rectify, or request restriction of your personal data processed within the DJNRMHS Hospital Inventory Management System.
            </x-ui.alert>

            <form method="POST" action="{{ route('privacy.requests.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="request_type" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        {{ __('Request Category') }} <span class="text-danger-600 dark:text-danger-400">*</span>
                    </label>
                    <select
                        id="request_type"
                        name="request_type"
                        x-model="requestType"
                        class="mt-1.5 block w-full rounded-lg border-neutral-300 bg-white pl-3 pr-10 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                        required
                    >
                        <option value="access">Right to Access &amp; Data Portability (Sec. 16c)</option>
                        <option value="correction">Right to Rectification / Correction (Sec. 16d)</option>
                        <option value="erasure">Right to Erasure / Account Deactivation (Sec. 16e)</option>
                        <option value="objection">Right to Object / Restrict Processing (Sec. 16b)</option>
                    </select>

                    <p class="mt-2 rounded border border-neutral-200 bg-neutral-50 p-2.5 text-xs leading-normal text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-300" x-text="descriptions[requestType]"></p>
                </div>

                <div>
                    <label for="details" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        {{ __('Specific Details or Request Ground') }} <span class="text-danger-600 dark:text-danger-400">*</span>
                    </label>
                    <textarea
                        id="details"
                        name="details"
                        rows="4"
                        class="mt-1.5 block w-full rounded-lg border-neutral-300 bg-white text-sm text-neutral-900 shadow-sm placeholder:text-neutral-500 focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-400"
                        placeholder="Please describe the specific data elements, corrections needed, or grounds for your request..."
                        required
                    >{{ old('details') }}</textarea>
                    <p class="mt-1 text-xs leading-normal text-neutral-600 dark:text-neutral-300">Minimum 10 characters. Your request will be securely logged and reviewed by the Data Protection Officer.</p>
                </div>

                <div class="flex flex-col gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-end">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        x-on:click="$dispatch('close-modal', 'submit-privacy-request')"
                        class="w-full sm:w-auto"
                    >
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        class="w-full sm:w-auto"
                        data-loading-text="Submitting request..."
                    >
                        {{ __('Submit Request to DPO') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
