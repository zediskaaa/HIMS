<x-app-layout>
    <div class="space-y-6" x-data="{
        newRequisitionModal: {{ ($errors->any() || $preselectedItem) ? 'true' : 'false' }},
        itemsList: {{ Js::from($items) }},
        preselectedItem: {{ Js::from($preselectedItem) }},
        isContextLocked: {{ ($preselectedItem && (!old('lines') || old('context_item_id') || old('lines.0.item_id') == $preselectedItem?->id)) ? 'true' : 'false' }},
        lines: {{ Js::from(old('lines', [
            $preselectedItem ? [
                'item_id' => (string) $preselectedItem->id,
                'requested_quantity' => max(1, (int) ($preselectedItem->reorder_level - $preselectedItem->quantity_on_hand)),
                'allocation_strategy' => 'FEFO',
                'notes' => 'Restock replenishment for ' . $preselectedItem->name,
                'max_atp' => (int) ($preselectedItem->quantity_on_hand ?? 0)
            ] : [
                'item_id' => '',
                'requested_quantity' => 1,
                'allocation_strategy' => 'FEFO',
                'notes' => '',
                'max_atp' => 0
            ]
        ])) }},
        addLine() {
            this.lines.push({ item_id: '', requested_quantity: 1, allocation_strategy: 'FEFO', notes: '', max_atp: 0 });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },
        unlockContext() {
            this.isContextLocked = false;
        },
        updateItemAtp(line, itemId) {
            const item = this.itemsList.find(i => i.id == itemId);
            if (item) {
                line.max_atp = item.quantity_on_hand ?? 0;
            } else {
                line.max_atp = 0;
            }
        }
    }"
    @open-new-requisition-modal.window="newRequisitionModal = true"
    @keydown.escape.window="newRequisitionModal = false"
    >
        {{-- Header with Action Button --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-neutral-200 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-800">Store Requisitions</span>
                    <span class="text-xs text-neutral-500">• Department Issuance &amp; ATP Reservation Protocol</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">Department Material Requisitions</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" :href="route('inventory.items')" icon="arrow-left">Back to Inventory</x-ui.button>
                @can(\App\Enums\Permission::CreateRequisition->value)
                    <button type="button"
                            @click="newRequisitionModal = true"
                            id="btn-new-requisition"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 active:bg-primary-800 transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Store Requisition
                    </button>
                @endcan
            </div>
        </div>

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

            {{-- Flash Alerts --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <div class="rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-900 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <div>
                        <p class="font-semibold">Who can approve a Store Requisition?</p>
                        <p class="mt-1 text-xs leading-5 text-primary-800">
                            Users with any of these active roles may approve: {{ implode(', ', $approverRoleLabels) }}. They cannot approve a requisition they created. Approval is not pre-assigned to one person; authorized independent reviewers receive the Approve action for pending requests.
                        </p>
                        @can(\App\Enums\Permission::ApproveRequisition->value)
                            <p class="mt-2 text-xs font-semibold text-emerald-700">Your current role has approval access. You can approve another user's pending requisition from the Registry or its Details page.</p>
                        @else
                            <p class="mt-2 text-xs font-semibold text-neutral-700">Your current role can view or submit requisitions, but it cannot approve them.</p>
                        @endcan
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Requisition Submission Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Metric Cards --}}
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Total Requisitions</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $requisitionMetrics['total'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Department orders tracked</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Pending Review</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600">
                        {{ $requisitionMetrics['pending'] }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">Awaiting supervisor approval</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Approved / Reserved</p>
                    <p class="mt-2 text-2xl font-bold text-primary-600">
                        {{ $requisitionMetrics['approved'] }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">ATP stock reserved, ready to pick</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Segregation of Duties</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">Enforced</p>
                    <p class="mt-1 text-xs text-neutral-500">Self-approval strictly blocked</p>
                </div>
            </div>

            {{-- Requisitions Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Requisitions Registry</h3>
                        <p class="text-xs text-neutral-500">Track demand status, reservation holds, and FEFO picking fulfillment.</p>
                    </div>
                </div>
                <div class="overflow-x-auto hims-table-scroll">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Requisition #</th>
                                <th class="px-6 py-3 font-medium">Department &amp; Cost Center</th>
                                <th class="px-6 py-3 font-medium">Requester</th>
                                <th class="px-6 py-3 font-medium">Approval / Decision</th>
                                <th class="px-6 py-3 font-medium">Urgency</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Date</th>
                                <th class="px-6 py-3 font-medium text-right hims-sticky-actions min-w-[200px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($requisitions as $req)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('inventory.requisitions.show', $req) }}" class="font-bold text-primary-600 hover:underline">
                                            {{ $req->requisition_number }}
                                        </a>
                                        <p class="text-xs text-neutral-500">{{ $req->lines->count() }} line item(s)</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $req->department }}</p>
                                        <p class="text-xs text-neutral-500">{{ $req->costCenter->name ?? 'Default Cost Center' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <p class="font-medium text-neutral-800">{{ $req->requestingUser->name ?? 'System' }}</p>
                                        <p class="text-neutral-500">{{ $req->requestingUser->email ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        @if($req->status === 'approved' && $req->approvedBy)
                                            <p class="font-medium text-emerald-700">Approved by {{ $req->approvedBy->name }}</p>
                                            <p class="text-neutral-500">{{ $req->approvedBy->role->label() }}</p>
                                        @elseif($req->status === 'rejected' && $req->approvedBy)
                                            <p class="font-medium text-rose-700">Rejected by {{ $req->approvedBy->name }}</p>
                                            <p class="text-neutral-500">{{ $req->approvedBy->role->label() }}</p>
                                        @elseif(in_array($req->status, ['submitted', 'pending_approval'], true))
                                            <p class="font-medium text-amber-700">Pending independent review</p>
                                            <p class="text-neutral-500">Not assigned to one approver</p>
                                        @else
                                            <span class="text-neutral-400">No approval action pending</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($req->urgency === 'stat_emergency')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-800 animate-pulse">
                                                STAT Emergency
                                            </span>
                                        @elseif($req->urgency === 'urgent')
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                Urgent
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                                Routine
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(in_array($req->status, ['submitted', 'pending_approval'], true))
                                            <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 border border-amber-200">
                                                Awaiting Approval
                                            </span>
                                        @elseif($req->status === 'approved')
                                            <span class="inline-flex items-center rounded bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-800 border border-primary-200">
                                                Approved (ATP Reserved)
                                            </span>
                                        @elseif($req->status === 'picking')
                                            <span class="inline-flex items-center rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-800 border border-blue-200">
                                                Picking in Progress
                                            </span>
                                        @elseif($req->status === 'issued')
                                            <span class="inline-flex items-center rounded bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800 border border-indigo-200">
                                                Issued (Pending Handover)
                                            </span>
                                        @elseif($req->status === 'acknowledged')
                                            <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 border border-emerald-200">
                                                Fulfilled &amp; Acknowledged
                                            </span>
                                        @elseif($req->status === 'rejected')
                                            <span class="inline-flex items-center rounded bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-800 border border-rose-200">
                                                Rejected
                                            </span>
                                        @elseif($req->status === 'cancelled')
                                            <span class="inline-flex items-center rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 border border-neutral-300">
                                                Cancelled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-800 border border-neutral-200">
                                                {{ ucfirst($req->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-neutral-500">
                                        {{ $req->created_at ? $req->created_at->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-right hims-sticky-actions min-w-[200px]">
                                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                            <a href="{{ route('inventory.requisitions.show', $req) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 transition">
                                                View Details
                                            </a>
                                            @if(in_array($req->status, ['submitted', 'pending_approval'], true) && auth()->user()->can(\App\Enums\Permission::ApproveRequisition->value) && auth()->id() !== $req->requesting_user_id)
                                                <form action="{{ route('inventory.requisitions.approve', $req) }}" method="POST" class="inline"
                                                      data-confirm-title="Approve Store Requisition"
                                                      data-confirm-message="Approve Requisition #{{ $req->requisition_number }} and place a hard reservation on available stock?"
                                                      data-confirm-label="Approve &amp; Reserve">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition shadow-xs">
                                                        Approve
                                                    </button>
                                                </form>
                                            @elseif(in_array($req->status, ['submitted', 'pending_approval'], true) && auth()->id() === $req->requesting_user_id)
                                                <span class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-800" title="A different authorized user must approve this requisition.">
                                                    Self-approval blocked
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-10 text-center text-sm text-neutral-500">
                                        <div class="max-w-md mx-auto space-y-3">
                                            <p class="text-neutral-700 font-medium">No material store requisitions found.</p>
                                            <p class="text-xs text-neutral-400">Initiate an internal stock request for your department from central inventory storage.</p>
                                            @can(\App\Enums\Permission::CreateRequisition->value)
                                                <button type="button" @click="newRequisitionModal = true" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                    Create First Store Requisition
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($requisitions->hasPages())
                    <div class="border-t border-neutral-200 px-4 py-3 sm:px-5 dark:border-neutral-800">
                        {{ $requisitions->links() }}
                    </div>
                @endif
            </div>

        {{-- NEW STORE REQUISITION MODAL --}}
        @can(\App\Enums\Permission::CreateRequisition->value)
        <div x-show="newRequisitionModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center p-4 sm:p-6 text-center">
                <div class="fixed inset-0 bg-neutral-900/60 dark:bg-black/70 backdrop-blur-xs transition-opacity" @click="newRequisitionModal = false"></div>

                <div class="relative my-auto z-10 w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white dark:bg-neutral-900 text-left shadow-2xl transition-all border border-neutral-200 dark:border-neutral-800">
                    <form action="{{ route('inventory.requisitions.store') }}" method="POST"
                          data-confirm-title="Submit store requisition"
                          data-confirm-message="Are you sure you want to submit this store requisition for approval?"
                          data-confirm-label="Submit Requisition">
                        @csrf
                        @if($preselectedItem)
                            <input type="hidden" name="context_item_id" value="{{ $preselectedItem->id }}">
                        @endif
                        <div class="bg-white dark:bg-neutral-900 px-6 pt-6 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">Create Material Store Requisition</h3>
                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Request clinical and medical supplies from central inventory storage.</p>
                                    </div>
                                </div>
                                <button type="button" @click="newRequisitionModal = false" class="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition" title="Close modal">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            @if($preselectedItem)
                                <div class="mt-4 rounded-xl border border-primary-200 dark:border-primary-900/60 bg-primary-50/80 dark:bg-primary-950/30 p-3 text-xs text-primary-950 dark:text-primary-200 flex items-center justify-between shadow-2xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300 shrink-0">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="font-bold text-primary-950 dark:text-primary-100">Contextual Item: <span class="font-extrabold underline decoration-primary-300 dark:decoration-primary-600 underline-offset-2">{{ $preselectedItem->name }}</span></p>
                                            <p class="text-[11px] text-primary-700 dark:text-primary-300 mt-0.5">
                                                SKU: <strong class="font-mono text-primary-900 dark:text-primary-200">{{ $preselectedItem->sku }}</strong> &bull; 
                                                On Hand: <strong class="tabular-nums text-primary-900 dark:text-primary-200">{{ $preselectedItem->quantity_on_hand }} {{ $preselectedItem->unit }}</strong> &bull; 
                                                Reorder Level: <strong class="tabular-nums text-primary-900 dark:text-primary-200">{{ $preselectedItem->reorder_level }} {{ $preselectedItem->unit }}</strong>
                                                @if ($preselectedItem->defaultLocation)
                                                    &bull; Storage: <strong class="text-primary-900 dark:text-primary-200">{{ $preselectedItem->defaultLocation->name }}</strong>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-primary-200/90 dark:bg-primary-900/80 px-2.5 py-0.5 text-[10px] font-bold text-primary-900 dark:text-primary-200">
                                        Auto-Carried Forward
                                    </span>
                                </div>
                            @endif

                            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Requesting Department <span class="text-rose-500">*</span></label>
                                    <select name="department" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                        <option value="">-- Select Department --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept }}" {{ (auth()->user()->department === $dept) ? 'selected' : '' }}>{{ $dept }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Cost Center</label>
                                    <select name="cost_center_id" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                        <option value="">-- Optional Cost Center --</option>
                                        @foreach($costCenters as $cc)
                                            <option value="{{ $cc->id }}">{{ $cc->code }} &bull; {{ $cc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Urgency Level</label>
                                    <select name="urgency" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                        <option value="routine">Routine</option>
                                        <option value="urgent" {{ ($preselectedItem && (int) $preselectedItem->quantity_on_hand <= 0) ? 'selected' : '' }}>Urgent</option>
                                        <option value="stat_emergency">STAT Emergency</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3.5 grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Required Date</label>
                                    <input type="date" name="required_date" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Clinical Justification</label>
                                    <input type="text" name="justification" value="{{ old('justification', $preselectedItem ? 'Restock replenishment for ' . $preselectedItem->name : '') }}" placeholder="Scheduled surgeries / ward restock"
                                           class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                </div>
                            </div>

                            {{-- Line Items --}}
                            <div class="mt-5 border-t border-neutral-200 dark:border-neutral-800 pt-3.5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">Requested Items</h4>
                                    <button type="button" @click="addLine" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Add Another Item
                                    </button>
                                </div>

                                <div class="space-y-2.5 max-h-60 overflow-y-auto p-0.5">
                                    <template x-for="(line, idx) in lines" :key="idx">
                                        <div class="flex items-center gap-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/40 p-2.5 border border-neutral-200 dark:border-neutral-700">
                                            <div class="flex-1">
                                                <template x-if="idx === 0 && isContextLocked && preselectedItem">
                                                    <div class="flex items-center justify-between rounded-md bg-primary-50/90 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800/60 px-2.5 py-1.5 text-xs">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <span class="inline-flex items-center justify-center rounded bg-primary-200 dark:bg-primary-900/80 px-1.5 py-0.5 text-[10px] font-bold text-primary-800 dark:text-primary-200 shrink-0">
                                                                Preselected
                                                            </span>
                                                            <div class="truncate">
                                                                <span class="font-bold text-neutral-900 dark:text-neutral-100" x-text="preselectedItem.name"></span>
                                                                <span class="text-neutral-600 dark:text-neutral-300 font-mono text-[11px]" x-text="' (' + (preselectedItem.sku || 'No SKU') + ')'"></span>
                                                                <span class="text-neutral-600 dark:text-neutral-300 text-[10px] ml-1" x-text="'· On Hand: ' + (preselectedItem.quantity_on_hand ?? 0) + ' ' + (preselectedItem.unit || 'units')"></span>
                                                            </div>
                                                            <input type="hidden" :name="'lines[' + idx + '][item_id]'" :value="preselectedItem.id">
                                                        </div>
                                                        <button type="button" @click="unlockContext()" class="text-[11px] font-semibold text-primary-700 dark:text-primary-300 hover:underline shrink-0 ml-2" title="Unlock item selector">
                                                            Change Item
                                                        </button>
                                                    </div>
                                                </template>
                                                <template x-if="!(idx === 0 && isContextLocked && preselectedItem)">
                                                    <select :name="'lines[' + idx + '][item_id]'" x-model="line.item_id" @change="updateItemAtp(line, line.item_id)" required
                                                            class="block w-full rounded-md border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                                        <option value="">-- Select Item to Requisition --</option>
                                                        <template x-for="itm in itemsList" :key="itm.id">
                                                            <option :value="itm.id" x-text="itm.name + ' (ATP: ' + itm.quantity_on_hand + ')'" :selected="itm.id == line.item_id"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                            </div>
                                            <div class="w-24">
                                                <input type="number" :name="'lines[' + idx + '][requested_quantity]'" x-model="line.requested_quantity" min="1" required placeholder="Qty"
                                                       class="block w-full rounded-md border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs text-right font-medium">
                                            </div>
                                            <div class="w-32">
                                                <select :name="'lines[' + idx + '][allocation_strategy]'" x-model="line.allocation_strategy"
                                                        class="block w-full rounded-md border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                                    <option value="FEFO">FEFO (Earliest Expiry)</option>
                                                    <option value="FIFO">FIFO (Oldest In)</option>
                                                    <option value="MANUAL">Manual Pick</option>
                                                </select>
                                            </div>
                                            <button type="button" @click="removeLine(idx)" :disabled="lines.length === 1"
                                                    class="rounded p-1 text-neutral-400 hover:text-rose-600 disabled:opacity-30">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="bg-neutral-50 dark:bg-neutral-800/60 px-6 py-3.5 flex items-center justify-end gap-3 border-t border-neutral-200 dark:border-neutral-800">
                            <button type="button" @click="newRequisitionModal = false" class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 transition">
                                Submit Store Requisition
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan
    </div>
</x-app-layout>
