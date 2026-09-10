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
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.demand-forecast') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Demand Forecasts
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ activeTab: 'enterprise_s2p' }">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

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
                    <button @click="activeTab = 'enterprise_s2p'" :class="activeTab === 'enterprise_s2p' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Enterprise Source-to-Pay Workspace
                    </button>
                    <button @click="activeTab = 'sourcing_rfqs'" :class="activeTab === 'sourcing_rfqs' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Sourcing Events &amp; RFQs ({{ $rfqs->count() }})
                    </button>
                    <button @click="activeTab = 'evaluations'" :class="activeTab === 'evaluations' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Comparative Evaluation &amp; Landed Cost Matrix
                    </button>
                    <button @click="activeTab = 'doa_approvals'" :class="activeTab === 'doa_approvals' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Delegation of Authority (DOA) Hub
                    </button>
                    <button @click="activeTab = 'orders_revisions'" :class="activeTab === 'orders_revisions' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Purchase Orders &amp; Revisions
                    </button>
                    <button @click="activeTab = 'legacy_canvass'" :class="activeTab === 'legacy_canvass' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Standard Canvassing (Stages 1-5)
                    </button>
                    <button @click="activeTab = 'audit_trail'" :class="activeTab === 'audit_trail' ? 'border-primary-600 text-primary-600 border-b-2 font-semibold' : 'text-neutral-500 hover:text-neutral-700'" class="whitespace-nowrap py-3 px-1">
                        Procurement Audit Trail
                    </button>
                </nav>
            </div>

            {{-- ======================================================== TAB 1: Enterprise S2P Workspace --}}
            <div x-show="activeTab === 'enterprise_s2p'" class="space-y-6">
                {{-- Department Requisition Intake with Synchronous Budget Soft Commitment --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Requisition Intake &amp; Budget Encumbrance Check</h3>
                            <p class="text-sm text-neutral-500">Multi-line item intake enforcing real-time departmental budget reservation.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Synchronous GL Check</span>
                    </div>

                    <form method="POST" action="{{ route('inventory.purchases.enterprise-requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Requisition Title</label>
                            <input type="text" name="title" placeholder="e.g. ICU PPE &amp; Ventilator Tubing Restock" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Cost Center (Department)</label>
                            <select name="cost_center_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Cost Center</option>
                                @foreach($costCenters as $cc)
                                    <option value="{{ $cc->id }}">{{ $cc->name }} ({{ $cc->code }}) — Available: ₱{{ number_format($cc->currentBudget()?->availableBudget() ?? 1000000, 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Priority Level</label>
                            <select name="priority" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="low">Low (Routine)</option>
                                <option value="medium" selected>Medium (Standard)</option>
                                <option value="high">High (Priority)</option>
                                <option value="urgent">Urgent (Clinical Stat)</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Item Master Code</label>
                            <select name="item_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="">Select Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }}) — Current Unit Cost: ₱{{ number_format($item->unit_cost, 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Requested Quantity</label>
                            <input type="number" name="quantity" min="1" value="100" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Estimated Unit Price (₱)</label>
                            <input type="number" step="0.01" name="estimated_unit_price" value="45.00" min="0" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" required />
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Clinical Justification &amp; Notes</label>
                            <input type="text" name="description" placeholder="Explain patient care need, reorder trigger, or clinical protocol rationale..." class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-600">Target Need-By Date</label>
                            <input type="date" name="need_by_date" value="{{ now()->addDays(14)->toDateString() }}" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>

                        <div class="md:col-span-3 flex justify-end gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-primary-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Submit PR with Budget Soft Encumbrance
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Enterprise Requisitions Table --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-neutral-900">Active Purchase Requests ({{ $enterpriseRequests->count() }})</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <tr>
                                    <th class="px-3.5 py-3">PR Number</th>
                                    <th class="px-3.5 py-3">Title &amp; Requester</th>
                                    <th class="px-3.5 py-3">Cost Center</th>
                                    <th class="px-3.5 py-3">Items / Lines</th>
                                    <th class="px-3.5 py-3">Total Est. (₱)</th>
                                    <th class="px-3.5 py-3">Encumbrance Status</th>
                                    <th class="px-3.5 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($enterpriseRequests as $pr)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-3.5 py-3 font-semibold text-neutral-900">{{ $pr->pr_number }}</td>
                                        <td class="px-3.5 py-3">
                                            <p class="font-medium text-neutral-900">{{ $pr->title }}</p>
                                            <p class="text-xs text-neutral-500">{{ $pr->requester?->name }} • {{ $pr->submitted_at?->diffForHumans() }}</p>
                                        </td>
                                        <td class="px-3.5 py-3">{{ $pr->costCenter?->name }}</td>
                                        <td class="px-3.5 py-3">{{ $pr->lines->count() }} item(s)</td>
                                        <td class="px-3.5 py-3 font-bold text-neutral-900">₱{{ number_format($pr->total_estimated_amount, 2) }}</td>
                                        <td class="px-3.5 py-3">
                                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 uppercase tracking-wider">
                                                {{ $pr->status->value }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($pr->status->value === 'approved' || $pr->status->value === 'pending_approval')
                                                <button @click="activeTab = 'sourcing_rfqs'" class="text-xs font-semibold text-primary-600 hover:underline">
                                                    Package into RFQ &rarr;
                                                </button>
                                            @else
                                                <span class="text-xs text-neutral-400">Processed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3.5 py-6 text-center text-sm text-neutral-500">No enterprise purchase requests created yet. Submit one above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ======================================================== TAB 2: Sourcing Events & RFQs --}}
            <div x-show="activeTab === 'sourcing_rfqs'" class="space-y-6">
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
                                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 uppercase tracking-wider">
                                                {{ $rfq->status->value }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($rfq->quotes->isNotEmpty())
                                                <form method="POST" action="{{ route('inventory.purchases.rfqs.evaluate', $rfq) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded bg-neutral-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
                                                        Run Evaluation Matrix
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-neutral-400">Awaiting Quotes</span>
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

            {{-- ======================================================== TAB 3: Comparative Evaluation & Landed Cost Matrix --}}
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
                                                            <form method="POST" action="{{ route('inventory.purchases.rfqs.award', $activeEvaluatedRfq) }}" class="mt-1">
                                                                @csrf
                                                                <input type="hidden" name="supplier_quote_id" value="{{ $eval->supplier_quote_id }}">
                                                                <button type="submit" class="rounded bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow hover:bg-emerald-700">
                                                                    Award &amp; Initiate DOA
                                                                </button>
                                                            </form>
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

            {{-- ======================================================== TAB 4: Delegation of Authority (DOA) Hub --}}
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
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-neutral-500 text-center py-6">No approval chains active.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ======================================================== TAB 5: Purchase Orders, Revisions & cXML Payloads --}}
            <div x-show="activeTab === 'orders_revisions'" class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Purchase Orders &amp; Change Order Management</h3>
                            <p class="text-sm text-neutral-500">Legally binding encumbrance, formal revision diffs (PO-REV2), and B2B cXML OrderRequest payloads.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">cXML / EDI 850 Dispatch</span>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <tr>
                                    <th class="px-3.5 py-3">PO Number &amp; Version</th>
                                    <th class="px-3.5 py-3">Supplier Counterparty</th>
                                    <th class="px-3.5 py-3">Line Items</th>
                                    <th class="px-3.5 py-3">Total Encumbered</th>
                                    <th class="px-3.5 py-3">Status</th>
                                    <th class="px-3.5 py-3">Change Orders (Diffs)</th>
                                    <th class="px-3.5 py-3">Receiving &amp; Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @forelse($purchaseOrders as $po)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-3.5 py-3">
                                            <p class="font-bold text-neutral-900">{{ $po->po_number }}</p>
                                            <span class="inline-flex rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-mono text-neutral-600">{{ $po->version }}</span>
                                        </td>
                                        <td class="px-3.5 py-3">{{ $po->supplier?->name }}</td>
                                        <td class="px-3.5 py-3">
                                            @if($po->lines->isNotEmpty())
                                                {{ $po->lines->count() }} line(s)
                                            @else
                                                {{ $po->item?->name }} ({{ $po->quantity }})
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3 font-bold text-neutral-900">₱{{ number_format($po->total_amount, 2) }}</td>
                                        <td class="px-3.5 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wider {{ $po->status === 'received' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">
                                                {{ $po->status }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <span class="text-xs text-neutral-500">{{ $po->revisions->count() }} revisions</span>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            @if($po->status !== 'received')
                                                <form method="POST" action="{{ route('inventory.purchases.receive', $po) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="rounded bg-primary-600 px-3 py-1 text-xs font-semibold text-white hover:bg-primary-700">
                                                        Receive into Stock
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-emerald-700 font-semibold">Stock In Posted</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3.5 py-6 text-center text-sm text-neutral-500">No purchase orders issued yet.</td>
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

                {{-- Procurement requests API-fed list --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Procurement requests</h3>
                    <div id="procurement-requests-list" class="mt-4 space-y-3">
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading procurement requests from API...</p>
                    </div>
                    <x-ui.loader id="procurement-requests-api-status" size="sm" label="Loading procurement requests from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>

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

                {{-- Supplier quotations API list --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Supplier quotations</h3>
                    <div id="supplier-quotes-list" class="mt-4 space-y-3">
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading supplier quotes from API...</p>
                    </div>
                    <x-ui.loader id="supplier-quotes-api-status" size="sm" label="Loading supplier quotes from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>

                {{-- Stage 4: Purchase order --}}
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stage 4 • Purchase order</h3>
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
                            <input type="number" name="quantity" min="1" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Unit cost</label>
                            <input type="number" step="0.01" name="unit_cost" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-[var(--text)]">Notes</label>
                            <textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Create purchase order</button>
                        </div>
                    </form>
                </div>

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
                                    <th class="px-3 py-2">Unit cost</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody id="purchase-orders-table-body" class="divide-y divide-[var(--border)]">
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-[var(--muted)]">Loading purchase orders from API...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <x-ui.loader id="purchase-orders-api-status" size="sm" label="Loading purchase orders from API..." class="mt-3 text-sm text-[var(--muted)]" />
                </div>
            </div>

            {{-- ======================================================== TAB 7: Procurement Audit Trail --}}
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

            @can(\App\Enums\Permission::ManageProcurement->value)
                return `
                    <form method="POST" action="/inventory/purchases/requests/${request.id}/approve" class="inline">
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

            @can(\App\Enums\Permission::RecordMovements->value)
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
                    tbody.innerHTML = `<tr><td colspan="7" class="px-3 py-4 text-[var(--muted)]">No purchase orders found via API.</td></tr>`;
                } else {
                    tbody.innerHTML = items.map(order => `
                        <tr>
                            <td class="px-3 py-2 font-medium text-[var(--text)]">${escapeHtml(order.po_number)}</td>
                            <td class="px-3 py-2">${order.supplier ? escapeHtml(order.supplier.name) : '-'}</td>
                            <td class="px-3 py-2">${order.item ? escapeHtml(order.item.name) : '-'}</td>
                            <td class="px-3 py-2">${order.quantity}</td>
                            <td class="px-3 py-2">${formatCurrency(order.unit_cost)}</td>
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
