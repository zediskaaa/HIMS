<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\SupplierQuote;
use App\Services\Procurement\POConversionService;
use App\Services\Procurement\ProcurementAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderGenerationController extends Controller
{
    public function __construct(
        private readonly POConversionService $conversionService,
        private readonly ProcurementAuditService $auditService
    ) {}

    /**
     * Convert approved sourcing award into an encumbered legally binding Purchase Order.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sourcing_rfq_id' => ['required_without:purchase_request_id', 'nullable', 'exists:sourcing_rfqs,id'],
            'supplier_quote_id' => ['required_with:sourcing_rfq_id', 'nullable', 'exists:supplier_quotes,id'],
            'purchase_request_id' => ['required_without:sourcing_rfq_id', 'nullable', 'exists:purchase_requests,id'],
        ]);

        $user = $request->user();

        if (! empty($validated['sourcing_rfq_id'])) {
            $rfq = SourcingRfq::with('purchaseRequest')->findOrFail($validated['sourcing_rfq_id']);
            $quote = SupplierQuote::with(['supplier', 'lines'])->findOrFail($validated['supplier_quote_id']);

            $po = $this->conversionService->convertAwardToPO($rfq, $quote, $user);
        } else {
            $pr = PurchaseRequest::with('lines.contract.supplier')->findOrFail($validated['purchase_request_id']);
            $po = $this->conversionService->convertCatalogPRToPO($pr, $user);
        }

        $this->auditService->record(
            $user,
            'PurchaseOrder',
            $po->id,
            'issued_purchase_order',
            null,
            ['po_number' => $po->po_number, 'amount' => (float) $po->total_amount, 'version' => $po->version]
        );

        $po->load(['lines.item', 'supplier']);

        return response()->json([
            'status' => 'success',
            'message' => "Purchase Order {$po->po_number} generated and encumbered liability posted.",
            'data' => [
                'purchase_order' => $po,
                'cxml_dispatch_ready' => filled($po->cxml_payload),
            ],
        ], 201);
    }
}
