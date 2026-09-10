<?php

namespace App\Services\Procurement;

use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Models\RfqLineItem;
use App\Models\SourcingEvaluation;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EvaluationEngine
{
    /**
     * Compute Landed Cost for a line item quote in the buying entity's functional currency.
     * Landed Cost = (Base Unit Price * Quantity) + Shipping + Tariffs + Handling - Payment Term Discounts
     */
    public function computeLandedCost(
        float $unitPrice,
        int $quantity,
        float $shipping = 0,
        float $tariffs = 0,
        float $handling = 0,
        float $discount = 0,
        float $exchangeRate = 1.0
    ): float {
        $baseExtended = $unitPrice * $quantity;
        $landedInForeign = ($baseExtended + $shipping + $tariffs + $handling) - $discount;
        $normalizedLanded = max(0, $landedInForeign * ($exchangeRate > 0 ? $exchangeRate : 1.0));

        return round($normalizedLanded, 2);
    }

    /**
     * Execute comparative evaluation across all submitted quotes for a Sourcing RFQ.
     * Enforces sealed-bid unsealing condition.
     *
     * @return Collection<int, SourcingEvaluation>
     */
    public function evaluateRfq(SourcingRfq $rfq, User $evaluator): Collection
    {
        if ($rfq->status === RfqStatus::Draft) {
            throw new DomainException("Cannot evaluate RFQ #{$rfq->rfq_number}: RFQ package is still in draft.");
        }

        if ($rfq->status === RfqStatus::Cancelled) {
            throw new DomainException("Cannot evaluate RFQ #{$rfq->rfq_number}: Sourcing tender has been cancelled.");
        }

        if ($rfq->status === RfqStatus::Awarded) {
            throw new DomainException("Cannot evaluate RFQ #{$rfq->rfq_number}: Sourcing tender has already been awarded.");
        }

        if ($rfq->isSealed() && ! $rfq->isDeadlineElapsed()) {
            throw new DomainException(
                "Sealed Bid Protocol Violation: Cannot evaluate RFQ #{$rfq->rfq_number} before submission deadline ({$rfq->submission_deadline->toIso8601String()}) elapses."
            );
        }

        return DB::transaction(function () use ($rfq, $evaluator) {
            // Lifecycle transition: Published -> BiddingClosed -> UnderEvaluation
            if ($rfq->status === RfqStatus::Published && $rfq->isDeadlineElapsed()) {
                $rfq->status = RfqStatus::BiddingClosed;
                $rfq->save();
            }

            // Unseal the RFQ and advance to UnderEvaluation
            $rfq->unsealed_at = $rfq->unsealed_at ?? now();
            $rfq->unsealed_by_user_id = $rfq->unsealed_by_user_id ?? $evaluator->id;
            $rfq->status = RfqStatus::UnderEvaluation;
            $rfq->save();

            // Fetch only valid quotations that are submitted or under review
            $quotes = $rfq->quotes()
                ->whereIn('status', [QuoteStatus::Submitted->value, QuoteStatus::UnderReview->value])
                ->with(['lines', 'supplier'])
                ->get();

            if ($quotes->isEmpty()) {
                if (! $rfq->quotes()->exists()) {
                    throw new DomainException("Cannot evaluate RFQ #{$rfq->rfq_number}: No supplier quotes have been submitted.");
                }

                throw new DomainException("Cannot evaluate RFQ #{$rfq->rfq_number}: No valid submitted supplier quotes are available for evaluation.");
            }

            // 1. Calculate and normalize landed costs for each quote
            $quoteSummaries = [];
            foreach ($quotes as $quote) {
                $quote->is_sealed = false;
                $quote->unsealed_at = $quote->unsealed_at ?? now();
                $quote->status = 'under_review';
                $quote->save();

                $totalLanded = 0.0;
                $totalTechScore = 0.0;
                $lineCount = 0;
                $maxLeadTime = 0;

                if ($quote->lines()->exists()) {
                    foreach ($quote->lines as $line) {
                        $lineLanded = $this->computeLandedCost(
                            (float) $line->offered_unit_price,
                            (int) $line->offered_quantity,
                            (float) $line->shipping_cost,
                            (float) $line->tariffs_cost,
                            (float) $line->handling_cost,
                            (float) $line->discount_amount,
                            (float) ($quote->exchange_rate ?? 1.0)
                        );
                        $line->landed_cost = $lineLanded;
                        $line->save();

                        $totalLanded += $lineLanded;
                        $totalTechScore += (float) $line->technical_score;
                        $maxLeadTime = max($maxLeadTime, (int) $line->lead_time_days);
                        $lineCount++;
                    }
                } else {
                    // Single-line or flat quote fallback
                    $totalLanded = $this->computeLandedCost(
                        (float) ($quote->total_bid_amount > 0 ? $quote->total_bid_amount : $quote->quoted_price),
                        1,
                        0, 0, 0, 0,
                        (float) ($quote->exchange_rate ?? 1.0)
                    );
                    $totalTechScore = 95.0;
                    $lineCount = 1;
                    $maxLeadTime = 7;
                }

                $avgTech = $lineCount > 0 ? ($totalTechScore / $lineCount) : 80.0;
                $qualityScore = $this->resolveSupplierQualityRating($quote->supplier);

                $quoteSummaries[$quote->id] = [
                    'quote' => $quote,
                    'total_landed' => $totalLanded,
                    'technical_score' => round($avgTech, 2),
                    'quality_score' => $qualityScore,
                    'lead_time_days' => $maxLeadTime,
                ];
            }

            // 2. Identify lowest compliant landed price (P_min)
            $minLandedCost = collect($quoteSummaries)->min('total_landed');
            if ($minLandedCost <= 0) {
                $minLandedCost = 1.0;
            }

            // 3. Score each candidate against weights
            $wPrice = (float) ($rfq->weight_price ?? 0.40);
            $wTech = (float) ($rfq->weight_technical ?? 0.30);
            $wQual = (float) ($rfq->weight_quality ?? 0.15);
            $wLead = (float) ($rfq->weight_lead_time ?? 0.15);

            $evaluations = collect();

            foreach ($quoteSummaries as $quoteId => $data) {
                $candidatePrice = $data['total_landed'];

                // Inverted linear ratio against lowest compliant bidder: S_price = (P_min / P_candidate) * 100
                $commercialScore = $candidatePrice > 0
                    ? round(($minLandedCost / $candidatePrice) * 100, 2)
                    : 100.0;
                $commercialScore = min(100.0, max(0.0, $commercialScore));

                $technicalScore = min(100.0, max(0.0, $data['technical_score']));
                $qualityScore = min(100.0, max(0.0, $data['quality_score']));

                // Lead time score: benchmarked against standard 14 calendar days
                $leadDays = $data['lead_time_days'];
                $leadScore = $leadDays <= 7 ? 100.0 : max(30.0, 100.0 - (($leadDays - 7) * 4));

                // Composite linear sum: S_vendor = w_p * S_price + w_t * S_tech + w_q * S_qual + w_l * S_lead
                $compositeScore = round(
                    ($wPrice * $commercialScore) +
                    ($wTech * $technicalScore) +
                    ($wQual * $qualityScore) +
                    ($wLead * $leadScore),
                    2
                );

                $evaluation = SourcingEvaluation::updateOrCreate(
                    [
                        'sourcing_rfq_id' => $rfq->id,
                        'supplier_quote_id' => $quoteId,
                        'evaluator_user_id' => $evaluator->id,
                    ],
                    [
                        'commercial_score' => $commercialScore,
                        'technical_score' => $technicalScore,
                        'quality_score' => $qualityScore,
                        'lead_time_score' => $leadScore,
                        'composite_score' => $compositeScore,
                        'normalized_landed_cost' => $candidatePrice,
                        'conflict_of_interest_declared' => false,
                        'justification_notes' => "Multi-attribute scoring: Commercial ({$commercialScore}%), Tech ({$technicalScore}%), Quality ({$qualityScore}%), Lead ({$leadScore}%).",
                        'completed_at' => now(),
                    ]
                );

                $evaluations->push($evaluation);
            }

            return $evaluations->sortByDesc('composite_score')->values();
        });
    }

    /**
     * Compute split-award optimization across line items to achieve lowest landed cost
     * with technical compliance.
     *
     * @return array<int, array{rfq_line_id: int, winning_quote_id: int, supplier_id: int, landed_cost: float}>
     */
    public function computeSplitAwardOptimization(SourcingRfq $rfq): array
    {
        $splitAwards = [];

        foreach ($rfq->lines as $rfqLine) {
            $competingLines = \App\Models\QuoteLineItem::where('rfq_line_item_id', $rfqLine->id)
                ->where('technical_compliance', true)
                ->with('quote')
                ->get();

            if ($competingLines->isEmpty()) {
                continue;
            }

            // Find lowest landed cost for this line
            $bestLine = $competingLines->sortBy('landed_cost')->first();

            $splitAwards[$rfqLine->id] = [
                'rfq_line_id' => $rfqLine->id,
                'quote_line_id' => $bestLine->id,
                'winning_quote_id' => $bestLine->supplier_quote_id,
                'supplier_id' => $bestLine->quote->supplier_id,
                'landed_cost' => (float) $bestLine->landed_cost,
                'offered_unit_price' => (float) $bestLine->offered_unit_price,
            ];
        }

        return $splitAwards;
    }

    /**
     * Query historical vendor performance rating from Vendor Master.
     */
    private function resolveSupplierQualityRating(Supplier $supplier): float
    {
        if (! $supplier->isProcurementEligible()) {
            return 40.0;
        }

        $base = 90.0;

        // Bonus for long-standing approved accreditation
        if ($supplier->effectiveAccreditationStatus()->value === 'approved') {
            $base += 5.0;
        }

        // Check if supplier has open compliance alerts
        if ($supplier->complianceAlerts()->where('status', 'open')->exists()) {
            $base -= 15.0;
        }

        return min(100.0, max(50.0, $base));
    }
}
