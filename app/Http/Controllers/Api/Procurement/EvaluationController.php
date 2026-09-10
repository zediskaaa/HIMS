<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SourcingRfq;
use App\Services\Procurement\EvaluationEngine;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EvaluationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::EvaluateBids->value),
        ];
    }

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

        try {
            $evaluations = $this->evaluationEngine->evaluateRfq($rfq, $user);
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
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
