<x-app-layout>
    @php
        $advancedFilterKeys = ['accreditation_status', 'eligibility', 'compliance', 'expiry', 'contract', 'performance', 'sort'];
        $advancedFiltersActive = collect($advancedFilterKeys)->contains(fn (string $key) => filled($filters[$key] ?? null) && ! ($key === 'sort' && ($filters[$key] ?? null) === 'name'));
        $activeFilterCount = collect($filters)->except(['direction'])->filter(fn ($value, $key) => filled($value) && ! ($key === 'sort' && $value === 'name'))->count();
    @endphp

    <x-ui.page-header
        title="Supplier Vendor Analytics"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Supplier Management' => null]"
    />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat compact label="Active suppliers" :value="$counts['active']" icon="users" tone="primary" :hint="$counts['new_this_month'].' added this month · '.$counts['total'].' total'" />
        <x-ui.stat compact label="Procurement eligible" :value="$counts['eligible']" icon="shield-check" tone="success" :hint="$counts['pending'].' awaiting accreditation review'" />
        <x-ui.stat compact label="Compliance attention" :value="$counts['attention']" icon="exclamation-triangle" :tone="$counts['critical_alerts'] > 0 ? 'danger' : 'warning'" :hint="$counts['critical_alerts'].' critical · '.$counts['active_alerts'].' active alerts'" />
        @if ($canViewProcurement)
            <x-ui.stat compact label="Open purchase orders" :value="$counts['open_purchase_orders']" icon="shopping-cart" tone="warning" :hint="$counts['purchase_orders'].' total purchase orders'" />
        @else
            <x-ui.stat compact label="Pending review" :value="$counts['pending']" icon="clipboard-document-list" tone="warning" hint="Awaiting an accreditation decision" />
        @endif
    </div>

    @if ($counts['critical_alerts'] > 0)
        <x-ui.alert variant="danger" class="mt-4 !px-3 !py-2.5" :title="$counts['critical_alerts'].' critical compliance '.str('alert')->plural($counts['critical_alerts'])">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>{{ $counts['attention'] }} {{ str('supplier')->plural($counts['attention']) }} currently require compliance attention.</span>
                <a href="{{ route('inventory.suppliers', ['compliance' => 'alerts']) }}" class="font-semibold underline underline-offset-2">Review suppliers</a>
            </div>
        </x-ui.alert>
    @endif

    {{-- The table needs roughly 700px to hold six columns. Splitting the layout
         any earlier squeezes it to ~604px at 1280, which is narrower than its
         own headers, so the summary aside only moves alongside once the row can
         afford it. --}}
    <div class="mt-5 grid min-w-0 gap-5 2xl:grid-cols-[minmax(0,1fr)_21rem]">
        <x-ui.card :padding="false">
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900">Supplier directory</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">{{ number_format($suppliers->total()) }} matching {{ str('supplier')->plural($suppliers->total()) }}</p>
                </div>
            </x-slot:header>
            <x-slot:actions>
                @can(\App\Enums\Permission::ManageSuppliers->value)
                    <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-supplier')">Add Supplier</x-ui.button>
                @endcan
            </x-slot:actions>

            <form method="GET" class="border-b border-neutral-200 p-3 sm:p-4" role="search">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(15rem,1fr)_minmax(10rem,0.55fr)_minmax(9rem,0.45fr)_auto]">
                    <div class="relative">
                        <label for="supplier-search" class="sr-only">Search suppliers</label>
                        <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-neutral-400" />
                        <input id="supplier-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search supplier{{ $canViewSensitiveData ? ', TIN, or email' : '' }}"
                               class="block min-h-10 w-full rounded-md border border-neutral-300 py-2 pl-10 pr-3 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                    </div>
                    <label class="sr-only" for="supplier-category">Product category</label>
                    <select id="supplier-category" name="product_category_id" class="block min-h-10 w-full rounded-md border border-neutral-300 px-3 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                        <option value="">All categories</option>
                        @foreach ($productCategories as $value => $label)
                            <option value="{{ $value }}" @selected((string) ($filters['product_category_id'] ?? '') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="supplier-status">Operational state</label>
                    <select id="supplier-status" name="status" class="block min-h-10 w-full rounded-md border border-neutral-300 px-3 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                        <option value="">All states</option>
                        @foreach ($operationalStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                        <x-ui.button type="submit" class="flex-1 lg:flex-none">Apply</x-ui.button>
                        @if ($activeFilterCount > 0)
                            <x-ui.button variant="ghost" :href="route('inventory.suppliers')" aria-label="Clear all supplier filters">Clear</x-ui.button>
                        @endif
                    </div>
                </div>

                <details class="group mt-2" @if($advancedFiltersActive) open @endif>
                    <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-2 rounded-md px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        <x-ui.icon name="adjustments-horizontal" class="h-4 w-4" />
                        More filters
                        @if ($activeFilterCount > 0)
                            <span class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[11px] text-primary-700">{{ $activeFilterCount }}</span>
                        @endif
                        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="mt-2 grid gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field name="accreditation_status" label="Accreditation" type="select" :value="$filters['accreditation_status'] ?? ''" :options="$accreditationStatuses" placeholder="All decisions" />
                        <x-ui.field name="eligibility" label="Procurement" type="select" :value="$filters['eligibility'] ?? ''" :options="['eligible' => 'Eligible', 'ineligible' => 'Not eligible']" placeholder="All suppliers" />
                        <x-ui.field name="compliance" label="Compliance" type="select" :value="$filters['compliance'] ?? ''" :options="['alerts' => 'Action required', 'clear' => 'No active alerts']" placeholder="All states" />
                        <x-ui.field name="expiry" label="Validity" type="select" :value="$filters['expiry'] ?? ''" :options="['within_30_days' => 'Expires within 30 days']" placeholder="Any validity" />
                        <x-ui.field name="contract" label="Current contract" type="select" :value="$filters['contract'] ?? ''" :options="['active' => 'Has active contract', 'none' => 'No active contract']" placeholder="Any contract" />
                        <x-ui.field name="performance" label="PO history" type="select" :value="$filters['performance'] ?? ''" :options="['available' => 'Available', 'none' => 'Not yet available']" placeholder="Any history" />
                        <x-ui.field name="sort" label="Sort by" type="select" :value="$filters['sort'] ?? 'name'" :options="['name' => 'Supplier name', 'created_at' => 'Date added', 'accreditation_expires_at' => 'Accreditation expiry']" />
                        <x-ui.field name="direction" label="Direction" type="select" :value="$filters['direction'] ?? 'asc'" :options="['asc' => 'Ascending', 'desc' => 'Descending']" />
                    </div>
                </details>
            </form>

            {{-- Fixed layout, so the six columns share whatever width the card
                 has instead of sizing to their content. Auto layout handed the
                 supplier column ~40% of the row because the truncating name
                 still reported its full width as a minimum, which pushed the
                 table past the card and drew a horizontal scrollbar. --}}
            <div class="hidden md:block [&_.hims-table-scroll]:overflow-x-hidden">
                <x-ui.table :sticky-header="false" class="w-full table-fixed supplier-directory-table">
                    <colgroup>
                        <col class="w-[33%]">
                        <col class="w-[18%]">
                        <col class="w-[11%]">
                        <col class="w-[15%]">
                        <col class="w-[12%]">
                        <col class="w-[11%]">
                    </colgroup>
                    <x-ui.table.head>
                        <x-ui.table.th>Supplier</x-ui.table.th>
                        <x-ui.table.th>Categories</x-ui.table.th>
                        <x-ui.table.th class="!px-2.5">Lead time</x-ui.table.th>
                        <x-ui.table.th>Score</x-ui.table.th>
                        <x-ui.table.th class="!pr-2">Status</x-ui.table.th>
                        <x-ui.table.th align="right" class="!pr-4 !pl-1">Action</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            @php
                                $categories = $supplier->supplierProducts->pluck('item.category.name')->filter()->unique()->values();
                                $scorecard = $supplier->latestApprovedScorecard;
                                $score = $scorecard ? max(0, min(100, (float) $scorecard->total_score)) : null;
                                $selectUrl = route('inventory.suppliers', array_merge(request()->query(), ['supplier' => $supplier->id])).'#supplier-summary';
                                $rowOnclick = ! $selectedSupplier?->is($supplier)
                                    ? "document.dispatchEvent(new CustomEvent('hims-loading-start', { detail: { message: 'Loading supplier...' } })); window.location.assign('{$selectUrl}');"
                                    : null;
                            @endphp
                            <x-ui.table.row
                                @class([
                                    'cursor-pointer transition-colors hover:bg-neutral-50/80',
                                    '!bg-primary-50/80 ring-1 ring-inset ring-primary-500/20' => $selectedSupplier?->is($supplier),
                                ])
                                :onclick="$rowOnclick"
                            >
                                <x-ui.table.td class="!pr-2">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-ui.supplier-logo :supplier="$supplier" size="md" />
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ $selectUrl }}" data-no-loading class="block font-semibold text-neutral-900 hover:text-primary-700 hover:underline leading-snug break-words" title="{{ $supplier->name }}" {{ $selectedSupplier?->is($supplier) ? 'aria-current="true" onclick="event.preventDefault();"' : '' }}>{{ $supplier->name }}</a>
                                            <span class="block truncate text-xs text-neutral-500 leading-tight">SUP-{{ str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT) }}{{ $supplier->trade_name ? ' · '.$supplier->trade_name : '' }}</span>
                                        </div>
                                    </div>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <span class="block truncate" title="{{ $categories->take(2)->join(', ') }}">{{ $categories->take(2)->join(', ') ?: 'No linked categories' }}</span>
                                    <span class="block truncate text-xs text-neutral-500">{{ $supplier->active_products_count }} active {{ str('item')->plural($supplier->active_products_count) }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td class="!px-2.5">{{ $supplier->standard_lead_time_days !== null ? $supplier->standard_lead_time_days.' days' : 'Not set' }}</x-ui.table.td>
                                <x-ui.table.td>
                                    @if ($score !== null)
                                        <div class="flex items-center gap-2">
                                            <span class="w-9 shrink-0 font-semibold tabular-nums">{{ number_format($score, 0) }}%</span>
                                            <span class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-neutral-200"><span class="block h-full rounded-full bg-primary-600" style="width: {{ $score }}%"></span></span>
                                        </div>
                                        <span class="block whitespace-nowrap text-xs text-neutral-500">Reviewed score</span>
                                    @else
                                        <span class="block whitespace-nowrap text-xs text-neutral-500">Not reviewed</span>
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td class="!pr-2">
                                    <x-ui.badge :status="$supplier->status->value" dot>{{ $supplier->status->label() }}</x-ui.badge>
                                    <span class="mt-1 block truncate text-xs text-neutral-500">{{ $supplier->effectiveAccreditationStatus()->label() }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td align="right" class="!pr-4 !pl-1" onclick="event.stopPropagation()">
                                    <x-ui.button size="sm" variant="secondary" :href="route('inventory.suppliers.show', $supplier)" icon="eye">View</x-ui.button>
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty :colspan="6" icon="truck" title="No matching suppliers" message="Adjust the filters or add a supplier record." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>

            <div class="divide-y divide-neutral-100 md:hidden">
                @forelse ($suppliers as $supplier)
                    @php
                        $categories = $supplier->supplierProducts->pluck('item.category.name')->filter()->unique();
                        $scorecard = $supplier->latestApprovedScorecard;
                        $selectUrl = route('inventory.suppliers', array_merge(request()->query(), ['supplier' => $supplier->id])).'#supplier-summary';
                    @endphp
                    <a href="{{ $selectUrl }}" @class(['block p-4 transition-colors hover:bg-neutral-50', 'bg-primary-50/70 ring-1 ring-inset ring-primary-500/20' => $selectedSupplier?->is($supplier)])>
                        <div class="flex items-start gap-3">
                            <x-ui.supplier-logo :supplier="$supplier" size="lg" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="truncate font-semibold text-neutral-900">{{ $supplier->name }}</p>
                                            @if ($selectedSupplier?->is($supplier))
                                                <span class="inline-flex items-center rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-800">Selected</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-neutral-500">SUP-{{ str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT) }}</p>
                                    </div>
                                    <x-ui.badge :status="$supplier->status->value" dot>{{ $supplier->status->label() }}</x-ui.badge>
                                </div>
                                <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <div><dt class="text-neutral-500">Category</dt><dd class="mt-0.5 truncate font-medium text-neutral-800">{{ $categories->first() ?: 'Not linked' }}</dd></div>
                                    <div><dt class="text-neutral-500">Lead time</dt><dd class="mt-0.5 font-medium text-neutral-800">{{ $supplier->standard_lead_time_days !== null ? $supplier->standard_lead_time_days.' days' : 'Not set' }}</dd></div>
                                    <div><dt class="text-neutral-500">Score</dt><dd class="mt-0.5 font-medium text-neutral-800">{{ $scorecard ? number_format((float) $scorecard->total_score, 0).'%' : 'Not reviewed' }}</dd></div>
                                </dl>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-8 text-center"><x-ui.icon name="truck" class="mx-auto h-8 w-8 text-neutral-300" /><p class="mt-2 text-sm font-semibold text-neutral-800">No matching suppliers</p><p class="mt-1 text-xs text-neutral-500">Adjust the filters or add a supplier record.</p></div>
                @endforelse
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-neutral-200 p-3 sm:p-4">{{ $suppliers->links() }}</div>
            @endif
        </x-ui.card>

        <aside id="supplier-summary" class="min-w-0 max-w-xl self-start 2xl:sticky 2xl:top-4 2xl:max-w-none">
            <x-ui.card :padding="false">
                <x-slot:header>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-primary-700">Selected supplier</p>
                        <h2 class="mt-0.5 text-sm font-semibold text-neutral-900">Decision summary</h2>
                    </div>
                </x-slot:header>

                @if ($suppliers->count() > 1)
                    <div
                        x-data="{ switching: false }"
                        class="border-b border-neutral-100 bg-neutral-50/70 px-4 py-2 sm:px-5"
                    >
                        <div class="flex items-center gap-2">
                            <label for="switch-supplier" class="shrink-0 text-xs font-medium text-neutral-500">
                                Switch:
                            </label>
                            <div class="relative min-w-0 flex-1">
                                <select
                                    id="switch-supplier"
                                    :disabled="switching"
                                    x-on:change="
                                        if ($event.target.value) {
                                            switching = true;
                                            document.dispatchEvent(new CustomEvent('hims-loading-start', { detail: { message: 'Loading supplier...' } }));
                                            window.location.assign($event.target.value);
                                        }
                                    "
                                    class="block w-full rounded-lg border border-neutral-200 bg-white py-1.5 pl-2.5 pr-8 text-xs font-medium text-neutral-800 shadow-2xs hover:border-neutral-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors disabled:opacity-60 disabled:cursor-wait"
                                    title="Switch selected supplier"
                                >
                                    @foreach ($suppliers as $s)
                                        <option value="{{ route('inventory.suppliers', array_merge(request()->query(), ['supplier' => $s->id])) }}#supplier-summary" @selected($selectedSupplier?->is($s))>
                                            {{ $s->name }} (SUP-{{ str_pad((string) $s->id, 4, '0', STR_PAD_LEFT) }})
                                        </option>
                                    @endforeach
                                </select>
                                <div x-show="switching" x-cloak class="pointer-events-none absolute inset-y-0 right-2 flex items-center bg-white pl-1">
                                    <span class="loader loader--sm text-primary-600" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($selectedSupplier)
                    @php
                        $selectedCategories = $selectedSupplier->supplierProducts->pluck('item.category.name')->filter()->unique();
                        $selectedScorecard = $selectedSupplier->latestApprovedScorecard;
                        $primaryContact = $canViewSensitiveData ? $selectedSupplier->contacts->firstWhere('is_primary', true) ?? $selectedSupplier->contacts->first() : null;
                        $activities = collect();
                        if ($canViewProcurement) {
                            foreach ($selectedSupplier->purchaseOrders as $purchaseOrder) {
                                $activities->push([
                                    'date' => $purchaseOrder->requested_at ?? $purchaseOrder->created_at,
                                    'tone' => in_array($purchaseOrder->status, ['received', 'fulfilled'], true) ? 'success' : 'primary',
                                    'title' => $purchaseOrder->po_number,
                                    'detail' => str($purchaseOrder->status)->headline().' purchase order',
                                ]);
                            }
                        }
                        if ($canViewSensitiveData) {
                            foreach ($selectedSupplier->complianceAlerts as $alert) {
                                $activities->push([
                                    'date' => $alert->first_detected_at ?? $alert->created_at,
                                    'tone' => $alert->severity->value === 'critical' ? 'danger' : 'warning',
                                    'title' => $alert->severity->label().' compliance alert',
                                    'detail' => $alert->message,
                                ]);
                            }
                        }
                        if ($selectedScorecard) {
                            $activities->push([
                                'date' => $selectedScorecard->processReview?->approved_at ?? $selectedScorecard->created_at,
                                'tone' => 'success',
                                'title' => 'Performance review approved',
                                'detail' => number_format((float) $selectedScorecard->total_score, 0).'% reviewed score',
                            ]);
                        }
                        $activities = $activities->sortByDesc('date')->take(3);
                    @endphp

                    <div class="p-4 sm:p-5">
                        <div class="flex items-start gap-3">
                            <x-ui.supplier-logo :supplier="$selectedSupplier" size="2xl" />
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-neutral-900">{{ $selectedSupplier->name }}</h3>
                                <p class="text-xs text-neutral-500">SUP-{{ str_pad((string) $selectedSupplier->id, 4, '0', STR_PAD_LEFT) }}{{ $selectedSupplier->trade_name ? ' · '.$selectedSupplier->trade_name : '' }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <x-ui.badge :status="$selectedSupplier->status->value" dot>{{ $selectedSupplier->status->label() }}</x-ui.badge>
                                    <x-ui.badge :status="$selectedSupplier->effectiveAccreditationStatus()->value">{{ $selectedSupplier->effectiveAccreditationStatus()->label() }}</x-ui.badge>
                                </div>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-neutral-600">{{ $selectedCategories->take(3)->join(', ') ?: 'No product categories linked yet.' }}</p>

                        @if ($canViewSensitiveData)
                            <div class="mt-4 space-y-2 border-t border-neutral-200 pt-4 text-xs text-neutral-600">
                                <p class="flex items-start gap-2">
                                    <x-ui.icon name="user-circle" class="h-4 w-4 shrink-0 text-neutral-400" />
                                    <span class="flex-1">
                                        @if ($primaryContact?->name || $selectedSupplier->contact_person)
                                            <span class="font-medium text-neutral-800">{{ $primaryContact?->name ?: $selectedSupplier->contact_person }}</span>
                                            @if ($primaryContact?->is_primary)
                                                <span class="ml-1 text-[10px] font-semibold text-primary-700">(Primary)</span>
                                            @endif
                                        @else
                                            <span class="text-neutral-400">No primary contact recorded</span>
                                            @can(\App\Enums\Permission::ManageSuppliers->value)
                                                <a href="{{ route('inventory.suppliers.show', $selectedSupplier) }}#contacts" class="ml-1.5 font-medium text-primary-600 hover:underline">Add contact</a>
                                            @endcan
                                        @endif
                                    </span>
                                </p>
                                <p class="flex items-start gap-2"><span class="w-4 shrink-0 text-center text-neutral-400">@</span><span class="break-all">{{ $primaryContact?->email ?: ($selectedSupplier->email ?: 'No email recorded') }}</span></p>
                                <p class="flex items-start gap-2"><x-ui.icon name="map-pin" class="h-4 w-4 shrink-0 text-neutral-400" /><span>{{ $selectedSupplier->address ?: 'No address recorded' }}</span></p>
                            </div>
                        @else
                            <p class="mt-4 rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-500">Contact details are restricted for your role.</p>
                        @endif
                    </div>

                    <div class="border-t border-neutral-200 p-4 sm:p-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Performance overview</h3>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div><dt class="text-xs text-neutral-500">Reviewed score</dt><dd class="mt-0.5 font-semibold text-neutral-900">{{ $selectedScorecard ? number_format((float) $selectedScorecard->total_score, 0).'%' : 'Not available' }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">Standard lead time</dt><dd class="mt-0.5 font-semibold text-neutral-900">{{ $selectedSupplier->standard_lead_time_days !== null ? $selectedSupplier->standard_lead_time_days.' days' : 'Not set' }}</dd></div>
                            @if ($canViewProcurement)
                                <div><dt class="text-xs text-neutral-500">Purchase orders</dt><dd class="mt-0.5 font-semibold text-neutral-900">{{ $selectedSupplier->purchase_orders_count }}</dd></div>
                                <div><dt class="text-xs text-neutral-500">Open orders</dt><dd class="mt-0.5 font-semibold text-neutral-900">{{ $selectedSupplier->open_purchase_orders_count }}</dd></div>
                            @endif
                        </dl>
                    </div>

                    <div class="border-t border-neutral-200 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Compliance</h3>
                            <x-ui.badge :status="$selectedSupplier->computed_compliance_state">{{ str($selectedSupplier->computed_compliance_state)->headline() }}</x-ui.badge>
                        </div>
                        <p class="mt-2 text-xs text-neutral-600">{{ $selectedSupplier->documents_count }} {{ str('document')->plural($selectedSupplier->documents_count) }} recorded · {{ $selectedSupplier->active_compliance_alerts_count }} active {{ str('alert')->plural($selectedSupplier->active_compliance_alerts_count) }}</p>
                    </div>

                    <div class="border-t border-neutral-200 p-4 sm:p-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Recent activity</h3>
                        @if ($activities->isNotEmpty())
                            <ol class="mt-3 space-y-3">
                                @foreach ($activities as $activity)
                                    <li class="flex gap-2.5 text-xs">
                                        <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-success-500' => $activity['tone'] === 'success', 'bg-primary-500' => $activity['tone'] === 'primary', 'bg-warning-500' => $activity['tone'] === 'warning', 'bg-danger-500' => $activity['tone'] === 'danger'])></span>
                                        <div class="min-w-0"><p class="font-medium text-neutral-800">{{ $activity['title'] }}</p><p class="mt-0.5 text-neutral-500">{{ $activity['detail'] }}{{ $activity['date'] ? ' · '.$activity['date']->format('M d, Y') : '' }}</p></div>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="mt-2 text-xs text-neutral-500">No authorized supplier activity is available yet.</p>
                        @endif
                    </div>

                    <div class="border-t border-neutral-200 bg-neutral-50 p-4">
                        <x-ui.button class="w-full" :href="route('inventory.suppliers.show', $selectedSupplier)" icon="eye">View Supplier</x-ui.button>
                    </div>
                @else
                    <div class="p-8 text-center"><x-ui.icon name="users" class="mx-auto h-8 w-8 text-neutral-300" /><p class="mt-2 text-sm font-semibold text-neutral-800">No supplier selected</p><p class="mt-1 text-xs text-neutral-500">Add a supplier or adjust the directory filters.</p></div>
                @endif
            </x-ui.card>
        </aside>
    </div>

    @can(\App\Enums\Permission::ManageSuppliers->value)
        <x-ui.modal name="create-supplier" title="Add supplier" maxWidth="2xl">
            @if ($errors->any() && old('_supplier_form') === 'create')
                <x-ui.alert variant="danger" title="Supplier could not be created" class="mb-4">Review the highlighted fields and try again.</x-ui.alert>
            @endif
            <form
                method="POST"
                action="{{ route('inventory.suppliers.store') }}"
                data-confirm-title="Create draft supplier"
                data-confirm-message="Are you sure you want to register this new supplier? A draft profile will be created for compliance review."
                data-confirm-label="Create draft supplier"
                enctype="multipart/form-data"
                class="space-y-4"
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
                @close-modal.window="if ($event.detail === 'create-supplier') clearPreview()"
            >
                @csrf
                <input type="hidden" name="_supplier_form" value="create">

                {{-- Optional Display Picture / Logo --}}
                <div class="flex items-center gap-4 rounded-lg border border-neutral-200 bg-neutral-50/70 p-3">
                    <div class="shrink-0">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Display picture preview" class="h-14 w-14 rounded-lg border border-neutral-200 bg-white object-contain shadow-sm" />
                        </template>
                        <div x-show="!previewUrl" class="flex h-14 w-14 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-400 shadow-sm">
                            <x-ui.icon name="building-office-2" class="h-6 w-6 text-neutral-300" />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-neutral-800">Display picture / Logo</span>
                            <span class="rounded bg-neutral-200/70 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600">Optional</span>
                        </div>
                        <p class="mt-0.5 text-xs text-neutral-500">Upload a company logo or avatar. JPG, JPEG, or PNG up to 3 MB.</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <input
                                x-ref="logoInput"
                                type="file"
                                name="logo"
                                accept="image/jpeg,image/png,image/jpg"
                                aria-label="Supplier display picture or logo"
                                class="sr-only"
                                @change="handleFileSelect($event)"
                            />
                            <x-ui.button type="button" size="sm" variant="secondary" icon="arrow-up-tray" x-on:click="$refs.logoInput.click()">
                                <span x-text="previewUrl ? 'Change Image' : 'Choose Image'"></span>
                            </x-ui.button>
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
                        <div x-show="fileError" x-cloak class="mt-1.5 text-xs text-danger-600" x-text="fileError"></div>
                        @error('logo')
                            <p class="mt-1.5 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="name" label="Legal / registered name" required />
                    <x-ui.field name="trade_name" label="Trade name" hint="Optional; keep the legal identity above." />
                    <x-ui.field name="business_structure" label="Business structure" type="select" :options="$businessStructures" placeholder="Select if known" />
                    <x-ui.field name="tax_number" label="Tax identifier" hint="Used for exact duplicate prevention." />
                    <x-ui.field name="contact_person" label="Primary contact name" />
                    <x-ui.field name="email" label="General business email" type="email" />
                    <x-ui.field name="phone" label="General business phone" />
                    <x-ui.field name="standard_lead_time_days" label="Standard lead time (days)" type="number" min="0" />
                    <div class="sm:col-span-2"><x-ui.field name="address" label="Registered / business address" type="textarea" rows="2" /></div>
                    <x-ui.field name="payment_terms" label="Default payment terms" type="textarea" rows="2" />
                    <x-ui.field name="notes" label="Internal notes" type="textarea" rows="2" />
                </div>
                <label class="flex items-start gap-2 text-sm text-neutral-700">
                    <input type="checkbox" name="provides_regulated_health_products" value="1" @checked(old('provides_regulated_health_products')) class="mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                    <span>Supplies FDA-regulated health products <span class="block text-xs text-neutral-500">This flags applicable review; it does not prove licensing.</span></span>
                </label>
                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'create-supplier')">Cancel</x-ui.button>
                    <x-ui.button type="submit" data-loading-text="Creating supplier...">Create Draft Supplier</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any() && old('_supplier_form') === 'create')
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'create-supplier'))"></div>
        @endif
    @endcan
</x-app-layout>
