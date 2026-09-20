<x-app-layout :full-width="true">
    <x-slot name="header">
        <div>
            <span class="rounded-md bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200">Operational workspace</span>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Procurement &amp; Purchase Orders</h2>
        </div>
    </x-slot>

    @php
        $defaultTab = 'orders_revisions';
        $canIssuePurchaseOrder = auth()->user()?->can(\App\Enums\Permission::IssuePurchaseOrder->value) ?? false;
        $procurementWorkspaceConfig = [
            'activeTab' => $defaultTab,
            'items' => $canIssuePurchaseOrder ? $items->map(function ($item) use ($itemProcurementContext) {
                $context = $itemProcurementContext->get((string) $item->id, []);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'unit' => $item->unit ?: 'unit',
                    'category' => $item->category?->name ?? 'Uncategorized',
                    'current_stock' => $context['current_stock'] ?? 0,
                    'reorder_point' => $context['reorder_point'] ?? (int) $item->reorder_level,
                    'recent_demand' => $context['recent_demand'] ?? 0,
                    'average_daily_usage' => $context['average_daily_usage'] ?? 0,
                    'suggested_order_quantity' => $context['suggested_order_quantity'] ?? 0,
                    'trend' => $context['trend'] ?? 'Not enough data',
                    'catalog_unit_cost' => (float) $item->unit_cost,
                    'lead_time_days' => (int) ($item->lead_time_days ?: 7),
                ];
            })->values() : [],
            'suppliers' => $canIssuePurchaseOrder ? $suppliers->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'status' => $supplier->status->value,
                'lead_time_days' => (int) ($supplier->standard_lead_time_days ?: 7),
                'score' => $supplier->latestApprovedScorecard?->total_score !== null
                    ? (float) $supplier->latestApprovedScorecard->total_score
                    : null,
            ])->values() : [],
            'supplierTerms' => $canIssuePurchaseOrder ? $supplierCatalogTerms : [],
            'initial' => [
                'itemId' => old('item_id'),
                'supplierId' => old('supplier_id'),
                'quantity' => old('quantity', 1),
                'deliveryDate' => old('delivery_date'),
            ],
        ];
    @endphp

    <div x-data="procurementWorkspace({{ Js::from($procurementWorkspaceConfig) }})" class="space-y-6">


            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Please correct the errors below:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Compact operational status strip --}}
            <div class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200 lg:grid-cols-4">
                <div class="bg-white p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Open purchase orders</p>
                        <span class="rounded-full bg-emerald-50 p-1.5 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <p class="mt-1 text-xl font-bold tabular-nums text-neutral-900">{{ $poMetrics['open'] }}</p>
                    <p class="text-xs text-neutral-500">Active commitments</p>
                </div>

                <div class="bg-white p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Pending approval</p>
                        <span class="rounded-full bg-blue-50 p-1.5 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        </span>
                    </div>
                    <p class="mt-1 text-xl font-bold tabular-nums text-neutral-900">{{ $poMetrics['pending_approval'] }}</p>
                    <p class="text-xs text-neutral-500">Awaiting authorization</p>
                </div>

                <div class="bg-white p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">In fulfillment</p>
                        <span class="rounded-full bg-amber-50 p-1.5 text-amber-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <p class="mt-1 text-xl font-bold tabular-nums text-neutral-900">{{ $poMetrics['in_transit'] }}</p>
                    <p class="text-xs text-neutral-500">Dispatched or partial</p>
                </div>

                <div class="bg-white p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Overdue delivery</p>
                        <span class="rounded-full bg-purple-50 p-1.5 text-purple-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </span>
                    </div>
                    <p class="mt-1 text-xl font-bold tabular-nums {{ $poMetrics['overdue'] > 0 ? 'text-danger-700' : 'text-neutral-900' }}">{{ $poMetrics['overdue'] }}</p>
                    <p class="text-xs text-neutral-500">Past required date</p>
                </div>
            </div>

            {{-- Navigation: Major Dropdown Tabs --}}
            <div
                class="rounded-xl border border-neutral-200 bg-white p-2.5 shadow-sm"
                x-data="{ openDropdown: null }"
                @keydown.escape.window="openDropdown = null"
            >
                {{-- Mobile / Small Screen Quick Selector (< sm) --}}
                <div class="sm:hidden">
                    <label for="procurement-mobile-tab-select" class="block text-[11px] font-semibold uppercase tracking-wider text-neutral-500 mb-1.5">
                        Select Procurement Area:
                    </label>
                    <select
                        id="procurement-mobile-tab-select"
                        x-on:change="if ($event.target.value.startsWith('http') || $event.target.value.startsWith('/')) { window.location.href = $event.target.value; } else { activeTab = $event.target.value; }"
                        class="block w-full rounded-lg border border-neutral-300 py-2.5 pl-3 pr-10 text-xs font-semibold text-neutral-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-2xs"
                    >
                        <optgroup label="Purchasing &amp; Orders">
                            @canany(['view_procurement_sensitive_data', 'create_requisition', 'manage_sourcing', 'issue_purchase_order', 'manage_procurement'])
                                <option value="enterprise_s2p" :selected="activeTab === 'enterprise_s2p'">Enterprise Source-to-Pay Workspace</option>
                            @endcanany
                            <option value="orders_revisions" :selected="activeTab === 'orders_revisions'">Purchase Orders &amp; Revisions</option>
                            @canany(['create_requisition', 'manage_sourcing', 'issue_purchase_order'])
                                <option value="legacy_canvass" :selected="activeTab === 'legacy_canvass'">Standard Canvassing</option>
                            @endcanany
                        </optgroup>

                        @canany([\App\Enums\Permission::GenerateForecasts->value, \App\Enums\Permission::ViewReports->value, 'view_procurement_sensitive_data', 'manage_sourcing', 'evaluate_bids', 'award_procurement'])
                            <optgroup label="Strategic Sourcing">
                                @canany(['view_procurement_sensitive_data', 'manage_sourcing', 'evaluate_bids'])
                                    <option value="sourcing_rfqs" :selected="activeTab === 'sourcing_rfqs'">Sourcing Events &amp; RFQs ({{ $rfqs->count() }})</option>
                                @endcanany
                                @canany(['view_procurement_sensitive_data', 'evaluate_bids', 'award_procurement'])
                                    <option value="evaluations" :selected="activeTab === 'evaluations'">Comparative Evaluation &amp; Landed Cost Matrix</option>
                                @endcanany
                                @canany([\App\Enums\Permission::GenerateForecasts->value, \App\Enums\Permission::ViewReports->value])
                                    <option value="{{ route('inventory.demand-forecast') }}">Demand Forecasts</option>
                                @endcanany
                            </optgroup>
                        @endcanany

                        @canany(['approve_purchase_order', 'view_audit_trail'])
                            <optgroup label="Governance &amp; Approvals">
                                @can('approve_purchase_order')
                                    <option value="doa_approvals" :selected="activeTab === 'doa_approvals'">Delegation of Authority (DOA) Hub</option>
                                @endcan
                                @can('view_audit_trail')
                                    <option value="audit_trail" :selected="activeTab === 'audit_trail'">Procurement Audit Trail</option>
                                @endcan
                            </optgroup>
                        @endcanany
                    </select>
                </div>

                {{-- Desktop & Tablet Major Dropdowns (>= sm) --}}
                <div class="hidden sm:flex sm:items-center sm:gap-2 flex-wrap text-xs">
                    {{-- Major Tab 1: Purchasing & Orders --}}
                    <div class="relative" @click.outside="if (openDropdown === 'purchasing') openDropdown = null">
                        <button
                            type="button"
                            @click="openDropdown = openDropdown === 'purchasing' ? null : 'purchasing'"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs"
                            :class="['enterprise_s2p', 'orders_revisions', 'legacy_canvass'].includes(activeTab)
                                ? 'bg-primary-50 text-primary-800 border-primary-200 ring-1 ring-primary-500/20'
                                : 'bg-white text-neutral-700 border-neutral-200 hover:bg-neutral-50 hover:text-neutral-900'"
                        >
                            <x-ui.icon name="shopping-bag" class="h-4 w-4 text-primary-600" />
                            <span>Purchasing &amp; Orders</span>
                            <span class="text-[10px] font-mono text-neutral-400 font-normal" x-text="activeTab === 'enterprise_s2p' ? '(S2P Workspace)' : (activeTab === 'orders_revisions' ? '(Purchase Orders)' : (activeTab === 'legacy_canvass' ? '(Canvassing)' : ''))"></span>
                            <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'purchasing' ? 'rotate-180' : ''" />
                        </button>

                        <div
                            x-show="openDropdown === 'purchasing'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                            class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                        >
                            @canany(['view_procurement_sensitive_data', 'create_requisition', 'manage_sourcing', 'issue_purchase_order', 'manage_procurement'])
                                <button
                                    type="button"
                                    @click="activeTab = 'enterprise_s2p'; openDropdown = null"
                                    class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                    :class="activeTab === 'enterprise_s2p' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="building-office-2" class="w-4 h-4 text-neutral-400" />
                                        <span>Enterprise S2P Workspace</span>
                                    </span>
                                    <span x-show="activeTab === 'enterprise_s2p'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                </button>
                            @endcanany

                            <button
                                type="button"
                                @click="activeTab = 'orders_revisions'; openDropdown = null"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                :class="activeTab === 'orders_revisions' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                            >
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="clipboard-document-list" class="w-4 h-4 text-neutral-400" />
                                    <span>Purchase Orders &amp; Revisions</span>
                                </span>
                                <span x-show="activeTab === 'orders_revisions'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                            </button>

                            @canany(['create_requisition', 'manage_sourcing', 'issue_purchase_order'])
                                <button
                                    type="button"
                                    @click="activeTab = 'legacy_canvass'; openDropdown = null"
                                    class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                    :class="activeTab === 'legacy_canvass' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-ui.icon name="magnifying-glass" class="w-4 h-4 text-neutral-400" />
                                        <span>Standard Canvassing</span>
                                    </span>
                                    <span x-show="activeTab === 'legacy_canvass'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                </button>
                            @endcanany
                        </div>
                    </div>

                    {{-- Major Tab 2: Strategic Sourcing --}}
                    @canany([\App\Enums\Permission::GenerateForecasts->value, \App\Enums\Permission::ViewReports->value, 'view_procurement_sensitive_data', 'manage_sourcing', 'evaluate_bids', 'award_procurement'])
                        <div class="relative" @click.outside="if (openDropdown === 'sourcing') openDropdown = null">
                            <button
                                type="button"
                                @click="openDropdown = openDropdown === 'sourcing' ? null : 'sourcing'"
                                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs"
                                :class="['sourcing_rfqs', 'evaluations'].includes(activeTab)
                                    ? 'bg-primary-50 text-primary-800 border-primary-200 ring-1 ring-primary-500/20'
                                    : 'bg-white text-neutral-700 border-neutral-200 hover:bg-neutral-50 hover:text-neutral-900'"
                            >
                                <x-ui.icon name="globe-alt" class="h-4 w-4 text-blue-600" />
                                <span>Strategic Sourcing</span>
                                @if($rfqs->count() > 0)
                                    <span class="rounded-full bg-blue-100 px-1.5 py-0.2 text-[10px] font-bold text-blue-800">{{ $rfqs->count() }}</span>
                                @endif
                                <span class="text-[10px] font-mono text-neutral-400 font-normal" x-text="activeTab === 'sourcing_rfqs' ? '(RFQs)' : (activeTab === 'evaluations' ? '(Evaluations)' : '')"></span>
                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'sourcing' ? 'rotate-180' : ''" />
                            </button>

                            <div
                                x-show="openDropdown === 'sourcing'"
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                class="absolute left-0 z-40 mt-1.5 w-72 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                            >
                                @canany(['view_procurement_sensitive_data', 'manage_sourcing', 'evaluate_bids'])
                                    <button
                                        type="button"
                                        @click="activeTab = 'sourcing_rfqs'; openDropdown = null"
                                        class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                        :class="activeTab === 'sourcing_rfqs' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                                    >
                                        <span class="flex items-center gap-2">
                                            <x-ui.icon name="document-duplicate" class="w-4 h-4 text-neutral-400" />
                                            <span>Sourcing Events &amp; RFQs ({{ $rfqs->count() }})</span>
                                        </span>
                                        <span x-show="activeTab === 'sourcing_rfqs'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    </button>
                                @endcanany

                                @canany(['view_procurement_sensitive_data', 'evaluate_bids', 'award_procurement'])
                                    <button
                                        type="button"
                                        @click="activeTab = 'evaluations'; openDropdown = null"
                                        class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                        :class="activeTab === 'evaluations' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                                    >
                                        <span class="flex items-center gap-2">
                                            <x-ui.icon name="scale" class="w-4 h-4 text-neutral-400" />
                                            <span>Comparative Landed Cost Matrix</span>
                                        </span>
                                        <span x-show="activeTab === 'evaluations'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    </button>
                                @endcanany

                                @canany([\App\Enums\Permission::GenerateForecasts->value, \App\Enums\Permission::ViewReports->value])
                                    <a
                                        href="{{ route('inventory.demand-forecast') }}"
                                        class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition text-neutral-700 hover:bg-neutral-50"
                                    >
                                        <span class="flex items-center gap-2">
                                            <x-ui.icon name="chart-bar" class="w-4 h-4 text-neutral-400" />
                                            <span>Demand Forecasts</span>
                                        </span>
                                        <x-ui.icon name="arrow-top-right-on-square" class="w-3.5 h-3.5 text-neutral-400" />
                                    </a>
                                @endcanany
                            </div>
                        </div>
                    @endcanany

                    {{-- Major Tab 4: Governance & Approvals --}}
                    @canany(['approve_purchase_order', 'view_audit_trail'])
                        <div class="relative" @click.outside="if (openDropdown === 'governance') openDropdown = null">
                            <button
                                type="button"
                                @click="openDropdown = openDropdown === 'governance' ? null : 'governance'"
                                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition border shadow-2xs"
                                :class="['doa_approvals', 'audit_trail'].includes(activeTab)
                                    ? 'bg-primary-50 text-primary-800 border-primary-200 ring-1 ring-primary-500/20'
                                    : 'bg-white text-neutral-700 border-neutral-200 hover:bg-neutral-50 hover:text-neutral-900'"
                            >
                                <x-ui.icon name="shield-check" class="h-4 w-4 text-purple-600" />
                                <span>Governance &amp; Approvals</span>
                                <span class="text-[10px] font-mono text-neutral-400 font-normal" x-text="activeTab === 'doa_approvals' ? '(DOA Hub)' : (activeTab === 'audit_trail' ? '(Audit Trail)' : '')"></span>
                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-neutral-400 transition-transform duration-200" ::class="openDropdown === 'governance' ? 'rotate-180' : ''" />
                            </button>

                            <div
                                x-show="openDropdown === 'governance'"
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                class="absolute left-0 z-40 mt-1.5 w-64 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl space-y-0.5"
                            >
                                @can('approve_purchase_order')
                                    <button
                                        type="button"
                                        @click="activeTab = 'doa_approvals'; openDropdown = null"
                                        class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                        :class="activeTab === 'doa_approvals' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                                    >
                                        <span class="flex items-center gap-2">
                                            <x-ui.icon name="check-badge" class="w-4 h-4 text-neutral-400" />
                                            <span>Delegation of Authority (DOA) Hub</span>
                                        </span>
                                        <span x-show="activeTab === 'doa_approvals'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    </button>
                                @endcan

                                @can('view_audit_trail')
                                    <button
                                        type="button"
                                        @click="activeTab = 'audit_trail'; openDropdown = null"
                                        class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition flex items-center justify-between"
                                        :class="activeTab === 'audit_trail' ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-neutral-700 hover:bg-neutral-50'"
                                    >
                                        <span class="flex items-center gap-2">
                                            <x-ui.icon name="clock" class="w-4 h-4 text-neutral-400" />
                                            <span>Procurement Audit Trail</span>
                                        </span>
                                        <span x-show="activeTab === 'audit_trail'" class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endcanany
                </div>
            </div>

            {{-- ======================================================== TAB 1: Enterprise S2P Workspace --}}
            @canany(['view_procurement_sensitive_data', 'create_requisition', 'manage_sourcing', 'issue_purchase_order', 'manage_procurement'])
            <div x-show="activeTab === 'enterprise_s2p'" class="space-y-6">
                @can('create_requisition')
                {{-- Department Requisition Intake with Synchronous Budget Soft Commitment --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
                     x-data="{
                         prQuantity: 100,
                         prUnitPrice: 45.00,
                         prSelectedCost: 0,
                         prSelectedUom: 'units',
                         prSelectedBudget: 0,
                         prItemChanged(event) {
                             const opt = event.target.options[event.target.selectedIndex];
                             if (opt && opt.dataset.cost) {
                                 this.prUnitPrice = parseFloat(opt.dataset.cost);
                                 this.prSelectedUom = opt.dataset.uom || 'units';
                             }
                         },
                         prCostCenterChanged(event) {
                             const opt = event.target.options[event.target.selectedIndex];
                             if (opt && opt.dataset.budget) {
                                 this.prSelectedBudget = parseFloat(opt.dataset.budget);
                             }
                         },
                         get prTotalEstimated() {
                             const q = parseFloat(this.prQuantity) || 0;
                             const p = parseFloat(this.prUnitPrice) || 0;
                             return (q * p).toFixed(2);
                         },
                         get isBudgetExceeded() {
                             return this.prSelectedBudget > 0 && parseFloat(this.prTotalEstimated) > this.prSelectedBudget;
                         }
                     }">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-neutral-900">Requisition Intake &amp; Budget Encumbrance Check</h3>
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Synchronous GL Check</span>
                            </div>
                            <p class="text-sm text-neutral-500">Multi-attribute intake with automated sequence assignment and real-time departmental budget reservation.</p>
                        </div>
                        <div class="flex items-center gap-2 self-start sm:self-auto">
                            <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Autogenerated Reference:</span>
                            <span class="rounded bg-neutral-100 border border-neutral-200 px-2.5 py-1 text-xs font-mono font-bold text-neutral-700 shadow-inner">PR-{{ date('Ymd') }}-AUTO</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('inventory.purchases.enterprise-requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-3"
                          data-confirm-title="Submit procurement request"
                          data-confirm-message="Are you sure you want to submit this procurement request for department review?"
                          data-confirm-label="Submit Request">
                        @csrf
                        {{-- Requisition Title --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Requisition Title</label>
                            <input type="text" name="title" placeholder="e.g. ICU PPE &amp; Ventilator Tubing Restock" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        {{-- Cost Center Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Cost Center (Department Budget)</label>
                            <select name="cost_center_id" @change="prCostCenterChanged($event)" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Cost Center</option>
                                @foreach($costCenters as $cc)
                                    @php $avail = $cc->currentBudget()?->availableBudget() ?? 1000000; @endphp
                                    <option value="{{ $cc->id }}" data-budget="{{ $avail }}">
                                        {{ $cc->name }} ({{ $cc->code }}) — Avail: ₱{{ number_format($avail, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Procurement Category Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Procurement Category</label>
                            <select name="procurement_category_id" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Procurement Method Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Procurement Method</label>
                            <select name="procurement_method" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="Request for Quotation" selected>Request for Quotation (Competitive Canvass)</option>
                                <option value="Direct Contracting">Direct Contracting (Single Source Authorized)</option>
                                <option value="Emergency Procurement">Emergency Procurement (Stat Patient Care)</option>
                                <option value="Competitive Bidding">Competitive Bidding (Public Tender)</option>
                                <option value="Repeat Order">Repeat Order (Contractual Catalog)</option>
                            </select>
                        </div>

                        {{-- Priority Level Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Priority Level</label>
                            <select name="priority" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="low">Low (Routine Stock Replenishment)</option>
                                <option value="medium" selected>Medium (Standard 14-Day Cycle)</option>
                                <option value="high">High (Department Critical)</option>
                                <option value="urgent">Urgent (Clinical Stat / Critical Shortage)</option>
                            </select>
                        </div>

                        {{-- Item Master Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Item Master Code</label>
                            <select name="item_id" @change="prItemChanged($event)" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Item from Catalog</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-cost="{{ $item->unit_cost }}" data-uom="{{ $item->unit }}" data-sku="{{ $item->sku }}">
                                        {{ $item->name }} ({{ $item->sku }}) — ₱{{ number_format($item->unit_cost, 2) }} / {{ $item->unit ?: 'unit' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Requested Quantity (Strict Numbers Only) --}}
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Requested Quantity</label>
                                <span class="text-xs text-neutral-500 font-medium" x-show="prSelectedUom">Unit: <strong class="text-neutral-800" x-text="prSelectedUom"></strong></span>
                            </div>
                            <input type="number" name="quantity" min="1" step="1" inputmode="numeric" x-model.number="prQuantity"
                                   onkeydown="return ['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key) || /^[0-9]$/.test(event.key)"
                                   class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        {{-- Estimated Unit Price (Strict Numbers Only) --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Estimated Unit Price (₱)</label>
                            <input type="number" step="0.01" min="0.01" name="estimated_unit_price" inputmode="decimal" x-model.number="prUnitPrice"
                                   class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        {{-- Target Need-By Date --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Target Need-By Date</label>
                            <input type="date" name="need_by_date" value="{{ now()->addDays(14)->toDateString() }}" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>

                        {{-- Clinical Justification --}}
                        <div class="md:col-span-3">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Clinical Justification &amp; Notes</label>
                            <input type="text" name="description" placeholder="Explain patient care rationale, minimum consumption threshold, or reorder trigger..." class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>

                        {{-- Real-Time Live Calculation & Budget Verification Panel --}}
                        <div class="md:col-span-3 rounded-lg border border-neutral-200 bg-neutral-50 p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-sm">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Live Calculated Soft Encumbrance:</span>
                                <div class="flex items-baseline gap-2 mt-0.5">
                                    <span class="text-2xl font-black text-primary-700">₱<span x-text="Number(prTotalEstimated).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></span>
                                    <span class="text-xs text-neutral-500 font-medium">(<span x-text="prQuantity"></span> <span x-text="prSelectedUom"></span> @ ₱<span x-text="parseFloat(prUnitPrice || 0).toFixed(2)"></span>)</span>
                                </div>
                            </div>
                            <div x-show="isBudgetExceeded" class="rounded-md bg-rose-50 border border-rose-200 px-3 py-1.5 text-xs text-rose-700 font-semibold flex items-center gap-1.5">
                                <svg class="h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Warning: Total exceeds selected Cost Center uncommitted budget!</span>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-primary-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Submit PR with Soft Encumbrance
                            </button>
                        </div>
                    </form>
                </div>
                @endcan

                {{-- Enterprise Requisitions Table --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-base font-bold text-neutral-900">Active Purchase Requests ({{ $enterpriseRequests->count() }})</h3>
                            <p class="text-xs text-neutral-500">Chronological ledger of departmental requisitions and soft encumbrances.</p>
                        </div>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <tr>
                                    <th class="px-3.5 py-3">PR Number</th>
                                    <th class="px-3.5 py-3">Category</th>
                                    <th class="px-3.5 py-3">Title &amp; Requester</th>
                                    <th class="px-3.5 py-3">Cost Center</th>
                                    <th class="px-3.5 py-3">Priority</th>
                                    <th class="px-3.5 py-3">Line Items</th>
                                    <th class="px-3.5 py-3">Total Est. (₱)</th>
                                    <th class="px-3.5 py-3">Status</th>
                                    <th class="px-3.5 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($enterpriseRequests as $pr)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-3.5 py-3 font-semibold text-neutral-900 font-mono text-xs">{{ $pr->pr_number }}</td>
                                        <td class="px-3.5 py-3">
                                            <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-neutral-700">
                                                {{ $pr->procurementCategory?->code ?? 'GEN' }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <p class="font-medium text-neutral-900">{{ $pr->title }}</p>
                                            <p class="text-xs text-neutral-500">{{ $pr->requester?->name }} • {{ $pr->submitted_at?->diffForHumans() }}</p>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <span class="font-medium text-neutral-800">{{ $pr->costCenter?->name }}</span>
                                            <span class="block text-[11px] text-neutral-400 font-mono">{{ $pr->costCenter?->code }}</span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @php
                                                $priorityClasses = match($pr->priority) {
                                                    'urgent' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                    'high' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    'medium' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    default => 'bg-neutral-50 text-neutral-600 border-neutral-200',
                                                };
                                            @endphp
                                            <span class="rounded border px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $priorityClasses }}">
                                                {{ $pr->priority }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($pr->lines->isNotEmpty())
                                                <p class="text-xs font-medium text-neutral-800">{{ $pr->lines->first()->item_description ?? $pr->lines->first()->item?->name }}</p>
                                                <p class="text-[11px] text-neutral-500">{{ $pr->lines->sum('quantity') }} total {{ $pr->lines->first()->uom ?: 'units' }} ({{ $pr->lines->count() }} line(s))</p>
                                            @else
                                                <span class="text-xs text-neutral-400">0 lines</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3 font-bold text-neutral-900">₱{{ number_format($pr->total_estimated_amount, 2) }}</td>
                                        <td class="px-3.5 py-3">
                                            @php
                                                $statusClasses = match($pr->status->value ?? $pr->status) {
                                                    'approved' => 'bg-emerald-50 text-emerald-700',
                                                    'pending_approval' => 'bg-amber-50 text-amber-700',
                                                    'rejected' => 'bg-rose-50 text-rose-700',
                                                    default => 'bg-neutral-100 text-neutral-700',
                                                };
                                            @endphp
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wider {{ $statusClasses }}">
                                                {{ str_replace('_', ' ', $pr->status->value ?? $pr->status) }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @can('manage_sourcing')
                                                @if(($pr->status->value ?? $pr->status) === 'approved' || ($pr->status->value ?? $pr->status) === 'pending_approval')
                                                    <button @click="activeTab = 'sourcing_rfqs'" class="text-xs font-semibold text-primary-600 hover:underline inline-flex items-center gap-1">
                                                        Package into RFQ &rarr;
                                                    </button>
                                                @else
                                                    <span class="text-xs text-neutral-400">Processed</span>
                                                @endif
                                            @else
                                                <span class="text-xs text-neutral-400">{{ ucfirst(str_replace('_', ' ', $pr->status->value ?? $pr->status)) }}</span>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3.5 py-6 text-center text-sm text-neutral-500">No enterprise purchase requests created yet. Submit one above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ======================================================== TAB 2: Sourcing Events & RFQs --}}
            @canany(['view_procurement_sensitive_data', 'manage_sourcing', 'evaluate_bids'])
            <div x-show="activeTab === 'sourcing_rfqs'" class="space-y-6">
                @can('manage_sourcing')
                {{-- Create RFQ Package Card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Publish Sourcing RFQ Package (Sealed Bidding)</h3>
                            <p class="text-sm text-neutral-500">Configure RFQ event parameters, invited accredited vendors, and strict sealed-bid deadlines.</p>
                        </div>
                        <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Sealed Bidding Protocol</span>
                    </div>

                    <form method="POST" action="{{ route('inventory.purchases.rfqs.store') }}" class="mt-4 grid gap-4 md:grid-cols-3"
                          data-confirm-title="Issue Request for Quotation (RFQ)"
                          data-confirm-message="Are you sure you want to publish this RFQ to invited suppliers?"
                          data-confirm-label="Issue RFQ">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">RFQ Event Title</label>
                            <input type="text" name="title" placeholder="e.g. Competitive Canvass: Syringes &amp; PPE" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Bidding Protocol</label>
                            <select name="bidding_type" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500">
                                <option value="sealed" selected>Sealed Bid (Commercial prices masked until close)</option>
                                <option value="open">Open Canvass / Quotation</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Submission Deadline</label>
                            <input type="datetime-local" name="submission_deadline" value="{{ now()->addDays(5)->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500" required />
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Item to Sourcing</label>
                            <select name="item_id" class="w-full rounded-lg border border-neutral-300 pl-3 pr-10 py-2 text-sm focus:border-primary-500" required>
                                <option value="">Select Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Target Quantity</label>
                            <input type="number" name="target_quantity" min="1" value="500" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Invited Accredited Suppliers</label>
                            <select name="supplier_ids[]" multiple class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 h-24" required>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" selected>{{ $s->name }} ({{ $s->effectiveAccreditationStatus()->value }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-neutral-400">Hold Ctrl/Cmd to select multiple accredited suppliers.</p>
                        </div>

                        <div class="md:col-span-3 flex justify-end gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-primary-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                Publish Sourcing RFQ &amp; Dispatch Invitations
                            </button>
                        </div>
                    </form>
                </div>
                @endcan

                {{-- Published RFQs Table --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-neutral-900">Sourcing Events ({{ $rfqs->count() }})</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <tr>
                                    <th class="px-3.5 py-3">RFQ Number</th>
                                    <th class="px-3.5 py-3">Title &amp; Protocol</th>
                                    <th class="px-3.5 py-3">Target Items</th>
                                    <th class="px-3.5 py-3">Deadline &amp; Countdown</th>
                                    <th class="px-3.5 py-3">Bids Received</th>
                                    <th class="px-3.5 py-3">Status</th>
                                    <th class="px-3.5 py-3">Evaluation Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($rfqs as $rfq)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-3.5 py-3 font-semibold text-neutral-900">{{ $rfq->rfq_number }}</td>
                                        <td class="px-3.5 py-3">
                                            <p class="font-medium text-neutral-900">{{ $rfq->title }}</p>
                                            <span class="inline-flex items-center gap-1 text-xs {{ $rfq->isSealed() ? 'text-amber-600 font-semibold' : 'text-neutral-500' }}">
                                                @if($rfq->isSealed())
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                    Sealed Bid (Locked)
                                                @else
                                                    <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
                                                    Unsealed / Open
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @foreach($rfq->lines as $line)
                                                <span>{{ $line->item?->name }} ({{ $line->target_quantity }} {{ $line->uom }})</span><br>
                                            @endforeach
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <p class="text-xs text-neutral-900 font-medium">{{ $rfq->submission_deadline->format('M d, Y h:i A') }}</p>
                                            <p class="text-xs {{ $rfq->isDeadlineElapsed() ? 'text-rose-600 font-semibold' : 'text-neutral-500' }}">
                                                {{ $rfq->isDeadlineElapsed() ? 'Bidding Window Elapsed' : 'Closes '.$rfq->submission_deadline->diffForHumans() }}
                                            </p>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <span class="font-bold text-neutral-900">{{ $rfq->quotes->count() }}</span> / {{ $rfq->invitations->count() }} invited
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @php
                                                $effectiveStatus = $rfq->effectiveStatus();
                                                $statusTone = match($effectiveStatus) {
                                                    \App\Enums\RfqStatus::Draft => 'bg-neutral-100 text-neutral-600',
                                                    \App\Enums\RfqStatus::Published => 'bg-blue-50 text-blue-700',
                                                    \App\Enums\RfqStatus::BiddingClosed => 'bg-amber-50 text-amber-700',
                                                    \App\Enums\RfqStatus::UnderEvaluation => 'bg-purple-50 text-purple-700',
                                                    \App\Enums\RfqStatus::Awarded => 'bg-emerald-50 text-emerald-700',
                                                    \App\Enums\RfqStatus::Cancelled => 'bg-rose-50 text-rose-700',
                                                };
                                            @endphp
                                            <span class="rounded-full {{ $statusTone }} px-2.5 py-1 text-xs font-semibold uppercase tracking-wider">
                                                {{ $effectiveStatus->value }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($rfq->status === \App\Enums\RfqStatus::Awarded)
                                                <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                    Awarded
                                                </span>
                                            @elseif($rfq->status === \App\Enums\RfqStatus::Cancelled)
                                                <span class="text-xs text-neutral-400">Cancelled</span>
                                            @elseif($rfq->quotes->isEmpty())
                                                <span class="text-xs text-neutral-400">Awaiting Quotes</span>
                                            @elseif($rfq->isSealed() && ! $rfq->isDeadlineElapsed())
                                                <div class="space-y-1">
                                                    <button type="button" disabled title="Sealed bid evaluation locked until submission deadline elapses ({{ $rfq->submission_deadline->format('M d, Y h:i A') }} PHT)" class="inline-flex items-center gap-1.5 rounded bg-neutral-100 px-3 py-1.5 text-xs font-semibold text-neutral-400 cursor-not-allowed border border-neutral-200">
                                                        <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                        </svg>
                                                        Sealed • Bidding Open
                                                    </button>
                                                    <p class="text-[11px] text-amber-700 font-medium">
                                                        Unlocks {{ $rfq->submission_deadline->format('M d, h:i A') }}
                                                    </p>
                                                </div>
                                            @elseif($rfq->evaluations->isNotEmpty())
                                                <div class="flex items-center gap-1.5">
                                                    <button @click="activeTab = 'evaluations'" type="button" class="inline-flex items-center gap-1 rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 shadow-sm">
                                                        View Matrix
                                                    </button>
                                                    @can('evaluate_bids')
                                                    <form method="POST" action="{{ route('inventory.purchases.rfqs.evaluate', $rfq) }}"
                                                          data-confirm-title="Run RFQ evaluation matrix"
                                                          data-confirm-message="Are you sure you want to run the comparative evaluation matrix on submitted bids?"
                                                          data-confirm-label="Run Matrix">
                                                        @csrf
                                                        <button type="submit" title="Re-run comparative evaluation matrix" class="rounded border border-neutral-200 p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 transition">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    @endcan
                                                </div>
                                            @else
                                                @can('evaluate_bids')
                                                <form method="POST" action="{{ route('inventory.purchases.rfqs.evaluate', $rfq) }}"
                                                      data-confirm-title="Run RFQ evaluation matrix"
                                                      data-confirm-message="Are you sure you want to run the comparative evaluation matrix on submitted bids?"
                                                      data-confirm-label="Run Matrix">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded bg-neutral-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800 shadow-sm transition">
                                                        <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                                        </svg>
                                                        Run Evaluation Matrix
                                                    </button>
                                                </form>
                                                @else
                                                <span class="text-xs text-neutral-400">Awaiting Evaluation</span>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3.5 py-6 text-center text-sm text-neutral-500">No sourcing events published. Create one above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ======================================================== TAB 3: Comparative Evaluation & Landed Cost Matrix --}}
            @canany(['view_procurement_sensitive_data', 'evaluate_bids', 'award_procurement'])
            <div x-show="activeTab === 'evaluations'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Multi-Attribute Comparative Evaluation Matrix</h3>
                            <p class="text-sm text-neutral-500">Transparent landed cost calculations (TCO), price normalization, technical quality scoring, and split-award optimization.</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">TCO Normalization Formula</span>
                    </div>

                    @php
                        $activeEvaluatedRfq = $rfqs->firstWhere(fn ($r) => $r->evaluations->isNotEmpty()) ?? $rfqs->first();
                    @endphp

                    @if($activeEvaluatedRfq && $activeEvaluatedRfq->evaluations->isNotEmpty())
                        <div class="mt-4">
                            <div class="flex items-center justify-between bg-neutral-50 p-3 rounded-lg border border-neutral-200">
                                <div>
                                    <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Active Event:</span>
                                    <span class="text-sm font-bold text-neutral-900 ml-1">{{ $activeEvaluatedRfq->rfq_number }} — {{ $activeEvaluatedRfq->title }}</span>
                                </div>
                                <div class="text-xs text-neutral-500">
                                    Weights: Price (40%), Technical (30%), Quality (15%), Lead Time (15%)
                                </div>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm text-neutral-700">
                                    <thead class="bg-neutral-100 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                                        <tr>
                                            <th class="px-3.5 py-3">Supplier Candidate</th>
                                            <th class="px-3.5 py-3">Base Quoted Price</th>
                                            <th class="px-3.5 py-3">Normalized Landed Cost (TCO)</th>
                                            <th class="px-3.5 py-3">Price Score (S_price)</th>
                                            <th class="px-3.5 py-3">Tech Score (S_tech)</th>
                                            <th class="px-3.5 py-3">Vendor Quality (S_qual)</th>
                                            <th class="px-3.5 py-3">Composite Score (S_vendor)</th>
                                            <th class="px-3.5 py-3">Recommendation &amp; Award</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-200">
                                        @foreach($activeEvaluatedRfq->evaluations->sortByDesc('composite_score') as $index => $eval)
                                            <tr class="{{ $index === 0 ? 'bg-emerald-50/50 font-medium' : 'hover:bg-neutral-50' }}">
                                                <td class="px-3.5 py-3">
                                                    <p class="font-bold text-neutral-900">{{ $eval->quote?->supplier?->name }}</p>
                                                    <p class="text-xs text-neutral-500">{{ $eval->quote?->quote_number }}</p>
                                                </td>
                                                <td class="px-3.5 py-3">₱{{ number_format($eval->quote?->quoted_price, 2) }}</td>
                                                <td class="px-3.5 py-3 font-semibold text-neutral-900">₱{{ number_format($eval->normalized_landed_cost, 2) }}</td>
                                                <td class="px-3.5 py-3 text-emerald-700 font-bold">{{ $eval->commercial_score }}%</td>
                                                <td class="px-3.5 py-3">{{ $eval->technical_score }}%</td>
                                                <td class="px-3.5 py-3">{{ $eval->quality_score }}%</td>
                                                <td class="px-3.5 py-3 text-base font-black text-primary-700">{{ $eval->composite_score }}%</td>
                                                <td class="px-3.5 py-3">
                                                    @if($index === 0)
                                                        <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                                                            #1 Ranked Winner
                                                        </span>
                                                        @if($activeEvaluatedRfq->status->value !== 'awarded')
                                                            @can('award_procurement')
                                                            <form method="POST" action="{{ route('inventory.purchases.rfqs.award', $activeEvaluatedRfq) }}" class="mt-1"
                                                                  data-confirm-title="Award procurement contract / RFQ"
                                                                  data-confirm-message="Are you sure you want to award this contract to the selected supplier and initiate Delegation of Authority (DOA)?"
                                                                  data-confirm-label="Award &amp; Initiate DOA">
                                                                @csrf
                                                                <input type="hidden" name="supplier_quote_id" value="{{ $eval->supplier_quote_id }}">
                                                                <button type="submit" class="rounded bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow hover:bg-emerald-700">
                                                                    Award &amp; Initiate DOA
                                                                </button>
                                                            </form>
                                                            @else
                                                            <span class="text-xs text-neutral-500 block mt-1">Ready for Award</span>
                                                            @endcan
                                                        @else
                                                            <span class="text-xs text-neutral-500 block mt-1">Awarded</span>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-neutral-400">Rank #{{ $index + 1 }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-lg border border-dashed border-neutral-300 p-8 text-center text-neutral-500">
                            <p class="text-base font-medium">No active sourcing evaluation results available yet.</p>
                            <p class="text-xs mt-1">Go to the 'Sourcing Events &amp; RFQs' tab and click 'Run Evaluation Matrix' on an RFQ with submitted vendor quotes.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endcanany

            {{-- ======================================================== TAB 4: Delegation of Authority (DOA) Hub --}}
            @can('approve_purchase_order')
            <div x-show="activeTab === 'doa_approvals'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Delegation of Authority (DOA) Approval Chains</h3>
                            <p class="text-sm text-neutral-500">Tiered financial governance thresholds with cryptographic audit signing tokens and self-approval restrictions.</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Segregation of Duties Enforced</span>
                    </div>

                    <div class="mt-4 space-y-4">
                        @forelse($approvalChains as $chain)
                            <div class="rounded-lg border border-neutral-200 p-4 hover:border-primary-200 transition">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wider text-primary-700">{{ $chain->chain_type->label() }}</span>
                                        <h4 class="text-base font-bold text-neutral-900">Chain #{{ $chain->id }} — Commitment: ₱{{ number_format($chain->total_commitment_amount, 2) }}</h4>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wider {{ $chain->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($chain->status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                        {{ $chain->status }}
                                    </span>
                                </div>

                                {{-- Steps Graph --}}
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                                    @foreach($chain->steps as $step)
                                        <div class="rounded-md border {{ $step->status->value === 'approved' ? 'border-emerald-200 bg-emerald-50/40' : ($step->status->value === 'rejected' ? 'border-rose-200 bg-rose-50/40' : 'border-neutral-200 bg-neutral-50') }} p-3">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-semibold text-neutral-500">Step {{ $step->step_number }}</span>
                                                <span class="font-bold {{ $step->status->value === 'approved' ? 'text-emerald-700' : ($step->status->value === 'rejected' ? 'text-rose-700' : 'text-amber-600') }}">
                                                    {{ $step->status->label() }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs font-medium text-neutral-900">Role: {{ ucwords(str_replace('_', ' ', $step->required_role)) }}</p>
                                            <p class="text-[11px] text-neutral-500">Threshold: ₱{{ number_format($step->threshold_min, 0) }} @if($step->threshold_max) - ₱{{ number_format($step->threshold_max, 0) }} @else + @endif</p>
                                            @if($step->digital_signature_token)
                                                <p class="mt-1 text-[10px] font-mono text-neutral-400 truncate" title="{{ $step->digital_signature_token }}">Sig: {{ substr($step->digital_signature_token, 0, 16) }}...</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Target Purchase Order Line Items Cost Breakdown --}}
                                @if($chain->chain_type === \App\Enums\ApprovalChainType::PurchaseOrder && $chain->purchaseOrder)
                                    @php
                                        $targetPo = $chain->purchaseOrder;
                                    @endphp
                                    <div class="mt-4 rounded-lg border border-neutral-200/80 bg-neutral-50/50 p-3">
                                        <div class="flex items-center justify-between pb-2 border-b border-neutral-200/60 text-xs">
                                            <span class="font-semibold text-neutral-800">Target Order: PO #{{ $targetPo->po_number }} · {{ $targetPo->supplier?->name ?? 'Supplier' }}</span>
                                            <span class="text-neutral-500 font-medium">Item Pricing &amp; Commitment Breakdown</span>
                                        </div>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="min-w-full text-left text-xs">
                                                <thead class="bg-neutral-100/80 text-[11px] font-semibold text-neutral-600">
                                                    <tr>
                                                        <th scope="col" class="px-2.5 py-1.5">Item Name</th>
                                                        <th scope="col" class="px-2.5 py-1.5 text-right">Quantity</th>
                                                        <th scope="col" class="px-2.5 py-1.5 text-center">Unit</th>
                                                        <th scope="col" class="px-2.5 py-1.5 text-right">Price per Unit</th>
                                                        <th scope="col" class="px-2.5 py-1.5 text-right">Total Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-neutral-200/50 bg-white">
                                                    @forelse($targetPo->lines as $line)
                                                        @php
                                                            $lineUnit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
                                                        @endphp
                                                        <tr>
                                                            <td class="px-2.5 py-2">
                                                                <span class="font-medium text-neutral-900">{{ $line->item?->name ?? 'Item' }}</span>
                                                                @if($line->conversionFactor() > 1)
                                                                    <span class="ml-1 text-[10px] text-neutral-500 font-mono">({{ $line->conversionDisplay() }})</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-2.5 py-2 text-right tabular-nums font-semibold text-neutral-900">
                                                                {{ number_format($line->ordered_quantity) }}
                                                                @if($line->conversionFactor() > 1)
                                                                    <span class="text-[10px] text-neutral-400 font-normal">({{ number_format($line->orderedBaseQuantity()) }} base)</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-2.5 py-2 text-center capitalize text-neutral-700">
                                                                {{ $lineUnit }}
                                                            </td>
                                                            <td class="px-2.5 py-2 text-right font-mono tabular-nums text-neutral-700">
                                                                ₱{{ number_format((float) $line->unit_price, 2) }}/{{ $lineUnit }}
                                                            </td>
                                                            <td class="px-2.5 py-2 text-right font-mono font-semibold tabular-nums text-neutral-900">
                                                                ₱{{ number_format($line->lineTotal(), 2) }}
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        @php
                                                            $singleUnit = $targetPo->purchase_unit ?: ($targetPo->item?->unit ?: 'unit');
                                                            $singleUnitPrice = $targetPo->quantity > 0 ? ($targetPo->unit_cost ?: round($targetPo->total_amount / $targetPo->quantity, 2)) : 0;
                                                        @endphp
                                                        <tr>
                                                            <td class="px-2.5 py-2">
                                                                <span class="font-medium text-neutral-900">{{ $targetPo->item?->name ?? 'Direct Item' }}</span>
                                                                @if($targetPo->conversion_factor > 1)
                                                                    <span class="ml-1 text-[10px] text-neutral-500 font-mono">(1 {{ $singleUnit }} = {{ (int) $targetPo->conversion_factor }} {{ $targetPo->item?->unit }})</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-2.5 py-2 text-right tabular-nums font-semibold text-neutral-900">
                                                                {{ number_format($targetPo->quantity) }}
                                                                @if($targetPo->conversion_factor > 1)
                                                                    <span class="text-[10px] text-neutral-400 font-normal">({{ number_format($targetPo->orderedBaseQuantity()) }} base)</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-2.5 py-2 text-center capitalize text-neutral-700">
                                                                {{ $singleUnit }}
                                                            </td>
                                                            <td class="px-2.5 py-2 text-right font-mono tabular-nums text-neutral-700">
                                                                ₱{{ number_format($singleUnitPrice, 2) }}/{{ $singleUnit }}
                                                            </td>
                                                            <td class="px-2.5 py-2 text-right font-mono font-semibold tabular-nums text-neutral-900">
                                                                ₱{{ number_format((float) $targetPo->total_amount, 2) }}
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                                <tfoot class="border-t border-neutral-200 bg-neutral-50 font-semibold text-neutral-900">
                                                    <tr>
                                                        <td colspan="4" class="px-2.5 py-1.5 text-right text-xs">Purchase Order Commitment Total:</td>
                                                        <td class="px-2.5 py-1.5 text-right font-mono tabular-nums text-xs font-bold text-primary-700">
                                                            ₱{{ number_format($targetPo->grandTotal(), 2) }}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                @if($chain->status === 'pending')
                                    @can('approve_purchase_order')
                                    <div class="mt-4 flex items-center justify-end gap-2 border-t border-neutral-100 pt-3">
                                        <form method="POST" action="{{ route('inventory.purchases.approval-chains.approve', $chain) }}"
                                              data-confirm-title="Authorize procurement approval step"
                                              data-confirm-message="Are you sure you want to authorize this approval step for Chain #{{ $chain->id }} (₱{{ number_format($chain->total_commitment_amount, 2) }})?"
                                              data-confirm-label="Authorize Step">
                                            @csrf
                                            <button type="submit" class="rounded bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                Authorize Step
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory.purchases.approval-chains.reject', $chain) }}"
                                              data-confirm-title="Reject procurement approval step"
                                              data-confirm-message="Are you sure you want to reject this approval chain? The procurement commitment will be halted."
                                              data-confirm-label="Reject Step"
                                              data-confirm-variant="danger">
                                            @csrf
                                            <input type="hidden" name="rejection_reason" value="Executive budget re-allocation">
                                            <button type="submit" class="rounded border border-neutral-300 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-100">
                                                Reject Step
                                            </button>
                                        </form>
                                    </div>
                                    @endcan
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-neutral-500 text-center py-6">No approval chains active.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @endcan

            {{-- ======================================================== PRIMARY: Purchase Order Workspace --}}
            <div x-show="activeTab === 'orders_revisions'" x-cloak class="space-y-4">
                <div class="grid items-start gap-4 {{ $canIssuePurchaseOrder ? 'lg:grid-cols-[minmax(19rem,0.82fr)_minmax(0,1.65fr)]' : '' }}">
                    @can(\App\Enums\Permission::IssuePurchaseOrder->value)
                        <section class="order-2 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm lg:order-1" aria-labelledby="create-po-heading">
                            <header class="border-b border-neutral-200 bg-neutral-50/50 px-4 py-3.5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-200/60">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                        </span>
                                        <div>
                                            <h3 id="create-po-heading" class="text-sm font-bold text-neutral-900">Prepare Purchase Order</h3>
                                            <p class="text-[11px] text-neutral-500">Trusted catalog pricing &amp; compliance</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-200">Catalog PO</span>
                                </div>
                            </header>

                            @if($items->isEmpty() || $suppliers->isEmpty() || $costCenters->isEmpty())
                                <div class="p-4">
                                    <div class="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-4 py-6 text-center">
                                        <p class="text-sm font-medium text-neutral-800">Purchase order setup is incomplete</p>
                                        <p class="mt-1 text-xs text-neutral-500">An active item, eligible supplier, and active cost center are required.</p>
                                    </div>
                                </div>
                            @else
                                <form id="direct-po-form" x-ref="purchaseOrderForm" method="POST" action="{{ route('inventory.purchases.orders.store') }}" class="space-y-4 p-4" data-loading-text="Creating purchase order..." x-on:submit="if (! $refs.purchaseOrderForm.checkValidity()) { $refs.poTerms.open = true }"
                                      data-confirm-title="Issue Purchase Order"
                                      data-confirm-message="Are you sure you want to issue this purchase order? Financial commitments will be recorded."
                                      data-confirm-label="Issue PO">
                                    @csrf

                                    <x-ui.field name="item_id" label="Inventory item" type="select" required x-model="itemId">
                                        <option value="">Select an active item</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->name }} ({{ $item->sku }})</option>
                                        @endforeach
                                    </x-ui.field>

                                    {{-- Item context: only the figures that decide whether to reorder. --}}
                                    <div x-show="selectedItem()" x-cloak class="rounded-md border border-neutral-200 bg-neutral-50 p-2.5">
                                        <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-neutral-500" x-text="selectedItem()?.category"></p>
                                        <dl class="mt-1.5 grid grid-cols-3 gap-2">
                                            <div>
                                                <dt class="text-[10px] uppercase tracking-wide text-neutral-500">On hand</dt>
                                                <dd class="mt-0.5 text-sm font-semibold tabular-nums text-neutral-900"><span x-text="formatNumber(selectedItem()?.current_stock)"></span> <span class="text-[10px] font-normal text-neutral-500" x-text="selectedItem()?.unit"></span></dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] uppercase tracking-wide text-neutral-500">Reorder point</dt>
                                                <dd class="mt-0.5 text-sm font-semibold tabular-nums" x-bind:class="Number(selectedItem()?.current_stock || 0) <= Number(selectedItem()?.reorder_point || 0) ? 'text-danger-700' : 'text-neutral-900'" x-text="formatNumber(selectedItem()?.reorder_point)"></dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] uppercase tracking-wide text-neutral-500">90-day demand</dt>
                                                <dd class="mt-0.5 text-sm font-semibold tabular-nums text-neutral-900"><span x-text="formatNumber(selectedItem()?.recent_demand)"></span> <span class="text-[10px] font-normal text-neutral-500" x-text="selectedItem()?.trend"></span></dd>
                                            </div>
                                        </dl>
                                        <button
                                            type="button"
                                            x-show="Number(selectedItem()?.suggested_order_quantity || 0) > 0"
                                            x-on:click="useSuggestedQuantity()"
                                            class="mt-2.5 flex w-full items-center justify-between gap-2 rounded-md border border-primary-200 bg-white px-2.5 py-1.5 text-left text-xs text-primary-800 transition-colors hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        >
                                            <span class="inline-flex items-center gap-1.5 font-medium">
                                                <svg class="h-3.5 w-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                                <span>Suggested reorder quantity</span>
                                            </span>
                                            <span class="font-semibold tabular-nums text-primary-900">
                                                <span x-text="formatNumber(selectedItem()?.suggested_order_quantity)"></span>
                                                <span x-text="selectedItem()?.unit"></span>
                                                <span class="ml-1 rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold text-primary-800 shadow-2xs">Apply</span>
                                            </span>
                                        </button>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="min-w-0 space-y-1.5">
                                            <label for="po-quantity" class="block text-sm font-medium text-neutral-700">Quantity <span class="text-danger-600">*</span></label>
                                            <input id="po-quantity" name="quantity" type="number" inputmode="numeric" step="1" x-bind:min="minimumOrderQuantity()" max="1000000" x-model.number="quantity" required class="block min-h-10 w-full rounded-md border border-neutral-300 px-3 text-sm shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                                            <p class="text-xs text-neutral-500">Minimum: <span class="font-medium" x-text="formatNumber(minimumOrderQuantity())"></span> <span x-text="selectedItem()?.unit || 'units'"></span></p>
                                            @error('quantity')<p class="text-xs font-medium text-danger-600">{{ $message }}</p>@enderror
                                        </div>

                                        <x-ui.field name="delivery_date" id="po-delivery-date" label="Required delivery" type="date" :value="old('delivery_date')" min="{{ today()->toDateString() }}" x-model="deliveryDate" hint="Optional; supplier lead time is used when blank." />
                                    </div>

                                    <x-ui.field name="supplier_id" label="Eligible supplier" type="select" required x-model="supplierId">
                                        <option value="">Select an accredited supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                                        @endforeach
                                    </x-ui.field>

                                    <div x-show="selectedSupplier()" x-cloak class="rounded-md border border-primary-100 bg-primary-50/60 p-2.5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-semibold text-primary-900" x-text="selectedSupplier()?.name"></p>
                                            <template x-if="selectedSupplier()?.score !== null">
                                                <span class="rounded-full bg-white px-2 py-1 text-[10px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-200">Score <span x-text="formatNumber(selectedSupplier()?.score, 1)"></span>%</span>
                                            </template>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-primary-700">
                                            Procurement eligible
                                            · <span x-text="`${selectedTerms()?.lead_time_days || 0}-day lead time`"></span>
                                            · <span x-text="`min ${formatNumber(selectedTerms()?.minimum_order_quantity)}`"></span>
                                            · <span x-text="selectedTerms()?.price_source === 'supplier_catalog' ? 'supplier contract price' : 'item catalog price'"></span>
                                        </p>
                                    </div>


                                    {{-- Collapsed by default; opened automatically when the browser blocks an invalid submit
                                         or when the server returns a validation error for one of these fields. --}}
                                    <details x-ref="poTerms" @if($errors->hasAny(['cost_center_id', 'payment_terms', 'incoterms', 'notes'])) open @endif class="rounded-md border border-neutral-200 bg-neutral-50">
                                        <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">Order terms and instructions</summary>
                                        <div class="grid gap-3 border-t border-neutral-200 p-3 sm:grid-cols-2">
                                            <x-ui.field name="cost_center_id" label="Cost center" type="select" required>
                                                <option value="">Select active cost center</option>
                                                @foreach($costCenters as $costCenter)
                                                    <option value="{{ $costCenter->id }}" @selected((string) old('cost_center_id') === (string) $costCenter->id)>{{ $costCenter->name }} ({{ $costCenter->code }})</option>
                                                @endforeach
                                            </x-ui.field>

                                            <x-ui.field name="payment_terms" label="Payment terms" type="select" value="Net 30">
                                                @foreach(['Net 15', 'Net 30', 'Net 60', 'COD'] as $term)
                                                    <option value="{{ $term }}" @selected(old('payment_terms', 'Net 30') === $term)>{{ $term }}</option>
                                                @endforeach
                                            </x-ui.field>

                                            <x-ui.field name="incoterms" label="Delivery terms" type="select" value="DDP">
                                                @foreach(['DDP', 'FOB', 'CIF', 'EXW'] as $term)
                                                    <option value="{{ $term }}" @selected(old('incoterms', 'DDP') === $term)>{{ $term }}</option>
                                                @endforeach
                                            </x-ui.field>

                                            <x-ui.field name="notes" label="Delivery instructions" placeholder="Receiving dock or handling notes" :value="old('notes')" />
                                        </div>
                                    </details>

                                    <div class="rounded-lg border border-primary-100 bg-primary-50/60 p-3">
                                        <div class="flex items-end justify-between gap-3">
                                            <div>
                                                <p class="text-[10px] font-medium uppercase tracking-wide text-primary-700">Estimated commitment</p>
                                                <p class="mt-0.5 text-xl font-semibold tabular-nums text-primary-900" x-text="formatCurrency(orderTotal(), selectedTerms()?.currency)"></p>
                                                <p class="mt-0.5 text-[10px] text-primary-700">Expected <span class="font-medium" x-text="expectedDeliveryLabel()"></span></p>
                                            </div>
                                            <x-ui.button type="button" size="sm" x-on:click="openPurchaseOrderReview($refs.purchaseOrderForm)" x-bind:disabled="!selectedItem() || !selectedSupplier() || trustedUnitCost() <= 0" icon="clipboard-document-check">Review Purchase Order</x-ui.button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        </section>
                    @endcan

                    <section id="purchase-orders" class="order-1 min-w-0 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm lg:order-2" aria-labelledby="purchase-order-pipeline-heading">
                        <header class="border-b border-neutral-200 bg-neutral-50/50 px-4 py-3.5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200/60">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    </span>
                                    <div>
                                        <h3 id="purchase-order-pipeline-heading" class="text-sm font-bold text-neutral-900">
                                            Purchase Order Pipeline
                                            <span class="ml-1 text-xs font-normal text-neutral-500">({{ $purchaseOrders->total() }})</span>
                                        </h3>
                                        <p class="text-[11px] text-neutral-500">
                                            Orders across approval, fulfillment, and receiving
                                            @if($purchaseOrders->total() > 0)
                                                · Showing {{ $purchaseOrders->firstItem() }}–{{ $purchaseOrders->lastItem() }} of {{ $purchaseOrders->total() }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @can(\App\Enums\Permission::ViewInventory->value)
                                    <x-ui.button variant="secondary" size="sm" :href="route('inventory.receiving.index')" icon="inbox">Receiving Dock</x-ui.button>
                                @endcan
                            </div>

                            <form method="GET" action="{{ route('inventory.purchases') }}#purchase-orders" class="mt-3 space-y-2">
                                <div class="grid gap-2 sm:grid-cols-[minmax(10rem,1fr)_auto_auto_auto_auto]">
                                    <label class="sr-only" for="po-search">Search purchase orders</label>
                                    <input id="po-search" name="po_search" type="search" value="{{ $poFilters['poSearch'] }}" placeholder="Search PO, item, supplier..." class="min-h-9 min-w-0 rounded-md border border-neutral-300 px-3 text-sm shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                    <label class="sr-only" for="po-status">Status</label>
                                    <select id="po-status" name="po_status" class="min-h-9 rounded-md border border-neutral-300 pl-2.5 pr-8 text-xs text-neutral-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        <option value="">All statuses</option>
                                        @foreach($poStatusOptions as $value => $label)<option value="{{ $value }}" @selected($poFilters['poStatus'] === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                    <label class="sr-only" for="po-date">Created date</label>
                                    <select id="po-date" name="po_date" class="min-h-9 rounded-md border border-neutral-300 pl-2.5 pr-8 text-xs text-neutral-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        <option value="">Any date</option>
                                        <option value="7" @selected($poFilters['poDate'] === '7')>Last 7 days</option>
                                        <option value="30" @selected($poFilters['poDate'] === '30')>Last 30 days</option>
                                        <option value="90" @selected($poFilters['poDate'] === '90')>Last 90 days</option>
                                        <option value="overdue" @selected($poFilters['poDate'] === 'overdue')>Overdue delivery</option>
                                    </select>
                                    <label class="sr-only" for="po-per-page">Per page</label>
                                    <select id="po-per-page" name="po_per_page" class="min-h-9 rounded-md border border-neutral-300 pl-2.5 pr-8 text-xs text-neutral-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        <option value="3" @selected($poPerPage === 3)>3 per page</option>
                                        <option value="5" @selected($poPerPage === 5)>5 per page</option>
                                        <option value="10" @selected($poPerPage === 10)>10 per page</option>
                                        <option value="25" @selected($poPerPage === 25)>25 per page</option>
                                    </select>
                                    <div class="flex gap-1.5">
                                        <x-ui.button type="submit" size="sm">Apply</x-ui.button>
                                        @if($poFilters['poSearch'] !== '' || $poFilters['poStatus'] !== '' || $poFilters['poDate'] !== '' || $supplierFilter || $poPerPage !== 5)
                                            <x-ui.button variant="ghost" size="sm" :href="route('inventory.purchases').'#purchase-orders'">Clear</x-ui.button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Secondary filters stay collapsed so the toolbar keeps a single line. --}}
                                <details class="rounded-md border border-neutral-200 bg-neutral-50">
                                    <summary class="flex cursor-pointer flex-wrap items-center gap-2 px-3 py-2 text-xs font-semibold text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                        More filters
                                        @if($supplierFilter)
                                            <span class="rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-semibold text-primary-800">{{ $supplierFilter->name }}</span>
                                        @endif
                                    </summary>
                                    <div class="grid gap-2 border-t border-neutral-200 p-3 sm:grid-cols-2">
                                        <div>
                                            <label for="po-supplier" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Supplier</label>
                                            <select id="po-supplier" name="supplier_id" class="min-h-9 w-full rounded-md border border-neutral-300 pl-2.5 pr-8 text-xs text-neutral-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                                <option value="">All suppliers</option>
                                                @foreach($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected($supplierFilter?->id === $supplier->id)>{{ $supplier->name }}</option>
                                                @endforeach
                                                @if($supplierFilter && ! $suppliers->contains('id', $supplierFilter->id))
                                                    <option value="{{ $supplierFilter->id }}" selected>{{ $supplierFilter->name }} (not currently eligible)</option>
                                                @endif
                                            </select>
                                        </div>
                                        <p class="self-end text-[11px] text-neutral-500">Combines with the toolbar filters when you press Apply.</p>
                                    </div>
                                </details>
                            </form>
                        </header>

                        <div class="divide-y divide-neutral-200">
                            @forelse($purchaseOrders as $po)
                                @php
                                    $statusEnum = \App\Enums\PurchaseOrderStatus::tryFrom((string) $po->status);
                                    $statusLabel = $statusEnum?->label() ?? \Illuminate\Support\Str::headline((string) $po->status);
                                    $statusVariant = match(true) {
                                        in_array($po->status, ['approved', 'received', 'fulfilled'], true) => 'success',
                                        in_array($po->status, ['submitted', 'pending', 'pending_approval', 'acknowledged', 'partially_fulfilled', 'partially_received'], true) => 'warning',
                                        in_array($po->status, ['cancelled', 'rejected'], true) => 'danger',
                                        in_array($po->status, ['dispatched', 'issued'], true) => 'primary',
                                        default => 'neutral',
                                    };
                                    $poLines = $po->lines->isNotEmpty() ? $po->lines : collect();
                                    $primaryItem = $poLines->first()?->item ?? $po->item;
                                    $orderedQuantity = $poLines->isNotEmpty() ? (int) $poLines->sum('ordered_quantity') : (int) $po->quantity;
                                    $receivedQuantity = $poLines->isNotEmpty() ? (int) $poLines->sum('received_quantity') : ($po->received_at ? $orderedQuantity : 0);
                                    $expectedDelivery = $po->shipments->sortByDesc('id')->first()?->estimated_delivery_date ?? $po->delivery_date;
                                    $isOverdue = $expectedDelivery && $expectedDelivery->lt(today()) && !in_array($po->status, ['received', 'fulfilled', 'cancelled', 'rejected'], true);
                                    $canReceiveThisPo = $statusEnum?->canReceiveStock()
                                        ?? in_array($po->status, ['approved', 'dispatched', 'acknowledged', 'partially_fulfilled', 'partially_received', 'issued'], true);
                                    
                                    $hasSensitivePermission = auth()->user()?->can(\App\Enums\Permission::ViewProcurementSensitiveData->value) ?? false;
                                    $linesData = $poLines->isNotEmpty()
                                        ? $poLines->map(fn ($line) => [
                                            'item' => $line->item?->name ?? 'Unavailable item',
                                            'sku' => $line->item?->sku ?: $line->item?->code ?: '—',
                                            'ordered' => (int) $line->ordered_quantity,
                                            'purchase_unit' => $line->purchase_unit ?: $line->item?->unit ?: 'units',
                                            'conversion_factor' => $line->conversionFactor(),
                                            'conversion_display' => $line->conversionDisplay(),
                                            'equivalent_base_quantity' => $line->orderedBaseQuantity(),
                                            'base_unit' => $line->item?->unit ?: 'units',
                                            'received' => (int) $line->received_quantity,
                                            'received_base' => $line->receivedBaseQuantity(),
                                            'remaining' => $line->remainingQuantity(),
                                            'remaining_base' => $line->remainingBaseQuantity(),
                                            'unit_price' => $hasSensitivePermission ? (float) $line->unit_price : null,
                                            'amount' => $hasSensitivePermission ? $line->lineTotal() : null,
                                        ])->values()
                                        : ($po->item ? collect([[
                                            'item' => $po->item->name,
                                            'sku' => $po->item->sku ?: $po->item->code ?: '—',
                                            'ordered' => (int) $po->quantity,
                                            'purchase_unit' => $po->purchase_unit ?: $po->item->unit ?: 'units',
                                            'conversion_factor' => $po->conversionFactor(),
                                            'conversion_display' => $po->conversion_factor > 1 ? "1 {$po->purchase_unit} = " . (int) $po->conversion_factor . " {$po->item->unit}" : '',
                                            'equivalent_base_quantity' => $po->orderedBaseQuantity(),
                                            'base_unit' => $po->item->unit ?: 'units',
                                            'received' => $po->received_at ? (int) $po->quantity : 0,
                                            'received_base' => $po->receivedBaseQuantity(),
                                            'remaining' => $po->received_at ? 0 : (int) $po->quantity,
                                            'remaining_base' => $po->remainingBaseQuantity(),
                                            'unit_price' => $hasSensitivePermission ? (float) ($po->quantity > 0 ? ($po->unit_cost ?: round($po->total_amount / $po->quantity, 2)) : 0) : null,
                                            'amount' => $hasSensitivePermission ? (float) $po->total_amount : null,
                                        ]]) : collect());

                                    $poDetail = [
                                        'number' => $po->po_number,
                                        'version' => $po->version,
                                        'status' => $statusLabel,
                                        'supplier' => $po->supplier?->name ?? 'Supplier unavailable',
                                        'item' => $primaryItem?->name ?? 'Multiple items',
                                        'quantity' => $orderedQuantity,
                                        'received_quantity' => $receivedQuantity,
                                        'ordered_base_quantity' => $po->orderedBaseQuantity(),
                                        'received_base_quantity' => $po->receivedBaseQuantity(),
                                        'remaining_base_quantity' => $po->remainingBaseQuantity(),
                                        'unit' => $primaryItem?->unit ?: 'units',
                                        'amount' => $hasSensitivePermission ? (float) $po->total_amount : null,
                                        'subtotal' => $hasSensitivePermission ? $po->subtotal() : null,
                                        'additional_charges' => $hasSensitivePermission ? $po->additionalCharges() : null,
                                        'discounts' => $hasSensitivePermission ? $po->discounts() : null,
                                        'grand_total' => $hasSensitivePermission ? $po->grandTotal() : null,
                                        'currency' => $po->currency ?: 'PHP',
                                        'payment_terms' => $hasSensitivePermission ? $po->payment_terms : null,
                                        'incoterms' => $hasSensitivePermission ? $po->incoterms : null,
                                        'created_at' => optional($po->requested_at ?? $po->created_at)->toDateString(),
                                        'delivery_date' => $expectedDelivery?->toDateString(),
                                        'received_at' => $po->received_at?->toDateString(),
                                        'cost_center' => $hasSensitivePermission ? $po->costCenter?->name : null,
                                        'purchase_request' => $po->purchaseRequest?->pr_number,
                                        'created_by' => $po->createdBy?->name,
                                        'approval_status' => $po->approvalChain?->status,
                                        'approval_steps' => $po->approvalChain?->steps->map(fn ($step) => [
                                            'number' => $step->step_number,
                                            'role' => \Illuminate\Support\Str::headline($step->required_role),
                                            'status' => \Illuminate\Support\Str::headline($step->status->value ?? $step->status),
                                            'approver' => $step->approver?->name,
                                        ])->values() ?? [],
                                        'lines' => $linesData,
                                        'shipments' => $po->shipments->sortByDesc('id')->map(fn ($shipment) => [
                                            'number' => $shipment->shipment_number,
                                            'status' => \Illuminate\Support\Str::headline((string) $shipment->status),
                                            'eta' => optional($shipment->estimated_delivery_date)->toDateString(),
                                            'delivered' => optional($shipment->actual_delivery_date)->toDateString(),
                                        ])->values(),
                                        'revisions' => $po->revisions->map(fn ($revision) => [
                                            'code' => $revision->change_order_code,
                                            'status' => \Illuminate\Support\Str::headline((string) $revision->status),
                                            'delta' => (float) $revision->delta_amount,
                                            'variance' => (float) $revision->variance_percentage,
                                            'requires_doa' => (bool) $revision->requires_doa_reapproval,
                                        ])->values(),
                                        'cxml' => $hasSensitivePermission ? $po->cxml_payload : null,
                                        'can_receive' => $canReceiveThisPo
                                            && (auth()->user()?->can(\App\Enums\Permission::ReceivePurchaseOrder->value) ?? false),
                                        'receive_url' => route('inventory.purchases.receive', $po),
                                        'receiving_url' => route('inventory.receiving.index'),
                                    ];
                                    $isNewPo = session('new_po_id') && (string) session('new_po_id') === (string) $po->id;
                                @endphp

                                <article class="p-4 transition-colors {{ $isNewPo ? 'bg-emerald-50/60 ring-1 ring-inset ring-emerald-300/80 rounded-lg' : 'hover:bg-neutral-50/80' }}" data-purchase-order-row>
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button" x-on:click="openPurchaseOrderDetails({{ Js::from($poDetail) }})" class="font-mono text-xs font-bold text-primary-700 hover:text-primary-800 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 flex items-center gap-1">
                                                    <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                    <span>{{ $po->po_number }}</span>
                                                </button>
                                                <x-ui.badge :status="$po->status" :variant="$statusVariant" dot>{{ $statusLabel }}</x-ui.badge>
                                                @if($isNewPo)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 animate-pulse">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                                        Just Issued
                                                    </span>
                                                @endif
                                                @if($isOverdue)
                                                    <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-[10px] font-semibold text-danger-700 ring-1 ring-inset ring-danger-600/20">Overdue</span>
                                                @endif
                                                @if($po->revisions->isNotEmpty())
                                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-mono text-neutral-600">Rev {{ $po->revision_number }}</span>
                                                @endif
                                            </div>
                                            <p class="truncate text-sm font-semibold text-neutral-900">{{ $primaryItem?->name ?? 'Multiple-item order' }}</p>
                                            <div class="flex flex-wrap items-center gap-x-2 text-xs text-neutral-500">
                                                <span class="font-medium text-neutral-700">{{ $po->supplier?->name ?? 'Supplier unavailable' }}</span>
                                                <span>&bull;</span>
                                                @php
                                                    $cardUnit = ($poLines->count() === 1 && $poLines->first()->purchase_unit) ? $poLines->first()->purchase_unit : ($primaryItem?->unit ?: 'units');
                                                @endphp
                                                <span>{{ number_format($orderedQuantity) }} {{ \Illuminate\Support\Str::plural($cardUnit, $orderedQuantity) }}</span>
                                                @if($po->orderedBaseQuantity() !== $orderedQuantity)
                                                    <span class="text-neutral-500 font-medium">(≈ {{ number_format($po->orderedBaseQuantity()) }} base units)</span>
                                                @endif
                                                @if($poLines->count() > 1)
                                                    <span>&bull;</span>
                                                    <span class="text-neutral-400">{{ $poLines->count() }} items</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="grid shrink-0 grid-cols-2 gap-x-6 gap-y-1 text-xs sm:text-right">
                                            @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
                                                <div><p class="text-[10px] uppercase tracking-wider text-neutral-400 font-semibold">Total Amount</p><p class="font-bold tabular-nums text-neutral-900">₱{{ number_format((float) $po->total_amount, 2) }}</p></div>
                                            @endcan
                                            <div>
                                                <p class="text-[10px] uppercase tracking-wider text-neutral-400 font-semibold">Expected Delivery</p>
                                                <p class="font-semibold tabular-nums {{ $isOverdue ? 'text-danger-700 font-bold' : 'text-neutral-700' }}">{{ $expectedDelivery?->format('M j, Y') ?? 'Not scheduled' }}</p>
                                            </div>
                                            <div><p class="text-[10px] uppercase tracking-wider text-neutral-400 font-semibold">Created Date</p><p class="font-medium tabular-nums text-neutral-600">{{ optional($po->requested_at ?? $po->created_at)->format('M j, Y') }}</p></div>
                                            <div>
                                                <p class="text-[10px] uppercase tracking-wider text-neutral-400 font-semibold">Fulfillment</p>
                                                <p class="font-medium tabular-nums {{ $receivedQuantity >= $orderedQuantity && $orderedQuantity > 0 ? 'text-success-700 font-semibold' : 'text-neutral-600' }}">
                                                    {{ number_format($receivedQuantity) }} / {{ number_format($orderedQuantity) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
                                    {{-- Line Items Price & Total Breakdown --}}
                                    <div class="mt-3 overflow-hidden rounded-md border border-neutral-200/80 bg-neutral-50/40 text-xs">
                                        <table class="w-full text-left">
                                            <thead class="bg-neutral-100/75 text-[11px] font-semibold text-neutral-600 border-b border-neutral-200/70">
                                                <tr>
                                                    <th scope="col" class="px-3 py-1.5">Item Name</th>
                                                    <th scope="col" class="px-3 py-1.5 text-right">Quantity</th>
                                                    <th scope="col" class="px-3 py-1.5 text-center">Unit</th>
                                                    <th scope="col" class="px-3 py-1.5 text-right">Price per Unit</th>
                                                    <th scope="col" class="px-3 py-1.5 text-right">Total Price</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-neutral-200/50 bg-white">
                                                @forelse($poLines as $line)
                                                    @php
                                                        $lineUnit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
                                                        $lineUnitPrice = (float) $line->unit_price;
                                                        $lineTotal = $line->lineTotal();
                                                    @endphp
                                                    <tr>
                                                        <td class="px-3 py-2">
                                                            <span class="font-medium text-neutral-900">{{ $line->item?->name ?? 'Item' }}</span>
                                                            @if($line->conversionFactor() > 1)
                                                                <span class="ml-1 text-[10px] text-neutral-500 font-mono">({{ $line->conversionDisplay() }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-neutral-900">
                                                            {{ number_format($line->ordered_quantity) }}
                                                            @if($line->conversionFactor() > 1)
                                                                <span class="text-[10px] text-neutral-400 font-normal">({{ number_format($line->orderedBaseQuantity()) }} base)</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-center capitalize text-neutral-700">
                                                            {{ $lineUnit }}
                                                        </td>
                                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-neutral-700">
                                                            ₱{{ number_format($lineUnitPrice, 2) }}/{{ $lineUnit }}
                                                        </td>
                                                        <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums text-neutral-900">
                                                            ₱{{ number_format($lineTotal, 2) }}
                                                        </td>
                                                    </tr>
                                                @empty
                                                    @if($po->item)
                                                        @php
                                                            $singleUnit = $po->purchase_unit ?: ($po->item->unit ?: 'unit');
                                                            $singleUnitPrice = $po->quantity > 0 ? ($po->unit_cost ?: round($po->total_amount / $po->quantity, 2)) : 0;
                                                            $singleTotal = (float) $po->total_amount;
                                                        @endphp
                                                        <tr>
                                                            <td class="px-3 py-2">
                                                                <span class="font-medium text-neutral-900">{{ $po->item->name }}</span>
                                                                @if($po->conversion_factor > 1)
                                                                    <span class="ml-1 text-[10px] text-neutral-500 font-mono">(1 {{ $singleUnit }} = {{ (int) $po->conversion_factor }} {{ $po->item->unit }})</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-neutral-900">
                                                                {{ number_format($po->quantity) }}
                                                                @if($po->conversion_factor > 1)
                                                                    <span class="text-[10px] text-neutral-400 font-normal">({{ number_format($po->orderedBaseQuantity()) }} base)</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-center capitalize text-neutral-700">
                                                                {{ $singleUnit }}
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-mono tabular-nums text-neutral-700">
                                                                ₱{{ number_format($singleUnitPrice, 2) }}/{{ $singleUnit }}
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums text-neutral-900">
                                                                ₱{{ number_format($singleTotal, 2) }}
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforelse
                                            </tbody>
                                            <tfoot class="border-t border-neutral-200 bg-neutral-50 font-semibold text-neutral-900">
                                                <tr>
                                                    <td colspan="4" class="px-3 py-1.5 text-right text-xs">Purchase Order Total:</td>
                                                    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-xs font-bold text-primary-700">
                                                        ₱{{ number_format($po->grandTotal(), 2) }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    @endcan

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-2.5">
                                        <div class="flex items-center gap-2">
                                            <x-ui.button type="button" variant="secondary" size="sm" x-on:click="openPurchaseOrderDetails({{ Js::from($poDetail) }})">View details</x-ui.button>
                                            @if($po->cxml_payload)
                                                @can(\App\Enums\Permission::ViewProcurementSensitiveData->value)
                                                    <button type="button" x-on:click="selectedPoCxml = {{ Js::from($po->cxml_payload) }}; selectedPoNumber = '{{ $po->po_number }}'; showCxmlModal = true" class="rounded px-2 py-1 text-[11px] font-mono text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800 focus-visible:outline-none">cXML</button>
                                                @endcan
                                            @endif
                                        </div>
                                        @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                                            @if($canReceiveThisPo)
                                                <form method="POST" action="{{ route('inventory.purchases.receive', $po) }}" data-confirm-title="Receive Purchase Order" data-confirm-message="Confirm that this delivery is physically present at the dock before posting into inventory." data-confirm-label="Receive delivery">
                                                    @csrf
                                                    <x-ui.button type="submit" size="sm" icon="check-circle">Receive delivery</x-ui.button>
                                                </form>
                                            @elseif(in_array($po->status, ['received', 'fulfilled'], true))
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-success-700">
                                                    <svg class="h-3.5 w-3.5 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Stock posted
                                                </span>
                                            @endif
                                        @endcan
                                    </div>
                                </article>
                            @empty
                                <div class="px-5 py-12 text-center">
                                    <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100 text-neutral-500"><x-ui.icon name="document-text" class="h-5 w-5" /></span>
                                    <p class="mt-3 text-sm font-medium text-neutral-800">No purchase orders found</p>
                                    <p class="mt-1 text-xs text-neutral-500">{{ collect($poFilters)->filter()->isNotEmpty() || $supplierFilter ? 'Clear the filters to view other orders.' : 'Create the first catalog purchase order when stock needs replenishment.' }}</p>
                                </div>
                            @endforelse
                        </div>

                        <footer class="border-t border-neutral-200 bg-neutral-50 px-4 py-3 sm:px-5 dark:border-neutral-800 dark:bg-neutral-800/60">
                            @if($purchaseOrders->hasPages())
                                {{ $purchaseOrders->fragment('purchase-orders')->onEachSide(1)->links() }}
                            @else
                                <div class="flex items-center justify-between text-xs text-neutral-500">
                                    <span>Showing all {{ $purchaseOrders->total() }} {{ \Illuminate\Support\Str::plural('order', $purchaseOrders->total()) }}</span>
                                    <span class="font-medium text-neutral-400">Page 1 of 1</span>
                                </div>
                            @endif
                        </footer>
                    </section>
                </div>

                @can(\App\Enums\Permission::IssuePurchaseOrder->value)
                    <x-ui.modal name="review-purchase-order" title="Review purchase order" maxWidth="lg">
                        <div class="space-y-4">
                            <div class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2.5 text-xs text-warning-800">Confirm the item, supplier, quantity, and delivery timing. Creating this order commits funds but does not change stock.</div>
                            <dl class="divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                                <div class="flex justify-between gap-4 px-3 py-2.5"><dt class="text-xs text-neutral-500">Item</dt><dd class="text-right text-sm font-medium text-neutral-900" x-text="selectedItem()?.name"></dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2.5"><dt class="text-xs text-neutral-500">Quantity</dt><dd class="text-right text-sm font-medium tabular-nums text-neutral-900"><span x-text="formatNumber(quantity)"></span> <span x-text="selectedItem()?.unit"></span></dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2.5"><dt class="text-xs text-neutral-500">Price per unit</dt><dd class="text-right text-sm font-medium tabular-nums text-neutral-900" x-text="formatCurrency(trustedUnitCost(), selectedTerms()?.currency)"></dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2.5"><dt class="text-xs text-neutral-500">Supplier</dt><dd class="text-right text-sm font-medium text-neutral-900" x-text="selectedSupplier()?.name"></dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2.5"><dt class="text-xs text-neutral-500">Expected delivery</dt><dd class="text-right text-sm font-medium tabular-nums text-neutral-900" x-text="expectedDeliveryLabel()"></dd></div>
                                <div class="flex justify-between gap-4 bg-neutral-50 px-3 py-3"><dt class="text-xs font-semibold text-neutral-700">Estimated total</dt><dd class="text-right text-base font-semibold tabular-nums text-neutral-900" x-text="formatCurrency(orderTotal(), selectedTerms()?.currency)"></dd></div>
                            </dl>
                            <p class="text-xs text-neutral-500">The server will re-check supplier compliance, minimum quantity, catalog pricing, and authorization before saving.</p>
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'review-purchase-order')">Back</x-ui.button>
                                <x-ui.button type="submit" form="direct-po-form" data-loading-text="Creating purchase order...">Confirm &amp; create PO</x-ui.button>
                            </div>
                        </div>
                    </x-ui.modal>
                @endcan

                <x-ui.modal name="purchase-order-details" title="Purchase order details" maxWidth="3xl">
                    <template x-if="selectedPo">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-neutral-100 pb-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-base font-bold text-primary-700" x-text="selectedPo.number"></p>
                                    <p class="mt-0.5 text-xs text-neutral-500">
                                        <span x-text="selectedPo.version || 'Original issue'"></span> · <span x-text="selectedPo.status"></span>
                                        <template x-if="selectedPo.approval_status"><span> · Approval <span x-text="selectedPo.approval_status"></span></span></template>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-neutral-500">Supplier</p>
                                    <p class="text-sm font-semibold text-neutral-900" x-text="selectedPo.supplier"></p>
                                </div>
                            </div>

                            <div class="grid gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200 sm:grid-cols-3">
                                <div class="bg-neutral-50 p-3">
                                    <p class="text-[10px] uppercase tracking-wide text-neutral-500">Ordered Quantity</p>
                                    <p class="mt-1 text-sm font-semibold tabular-nums text-neutral-900">
                                        <span x-text="formatNumber(selectedPo.quantity)"></span> <span x-text="selectedPo.unit"></span>
                                    </p>
                                    <template x-if="selectedPo.ordered_base_quantity && selectedPo.ordered_base_quantity !== selectedPo.quantity">
                                        <p class="text-[11px] text-neutral-500 tabular-nums">≈ <span x-text="formatNumber(selectedPo.ordered_base_quantity)"></span> base units</p>
                                    </template>
                                </div>
                                <div class="bg-neutral-50 p-3">
                                    <p class="text-[10px] uppercase tracking-wide text-neutral-500">Received Stock</p>
                                    <p class="mt-1 text-sm font-semibold tabular-nums text-neutral-900" x-text="`${formatNumber(selectedPo.received_quantity)} / ${formatNumber(selectedPo.quantity)}`"></p>
                                    <template x-if="selectedPo.received_base_quantity && selectedPo.received_base_quantity !== selectedPo.received_quantity">
                                        <p class="text-[11px] text-neutral-500 tabular-nums">≈ <span x-text="formatNumber(selectedPo.received_base_quantity)"></span> base units</p>
                                    </template>
                                </div>
                                <div class="bg-neutral-50 p-3">
                                    <p class="text-[10px] uppercase tracking-wide text-neutral-500">Expected Delivery</p>
                                    <p class="mt-1 text-sm font-semibold tabular-nums text-neutral-900" x-text="formatDate(selectedPo.delivery_date)"></p>
                                </div>
                            </div>

                            {{-- Complete Item Cost Details & Summary Table --}}
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-neutral-600">Order Lines &amp; Cost Breakdown</h4>
                                    <span class="text-xs text-neutral-500" x-text="`${selectedPo.lines.length} item${selectedPo.lines.length === 1 ? '' : 's'}`"></span>
                                </div>
                                <div class="overflow-hidden rounded-lg border border-neutral-200">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                                            <thead class="bg-neutral-50 text-neutral-600">
                                                <tr>
                                                    <th scope="col" class="px-3 py-2 font-semibold">Item &amp; Details</th>
                                                    <th scope="col" class="px-3 py-2 font-semibold text-right">Ordered Qty</th>
                                                    <th scope="col" class="px-3 py-2 font-semibold text-center">Unit</th>
                                                    <th x-show="selectedPo.amount !== null" scope="col" class="px-3 py-2 font-semibold text-right">Price / Unit</th>
                                                    <th x-show="selectedPo.amount !== null" scope="col" class="px-3 py-2 font-semibold text-right">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-neutral-100 bg-white">
                                                <template x-for="(line, index) in selectedPo.lines" x-bind:key="index">
                                                    <tr class="hover:bg-neutral-50/50">
                                                        <td class="px-3 py-2.5">
                                                            <p class="font-medium text-neutral-900" x-text="line.item"></p>
                                                            <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                                                                <span class="font-mono text-[11px] text-neutral-500" x-text="`SKU: ${line.sku}`"></span>
                                                                <template x-if="line.conversion_display">
                                                                    <span class="inline-flex items-center rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20" x-text="line.conversion_display"></span>
                                                                </template>
                                                            </div>
                                                            <p class="mt-1 text-[11px] text-neutral-500" x-text="`${formatNumber(line.received)} of ${formatNumber(line.ordered)} ${line.purchase_unit} received`"></p>
                                                        </td>
                                                        <td class="px-3 py-2.5 text-right tabular-nums">
                                                            <span class="font-semibold text-neutral-900" x-text="formatNumber(line.ordered)"></span>
                                                            <template x-if="line.conversion_factor > 1">
                                                                <p class="text-[11px] text-neutral-500 tabular-nums" x-text="`≈ ${formatNumber(line.equivalent_base_quantity)} ${line.base_unit}`"></p>
                                                            </template>
                                                        </td>
                                                        <td class="px-3 py-2.5 text-center capitalize text-neutral-700" x-text="line.purchase_unit"></td>
                                                        <td x-show="selectedPo.amount !== null" class="px-3 py-2.5 text-right font-mono tabular-nums text-neutral-700" x-text="formatCurrency(line.unit_price, selectedPo.currency)"></td>
                                                        <td x-show="selectedPo.amount !== null" class="px-3 py-2.5 text-right font-semibold font-mono tabular-nums text-neutral-900" x-text="formatCurrency(line.amount, selectedPo.currency)"></td>
                                                    </tr>
                                                </template>
                                                <tr x-show="selectedPo.lines.length === 0">
                                                    <td :colspan="selectedPo.amount !== null ? 5 : 3" class="px-3 py-4 text-center text-neutral-500" x-text="selectedPo.item"></td>
                                                </tr>
                                            </tbody>
                                            <tfoot x-show="selectedPo.amount !== null" class="border-t border-neutral-200 bg-neutral-50/70 text-xs">
                                                <tr>
                                                    <td colspan="4" class="px-3 py-2 text-right font-medium text-neutral-600">Subtotal:</td>
                                                    <td class="px-3 py-2 text-right font-semibold font-mono tabular-nums text-neutral-900" x-text="formatCurrency(selectedPo.subtotal ?? selectedPo.amount, selectedPo.currency)"></td>
                                                </tr>
                                                <template x-if="selectedPo.additional_charges && selectedPo.additional_charges > 0">
                                                    <tr>
                                                        <td colspan="4" class="px-3 py-1.5 text-right font-medium text-neutral-600">Additional Charges:</td>
                                                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-neutral-800" x-text="formatCurrency(selectedPo.additional_charges, selectedPo.currency)"></td>
                                                    </tr>
                                                </template>
                                                <template x-if="selectedPo.discounts && selectedPo.discounts > 0">
                                                    <tr>
                                                        <td colspan="4" class="px-3 py-1.5 text-right font-medium text-neutral-600">Discounts:</td>
                                                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-emerald-700" x-text="`-${formatCurrency(selectedPo.discounts, selectedPo.currency)}`"></td>
                                                    </tr>
                                                </template>
                                                <tr class="border-t border-neutral-200 font-semibold">
                                                    <td colspan="4" class="px-3 py-2.5 text-right text-sm text-neutral-900">Grand Total:</td>
                                                    <td class="px-3 py-2.5 text-right text-sm font-bold font-mono tabular-nums text-primary-700" x-text="formatCurrency(selectedPo.grand_total ?? selectedPo.amount, selectedPo.currency)"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Delivery &amp; receiving</h4>
                                <dl class="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-xs text-neutral-500">Raised</dt><dd class="mt-0.5 font-medium text-neutral-900" x-text="formatDate(selectedPo.created_at)"></dd></div>
                                    <div><dt class="text-xs text-neutral-500">Stock posted</dt><dd class="mt-0.5 font-medium text-neutral-900" x-text="selectedPo.received_at ? formatDate(selectedPo.received_at) : 'Not yet received'"></dd></div>
                                </dl>
                                <template x-if="selectedPo.shipments.length > 0">
                                    <ul class="mt-2 divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                                        <template x-for="shipment in selectedPo.shipments" x-bind:key="shipment.number">
                                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs">
                                                <span class="font-mono font-medium text-neutral-800" x-text="shipment.number"></span>
                                                <span class="text-neutral-500" x-text="shipment.status"></span>
                                                <span class="tabular-nums text-neutral-700" x-text="shipment.delivered ? 'Delivered ' + formatDate(shipment.delivered) : 'ETA ' + formatDate(shipment.eta)"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div x-show="selectedPo.purchase_request"><dt class="text-xs text-neutral-500">Purchase request</dt><dd class="mt-0.5 font-medium text-neutral-900" x-text="selectedPo.purchase_request"></dd></div>
                                <div x-show="selectedPo.cost_center"><dt class="text-xs text-neutral-500">Cost center</dt><dd class="mt-0.5 font-medium text-neutral-900" x-text="selectedPo.cost_center"></dd></div>
                                <div x-show="selectedPo.payment_terms"><dt class="text-xs text-neutral-500">Commercial terms</dt><dd class="mt-0.5 font-medium text-neutral-900" x-text="`${selectedPo.payment_terms || ''} · ${selectedPo.incoterms || ''}`"></dd></div>
                                <div x-show="selectedPo.created_by"><dt class="text-xs text-neutral-500">Raised by</dt><dd class="mt-0.5 font-medium text-neutral-900" x-text="selectedPo.created_by"></dd></div>
                                <div x-show="selectedPo.amount !== null"><dt class="text-xs text-neutral-500">Total commitment</dt><dd class="mt-0.5 font-semibold tabular-nums text-neutral-900" x-text="formatCurrency(selectedPo.amount, selectedPo.currency)"></dd></div>
                            </dl>
                            <div x-show="selectedPo.approval_steps.length > 0">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Approval history</h4>
                                <div class="mt-2 space-y-2">
                                    <template x-for="step in selectedPo.approval_steps" x-bind:key="step.number">
                                        <div class="flex items-center justify-between rounded-md border border-neutral-200 px-3 py-2 text-xs"><span x-text="`Step ${step.number} · ${step.role}`"></span><span class="font-medium text-neutral-700" x-text="`${step.status}${step.approver ? ` · ${step.approver}` : ''}`"></span></div>
                                    </template>
                                </div>
                            </div>
                            <div x-show="selectedPo.revisions.length > 0">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Change orders</h4>
                                <div class="mt-2 divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                                    <template x-for="revision in selectedPo.revisions" x-bind:key="revision.code">
                                        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs">
                                            <span class="font-mono font-medium text-neutral-800" x-text="revision.code"></span>
                                            <span class="text-neutral-500" x-text="revision.status"></span>
                                            <span class="tabular-nums text-neutral-700" x-text="formatCurrency(revision.delta, selectedPo.currency) + ' (' + formatNumber(revision.variance, 2) + '%)'"></span>
                                            <span x-show="revision.requires_doa" class="rounded-full bg-warning-50 px-2 py-0.5 text-[10px] font-semibold text-warning-700">Re-approval required</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <x-slot name="footer">
                        <template x-if="selectedPo">
                            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                                <template x-if="selectedPo.cxml">
                                    <button type="button" x-on:click="selectedPoCxml = selectedPo.cxml; selectedPoNumber = selectedPo.number; showCxmlModal = true" class="mr-auto rounded-md px-2 py-1 font-mono text-xs text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">cXML payload</button>
                                </template>
                                @can(\App\Enums\Permission::ViewInventory->value)
                                    <a x-bind:href="selectedPo.receiving_url" class="inline-flex min-h-9 items-center gap-2 rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">View receiving</a>
                                @endcan
                                @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                                    <template x-if="selectedPo.can_receive">
                                        <form method="POST" x-bind:action="selectedPo.receive_url" data-confirm-title="Receive Purchase Order" data-confirm-message="Confirm that this delivery is physically present before posting it into stock." data-confirm-label="Receive delivery">
                                            @csrf
                                            <x-ui.button type="submit" size="sm">Receive delivery</x-ui.button>
                                        </form>
                                    </template>
                                @endcan
                            </div>
                        </template>
                    </x-slot>
                </x-ui.modal>
            </div>
            {{-- ======================================================== TAB 6: Standard Canvassing --}}
            {{-- ======================================================== TAB 6: Standard Canvassing --}}
            @php
                $initialCanvassStep = 1;
                if (session('success') && str_contains(session('success'), 'Procurement request')) {
                    $initialCanvassStep = 3;
                } elseif (session('success') && str_contains(session('success'), 'Supplier quote')) {
                    $initialCanvassStep = 3;
                } elseif (old('procurement_request_id') || old('quoted_price') || $errors->hasAny(['procurement_request_id', 'supplier_id', 'quoted_price', 'notes'])) {
                    $initialCanvassStep = 3;
                } elseif (old('title') || old('item_id') || old('requested_quantity') || $errors->hasAny(['title', 'item_id', 'requested_quantity', 'priority', 'description', 'preferred_supplier', 'evaluation_status', 'evaluation_score', 'approved_by', 'approval_notes'])) {
                    $initialCanvassStep = 2;
                }
            @endphp
            <div
                x-show="activeTab === 'legacy_canvass'"
                x-data="{
                    canvassStep: {{ $initialCanvassStep }},
                    hasExistingRequests: {{ $requests->isNotEmpty() ? 'true' : 'false' }},

                    proceedFromStep2() {
                        const form = document.getElementById('procurement-request-form');
                        if (form) {
                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }
                            form.submit();
                        } else {
                            this.canvassStep = 3;
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    },

                    proceedFromStep3() {
                        const form = document.getElementById('supplier-quote-form');
                        if (form) {
                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }
                            form.submit();
                        } else {
                            this.activeTab = 'orders_revisions';
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }
                }"
                class="space-y-3"
            >

                {{-- Stepper Progress Tracker (Non-clickable) --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-2 sm:p-2.5 shadow-xs">
                    <div class="flex items-center justify-between gap-2 overflow-x-auto select-none">
                        <!-- Step 1 Tracker -->
                        <div
                            class="flex items-center gap-2 flex-1 min-w-[150px] py-1.5 px-2 rounded-lg text-left transition-all cursor-default"
                            :class="canvassStep === 1 ? 'bg-primary-50 ring-1 ring-primary-500/30' : (canvassStep > 1 ? 'bg-white' : 'opacity-75')">
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold transition-colors"
                                :class="canvassStep === 1 ? 'bg-primary-600 text-white shadow-2xs' : (canvassStep > 1 ? 'bg-emerald-600 text-white' : 'bg-neutral-200 text-neutral-600')">
                                <template x-if="canvassStep > 1">
                                    <x-ui.icon name="check" class="w-3 h-3" />
                                </template>
                                <template x-if="canvassStep <= 1">
                                    <span>1</span>
                                </template>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold leading-tight" :class="canvassStep === 1 ? 'text-primary-900' : (canvassStep > 1 ? 'text-emerald-950' : 'text-neutral-700')">
                                    Step 1: Planning
                                </p>
                                <p class="text-[11px] text-neutral-500 truncate">Demand &amp; Forecasts</p>
                            </div>
                        </div>

                        <!-- Step 1 -> 2 Divider Arrow -->
                        <div class="hidden sm:flex items-center text-neutral-300">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>

                        <!-- Step 2 Tracker -->
                        <div
                            class="flex items-center gap-2 flex-1 min-w-[150px] py-1.5 px-2 rounded-lg text-left transition-all cursor-default"
                            :class="canvassStep === 2 ? 'bg-primary-50 ring-1 ring-primary-500/30' : (hasExistingRequests ? 'bg-white' : 'opacity-75')">
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold transition-colors"
                                :class="canvassStep === 2 ? 'bg-primary-600 text-white shadow-2xs' : (hasExistingRequests ? 'bg-emerald-600 text-white' : 'bg-neutral-200 text-neutral-600')">
                                <template x-if="hasExistingRequests">
                                    <x-ui.icon name="check" class="w-3 h-3" />
                                </template>
                                <template x-if="!hasExistingRequests">
                                    <span>2</span>
                                </template>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold leading-tight" :class="canvassStep === 2 ? 'text-primary-900' : (hasExistingRequests ? 'text-emerald-950' : 'text-neutral-700')">
                                    Step 2: Requisition
                                </p>
                                <p class="text-[11px] text-neutral-500 truncate">Procurement Request</p>
                            </div>
                        </div>

                        <!-- Step 2 -> 3 Divider Arrow -->
                        <div class="hidden sm:flex items-center text-neutral-300">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>

                        <!-- Step 3 Tracker -->
                        <div
                            class="flex items-center gap-2 flex-1 min-w-[150px] py-1.5 px-2 rounded-lg text-left transition-all cursor-default"
                            :class="canvassStep === 3 ? 'bg-primary-50 ring-1 ring-primary-500/30' : (hasExistingRequests ? 'bg-white' : 'opacity-60')">
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold transition-colors"
                                :class="canvassStep === 3 ? 'bg-primary-600 text-white shadow-2xs' : 'bg-neutral-200 text-neutral-600'">
                                <span>3</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold leading-tight" :class="canvassStep === 3 ? 'text-primary-900' : 'text-neutral-700'">
                                    Step 3: Canvass &amp; Orders
                                </p>
                                <p class="text-[11px] text-neutral-500 truncate">Quotes &amp; PO Workspace</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== STEP 1: Planning & Demand Forecasting ==================== --}}
                <div x-show="canvassStep === 1" class="space-y-3">
                    {{-- Step 1 Top Bar: Stage Info & Navigation Actions --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 sm:py-2.5 shadow-xs">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20">Step 1 of 3</span>
                            <h3 class="text-xs sm:text-sm font-bold text-neutral-900">Planning &amp; Demand Forecasting</h3>
                            <span class="hidden md:inline text-xs text-neutral-300">|</span>
                            <p class="hidden md:inline text-xs text-neutral-500">Review forecasted demand before generating procurement requisitions.</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('inventory.demand-forecast') }}"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 hover:text-neutral-900 transition">
                                <x-ui.icon name="chart-bar" class="w-3.5 h-3.5 text-neutral-500" />
                                <span>Open Demand Forecasting</span>
                            </a>
                        </div>
                    </div>

                    {{-- Side-by-Side: Planning & Forecasting Insights (Left) & Demand Plans List (Right) --}}
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                        {{-- Left: Planning & Forecasting Guidelines Card (7 cols) --}}
                        <div class="lg:col-span-7 rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-xs">
                            <div class="border-b border-neutral-100 pb-2.5 mb-3">
                                <h4 class="text-xs sm:text-sm font-bold text-neutral-900">Demand Forecasting Overview</h4>
                                <p class="text-[11px] text-neutral-500">Automated calculation metrics based on inventory consumption history.</p>
                            </div>

                            <div class="space-y-3">
                                <p class="text-xs text-neutral-600 leading-relaxed">
                                    Forecasts are calculated automatically from consumption history (average daily usage, safety stock, reorder point and suggested quantity) rather than entered manually.
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                                    <div class="rounded-lg border border-neutral-100 bg-neutral-50/70 p-2.5">
                                        <div class="flex items-center gap-1.5 text-primary-700">
                                            <x-ui.icon name="chart-bar" class="w-4 h-4" />
                                            <span class="text-xs font-semibold">Daily Usage</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-neutral-500">Averaged across real clinical and warehouse transactions.</p>
                                    </div>
                                    <div class="rounded-lg border border-neutral-100 bg-neutral-50/70 p-2.5">
                                        <div class="flex items-center gap-1.5 text-amber-700">
                                            <x-ui.icon name="shield-check" class="w-4 h-4" />
                                            <span class="text-xs font-semibold">Safety Stock</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-neutral-500">Dynamic safety buffers based on supplier lead times.</p>
                                    </div>
                                    <div class="rounded-lg border border-neutral-100 bg-neutral-50/70 p-2.5">
                                        <div class="flex items-center gap-1.5 text-emerald-700">
                                            <x-ui.icon name="arrow-trending-up" class="w-4 h-4" />
                                            <span class="text-xs font-semibold">Suggested Qty</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-neutral-500">Automated order batches avoiding excess storage costs.</p>
                                    </div>
                                </div>

                                <div class="pt-2 flex items-center justify-between border-t border-neutral-100">
                                    <span class="text-xs text-neutral-500">Ready to create a purchase requisition?</span>
                                    <x-ui.button type="button" variant="primary" size="sm" icon="arrow-right" @click="canvassStep = 2; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                        Proceed to Step 2
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Live Demand Plans List (5 cols) --}}
                        <div class="lg:col-span-5 rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-xs">
                            <div class="flex items-center justify-between border-b border-neutral-100 pb-2.5 mb-3">
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-neutral-900">Demand plans</h4>
                                    <p class="text-[11px] text-neutral-500">Generated automated plans</p>
                                </div>
                                <x-ui.loader id="demand-plans-api-status" size="sm" label="Loading..." class="text-xs text-neutral-400" />
                            </div>
                            <div id="demand-plans-list" class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
                                <p class="rounded-lg border border-dashed border-neutral-200 bg-neutral-50/70 px-3 py-2.5 text-xs text-neutral-500">Loading demand plans from API...</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== STEP 2: Procurement Request ==================== --}}
                <div x-show="canvassStep === 2" class="space-y-3">
                    {{-- Step 2 Top Bar: Stage Info & Navigation Actions --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 sm:py-2.5 shadow-xs">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20">Step 2 of 3</span>
                            <h3 class="text-xs sm:text-sm font-bold text-neutral-900">Procurement request</h3>
                            <span class="hidden md:inline text-xs text-neutral-300">|</span>
                            <p class="hidden md:inline text-xs text-neutral-500">Fill in requisition details or review submitted requests.</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-ui.button type="button" variant="secondary" size="sm" icon="arrow-left" @click="canvassStep = 1; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                Back: Planning
                            </x-ui.button>
                            @if ($requests->isNotEmpty())
                                <x-ui.button type="button" variant="secondary" size="sm" @click="canvassStep = 3; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                    <span>Skip to Quotes</span>
                                    <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 ml-1" />
                                </x-ui.button>
                            @endif
                            <x-ui.button type="button" variant="primary" size="sm" icon="arrow-right" @click="proceedFromStep2()">
                                <span>Save &amp; Next: Quotations</span>
                            </x-ui.button>
                        </div>
                    </div>

                    {{-- Side-by-Side: Requisition Form (Left) & Procurement Requests List (Right) --}}
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                        {{-- Left: Create Requisition Form --}}
                        <div class="lg:col-span-7 rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-xs">
                            <div class="border-b border-neutral-100 pb-2.5 mb-3">
                                <h4 class="text-xs sm:text-sm font-bold text-neutral-900">New Requisition Form</h4>
                                <p class="text-[11px] text-neutral-500">Fields marked with an asterisk (<span class="text-danger-600 font-bold">*</span>) are required.</p>
                            </div>

                            @can('create_requisition')
                            <form id="procurement-request-form" method="POST" action="{{ route('inventory.purchases.requests.store') }}" class="grid grid-cols-12 gap-x-3 gap-y-2.5"
                                  data-confirm-title="Submit purchase request"
                                  data-confirm-message="Are you sure you want to submit this purchase request?"
                                  data-confirm-label="Submit Request">
                                @csrf
                                {{-- Row 1: Title (8 cols) & Priority (4 cols) --}}
                                <div class="col-span-12 sm:col-span-8">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">
                                        Title <span class="text-danger-600">*</span>
                                    </label>
                                    <input type="text" name="title" placeholder="e.g. Monthly Clinical PPE Restock" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors" required />
                                </div>
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Priority</label>
                                    <select name="priority" class="block w-full rounded-lg border border-neutral-300 bg-white pl-3 pr-8 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>

                                {{-- Row 2: Item (8 cols) & Requested quantity (4 cols) --}}
                                <div class="col-span-12 sm:col-span-8">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">
                                        Item <span class="text-danger-600">*</span>
                                    </label>
                                    <select id="request-item-select" name="item_id" class="block w-full rounded-lg border border-neutral-300 bg-white pl-3 pr-8 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors" required>
                                        <option value="">Select item</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">
                                        Requested quantity <span class="text-danger-600">*</span>
                                    </label>
                                    <input type="number" name="requested_quantity" min="1" placeholder="e.g. 100" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono transition-colors" required />
                                </div>

                                {{-- Row 3: Description --}}
                                <div class="col-span-12">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Description</label>
                                    <textarea name="description" rows="2" placeholder="Requisition rationale, usage context, or technical specifications (optional)" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"></textarea>
                                </div>

                                {{-- Row 4: Preferred supplier (7 cols) & Evaluation status (5 cols) --}}
                                <div class="col-span-12 sm:col-span-7">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Preferred supplier</label>
                                    <select id="request-supplier-select" name="supplier_id" class="block w-full rounded-lg border border-neutral-300 bg-white pl-3 pr-8 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors">
                                        <option value="">Select supplier (optional)</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-12 sm:col-span-5">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Evaluation status</label>
                                    <select name="evaluation_status" class="block w-full rounded-lg border border-neutral-300 bg-white pl-3 pr-8 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors">
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>

                                {{-- Row 5: Evaluation score (4 cols) & Approved by (8 cols) --}}
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Evaluation score</label>
                                    <input type="number" step="0.01" name="evaluation_score" min="0" max="100" placeholder="0 - 100" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono transition-colors" />
                                </div>
                                <div class="col-span-12 sm:col-span-8">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Approved by</label>
                                    <input type="text" name="approved_by" placeholder="Approver name" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors" />
                                </div>

                                {{-- Row 6: Approval notes --}}
                                <div class="col-span-12">
                                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Approval notes</label>
                                    <textarea name="approval_notes" rows="2" placeholder="Conditions, budget tags, or approval remarks (optional)" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"></textarea>
                                </div>

                                {{-- Action Row --}}
                                <div class="col-span-12 flex items-center justify-end pt-1">
                                    <x-ui.button type="submit" size="sm" icon="plus">Create request</x-ui.button>
                                </div>
                            </form>
                            @else
                            <p class="text-xs text-neutral-500">You do not have permission to create procurement requests.</p>
                            @endcan
                        </div>

                        {{-- Right: Live Procurement Requests List --}}
                        <div class="lg:col-span-5 rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-xs">
                            <div class="flex items-center justify-between border-b border-neutral-100 pb-2.5 mb-3">
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-neutral-900">Procurement requests</h4>
                                    <p class="text-[11px] text-neutral-500">List of submitted requisitions</p>
                                </div>
                                <x-ui.loader id="procurement-requests-api-status" size="sm" label="Loading..." class="text-xs text-neutral-400" />
                            </div>
                            <div id="procurement-requests-list" class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
                                <p class="rounded-lg border border-dashed border-neutral-200 bg-neutral-50/70 px-3 py-2.5 text-xs text-neutral-500">Loading procurement requests from API...</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== STEP 3: Supplier Quotation & Purchase Orders ==================== --}}
                <div x-show="canvassStep === 3" class="space-y-3">
                    {{-- Step 3 Top Bar: Stage Info & Navigation Actions --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 sm:py-2.5 shadow-xs">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20">Step 3 of 3</span>
                            <span class="sr-only">Stage 3 &bull; Supplier quotation</span>
                            <h3 class="text-xs sm:text-sm font-bold text-neutral-900">Supplier quotation</h3>
                            <span class="hidden md:inline text-xs text-neutral-300">|</span>
                            <p class="hidden md:inline text-xs text-neutral-500">Record vendor quotes against an approved request. Canvass multiple suppliers, then proceed to PO.</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-ui.button type="button" variant="secondary" size="sm" icon="arrow-left" @click="canvassStep = 2; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                Back: Procurement request
                            </x-ui.button>
                            <x-ui.button type="button" variant="secondary" size="sm" x-on:click="activeTab = 'orders_revisions'; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                <span>PO Workspace</span>
                                <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 ml-1" />
                            </x-ui.button>
                            @if ($requests->isEmpty())
                                <x-ui.button type="button" variant="secondary" size="sm" icon="arrow-left" @click="canvassStep = 2; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                    Complete Requisition First
                                </x-ui.button>
                            @else
                                <x-ui.button type="button" variant="primary" size="sm" icon="check" @click="proceedFromStep3()">
                                    Record Quote &amp; Proceed
                                </x-ui.button>
                            @endif
                        </div>
                    </div>

                    {{-- Side-by-Side: Supplier Quote Form (Left) & Supplier Quotations List (Right) --}}
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                        {{-- Left: Supplier Quotation Form --}}
                        <div class="lg:col-span-7 rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-xs">
                            <div class="border-b border-neutral-100 pb-2.5 mb-3">
                                <h4 class="text-xs sm:text-sm font-bold text-neutral-900">Record Vendor Quotation</h4>
                                <p class="text-[11px] text-neutral-500">Attach competitive supplier pricing to an approved request.</p>
                            </div>

                            @can('manage_sourcing')
                                @if ($requests->isEmpty())
                                    <div class="rounded-lg border border-dashed border-neutral-200 bg-neutral-50/70 p-5 text-center">
                                        <x-ui.icon name="document-text" class="mx-auto h-7 w-7 text-neutral-400 mb-1.5" />
                                        <p class="text-xs font-semibold text-neutral-700">Create a procurement request first</p>
                                        <p class="mt-0.5 text-[11px] text-neutral-500">A quote has to attach to one before quotes can be recorded.</p>
                                        <div class="mt-3">
                                            <x-ui.button type="button" variant="primary" size="sm" icon="arrow-left" @click="canvassStep = 2; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                                Go to Step 2: Requisition
                                            </x-ui.button>
                                        </div>
                                    </div>
                                @else
                                    <form id="supplier-quote-form" method="POST" action="{{ route('inventory.purchases.quotes.store') }}" class="grid grid-cols-12 gap-x-3 gap-y-2.5"
                                          data-confirm-title="Submit supplier quote"
                                          data-confirm-message="Are you sure you want to record this supplier quotation?"
                                          data-confirm-label="Submit Quote">
                                        @csrf
                                        <div class="col-span-12 sm:col-span-7">
                                            <label for="quote-request" class="mb-1 block text-xs font-semibold text-neutral-700">
                                                Procurement request <span class="text-danger-600">*</span>
                                            </label>
                                            <select id="quote-request" name="procurement_request_id" class="block w-full rounded-lg border border-neutral-300 bg-white pl-3 pr-8 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors" required>
                                                <option value="">Select request</option>
                                                @foreach ($requests as $procurementRequest)
                                                    <option value="{{ $procurementRequest->id }}" @selected(old('procurement_request_id') == $procurementRequest->id)>
                                                        {{ $procurementRequest->request_number }} — {{ $procurementRequest->title }}
                                                        ({{ $procurementRequest->item?->name ?? 'no item' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('procurement_request_id')" class="mt-1" />
                                        </div>
                                        <div class="col-span-12 sm:col-span-5">
                                            <label for="quote-supplier" class="mb-1 block text-xs font-semibold text-neutral-700">
                                                Supplier <span class="text-danger-600">*</span>
                                            </label>
                                            <select id="quote-supplier" name="supplier_id" class="block w-full rounded-lg border border-neutral-300 bg-white pl-3 pr-8 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors" required>
                                                <option value="">Select supplier</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                                        {{ $supplier->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                                        </div>
                                        <div class="col-span-12 sm:col-span-5">
                                            <label class="mb-1 block text-xs font-semibold text-neutral-700">
                                                Quoted price (₱) <span class="text-danger-600">*</span>
                                            </label>
                                            <input type="number" step="0.01" min="0.01" name="quoted_price" value="{{ old('quoted_price') }}" placeholder="0.00" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono transition-colors" required />
                                            <x-input-error :messages="$errors->get('quoted_price')" class="mt-1" />
                                        </div>
                                        <div class="col-span-12 sm:col-span-7">
                                            <label class="mb-1 block text-xs font-semibold text-neutral-700">Notes</label>
                                            <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Terms or remarks (optional)" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs text-neutral-800 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors" />
                                        </div>
                                        <div class="col-span-12 flex items-center justify-end pt-1">
                                            <x-ui.button type="submit" size="sm" icon="check">Record quote</x-ui.button>
                                        </div>
                                    </form>
                                @endif
                            @else
                                <p class="text-xs text-neutral-500">You do not have permission to record supplier quotations.</p>
                            @endcan
                        </div>

                        {{-- Right: Live Supplier Quotations List --}}
                        <div class="lg:col-span-5 rounded-xl border border-neutral-200 bg-white p-3.5 sm:p-4 shadow-xs">
                            <div class="flex items-center justify-between border-b border-neutral-100 pb-2.5 mb-3">
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-neutral-900">Supplier quotations</h4>
                                    <p class="text-[11px] text-neutral-500">Vendor pricing submitted</p>
                                </div>
                                <x-ui.loader id="supplier-quotes-api-status" size="sm" label="Loading..." class="text-xs text-neutral-400" />
                            </div>
                            <div id="supplier-quotes-list" class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
                                <p class="rounded-lg border border-dashed border-neutral-200 bg-neutral-50/70 px-3 py-2.5 text-xs text-neutral-500">Loading supplier quotes from API...</p>
                            </div>
                        </div>
                    </div>

                    {{-- Handover to PO workspace card --}}
                    <div class="rounded-xl border border-primary-100 bg-primary-50/60 p-2.5 sm:p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-xs sm:text-sm font-semibold text-primary-950">Continue to purchase orders</h3>
                                <p class="text-[11px] text-primary-800">Order creation, status tracking, details, and receiving actions are consolidated in the primary workspace.</p>
                            </div>
                            <x-ui.button type="button" size="sm" x-on:click="activeTab = 'orders_revisions'; window.scrollTo({ top: 0, behavior: 'smooth' })">
                                Open PO workspace
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ======================================================== TAB 7: Procurement Audit Trail --}}
            @can('view_audit_trail')
            <div x-show="activeTab === 'audit_trail'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-neutral-900">Append-Only Procurement Audit Ledger</h3>
                    <p class="text-sm text-neutral-500">Immutable chronological record of procurement events, state transitions, and user attribution.</p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <tr>
                                    <th class="px-3.5 py-3">Timestamp (PHT)</th>
                                    <th class="px-3.5 py-3">Actor</th>
                                    <th class="px-3.5 py-3">Action Type</th>
                                    <th class="px-3.5 py-3">Entity &amp; ID</th>
                                    <th class="px-3.5 py-3">Serialized State Delta</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($procurementAuditLogs as $log)
                                    <tr class="hover:bg-neutral-50 font-mono text-xs">
                                        <td class="px-3.5 py-3 text-neutral-500">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                        <td class="px-3.5 py-3 font-sans font-medium text-neutral-900">{{ $log->user?->name ?? 'System' }}</td>
                                        <td class="px-3.5 py-3">
                                            <span class="rounded bg-neutral-100 px-2 py-0.5 font-sans font-semibold text-neutral-800">
                                                {{ $log->action_type }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3 font-sans">{{ $log->entity_name }} #{{ $log->entity_id }}</td>
                                        <td class="px-3.5 py-3 max-w-xs truncate text-neutral-600" title="{{ json_encode($log->new_values) }}">
                                            {{ json_encode($log->new_values) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3.5 py-6 text-center text-sm text-neutral-500">No procurement audit records captured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endcan

            {{-- B2B cXML Modal Viewer --}}
            <div x-show="showCxmlModal" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" style="display: none;">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="fixed inset-0 bg-neutral-900/60 transition-opacity" @click="showCxmlModal = false"></div>
                    <div class="relative w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl transition-all">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-800">B2B cXML Document</span>
                                <h3 class="text-base font-bold text-neutral-900">OrderRequest: <span x-text="selectedPoNumber" class="font-mono text-primary-700"></span></h3>
                            </div>
                            <button @click="showCxmlModal = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="mt-4 max-h-96 overflow-y-auto rounded-lg bg-neutral-900 p-4 font-mono text-xs text-emerald-400 select-all">
                            <pre><code x-text="selectedPoCxml || 'No cXML payload generated.'"></code></pre>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button @click="showCxmlModal = false" class="rounded-lg bg-neutral-100 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-200">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    {{-- Preserved Legacy API Rendering Scripts --}}
    <script>
        async function fetchApiJson(url) {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) {
                throw new Error(`Request failed with status ${res.status}`);
            }

            return res.json();
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) {
                return '';
            }

            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        function renderDemandPlans(plans) {
            const container = document.getElementById('demand-plans-list');
            if (!plans.length) {
                container.innerHTML = '<p class="text-sm text-[var(--muted)]">No demand plans generated yet.</p>';
                return;
            }

            container.innerHTML = plans.map(p => `
                <div class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-[var(--text)]">${escapeHtml(p.plan_number || 'Plan')}</span>
                        <span class="text-xs text-[var(--muted)]">${p.trend || 'stable'}</span>
                    </div>
                    <p class="mt-1 text-xs text-[var(--muted)]">Suggested order qty: <strong>${p.suggested_order_quantity || 0}</strong></p>
                </div>
            `).join('');
        }

        function renderApprovalForm(request) {
            if (request.status === 'approved') {
                return '<span class="text-xs text-[var(--muted)]">Approved</span>';
            }

            @can(\App\Enums\Permission::ApproveRequisition->value)
                return `
                    <form method="POST" action="/inventory/purchases/requests/${request.id}/approve"
                          class="inline"
                          data-confirm-title="Confirm request approval"
                          data-confirm-message="Are you sure you want to approve this procurement request?"
                          data-confirm-label="Approve Request">
                        <input type="hidden" name="_token" value="${csrfToken()}">
                        <button type="submit" class="rounded-lg bg-[var(--primary)] px-3 py-1.5 text-xs font-semibold text-white">
                            Approve request
                        </button>
                    </form>
                `;
            @else
                return '<span class="text-xs text-[var(--muted)]">Pending approval</span>';
            @endcan
        }

        function renderProcurementRequests(requests) {
            const container = document.getElementById('procurement-requests-list');
            if (!requests.length) {
                container.innerHTML = '<p class="text-sm text-[var(--muted)]">No procurement requests found.</p>';
                return;
            }

            container.innerHTML = requests.map(r => `
                <div class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-sm flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-[var(--text)]">${escapeHtml(r.request_number || '')}</span> —
                        <span>${escapeHtml(r.title || '')}</span>
                        <p class="text-xs text-[var(--muted)]">Qty: ${r.requested_quantity || 0} • Status: ${r.status || 'pending'}</p>
                    </div>
                    <div>${renderApprovalForm(r)}</div>
                </div>
            `).join('');
        }

        function renderSupplierQuotes(quotes) {
            const container = document.getElementById('supplier-quotes-list');
            if (!quotes.length) {
                container.innerHTML = '<p class="text-sm text-[var(--muted)]">No supplier quotes recorded yet.</p>';
                return;
            }

            container.innerHTML = quotes.map(q => `
                <div class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-sm flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-[var(--text)]">${q.supplier ? escapeHtml(q.supplier.name) : 'Supplier'}</span>:
                        <strong>${formatCurrency(q.quoted_price || 0)}</strong>
                        <p class="text-xs text-[var(--muted)]">${escapeHtml(q.notes || '')}</p>
                    </div>
                    <span class="rounded bg-neutral-100 px-2 py-1 text-xs">${escapeHtml(q.status || 'submitted')}</span>
                </div>
            `).join('');
        }

        async function loadDemandPlansFromApi() {
            const status = document.getElementById('demand-plans-api-status');

            try {
                const payload = await fetchApiJson('/api/v1/demand-plans?per_page=100');
                renderDemandPlans(payload.data || []);
                status.textContent = 'Demand plans loaded from API.';
            } catch (error) {
                console.error(error);
                status.textContent = 'Unable to load demand plans from API. Check console for details.';
            }
        }

        async function loadProcurementRequestsFromApi() {
            const status = document.getElementById('procurement-requests-api-status');

            try {
                const payload = await fetchApiJson('/api/v1/procurement-requests?per_page=100');
                renderProcurementRequests(payload.data || []);
                status.textContent = 'Procurement requests loaded from API.';
            } catch (error) {
                console.error(error);
                status.textContent = 'Unable to load procurement requests from API. Check console for details.';
            }
        }

        async function loadSupplierQuotesFromApi() {
            const status = document.getElementById('supplier-quotes-api-status');

            try {
                const payload = await fetchApiJson('/api/v1/supplier-quotes?per_page=100');
                renderSupplierQuotes(payload.data || []);
                status.textContent = 'Supplier quotes loaded from API.';
            } catch (error) {
                console.error(error);
                status.textContent = 'Unable to load supplier quotes from API. Check console for details.';
            }
        }

        async function loadItemsAndSuppliersForForm() {
            const itemSelectIds = ['request-item-select'];
            const supplierSelectIds = ['request-supplier-select'];

            try {
                const [itemsPayload, suppliersPayload] = await Promise.all([
                    fetchApiJson('/api/v1/inventory-items?per_page=100'),
                    fetchApiJson('/api/v1/suppliers?per_page=100')
                ]);

                const items = itemsPayload.data || [];
                const suppliers = suppliersPayload.data || [];

                const itemOptions = ['<option value="">Select item</option>', ...items.map(item => `<option value="${item.id}">${item.name} (${item.sku || ''})</option>`)];
                const supplierOptions = ['<option value="">Select supplier</option>', ...suppliers.map(supplier => `<option value="${supplier.id}">${supplier.name}</option>`)];

                itemSelectIds.forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        select.innerHTML = itemOptions.join('');
                    }
                });

                supplierSelectIds.forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        select.innerHTML = supplierOptions.join('');
                    }
                });
            } catch (error) {
                console.error('Unable to load form select options from API.', error);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadDemandPlansFromApi();
            loadProcurementRequestsFromApi();
            loadSupplierQuotesFromApi();
            loadItemsAndSuppliersForForm();
        });
    </script>
</x-app-layout>
