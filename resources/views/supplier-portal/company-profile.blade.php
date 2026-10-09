<x-layouts.supplier title="Company Profile">
    @php
        $status = $supplier->company_profile_status;
        $pendingDocuments = $supplier->documents->where('verification_status', \App\Enums\SupplierDocumentStatus::Pending);
        $billingSame = old('billing_same_as_registered', filled($profile['address'] ?? null) && ($profile['billing_address'] ?? null) === ($profile['address'] ?? null));
        $deliverySame = old('delivery_same_as_registered', filled($profile['address'] ?? null) && ($profile['delivery_address'] ?? null) === ($profile['address'] ?? null));
        $paymentTermOptions = [
            'Net 15' => 'Net 15',
            'Net 30' => 'Net 30',
            'Net 45' => 'Net 45',
            'Net 60' => 'Net 60',
            'Due on Receipt' => 'Due on Receipt',
            'COD' => 'Cash on Delivery (COD)',
            'custom' => 'Custom terms',
        ];
        $currentPaymentTerms = old('payment_terms', $profile['payment_terms'] ?? null);
        $paymentTermsChoice = old(
            'payment_terms_choice',
            array_key_exists((string) $currentPaymentTerms, $paymentTermOptions)
                ? $currentPaymentTerms
                : (filled($currentPaymentTerms) ? 'custom' : '')
        );
        $customPaymentTerms = old('payment_terms_custom', $paymentTermsChoice === 'custom' ? $currentPaymentTerms : '');
    @endphp

    <x-ui.page-header
        title="Complete Supplier Information"
        subtitle="Prepare your company record and supporting evidence for hospital verification."
        :breadcrumbs="['Dashboard' => route('supplier.dashboard'), 'Company Profile' => null]"
    >
        <x-slot:actions>
            <x-ui.badge :status="$status->value" dot>{{ $status->label() }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,2.25fr)_minmax(20rem,0.75fr)]">
        <form
            id="company-profile-form"
            method="POST"
            action="{{ route('supplier.company-profile.update') }}"
            class="min-w-0 space-y-5"
            novalidate
            x-data="{
                canSubmit: false,
                billingSame: @js((bool) $billingSame),
                deliverySame: @js((bool) $deliverySame),
                paymentTermsChoice: @js($paymentTermsChoice),
                refreshValidity() { this.$nextTick(() => this.canSubmit = this.$el.checkValidity()); }
            }"
            x-init="refreshValidity()"
            x-on:input="refreshValidity()"
            x-on:change="refreshValidity()"
        >
            @csrf
            @method('PATCH')

            <x-ui.card class="!rounded-2xl">
                <x-slot:header>
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-900">
                            <x-ui.icon name="building-office-2" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-neutral-950 dark:text-white">Company information</h2>
                            <p class="mt-0.5 text-xs leading-5 text-neutral-500 dark:text-neutral-400">Use the legal identity shown on your registration and tax records.</p>
                        </div>
                    </div>
                </x-slot:header>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="name" label="Registered company or business name" icon="building-office-2" :value="$profile['name'] ?? null" :disabled="!$editable" required />
                    <x-ui.field name="trade_name" label="Trade name" icon="tag" :value="$profile['trade_name'] ?? null" :disabled="!$editable" hint="Leave blank when it is the same as the registered name." />
                    <x-ui.field name="business_structure" label="Business structure" icon="squares-2x2" type="select" :value="$profile['business_structure'] ?? null" :options="$businessStructures" placeholder="Select business structure" :disabled="!$editable" required />
                    <x-ui.field
                        name="tax_number"
                        label="Tax Identification Number (TIN)"
                        icon="document-text"
                        :value="$profile['tax_number'] ?? null"
                        placeholder="000-000-000-000"
                        pattern="\d{3}-\d{3}-\d{3}-\d{3}"
                        inputmode="numeric"
                        maxlength="15"
                        x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 12).replace(/(\d{3})(?=\d)/g, '$1-')"
                        :disabled="!$editable"
                        hint="Enter 12 digits. Dashes are added automatically."
                        required
                    />
                    <div class="sm:col-span-2">
                        <input type="hidden" name="provides_regulated_health_products" value="0">
                        <label class="flex items-start gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-200">
                            <input type="checkbox" name="provides_regulated_health_products" value="1" @checked(old('provides_regulated_health_products', $profile['provides_regulated_health_products'] ?? false)) @disabled(!$editable) class="mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500 dark:border-neutral-600">
                            <span><strong class="block font-semibold text-neutral-900 dark:text-white">FDA-regulated health products</strong><span class="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">Select this when your company supplies regulated drugs, devices, or health products.</span></span>
                        </label>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="!rounded-2xl">
                <x-slot:header>
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-900">
                            <x-ui.icon name="map-pin" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-neutral-950 dark:text-white">Company addresses</h2>
                            <p class="mt-0.5 text-xs leading-5 text-neutral-500 dark:text-neutral-400">Keep each operational address accurate for billing and delivery coordination.</p>
                        </div>
                    </div>
                </x-slot:header>
                <div class="space-y-4">
                    <x-ui.field name="address" label="Registered business address" icon="map-pin" type="textarea" rows="3" :value="$profile['address'] ?? null" :disabled="!$editable" required />
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-3 rounded-xl border border-primary-100 bg-primary-50/35 p-3 dark:border-primary-900/70 dark:bg-primary-950/20">
                            <label class="flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                <input type="checkbox" name="billing_same_as_registered" value="1" x-model="billingSame" @disabled(!$editable) class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500 dark:border-neutral-600">
                                Use registered address for billing
                            </label>
                            <x-ui.field name="billing_address" label="Billing address" icon="building-office-2" type="textarea" rows="3" :value="$profile['billing_address'] ?? null" x-bind:disabled="billingSame || @js(!$editable)" :disabled="!$editable" required />
                        </div>
                        <div class="space-y-3 rounded-xl border border-primary-100 bg-primary-50/35 p-3 dark:border-primary-900/70 dark:bg-primary-950/20">
                            <label class="flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                <input type="checkbox" name="delivery_same_as_registered" value="1" x-model="deliverySame" @disabled(!$editable) class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500 dark:border-neutral-600">
                                Use registered address for delivery
                            </label>
                            <x-ui.field name="delivery_address" label="Delivery or dispatch address" icon="truck" type="textarea" rows="3" :value="$profile['delivery_address'] ?? null" x-bind:disabled="deliverySame || @js(!$editable)" :disabled="!$editable" required />
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="!rounded-2xl">
                <x-slot:header>
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-900">
                            <x-ui.icon name="user-circle" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-neutral-950 dark:text-white">Contact information</h2>
                            <p class="mt-0.5 text-xs leading-5 text-neutral-500 dark:text-neutral-400">Identify the representative the hospital may contact about this supplier profile.</p>
                        </div>
                    </div>
                </x-slot:header>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.field name="contact_first_name" label="First name" icon="user-circle" :value="$profile['contact_first_name'] ?? null" :disabled="!$editable" maxlength="80" required />
                    <x-ui.field name="contact_middle_name" label="Middle name" icon="user-circle" :value="$profile['contact_middle_name'] ?? null" :disabled="!$editable" maxlength="80" hint="Optional" />
                    <x-ui.field name="contact_surname" label="Surname" icon="user-circle" :value="$profile['contact_surname'] ?? null" :disabled="!$editable" maxlength="80" required />
                    <x-ui.field name="contact_position" label="Position or designation" icon="document-text" :value="$profile['contact_position'] ?? null" :disabled="!$editable" required />
                    <x-ui.field name="email" label="Business email address" icon="envelope" type="email" :value="$profile['email'] ?? null" :disabled="!$editable" required />
                    <x-ui.field
                        name="phone"
                        label="Contact number"
                        icon="phone"
                        :value="$profile['phone'] ?? null"
                        inputmode="numeric"
                        maxlength="11"
                        pattern="09[0-9]{9}"
                        placeholder="09XXXXXXXXX"
                        x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 11)"
                        :disabled="!$editable"
                        hint="Exactly 11 digits starting with 09."
                        required
                    />

                    <div class="min-w-0 space-y-1.5">
                        <label for="standard_lead_time_days" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Standard lead time (days)</label>
                        <div class="flex items-stretch gap-2">
                            <x-ui.button
                                type="button"
                                variant="secondary"
                                aria-label="Decrease standard lead time"
                                :disabled="!$editable"
                                x-on:click="$refs.leadTime.value = Math.max(0, Number($refs.leadTime.value || 0) - 1); $refs.leadTime.dispatchEvent(new Event('input', { bubbles: true }))"
                            >&minus;</x-ui.button>
                            <input
                                x-ref="leadTime"
                                id="standard_lead_time_days"
                                name="standard_lead_time_days"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="3650"
                                step="1"
                                value="{{ old('standard_lead_time_days', $profile['standard_lead_time_days'] ?? null) }}"
                                x-on:input="const digits = $event.target.value.replace(/\D/g, '').slice(0, 4); $event.target.value = digits === '' ? '' : Math.min(3650, Number(digits))"
                                @disabled(!$editable)
                                @class([
                                    'block min-h-10 min-w-0 flex-1 rounded-md border bg-white px-3 py-2 text-center text-sm text-neutral-900 shadow-sm focus:ring-2 focus:ring-primary-500/30 dark:bg-neutral-800 dark:text-neutral-100',
                                    'border-danger-500' => $errors->has('standard_lead_time_days'),
                                    'border-neutral-300 dark:border-neutral-700' => ! $errors->has('standard_lead_time_days'),
                                ])
                                aria-invalid="{{ $errors->has('standard_lead_time_days') ? 'true' : 'false' }}"
                            >
                            <x-ui.button
                                type="button"
                                variant="secondary"
                                aria-label="Increase standard lead time"
                                :disabled="!$editable"
                                x-on:click="$refs.leadTime.value = Math.min(3650, Number($refs.leadTime.value || 0) + 1); $refs.leadTime.dispatchEvent(new Event('input', { bubbles: true }))"
                            >&plus;</x-ui.button>
                        </div>
                        @error('standard_lead_time_days')<p class="text-xs font-medium text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Use the buttons or enter 0–3650 days.</p>
                    </div>

                    <div class="lg:col-span-2">
                        <x-ui.field name="payment_terms_choice" label="Default payment terms" icon="currency-dollar" type="select" :options="$paymentTermOptions" :value="$paymentTermsChoice" placeholder="No default terms" x-model="paymentTermsChoice" :disabled="!$editable" />
                        <div x-show="paymentTermsChoice === 'custom'" x-cloak class="mt-3">
                            <x-ui.field name="payment_terms_custom" label="Custom payment terms" :value="$customPaymentTerms" maxlength="200" placeholder="Enter the agreed payment terms" x-bind:required="paymentTermsChoice === 'custom'" :disabled="!$editable" />
                        </div>
                    </div>
                </div>
            </x-ui.card>

            @if ($editable)
                <div class="flex flex-col-reverse gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-end dark:border-neutral-800 dark:bg-neutral-900">
                    <x-ui.button type="submit" variant="secondary" data-loading-text="Saving draft...">Save Draft</x-ui.button>
                    <x-ui.button
                        type="submit"
                        formaction="{{ route('supplier.company-profile.submit') }}"
                        disabled
                        x-bind:disabled="!canSubmit || {{ $acceptableDocumentExists ? 'false' : 'true' }}"
                        data-confirm-title="Submit company profile for review?"
                        data-confirm-message="Your profile and supporting documents will be locked while the hospital reviews them. Confirm that the information is complete and accurate."
                        data-confirm-label="Submit for Review"
                        data-loading-text="Submitting for review..."
                    >Submit for Review</x-ui.button>
                </div>
            @endif
        </form>

        <aside class="min-w-0 space-y-5 xl:sticky xl:top-24">
            <x-ui.card class="!rounded-2xl">
                <x-slot:header>
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-900">
                            <x-ui.icon name="clock" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-neutral-950 dark:text-white">Onboarding progress</h2>
                            <p class="mt-0.5 text-xs leading-5 text-neutral-500 dark:text-neutral-400">Required company details and evidence.</p>
                        </div>
                    </div>
                </x-slot:header>

                <div class="flex flex-wrap items-center gap-6">
                    <div class="relative h-28 w-28 shrink-0" role="progressbar" aria-label="Company profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                            <circle cx="60" cy="60" r="48" pathLength="100" fill="none" stroke="currentColor" stroke-width="10" class="text-neutral-200 dark:text-neutral-800" />
                            <circle cx="60" cy="60" r="48" pathLength="100" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ $completion }} 100" class="text-success-500" />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-2xl font-bold tabular-nums text-neutral-950 dark:text-white">{{ $completion }}%</span>
                    </div>
                    <x-ui.badge :status="$status->value" dot>{{ $status->label() }}</x-ui.badge>
                </div>

                <div class="mt-4 flex items-start gap-3 rounded-xl bg-neutral-50 p-3 dark:bg-neutral-800/60">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-neutral-500 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:text-neutral-400 dark:ring-neutral-700">
                        <x-ui.icon name="lock-closed" class="h-4 w-4" />
                    </span>
                    <p class="pt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">
                    @if ($status === \App\Enums\SupplierCompanyProfileStatus::PendingReview)
                        Your submission is locked while the hospital reviews it.
                    @elseif ($status === \App\Enums\SupplierCompanyProfileStatus::Approved)
                        This is your approved company profile. Saving changes starts an amendment draft.
                    @else
                        Complete every required field and upload at least one supporting document.
                    @endif
                    </p>
                </div>
            </x-ui.card>

            @if ($supplier->company_profile_feedback)
                <x-ui.alert variant="{{ $status === \App\Enums\SupplierCompanyProfileStatus::Rejected ? 'danger' : 'warning' }}" title="Hospital feedback">
                    {{ $supplier->company_profile_feedback }}
                </x-ui.alert>
            @endif

            <x-ui.card :padding="false" class="!rounded-2xl">
                <x-slot:header>
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-900">
                            <x-ui.icon name="document-text" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-neutral-950 dark:text-white">Supporting documents</h2>
                            <p class="mt-0.5 text-xs leading-5 text-neutral-500 dark:text-neutral-400">Private evidence available only to your company and authorized hospital reviewers.</p>
                        </div>
                    </div>
                </x-slot:header>
                @error('documents')
                    <div class="border-b border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/40 dark:text-danger-300" role="alert">{{ $message }}</div>
                @enderror
                @if ($supplier->documents->isNotEmpty() && ! $acceptableDocumentExists)
                    <div class="border-b border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/40 dark:text-warning-300" role="status">
                        All current documents were rejected. Upload acceptable replacement evidence before submitting this profile.
                    </div>
                @endif
                <div class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @forelse ($supplier->documents as $document)
                        <div class="p-4">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger-50 text-danger-600 ring-1 ring-danger-100 dark:bg-danger-950/50 dark:text-danger-300 dark:ring-danger-900">
                                        <x-ui.icon name="document-text" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-neutral-900 dark:text-white" title="{{ $document->original_name }}">{{ $document->original_name }}</p>
                                        <p class="mt-1 text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ $document->document_type }}@if($document->document_number) &middot; {{ $document->document_number }}@endif</p>
                                    </div>
                                </div>
                                <x-ui.badge :status="$document->verification_status->value" dot>{{ $document->verification_status->label() }}</x-ui.badge>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-ui.button size="sm" variant="secondary" icon="arrow-down-tray" :href="route('supplier.compliance.download', $document)">Download</x-ui.button>
                                @if ($editable && $document->verification_status === \App\Enums\SupplierDocumentStatus::Pending)
                                    <form method="POST" action="{{ route('supplier.company-profile.documents.destroy', $document) }}" data-confirm-title="Remove pending document?" data-confirm-message="Remove {{ $document->original_name }} from this supplier submission?" data-confirm-label="Remove Document" data-confirm-variant="danger">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger-600 dark:text-danger-400">Remove</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-5 text-center">
                            <x-ui.icon name="document-plus" class="mx-auto h-8 w-8 text-neutral-400" />
                            <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-white">No supporting documents</p>
                            <p class="mt-1 text-xs leading-5 text-neutral-500 dark:text-neutral-400">Upload a business registration, permit, or supplier verification record.</p>
                        </div>
                    @endforelse
                </div>

                @if ($editable)
                    <div class="border-t border-neutral-200 p-4 dark:border-neutral-800">
                        <form
                            method="POST"
                            enctype="multipart/form-data"
                            action="{{ route('supplier.compliance.store') }}"
                            class="space-y-4"
                            novalidate
                            x-data="{
                                issuedAt: @js(old('issued_at', '')),
                                canUpload: false,
                                refreshValidity() { this.$nextTick(() => this.canUpload = this.$el.checkValidity()); }
                            }"
                            x-init="refreshValidity()"
                            x-on:input="refreshValidity()"
                            x-on:change="refreshValidity()"
                        >
                            @csrf
                            <x-ui.field name="document_type" label="Document type" placeholder="e.g. SEC registration" required />
                            <x-ui.field name="document_number" label="Reference number" />
                            @if ($pendingDocuments->isNotEmpty())
                                <x-ui.field name="replaces_document_id" label="Replace pending document" type="select" :options="$pendingDocuments->mapWithKeys(fn($document) => [$document->id => $document->original_name])->all()" placeholder="Upload as a new document" />
                            @endif
                            <x-ui.field name="issuing_authority" label="Issuing authority" />
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <x-ui.field name="issued_at" label="Issue date" type="date" x-model="issuedAt" :max="today()->toDateString()" />
                                <x-ui.field name="expires_at" label="Expiration date" type="date" x-bind:min="issuedAt || null" />
                            </div>
                            <x-ui.field name="file" label="Evidence file" type="file" accept=".pdf,.jpg,.jpeg,.png" hint="PDF, JPG, or PNG; maximum 10 MB." required />
                            <x-ui.button type="submit" class="w-full" disabled x-bind:disabled="!canUpload" data-loading-text="Uploading securely...">Upload Document</x-ui.button>
                        </form>
                    </div>
                @endif
            </x-ui.card>
        </aside>
    </div>
</x-layouts.supplier>
