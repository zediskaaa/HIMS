<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\SupplierCompanyProfileStatus;
use App\Enums\SupplierDocumentStatus;
use App\Http\Requests\UpdateSupplierCompanyProfileRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAcknowledgement;
use App\Models\InventoryItem;
use App\Models\QuoteLineItem;
use App\Models\RfqSupplierInvitation;
use App\Models\Shipment;
use App\Models\SupplierDiscrepancy;
use App\Models\SupplierDocument;
use App\Models\SupplierInvoice;
use App\Models\SupplierProduct;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FileContentValidator;
use App\Services\SupplierManagementService;
use App\Support\ItemFamilyArtwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SupplierPortalController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        $supplier = $request->user()->supplier;
        if ($request->user()->can(Permission::SupplierManageProfile->value)
            && in_array($supplier->company_profile_status, [
                SupplierCompanyProfileStatus::Draft,
                SupplierCompanyProfileStatus::ChangesRequested,
                SupplierCompanyProfileStatus::Rejected,
            ], true)) {
            return redirect()->route('supplier.company-profile.edit');
        }

        return view('supplier-portal.dashboard', [
            'supplier' => $supplier,
            'ordersRequiringAction' => $supplier->purchaseOrders()->whereIn('status', [PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::Dispatched->value])->count(),
            'upcomingDeliveries' => Shipment::where('supplier_id', $supplier->id)->whereIn('status', ['created', 'in_transit', 'out_for_delivery'])->count(),
            'openDiscrepancies' => SupplierDiscrepancy::where('supplier_id', $supplier->id)->where('status', '!=', 'closed')->count(),
            'availableRfqs' => RfqSupplierInvitation::where('supplier_id', $supplier->id)->whereHas('rfq', fn ($q) => $q->where('status', 'published')->where('submission_deadline', '>', now()))->count(),
            'invoices' => SupplierInvoice::where('supplier_id', $supplier->id)->selectRaw('status, COUNT(*) aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'recentOrders' => $supplier->purchaseOrders()->with('lines.item')->latest('requested_at')->limit(5)->get(),
            'scorecard' => $supplier->latestApprovedScorecard,
        ]);
    }

    public function companyProfile(Request $request): View
    {
        abort_unless($request->user()->can(Permission::SupplierManageProfile->value), 403);
        $supplier = $request->user()->supplier->load([
            'contacts' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')->orderBy('name'),
            'documents' => fn ($query) => $query->where('is_current', true)->latest(),
            'companyProfileReviewer',
        ]);
        $primaryContact = $supplier->contacts->firstWhere('is_primary', true) ?? $supplier->contacts->first();
        [$contactFirstName, $contactMiddleName, $contactSurname] = User::splitName(
            $supplier->contact_person ?: $primaryContact?->name
        );
        $official = [
            'name' => $supplier->name,
            'trade_name' => $supplier->trade_name,
            'business_structure' => $supplier->business_structure,
            'provides_regulated_health_products' => $supplier->provides_regulated_health_products,
            'tax_number' => $supplier->tax_number,
            'address' => $supplier->address,
            'billing_address' => $supplier->billing_address,
            'delivery_address' => $supplier->delivery_address,
            'contact_person' => $supplier->contact_person ?: $primaryContact?->name,
            'contact_first_name' => $contactFirstName,
            'contact_middle_name' => $contactMiddleName,
            'contact_surname' => $contactSurname,
            'contact_position' => $primaryContact?->position,
            'email' => $supplier->email ?: $primaryContact?->email,
            'phone' => $supplier->phone ?: ($primaryContact?->mobile ?: $primaryContact?->phone),
            'standard_lead_time_days' => $supplier->standard_lead_time_days,
            'payment_terms' => $supplier->payment_terms,
        ];
        $profile = array_replace($official, $supplier->company_profile_draft ?? []);
        if (blank($profile['contact_first_name'] ?? null) && filled($profile['contact_person'] ?? null)) {
            [$profile['contact_first_name'], $profile['contact_middle_name'], $profile['contact_surname']] = User::splitName($profile['contact_person']);
        }
        $requiredFields = ['name', 'business_structure', 'tax_number', 'address', 'billing_address', 'delivery_address', 'contact_first_name', 'contact_surname', 'contact_position', 'email', 'phone'];
        $acceptableDocumentExists = $supplier->documents->contains(
            fn (SupplierDocument $document): bool => $document->verification_status !== SupplierDocumentStatus::Rejected
        );
        $completed = collect($requiredFields)->filter(fn (string $field) => filled($profile[$field] ?? null))->count()
            + ($acceptableDocumentExists ? 1 : 0);

        return view('supplier-portal.company-profile', [
            'supplier' => $supplier,
            'profile' => $profile,
            'completion' => $supplier->company_profile_status === SupplierCompanyProfileStatus::Approved
                ? 100
                : (int) round(($completed / (count($requiredFields) + 1)) * 100),
            'editable' => $supplier->company_profile_status !== SupplierCompanyProfileStatus::PendingReview,
            'acceptableDocumentExists' => $acceptableDocumentExists,
            'businessStructures' => [
                'sole_proprietorship' => 'Sole Proprietorship',
                'partnership' => 'Partnership',
                'corporation' => 'Corporation',
                'cooperative' => 'Cooperative',
                'government_entity' => 'Government Entity',
                'foreign_entity' => 'Foreign Entity',
                'other' => 'Other',
            ],
        ]);
    }

    public function saveCompanyProfile(UpdateSupplierCompanyProfileRequest $request, SupplierManagementService $suppliers): RedirectResponse
    {
        $suppliers->saveCompanyProfileDraft($request->user()->supplier, $request->validated(), $request->user());

        return back()->with('success', 'Company profile draft saved.');
    }

    public function submitCompanyProfile(UpdateSupplierCompanyProfileRequest $request, SupplierManagementService $suppliers): RedirectResponse
    {
        $suppliers->submitCompanyProfile($request->user()->supplier, $request->validated(), $request->user());

        return back()->with('success', 'Company profile submitted for hospital review.');
    }

    public function orders(Request $request): View
    {
        return view('supplier-portal.orders.index', ['orders' => PurchaseOrder::where('supplier_id', $request->user()->supplier_id)->with(['supplier', 'lines.item.category'])->latest('requested_at')->paginate(10)->withQueryString()]);
    }

    public function order(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $this->own($request, $purchaseOrder->supplier_id);
        $order = $purchaseOrder->load(['lines.item.category', 'acknowledgements.responder', 'shipments.lines']);

        return view('supplier-portal.orders.show', [
            'order' => $order,
            'orderArtwork' => ItemFamilyArtwork::filename($order->lines->pluck('item')),
        ]);
    }

    public function acknowledge(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        $this->own($request, $purchaseOrder->supplier_id);
        abort_unless($request->user()->can(Permission::SupplierFulfillOrders->value), 403);
        abort_unless(in_array($purchaseOrder->statusEnum(), [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Dispatched], true), 422);
        $data = $request->validate([
            'response' => ['required', Rule::in(['accepted', 'rejected', 'change_requested'])],
            'exception_type' => ['nullable', Rule::in(['insufficient_stock', 'partial_availability', 'backorder', 'delivery_date', 'discontinued', 'other'])],
            'message' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('response') !== 'accepted')],
        ]);
        $ack = DB::transaction(function () use ($data, $purchaseOrder, $request) {
            $ack = PurchaseOrderAcknowledgement::create([...$data, 'purchase_order_id' => $purchaseOrder->id, 'supplier_id' => $purchaseOrder->supplier_id, 'responded_by' => $request->user()->id, 'responded_at' => now()]);
            if ($data['response'] === 'accepted') {
                $purchaseOrder->update(['status' => PurchaseOrderStatus::Acknowledged->value]);
            }
            return $ack;
        });
        $audit->log(AuditAction::AcknowledgedPurchaseOrder, $request->user(), "Supplier responded {$data['response']} to {$purchaseOrder->po_number}.", $ack, $purchaseOrder->po_number, newValues: ['response' => $data['response']]);
        return back()->with('success', 'Purchase order response recorded.');
    }

    public function storeAsn(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        $this->own($request, $purchaseOrder->supplier_id);
        abort_unless($request->user()->can(Permission::SupplierFulfillOrders->value), 403);
        abort_unless(in_array($purchaseOrder->statusEnum(), [PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::PartiallyFulfilled], true), 422);
        $data = $request->validate([
            'shipment_number' => ['required', 'string', 'max:80', 'unique:shipments,shipment_number'], 'dispatch_date' => ['required', 'date', 'before_or_equal:today'],
            'estimated_delivery_date' => ['required', 'date', 'after_or_equal:today', 'after_or_equal:dispatch_date'], 'carrier_name' => ['required', 'string', 'max:150'],
            'tracking_number' => ['nullable', 'string', 'max:100'], 'sscc' => ['nullable', 'digits:18'], 'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.po_line_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.lot_number' => ['nullable', 'string', 'max:100'], 'lines.*.serial_number' => ['nullable', 'string', 'max:100'], 'lines.*.expiry_date' => ['nullable', 'date', 'after:today'],
        ]);
        $shipment = DB::transaction(function () use ($data, $purchaseOrder) {
            $validLines = $purchaseOrder->lines()->pluck('ordered_quantity', 'id');
            foreach ($data['lines'] as $line) {
                if (! $validLines->has($line['po_line_id']) || $line['quantity'] > $validLines[$line['po_line_id']]) throw ValidationException::withMessages(['lines' => 'ASN lines must belong to this purchase order and may not exceed ordered quantities.']);
            }
            $shipment = Shipment::create([...collect($data)->except('lines')->all(), 'purchase_order_id' => $purchaseOrder->id, 'supplier_id' => $purchaseOrder->supplier_id, 'destination_facility' => 'Central Hospital Receiving Dock', 'status' => 'in_transit']);
            $shipment->lines()->createMany($data['lines']);
            return $shipment;
        });
        $audit->log(AuditAction::SubmittedAdvanceShipNotice, $request->user(), "Submitted ASN {$shipment->shipment_number} for {$purchaseOrder->po_number}.", $shipment, $shipment->shipment_number);
        return back()->with('success', 'Advance Ship Notice submitted.');
    }

    public function discrepancies(Request $request): View
    {
        return view('supplier-portal.discrepancies', ['discrepancies' => SupplierDiscrepancy::where('supplier_id', $request->user()->supplier_id)->with(['receiptLine.item', 'receiptLine.goodsReceiptNote.purchaseOrder'])->latest()->paginate(10)]);
    }

    public function respondToDiscrepancy(Request $request, SupplierDiscrepancy $discrepancy, AuditLogger $audit): RedirectResponse
    {
        $this->own($request, $discrepancy->supplier_id);
        abort_unless($request->user()->can(Permission::SupplierFulfillOrders->value), 403);
        $data = $request->validate(['supplier_response_type' => ['required', Rule::in(['replacement_scheduled', 'supplemental_delivery', 'credit_requested', 'dispute', 'explanation', 'other'])], 'supplier_response' => ['required', 'string', 'max:2000']]);
        $discrepancy->update([...$data, 'status' => 'supplier_responded', 'responded_by' => $request->user()->id, 'responded_at' => now()]);
        $audit->log(AuditAction::RespondedToSupplierDiscrepancy, $request->user(), 'Supplier responded to a receiving discrepancy.', $discrepancy, "Discrepancy {$discrepancy->id}", newValues: ['status' => 'supplier_responded', 'response_type' => $data['supplier_response_type']]);
        return back()->with('success', 'Discrepancy response submitted for hospital review.');
    }

    public function rfqs(Request $request): View
    {
        return view('supplier-portal.rfqs', ['invitations' => RfqSupplierInvitation::where('supplier_id', $request->user()->supplier_id)->with(['rfq.lines.item.category', 'rfq.quotes' => fn ($q) => $q->where('supplier_id', $request->user()->supplier_id)])->latest('invited_at')->paginate(10)]);
    }

    public function submitBid(Request $request, RfqSupplierInvitation $invitation, AuditLogger $audit): RedirectResponse
    {
        $this->own($request, $invitation->supplier_id);
        abort_unless($request->user()->can(Permission::SupplierSubmitBids->value), 403);
        $rfq = $invitation->rfq()->with('lines')->firstOrFail();
        abort_if($rfq->isBiddingClosed() || $rfq->status->value !== 'published', 422, 'Bidding is closed.');
        $data = $request->validate(['quote_number' => ['required', 'string', 'max:60'], 'payment_terms' => ['nullable', 'string', 'max:100'], 'payment_terms_custom' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $request->input('payment_terms') === 'other')], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array'], 'lines.*.rfq_line_item_id' => ['required', 'integer'], 'lines.*.offered_unit_price' => ['required', 'numeric', 'min:0'], 'lines.*.offered_quantity' => ['required', 'integer', 'min:1'], 'lines.*.lead_time_days' => ['required', 'integer', 'min:0', 'max:3650']]);
        $paymentTerms = ($data['payment_terms'] ?? null) === 'other' ? $data['payment_terms_custom'] : ($data['payment_terms'] ?? null);
        $quote = DB::transaction(function () use ($data, $paymentTerms, $rfq, $invitation) {
            $valid = $rfq->lines->keyBy('id'); $total = 0;
            foreach ($data['lines'] as $line) { if (! $valid->has($line['rfq_line_item_id'])) throw ValidationException::withMessages(['lines' => 'Bid lines must belong to the invited RFQ.']); $total += $line['offered_unit_price'] * $line['offered_quantity']; }
            $quote = SupplierQuote::updateOrCreate(['sourcing_rfq_id' => $rfq->id, 'supplier_id' => $invitation->supplier_id], ['quote_number' => $data['quote_number'], 'quoted_price' => $total, 'total_bid_amount' => $total, 'currency' => $rfq->currency, 'status' => QuoteStatus::Submitted->value, 'is_sealed' => true, 'notes' => $data['notes'] ?? null, 'payment_terms' => $paymentTerms]);
            $quote->lines()->delete(); foreach ($data['lines'] as $line) { $model = new QuoteLineItem($line); $model->computeLandedCost(); $quote->lines()->save($model); }
            $invitation->update(['status' => 'submitted', 'acknowledged_at' => now()]); return $quote;
        });
        $audit->log(AuditAction::SubmittedSupplierQuote, $request->user(), "Submitted bid for {$rfq->rfq_number}.", $quote, $data['quote_number']);
        return back()->with('success', 'Bid submitted securely. Competitor bids remain inaccessible.');
    }

    public function invoices(Request $request): View
    {
        return view('supplier-portal.invoices', ['invoices' => SupplierInvoice::where('supplier_id', $request->user()->supplier_id)->with('purchaseOrder')->latest('submitted_at')->paginate(10), 'orders' => PurchaseOrder::where('supplier_id', $request->user()->supplier_id)->whereIn('status', [PurchaseOrderStatus::PartiallyFulfilled->value, PurchaseOrderStatus::Fulfilled->value, PurchaseOrderStatus::Received->value])->with('lines.item')->get()]);
    }

    public function submitInvoice(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::SupplierManageInvoices->value), 403);
        $data = $request->validate(['purchase_order_id' => ['required', 'integer'], 'invoice_number' => ['required', 'string', 'max:80'], 'invoice_date' => ['required', 'date', 'before_or_equal:today'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.po_line_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'integer', 'min:1'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0']]);
        $po = PurchaseOrder::where('supplier_id', $request->user()->supplier_id)->with('lines')->findOrFail($data['purchase_order_id']);
        $invoice = DB::transaction(function () use ($data, $po, $request) {
            $poLines = $po->lines->keyBy('id'); $total = 0; $matched = true; $rows = [];
            foreach ($data['lines'] as $line) { $poLine = $poLines->get($line['po_line_id']); if (! $poLine) throw ValidationException::withMessages(['lines' => 'Invoice lines must belong to the selected purchase order.']); $lineTotal = round($line['quantity'] * $line['unit_price'], 2); $lineMatched = $line['quantity'] <= $poLine->accepted_quantity && round((float)$line['unit_price'], 2) === round((float)$poLine->unit_price, 2); $matched = $matched && $lineMatched; $total += $lineTotal; $rows[] = [...$line, 'line_total' => $lineTotal, 'match_status' => $lineMatched ? 'matched' : 'exception']; }
            $invoice = SupplierInvoice::create(['supplier_id' => $po->supplier_id, 'purchase_order_id' => $po->id, 'invoice_number' => $data['invoice_number'], 'invoice_date' => $data['invoice_date'], 'total_amount' => $total, 'status' => $matched ? 'matched' : 'exception', 'match_notes' => $matched ? 'PO price and accepted receipt quantities match.' : 'Quantity or unit price differs from the PO/accepted receipt.', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
            $invoice->lines()->createMany($rows); return $invoice;
        });
        $audit->log(AuditAction::SubmittedSupplierInvoice, $request->user(), "Submitted invoice {$invoice->invoice_number}.", $invoice, $invoice->invoice_number, newValues: ['status' => $invoice->status]);
        return back()->with('success', "Invoice submitted with status: {$invoice->status}.");
    }

    public function catalog(Request $request): View
    {
        return view('supplier-portal.catalog', [
            'products' => SupplierProduct::where('supplier_id', $request->user()->supplier_id)->with('item')->orderBy('supplier_product_name')->paginate(10),
            'items' => InventoryItem::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function compliance(Request $request): View
    {
        return view('supplier-portal.compliance', [
            'documents' => SupplierDocument::where('supplier_id', $request->user()->supplier_id)
                ->where('is_current', true)
                ->latest()
                ->paginate(10),
        ]);
    }

    public function uploadComplianceDocument(Request $request, FileContentValidator $fileContentValidator, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::SupplierManageProfile->value), 403);
        $supplier = $request->user()->supplier;
        abort_if($supplier->company_profile_status === SupplierCompanyProfileStatus::PendingReview, 422, 'Documents cannot be changed while the company profile is under review.');
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'replaces_document_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_documents', 'id')->where(fn ($query) => $query
                    ->where('supplier_id', $supplier->id)
                    ->where('is_current', true)
                    ->where('verification_status', SupplierDocumentStatus::Pending->value)),
            ],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'min:1', 'max:10240'],
        ]);
        $file = $request->file('file');
        try {
            $fileContentValidator->validate($file, ['pdf', 'jpg', 'jpeg', 'png']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $path = $file->store('supplier-documents/'.$supplier->id, 'local');
        abort_if($path === false, 500, 'The supplier document could not be stored.');
        try {
            DB::transaction(function () use ($supplier, $data, $file, $path, $request, $audit): void {
                $replacement = isset($data['replaces_document_id'])
                    ? $supplier->documents()->whereKey($data['replaces_document_id'])->where('is_current', true)->where('verification_status', SupplierDocumentStatus::Pending->value)->lockForUpdate()->firstOrFail()
                    : null;
                $document = $supplier->documents()->create([
                    ...collect($data)->except(['file', 'replaces_document_id'])->all(),
                    'document_type' => $replacement?->document_type ?? $data['document_type'],
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
                $replacement?->update(['is_current' => false, 'superseded_by_id' => $document->id]);
                $audit->log(AuditAction::UploadedSupplierDocument, $request->user(), 'Supplier uploaded compliance evidence for hospital review.', $document, $document->original_name, newValues: ['supplier_id' => $supplier->id, 'document_type' => $document->document_type, 'replaces_document_id' => $replacement?->id]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Compliance document uploaded for hospital review.');
    }

    public function removeCompanyProfileDocument(Request $request, SupplierDocument $document, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::SupplierManageProfile->value), 403);
        $supplier = $request->user()->supplier;
        $this->own($request, $document->supplier_id);
        abort_if($supplier->company_profile_status === SupplierCompanyProfileStatus::PendingReview, 422, 'Documents cannot be removed while the company profile is under review.');
        abort_unless($document->is_current && $document->verification_status === SupplierDocumentStatus::Pending, 422, 'Only current documents awaiting review can be removed.');

        DB::transaction(function () use ($document, $request, $audit): void {
            $predecessor = SupplierDocument::where('superseded_by_id', $document->id)->lockForUpdate()->first();
            $audit->log(AuditAction::RemovedSupplierDocument, $request->user(), 'Removed a pending supplier document before profile submission.', $document, $document->original_name, newValues: ['supplier_id' => $document->supplier_id, 'document_type' => $document->document_type]);
            $document->delete();
            $predecessor?->update(['is_current' => true, 'superseded_by_id' => null]);
        });
        Storage::disk($document->disk)->delete($document->path);

        return back()->with('success', 'Pending document removed.');
    }

    public function downloadComplianceDocument(Request $request, SupplierDocument $document, AuditLogger $audit): StreamedResponse
    {
        $this->own($request, $document->supplier_id);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        $audit->log(AuditAction::DownloadedSupplierDocument, $request->user(), 'Supplier downloaded its compliance evidence.', $document, $document->original_name, newValues: ['supplier_id' => $document->supplier_id]);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function submitCatalogProduct(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::SupplierManageProfile->value), 403);
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:inventory_items,id', Rule::unique('supplier_products')->where(fn ($query) => $query->where('supplier_id', $request->user()->supplier_id))],
            'supplier_sku' => ['required', 'string', 'max:100'],
            'gtin' => ['nullable', 'regex:/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/'],
            'supplier_product_name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'pack_size' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:40'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);
        $product = SupplierProduct::create([...$data, 'supplier_id' => $request->user()->supplier_id, 'approval_status' => 'pending_review', 'is_active' => false]);
        $audit->log(AuditAction::AddedSupplierProduct, $request->user(), 'Submitted a supplier catalogue product for hospital review.', $product, $product->supplier_product_name, newValues: ['approval_status' => 'pending_review']);

        return back()->with('success', 'Catalog product submitted for hospital approval. The item master was not changed.');
    }

    public function performance(Request $request): View
    {
        return view('supplier-portal.performance', ['supplier' => $request->user()->supplier->load('latestApprovedScorecard')]);
    }

    private function own(Request $request, int $supplierId): void
    {
        abort_unless((int) $request->user()->supplier_id === $supplierId, 403);
    }
}
