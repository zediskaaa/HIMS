<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Enums\QuoteStatus;
use App\Enums\RfqBiddingType;
use App\Http\Controllers\Controller;
use App\Models\RfqLineItem;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Services\Procurement\EvaluationEngine;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function __construct(
        private readonly EvaluationEngine $evaluationEngine,
        private readonly ProcurementAuditService $auditService
    ) {}

    /**
     * Submit supplier quotation/bid against a Sourcing RFQ.
     * Enforces deadline and sealed-bid governance.
     */
    public function store(Request $request, int $rfqId): JsonResponse
    {
        $rfq = SourcingRfq::with('lines')->findOrFail($rfqId);

        // Strict submission deadline enforcement using trusted server time
        if ($rfq->isDeadlineElapsed()) {
            return response()->json([
                'status' => 'error',
                'message' => "Deadline Elapse Violation: RFQ #{$rfq->rfq_number} bidding window closed at {$rfq->submission_deadline->toIso8601String()}. Late submissions are rejected.",
            ], 422);
        }

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'portal_token' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'incoterms' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'validity_end_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.rfq_line_item_id' => ['required', 'exists:rfq_line_items,id'],
            'lines.*.offered_unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.offered_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.lead_time_days' => ['required', 'integer', 'min:1'],
            'lines.*.shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tariffs_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.handling_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.technical_compliance' => ['nullable', 'boolean'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);

        // Check supplier eligibility
        if (! $supplier->isProcurementEligible()) {
            return response()->json([
                'status' => 'error',
                'message' => "Supplier '{$supplier->name}' is currently ineligible for bidding due to accreditation expiration or compliance issues.",
            ], 422);
        }

        $isSealed = $rfq->bidding_type === RfqBiddingType::Sealed;
        $exchangeRate = (float) ($validated['exchange_rate'] ?? 1.0);

        return DB::transaction(function () use ($rfq, $supplier, $validated, $isSealed, $exchangeRate, $request) {
            $quoteNumber = 'QTN-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $quote = SupplierQuote::create([
                'sourcing_rfq_id' => $rfq->id,
                'supplier_id' => $supplier->id,
                'quote_number' => $quoteNumber,
                'currency' => $validated['currency'] ?? 'PHP',
                'exchange_rate' => $exchangeRate,
                'incoterms' => $validated['incoterms'] ?? 'DDP',
                'payment_terms' => $validated['payment_terms'] ?? 'Net 30',
                'validity_end_date' => $validated['validity_end_date'] ?? now()->addDays(30)->toDateString(),
                'status' => QuoteStatus::Submitted->value,
                'is_sealed' => $isSealed,
                'unsealed_at' => null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $totalBidAmount = 0.0;

            foreach ($validated['lines'] as $lineData) {
                $rfqLine = RfqLineItem::find($lineData['rfq_line_item_id']);

                $unitPrice = (float) $lineData['offered_unit_price'];
                $quantity = (int) $lineData['offered_quantity'];
                $shipping = (float) ($lineData['shipping_cost'] ?? 0);
                $tariffs = (float) ($lineData['tariffs_cost'] ?? 0);
                $handling = (float) ($lineData['handling_cost'] ?? 0);
                $discount = (float) ($lineData['discount_amount'] ?? 0);

                $landedCost = $this->evaluationEngine->computeLandedCost(
                    $unitPrice,
                    $quantity,
                    $shipping,
                    $tariffs,
                    $handling,
                    $discount,
                    $exchangeRate
                );

                $quote->lines()->create([
                    'rfq_line_item_id' => $rfqLine->id,
                    'offered_unit_price' => $unitPrice,
                    'offered_quantity' => $quantity,
                    'lead_time_days' => (int) $lineData['lead_time_days'],
                    'shipping_cost' => $shipping,
                    'tariffs_cost' => $tariffs,
                    'handling_cost' => $handling,
                    'discount_amount' => $discount,
                    'landed_cost' => $landedCost,
                    'technical_compliance' => (bool) ($lineData['technical_compliance'] ?? true),
                    'technical_score' => (bool) ($lineData['technical_compliance'] ?? true) ? 100.0 : 50.0,
                    'notes' => $lineData['notes'] ?? null,
                ]);

                $totalBidAmount += $landedCost;
            }

            $quote->total_bid_amount = $totalBidAmount;
            $quote->quoted_price = $totalBidAmount;
            $quote->save();

            // Update invitation status if applicable
            $rfq->invitations()->where('supplier_id', $supplier->id)->update([
                'status' => 'submitted',
            ]);

            $this->auditService->record(
                $request->user(),
                'SupplierQuote',
                $quote->id,
                'submitted_supplier_quote',
                null,
                ['quote_number' => $quote->quote_number, 'supplier_id' => $supplier->id, 'sealed' => $isSealed]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier quotation bid submitted successfully.',
                'data' => [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'status' => $quote->status,
                    'is_sealed' => $quote->is_sealed,
                    'line_items_count' => $quote->lines()->count(),
                ],
            ], 201);
        });
    }
}
