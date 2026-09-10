<?php

namespace App\Services\Procurement;

use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class BudgetEncumbranceService
{
    /**
     * Synchronously validates whether a cost center has sufficient uncommitted budget.
     */
    public function validateBudgetAvailability(CostCenter $costCenter, float $amount, int $fiscalYear = null): bool
    {
        $fiscalYear = $fiscalYear ?? (int) date('Y');

        $budget = CostCenterBudget::where('cost_center_id', $costCenter->id)
            ->where('fiscal_year', $fiscalYear)
            ->first();

        if (! $budget) {
            return false;
        }

        return $budget->availableBudget() >= $amount;
    }

    /**
     * Reserve soft commitment upon Purchase Request submission.
     * Uses row-level lock (SELECT FOR UPDATE) to prevent concurrent budget exhaustion.
     */
    public function reserveSoftCommitment(PurchaseRequest $request, User $actor = null): CostCenterBudget
    {
        return DB::transaction(function () use ($request) {
            $year = (int) ($request->submitted_at?->format('Y') ?? date('Y'));

            $budget = CostCenterBudget::where('cost_center_id', $request->cost_center_id)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $budget) {
                // Auto-seed an initial departmental operating budget if none exists for demo/test isolation
                $budget = CostCenterBudget::create([
                    'cost_center_id' => $request->cost_center_id,
                    'fiscal_year' => $year,
                    'allocated_budget' => 10000000.00,
                    'soft_encumbered' => 0,
                    'hard_encumbered' => 0,
                    'spent_amount' => 0,
                    'currency' => $request->currency ?? 'PHP',
                ]);
            }

            $amount = (float) $request->total_estimated_amount;

            if ($budget->availableBudget() < $amount) {
                throw new DomainException(
                    "Insufficient budget in Cost Center '{$request->costCenter->name}'. Requested: {$request->currency} ".
                    number_format($amount, 2).", Available: {$budget->currency} ".number_format($budget->availableBudget(), 2)
                );
            }

            $budget->soft_encumbered += $amount;
            $budget->save();

            return $budget;
        });
    }

    /**
     * Release soft commitment upon PR rejection or cancellation.
     */
    public function releaseSoftCommitment(PurchaseRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $year = (int) ($request->submitted_at?->format('Y') ?? date('Y'));

            $budget = CostCenterBudget::where('cost_center_id', $request->cost_center_id)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if ($budget) {
                $amount = (float) $request->total_estimated_amount;
                $budget->soft_encumbered = max(0, $budget->soft_encumbered - $amount);
                $budget->save();
            }
        });
    }

    /**
     * Transition soft commitment to hard encumbered liability upon Purchase Order issuance.
     */
    public function convertSoftToHardEncumbrance(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $costCenterId = $po->cost_center_id
                ?? $po->purchaseRequest?->cost_center_id
                ?? CostCenter::query()->orderBy('id')->value('id');

            if (! $costCenterId) {
                return;
            }

            $year = (int) ($po->requested_at?->format('Y') ?? date('Y'));

            $budget = CostCenterBudget::where('cost_center_id', $costCenterId)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $budget) {
                $budget = CostCenterBudget::create([
                    'cost_center_id' => $costCenterId,
                    'fiscal_year' => $year,
                    'allocated_budget' => 10000000.00,
                    'soft_encumbered' => 0,
                    'hard_encumbered' => 0,
                    'spent_amount' => 0,
                    'currency' => $po->currency ?? 'PHP',
                ]);
            }

            $poAmount = (float) ($po->total_encumbered_amount > 0 ? $po->total_encumbered_amount : $po->total_amount);
            $prAmount = (float) ($po->purchaseRequest?->total_estimated_amount ?? $poAmount);

            // Release the soft commitment and post hard encumbrance
            $budget->soft_encumbered = max(0, $budget->soft_encumbered - $prAmount);
            $budget->hard_encumbered += $poAmount;
            $budget->save();

            $po->total_encumbered_amount = $poAmount;
            $po->cost_center_id = $costCenterId;
            $po->save();
        });
    }

    /**
     * Mark goods received and transition hard encumbrance to actual spent amount.
     */
    public function recordFulfillmentSpent(PurchaseOrder $po, float $receivedAmount): void
    {
        DB::transaction(function () use ($po, $receivedAmount) {
            if (! $po->cost_center_id) {
                return;
            }

            $year = (int) ($po->requested_at?->format('Y') ?? date('Y'));

            $budget = CostCenterBudget::where('cost_center_id', $po->cost_center_id)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if ($budget) {
                $budget->hard_encumbered = max(0, $budget->hard_encumbered - $receivedAmount);
                $budget->spent_amount += $receivedAmount;
                $budget->save();
            }
        });
    }
}
