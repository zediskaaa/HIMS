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
    public function validateBudgetAvailability(CostCenter $costCenter, float $amount, ?int $fiscalYear = null): bool
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
    public function reserveSoftCommitment(PurchaseRequest $request, ?User $actor = null): CostCenterBudget
    {
        return DB::transaction(function () use ($request) {
            $year = (int) ($request->submitted_at?->format('Y') ?? date('Y'));

            $budget = CostCenterBudget::where('cost_center_id', $request->cost_center_id)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $budget) {
                throw new DomainException("No budget configured for Cost Center #{$request->cost_center_id} in fiscal year {$year}.");
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
            $purchaseRequest = $po->purchaseRequest;
            $costCenterId = $po->cost_center_id ?? $purchaseRequest?->cost_center_id;

            if (! $costCenterId) {
                throw new DomainException("No cost center assigned to Purchase Order {$po->po_number}.");
            }

            if ($purchaseRequest && $purchaseRequest->cost_center_id !== $costCenterId) {
                throw new DomainException("Purchase Order {$po->po_number} and its purchase request have different cost centers.");
            }

            $year = (int) ($po->requested_at?->format('Y') ?? date('Y'));

            $budget = CostCenterBudget::where('cost_center_id', $costCenterId)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $budget) {
                throw new DomainException("No budget configured for Cost Center #{$costCenterId} in fiscal year {$year}.");
            }

            $poAmount = (float) ($po->total_encumbered_amount > 0 ? $po->total_encumbered_amount : $po->total_amount);
            $prAmount = (float) ($purchaseRequest?->total_estimated_amount ?? 0);

            if ($prAmount > (float) $budget->soft_encumbered) {
                throw new DomainException("The purchase request's soft commitment is not available in Cost Center #{$costCenterId}.");
            }

            $availableAfterRelease = (float) $budget->allocated_budget
                - (float) $budget->soft_encumbered
                - (float) $budget->hard_encumbered
                - (float) $budget->spent_amount
                + $prAmount;

            if ($poAmount > $availableAfterRelease) {
                throw new DomainException("Insufficient budget in Cost Center #{$costCenterId} for Purchase Order {$po->po_number}.");
            }

            // Release the soft commitment and post hard encumbrance
            $budget->soft_encumbered -= $prAmount;
            $budget->hard_encumbered += $poAmount;
            $budget->save();

            $po->total_encumbered_amount = $poAmount;
            $po->cost_center_id = $costCenterId;
            $po->save();
        });
    }

    /**
     * Release a purchase-order commitment when its approval chain is rejected.
     */
    public function releaseHardEncumbrance(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po): void {
            if (! $po->cost_center_id) {
                return;
            }

            $year = (int) ($po->requested_at?->format('Y') ?? date('Y'));
            $budget = CostCenterBudget::where('cost_center_id', $po->cost_center_id)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $budget) {
                return;
            }

            $amount = (float) ($po->total_encumbered_amount > 0
                ? $po->total_encumbered_amount
                : $po->total_amount);
            $budget->hard_encumbered = max(0, (float) $budget->hard_encumbered - $amount);
            $budget->save();

            $po->total_encumbered_amount = 0;
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
