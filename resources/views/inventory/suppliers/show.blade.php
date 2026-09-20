<x-app-layout>
    @php
        $accreditation = $supplier->effectiveAccreditationStatus();
        $compliance = $supplier->complianceState();
        $activeProducts = $supplier->supplierProducts->where('is_active', true);

        // The Lifecycle card only earns its place when at least one decision
        // branch below actually applies; otherwise it would be an empty shell.
        $reviewableStates = [
            \App\Enums\SupplierAccreditationStatus::Draft,
            \App\Enums\SupplierAccreditationStatus::Rejected,
            \App\Enums\SupplierAccreditationStatus::Expired,
        ];
        $hasLifecycle = ($canReview && in_array($accreditation, $reviewableStates, true))
            || ($canApprove && $supplier->accreditation_status === \App\Enums\SupplierAccreditationStatus::PendingReview)
            || ($canApprove && $supplier->status === \App\Enums\SupplierStatus::Active)
            || ($canApprove && in_array($supplier->status, [\App\Enums\SupplierStatus::Suspended, \App\Enums\SupplierStatus::Inactive], true));

        // Sections render as tabs, so only the permitted ones become tabs at all.
        $profileSections = ['overview' => 'Overview'];
        if (auth()->user()?->can(\App\Enums\Permission::ViewSupplierSensitiveData->value)) {
            $profileSections += ['contacts' => 'Contacts', 'compliance' => 'Compliance', 'products' => 'Products & Pricing', 'contracts' => 'Contracts'];
        }
        $profileSections += ['performance' => 'Performance'];
        if (auth()->user()?->can(\App\Enums\Permission::ViewSupplierSensitiveData->value)) {
            $profileSections += ['history' => 'History'];
        }

        $sectionIcons = [
            'overview' => 'clipboard-document-list',
            'contacts' => 'users',
            'compliance' => 'shield-check',
            'products' => 'cube',
            'contracts' => 'document-text',
            'performance' => 'chart-bar',
            'history' => 'clipboard-document-check',
        ];
    @endphp

    <x-ui.page-header
        :title="$supplier->name"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Suppliers' => route('inventory.suppliers'),
            $supplier->name => null,
        ]">
        <x-slot:media>
            <x-ui.supplier-logo :supplier="$supplier" size="xl" />
        </x-slot:media>
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('inventory.suppliers')" icon="arrow-left">Back to Suppliers</x-ui.button>
            <x-ui.badge :status="$supplier->status->value" dot>{{ $supplier->status->label() }}</x-ui.badge>
            <x-ui.badge :status="$accreditation->value" dot>{{ $accreditation->label() }}</x-ui.badge>
            <x-ui.badge :status="$compliance">{{ str($compliance)->headline() }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Page-level, so it stays visible whichever tab is open. --}}
    @if (! $supplier->isProcurementEligible())
        <x-ui.alert variant="warning" title="Not eligible for new procurement" class="mt-5">
            This record is retained for history, but it will not appear in new requisition, quotation, or purchase-order supplier choices until operational status, accreditation, and blocking evidence are current.
        </x-ui.alert>
    @endif

    {{--
        The profile reads one section at a time. Deep links keep working: the
        directory links here with #overview and #performance, and the initial
        tab is taken from the hash. Switching tabs rewrites the hash with
        replaceState, which records no history entry and triggers no scroll.
    --}}
    <div
        class="mt-5 space-y-6"
        x-data="{
            tab: 'overview',
            tabs: {{ Js::from(array_keys($profileSections)) }},
            init() {
                const requested = window.location.hash.slice(1);
                if (this.tabs.includes(requested)) {
                    this.tab = requested;
                }
            },
            go(next) {
                this.tab = next;
                history.replaceState(null, '', '#' + next);
            },
        }">
        <div class="rounded-lg border border-neutral-200 bg-white px-3 py-2 shadow-sm">
            <nav class="flex flex-wrap items-center gap-1.5" aria-label="Supplier profile sections">
                @foreach ($profileSections as $anchor => $label)
                    <button
                        type="button"
                        x-on:click="go('{{ $anchor }}')"
                        :aria-current="tab === '{{ $anchor }}' ? 'true' : null"
                        :class="tab === '{{ $anchor }}'
                            ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold'
                            : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent'"
                        class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                        <x-ui.icon :name="$sectionIcons[$anchor] ?? 'document-text'" class="w-4 h-4" />
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </nav>
        </div>

        <section id="overview" x-show="tab === 'overview'" x-cloak class="scroll-mt-20 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <x-ui.card title="Supplier master" subtitle="Business identity and procurement-planning defaults.">
                @can(\App\Enums\Permission::ManageSuppliers->value)
                    <x-slot:actions>
                        <x-ui.button type="button" size="sm" variant="secondary" icon="pencil-square" x-data x-on:click="$dispatch('open-modal', 'edit-supplier')">Edit Supplier Information</x-ui.button>
                    </x-slot:actions>
                @endcan

                <div class="grid gap-4 md:grid-cols-2 text-sm">
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Legal / Registered Name</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->name }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Trade Name</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->trade_name ?: '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Business Structure</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->business_structure ? str($supplier->business_structure)->headline() : '—' }}</p></div>
                    @can(\App\Enums\Permission::ViewSupplierSensitiveData->value)
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Tax Identifier (TIN)</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->tax_number ?: '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">General Email</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->email ?: '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Phone</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->phone ?: '—' }}</p></div>
                    <div class="md:col-span-2"><span class="text-xs font-semibold uppercase text-neutral-500">Registered Address</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->address ?: '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Billing Address</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->billing_address ?: '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Delivery Address</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->delivery_address ?: '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Standard Lead Time</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->standard_lead_time_days !== null ? $supplier->standard_lead_time_days.' days' : '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Regulated Health Products</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->provides_regulated_health_products ? 'Yes (FDA Regulated)' : 'No' }}</p></div>
                    <div class="md:col-span-2"><span class="text-xs font-semibold uppercase text-neutral-500">Default Payment Terms</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->payment_terms ?: '—' }}</p></div>
                    <div class="md:col-span-2"><span class="text-xs font-semibold uppercase text-neutral-500">Internal Notes</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->notes ?: '—' }}</p></div>
                    @else
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Standard Lead Time</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->standard_lead_time_days !== null ? $supplier->standard_lead_time_days.' days' : '—' }}</p></div>
                    <div><span class="text-xs font-semibold uppercase text-neutral-500">Regulated Health Products</span><p class="mt-1 font-medium text-neutral-900">{{ $supplier->provides_regulated_health_products ? 'Yes (FDA Regulated)' : 'No' }}</p></div>
                    @endcan
                </div>
            </x-ui.card>

            <div class="space-y-6">
                {{-- Operational, accreditation, and compliance state already appear as
                     badges in the page header; only the two facts they do not carry are
                     repeated here. --}}
                <x-ui.card title="Procurement eligibility">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-neutral-500">Valid until</dt><dd>{{ $supplier->accreditation_expires_at?->format('M d, Y') ?? 'No fixed date recorded' }}</dd></div>
                        <div class="flex justify-between gap-3 font-medium"><dt>New procurement</dt><dd>{{ $supplier->isProcurementEligible() ? 'Allowed' : 'Blocked' }}</dd></div>
                    </dl>
                    @if ($supplier->suspension_reason)
                        @can(\App\Enums\Permission::ViewSupplierSensitiveData->value)
                        <p class="mt-4 rounded-md bg-danger-50 p-3 text-sm text-danger-700"><strong>Suspension reason:</strong> {{ $supplier->suspension_reason }}</p>
                        @endcan
                    @endif
                </x-ui.card>

                @if ($hasLifecycle)
                    <x-ui.card title="Lifecycle actions" subtitle="Decisions are server-authorized and audit logged.">
                        <x-ui.button type="button" variant="secondary" icon="arrow-path" class="w-full" x-data x-on:click="$dispatch('open-modal', 'manage-lifecycle')">Manage Lifecycle Actions</x-ui.button>
                    </x-ui.card>
                @endif
            </div>
        </section>

        @can(\App\Enums\Permission::ViewSupplierSensitiveData->value)
        <section id="contacts" x-show="tab === 'contacts'" x-cloak class="scroll-mt-20">
            <x-ui.card title="Contacts" subtitle="Only business contact details needed for procurement are stored." :padding="false">
                @can(\App\Enums\Permission::ManageSuppliers->value)
                    <x-slot:actions>
                        <x-ui.button type="button" size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-contact')">Add Contact</x-ui.button>
                    </x-slot:actions>
                @endcan
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head><x-ui.table.th>Name</x-ui.table.th><x-ui.table.th>Purpose</x-ui.table.th><x-ui.table.th>Email</x-ui.table.th><x-ui.table.th>Phone</x-ui.table.th></x-ui.table.head>
                    <tbody>
                    @forelse ($supplier->contacts as $contact)
                        <x-ui.table.row>
                            <x-ui.table.td><span class="font-medium">{{ $contact->name }}</span>@if($contact->position)<span class="block text-xs text-neutral-500">{{ $contact->position }}</span>@endif</x-ui.table.td>
                            <x-ui.table.td>{{ str($contact->contact_type)->headline() }} @if($contact->is_primary)<x-ui.badge variant="primary">Primary</x-ui.badge>@endif</x-ui.table.td>
                            <x-ui.table.td>{{ $contact->email ?? '—' }}</x-ui.table.td>
                            <x-ui.table.td>{{ $contact->mobile ?: ($contact->phone ?: '—') }}</x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty :colspan="4" icon="users" title="No supplier contacts" message="Add only contacts needed for procurement, delivery, or billing." />
                    @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </section>

        <section id="compliance" x-show="tab === 'compliance'" x-cloak class="scroll-mt-20 space-y-6">
            <x-ui.card title="Expiry alerts" subtitle="Generated daily from recorded accreditation, document, and active-contract dates.">
                @forelse ($supplier->complianceAlerts as $alert)
                    <div class="flex items-start justify-between gap-3 border-b border-neutral-100 py-2 last:border-0">
                        <div><p class="text-sm font-medium text-neutral-900">{{ $alert->message }}</p><p class="text-xs text-neutral-500">Due {{ $alert->due_date->format('M d, Y') }}</p></div>
                        <x-ui.badge :status="$alert->severity->value">{{ $alert->severity->label() }}</x-ui.badge>
                    </div>
                @empty
                    <p class="text-sm text-neutral-500">No open expiry alerts. The daily compliance check warns 30 days before recorded dates.</p>
                @endforelse
            </x-ui.card>

            <x-ui.card title="Compliance evidence" subtitle="Uploaded evidence remains unverified until a reviewer records a decision." :padding="false">
                @can(\App\Enums\Permission::ManageSuppliers->value)
                    <x-slot:actions>
                        <x-ui.button type="button" size="sm" icon="arrow-up-tray" x-data x-on:click="$dispatch('open-modal', 'upload-evidence')">Upload for Verification</x-ui.button>
                    </x-slot:actions>
                @endcan
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head><x-ui.table.th>Document</x-ui.table.th><x-ui.table.th>Validity</x-ui.table.th><x-ui.table.th>Verification</x-ui.table.th><x-ui.table.th>Control</x-ui.table.th><x-ui.table.th>Action</x-ui.table.th></x-ui.table.head>
                    <tbody>
                    @forelse ($supplier->documents as $document)
                        <x-ui.table.row>
                            <x-ui.table.td><a class="font-medium text-primary-700 hover:underline" href="{{ route('inventory.suppliers.documents.download', [$supplier, $document]) }}">{{ $document->document_type }}</a><span class="block text-xs text-neutral-500">{{ $document->document_number ?: $document->original_name }}</span></x-ui.table.td>
                            <x-ui.table.td><span class="{{ $document->isExpired() ? 'font-medium text-danger-700' : '' }}">{{ $document->expires_at?->format('M d, Y') ?? 'No expiry recorded' }}</span></x-ui.table.td>
                            <x-ui.table.td><x-ui.badge :status="$document->isExpired() ? 'expired' : $document->verification_status->value">{{ $document->isExpired() ? 'Expired' : $document->verification_status->label() }}</x-ui.badge>@if($document->verifier)<span class="mt-1 block text-xs text-neutral-500">by {{ $document->verifier->name }}</span>@endif</x-ui.table.td>
                            <x-ui.table.td><span class="text-xs">{{ $document->required_for_accreditation ? 'Required for this review' : 'Supporting' }}</span>@if($document->blocks_procurement_when_invalid)<span class="block text-xs font-medium text-danger-700">Blocks when invalid</span>@endif @if(!$document->is_current)<span class="block text-xs text-neutral-500">Historical version</span>@endif</x-ui.table.td>
                            <x-ui.table.td>
                                @if ($canReview && $document->is_current && $document->verification_status === \App\Enums\SupplierDocumentStatus::Pending && $document->uploaded_by !== auth()->id())
                                    <form method="POST" action="{{ route('inventory.suppliers.documents.verify', [$supplier, $document]) }}" class="space-y-2"
                                          data-confirm-title="Verify supplier document"
                                          data-confirm-message="Are you sure you want to record this document verification decision for {{ $document->document_type }}?"
                                          data-confirm-label="Record Decision">
                                        @csrf @method('PATCH')
                                        <select name="decision" class="rounded-md border-neutral-300 text-xs"><option value="verified">Verify</option><option value="rejected">Reject</option></select>
                                        <input name="review_notes" class="block w-44 rounded-md border-neutral-300 text-xs" placeholder="Reason if rejected">
                                        <x-ui.button type="submit" size="sm">Record</x-ui.button>
                                    </form>
                                @else
                                    <span class="text-xs text-neutral-500">Read only</span>
                                @endif
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty :colspan="5" icon="document-text" title="No compliance evidence" message="Add only documents applicable to this supplier, product, and organization." />
                    @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </section>

        <section id="products" x-show="tab === 'products'" x-cloak class="scroll-mt-20">
            <x-ui.card title="Products and price history" subtitle="Supplier data extends the inventory item master; it does not duplicate or overwrite it." :padding="false">
                @can(\App\Enums\Permission::ManageSuppliers->value)
                    @if ($items->isNotEmpty() || $activeProducts->isNotEmpty())
                        <x-slot:actions>
                            @if ($items->isNotEmpty())
                                <x-ui.button type="button" size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'link-product')">Link Product</x-ui.button>
                            @endif
                            @if ($activeProducts->isNotEmpty())
                                <x-ui.button type="button" size="sm" variant="secondary" icon="currency-dollar" x-data x-on:click="$dispatch('open-modal', 'record-price')">Record Price</x-ui.button>
                            @endif
                        </x-slot:actions>
                    @endif
                @endcan
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head><x-ui.table.th>Item</x-ui.table.th><x-ui.table.th>Supplier catalog</x-ui.table.th><x-ui.table.th>MOQ / lead time</x-ui.table.th><x-ui.table.th>Prices</x-ui.table.th><x-ui.table.th>Status</x-ui.table.th></x-ui.table.head>
                    <tbody>
                    @forelse ($supplier->supplierProducts as $product)
                        <x-ui.table.row>
                            <x-ui.table.td><span class="font-medium">{{ $product->item->name }}</span><span class="block text-xs text-neutral-500">{{ $product->item->sku }} · {{ $product->item->category?->fullPath() ?? 'Uncategorized' }}</span></x-ui.table.td>
                            <x-ui.table.td>{{ $product->supplier_sku ?: '—' }}<span class="block text-xs text-neutral-500">{{ collect([$product->brand, $product->manufacturer, $product->pack_size])->filter()->join(' · ') ?: 'No catalog details' }}</span></x-ui.table.td>
                            <x-ui.table.td>{{ $product->minimum_order_quantity ? number_format($product->minimum_order_quantity) : '—' }} / {{ $product->lead_time_days !== null ? $product->lead_time_days.' days' : 'not set' }}</x-ui.table.td>
                            <x-ui.table.td>
                                @forelse ($product->prices->sortByDesc('effective_from')->take(3) as $price)
                                    <span class="block text-xs {{ $price->isCurrent() ? 'font-medium text-success-700' : 'text-neutral-500' }}">{{ $price->currency }} {{ number_format((float)$price->unit_price, 2) }} from {{ $price->effective_from->format('M d, Y') }}{{ $price->effective_until ? ' to '.$price->effective_until->format('M d, Y') : '' }}</span>
                                @empty <span class="text-xs text-neutral-500">No price history</span> @endforelse
                            </x-ui.table.td>
                            <x-ui.table.td><x-ui.badge :status="$product->is_active ? 'active' : 'inactive'">{{ $product->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>@if($product->is_preferred)<x-ui.badge variant="primary">Preferred</x-ui.badge>@endif
                                @can(\App\Enums\Permission::ManageSuppliers->value)
                                @if($product->is_active)
                                    <form method="POST" action="{{ route('inventory.suppliers.products.deactivate', [$supplier, $product]) }}" class="mt-2"
                                          data-confirm-title="Deactivate supplier product"
                                          data-confirm-message="Are you sure you want to deactivate {{ $product->item?->name ?? 'this product' }} for this supplier?"
                                          data-confirm-label="Deactivate Product"
                                          data-confirm-variant="danger">@csrf @method('PATCH')<button class="text-xs text-danger-700 hover:underline">Deactivate</button></form>
                                @else
                                    <form method="POST" action="{{ route('inventory.suppliers.products.reactivate', [$supplier, $product]) }}" class="mt-2"
                                          data-confirm-title="Reactivate supplier product"
                                          data-confirm-message="Are you sure you want to reactivate {{ $product->item?->name ?? 'this product' }} for this supplier?"
                                          data-confirm-label="Reactivate Product">@csrf @method('PATCH')<button class="text-xs text-primary-700 hover:underline">Reactivate</button></form>
                                @endif
                                @endcan
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty :colspan="5" icon="cube" title="No products linked" message="Associate existing inventory items before recording supplier-specific prices." />
                    @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </section>

        <section id="contracts" x-show="tab === 'contracts'" x-cloak class="scroll-mt-20">
            <x-ui.card title="Contracts" subtitle="Reference records only; HIMS does not generate legal agreements." :padding="false">
                @can(\App\Enums\Permission::ManageSuppliers->value)
                    <x-slot:actions>
                        <x-ui.button type="button" size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-contract')">Record Contract</x-ui.button>
                    </x-slot:actions>
                @endcan
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head><x-ui.table.th>Contract</x-ui.table.th><x-ui.table.th>Period</x-ui.table.th><x-ui.table.th>Terms</x-ui.table.th><x-ui.table.th>Status</x-ui.table.th></x-ui.table.head>
                    <tbody>
                    @forelse($supplier->contracts as $contract)
                        <x-ui.table.row><x-ui.table.td><span class="font-medium">{{ $contract->contract_number }}</span><span class="block text-xs text-neutral-500">{{ $contract->contract_type ?: 'Type not specified' }}</span></x-ui.table.td><x-ui.table.td>{{ $contract->starts_at->format('M d, Y') }} — {{ $contract->ends_at?->format('M d, Y') ?? 'open-ended' }}</x-ui.table.td><x-ui.table.td><span class="block text-xs">Payment: {{ $contract->payment_terms ?: '—' }}</span><span class="block text-xs">Delivery: {{ $contract->delivery_terms ?: '—' }}</span></x-ui.table.td><x-ui.table.td><x-ui.badge :status="$contract->effectiveStatus()">{{ str($contract->effectiveStatus())->headline() }}</x-ui.badge>@can(\App\Enums\Permission::ManageSuppliers->value)<form method="POST" action="{{ route('inventory.suppliers.contracts.update', [$supplier, $contract]) }}" class="mt-2" data-confirm-title="Change contract status" data-confirm-message="Are you sure you want to change the status of Contract {{ $contract->contract_number }} to {{ $contract->status === 'active' ? 'inactive' : 'active' }}?" data-confirm-label="Update Status">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $contract->status === 'active' ? 'inactive' : 'active' }}"><button class="text-xs text-primary-700 hover:underline">Mark {{ $contract->status === 'active' ? 'inactive' : 'active' }}</button></form>@endcan</x-ui.table.td></x-ui.table.row>
                    @empty <x-ui.table.empty :colspan="4" icon="document-text" title="No contracts recorded" message="Add a reference when a supplier agreement actually exists." /> @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </section>
        @endcan

        <section id="performance" x-show="tab === 'performance'" x-cloak class="scroll-mt-20">
            <x-ui.card title="Supplier performance" subtitle="Only facts available from existing purchase and receiving records are shown.">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.stat label="Purchase orders" :value="$performance['purchase_orders']" icon="document-text" />
                    <x-ui.stat label="Received orders" :value="$performance['received_orders']" icon="check-circle" tone="success" />
                    <x-ui.stat label="Open / not received" :value="$performance['pending_orders']" icon="clock" tone="warning" />
                </div>
                <x-ui.alert variant="info" title="No defensible performance score yet" class="mt-5">Promised delivery dates, accepted/rejected quantities, and inspection outcomes are not recorded yet, so on-time rate, fill rate, rejection rate, and quality scoring remain unavailable.</x-ui.alert>
            </x-ui.card>
        </section>

        @can(\App\Enums\Permission::ViewSupplierSensitiveData->value)
        <section id="history" x-show="tab === 'history'" x-cloak class="scroll-mt-20 grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Accreditation history" subtitle="Every submitted review cycle remains intact.">
                <ol class="space-y-4">
                    @forelse($supplier->accreditations as $review)
                        <li class="border-l-2 border-neutral-200 pl-4 text-sm"><div class="flex items-center gap-2"><span class="font-medium">Cycle {{ $review->cycle_number }}</span><x-ui.badge :status="$review->status->value">{{ $review->status->label() }}</x-ui.badge></div><p class="mt-1 text-xs text-neutral-500">Submitted {{ $review->submitted_at->format('M d, Y g:i A') }} by {{ $review->submitter?->name ?? 'Former/system user' }}@if($review->decided_at) · Decided {{ $review->decided_at->format('M d, Y g:i A') }} by {{ $review->decisionMaker?->name ?? 'Former/system user' }}@endif</p>@if($review->decision_notes)<p class="mt-1 text-neutral-700">{{ $review->decision_notes }}</p>@endif</li>
                    @empty <li class="text-sm text-neutral-500">No accreditation cycle has been submitted.</li> @endforelse
                </ol>
            </x-ui.card>
            <x-ui.card title="Audit activity" subtitle="Restricted audit details are shown only to users with audit-trail permission.">
                @can(\App\Enums\Permission::ViewAuditTrail->value)
                    <ol class="space-y-3">
                        @forelse($recentAudit as $log)
                            <li class="text-sm"><p class="font-medium text-neutral-800">{{ $log->action->label() }}</p><p class="text-xs text-neutral-500">{{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y g:i A') }} · {{ $log->actor_name }}</p><p class="mt-1 text-neutral-600">{{ $log->description }}</p></li>
                        @empty <li class="text-sm text-neutral-500">No supplier audit events recorded.</li> @endforelse
                    </ol>
                @else
                    <p class="text-sm text-neutral-500">Detailed append-only audit records are available through the restricted Audit Trail. Accreditation decisions above remain visible for operational continuity.</p>
                @endcan
            </x-ui.card>
        </section>
        @endcan
    </div>

    {{--
        Data entry lives in dialogs so the profile reads as a profile: every tab
        shows its records, and the forms that create them are one click away.
        Each form carries a `_supplier_form` marker so a failed validation
        redirect can reopen the dialog it came from.
    --}}
    @can(\App\Enums\Permission::ManageSuppliers->value)
        <x-ui.modal name="edit-supplier" title="Edit supplier" maxWidth="2xl">
            @if ($errors->any() && old('_supplier_form') === 'edit')
                <x-ui.alert variant="danger" title="Supplier could not be updated" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
            @endif

            @if ($errors->any() && old('_supplier_form') === 'edit-logo')
                <x-ui.alert variant="danger" title="Logo could not be updated" class="mb-4">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->get('logo') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            {{-- Optional Display Picture / Logo --}}
            <div
                class="mb-4 flex items-center gap-4 rounded-lg border border-neutral-200 bg-neutral-50/70 p-3"
                x-data="{
                    previewUrl: null,
                    fileName: '',
                    fileSize: '',
                    fileError: '',
                    handleFileSelect(event) {
                        const files = event.dataTransfer ? event.dataTransfer.files : event.target.files;
                        const file = files ? files[0] : null;
                        if (!file) return;
                        this.fileError = '';

                        const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                        if (!validTypes.includes(file.type)) {
                            this.fileError = 'Please select a valid JPG, JPEG, or PNG image file.';
                            this.clearPreview();
                            return;
                        }
                        if (file.size > 3 * 1024 * 1024) {
                            this.fileError = 'The selected image exceeds the 3 MB maximum size limit.';
                            this.clearPreview();
                            return;
                        }

                        this.fileName = file.name;
                        this.fileSize = (file.size / 1024).toFixed(0) + ' KB';
                        this.previewUrl = URL.createObjectURL(file);
                    },
                    clearPreview() {
                        this.previewUrl = null;
                        this.fileName = '';
                        this.fileSize = '';
                        this.fileError = '';
                        if (this.$refs.logoInput) {
                            this.$refs.logoInput.value = '';
                        }
                    }
                }"
                @close-modal.window="if ($event.detail === 'edit-supplier') clearPreview()"
            >
                <div class="shrink-0">
                    <template x-if="previewUrl">
                        <img :src="previewUrl" alt="Display picture preview" class="h-14 w-14 rounded-lg border border-neutral-200 bg-white object-contain shadow-sm" />
                    </template>
                    <div x-show="!previewUrl">
                        <x-ui.supplier-logo :supplier="$supplier" size="xl" class="rounded-lg shadow-sm" />
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-neutral-800">Display picture / Logo</span>
                            <span class="rounded bg-neutral-200/70 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">Optional</span>
                        </div>
                        @if ($supplier->hasLogo())
                            <form
                                method="POST"
                                action="{{ route('inventory.suppliers.logo.destroy', $supplier) }}"
                                data-confirm-title="Remove supplier logo?"
                                data-confirm-message="Are you sure you want to remove this supplier's logo and show its initials instead?"
                                data-confirm-label="Remove Logo"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-danger-600 transition-colors hover:bg-danger-50 hover:text-danger-700">
                                    <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                                    Remove Logo
                                </button>
                            </form>
                        @endif
                    </div>
                    <p class="mt-0.5 text-xs text-neutral-500">Upload a company logo or avatar. JPG, JPEG, or PNG up to 3 MB.</p>
                    <form method="POST" action="{{ route('inventory.suppliers.logo.update', $supplier) }}" enctype="multipart/form-data" class="mt-2 min-w-0">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="edit-logo">
                        <input
                            x-ref="logoInput"
                            type="file"
                            name="logo"
                            accept="image/jpeg,image/png,image/jpg"
                            aria-label="Supplier display picture or logo"
                            class="sr-only"
                            @change="handleFileSelect($event)"
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button type="button" size="sm" variant="secondary" icon="arrow-up-tray" x-on:click="$refs.logoInput.click()">
                                <span x-text="previewUrl ? 'Change Image' : 'Choose Image'"></span>
                            </x-ui.button>
                            <x-ui.button type="submit" size="sm" icon="check" x-show="previewUrl" x-cloak data-loading-text="Saving logo...">Save Logo</x-ui.button>
                            <button
                                type="button"
                                x-show="previewUrl"
                                x-cloak
                                x-on:click="clearPreview()"
                                class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-danger-600 hover:bg-danger-50 hover:text-danger-700 transition-colors"
                            >
                                <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                                Remove
                            </button>
                            <span x-show="fileName" x-cloak class="min-w-0 truncate text-xs text-neutral-500">
                                <span x-text="fileName"></span>
                                <span class="font-mono text-neutral-400" x-text="'(' + fileSize + ')'"></span>
                            </span>
                        </div>
                    </form>
                    <div x-show="fileError" x-cloak class="mt-1.5 text-xs text-danger-600" x-text="fileError"></div>
                    @error('logo')
                        <p class="mt-1.5 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <form method="POST" action="{{ route('inventory.suppliers.update', $supplier) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_supplier_form" value="edit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="name" label="Legal / registered name" :value="$supplier->name" required />
                    <x-ui.field name="trade_name" label="Trade name" :value="$supplier->trade_name" />
                    <x-ui.field name="business_structure" label="Business structure" type="select" :value="$supplier->business_structure" :options="$businessStructures" placeholder="Select if known" />
                    <x-ui.field name="tax_number" label="Tax identifier" :value="$supplier->tax_number" />
                    <x-ui.field name="email" label="General business email" type="email" :value="$supplier->email" />
                    <x-ui.field name="phone" label="General business phone" :value="$supplier->phone" />
                    <div class="sm:col-span-2"><x-ui.field name="address" label="Registered / business address" type="textarea" rows="2" :value="$supplier->address" /></div>
                    <x-ui.field name="billing_address" label="Billing address" type="textarea" rows="2" :value="$supplier->billing_address" />
                    <x-ui.field name="delivery_address" label="Delivery / dispatch address" type="textarea" rows="2" :value="$supplier->delivery_address" />
                    <x-ui.field name="standard_lead_time_days" label="Standard lead time (days)" type="number" min="0" :value="$supplier->standard_lead_time_days" />
                    <div class="flex items-center">
                        <input type="hidden" name="provides_regulated_health_products" value="0">
                        <label class="flex items-start gap-2 text-sm text-neutral-700">
                            <input type="checkbox" name="provides_regulated_health_products" value="1" @checked(old('provides_regulated_health_products', $supplier->provides_regulated_health_products)) class="mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                            <span>Supplies FDA-regulated health products</span>
                        </label>
                    </div>
                    <x-ui.field name="payment_terms" label="Default payment terms" type="textarea" rows="2" :value="$supplier->payment_terms" />
                    <x-ui.field name="notes" label="Internal notes" type="textarea" rows="2" :value="$supplier->notes" />
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'edit-supplier')">Cancel</x-ui.button>
                    <x-ui.button type="submit" data-loading-text="Saving supplier...">Save Supplier Information</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any() && old('_supplier_form') === 'edit')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'edit-supplier'))"></div>
        @endif

        @if ($errors->any() && old('_supplier_form') === 'edit-logo')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'edit-supplier'))"></div>
        @endif

        <x-ui.modal name="add-contact" title="Add contact" maxWidth="lg">
            @if ($errors->any() && old('_supplier_form') === 'add-contact')
                <x-ui.alert variant="danger" title="Contact could not be added" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
            @endif
            <form method="POST" action="{{ route('inventory.suppliers.contacts.store', $supplier) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_supplier_form" value="add-contact">
                <x-ui.field name="name" label="Contact name" required />
                <x-ui.field name="contact_type" label="Purpose" type="select" :options="['primary'=>'Primary','procurement'=>'Procurement','sales'=>'Sales','finance'=>'Finance / billing','authorized_representative'=>'Authorized representative','other'=>'Other']" required />
                <x-ui.field name="position" label="Position / title" />
                <x-ui.field name="email" label="Email" type="email" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="phone" label="Phone" /><x-ui.field name="mobile" label="Mobile" /></div>
                <label class="flex gap-2 text-sm"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary')) class="rounded border-neutral-300 text-primary-600"> Primary contact</label>
                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'add-contact')">Cancel</x-ui.button>
                    <x-ui.button type="submit" data-loading-text="Adding contact...">Add Contact</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any() && old('_supplier_form') === 'add-contact')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'add-contact'))"></div>
        @endif

        <x-ui.modal name="upload-evidence" title="Upload evidence" maxWidth="xl">
            @if ($errors->any() && old('_supplier_form') === 'upload-evidence')
                <x-ui.alert variant="danger" title="Evidence could not be uploaded" class="mb-4">Review the highlighted fields and try again. A file must be re-selected after a failed upload.</x-ui.alert>
            @endif
            <p class="mb-3 text-xs text-neutral-500">Private PDF/JPG/PNG, up to 10 MB.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('inventory.suppliers.documents.store', $supplier) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_supplier_form" value="upload-evidence">
                @if ($supplier->provides_regulated_health_products)
                    <x-ui.alert variant="info" title="Regulated product scope">Check applicable establishment and product authorizations using the FDA Verification Portal. An LTO is not automatically sufficient for every product.</x-ui.alert>
                @endif
                <x-ui.field name="document_type" label="Document type" placeholder="e.g. FDA License to Operate" required />
                <x-ui.field name="document_number" label="Reference number" />
                <x-ui.field name="issuing_authority" label="Issuing authority" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="issued_at" label="Issue date" type="date" /><x-ui.field name="expires_at" label="Expiry date" type="date" /></div>
                <x-ui.field name="file" label="File" type="file" accept=".pdf,.jpg,.jpeg,.png" required />
                <x-ui.field name="replaces_document_id" label="Replaces / renews" type="select" :options="$supplier->documents->where('is_current', true)->mapWithKeys(fn($document) => [$document->id => $document->document_type.' — '.($document->document_number ?: $document->original_name)])->all()" placeholder="New evidence (not a replacement)" hint="Selecting a document preserves it as history and carries forward its required/blocking controls." />
                <label class="flex gap-2 text-sm"><input type="checkbox" name="required_for_accreditation" value="1" @checked(old('required_for_accreditation')) class="rounded border-neutral-300 text-primary-600"> Required for this accreditation</label>
                <label class="flex gap-2 text-sm"><input type="checkbox" name="blocks_procurement_when_invalid" value="1" @checked(old('blocks_procurement_when_invalid')) class="rounded border-neutral-300 text-primary-600"> Block new procurement when pending, rejected, or expired</label>
                <x-ui.field name="notes" label="Notes" type="textarea" rows="2" />
                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'upload-evidence')">Cancel</x-ui.button>
                    <x-ui.button type="submit" data-loading-text="Uploading securely...">Upload for Verification</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any() && old('_supplier_form') === 'upload-evidence')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'upload-evidence'))"></div>
        @endif

        @if ($items->isNotEmpty())
            <x-ui.modal name="link-product" title="Link product" maxWidth="lg">
                @if ($errors->any() && old('_supplier_form') === 'link-product')
                    <x-ui.alert variant="danger" title="Product could not be linked" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
                @endif
                <form method="POST" action="{{ route('inventory.suppliers.products.store', $supplier) }}"
                      data-confirm-title="Link catalog item"
                      data-confirm-message="Are you sure you want to link this catalog item to this supplier?"
                      data-confirm-label="Link item"
                      class="space-y-3">
                    @csrf
                    <input type="hidden" name="_supplier_form" value="link-product">
                    <x-ui.field name="item_id" label="Inventory item" type="select" :options="$items->mapWithKeys(fn($item) => [$item->id => $item->name.' ('.$item->sku.')'])->all()" placeholder="Select item" required />
                    <x-ui.field name="supplier_sku" label="Supplier SKU / catalog no." />
                    <x-ui.field name="supplier_product_name" label="Supplier product name" />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="brand" label="Brand" /><x-ui.field name="manufacturer" label="Manufacturer" /></div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="pack_size" label="Pack size" /><x-ui.field name="unit" label="Supplier unit" type="select" :options="\App\Enums\UnitOfMeasure::options()" placeholder="Select unit" /></div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="minimum_order_quantity" label="MOQ" type="number" min="1" /><x-ui.field name="lead_time_days" label="Lead days" type="number" min="0" /></div>
                    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_preferred" value="1" @checked(old('is_preferred')) class="rounded border-neutral-300 text-primary-600"> Preferred source for this item</label>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'link-product')">Cancel</x-ui.button>
                        <x-ui.button type="submit" data-loading-text="Linking product...">Link Product</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>

            @if ($errors->any() && old('_supplier_form') === 'link-product')
                <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'link-product'))"></div>
            @endif
        @endif

        @if ($activeProducts->isNotEmpty())
            <x-ui.modal name="record-price" title="Record price" maxWidth="lg">
                @if ($errors->any() && old('_supplier_form') === 'record-price')
                    <x-ui.alert variant="danger" title="Price could not be recorded" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
                @endif
                <form method="POST" action="{{ route('inventory.suppliers.prices.store', $supplier) }}"
                      data-confirm-title="Record product price"
                      data-confirm-message="Are you sure you want to commit this price schedule for this supplier product?"
                      data-confirm-label="Record price"
                      class="space-y-3">
                    @csrf
                    <input type="hidden" name="_supplier_form" value="record-price">
                    <x-ui.field name="supplier_product_id" label="Supplier product" type="select" :options="$activeProducts->mapWithKeys(fn($p) => [$p->id => $p->item->name])->all()" required />
                    <x-ui.field name="supplier_contract_id" label="Contract reference" type="select" :options="$supplier->contracts->filter(fn($c) => $c->effectiveStatus() === 'active')->mapWithKeys(fn($c) => [$c->id => $c->contract_number])->all()" placeholder="No contract" />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="currency" label="Currency" value="PHP" required /><x-ui.field name="unit_price" label="Unit price" type="number" step="0.01" min="0.01" required /></div>
                    <x-ui.field name="minimum_order_quantity" label="Minimum quantity for price" type="number" min="1" value="1" required />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="effective_from" label="Effective from" type="date" required /><x-ui.field name="effective_until" label="Effective until" type="date" /></div>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'record-price')">Cancel</x-ui.button>
                        <x-ui.button type="submit" data-loading-text="Recording price...">Record Price</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>

            @if ($errors->any() && old('_supplier_form') === 'record-price')
                <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'record-price'))"></div>
            @endif
        @endif

        <x-ui.modal name="add-contract" title="Add contract reference" maxWidth="lg">
            @if ($errors->any() && old('_supplier_form') === 'add-contract')
                <x-ui.alert variant="danger" title="Contract could not be recorded" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
            @endif
            <form method="POST" action="{{ route('inventory.suppliers.contracts.store', $supplier) }}"
                  data-confirm-title="Register contract"
                  data-confirm-message="Are you sure you want to register this procurement contract reference?"
                  data-confirm-label="Register contract"
                  class="space-y-3">
                @csrf
                <input type="hidden" name="_supplier_form" value="add-contract">
                <x-ui.field name="contract_number" label="Contract number" required />
                <x-ui.field name="contract_type" label="Contract type" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><x-ui.field name="starts_at" label="Start date" type="date" required /><x-ui.field name="ends_at" label="End date" type="date" /></div>
                <x-ui.field name="status" label="Record status" type="select" :options="['active'=>'Active','inactive'=>'Inactive']" required />
                <x-ui.field name="payment_terms" label="Payment terms" type="textarea" rows="2" />
                <x-ui.field name="delivery_terms" label="Delivery terms" type="textarea" rows="2" />
                <x-ui.field name="notes" label="Notes" type="textarea" rows="2" />
                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'add-contract')">Cancel</x-ui.button>
                    <x-ui.button type="submit" data-loading-text="Recording contract...">Record Contract</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any() && old('_supplier_form') === 'add-contract')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'add-contract'))"></div>
        @endif
    @endcan

    @if ($hasLifecycle)
        <x-ui.modal name="manage-lifecycle" title="Lifecycle actions" maxWidth="lg">
            @if ($errors->any() && old('_supplier_form') === 'lifecycle')
                <x-ui.alert variant="danger" title="Decision could not be recorded" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
            @endif
            <div class="space-y-4">
                @if ($canReview && $supplier->isReadyForAccreditationReview() && in_array($accreditation, $reviewableStates, true))
                    <form method="POST" action="{{ route('inventory.suppliers.submit', $supplier) }}" data-confirm-title="Submit accreditation" data-confirm-message="Submit this supplier and its current evidence for review?" data-confirm-label="Submit">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="lifecycle">
                        <x-ui.button type="submit" class="w-full" data-loading-text="Submitting...">Submit for Accreditation Review</x-ui.button>
                    </form>
                @elseif ($canReview && in_array($accreditation, $reviewableStates, true))
                    <x-ui.alert variant="info" title="Profile not ready for review">Complete {{ implode(', ', $supplier->accreditationReviewReadinessIssues()) }} before submitting.</x-ui.alert>
                @endif

                @if ($canDecide && $supplier->accreditation_status === \App\Enums\SupplierAccreditationStatus::PendingReview)
                    <form method="POST" action="{{ route('inventory.suppliers.approve', $supplier) }}" class="space-y-3"
                          data-confirm-title="Approve supplier accreditation"
                          data-confirm-message="Are you sure you want to approve accreditation for {{ $supplier->name }}? The supplier will become active for procurement."
                          data-confirm-label="Approve Supplier">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="lifecycle">
                        <x-ui.field name="expires_at" label="Accreditation valid until" type="date" hint="Leave blank only when the approving policy has no fixed renewal date." />
                        <x-ui.field name="decision_notes" label="Approval notes" type="textarea" rows="2" />
                        <label class="flex items-start gap-2 text-xs text-neutral-700">
                            <input type="checkbox" name="compliance_attested" value="1" required class="mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                            <span>I confirm that requirements applicable to this supplier, its legal form, products, and this procurement context were reviewed.</span>
                        </label>
                        <x-ui.button type="submit" class="w-full" data-loading-text="Approving...">Approve Accreditation</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('inventory.suppliers.reject', $supplier) }}" class="space-y-3 border-t border-neutral-200 pt-4"
                          data-confirm-title="Reject supplier accreditation"
                          data-confirm-message="Are you sure you want to reject accreditation for {{ $supplier->name }}? A rejection reason will be permanently recorded."
                          data-confirm-label="Reject Supplier"
                          data-confirm-variant="danger">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="lifecycle">
                        <x-ui.field name="decision_notes" label="Rejection reason" type="textarea" rows="2" required />
                        <x-ui.button type="submit" variant="danger" class="w-full" data-loading-text="Recording decision...">Reject Accreditation</x-ui.button>
                    </form>
                @elseif ($canApprove && $supplier->accreditation_status === \App\Enums\SupplierAccreditationStatus::PendingReview)
                    <x-ui.alert variant="info" title="Independent decision required">You participated in this supplier review. A different authorized approver must record the decision.</x-ui.alert>
                @endif

                @if ($canApprove && $supplier->status === \App\Enums\SupplierStatus::Active)
                    <form method="POST" action="{{ route('inventory.suppliers.suspend', $supplier) }}" class="space-y-3 border-t border-neutral-200 pt-4"
                          data-confirm-title="Suspend supplier"
                          data-confirm-message="Are you sure you want to suspend {{ $supplier->name }}? This supplier will be blocked from new purchase orders and deliveries."
                          data-confirm-label="Suspend Supplier"
                          data-confirm-variant="danger">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="lifecycle">
                        <x-ui.field name="suspension_reason" label="Suspension reason" type="textarea" rows="2" required />
                        <x-ui.button type="submit" variant="danger" class="w-full" data-loading-text="Suspending...">Suspend Procurement Use</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('inventory.suppliers.inactivate', $supplier) }}" class="space-y-3 border-t border-neutral-200 pt-4"
                          data-confirm-title="Inactivate supplier"
                          data-confirm-message="Are you sure you want to mark {{ $supplier->name }} as inactive?"
                          data-confirm-label="Inactivate Supplier"
                          data-confirm-variant="danger">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="lifecycle">
                        <x-ui.field name="inactivation_reason" label="Inactivation reason" type="textarea" rows="2" required />
                        <x-ui.button type="submit" variant="secondary" class="w-full" data-loading-text="Inactivating...">Mark Supplier Inactive</x-ui.button>
                    </form>
                @elseif ($canApprove && in_array($supplier->status, [\App\Enums\SupplierStatus::Suspended, \App\Enums\SupplierStatus::Inactive], true))
                    <form method="POST" action="{{ route('inventory.suppliers.reactivate', $supplier) }}" data-confirm-title="Reactivate supplier" data-confirm-message="Reactivate this operational record? Accreditation and document controls will still apply." data-confirm-label="Reactivate">
                        @csrf
                        <input type="hidden" name="_supplier_form" value="lifecycle">
                        <x-ui.button type="submit" class="w-full" data-loading-text="Reactivating...">Reactivate Supplier</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.modal>

        @if ($errors->any() && old('_supplier_form') === 'lifecycle')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'manage-lifecycle'))"></div>
        @endif
    @endif
</x-app-layout>
