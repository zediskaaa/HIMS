<x-app-layout>
    <div class="space-y-6" x-data="{
        newRequisitionModal: {{ ($errors->any() || $preselectedItem) ? 'true' : 'false' }},
        selectedDepartment: {{ Js::from($selectedDepartment) }},
        departmentCostCenterMap: {{ Js::from($departmentCostCenterMap) }},
        assignedCostCenter() {
            if (!this.selectedDepartment) return null;
            return this.departmentCostCenterMap[this.selectedDepartment] || null;
        },
        itemsList: {{ Js::from($items) }},
        preselectedItem: {{ Js::from($preselectedItem) }},
        aiRecommendation: {{ Js::from($aiRecommendation) }},
        aiSuggestedQuantity: {{ Js::from(($aiRecommendation['available'] ?? false) ? $aiRecommendation['suggested_quantity'] : null) }},
        aiRecommendationsCache: {
            @if($preselectedItem && $aiRecommendation)
                '{{ $preselectedItem->id }}': {{ Js::from($aiRecommendation) }},
            @endif
        },
        isContextLocked: {{ ($preselectedItem && (!old('lines') || old('context_item_id') || old('lines.0.item_id') == $preselectedItem?->id)) ? 'true' : 'false' }},
        lines: {{ Js::from(old('lines') ? collect(old('lines'))->map(function ($l) use ($preselectedItem, $aiRecommendation) {
            $itemId = (string) ($l['item_id'] ?? '');
            $isPreselected = $preselectedItem && (string) $preselectedItem->id === $itemId;
            $aiSuggested = isset($l['ai_suggested_quantity']) && $l['ai_suggested_quantity'] !== ''
                ? (int) $l['ai_suggested_quantity']
                : ($isPreselected && ($aiRecommendation['available'] ?? false) ? (int) $aiRecommendation['suggested_quantity'] : null);
            $justification = $l['clinical_justification'] ?? $l['notes'] ?? ($isPreselected ? ('Restock replenishment for ' . $preselectedItem->name) : '');
            return [
                'item_id' => $itemId,
                'requested_quantity' => (int) ($l['requested_quantity'] ?? 1),
                'ai_suggested_quantity' => $aiSuggested,
                'ai_recommendation' => $isPreselected ? $aiRecommendation : null,
                'loading_ai' => false,
                'allocation_strategy' => $l['allocation_strategy'] ?? 'FEFO',
                'clinical_justification' => $justification,
                'notes' => $justification,
                'max_atp' => 0,
            ];
        })->values()->all() : (
            $preselectedItem ? [
                [
                    'item_id' => (string) $preselectedItem->id,
                    'requested_quantity' => ($aiRecommendation['available'] ?? false && ($aiRecommendation['suggested_quantity'] ?? 0) > 0)
                        ? (int) $aiRecommendation['suggested_quantity']
                        : max(1, (int) ($preselectedItem->reorder_level - $preselectedItem->quantity_on_hand)),
                    'ai_suggested_quantity' => ($aiRecommendation['available'] ?? false && ($aiRecommendation['suggested_quantity'] ?? 0) > 0) ? (int) $aiRecommendation['suggested_quantity'] : null,
                    'ai_recommendation' => $aiRecommendation,
                    'loading_ai' => false,
                    'allocation_strategy' => 'FEFO',
                    'clinical_justification' => 'Restock replenishment for ' . $preselectedItem->name,
                    'notes' => 'Restock replenishment for ' . $preselectedItem->name,
                    'max_atp' => (int) ($preselectedItem->quantity_on_hand ?? 0)
                ]
            ] : []
        )) }},
        itemDrawerOpen: false,
        editingIndex: null,
        drawerForm: {
            item_id: '',
            requested_quantity: 1,
            allocation_strategy: 'FEFO',
            clinical_justification: '',
            ai_suggested_quantity: null,
            ai_recommendation: null,
            loading_ai: false,
            max_atp: 0,
            error: ''
        },
        clientValidationError: '',

        getItemName(itemId) {
            if (!itemId) return 'Unknown Item';
            if (this.preselectedItem && this.preselectedItem.id == itemId) return this.preselectedItem.name;
            const item = this.itemsList.find(i => i.id == itemId);
            return item?.name || 'Item #' + itemId;
        },
        getItemSku(itemId) {
            if (!itemId) return 'No SKU';
            if (this.preselectedItem && this.preselectedItem.id == itemId) return this.preselectedItem.sku || 'No SKU';
            const item = this.itemsList.find(i => i.id == itemId);
            return item?.sku || 'No SKU';
        },
        getItemUnit(itemId) {
            if (!itemId) return 'units';
            if (this.preselectedItem && this.preselectedItem.id == itemId) return this.preselectedItem.unit || 'units';
            const item = this.itemsList.find(i => i.id == itemId);
            return item?.unit || 'units';
        },
        getItemAtp(itemId) {
            if (!itemId) return 0;
            if (this.preselectedItem && this.preselectedItem.id == itemId) return this.preselectedItem.quantity_on_hand ?? 0;
            const item = this.itemsList.find(i => i.id == itemId);
            return item?.quantity_on_hand ?? 0;
        },
        openAddItemDrawer() {
            this.editingIndex = null;
            this.drawerForm = {
                item_id: '',
                requested_quantity: 1,
                allocation_strategy: 'FEFO',
                clinical_justification: '',
                ai_suggested_quantity: null,
                ai_recommendation: null,
                loading_ai: false,
                max_atp: 0,
                error: ''
            };
            this.itemDrawerOpen = true;
        },
        openEditItemDrawer(index) {
            this.editingIndex = index;
            const line = this.lines[index];
            if (!line) return;
            this.drawerForm = {
                item_id: line.item_id,
                requested_quantity: line.requested_quantity || 1,
                allocation_strategy: line.allocation_strategy || 'FEFO',
                clinical_justification: line.clinical_justification || line.notes || '',
                ai_suggested_quantity: line.ai_suggested_quantity,
                ai_recommendation: line.ai_recommendation,
                loading_ai: false,
                max_atp: this.getItemAtp(line.item_id),
                error: ''
            };
            if (line.item_id && !this.drawerForm.ai_recommendation) {
                this.updateDrawerItemAtp(line.item_id);
            }
            this.itemDrawerOpen = true;
        },
        closeItemDrawer() {
            this.itemDrawerOpen = false;
            this.editingIndex = null;
        },
        updateDrawerItemAtp(itemId) {
            this.drawerForm.max_atp = this.getItemAtp(itemId);
            if (!itemId) {
                this.drawerForm.ai_suggested_quantity = null;
                this.drawerForm.ai_recommendation = null;
                this.drawerForm.loading_ai = false;
                return;
            }

            if (this.aiRecommendationsCache[itemId]) {
                const rec = this.aiRecommendationsCache[itemId];
                this.applyRecommendationToDrawer(rec);
                return;
            }

            this.drawerForm.loading_ai = true;
            this.drawerForm.ai_suggested_quantity = null;
            this.drawerForm.ai_recommendation = null;

            fetch('/inventory/requisitions/ai-recommendation/' + itemId, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Network response error');
                return res.json();
            })
            .then(rec => {
                this.aiRecommendationsCache[itemId] = rec;
                if (this.drawerForm.item_id == itemId) {
                    this.applyRecommendationToDrawer(rec);
                }
            })
            .catch(err => {
                console.warn('AI recommendation fetch error:', err);
                const item = this.itemsList.find(i => i.id == itemId);
                const fallback = Math.max(1, item ? (item.economic_order_quantity || item.reorder_point || item.reorder_level || 1) : 1);
                this.applyRecommendationToDrawer({
                    available: true,
                    suggested_quantity: fallback,
                    unit: this.getItemUnit(itemId),
                    explanation: 'Standard replenishment quantity'
                });
            })
            .finally(() => {
                if (this.drawerForm.item_id == itemId) {
                    this.drawerForm.loading_ai = false;
                }
            });
        },
        applyRecommendationToDrawer(rec) {
            if (rec && rec.available && rec.suggested_quantity !== null && rec.suggested_quantity > 0) {
                this.drawerForm.ai_suggested_quantity = rec.suggested_quantity;
                this.drawerForm.ai_recommendation = rec;
                this.drawerForm.requested_quantity = rec.suggested_quantity;
            } else {
                const item = this.itemsList.find(i => i.id == this.drawerForm.item_id);
                const fallback = Math.max(1, item ? (item.economic_order_quantity || item.reorder_point || item.reorder_level || 1) : 1);
                this.drawerForm.ai_suggested_quantity = fallback;
                this.drawerForm.ai_recommendation = {
                    available: true,
                    suggested_quantity: fallback,
                    unit: this.getItemUnit(this.drawerForm.item_id),
                    explanation: 'Standard replenishment quantity'
                };
                this.drawerForm.requested_quantity = fallback;
            }
        },
        saveDrawerItem() {
            this.drawerForm.error = '';
            if (!this.drawerForm.item_id) {
                this.drawerForm.error = 'Please select a medical supply or medication.';
                return;
            }
            if (!this.drawerForm.requested_quantity || this.drawerForm.requested_quantity < 1) {
                this.drawerForm.error = 'Requested quantity must be at least 1.';
                return;
            }
            const justification = (this.drawerForm.clinical_justification || '').trim();
            if (!justification) {
                this.drawerForm.error = 'Clinical justification is required for this item.';
                return;
            }

            const duplicateIdx = this.lines.findIndex((l, i) => l.item_id == this.drawerForm.item_id && i !== this.editingIndex);
            if (duplicateIdx !== -1) {
                this.drawerForm.error = 'This item is already added in line #' + (duplicateIdx + 1) + '. Please edit that item instead.';
                return;
            }

            const itemData = {
                item_id: String(this.drawerForm.item_id),
                requested_quantity: parseInt(this.drawerForm.requested_quantity, 10) || 1,
                ai_suggested_quantity: this.drawerForm.ai_suggested_quantity,
                ai_recommendation: this.drawerForm.ai_recommendation,
                allocation_strategy: this.drawerForm.allocation_strategy || 'FEFO',
                clinical_justification: justification,
                notes: justification,
                max_atp: this.drawerForm.max_atp || 0,
                loading_ai: false
            };

            if (this.editingIndex !== null && this.editingIndex >= 0 && this.editingIndex < this.lines.length) {
                this.lines[this.editingIndex] = itemData;
            } else {
                this.lines.push(itemData);
            }

            this.clientValidationError = '';
            this.closeItemDrawer();
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },
        unlockContext() {
            this.isContextLocked = false;
        },
        validateAndSubmit(e) {
            this.clientValidationError = '';
            if (!this.selectedDepartment) {
                e.preventDefault();
                this.clientValidationError = 'Please select a requesting department.';
                return false;
            }
            if (!this.assignedCostCenter()) {
                e.preventDefault();
                this.clientValidationError = 'The selected department has no active Cost Center assigned. Requisition cannot be submitted.';
                return false;
            }
            if (this.lines.length === 0) {
                e.preventDefault();
                this.clientValidationError = 'At least one item must be requested in the requisition.';
                return false;
            }

            for (let i = 0; i < this.lines.length; i++) {
                const line = this.lines[i];
                if (!line.item_id) {
                    e.preventDefault();
                    this.clientValidationError = 'Item #' + (i + 1) + ' is missing an item selection.';
                    this.openEditItemDrawer(i);
                    return false;
                }
                if (!line.requested_quantity || line.requested_quantity < 1) {
                    e.preventDefault();
                    this.clientValidationError = 'Quantity must be at least 1 for ' + this.getItemName(line.item_id) + '.';
                    this.openEditItemDrawer(i);
                    return false;
                }
                if (!line.clinical_justification || !line.clinical_justification.trim()) {
                    e.preventDefault();
                    this.clientValidationError = 'Clinical justification is required for ' + this.getItemName(line.item_id) + '.';
                    this.openEditItemDrawer(i);
                    return false;
                }
            }
            return true;
        }
    }"
    @open-new-requisition-modal.window="newRequisitionModal = true"
    @keydown.escape.window="if (itemDrawerOpen) { closeItemDrawer(); } else { newRequisitionModal = false; }"
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
                                        <p class="text-xs text-neutral-500">
                                            @if($req->costCenter)
                                                {{ $req->costCenter->code }} &bull; {{ $req->costCenter->name }}
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">No Cost Center assigned</span>
                                            @endif
                                        </p>
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
            <div class="flex min-h-screen items-center justify-center p-3 sm:p-5 text-center">
                <div class="fixed inset-0 bg-neutral-900/60 dark:bg-black/70 backdrop-blur-xs transition-opacity" @click="if (!itemDrawerOpen) newRequisitionModal = false"></div>

                <div class="relative my-auto z-10 w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white dark:bg-neutral-900 text-left shadow-2xl transition-all border border-neutral-200 dark:border-neutral-800 flex flex-col max-h-[88vh]">
                    <form action="{{ route('inventory.requisitions.store') }}" method="POST"
                          @submit="validateAndSubmit($event)"
                          data-confirm-title="Submit store requisition"
                          data-confirm-message="Are you sure you want to submit this store requisition for approval?"
                          data-confirm-label="Submit Requisition"
                          class="flex flex-col flex-1 min-h-0 overflow-hidden relative">
                        @csrf
                        @if($preselectedItem)
                            <input type="hidden" name="context_item_id" value="{{ $preselectedItem->id }}">
                        @endif

                        {{-- MODAL HEADER (Fixed) --}}
                        <div class="bg-white dark:bg-neutral-900 px-6 pt-5 pb-4 border-b border-neutral-200 dark:border-neutral-800 shrink-0 flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 shrink-0">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100 truncate">Create Material Store Requisition</h3>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 truncate">Request clinical and medical supplies from central inventory storage.</p>
                                </div>
                            </div>
                            <button type="button" @click="newRequisitionModal = false" class="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition" title="Close modal">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- MODAL BODY (Scrollable, fixed height boundary) --}}
                        <div class="p-6 overflow-y-auto flex-1 space-y-4">
                            {{-- Server Errors --}}
                            @if($errors->any())
                                <div class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-950/40 p-3 text-xs text-rose-800 dark:text-rose-300">
                                    <div class="flex items-center gap-2 font-semibold">
                                        <svg class="h-4 w-4 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span>Please resolve the following submission issues:</span>
                                    </div>
                                    <ul class="mt-1.5 list-inside list-disc space-y-0.5 text-[11px]">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Client Validation Error Alert --}}
                            <div x-show="clientValidationError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-950/40 p-3 text-xs text-rose-800 dark:text-rose-300 flex items-center justify-between shadow-2xs">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <span x-text="clientValidationError"></span>
                                </div>
                                <button type="button" @click="clientValidationError = ''" class="text-rose-500 hover:text-rose-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            {{-- Contextual Item & AI Banner (Compact) --}}
                            @if($preselectedItem)
                                <div class="rounded-xl border border-primary-200 dark:border-primary-900/60 bg-primary-50/80 dark:bg-primary-950/30 p-3 text-xs text-primary-950 dark:text-primary-200 flex flex-col gap-2 shadow-2xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300 shrink-0">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                            </span>
                                            <div class="truncate">
                                                <p class="font-bold text-primary-950 dark:text-primary-100 truncate">
                                                    Contextual Item: <span class="font-extrabold underline decoration-primary-300 dark:decoration-primary-600 underline-offset-2">{{ $preselectedItem->name }}</span>
                                                    <template x-if="lines.length > 1">
                                                        <span class="font-normal text-primary-700 dark:text-primary-300 ml-1">
                                                            (+<span x-text="lines.length - 1"></span> more <span x-text="(lines.length - 1) === 1 ? 'item' : 'items'"></span> in request)
                                                        </span>
                                                    </template>
                                                </p>
                                                <p class="text-[11px] text-primary-700 dark:text-primary-300 mt-0.5 truncate">
                                                    SKU: <strong class="font-mono text-primary-900 dark:text-primary-200">{{ $preselectedItem->sku }}</strong> &bull; 
                                                    On Hand: <strong class="tabular-nums text-primary-900 dark:text-primary-200">{{ $preselectedItem->quantity_on_hand }} {{ $preselectedItem->unit }}</strong> &bull; 
                                                    Reorder Level: <strong class="tabular-nums text-primary-900 dark:text-primary-200">{{ $preselectedItem->reorder_level }} {{ $preselectedItem->unit }}</strong>
                                                    @if ($preselectedItem->defaultLocation)
                                                        &bull; Storage: <strong class="text-primary-900 dark:text-primary-200">{{ $preselectedItem->defaultLocation->name }}</strong>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="shrink-0">
                                            <template x-if="lines.length <= 1">
                                                <span class="inline-flex items-center rounded-full bg-primary-200/90 dark:bg-primary-900/80 px-2.5 py-0.5 text-[10px] font-bold text-primary-900 dark:text-primary-200 whitespace-nowrap">
                                                    Auto-Carried Forward
                                                </span>
                                            </template>
                                            <template x-if="lines.length > 1">
                                                <span class="inline-flex items-center rounded-full bg-primary-200/90 dark:bg-primary-900/80 px-2.5 py-0.5 text-[10px] font-bold text-primary-900 dark:text-primary-200 whitespace-nowrap">
                                                    <span x-text="lines.length"></span> Items in Requisition
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- When multiple items are added, show pill summary of all items in this requisition --}}
                                    <template x-if="lines.length > 1">
                                        <div class="flex items-center gap-1.5 flex-wrap pt-2 border-t border-primary-200/70 dark:border-primary-800/60">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-primary-800 dark:text-primary-300">Requisition Items:</span>
                                            <template x-for="(l, i) in lines" :key="i">
                                                <span class="inline-flex items-center gap-1 rounded-md bg-white/90 dark:bg-neutral-900/80 px-2 py-0.5 text-[10px] font-semibold text-neutral-800 dark:text-neutral-200 border border-primary-200 dark:border-primary-800/80 shadow-2xs">
                                                    <span class="font-bold text-primary-600 dark:text-primary-400" x-text="'#' + (i + 1)"></span>
                                                    <span class="max-w-[140px] truncate" x-text="getItemName(l.item_id)"></span>
                                                    <span class="font-bold text-primary-700 dark:text-primary-300 font-mono" x-text="'(' + l.requested_quantity + ' ' + (l.ai_recommendation?.unit || getItemUnit(l.item_id)) + ')'"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                @if($aiRecommendation)
                                    @if($aiRecommendation['available'] ?? false)
                                        <div class="rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/80 dark:bg-indigo-950/40 p-3.5 text-xs">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-600 text-white shrink-0">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                    </span>
                                                    <div>
                                                        <span class="font-bold text-neutral-900 dark:text-neutral-100">AI-Suggested Reorder:</span>
                                                        <span class="font-extrabold text-indigo-700 dark:text-indigo-300 ml-1">
                                                            {{ $aiRecommendation['suggested_quantity'] }} {{ $aiRecommendation['unit'] }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1.5 text-[11px]">
                                                    @if(($aiRecommendation['confidence'] ?? '') === 'high')
                                                        <span class="rounded-full bg-emerald-100 dark:bg-emerald-950/80 px-2 py-0.5 font-semibold text-emerald-800 dark:text-emerald-300">
                                                            High Confidence
                                                        </span>
                                                    @elseif(($aiRecommendation['confidence'] ?? '') === 'medium')
                                                        <span class="rounded-full bg-indigo-100 dark:bg-indigo-950/80 px-2 py-0.5 font-semibold text-indigo-800 dark:text-indigo-300">
                                                            Medium Confidence
                                                        </span>
                                                    @else
                                                        <span class="rounded-full bg-neutral-200 dark:bg-neutral-800 px-2 py-0.5 font-semibold text-neutral-700 dark:text-neutral-300">
                                                            Low Confidence
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Data Breakdown --}}
                                            <div class="mt-2.5 grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                                                <div class="rounded-lg bg-white/80 dark:bg-neutral-900/60 p-2 border border-indigo-100/80 dark:border-indigo-900/30">
                                                    <span class="text-neutral-500 dark:text-neutral-400 block text-[10px]">Forecast Demand</span>
                                                    <span class="font-bold text-neutral-900 dark:text-neutral-100">{{ $aiRecommendation['breakdown']['forecast_demand'] ?? 'N/A' }}</span>
                                                </div>
                                                <div class="rounded-lg bg-white/80 dark:bg-neutral-900/60 p-2 border border-indigo-100/80 dark:border-indigo-900/30">
                                                    <span class="text-neutral-500 dark:text-neutral-400 block text-[10px]">Available Stock</span>
                                                    <span class="font-bold text-neutral-900 dark:text-neutral-100">{{ $aiRecommendation['breakdown']['current_stock'] ?? 'N/A' }}</span>
                                                </div>
                                                <div class="rounded-lg bg-white/80 dark:bg-neutral-900/60 p-2 border border-indigo-100/80 dark:border-indigo-900/30">
                                                    <span class="text-neutral-500 dark:text-neutral-400 block text-[10px]">Incoming PO Supply</span>
                                                    <span class="font-bold text-neutral-900 dark:text-neutral-100">{{ $aiRecommendation['breakdown']['incoming_stock'] ?? ('0 ' . $aiRecommendation['unit']) }}</span>
                                                </div>
                                                <div class="rounded-lg bg-white/80 dark:bg-neutral-900/60 p-2 border border-indigo-100/80 dark:border-indigo-900/30">
                                                    <span class="text-neutral-500 dark:text-neutral-400 block text-[10px]">Safety Buffer</span>
                                                    <span class="font-bold text-neutral-900 dark:text-neutral-100">{{ $aiRecommendation['breakdown']['safety_stock'] ?? 'N/A' }}</span>
                                                </div>
                                            </div>

                                            <p class="mt-2 text-[11px] text-neutral-600 dark:text-neutral-300 leading-relaxed">
                                                <strong class="text-neutral-700 dark:text-neutral-200">Analysis:</strong> {{ $aiRecommendation['explanation'] }}
                                            </p>
                                        </div>
                                    @else
                                        <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40 p-3 text-xs text-neutral-700 dark:text-neutral-300 flex items-center gap-2.5">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 shrink-0">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </span>
                                            <div>
                                                <span class="font-bold text-neutral-900 dark:text-neutral-100">AI Forecast: Insufficient Data</span>
                                                <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                    {{ $aiRecommendation['explanation'] ?? 'Insufficient historical consumption data to generate automated projection.' }}
                                                </p>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @endif

                            {{-- Requisition-Level Fields (4-column responsive grid, perfectly aligned) --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                                <div class="flex flex-col justify-end">
                                    <label class="flex items-end text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5 h-6 truncate" title="Requesting Department">
                                        <span>Requesting Dept.</span> <span class="text-rose-500 ml-0.5">*</span>
                                    </label>
                                    <select name="department" x-model="selectedDepartment" required class="h-9 block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium py-1.5 px-2.5">
                                        <option value="">-- Select Dept --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept }}">{{ $dept }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex flex-col justify-end">
                                    <div class="flex items-end justify-between mb-1.5 h-6">
                                        <label class="text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 truncate" title="Cost Center">
                                            Cost Center
                                        </label>
                                        <span x-show="assignedCostCenter()" class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 px-1.5 py-0.2 rounded">
                                            Auto-assigned
                                        </span>
                                    </div>
                                    
                                    {{-- Read-only system-derived presentation --}}
                                    <div class="relative">
                                        <div class="h-9 w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-100/90 dark:bg-neutral-800/90 text-neutral-900 dark:text-neutral-100 px-2.5 flex items-center justify-between shadow-2xs select-none"
                                             :class="{
                                                 'border-amber-400 dark:border-amber-600 bg-amber-50/50 dark:bg-amber-950/20': (!assignedCostCenter() && selectedDepartment)
                                             }">
                                            <template x-if="assignedCostCenter()">
                                                <div class="flex items-center gap-1.5 min-w-0 pr-1">
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                    <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate" x-text="assignedCostCenter().display"></span>
                                                </div>
                                            </template>
                                            <template x-if="!assignedCostCenter() && selectedDepartment">
                                                <div class="flex items-center gap-1.5 min-w-0 text-amber-600 dark:text-amber-400">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    <span class="text-xs font-medium truncate">No Cost Center assigned</span>
                                                </div>
                                            </template>
                                            <template x-if="!selectedDepartment">
                                                <span class="text-xs text-neutral-400 dark:text-neutral-500">Select department first</span>
                                            </template>
                                            <svg class="w-3.5 h-3.5 text-neutral-400 shrink-0 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="System-derived organizational data">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                        </div>
                                        
                                        {{-- Hidden Form Input ensuring value is submitted --}}
                                        <input type="hidden" name="cost_center_id" :value="assignedCostCenter() ? assignedCostCenter().id : ''">
                                    </div>
                                </div>
                                <div class="flex flex-col justify-end">
                                    <label class="flex items-end text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5 h-6 truncate" title="Urgency Level">
                                        <span>Urgency Level</span>
                                    </label>
                                    <select name="urgency" class="h-9 block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium py-1.5 px-2.5">
                                        <option value="routine">Routine</option>
                                        <option value="urgent" {{ ($preselectedItem && (int) $preselectedItem->quantity_on_hand <= 0) ? 'selected' : '' }}>Urgent</option>
                                        <option value="stat_emergency">STAT Emergency</option>
                                    </select>
                                </div>
                                <div class="flex flex-col justify-end">
                                    <label class="flex items-end text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5 h-6 truncate" title="Required Date">
                                        <span>Required Date</span>
                                    </label>
                                    <input type="date" name="required_date" class="h-9 block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium py-1.5 px-2.5">
                                </div>
                            </div>

                            {{-- REQUESTED ITEMS SECTION --}}
                            <div class="border-t border-neutral-200 dark:border-neutral-800 pt-3.5">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
                                            Requested Items (<span x-text="lines.length"></span>)
                                        </h4>
                                        <span class="text-[11px] text-neutral-500 dark:text-neutral-400 hidden sm:inline">&bull; Item-specific justifications</span>
                                    </div>
                                    <button type="button" @click="openAddItemDrawer()" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-primary-700 active:bg-primary-800 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Another Item
                                    </button>
                                </div>

                                {{-- Empty Items State --}}
                                <template x-if="lines.length === 0">
                                    <div class="rounded-xl border-2 border-dashed border-neutral-200 dark:border-neutral-800 p-8 text-center">
                                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-500">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                        </div>
                                        <p class="mt-2 text-xs font-semibold text-neutral-800 dark:text-neutral-200">No items requested yet</p>
                                        <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">Add medical supplies and medications needed for your department.</p>
                                        <button type="button" @click="openAddItemDrawer()" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                            Add First Item
                                        </button>
                                    </div>
                                </template>

                                {{-- Compact Item Cards List --}}
                                <div class="space-y-2.5">
                                    <template x-for="(line, idx) in lines" :key="idx">
                                        <div class="rounded-xl border p-3 transition-colors bg-white dark:bg-neutral-800/80 shadow-2xs"
                                             :class="(!line.clinical_justification || !line.clinical_justification.trim() || !line.requested_quantity || line.requested_quantity < 1)
                                                 ? 'border-amber-300 dark:border-amber-700/70 bg-amber-50/20 dark:bg-amber-950/10'
                                                 : 'border-neutral-200 dark:border-neutral-700 hover:border-primary-300 dark:hover:border-primary-700'">
                                            
                                            {{-- Card Header: Item info + Requested Qty --}}
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100" x-text="getItemName(line.item_id)"></span>
                                                        <span class="font-mono text-[10px] text-neutral-500 dark:text-neutral-400" x-text="'(' + getItemSku(line.item_id) + ')'"></span>
                                                        <span class="inline-flex items-center rounded bg-neutral-100 dark:bg-neutral-700/80 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:text-neutral-300" x-text="'ATP: ' + getItemAtp(line.item_id)"></span>
                                                        <template x-if="idx === 0 && isContextLocked && preselectedItem">
                                                            <span class="rounded bg-primary-100 dark:bg-primary-950 px-1.5 py-0.5 text-[9px] font-bold text-primary-700 dark:text-primary-300">Preselected</span>
                                                        </template>
                                                    </div>

                                                    {{-- Badges: AI suggestion & allocation strategy --}}
                                                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                                        <template x-if="line.ai_suggested_quantity !== null">
                                                            <span :title="line.ai_recommendation?.explanation || 'AI forecast recommendation'"
                                                                  class="inline-flex items-center gap-1 rounded bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/60 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:text-indigo-300 cursor-help">
                                                                <svg class="h-3 w-3 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                </svg>
                                                                <span>AI Suggested: <strong x-text="line.ai_suggested_quantity + ' ' + (line.ai_recommendation?.unit || getItemUnit(line.item_id))"></strong></span>
                                                            </span>
                                                        </template>
                                                        <span class="rounded bg-neutral-100 dark:bg-neutral-700/60 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:text-neutral-300" x-text="line.allocation_strategy || 'FEFO'"></span>
                                                    </div>
                                                </div>

                                                {{-- Requested Qty + Action Buttons --}}
                                                <div class="text-right shrink-0">
                                                    <div class="flex items-baseline justify-end gap-1">
                                                        <span class="text-base font-extrabold text-neutral-900 dark:text-neutral-100 tabular-nums" x-text="line.requested_quantity"></span>
                                                        <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-400" x-text="line.ai_recommendation?.unit || getItemUnit(line.item_id)"></span>
                                                    </div>
                                                    <div class="flex items-center gap-1 mt-1 justify-end">
                                                        <button type="button" @click="openEditItemDrawer(idx)" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/40 transition">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                            Edit
                                                        </button>
                                                        <button type="button" @click="removeLine(idx)" :disabled="lines.length === 1" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition disabled:opacity-20" title="Remove item">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Justification Preview / Warning --}}
                                            <div class="mt-2.5 pt-2 border-t border-neutral-100 dark:border-neutral-700/60 text-xs">
                                                <template x-if="line.clinical_justification && line.clinical_justification.trim()">
                                                    <div class="flex items-start gap-1.5 text-neutral-600 dark:text-neutral-300">
                                                        <span class="font-semibold text-neutral-500 dark:text-neutral-400 shrink-0">Justification:</span>
                                                        <span class="italic line-clamp-2" x-text="line.clinical_justification"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!line.clinical_justification || !line.clinical_justification.trim()">
                                                    <div class="flex items-center justify-between text-amber-700 dark:text-amber-400 bg-amber-50/80 dark:bg-amber-950/40 rounded-lg px-2.5 py-1">
                                                        <span class="flex items-center gap-1 text-[11px] font-semibold">
                                                            <svg class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                            Clinical justification required
                                                        </span>
                                                        <button type="button" @click="openEditItemDrawer(idx)" class="text-[11px] font-bold text-amber-800 dark:text-amber-300 underline hover:no-underline">
                                                            Add Justification &rarr;
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>

                                            {{-- Hidden Form Fields --}}
                                            <input type="hidden" :name="'lines[' + idx + '][item_id]'" :value="line.item_id">
                                            <input type="hidden" :name="'lines[' + idx + '][requested_quantity]'" :value="line.requested_quantity">
                                            <input type="hidden" :name="'lines[' + idx + '][ai_suggested_quantity]'" :value="line.ai_suggested_quantity">
                                            <input type="hidden" :name="'lines[' + idx + '][allocation_strategy]'" :value="line.allocation_strategy">
                                            <input type="hidden" :name="'lines[' + idx + '][clinical_justification]'" :value="line.clinical_justification">
                                            <input type="hidden" :name="'lines[' + idx + '][notes]'" :value="line.clinical_justification">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- MODAL FOOTER (Fixed) --}}
                        <div class="bg-neutral-50 dark:bg-neutral-800/60 px-6 py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 shrink-0">
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                Total <strong class="text-neutral-900 dark:text-neutral-100" x-text="lines.length"></strong> <span x-text="lines.length === 1 ? 'item' : 'items'"></span> in requisition
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="newRequisitionModal = false" class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                    :disabled="lines.length === 0 || !assignedCostCenter()"
                                    :class="(!assignedCostCenter() && selectedDepartment) ? 'opacity-50 cursor-not-allowed' : ''"
                                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 active:bg-primary-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    Submit Store Requisition
                                </button>
                            </div>
                        </div>

                        {{-- ITEM CONFIGURATION DRAWER / SUB-PANEL --}}
                        <div x-show="itemDrawerOpen"
                             x-cloak
                             class="absolute inset-0 z-30 flex flex-col bg-white dark:bg-neutral-900 shadow-2xl overflow-hidden"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 translate-x-4">
                            
                            {{-- Drawer Header --}}
                            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/70 dark:bg-neutral-800/50 shrink-0">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-bold text-neutral-900 dark:text-neutral-100 truncate" x-text="editingIndex !== null ? 'Configure Requested Item' : 'Add Item to Requisition'"></h3>
                                        <p class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">Configure quantity, fulfillment strategy, and specific clinical justification.</p>
                                    </div>
                                </div>
                                <button type="button" @click="closeItemDrawer()" class="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-200/60 dark:hover:bg-neutral-700 transition" title="Close drawer">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            {{-- Drawer Scrollable Content --}}
                            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                                {{-- Drawer Error Alert --}}
                                <template x-if="drawerForm.error">
                                    <div class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-950/40 p-3 text-xs text-rose-800 dark:text-rose-300 flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span x-text="drawerForm.error"></span>
                                    </div>
                                </template>

                                {{-- Item Selector --}}
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                                        Medical Item <span class="text-rose-500">*</span>
                                    </label>
                                    <template x-if="editingIndex === 0 && isContextLocked && preselectedItem">
                                        <div class="rounded-lg border border-primary-200 dark:border-primary-800/60 bg-primary-50/80 dark:bg-primary-950/40 p-3 flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-bold text-neutral-900 dark:text-neutral-100" x-text="preselectedItem.name"></span>
                                                <span class="text-neutral-500 dark:text-neutral-400 ml-1 font-mono" x-text="'(' + (preselectedItem.sku || 'No SKU') + ')'"></span>
                                                <p class="text-[11px] text-neutral-600 dark:text-neutral-400 mt-0.5" x-text="'Available in Central Store: ' + (preselectedItem.quantity_on_hand ?? 0) + ' ' + (preselectedItem.unit || 'units')"></p>
                                            </div>
                                            <button type="button" @click="unlockContext()" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                                Change Item
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="!(editingIndex === 0 && isContextLocked && preselectedItem)">
                                        <select x-model="drawerForm.item_id" @change="updateDrawerItemAtp(drawerForm.item_id)" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                            <option value="">-- Select Medical Supply or Drug --</option>
                                            <template x-for="itm in itemsList" :key="itm.id">
                                                <option :value="itm.id" x-text="itm.name + ' (' + (itm.sku || 'No SKU') + ') • ATP: ' + (itm.quantity_on_hand ?? 0) + ' ' + (itm.unit || 'units')" :selected="itm.id == drawerForm.item_id"></option>
                                            </template>
                                        </select>
                                    </template>
                                </div>

                                {{-- Dynamic AI Recommendation Card in Drawer --}}
                                <div x-show="drawerForm.loading_ai || drawerForm.ai_suggested_quantity !== null || drawerForm.item_id" class="rounded-xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/60 dark:bg-indigo-950/30 p-3">
                                    <template x-if="drawerForm.loading_ai">
                                        <div class="flex items-center gap-2 text-xs text-indigo-700 dark:text-indigo-300 animate-pulse">
                                            <svg class="h-4 w-4 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Calculating AI demand forecast for this item...</span>
                                        </div>
                                    </template>
                                    <template x-if="!drawerForm.loading_ai && drawerForm.ai_suggested_quantity !== null">
                                        <div class="flex items-center justify-between gap-3 text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-6 w-6 items-center justify-center rounded bg-indigo-600 text-white shrink-0">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                </span>
                                                <div>
                                                    <span class="font-bold text-neutral-900 dark:text-neutral-100">AI Suggested Quantity:</span>
                                                    <span class="font-extrabold text-indigo-700 dark:text-indigo-300 ml-1" x-text="drawerForm.ai_suggested_quantity + ' ' + (drawerForm.ai_recommendation?.unit || getItemUnit(drawerForm.item_id))"></span>
                                                    <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5" x-text="drawerForm.ai_recommendation?.explanation || 'Projected consumption need for standard cycle.'"></p>
                                                </div>
                                            </div>
                                            <button type="button" x-show="drawerForm.requested_quantity != drawerForm.ai_suggested_quantity" @click="drawerForm.requested_quantity = drawerForm.ai_suggested_quantity" class="shrink-0 text-[11px] font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                                Apply AI Qty (<span x-text="drawerForm.ai_suggested_quantity"></span>)
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                {{-- Quantity and Allocation Strategy Grid --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                                            Requested Quantity <span class="text-rose-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="number" x-model.number="drawerForm.requested_quantity" min="1" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-semibold pr-14">
                                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs font-medium text-neutral-500 dark:text-neutral-400" x-text="getItemUnit(drawerForm.item_id)"></span>
                                        </div>
                                        <p class="text-[10px] text-neutral-500 dark:text-neutral-400 mt-1" x-text="'Available to pick: ' + getItemAtp(drawerForm.item_id) + ' ' + getItemUnit(drawerForm.item_id)"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                                            Fulfillment Strategy
                                        </label>
                                        <select x-model="drawerForm.allocation_strategy" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-medium">
                                            <option value="FEFO">FEFO (Earliest Expiry First)</option>
                                            <option value="FIFO">FIFO (Oldest In First)</option>
                                            <option value="MANUAL">Manual Batch Selection</option>
                                        </select>
                                        <p class="text-[10px] text-neutral-500 dark:text-neutral-400 mt-1">Recommended for expiration-sensitive items.</p>
                                    </div>
                                </div>

                                {{-- Clinical Justification Textarea --}}
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300">
                                            Item Clinical Justification <span class="text-rose-500">*</span>
                                        </label>
                                        <span class="text-[10px] font-medium text-neutral-400" x-text="(drawerForm.clinical_justification || '').length + '/500'"></span>
                                    </div>
                                    <textarea x-model="drawerForm.clinical_justification"
                                              rows="3"
                                              maxlength="500"
                                              placeholder="e.g. Required for routine blood glucose monitoring of diabetic patients..."
                                              class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-primary-500 text-xs font-normal placeholder:text-neutral-400"></textarea>
                                    <p class="text-[10px] text-neutral-500 dark:text-neutral-400 mt-1">
                                        State why your department or unit requires this specific item (e.g. procedure name, ward census, patient condition).
                                    </p>
                                </div>
                            </div>

                            {{-- Drawer Footer --}}
                            <div class="px-6 py-3.5 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/80 dark:bg-neutral-800/60 shrink-0">
                                <button type="button" @click="closeItemDrawer()" class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition">
                                    Cancel
                                </button>
                                <button type="button" @click="saveDrawerItem()" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-primary-700 active:bg-primary-800 transition">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span x-text="editingIndex !== null ? 'Update Item' : 'Add Item'"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endcan
    </div>
</x-app-layout>
