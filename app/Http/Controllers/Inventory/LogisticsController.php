<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ChainOfCustodyLog;
use App\Models\GoodsReceiptNote;
use App\Models\InspectionAcceptanceReport;
use App\Models\LogisticsDocument;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\Logistics\ChainOfCustodyService;
use App\Services\Logistics\DocumentTrackingService;
use App\Services\Logistics\InspectionAcceptanceService;
use App\Services\Logistics\ShipmentTrackingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogisticsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewLogisticsRecords->value, only: [
                'dashboard',
            ]),
            new Middleware('can:'.Permission::ViewLogisticsSensitiveData->value, only: [
                'documents',
                'downloadDocument',
                'shipments',
                'iarIndex',
                'iarShow',
                'chainOfCustody',
                'risShow',
            ]),
            new Middleware('can:'.Permission::ManageLogisticsRecords->value, only: [
                'uploadDocument',
                'supersedeDocument',
                'storeShipment',
                'recordDockArrival',
                'generateIarFromReceipt',
            ]),
            new Middleware('can:'.Permission::VerifyLogisticsDocuments->value, only: [
                'verifyDocument',
            ]),
            new Middleware('can:'.Permission::PerformTechnicalInspection->value, only: [
                'performTechnicalInspection',
            ]),
            new Middleware('can:'.Permission::ApproveIarAcceptance->value, only: [
                'approveCustodialAcceptance',
                'transmitToCoa',
            ]),
        ];
    }

    public function __construct(
        protected DocumentTrackingService $documentService,
        protected InspectionAcceptanceService $iarService,
        protected ShipmentTrackingService $shipmentService,
        protected ChainOfCustodyService $custodyService,
        protected AuditLogger $audit,
    ) {}

    /**
     * Logistics & Records Central Executive Dashboard
     */
    public function dashboard(): View
    {
        $metrics = [
            'total_documents' => LogisticsDocument::where('status', '!=', 'archived')->count(),
            'pending_verification' => LogisticsDocument::whereIn('status', ['submitted', 'pending_verification'])->count(),
            'iar_pending_inspection' => InspectionAcceptanceReport::where('status', 'pending_inspection')->count(),
            'iar_pending_acceptance' => InspectionAcceptanceReport::where('status', 'inspected_passed')->count(),
            'iar_coa_due' => InspectionAcceptanceReport::where('status', 'accepted')
                ->whereNull('coa_transmitted_at')
                ->where('acceptance_date', '<=', now()->subDays(3)->toDateString())
                ->count(),
            'active_shipments' => Shipment::whereIn('status', ['dispatched', 'in_transit', 'customs_hold'])->count(),
            'dock_excursions' => Shipment::where('temp_excursion', true)->count(),
            'overdue_shipments' => Shipment::whereNotIn('status', ['arrived_at_dock', 'received'])
                ->whereNotNull('estimated_delivery_date')
                ->where('estimated_delivery_date', '<', now()->toDateString())
                ->count(),
        ];

        $recentShipments = Shipment::with(['purchaseOrder', 'supplier'])
            ->latest()
            ->take(6)
            ->get();

        $recentIars = InspectionAcceptanceReport::with(['purchaseOrder', 'goodsReceiptNote', 'supplier', 'inspectedBy', 'acceptedBy'])
            ->latest()
            ->take(6)
            ->get();

        $recentCustodyLogs = ChainOfCustodyLog::with(['releasingUser', 'receivingUser', 'trackable'])
            ->latest('transferred_at')
            ->take(8)
            ->get();

        $recentDocuments = LogisticsDocument::with(['uploadedBy', 'verifiedBy'])
            ->where('status', '!=', 'archived')
            ->latest()
            ->take(6)
            ->get();

        return view('inventory.logistics.index', compact(
            'metrics',
            'recentShipments',
            'recentIars',
            'recentCustodyLogs',
            'recentDocuments'
        ));
    }

    /**
     * Document Tracking Registry
     */
    public function documents(Request $request): View
    {
        $query = LogisticsDocument::with(['uploadedBy', 'verifiedBy', 'purchaseOrder', 'goodsReceiptNote']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('document_type')) {
            $query->where('document_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

        $purchaseOrders = PurchaseOrder::latest()->take(50)->get(['id', 'po_number', 'supplier_id']);
        $goodsReceiptNotes = GoodsReceiptNote::latest()->take(50)->get(['id', 'grn_number', 'dr_number', 'sales_invoice_number']);
        $documentTypes = DocumentType::cases();

        return view('inventory.logistics.documents', compact(
            'documents',
            'purchaseOrders',
            'goodsReceiptNotes',
            'documentTypes'
        ));
    }

    /**
     * Upload Logistics Document with SHA-256 Checksum & Retention Rules
     */
    public function uploadDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'document_type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'goods_receipt_note_id' => ['nullable', 'exists:goods_receipt_notes,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $doc = $this->documentService->uploadDocument(
                data: [
                    'document_type' => $validated['document_type'],
                    'title' => $validated['title'],
                    'reference_number' => $validated['document_number'] ?? null,
                    'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                    'goods_receipt_note_id' => $validated['goods_receipt_note_id'] ?? null,
                    'notes' => $validated['remarks'] ?? null,
                ],
                file: $request->file('file'),
                uploader: $request->user()
            );

            return redirect()->route('inventory.logistics.documents')
                ->with('success', "Document [{$doc->tracking_number}] uploaded successfully. SHA-256: ".substr($doc->sha256_checksum, 0, 12).'...');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Document upload failed: '.$e->getMessage());
        }
    }

    /**
     * Verify Logistics Document (Technical/Audit Validation)
     */
    public function verifyDocument(LogisticsDocument $document, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->documentService->verifyDocument(
                document: $document,
                decision: $validated['status'],
                notes: $validated['verification_notes'] ?? null,
                verifier: $request->user()
            );

            return redirect()->back()
                ->with('success', "Document [{$document->tracking_number}] status marked as {$validated['status']}.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Verification failed: '.$e->getMessage());
        }
    }

    /**
     * Supersede / Upload New Version of Document
     */
    public function supersedeDocument(LogisticsDocument $document, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $newDoc = $this->documentService->supersedeDocument(
                original: $document,
                data: [
                    'revision_reason' => $validated['reason'],
                ],
                newFile: $request->file('file'),
                actor: $request->user(),
                reason: $validated['reason']
            );

            return redirect()->route('inventory.logistics.documents')
                ->with('success', "Document [{$document->tracking_number}] superseded by new version [{$newDoc->tracking_number}].");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to supersede document: '.$e->getMessage());
        }
    }

    /**
     * Download Document File (Secure Local Storage Stream)
     */
    public function downloadDocument(Request $request, LogisticsDocument $document): StreamedResponse
    {
        $response = $this->documentService->downloadDocument($document);

        $this->audit->log(
            AuditAction::DownloadedLogisticsDocument,
            $request->user(),
            'Downloaded a protected logistics document.',
            $document,
            $document->tracking_number ?? $document->original_name,
            newValues: [
                'document_type' => $document->document_type?->value ?? $document->document_type,
            ],
        );

        return $response;
    }

    /**
     * Shipment & Carrier Tracking Dashboard
     */
    public function shipments(Request $request): View
    {
        $query = Shipment::with(['purchaseOrder', 'supplier', 'custodyLogs']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('carrier_name', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('sscc', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->filled('cold_chain')) {
            $query->where('is_cold_chain', (bool) $request->input('cold_chain'));
        }

        $shipments = $query->latest()->paginate(15)->withQueryString();

        $openPurchaseOrders = PurchaseOrder::whereNotIn('status', ['received', 'cancelled', 'rejected'])
            ->latest()
            ->take(50)
            ->get(['id', 'po_number', 'supplier_id', 'delivery_date']);

        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('inventory.logistics.shipments', compact('shipments', 'openPurchaseOrders', 'suppliers'));
    }

    /**
     * Register Inbound Shipment
     */
    public function storeShipment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'carrier_name' => ['required', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_contact' => ['nullable', 'string', 'max:50'],
            'sscc' => ['nullable', 'string', 'size:18'],
            'origin_address' => ['nullable', 'string', 'max:255'],
            'destination_facility' => ['nullable', 'string', 'max:255'],
            'dispatch_date' => ['nullable', 'date'],
            'estimated_delivery_date' => ['nullable', 'date'],
            'is_cold_chain' => ['nullable', 'boolean'],
            'temp_logger_serial' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $shipment = $this->shipmentService->registerInboundShipment($validated, $request->user());

            return redirect()->route('inventory.logistics.shipments')
                ->with('success', "Inbound shipment [{$shipment->shipment_number}] registered successfully.");
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Shipment registration failed: '.$e->getMessage());
        }
    }

    /**
     * Record Dock Arrival and Telemetry
     */
    public function recordDockArrival(Shipment $shipment, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'actual_delivery_date' => ['required', 'date'],
            'temp_min' => ['nullable', 'numeric'],
            'temp_max' => ['nullable', 'numeric'],
            'temp_logger_serial' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->shipmentService->recordDockArrival($shipment, $validated, $request->user());

            $msg = "Shipment [{$shipment->shipment_number}] marked as arrived at dock.";
            if ($shipment->fresh()->temp_excursion) {
                return redirect()->back()->with('warning', "{$msg} WARNING: Cold-chain temperature excursion detected! Lot placed in quarantine.");
            }

            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Dock arrival recording failed: '.$e->getMessage());
        }
    }

    /**
     * COA Inspection and Acceptance Reports (IAR - Appendix 50) Index
     */
    public function iarIndex(Request $request): View
    {
        $query = InspectionAcceptanceReport::with([
            'goodsReceiptNote',
            'purchaseOrder',
            'supplier',
            'inspectedBy',
            'acceptedBy',
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('iar_number', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', "%{$search}%"))
                    ->orWhereHas('goodsReceiptNote', fn ($grn) => $grn->where('dr_number', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $iars = $query->latest()->paginate(15)->withQueryString();

        // Receipts without an IAR
        $unreportedReceipts = GoodsReceiptNote::whereDoesntHave('inspectionAcceptanceReport')
            ->with(['purchaseOrder', 'supplier'])
            ->latest('received_at')
            ->take(30)
            ->get();

        return view('inventory.logistics.iar_index', compact('iars', 'unreportedReceipts'));
    }

    /**
     * Generate IAR from Goods Receipt Note
     */
    public function generateIarFromReceipt(GoodsReceiptNote $goodsReceiptNote, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'dr_number' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            if (! empty($validated['dr_number'])) {
                $goodsReceiptNote->update(['dr_number' => $validated['dr_number']]);
            }

            $iar = $this->iarService->createFromReceipt(
                grn: $goodsReceiptNote,
                data: $validated,
                actor: $request->user()
            );

            return redirect()->route('inventory.logistics.iar.show', $iar)
                ->with('success', "COA GAM Appendix 50 IAR [{$iar->iar_number}] successfully generated.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to generate IAR: '.$e->getMessage());
        }
    }

    /**
     * View COA GAM Appendix 50 Inspection & Acceptance Report
     */
    public function iarShow(InspectionAcceptanceReport $iar): View
    {
        $iar->load([
            'goodsReceiptNote.lines.item',
            'purchaseOrder.lines.item',
            'supplier',
            'inspectedBy',
            'acceptedBy',
            'documents',
            'custodyLogs.releasingUser',
            'custodyLogs.receivingUser',
        ]);

        return view('inventory.logistics.iar_show', compact('iar'));
    }

    /**
     * Perform Technical Inspection
     */
    public function performTechnicalInspection(InspectionAcceptanceReport $iar, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:inspected,rejected'],
            'inspection_date' => ['required', 'date'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->iarService->performTechnicalInspection(
                iar: $iar,
                data: [
                    'inspection_status' => $validated['status'] === 'inspected' ? 'in_order' : 'rejected',
                    'inspection_findings' => $validated['remarks'],
                ],
                inspector: $request->user()
            );

            return redirect()->back()->with('success', "Technical inspection completed for IAR [{$iar->iar_number}]. Status: {$validated['status']}.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Inspection execution failed: '.$e->getMessage());
        }
    }

    /**
     * Approve Custodial Acceptance
     */
    public function approveCustodialAcceptance(InspectionAcceptanceReport $iar, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'acceptance_type' => ['required', 'in:complete,partial'],
            'acceptance_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->iarService->performCustodialAcceptance(
                iar: $iar,
                data: [
                    'delivery_status' => $validated['acceptance_type'],
                    'notes' => $validated['remarks'] ?? null,
                ],
                custodian: $request->user()
            );

            $msg = "Custodial acceptance approved for IAR [{$iar->iar_number}].";
            if ($iar->fresh()->liquidated_damages_amount > 0) {
                $msg .= ' Liquidated damages assessed: ₱'.number_format($iar->fresh()->liquidated_damages_amount, 2);
            }

            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Acceptance approval failed: '.$e->getMessage());
        }
    }

    /**
     * Transmit IAR to Resident COA Auditor within 5 days
     */
    public function transmitToCoa(InspectionAcceptanceReport $iar, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transmittal_reference' => ['required', 'string', 'max:100'],
            'transmittal_date' => ['required', 'date'],
        ]);

        try {
            $this->iarService->transmitToCoa(
                iar: $iar,
                data: [
                    'coa_received_by' => $validated['transmittal_reference'],
                ],
                officer: $request->user()
            );

            return redirect()->back()->with('success', "IAR [{$iar->iar_number}] officially transmitted to Resident COA Auditor. Ref: {$validated['transmittal_reference']}.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'COA transmittal failed: '.$e->getMessage());
        }
    }

    /**
     * Chain of Custody Audit Ledger
     */
    public function chainOfCustody(Request $request): View
    {
        $query = ChainOfCustodyLog::with(['releasingUser', 'receivingUser', 'trackable']);

        if ($action = $request->input('action')) {
            $query->where('event_type', $action);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('releasing_party_name', 'like', "%{$search}%")
                    ->orWhere('receiving_party_name', 'like', "%{$search}%")
                    ->orWhere('origin_location', 'like', "%{$search}%")
                    ->orWhere('destination_location', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('transferred_at')->paginate(25)->withQueryString();

        return view('inventory.logistics.chain_of_custody', compact('logs'));
    }

    /**
     * COA GAM Appendix 63 Requisition and Issue Slip (RIS) Print View
     */
    public function risShow(MaterialRequisition $requisition): View
    {
        $requisition->load([
            'lines.item',
            'requestingUser',
            'approvedBy',
            'costCenter',
        ]);

        return view('inventory.logistics.ris_show', compact('requisition'));
    }
}
