<?php

namespace App\Services\Procurement;

use App\Enums\ApprovalChainType;
use App\Enums\ApprovalStepStatus;
use App\Enums\UserRole;
use App\Models\ApprovalChain;
use App\Models\ApprovalStep;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApprovalRoutingEngine
{
    /**
     * Determine and instantiate an Approval Chain for a Purchase Request.
     */
    public function routePurchaseRequest(PurchaseRequest $pr): ApprovalChain
    {
        return $this->instantiateChain(
            ApprovalChainType::PurchaseRequest,
            $pr->id,
            (float) $pr->total_estimated_amount,
            $pr->requester
        );
    }

    /**
     * Determine and instantiate an Approval Chain for a Purchase Order.
     */
    public function routePurchaseOrder(PurchaseOrder $po, ?User $initiator = null): ApprovalChain
    {
        $initiator = $initiator ?? ($po->purchaseRequest?->requester ?? User::first());

        return $this->instantiateChain(
            ApprovalChainType::PurchaseOrder,
            $po->id,
            (float) $po->total_amount,
            $initiator
        );
    }

    /**
     * Determine and instantiate an Approval Chain for a Purchase Request or Sourcing Award.
     */
    public function instantiateChain(
        ApprovalChainType $chainType,
        int $targetId,
        float $commitmentAmount,
        User $initiator
    ): ApprovalChain {
        return DB::transaction(function () use ($chainType, $targetId, $commitmentAmount, $initiator) {
            $chain = ApprovalChain::create([
                'chain_type' => $chainType,
                'target_id' => $targetId,
                'total_commitment_amount' => $commitmentAmount,
                'status' => 'pending',
            ]);

            // Build dynamic step graph based on Delegation of Authority (DOA) spend thresholds
            $stepNumber = 1;

            // Tier 1: Always requires Departmental Manager / Supervisor
            $chain->steps()->create([
                'step_number' => $stepNumber++,
                'required_role' => UserRole::InventoryManager->value,
                'status' => ApprovalStepStatus::Pending,
                'threshold_min' => 0.0,
                'threshold_max' => 50000.0,
            ]);

            // Tier 2: > 50,000 PHP requires Category Manager / Administrator
            if ($commitmentAmount > 50000.0) {
                $chain->steps()->create([
                    'step_number' => $stepNumber++,
                    'required_role' => UserRole::Administrator->value,
                    'status' => ApprovalStepStatus::Pending,
                    'threshold_min' => 50000.01,
                    'threshold_max' => 250000.0,
                ]);
            }

            // Tier 3: > 250,000 PHP requires Executive Administration
            if ($commitmentAmount > 250000.0) {
                $chain->steps()->create([
                    'step_number' => $stepNumber++,
                    'required_role' => UserRole::Administrator->value,
                    'status' => ApprovalStepStatus::Pending,
                    'threshold_min' => 250000.01,
                    'threshold_max' => 1000000.0,
                ]);
            }

            // Tier 4: > 1,000,000 PHP requires Super Administrator / Executive sign-off
            if ($commitmentAmount > 1000000.0) {
                $chain->steps()->create([
                    'step_number' => $stepNumber++,
                    'required_role' => UserRole::SuperAdministrator->value,
                    'status' => ApprovalStepStatus::Pending,
                    'threshold_min' => 1000000.01,
                    'threshold_max' => null,
                ]);
            }

            return $chain;
        });
    }

    /**
     * Process an approval decision on the current pending step.
     */
    public function approveStep(
        ApprovalChain $chain,
        User $approver,
        string $decisionNotes = null
    ): ApprovalStep {
        return DB::transaction(function () use ($chain, $approver, $decisionNotes) {
            $step = $chain->currentPendingStep();

            if (! $step) {
                throw new DomainException("Approval Chain #{$chain->id} has no pending steps.");
            }

            // Segregation of Duties: Check if approver is the original requester
            $this->enforceSegregationOfDuties($chain, $approver);

            // Verify approver holds requisite authority/role
            if (! $this->userSatisfiesRoleRequirement($approver, $step->required_role)) {
                throw new DomainException(
                    "Unauthorized: User '{$approver->name}' holds role '{$approver->role->value}', but step #{$step->step_number} requires '{$step->required_role}'."
                );
            }

            // Generate immutable digital signing token
            $signingToken = hash('sha256', "CHAIN:{$chain->id}:STEP:{$step->step_number}:APPROVER:{$approver->id}:AMOUNT:{$chain->total_commitment_amount}:TIME:".now()->timestamp);

            $step->status = ApprovalStepStatus::Approved;
            $step->approver_user_id = $approver->id;
            $step->decision_notes = $decisionNotes ?? 'Approved per Delegation of Authority policy.';
            $step->digital_signature_token = $signingToken;
            $step->decided_at = now();
            $step->save();

            // Check if all steps in chain are approved
            if ($chain->isFullyApproved()) {
                $chain->status = 'approved';
                $chain->save();

                $this->applyApprovedStateToTarget($chain);
            }

            return $step;
        });
    }

    /**
     * Reject an approval step with mandatory justification.
     */
    public function rejectStep(ApprovalChain $chain, User $approver, string $rejectionReason): ApprovalStep
    {
        if (blank($rejectionReason)) {
            throw new DomainException('A rejection reason must be documented for audit compliance.');
        }

        return DB::transaction(function () use ($chain, $approver, $rejectionReason) {
            $step = $chain->currentPendingStep();

            if (! $step) {
                throw new DomainException("Approval Chain #{$chain->id} has no pending steps.");
            }

            $step->status = ApprovalStepStatus::Rejected;
            $step->approver_user_id = $approver->id;
            $step->decision_notes = $rejectionReason;
            $step->decided_at = now();
            $step->save();

            $chain->status = 'rejected';
            $chain->save();

            $this->applyRejectedStateToTarget($chain, $rejectionReason);

            return $step;
        });
    }

    /**
     * Enforces that a user cannot approve their own requisition or award.
     */
    private function enforceSegregationOfDuties(ApprovalChain $chain, User $approver): void
    {
        if ($chain->chain_type === ApprovalChainType::PurchaseRequest) {
            $pr = PurchaseRequest::find($chain->target_id);
            if ($pr && $pr->requester_id === $approver->id) {
                throw new DomainException("Segregation of Duties Violation: Requester cannot approve their own Purchase Request #{$pr->pr_number}.");
            }
        }
    }

    private function userSatisfiesRoleRequirement(User $user, string $requiredRole): bool
    {
        if ($user->isSuperAdministrator()) {
            return true;
        }

        if ($requiredRole === UserRole::Administrator->value && $user->isAdministrator()) {
            return true;
        }

        return $user->role->value === $requiredRole;
    }

    private function applyApprovedStateToTarget(ApprovalChain $chain): void
    {
        if ($chain->chain_type === ApprovalChainType::PurchaseRequest) {
            $pr = PurchaseRequest::find($chain->target_id);
            if ($pr) {
                $pr->status = 'approved';
                $pr->approved_at = now();
                $pr->save();
            }
        } elseif ($chain->chain_type === ApprovalChainType::PurchaseOrder) {
            $po = PurchaseOrder::find($chain->target_id);
            if ($po) {
                $po->status = 'approved';
                $po->save();
            }
        }
    }

    private function applyRejectedStateToTarget(ApprovalChain $chain, string $reason): void
    {
        if ($chain->chain_type === ApprovalChainType::PurchaseRequest) {
            $pr = PurchaseRequest::find($chain->target_id);
            if ($pr) {
                $pr->status = 'rejected';
                $pr->rejection_reason = $reason;
                $pr->save();

                // Release the soft commitment back to cost center
                app(BudgetEncumbranceService::class)->releaseSoftCommitment($pr);
            }
        }
    }
}
