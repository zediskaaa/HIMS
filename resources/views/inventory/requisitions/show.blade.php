<x-app-layout>
    <div class="py-6" x-data="{
        issueModalOpen: false,
        ackModalOpen: false,
        rejectModalOpen: false,
        cancelModalOpen: false
    }"
    @open-issue-modal.window="issueModalOpen = true"
    @open-ack-modal.window="ackModalOpen = true"
    @open-reject-modal.window="rejectModalOpen = true"
    @open-cancel-modal.window="cancelModalOpen = true"
    @keydown.escape.window="issueModalOpen = false; ackModalOpen = false; rejectModalOpen = false; cancelModalOpen = false;"
    >
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Header with Action Buttons --}}
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-neutral-200 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('inventory.requisitions.index') }}" class="text-xs font-semibold text-primary-600 hover:underline">
                            &larr; Requisitions Registry
                        </a>
                        <span class="text-xs text-neutral-400">/</span>
                        <span class="text-xs text-neutral-500">{{ $requisition->requisition_number }}</span>
                    </div>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">
                        Store Requisition: {{ $requisition->requisition_number }}
                    </h2>
                    <p class="text-sm text-neutral-600">
                        Department supply request, algorithmic FEFO pick list, and custody handover protocol.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Approve & Reject Actions --}}
                    @if(in_array($requisition->status, ['submitted', 'pending_approval'], true) && auth()->user()->can(\App\Enums\Permission::ApproveRequisition->value) && auth()->id() !== $requisition->requesting_user_id)
                        <form action="{{ route('inventory.requisitions.approve', $requisition) }}" method="POST"
                              data-confirm-title="Approve Store Requisition"
                              data-confirm-message="Approve Requisition #{{ $requisition->requisition_number }} and place a hard reservation on available stock?"
                              data-confirm-label="Approve &amp; Reserve">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Approve &amp; Reserve Stock
                            </button>
                        </form>

                        <button type="button" @click="rejectModalOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Reject
                        </button>
                    @endif

                    {{-- Cancel Action --}}
                    @if(in_array($requisition->status, ['submitted', 'pending_approval', 'approved'], true) && (auth()->id() === $requisition->requesting_user_id || auth()->user()->can(\App\Enums\Permission::ApproveRequisition->value)))
                        <button type="button" @click="cancelModalOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50 transition">
                            Cancel Requisition
                        </button>
                    @endif

                    {{-- Issue Action Button --}}
                    @if(in_array($requisition->status, ['approved', 'picking'], true) && auth()->user()->can(\App\Enums\Permission::IssueStock->value))
                        <button type="button" @click="issueModalOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            Issue Stock to Department
                        </button>
                    @endif

                    {{-- Acknowledge Action Button --}}
                    @if($requisition->status === 'issued' && auth()->id() === $requisition->requesting_user_id)
                        <button type="button" @click="ackModalOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Acknowledge Custody Handover
                        </button>
                    @endif
                </div>
            </div>

            {{-- Flash Alerts --}}
            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Requisition Error:</span>
                    </div>
                    <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Segregation of Duties Notice --}}
            @if(in_array($requisition->status, ['submitted', 'pending_approval'], true) && auth()->id() === $requisition->requesting_user_id)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 flex items-start gap-3 shadow-sm">
                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-bold">Segregation of Duties Policy Active</p>
                        <p class="text-xs text-amber-700 mt-0.5">
                            You are the original author of this requisition. Self-approval is blocked. Another user with one of these active roles must review it: {{ implode(', ', $approverRoleLabels) }}.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Rejection Notice --}}
            @if($requisition->status === 'rejected')
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 flex items-start gap-3 shadow-sm">
                    <svg class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-bold">Requisition Rejected</p>
                        <p class="text-xs text-rose-700 mt-0.5">
                            Reason: {{ $requisition->rejection_reason ?? 'Administrative / budgetary rejection' }}
                        </p>
                    </div>
                </div>
            @endif

            {{-- Cancellation Notice --}}
            @if($requisition->status === 'cancelled')
                <div class="rounded-xl border border-neutral-300 bg-neutral-100 p-4 text-sm text-neutral-700 flex items-start gap-3 shadow-sm">
                    <svg class="h-5 w-5 text-neutral-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <div>
                        <p class="font-bold">Requisition Cancelled</p>
                        <p class="text-xs text-neutral-600 mt-0.5">
                            This store requisition was cancelled and any allocated inventory holds were released back to available stock.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Requisition Metadata Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Lifecycle Status</p>
                        <div class="mt-2">
                            @if(in_array($requisition->status, ['submitted', 'pending_approval'], true))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Submitted (Pending Approval)
                                </span>
                            @elseif($requisition->status === 'approved')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                                    Approved &amp; Reserved
                                </span>
                            @elseif($requisition->status === 'picking')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    Picking in Progress
                                </span>
                            @elseif($requisition->status === 'issued')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                    Issued (In Transit to Unit)
                                </span>
                            @elseif($requisition->status === 'acknowledged')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Handover Acknowledged
                                </span>
                            @elseif($requisition->status === 'rejected')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    Rejected
                                </span>
                            @elseif($requisition->status === 'cancelled')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-200 px-3 py-1 text-xs font-semibold text-neutral-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-neutral-500"></span>
                                    Cancelled
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800">
                                    {{ ucfirst($requisition->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Department / Unit</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $requisition->department }}</p>
                        <p class="text-xs text-neutral-500">Cost Center: {{ $requisition->costCenter->name ?? 'Default Operating Fund' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Personnel &amp; Governance</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900">Requester: {{ $requisition->requestingUser->name ?? 'System' }}</p>
                        <p class="text-xs text-neutral-500">
                            @if($requisition->status === 'rejected' && $requisition->approvedBy)
                                Decision: Rejected by {{ $requisition->approvedBy->name }} ({{ $requisition->approvedBy->role->label() }})
                            @elseif($requisition->approvedBy)
                                Approver: {{ $requisition->approvedBy->name }} ({{ $requisition->approvedBy->role->label() }})
                            @else
                                Approver: Not assigned — pending an independent {{ implode(', ', $approverRoleLabels) }}
                            @endif
                        </p>
                        @if($requisition->issuedBy)
                            <p class="text-xs text-neutral-500">Issued by: {{ $requisition->issuedBy->name }}</p>
                        @endif
                        @if($requisition->acknowledgedBy)
                            <p class="text-xs text-neutral-500">Received by: {{ $requisition->acknowledgedBy->name }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-500">Urgency &amp; Delivery</p>
                        <p class="mt-1 text-sm font-semibold text-neutral-900 uppercase tracking-wide">
                            {{ $requisition->urgency }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            Required by: {{ $requisition->required_date ? $requisition->required_date->format('M d, Y') : 'Immediate' }}
                        </p>
                    </div>
                </div>

                @if($requisition->justification)
                    <div class="mt-6 border-t border-neutral-100 pt-4">
                        <p class="text-xs font-medium text-neutral-500">Clinical Purpose / Justification:</p>
                        <p class="mt-1 text-sm text-neutral-700">{{ $requisition->justification }}</p>
                    </div>
                @endif
            </div>

            {{-- FEFO Pick List Recommendations --}}
            <div class="rounded-xl border border-primary-200 bg-primary-50/40 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-primary-600 px-2 py-0.5 text-xs font-bold text-white">Algorithm Output</span>
                            <h3 class="text-base font-bold text-neutral-900">Recommended FEFO Pick List (First-Expired, First-Out)</h3>
                        </div>
                        <p class="text-xs text-neutral-600 mt-1">
                            Calculated allocation respecting shelf-life minimization, picking oldest expiry lots first to eliminate pharmaceutical expiration write-offs.
                        </p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-lg border border-primary-200 bg-white shadow-xs">
                    <table class="w-full text-left text-xs text-neutral-600">
                        <thead class="bg-neutral-50 text-neutral-500 uppercase border-b border-neutral-200">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Item</th>
                                <th class="px-4 py-2.5 font-medium">Batch Number</th>
                                <th class="px-4 py-2.5 font-medium">Expiration Date</th>
                                <th class="px-4 py-2.5 font-medium">Pick From Location</th>
                                <th class="px-4 py-2.5 font-medium text-right">Pick Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse(collect($pickList)->flatten(1) as $pick)
                                <tr class="hover:bg-primary-50/20">
                                    <td class="px-4 py-3 font-semibold text-neutral-900">
                                        {{ $pick['item_name'] }}
                                    </td>
                                    <td class="px-4 py-3 font-mono font-medium text-primary-700">
                                        {{ $pick['batch_number'] ?? 'Standard Lot' }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-700">
                                        {{ $pick['expiry_date'] ?? 'Non-expiring' }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-neutral-800">
                                        {{ $pick['location_name'] ?? 'Main Storage' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-600">
                                        {{ number_format($pick['pick_quantity']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-xs text-neutral-500">
                                        No specific batch pick list required or stock is currently unreserved.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Requisition Lines Table --}}
            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Requested Line Items</h3>
                    <p class="text-xs text-neutral-500">Requested vs. fulfilled quantities per item.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">Item Description</th>
                                <th class="px-6 py-3 font-medium">Allocation Rule</th>
                                <th class="px-6 py-3 font-medium text-right">Requested Qty</th>
                                <th class="px-6 py-3 font-medium text-right">Issued Qty</th>
                                <th class="px-6 py-3 font-medium text-right">Unit Price</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @foreach($requisition->lines as $line)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-neutral-900">{{ $line->item->name ?? 'Item #' . $line->inventory_item_id }}</p>
                                        <p class="text-xs text-neutral-500">SKU: {{ $line->item->sku ?? 'N/A' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-neutral-700">
                                        {{ $line->allocation_strategy ?? 'FEFO' }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-neutral-900">
                                        {{ number_format($line->requested_quantity) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold {{ $line->issued_quantity >= $line->requested_quantity ? 'text-emerald-600' : 'text-neutral-500' }}">
                                        {{ number_format($line->issued_quantity) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-neutral-700">
                                        ₱{{ number_format($line->unit_cost ?? $line->item->unit_cost ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($line->issued_quantity >= $line->requested_quantity)
                                            <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                                Fulfilled
                                            </span>
                                        @elseif($line->issued_quantity > 0)
                                            <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                Partially Issued
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">
                                                Pending Pick
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- ISSUE GOODS MODAL --}}
        <div x-show="issueModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="issueModalOpen = false"></div>

                <div class="inline-block w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.requisitions.issue', $requisition) }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Execute Material Store Issuance</h3>
                                    <p class="text-xs text-neutral-500">Atomic deduction from unrestricted physical stock and release of reservation holds.</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-700">Confirm Issuance Quantities</p>
                                @foreach($requisition->lines as $idx => $line)
                                    <div class="flex items-center justify-between rounded-lg bg-neutral-50 p-3 border border-neutral-200">
                                        <div>
                                            <p class="text-sm font-semibold text-neutral-900">{{ $line->item->name }}</p>
                                            <p class="text-xs text-neutral-500">Requested: {{ $line->requested_quantity }} units</p>
                                            <input type="hidden" name="lines[{{ $idx }}][line_id]" value="{{ $line->id }}">
                                        </div>
                                        <div class="w-32">
                                            <input type="number" name="lines[{{ $idx }}][quantity]" min="1" max="{{ $line->requested_quantity }}" value="{{ $line->requested_quantity }}" required
                                                   class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm text-right">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="issueModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                Confirm &amp; Deduct Physical Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- HANDOVER ACKNOWLEDGMENT MODAL --}}
        <div x-show="ackModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="ackModalOpen = false"></div>

                <div class="inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.requisitions.acknowledge', $requisition) }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Acknowledge Physical Custody</h3>
                                    <p class="text-xs text-neutral-500">Record recipient signature and close requisition lifecycle.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Recipient Notes / Remarks</label>
                                <textarea name="notes" rows="3" placeholder="Goods physically verified, intact, and transferred to Ward 3 drug room."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="ackModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                                Acknowledge &amp; Close Requisition
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        {{-- REJECT REQUISITION MODAL --}}
        <div x-show="rejectModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="rejectModalOpen = false"></div>

                <div class="inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.requisitions.reject', $requisition) }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Reject Store Requisition</h3>
                                    <p class="text-xs text-neutral-500">Provide reason for disapproval.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Rejection Reason / Justification</label>
                                <textarea name="rejection_reason" rows="3" required placeholder="Exceeds weekly departmental allocation / alternate formulary item available."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"></textarea>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="rejectModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Dismiss
                            </button>
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">
                                Confirm Rejection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- CANCEL REQUISITION MODAL --}}
        <div x-show="cancelModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity" @click="cancelModalOpen = false"></div>

                <div class="inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                    <form action="{{ route('inventory.requisitions.cancel', $requisition) }}" method="POST">
                        @csrf
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-neutral-900">Cancel Store Requisition</h3>
                                    <p class="text-xs text-neutral-500">Cancelling will release any reserved inventory holds.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">Reason for Cancellation</label>
                                <textarea name="cancellation_reason" rows="3" placeholder="Case cancelled / duplicate request."
                                          class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 text-sm"></textarea>
                            </div>
                        </div>

                        <div class="bg-neutral-50 px-6 py-3 flex items-center justify-end gap-3 border-t border-neutral-200">
                            <button type="button" @click="cancelModalOpen = false" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                Keep Requisition
                            </button>
                            <button type="submit" class="rounded-lg bg-neutral-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-neutral-900">
                                Confirm Cancellation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
