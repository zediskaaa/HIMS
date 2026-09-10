<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\SupplierContract;
use App\Models\SupplierDocument;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Services\AuditLogger;
use App\Services\SupplierManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SupplierManagementService $suppliers,
        private readonly AuditLogger $audit,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ManageSuppliers->value),
            new Middleware('can:'.Permission::ReviewSupplierCompliance->value, only: ['submitForReview', 'verifyDocument']),
            new Middleware('can:'.Permission::ApproveSuppliers->value, only: ['approve', 'reject', 'suspend', 'inactivate', 'reactivate']),
        ];
    }

    public function index(Request $request): View
    {
        $sorts = ['name', 'created_at', 'accreditation_expires_at'];
        $sort = in_array($request->query('sort'), $sorts, true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $suppliers = Supplier::query()
            ->with(['supplierProducts' => fn ($query) => $query->where('is_active', true)->with('item.category')])
            ->withCount([
                'documents',
                'supplierProducts as active_products_count' => fn ($query) => $query->where('is_active', true),
                'complianceAlerts as active_compliance_alerts_count' => fn ($query) => $query->active(),
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = $request->string('search')->trim()->toString();
                $query->where(fn ($search) => $search
                    ->whereRaw('INSTR(LOWER(name), LOWER(?)) > 0', [$term])
                    ->orWhereRaw('INSTR(LOWER(trade_name), LOWER(?)) > 0', [$term])
                    ->orWhereRaw('INSTR(LOWER(tax_number), LOWER(?)) > 0', [$term])
                    ->orWhereRaw('INSTR(LOWER(email), LOWER(?)) > 0', [$term]));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('accreditation_status'), function ($query) use ($request): void {
                $status = $request->string('accreditation_status')->toString();
                if ($status === SupplierAccreditationStatus::Expired->value) {
                    $query->where('accreditation_status', SupplierAccreditationStatus::Approved)->whereDate('accreditation_expires_at', '<', today());
                } elseif ($status === SupplierAccreditationStatus::Approved->value) {
                    $query->where('accreditation_status', $status)->where(fn ($dates) => $dates->whereNull('accreditation_expires_at')->orWhereDate('accreditation_expires_at', '>=', today()));
                } else {
                    $query->where('accreditation_status', $status);
                }
            })
            ->when($request->query('eligibility') === 'eligible', fn ($query) => $query->procurementEligible())
            ->when($request->query('eligibility') === 'ineligible', fn ($query) => $query->whereNot(fn ($ineligible) => $ineligible->procurementEligible()))
            ->when($request->filled('product_category_id'), fn ($query) => $query->whereHas('supplierProducts', fn ($products) => $products
                ->where('is_active', true)
                ->whereHas('item', fn ($items) => $items->where('category_id', $request->integer('product_category_id')))))
            ->when($request->query('compliance') === 'alerts', fn ($query) => $query->whereHas('complianceAlerts', fn ($alerts) => $alerts->active()))
            ->when($request->query('compliance') === 'clear', fn ($query) => $query->whereDoesntHave('complianceAlerts', fn ($alerts) => $alerts->active()))
            ->when($request->query('expiry') === 'within_30_days', fn ($query) => $query->where(function ($expiring): void {
                $expiring->whereBetween('accreditation_expires_at', [today(), today()->addDays(30)])
                    ->orWhereHas('documents', fn ($documents) => $documents->where('is_current', true)->whereBetween('expires_at', [today(), today()->addDays(30)]))
                    ->orWhereHas('contracts', fn ($contracts) => $contracts->where('status', 'active')->whereBetween('ends_at', [today(), today()->addDays(30)]));
            }))
            ->when($request->query('contract') === 'active', fn ($query) => $query->whereHas('contracts', fn ($contracts) => $contracts
                ->where('status', 'active')->whereDate('starts_at', '<=', today())
                ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))))
            ->when($request->query('contract') === 'none', fn ($query) => $query->whereDoesntHave('contracts', fn ($contracts) => $contracts
                ->where('status', 'active')->whereDate('starts_at', '<=', today())
                ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))))
            ->when($request->query('performance') === 'available', fn ($query) => $query->whereHas('purchaseOrders'))
            ->when($request->query('performance') === 'none', fn ($query) => $query->whereDoesntHave('purchaseOrders'))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        $suppliers->getCollection()->each(fn (Supplier $supplier) => $supplier->setAttribute('computed_compliance_state', $supplier->complianceState()));

        return view('inventory.suppliers.index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status', 'accreditation_status', 'eligibility', 'product_category_id', 'compliance', 'expiry', 'contract', 'performance', 'sort', 'direction']),
            'operationalStatuses' => SupplierStatus::options(),
            'accreditationStatuses' => SupplierAccreditationStatus::options(),
            'businessStructures' => $this->businessStructures(),
            'productCategories' => ItemCategory::active()->orderBy('name')->pluck('name', 'id')->all(),
            'counts' => [
                'total' => Supplier::count(),
                'eligible' => Supplier::procurementEligible()->count(),
                'pending' => Supplier::where('accreditation_status', SupplierAccreditationStatus::PendingReview)->count(),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->suppliers->create($request->validated(), $request->user());

        return redirect()->route('inventory.suppliers.show', $supplier)->with('success', 'Supplier record created as an accreditation draft.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load([
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            'documents' => fn ($query) => $query->with('verifier')->latest(),
            'accreditations' => fn ($query) => $query->with(['submitter', 'decisionMaker'])->latest('cycle_number'),
            'supplierProducts' => fn ($query) => $query->with(['item.category', 'prices.contract'])->orderByDesc('is_active')->latest(),
            'contracts' => fn ($query) => $query->with('responsibleUser')->latest('starts_at'),
            'complianceAlerts' => fn ($query) => $query->active()->orderBy('due_date'),
        ]);

        return view('inventory.suppliers.show', [
            'supplier' => $supplier,
            'items' => InventoryItem::query()
                ->where('status', '!=', 'inactive')
                ->whereNotIn('id', $supplier->supplierProducts->pluck('item_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit']),
            'businessStructures' => $this->businessStructures(),
            'canReview' => request()->user()->can(Permission::ReviewSupplierCompliance->value),
            'canApprove' => request()->user()->can(Permission::ApproveSuppliers->value),
            'canDecide' => request()->user()->can(Permission::ApproveSuppliers->value)
                && $this->suppliers->canIndependentlyDecide($supplier, request()->user()),
            'performance' => [
                'purchase_orders' => $supplier->purchaseOrders()->count(),
                'received_orders' => $supplier->purchaseOrders()->whereNotNull('received_at')->count(),
                'pending_orders' => $supplier->purchaseOrders()->whereNull('received_at')->where('status', '!=', 'cancelled')->count(),
            ],
            'recentAudit' => request()->user()->can(Permission::ViewAuditTrail->value)
                ? AuditLog::query()->where('target_type', $supplier->getMorphClass())->where('target_id', (string) $supplier->id)->latest()->limit(20)->get()
                : collect(),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->suppliers->update($supplier, $request->validated(), $request->user());

        return back()->with('success', 'Supplier information updated.');
    }

    public function addContact(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_type' => ['required', Rule::in(['primary', 'procurement', 'sales', 'finance', 'authorized_representative', 'other'])],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without_all:phone,mobile'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+().\-\s]+$/', 'required_without_all:email,mobile'],
            'mobile' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+().\-\s]+$/', 'required_without_all:email,phone'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        DB::transaction(function () use ($supplier, $data, $request): void {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($data['is_primary']) {
                $supplier->contacts()->update(['is_primary' => false]);
            }
            $contact = $supplier->contacts()->create($data);
            $this->audit->log(AuditAction::UpdatedSupplier, $request->user(), 'Added a supplier business contact.', $supplier, $supplier->name, newValues: ['contact_id' => $contact->id, 'contact_type' => $contact->contact_type, 'is_primary' => $contact->is_primary]);
        });

        return back()->with('success', 'Supplier contact added.');
    }

    public function uploadDocument(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'required_for_accreditation' => ['sometimes', 'boolean'],
            'blocks_procurement_when_invalid' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'replaces_document_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_documents', 'id')->where(fn ($query) => $query
                    ->where('supplier_id', $supplier->id)
                    ->where('is_current', true)),
            ],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'min:1', 'max:10240'],
        ]);

        $replacement = isset($data['replaces_document_id'])
            ? $supplier->documents()->whereKey($data['replaces_document_id'])->where('is_current', true)->firstOrFail()
            : null;
        $documentType = $replacement?->document_type ?? $data['document_type'];
        if (filled($data['document_number'] ?? null)) {
            $duplicate = $supplier->documents()->where('is_current', true)
                ->where('document_type', $documentType)
                ->where('document_number', trim($data['document_number']))
                ->when($replacement, fn ($query) => $query->whereKeyNot($replacement->id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['document_number' => 'This current document type and reference number already exists. Upload it as a replacement instead.']);
            }
            $data['document_number'] = trim($data['document_number']);
        }

        $file = $request->file('file');
        $path = $file->store('supplier-documents/'.$supplier->id, 'local');
        abort_if($path === false, 500, 'The supplier document could not be stored.');

        try {
            DB::transaction(function () use ($supplier, $data, $file, $path, $request): void {
                $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
                $replacement = isset($data['replaces_document_id'])
                    ? $supplier->documents()->whereKey($data['replaces_document_id'])->where('is_current', true)->lockForUpdate()->firstOrFail()
                    : null;
                $documentType = $replacement?->document_type ?? $data['document_type'];
                if (filled($data['document_number'] ?? null)) {
                    $duplicate = $supplier->documents()->where('is_current', true)
                        ->where('document_type', $documentType)
                        ->where('document_number', $data['document_number'])
                        ->when($replacement, fn ($query) => $query->whereKeyNot($replacement->id))
                        ->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['document_number' => 'This current document type and reference number already exists. Upload it as a replacement instead.']);
                    }
                }
                $document = $supplier->documents()->create([
                    ...collect($data)->except(['file', 'replaces_document_id'])->all(),
                    'document_type' => $replacement?->document_type ?? $data['document_type'],
                    'required_for_accreditation' => $replacement?->required_for_accreditation || (bool) ($data['required_for_accreditation'] ?? false),
                    'blocks_procurement_when_invalid' => $replacement?->blocks_procurement_when_invalid || (bool) ($data['blocks_procurement_when_invalid'] ?? false),
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
                $replacement?->update(['is_current' => false, 'superseded_by_id' => $document->id]);
                $this->audit->log(AuditAction::UploadedSupplierDocument, $request->user(), 'Uploaded supplier document evidence for review.', $supplier, $supplier->name, newValues: ['document_id' => $document->id, 'document_type' => $document->document_type, 'replaces_document_id' => $replacement?->id]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Document uploaded and awaiting verification.');
    }

    public function downloadDocument(Supplier $supplier, SupplierDocument $document): StreamedResponse
    {
        $this->ensureOwnedBy($supplier, $document);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function verifyDocument(Request $request, Supplier $supplier, SupplierDocument $document): RedirectResponse
    {
        $this->ensureOwnedBy($supplier, $document);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:2000', 'required_if:decision,rejected'],
        ]);
        $this->suppliers->verifyDocument($supplier, $document, $request->user(), $data['decision'] === 'verified', $data['review_notes'] ?? null);

        return back()->with('success', 'Document review recorded.');
    }

    public function submitForReview(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->suppliers->submitForReview($supplier, $request->user());

        return back()->with('success', 'Supplier submitted for accreditation review.');
    }

    public function approve(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'compliance_attested' => ['accepted'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->suppliers->approve($supplier, $request->user(), $data['expires_at'] ?? null, $data['decision_notes'] ?? null);

        return back()->with('success', 'Supplier accreditation approved.');
    }

    public function reject(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['decision_notes' => ['required', 'string', 'max:2000']]);
        $this->suppliers->reject($supplier, $request->user(), $data['decision_notes']);

        return back()->with('success', 'Supplier accreditation rejected with a recorded reason.');
    }

    public function suspend(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['suspension_reason' => ['required', 'string', 'max:2000']]);
        $this->suppliers->suspend($supplier, $request->user(), $data['suspension_reason']);

        return back()->with('success', 'Supplier suspended from new procurement.');
    }

    public function reactivate(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->suppliers->reactivate($supplier, $request->user());

        return back()->with('success', $supplier->fresh()->isProcurementEligible() ? 'Supplier reactivated and eligible for procurement.' : 'Supplier record reactivated; compliance or accreditation still prevents procurement use.');
    }

    public function inactivate(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['inactivation_reason' => ['required', 'string', 'max:2000']]);
        $this->suppliers->inactivate($supplier, $request->user(), $data['inactivation_reason']);

        return back()->with('success', 'Supplier inactivated; historical records were retained.');
    }

    public function addProduct(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query->where('status', '!=', 'inactive')),
                Rule::unique('supplier_products')->where('supplier_id', $supplier->id),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'supplier_product_name' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'pack_size' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_preferred' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($supplier, $data, $request): void {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($supplier->supplierProducts()->where('item_id', $data['item_id'])->exists()) {
                throw ValidationException::withMessages(['item_id' => 'This item is already linked to the supplier. Reactivate the existing relationship if needed.']);
            }
            $product = $supplier->supplierProducts()->create([...$data, 'is_preferred' => (bool) ($data['is_preferred'] ?? false)]);
            $this->suppliers->recordSupplierProductChange($supplier, $product, $request->user(), true);
        });

        return back()->with('success', 'Product linked to the supplier.');
    }

    public function deactivateProduct(Request $request, Supplier $supplier, SupplierProduct $supplierProduct): RedirectResponse
    {
        $this->ensureOwnedBy($supplier, $supplierProduct);
        DB::transaction(function () use ($supplier, $supplierProduct, $request): void {
            $supplierProduct = SupplierProduct::query()->whereKey($supplierProduct->id)->where('supplier_id', $supplier->id)->lockForUpdate()->firstOrFail();
            if (! $supplierProduct->is_active) {
                throw ValidationException::withMessages(['product' => 'The supplier product relationship is already inactive.']);
            }
            $supplierProduct->update(['is_active' => false, 'is_preferred' => false]);
            $this->suppliers->recordSupplierProductChange($supplier, $supplierProduct, $request->user(), false);
        });

        return back()->with('success', 'Supplier product relationship deactivated; price history was retained.');
    }

    public function reactivateProduct(Request $request, Supplier $supplier, SupplierProduct $supplierProduct): RedirectResponse
    {
        $this->ensureOwnedBy($supplier, $supplierProduct);
        DB::transaction(function () use ($supplier, $supplierProduct, $request): void {
            $supplierProduct = SupplierProduct::query()->whereKey($supplierProduct->id)->where('supplier_id', $supplier->id)->lockForUpdate()->firstOrFail();
            if ($supplierProduct->is_active) {
                throw ValidationException::withMessages(['product' => 'The supplier product relationship is already active.']);
            }
            if ($supplierProduct->item()->where('status', 'inactive')->exists()) {
                throw ValidationException::withMessages(['product' => 'An inactive inventory item cannot be offered by a supplier.']);
            }
            $supplierProduct->update(['is_active' => true]);
            $this->suppliers->recordSupplierProductChange($supplier, $supplierProduct, $request->user(), false);
        });

        return back()->with('success', 'Supplier product relationship reactivated; price history remains available.');
    }

    public function addPrice(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'supplier_product_id' => ['required', 'integer', 'exists:supplier_products,id'],
            'supplier_contract_id' => ['nullable', 'integer', 'exists:supplier_contracts,id'],
            'unit_price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $product = SupplierProduct::findOrFail($data['supplier_product_id']);
        $this->ensureOwnedBy($supplier, $product);
        if (! $product->is_active) {
            throw ValidationException::withMessages(['supplier_product_id' => 'Prices can only be recorded for an active supplier product.']);
        }
        if (! empty($data['supplier_contract_id'])) {
            $contract = SupplierContract::findOrFail($data['supplier_contract_id']);
            $this->ensureOwnedBy($supplier, $contract);
            if ($contract->effectiveStatus() !== 'active') {
                throw ValidationException::withMessages(['supplier_contract_id' => 'Prices can only reference a currently active contract.']);
            }
        }
        DB::transaction(function () use ($data, $request, $supplier, $product): void {
            $product = SupplierProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $product->is_active) {
                throw ValidationException::withMessages(['supplier_product_id' => 'Prices can only be recorded for an active supplier product.']);
            }
            if (! empty($data['supplier_contract_id'])) {
                $contract = SupplierContract::query()->whereKey($data['supplier_contract_id'])->lockForUpdate()->firstOrFail();
                if ((int) $contract->supplier_id !== (int) $supplier->id || $contract->effectiveStatus() !== 'active') {
                    throw ValidationException::withMessages(['supplier_contract_id' => 'Prices can only reference a currently active contract for this supplier.']);
                }
            }

            $overlap = $product->prices()
                ->where('currency', strtoupper($data['currency']))
                ->where('minimum_order_quantity', $data['minimum_order_quantity'])
                ->when(
                    empty($data['supplier_contract_id']),
                    fn ($query) => $query->whereNull('supplier_contract_id'),
                    fn ($query) => $query->where('supplier_contract_id', $data['supplier_contract_id']),
                )
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))
                ->when(! empty($data['effective_until']), fn ($query) => $query->whereDate('effective_from', '<=', $data['effective_until']))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'This price overlaps an existing period for the same product, currency, quantity tier, and contract.']);
            }

            $price = SupplierPrice::create([...$data, 'currency' => strtoupper($data['currency']), 'created_by' => $request->user()->id]);
            $this->audit->log(AuditAction::AddedSupplierPrice, $request->user(), 'Recorded a time-bounded supplier price.', $supplier, $supplier->name, newValues: ['price_id' => $price->id, 'item_id' => $product->item_id, 'currency' => $price->currency, 'unit_price' => $price->unit_price, 'effective_from' => $price->effective_from?->toDateString(), 'effective_until' => $price->effective_until?->toDateString()]);
        });

        return back()->with('success', 'Supplier price recorded without changing the inventory item price.');
    }

    public function addContract(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'contract_number' => ['required', 'string', 'max:255', Rule::unique('supplier_contracts')->where('supplier_id', $supplier->id)],
            'contract_type' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'payment_terms' => ['nullable', 'string', 'max:2000'],
            'delivery_terms' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($supplier, $data, $request): void {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($supplier->contracts()->where('contract_number', $data['contract_number'])->exists()) {
                throw ValidationException::withMessages(['contract_number' => 'This contract number already exists for the supplier.']);
            }
            $contract = $supplier->contracts()->create([...$data, 'responsible_user_id' => $request->user()->id]);
            $this->audit->log(AuditAction::AddedSupplierContract, $request->user(), 'Recorded a supplier contract reference.', $supplier, $supplier->name, newValues: ['contract_id' => $contract->id, 'contract_number' => $contract->contract_number, 'starts_at' => $contract->starts_at?->toDateString(), 'ends_at' => $contract->ends_at?->toDateString(), 'status' => $contract->status]);
        });

        return back()->with('success', 'Supplier contract recorded.');
    }

    public function updateContract(Request $request, Supplier $supplier, SupplierContract $contract): RedirectResponse
    {
        $this->ensureOwnedBy($supplier, $contract);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        DB::transaction(function () use ($contract, $data, $request, $supplier): void {
            $contract = SupplierContract::query()->whereKey($contract->id)->where('supplier_id', $supplier->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $contract->status;
            if ($oldStatus === $data['status']) {
                throw ValidationException::withMessages(['status' => 'The contract already has this status.']);
            }
            $contract->update($data);
            $this->audit->log(AuditAction::UpdatedSupplierContract, $request->user(), 'Updated a supplier contract record.', $supplier, $supplier->name, ['contract_id' => $contract->id, 'status' => $oldStatus], ['contract_id' => $contract->id, 'status' => $contract->status]);
        });

        return back()->with('success', 'Contract status updated; the contract history was retained.');
    }

    private function ensureOwnedBy(Supplier $supplier, object $child): void
    {
        abort_unless((int) $child->supplier_id === (int) $supplier->id, 404);
    }

    private function businessStructures(): array
    {
        return [
            'sole_proprietorship' => 'Sole proprietorship',
            'partnership' => 'Partnership',
            'corporation' => 'Corporation',
            'cooperative' => 'Cooperative',
            'government_entity' => 'Government entity',
            'foreign_entity' => 'Foreign entity',
            'other' => 'Other / not yet classified',
        ];
    }
}
