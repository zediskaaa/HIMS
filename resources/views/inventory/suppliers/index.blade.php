<x-app-layout>
    <x-ui.page-header
        title="Supplier Management"
        subtitle="Qualify suppliers, maintain commercial records, and control procurement eligibility."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Suppliers' => null]" />

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Supplier records" :value="$counts['total']" icon="truck" />
        <x-ui.stat label="Procurement eligible" :value="$counts['eligible']" icon="check-circle" tone="success" />
        <x-ui.stat label="Pending review" :value="$counts['pending']" icon="clipboard-document-list" tone="warning" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <x-ui.card title="Supplier directory" subtitle="Approval and compliance—not record existence—determine procurement eligibility." :padding="false">
            <form method="GET" class="grid gap-3 border-b border-neutral-200 p-4 md:grid-cols-3 xl:grid-cols-5" role="search">
                <x-ui.field name="search" label="Search" :value="$filters['search'] ?? ''" placeholder="Name, TIN, or email" />
                <x-ui.field name="status" label="Operational state" type="select" :value="$filters['status'] ?? ''" :options="$operationalStatuses" placeholder="All states" />
                <x-ui.field name="accreditation_status" label="Accreditation" type="select" :value="$filters['accreditation_status'] ?? ''" :options="$accreditationStatuses" placeholder="All decisions" />
                <x-ui.field name="eligibility" label="Procurement" type="select" :value="$filters['eligibility'] ?? ''" :options="['eligible' => 'Eligible', 'ineligible' => 'Not eligible']" placeholder="All suppliers" />
                <x-ui.field name="product_category_id" label="Product category" type="select" :value="$filters['product_category_id'] ?? ''" :options="$productCategories" placeholder="All categories" />
                <x-ui.field name="compliance" label="Compliance alerts" type="select" :value="$filters['compliance'] ?? ''" :options="['alerts' => 'Action required', 'clear' => 'No open alerts']" placeholder="All states" />
                <x-ui.field name="expiry" label="Expiry" type="select" :value="$filters['expiry'] ?? ''" :options="['within_30_days' => 'Within 30 days']" placeholder="Any validity" />
                <x-ui.field name="contract" label="Current contract" type="select" :value="$filters['contract'] ?? ''" :options="['active' => 'Has active contract', 'none' => 'No active contract']" placeholder="Any contract" />
                <x-ui.field name="performance" label="Performance data" type="select" :value="$filters['performance'] ?? ''" :options="['available' => 'Available', 'none' => 'Not yet available']" placeholder="Any history" />
                <x-ui.field name="sort" label="Sort by" type="select" :value="$filters['sort'] ?? 'name'" :options="['name' => 'Supplier name', 'created_at' => 'Date added', 'accreditation_expires_at' => 'Accreditation expiry']" />
                <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'asc' }}">
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit">Apply</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('inventory.suppliers')">Clear</x-ui.button>
                </div>
            </form>

            <x-ui.table :sticky-header="false">
                <x-ui.table.head>
                    <x-ui.table.th>Supplier</x-ui.table.th>
                    <x-ui.table.th>Accreditation</x-ui.table.th>
                    <x-ui.table.th>Compliance</x-ui.table.th>
                    <x-ui.table.th>Products</x-ui.table.th>
                    <x-ui.table.th>Lead time</x-ui.table.th>
                    <x-ui.table.th>Procurement</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <x-ui.table.row>
                            <x-ui.table.td>
                                <a href="{{ route('inventory.suppliers.show', $supplier) }}" class="font-medium text-primary-700 hover:underline">{{ $supplier->name }}</a>
                                <span class="block text-xs text-neutral-500">{{ $supplier->trade_name ?: ($supplier->business_structure ? str($supplier->business_structure)->headline() : 'Business type not classified') }}</span>
                            </x-ui.table.td>
                            <x-ui.table.td>
                                <x-ui.badge :status="$supplier->effectiveAccreditationStatus()->value" dot>{{ $supplier->effectiveAccreditationStatus()->label() }}</x-ui.badge>
                                @if ($supplier->accreditation_expires_at)
                                    <span class="mt-1 block text-xs text-neutral-500">to {{ $supplier->accreditation_expires_at->format('M d, Y') }}</span>
                                @endif
                            </x-ui.table.td>
                            <x-ui.table.td>
                                <x-ui.badge :status="$supplier->computed_compliance_state">{{ str($supplier->computed_compliance_state)->headline() }}</x-ui.badge>
                                <span class="mt-1 block text-xs text-neutral-500">{{ $supplier->documents_count }} document(s)</span>
                                @if ($supplier->active_compliance_alerts_count)
                                    <span class="block text-xs font-medium text-warning-700">{{ $supplier->active_compliance_alerts_count }} open expiry alert(s)</span>
                                @endif
                            </x-ui.table.td>
                            <x-ui.table.td>{{ $supplier->active_products_count ?: '—' }}<span class="block text-xs text-neutral-500">{{ $supplier->supplierProducts->pluck('item.category.name')->filter()->unique()->take(2)->join(', ') ?: 'No categories' }}</span></x-ui.table.td>
                            <x-ui.table.td>{{ $supplier->standard_lead_time_days !== null ? $supplier->standard_lead_time_days.' days' : 'Not set' }}</x-ui.table.td>
                            <x-ui.table.td>
                                @if ($supplier->isProcurementEligible())
                                    <x-ui.badge variant="success">Eligible</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">Not eligible</x-ui.badge>
                                @endif
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty :colspan="6" icon="truck" title="No matching suppliers" message="Create a supplier record or adjust the filters." />
                    @endforelse
                </tbody>
            </x-ui.table>

            @if ($suppliers->hasPages())
                <div class="border-t border-neutral-200 p-4">{{ $suppliers->links() }}</div>
            @endif
        </x-ui.card>

        <x-ui.card title="Register supplier" subtitle="This creates a draft record; it does not approve the supplier.">
            <form method="POST" action="{{ route('inventory.suppliers.store') }}" class="space-y-4">
                @csrf
                <x-ui.field name="name" label="Legal / registered name" required />
                <x-ui.field name="trade_name" label="Trade name" hint="Optional; do not use this as the legal identity." />
                <x-ui.field name="business_structure" label="Business structure" type="select" :options="$businessStructures" placeholder="Select if known" />
                <label class="flex items-start gap-2 text-sm text-neutral-700">
                    <input type="checkbox" name="provides_regulated_health_products" value="1" @checked(old('provides_regulated_health_products')) class="mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                    <span>Supplies FDA-regulated health products <span class="block text-xs text-neutral-500">Use this to surface applicable regulatory review; it does not by itself prove licensing.</span></span>
                </label>
                <x-ui.field name="tax_number" label="Tax identifier" hint="Used for exact duplicate prevention when supplied." />
                <x-ui.field name="email" label="General business email" type="email" />
                <x-ui.field name="phone" label="General business phone" />
                <x-ui.field name="address" label="Registered / business address" type="textarea" rows="2" />
                <x-ui.field name="standard_lead_time_days" label="Standard lead time (days)" type="number" min="0" />
                <x-ui.field name="payment_terms" label="Default payment terms" type="textarea" rows="2" />
                <x-ui.field name="notes" label="Internal notes" type="textarea" rows="2" />
                <x-ui.button type="submit" class="w-full" data-loading-text="Creating supplier...">Create Draft Supplier</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
