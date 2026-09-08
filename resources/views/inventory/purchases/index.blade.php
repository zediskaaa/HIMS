<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">Procurement & Sourcing Management</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Manage sourcing requests, supplier quotes, and purchase orders from a single procurement workspace.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            {{--
                Stage 1 used to be a form asking the user to type in the current
                stock, historical usage and reorder point by hand — the very
                figures a forecast is supposed to produce. It now lives on its own
                screen and derives all of them from recorded stock movements.
            --}}
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

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Demand plans</h3>
                <div id="demand-plans-list" class="mt-4 space-y-2">
                    <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading demand plans from API...</p>
                </div>
                <x-ui.loader id="demand-plans-api-status" size="sm" label="Loading demand plans from API..." class="mt-3 text-sm text-[var(--muted)]" />
            </div>

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

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Procurement requests</h3>
                <div id="procurement-requests-list" class="mt-4 space-y-3">
                    <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading procurement requests from API...</p>
                </div>
                <x-ui.loader id="procurement-requests-api-status" size="sm" label="Loading procurement requests from API..." class="mt-3 text-sm text-[var(--muted)]" />
            </div>

            {{--
                Stage 3 had a backend route and controller from the start but no
                form, so a quote could never be raised from the screen and the
                list below always read empty. This is that missing form; it posts
                to the existing inventory.purchases.quotes.store.
            --}}
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
                                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                        </div>
                        <div>
                            <label for="quote-price" class="mb-1 block text-sm font-medium text-[var(--text)]">Quoted price</label>
                            <input id="quote-price" type="number" step="0.01" min="0" name="quoted_price" value="{{ old('quoted_price') }}"
                                   class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                            <x-input-error :messages="$errors->get('quoted_price')" class="mt-1" />
                        </div>
                        <div>
                            <label for="quote-notes" class="mb-1 block text-sm font-medium text-[var(--text)]">Notes</label>
                            <input id="quote-notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="255"
                                   placeholder="Lead time, payment terms, inclusions"
                                   class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Submit quote</button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Supplier quotations</h3>
                <div id="supplier-quotes-list" class="mt-4 space-y-2">
                    <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">Loading supplier quotes from API...</p>
                </div>
                <x-ui.loader id="supplier-quotes-api-status" size="sm" label="Loading supplier quotes from API..." class="mt-3 text-sm text-[var(--muted)]" />
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Create purchase order</h3>
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
                        <input type="number" step="0.01" name="unit_cost" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Notes</label>
                        <textarea name="notes" rows="3" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Create purchase order</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Purchase Orders</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead>
                            <tr class="text-left text-[var(--muted)]">
                                <th class="px-3 py-2">PO Number</th>
                                <th class="px-3 py-2">Supplier</th>
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2">Qty</th>
                                <th class="px-3 py-2">Unit Cost</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody id="purchase-orders-table-body" class="divide-y divide-[var(--border)]">
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-[var(--muted)]">Loading purchase orders from API...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <x-ui.loader id="purchase-orders-api-status" size="sm" label="Loading purchase orders from API..." class="mt-3 text-sm text-[var(--muted)]" />
            </div>
        </div>
    </div>

    <script>
        async function fetchApiJson(path) {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
            const response = await fetch(path, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`API request failed with status ${response.status}`);
            }

            return response.json();
        }

        function formatCurrency(amount) {
            return `₱${parseFloat(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        function renderDemandPlans(plans) {
            const container = document.getElementById('demand-plans-list');
            if (!plans.length) {
                container.innerHTML = '<p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No demand plans found via API.</p>';
                return;
            }

            container.innerHTML = plans.map(plan => `
                <div class="rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold text-[var(--text)]">${plan.item ? plan.item.name : '-'}</span>
                        <span class="rounded-full bg-[var(--primary-light)] px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--primary)]">${plan.status}</span>
                    </div>
                    <p class="mt-1 text-[var(--muted)]">Current stock: ${plan.current_stock} • Historical usage: ${plan.historical_usage} • Upcoming need: ${plan.upcoming_need} • Reorder point: ${plan.reorder_point}</p>
                    ${plan.trigger_reason ? `<p class="mt-1 text-[var(--muted)]">Trigger: ${plan.trigger_reason}</p>` : ''}
                </div>
            `).join('');
        }

        function renderProcurementRequests(requests) {
            const container = document.getElementById('procurement-requests-list');
            if (!requests.length) {
                container.innerHTML = '<p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No procurement requests found via API.</p>';
                return;
            }

            container.innerHTML = requests.map(request => `
                <div class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-[var(--text)]">${escapeHtml(request.title)}</p>
                            <p class="text-sm text-[var(--muted)]">${request.item ? escapeHtml(request.item.name) : '-'} • Qty: ${request.requested_quantity}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-[var(--primary-light)] px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--primary)]">${escapeHtml(request.priority)}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">${escapeHtml(request.status)}</span>
                        </div>
                    </div>
                    ${request.description ? `<p class="mt-3 text-sm text-[var(--muted)]">${escapeHtml(request.description)}</p>` : ''}
                    ${request.supplier ? `<p class="mt-2 text-sm text-[var(--muted)]"><strong>Preferred supplier:</strong> ${escapeHtml(request.supplier.name)}</p>` : ''}
                    ${renderApprovalForm(request)}
                </div>
            `).join('');
        }

        /*
         * Stage 4. The approve route existed but nothing on the page posted to
         * it, so a request stayed PENDING forever. Already-approved requests get
         * a stamp instead of a second button — approve() has no transition guard,
         * so re-approving would silently overwrite who signed off.
         */
        function renderApprovalForm(request) {
            if (request.status === 'approved') {
                return `
                    <p class="mt-3 border-t border-[var(--border)] pt-3 text-sm text-[var(--muted)]">
                        Approved${request.approved_by ? ` by <strong class="text-[var(--text)]">${escapeHtml(request.approved_by)}</strong>` : ''}.
                        Raise the purchase order below.
                    </p>
                `;
            }

            return `
                <form method="POST" action="/inventory/purchases/requests/${request.id}/approve"
                      class="mt-3 grid gap-3 border-t border-[var(--border)] pt-3 md:grid-cols-3"
                      data-confirm-title="Confirm request approval"
                      data-confirm-message="Are you sure you want to approve this procurement request?"
                      data-confirm-label="Approve Request">
                    <input type="hidden" name="_token" value="${csrfToken()}">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[var(--muted)]">Approved by</label>
                        <input type="text" name="approved_by" maxlength="255" required
                               class="w-full rounded-lg border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[var(--muted)]">Approval notes</label>
                        <input type="text" name="approval_notes" maxlength="255"
                               class="w-full rounded-lg border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white">
                            Approve request
                        </button>
                    </div>
                </form>
            `;
        }

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        }

        // These rows are built by string concatenation, so anything coming back
        // from the API is escaped before it is interpolated.
        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function renderSupplierQuotes(quotes) {
            const container = document.getElementById('supplier-quotes-list');
            if (!quotes.length) {
                container.innerHTML = '<p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No supplier quotes found via API.</p>';
                return;
            }

            container.innerHTML = quotes.map(quote => `
                <div class="rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold text-[var(--text)]">${quote.supplier ? quote.supplier.name : '-'}</span>
                        <span class="text-[var(--muted)]">${formatCurrency(quote.quoted_price)}</span>
                    </div>
                    <p class="mt-1 text-[var(--muted)]">Request: ${quote.procurement_request ? quote.procurement_request.request_number : '-'} • Status: ${quote.status}</p>
                </div>
            `).join('');
        }

        /*
         * A pending order is one nobody has booked in yet. Receiving it is what
         * turns a paid-for order into stock on the shelf: receive() posts a
         * stock_in through InventoryAutomationService and stamps the PO
         * `received`. The route existed from the start but this cell only ever
         * held a placeholder label, so there was no way to trigger it.
         *
         * Gated on record_movements rather than on the page's own permission —
         * counting what arrives on the dock is warehouse work, not procurement's.
         */
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
            const itemSelectIds = ['demand-plan-item-select', 'request-item-select', 'po-item-select'];
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
