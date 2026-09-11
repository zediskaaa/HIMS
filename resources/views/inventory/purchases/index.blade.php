<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-800">Enterprise S2P / P2P</span>
                    <span class="text-xs text-neutral-500">• ISO 9001 &amp; SOX 404 Compliant Controls</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Procurement &amp; Strategic Sourcing</h2>
                <p class="text-sm text-neutral-600">Enterprise requisition intake, sealed-bid sourcing, landed cost normalization, DOA approvals, and encumbered purchase orders.</p>
            </div>
            @can('generate_forecasts')
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.demand-forecast') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Demand Forecasts
                </a>
            </div>
            @endcan
        </div>
    </x-slot>

    @php
        $defaultTab = 'enterprise_s2p';
        if (!auth()->user()?->canany(['create_requisition', 'manage_sourcing', 'issue_purchase_order', 'manage_procurement'])) {
            $defaultTab = 'orders_revisions';
        }
    @endphp

    <div class="py-6" x-data="{ activeTab: '{{ $defaultTab }}', selectedPoCxml: '', selectedPoNumber: '', showCxmlModal: false }">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Flash Notification Banners --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('info'))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="font-medium">{{ session('info') }}</span>
                    </div>
                </div>
            @endif

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

            {{-- Metric Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Committed Encumbrance</p>
                        <span class="rounded-full bg-emerald-50 p-1.5 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">₱{{ number_format($purchaseOrders->sum('total_encumbered_amount'), 2) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Across {{ $purchaseOrders->count() }} active orders</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Sourcing RFQs</p>
                        <span class="rounded-full bg-blue-50 p-1.5 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $rfqs->count() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">{{ $rfqs->where('status.value', 'published')->count() }} open for bidding</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Pending DOA Steps</p>
                        <span class="rounded-full bg-amber-50 p-1.5 text-amber-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $approvalChains->where('status', 'pending')->count() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Awaiting executive authorization</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Accredited Suppliers</p>
                        <span class="rounded-full bg-purple-50 p-1.5 text-purple-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $suppliers->count() }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Procurement eligible &amp; verified</p>
                </div>
            </div>

            {{-- Navigation Tabs --}}
            <div class="border-b border-neutral-200">
                <nav class="-mb-px flex space-x-6 overflow-x-auto text-sm font-medium">
                    @canany(['view_procurement_sensitive_data', 'create_requisition', 'manage_sourcing', 'issue_purchase_order', 'manage_procurement'])
                    <button @click="activeTab = 'enterprise_s2p'" :class="activeTab === 'enterprise_s2p' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Enterprise Source-to-Pay Workspace
                    </button>
                    @endcanany
                    @canany(['view_procurement_sensitive_data', 'manage_sourcing', 'evaluate_bids'])
                    <button @click="activeTab = 'sourcing_rfqs'" :class="activeTab === 'sourcing_rfqs' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Sourcing Events &amp; RFQs ({{ $rfqs->count() }})
                    </button>
                    @endcanany
                    @canany(['view_procurement_sensitive_data', 'evaluate_bids', 'award_procurement'])
                    <button @click="activeTab = 'evaluations'" :class="activeTab === 'evaluations' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Comparative Evaluation &amp; Landed Cost Matrix
                    </button>
                    @endcanany
                    @can('approve_purchase_order')
                    <button @click="activeTab = 'doa_approvals'" :class="activeTab === 'doa_approvals' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Delegation of Authority (DOA) Hub
                    </button>
                    @endcan
                    <button @click="activeTab = 'orders_revisions'" :class="activeTab === 'orders_revisions' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Purchase Orders &amp; Revisions
                    </button>
                    @canany(['create_requisition', 'manage_sourcing', 'issue_purchase_order'])
                    <button @click="activeTab = 'legacy_canvass'" :class="activeTab === 'legacy_canvass' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Standard Canvassing (Stages 1-5)
                    </button>
                    @endcanany
                    @can('view_audit_trail')
                    <button @click="activeTab = 'audit_trail'" :class="activeTab === 'audit_trail' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Procurement Audit Trail
                    </button>
                    @endcan
                </nav>
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

                    <form method="POST" action="{{ route('inventory.purchases.enterprise-requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
                        @csrf
                        {{-- Requisition Title --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Requisition Title</label>
                            <input type="text" name="title" placeholder="e.g. ICU PPE &amp; Ventilator Tubing Restock" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        {{-- Cost Center Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Cost Center (Department Budget)</label>
                            <select name="cost_center_id" @change="prCostCenterChanged($event)" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
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
                            <select name="procurement_category_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Procurement Method Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Procurement Method</label>
                            <select name="procurement_method" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
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
                            <select name="priority" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="low">Low (Routine Stock Replenishment)</option>
                                <option value="medium" selected>Medium (Standard 14-Day Cycle)</option>
                                <option value="high">High (Department Critical)</option>
                                <option value="urgent">Urgent (Clinical Stat / Critical Shortage)</option>
                            </select>
                        </div>

                        {{-- Item Master Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Item Master Code</label>
                            <select name="item_id" @change="prItemChanged($event)" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
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

                    <form method="POST" action="{{ route('inventory.purchases.rfqs.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">RFQ Event Title</label>
                            <input type="text" name="title" placeholder="e.g. Competitive Canvass: Syringes &amp; PPE" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Bidding Protocol</label>
                            <select name="bidding_type" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500">
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
                            <select name="item_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500" required>
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
                                                    <form method="POST" action="{{ route('inventory.purchases.rfqs.evaluate', $rfq) }}">
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
                                                <form method="POST" action="{{ route('inventory.purchases.rfqs.evaluate', $rfq) }}">
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
                                                            <form method="POST" action="{{ route('inventory.purchases.rfqs.award', $activeEvaluatedRfq) }}" class="mt-1">
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

                                @if($chain->status === 'pending')
                                    @can('approve_purchase_order')
                                    <div class="mt-4 flex items-center justify-end gap-2 border-t border-neutral-100 pt-3">
                                        <form method="POST" action="{{ route('inventory.purchases.approval-chains.approve', $chain) }}">
                                            @csrf
                                            <button type="submit" class="rounded bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                Authorize Step
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory.purchases.approval-chains.reject', $chain) }}">
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

            {{-- ======================================================== TAB 5: Purchase Orders, Revisions & cXML Payloads --}}
            <div x-show="activeTab === 'orders_revisions'" class="space-y-6">
                @can('issue_purchase_order')
                {{-- Issue Direct Purchase Order Card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
                     x-data="{
                         poQuantity: 100,
                         poUnitCost: 50.00,
                         selectedPoUom: 'units',
                         poItemChanged(event) {
                             const opt = event.target.options[event.target.selectedIndex];
                             if (opt && opt.dataset.cost) {
                                 this.poUnitCost = parseFloat(opt.dataset.cost);
                                 this.selectedPoUom = opt.dataset.uom || 'units';
                             }
                         },
                         get poTotalEncumbered() {
                             const q = parseFloat(this.poQuantity) || 0;
                             const c = parseFloat(this.poUnitCost) || 0;
                             return (q * c).toFixed(2);
                         }
                     }">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-neutral-900">Issue Direct Purchase Order &amp; Encumbrance Contract</h3>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Hard Commitment</span>
                            </div>
                            <p class="text-sm text-neutral-500">Issue legally binding purchase contract, commit departmental funds, and dispatch cXML OrderRequest document.</p>
                        </div>
                        <div class="flex items-center gap-2 self-start sm:self-auto">
                            <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Autogenerated Reference:</span>
                            <span class="rounded bg-neutral-100 border border-neutral-200 px-2.5 py-1 text-xs font-mono font-bold text-neutral-700 shadow-inner">PO-{{ date('Ymd') }}-AUTO</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('inventory.purchases.orders.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
                        @csrf

                        {{-- Accredited Supplier Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Accredited Supplier Counterparty</label>
                            <select name="supplier_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">
                                        {{ $s->name }} (Accreditation: {{ $s->effectiveAccreditationStatus()->value }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cost Center Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Cost Center (Encumbrance)</label>
                            <select name="cost_center_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Cost Center</option>
                                @foreach($costCenters as $cc)
                                    @php $avail = $cc->currentBudget()?->availableBudget() ?? 1000000; @endphp
                                    <option value="{{ $cc->id }}">
                                        {{ $cc->name }} ({{ $cc->code }}) — Avail: ₱{{ number_format($avail, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Item Master Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Item Master Code</label>
                            <select name="item_id" @change="poItemChanged($event)" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Item from Catalog</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-cost="{{ $item->unit_cost }}" data-uom="{{ $item->unit }}" data-sku="{{ $item->sku }}">
                                        {{ $item->name }} ({{ $item->sku }}) — Catalog: ₱{{ number_format($item->unit_cost, 2) }} / {{ $item->unit ?: 'unit' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Quantity (Strict Numbers Only) --}}
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-600">Ordered Quantity</label>
                                <span class="text-xs text-neutral-500 font-medium" x-show="selectedPoUom">Unit: <strong class="text-neutral-800" x-text="selectedPoUom"></strong></span>
                            </div>
                            <input type="number" name="quantity" min="1" step="1" inputmode="numeric" x-model.number="poQuantity"
                                   onkeydown="return ['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key) || /^[0-9]$/.test(event.key)"
                                   class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        {{-- Unit Cost (Strict Numbers Only) --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Contracted Unit Cost (₱)</label>
                            <input type="number" step="0.01" min="0.01" name="unit_cost" inputmode="decimal" x-model.number="poUnitCost"
                                   class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        {{-- Payment Terms Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Payment Terms</label>
                            <select name="payment_terms" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="Net 30" selected>Net 30 (Standard 30 Days)</option>
                                <option value="Net 60">Net 60 (Extended Capital 60 Days)</option>
                                <option value="Net 15">Net 15 (Expedited 15 Days)</option>
                                <option value="COD">COD (Cash on Delivery)</option>
                                <option value="Advance">Advance (100% Pre-payment)</option>
                            </select>
                        </div>

                        {{-- Incoterms Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Incoterms (Delivery Terms)</label>
                            <select name="incoterms" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="DDP" selected>DDP - Delivered Duty Paid (Hospital Receiving Dock)</option>
                                <option value="FOB">FOB - Free On Board (Vendor Origin Port)</option>
                                <option value="CIF">CIF - Cost, Insurance &amp; Freight</option>
                                <option value="EXW">EXW - Ex Works (Factory Floor Pickup)</option>
                            </select>
                        </div>

                        {{-- Status Dropdown --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Initial PO Status</label>
                            <select name="status" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="issued" selected>Issued (Committed &amp; Bound)</option>
                                <option value="dispatched">Dispatched (Direct EDI / cXML Push)</option>
                                <option value="pending">Pending Vendor Confirmation</option>
                            </select>
                        </div>

                        {{-- Delivery Notes --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Delivery Instructions / Notes</label>
                            <input type="text" name="notes" placeholder="e.g. Deliver to Central Receiving Dock, Bldg B" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>

                        {{-- Live Total & Encumbrance Commitment Box --}}
                        <div class="md:col-span-3 rounded-lg border border-neutral-200 bg-neutral-50 p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-sm">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Live Committed Hard Encumbrance:</span>
                                <div class="flex items-baseline gap-2 mt-0.5">
                                    <span class="text-2xl font-black text-emerald-700">₱<span x-text="Number(poTotalEncumbered).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></span>
                                    <span class="text-xs text-neutral-500 font-medium">(<span x-text="poQuantity"></span> <span x-text="selectedPoUom"></span> @ ₱<span x-text="parseFloat(poUnitCost || 0).toFixed(2)"></span>)</span>
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Execute Purchase Order &amp; Dispatch cXML
                            </button>
                        </div>
                    </form>
                </div>
                @endcan

                {{-- Purchase Orders Ledger Table --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Purchase Orders Ledger &amp; Fulfillment ({{ $purchaseOrders->count() }})</h3>
                            <p class="text-sm text-neutral-500">Legally binding encumbrance, revision histories, and B2B cXML OrderRequest payloads.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">cXML / EDI 850 Dispatch</span>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <tr>
                                    <th class="px-3.5 py-3">PO Number &amp; Version</th>
                                    <th class="px-3.5 py-3">Supplier Counterparty</th>
                                    <th class="px-3.5 py-3">Line Items &amp; Qty</th>
                                    <th class="px-3.5 py-3">Cost Center</th>
                                    @can('view_procurement_sensitive_data')
                                    <th class="px-3.5 py-3">Commercial Terms</th>
                                    <th class="px-3.5 py-3">Total Encumbered</th>
                                    @endcan
                                    <th class="px-3.5 py-3">Status</th>
                                    <th class="px-3.5 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($purchaseOrders as $po)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-3.5 py-3">
                                            <p class="font-bold text-neutral-900 font-mono text-xs">{{ $po->po_number }}</p>
                                            <span class="inline-flex rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-mono text-neutral-600">{{ $po->version }}</span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <p class="font-semibold text-neutral-900">{{ $po->supplier?->name }}</p>
                                            @can('view_supplier_sensitive_data')
                                            <p class="text-[11px] text-neutral-500">{{ $po->supplier?->email ?? $po->supplier?->phone }}</p>
                                            @endcan
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($po->lines->isNotEmpty())
                                                <p class="font-medium text-neutral-800">{{ $po->lines->first()->item?->name }}</p>
                                                <p class="text-[11px] text-neutral-500">{{ $po->lines->sum('ordered_quantity') }} {{ $po->lines->first()->item?->unit ?: 'units' }} ({{ $po->lines->count() }} line(s))</p>
                                            @else
                                                <p class="font-medium text-neutral-800">{{ $po->item?->name ?? 'Direct Item' }}</p>
                                                <p class="text-[11px] text-neutral-500">{{ $po->quantity }} {{ $po->item?->unit ?: 'units' }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($po->costCenter)
                                                <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700">{{ $po->costCenter->code }}</span>
                                            @else
                                                <span class="text-xs text-neutral-400">General Fund</span>
                                            @endif
                                        </td>
                                        @can('view_procurement_sensitive_data')
                                        <td class="px-3.5 py-3">
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] font-mono text-neutral-700">{{ $po->payment_terms ?: 'Net 30' }}</span>
                                            <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-mono text-blue-700">{{ $po->incoterms ?: 'DDP' }}</span>
                                        </td>
                                        <td class="px-3.5 py-3 font-bold text-neutral-900">₱{{ number_format($po->total_amount, 2) }}</td>
                                        @endcan
                                        <td class="px-3.5 py-3">
                                            @php
                                                $poStatusClasses = match($po->status) {
                                                    'received', 'fulfilled' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'dispatched' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'issued' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    default => 'bg-neutral-100 text-neutral-700 border-neutral-200',
                                                };
                                            @endphp
                                            <span class="rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-wider {{ $poStatusClasses }}">
                                                {{ $po->status }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <div class="flex items-center gap-2">
                                                @can('receive_purchase_order')
                                                    @if($po->status !== 'received' && $po->status !== 'fulfilled')
                                                        <form method="POST" action="{{ route('inventory.purchases.receive', $po) }}" class="inline"
                                                              data-confirm-title="Receive Purchase Order"
                                                              data-confirm-message="Are you sure you want to receive this delivery into stock?"
                                                              data-confirm-label="Receive Delivery">
                                                            @csrf
                                                            <button type="submit" class="rounded bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-700 shadow-sm">
                                                                Receive
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-xs text-emerald-700 font-semibold inline-flex items-center gap-1">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                            Stock Posted
                                                        </span>
                                                    @endif
                                                @else
                                                    @if($po->status === 'received' || $po->status === 'fulfilled')
                                                        <span class="text-xs text-emerald-700 font-semibold inline-flex items-center gap-1">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                            Stock Posted
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-neutral-400 italic">Pending Delivery</span>
                                                    @endif
                                                @endcan

                                                @if($po->cxml_payload)
                                                    <button type="button"
                                                            @click="selectedPoCxml = {{ json_encode($po->cxml_payload) }}; selectedPoNumber = '{{ $po->po_number }}'; showCxmlModal = true;"
                                                            class="rounded border border-neutral-200 bg-neutral-50 px-2 py-1 text-xs font-mono text-neutral-600 hover:bg-neutral-100">
                                                        cXML
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3.5 py-6 text-center text-sm text-neutral-500">No purchase orders issued yet. Issue one above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ======================================================== TAB 6: Standard Canvassing (Stages 1-5 Preserved) --}}
            <div x-show="activeTab === 'legacy_canvass'" class="space-y-6">
                {{-- Stage 1 --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stage 1 • Planning &amp; Demand Forecasting</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">
                        Forecasts are now calculated from consumption history — average daily usage, safety stock,
                        reorder point and a suggested order quantity per item — rather than entered by hand.
                    </p>
                    <a href="{{ route('inventory.demand-forecast') }}"
                       class="mt-4 inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">
                        Open Demand Forecasting
                    </a>
                </div>

                {{-- Demand plans list --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Demand plans</h3>
                    <div id="demand-plans-list" class="mt-4 space-y-2">
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading demand plans from API...</p>
                    </div>
                    <x-ui.loader id="demand-plans-api-status" size="sm" label="Loading demand plans from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>

                @can('create_requisition')
                {{-- Stage 2 • Procurement request --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stage 2 • Procurement request</h3>
                    <form method="POST" action="{{ route('inventory.purchases.requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Title</label>
                            <input type="text" name="title" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Priority</label>
                            <select name="priority" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Item</label>
                            <select id="request-item-select" name="item_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                                <option value="">Select item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Requested quantity</label>
                            <input type="number" name="requested_quantity" min="1" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Description</label>
                            <textarea name="description" rows="3" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Preferred supplier</label>
                            <select id="request-supplier-select" name="supplier_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Evaluation status</label>
                            <select name="evaluation_status" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Evaluation score</label>
                            <input type="number" step="0.01" name="evaluation_score" min="0" max="100" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Approved by</label>
                            <input type="text" name="approved_by" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Approval notes</label>
                            <textarea name="approval_notes" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Create request</button>
                        </div>
                    </form>
                </div>
                @endcan

                {{-- Procurement requests API-fed list --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Procurement requests</h3>
                    <div id="procurement-requests-list" class="mt-4 space-y-3">
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading procurement requests from API...</p>
                    </div>
                    <x-ui.loader id="procurement-requests-api-status" size="sm" label="Loading procurement requests from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>

                @can('manage_sourcing')
                {{-- Stage 3 • Supplier quotation --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stage 3 &bull; Supplier quotation</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">
                        Record what each vendor quoted against a request. Canvass several suppliers on the
                        same request, then approve the request naming the one you picked.
                    </p>

                    @if ($requests->isEmpty())
                        <p class="mt-4 rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">
                            Create a procurement request first — a quote has to attach to one.
                        </p>
                    @else
                        <form method="POST" action="{{ route('inventory.purchases.quotes.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                            @csrf
                            <div>
                                <label for="quote-request" class="mb-1 block text-sm font-medium text-[var(--text)]">Procurement request</label>
                                <select id="quote-request" name="procurement_request_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
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
                            <div>
                                <label for="quote-supplier" class="mb-1 block text-sm font-medium text-[var(--text)]">Supplier</label>
                                <select id="quote-supplier" name="supplier_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                                    <option value="">Select supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--text)]">Quoted price (₱)</label>
                                <input type="number" step="0.01" name="quoted_price" value="{{ old('quoted_price') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                                <x-input-error :messages="$errors->get('quoted_price')" class="mt-1" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--text)]">Notes</label>
                                <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Record quote</button>
                            </div>
                        </form>
                    @endif
                </div>
                @endcan

                {{-- Supplier quotations API list --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Supplier quotations</h3>
                    <div id="supplier-quotes-list" class="mt-4 space-y-3">
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading supplier quotes from API...</p>
                    </div>
                    <x-ui.loader id="supplier-quotes-api-status" size="sm" label="Loading supplier quotes from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>

                @can('issue_purchase_order')
                {{-- Stage 4: Purchase order --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm"
                     x-data="{
                         s4Qty: 100,
                         s4Cost: 50.00,
                         get s4Total() {
                             return ((parseFloat(this.s4Qty) || 0) * (parseFloat(this.s4Cost) || 0)).toFixed(2);
                         }
                     }">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                        <h3 class="text-lg font-semibold text-[var(--text)]">Stage 4 • Purchase order</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-[var(--muted)]">Autogenerated Reference:</span>
                            <span class="rounded bg-neutral-100 border border-neutral-200 px-2 py-0.5 text-xs font-mono font-bold text-neutral-700">PO-{{ date('Ymd') }}-AUTO</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('inventory.purchases.orders.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Supplier</label>
                            <select id="po-supplier-select" name="supplier_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Cost Center</label>
                            <select name="cost_center_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="">Select cost center</option>
                                @foreach($costCenters as $cc)
                                    <option value="{{ $cc->id }}">{{ $cc->name }} ({{ $cc->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Item</label>
                            <select id="po-item-select" name="item_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                                <option value="">Select item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Quantity</label>
                            <input type="number" name="quantity" min="1" step="1" inputmode="numeric" x-model.number="s4Qty"
                                   onkeydown="return ['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key) || /^[0-9]$/.test(event.key)"
                                   class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Unit cost (₱)</label>
                            <input type="number" step="0.01" min="0.01" name="unit_cost" inputmode="decimal" x-model.number="s4Cost"
                                   class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Payment Terms</label>
                            <select name="payment_terms" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="Net 30" selected>Net 30 (30 Days)</option>
                                <option value="Net 60">Net 60 (60 Days)</option>
                                <option value="Net 15">Net 15 (15 Days)</option>
                                <option value="COD">COD (Cash on Delivery)</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Incoterms</label>
                            <select name="incoterms" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="DDP" selected>DDP - Delivered Duty Paid</option>
                                <option value="FOB">FOB - Free on Board</option>
                                <option value="CIF">CIF - Cost, Insurance &amp; Freight</option>
                                <option value="EXW">EXW - Ex Works</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Notes</label>
                            <input type="text" name="notes" placeholder="Delivery dock instructions..." class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div class="md:col-span-2 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-[var(--muted)]">Computed Total Encumbrance:</span>
                                <p class="text-lg font-bold text-[var(--text)]">₱<span x-text="Number(s4Total).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></p>
                            </div>
                            <button type="submit" class="rounded-xl bg-[var(--primary)] px-5 py-2 font-semibold text-white shadow hover:opacity-90">
                                Create purchase order
                            </button>
                        </div>
                    </form>
                </div>
                @endcan

                {{-- Purchase orders table --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Purchase orders</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-[var(--border)] text-[var(--muted)]">
                                    <th class="px-3 py-2">PO number</th>
                                    <th class="px-3 py-2">Supplier</th>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2">Quantity</th>
                                    @can('view_procurement_sensitive_data')
                                    <th class="px-3 py-2">Unit cost</th>
                                    @endcan
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody id="purchase-orders-table-body" class="divide-y divide-[var(--border)]">
                                <tr>
                                    <td colspan="{{ auth()->user()?->can('view_procurement_sensitive_data') ? 7 : 6 }}" class="px-3 py-4 text-[var(--muted)]">Loading purchase orders from API...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <x-ui.loader id="purchase-orders-api-status" size="sm" label="Loading purchase orders from API..." class="mt-3 text-sm text-[var(--muted)]" />
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
            <div x-show="showCxmlModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
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

        function renderReceiveForm(order) {
            if (order.status === 'received') {
                return '<span class="text-sm text-[var(--muted)]">Received</span>';
            }

            @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                return `
                    <form method="POST" action="/inventory/purchases/${order.id}/receive"
                          data-confirm-title="Confirm purchase receipt"
                          data-confirm-message="Are you sure you want to receive this purchase order? This will add the ordered stock to inventory."
                          data-confirm-label="Receive Order">
                        <input type="hidden" name="_token" value="${csrfToken()}">
                        <button type="submit" class="rounded-lg bg-[var(--primary)] px-3 py-1.5 text-xs font-semibold text-white">
                            Receive
                        </button>
                    </form>
                `;
            @else
                return '<span class="text-sm text-[var(--muted)]">Awaiting delivery</span>';
            @endcan
        }

        function renderOrderStatus(status) {
            const tone = status === 'received'
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-[var(--primary-light)] text-[var(--primary)]';

            return `<span class="rounded-full ${tone} px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em]">${escapeHtml(status)}</span>`;
        }

        async function loadPurchaseOrdersFromApi() {
            const status = document.getElementById('purchase-orders-api-status');
            const tbody = document.getElementById('purchase-orders-table-body');

            try {
                const payload = await fetchApiJson('/api/v1/purchase-orders');
                const items = payload.data || [];

                if (items.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="{{ auth()->user()?->can('view_procurement_sensitive_data') ? 7 : 6 }}" class="px-3 py-4 text-[var(--muted)]">No purchase orders found via API.</td></tr>`;
                } else {
                    tbody.innerHTML = items.map(order => `
                        <tr>
                            <td class="px-3 py-2 font-medium text-[var(--text)]">${escapeHtml(order.po_number)}</td>
                            <td class="px-3 py-2">${order.supplier ? escapeHtml(order.supplier.name) : '-'}</td>
                            <td class="px-3 py-2">${order.item ? escapeHtml(order.item.name) : '-'}</td>
                            <td class="px-3 py-2">${order.quantity}</td>
                            @can('view_procurement_sensitive_data')
                            <td class="px-3 py-2">${formatCurrency(order.unit_cost)}</td>
                            @endcan
                            <td class="px-3 py-2">${renderOrderStatus(order.status)}</td>
                            <td class="px-3 py-2">${renderReceiveForm(order)}</td>
                        </tr>
                    `).join('');
                }

                status.textContent = 'Purchase orders loaded from API.';
            } catch (error) {
                console.error(error);
                status.textContent = 'Unable to load purchase orders from API. Check console for details.';
            }
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
            const itemSelectIds = ['request-item-select', 'po-item-select'];
            const supplierSelectIds = ['request-supplier-select', 'po-supplier-select'];

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
            loadPurchaseOrdersFromApi();
            loadDemandPlansFromApi();
            loadProcurementRequestsFromApi();
            loadSupplierQuotesFromApi();
            loadItemsAndSuppliersForForm();
        });
    </script>
</x-app-layout>
