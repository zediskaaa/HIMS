<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\SourcingRfq;
use App\Services\Procurement\EvaluationEngine;
use App\Services\Procurement\ProcurementAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationEngine $evaluationEngine,
        private readonly ProcurementAuditService $auditService
    ) {}

    /**
     * Execute comparative scoring matrix across submitted quotations.
     */
    public function evaluate(Request $request, int $rfqId): JsonResponse
    {
        $rfq = SourcingRfq::with(['quotes.supplier', 'lines.item'])->findOrFail($rfqId);
        $user = $request->user();

        $evaluations = $this->evaluationEngine->evaluateRfq($rfq, $user);
        $splitAwards = $this->evaluationEngine->computeSplitAwardOptimization($rfq);

        $this->auditService->record(
            $user,
            'SourcingRfq',
            $rfq->id,
            'evaluated_sourcing_rfq',
            null,
            ['evaluations_count' => $evaluations->count()]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Comparative scoring matrix evaluated successfully.',
            'data' => [
                'rfq_id' => $rfq->id,
                'rfq_number' => $rfq->rfq_number,
                'status' => $rfq->fresh()->status->value,
                'evaluations' => $evaluations,
                'recommended_single_winner' => $evaluations->first(),
                'split_award_optimization' => $splitAwards,
            ],
        ]);
    }
}
