<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Enums\ApprovalChainType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SourcingRfq;
use App\Models\SupplierQuote;
use App\Services\Procurement\ApprovalRoutingEngine;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

class AwardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:'.Permission::AwardProcurement->value];
    }

    public function __construct(
        private readonly ApprovalRoutingEngine $approvalEngine,
        private readonly ProcurementAuditService $auditService
    ) {}

    /**
     * Execute supplier award recommendation and initiate DOA approval workflow.
     */
    public function award(Request $request, int $rfqId): JsonResponse
    {
        $rfq = SourcingRfq::with('quotes.supplier')->findOrFail($rfqId);

        $validated = $request->validate([
            'supplier_quote_id' => ['required', 'exists:supplier_quotes,id'],
            'justification_notes' => ['nullable', 'string'],
        ]);

        $quote = SupplierQuote::where('sourcing_rfq_id', $rfq->id)
            ->with('supplier')
            ->findOrFail($validated['supplier_quote_id']);

        if (! $quote->supplier->isProcurementEligible()) {
            throw new DomainException("Compliance Block: Supplier '{$quote->supplier->name}' is not eligible for procurement awards.");
        }

        $user = $request->user();

        return DB::transaction(function () use ($rfq, $quote, $validated, $user) {
            $awardedAmount = $quote->totalLandedCost();

            // Instantiate dynamic Delegation of Authority (DOA) approval chain for award
            $chain = $this->approvalEngine->instantiateChain(
                ApprovalChainType::SourcingAward,
                $rfq->id,
                $awardedAmount,
                $user
            );

            $this->auditService->record(
                $user,
                'SourcingRfq',
                $rfq->id,
                'awarded_sourcing_rfq',
                null,
                [
                    'quote_id' => $quote->id,
                    'supplier_id' => $quote->supplier_id,
                    'awarded_amount' => $awardedAmount,
                    'approval_chain_id' => $chain->id,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Award recommendation recorded for Supplier '{$quote->supplier->name}'. DOA approval workflow instantiated.",
                'data' => [
                    'rfq_id' => $rfq->id,
                    'awarded_quote_id' => $quote->id,
                    'supplier' => $quote->supplier->name,
                    'total_award_amount' => $awardedAmount,
                    'approval_chain_id' => $chain->id,
                    'steps_count' => $chain->steps()->count(),
                ],
            ]);
        });
    }
}
