<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Enums\ApprovalChainType;
use App\Enums\Permission;
use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\SupplierContract;
use App\Services\Procurement\ApprovalRoutingEngine;
use App\Services\Procurement\BudgetEncumbranceService;
use App\Services\Procurement\ProcurementAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:'.Permission::ManageProcurement->value];
    }

    public function __construct(
        private readonly BudgetEncumbranceService $budgetService,
        private readonly ApprovalRoutingEngine $approvalEngine,
        private readonly ProcurementAuditService $auditService
    ) {}

    public function index(): JsonResponse
    {
        $requests = PurchaseRequest::with(['requester', 'costCenter', 'lines.item', 'category'])
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $requests,
        ]);
    }

    public function show(PurchaseRequest $requisition): JsonResponse
    {
        $requisition->load(['requester', 'costCenter', 'category', 'lines.item', 'sourcingRfq', 'approvalChain.steps']);

        return response()->json([
            'status' => 'success',
            'data' => $requisition,
        ]);
    }

    /**
     * Submit Purchase Request with real-time budget encumbrance check.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cost_center_id' => ['required', 'exists:cost_centers,id'],
            'procurement_category_id' => ['nullable', 'exists:procurement_categories,id'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_emergency' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:inventory_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.uom' => ['nullable', 'string', 'max:40'],
            'lines.*.estimated_unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.gl_account_code' => ['nullable', 'string', 'max:50'],
            'lines.*.need_by_date' => ['nullable', 'date'],
            'lines.*.contract_id' => ['nullable', 'exists:supplier_contracts,id'],
        ]);

        $user = $request->user();
        $costCenter = CostCenter::findOrFail($validated['cost_center_id']);

        // Calculate total estimated amount
        $totalAmount = 0.0;
        foreach ($validated['lines'] as $line) {
            $totalAmount += round($line['quantity'] * $line['estimated_unit_price'], 2);
        }

        // Synchronous budget validation check
        if (! $this->budgetService->validateBudgetAvailability($costCenter, $totalAmount)) {
            return response()->json([
                'status' => 'error',
                'message' => "Budget limit exceeded for Cost Center '{$costCenter->name}'. Available funds are insufficient for this request.",
                'requested_amount' => $totalAmount,
            ], 422);
        }

        return DB::transaction(function () use ($validated, $user, $totalAmount, $request) {
            $prNumber = 'PR-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $pr = PurchaseRequest::create([
                'pr_number' => $prNumber,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'requester_id' => $user->id,
                'cost_center_id' => $validated['cost_center_id'],
                'procurement_category_id' => $validated['procurement_category_id'] ?? null,
                'total_estimated_amount' => $totalAmount,
                'currency' => $validated['currency'] ?? 'PHP',
                'priority' => $validated['priority'] ?? 'medium',
                'status' => RequisitionStatus::PendingApproval->value,
                'is_emergency' => (bool) ($validated['is_emergency'] ?? false),
                'submitted_at' => now(),
            ]);

            $lineNumber = 1;
            foreach ($validated['lines'] as $lineData) {
                $item = InventoryItem::find($lineData['item_id']);
                $lineTotal = round($lineData['quantity'] * $lineData['estimated_unit_price'], 2);

                $contractId = $lineData['contract_id'] ?? null;
                $isContracted = false;
                if ($contractId) {
                    $contract = SupplierContract::find($contractId);
                    $isContracted = $contract && $contract->effectiveStatus() === 'active';
                }

                $pr->lines()->create([
                    'item_id' => $item->id,
                    'line_number' => $lineNumber++,
                    'gl_account_code' => $lineData['gl_account_code'] ?? 'GL-MED-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT),
                    'item_description' => $item->name,
                    'quantity' => $lineData['quantity'],
                    'uom' => $lineData['uom'] ?? ($item->unit ?: 'unit'),
                    'estimated_unit_price' => $lineData['estimated_unit_price'],
                    'estimated_total_price' => $lineTotal,
                    'need_by_date' => $lineData['need_by_date'] ?? now()->addDays(14)->toDateString(),
                    'is_contracted_catalog' => $isContracted,
                    'contract_id' => $contractId,
                ]);
            }

            // Reserve soft commitment against budget
            $budget = $this->budgetService->reserveSoftCommitment($pr, $user);

            // Instantiate dynamic Delegation of Authority (DOA) approval chain
            $chain = $this->approvalEngine->instantiateChain(
                ApprovalChainType::PurchaseRequest,
                $pr->id,
                $totalAmount,
                $user
            );

            // Audit logging
            $this->auditService->record(
                $user,
                'PurchaseRequest',
                $pr->id,
                'created_purchase_request',
                null,
                ['pr_number' => $pr->pr_number, 'amount' => $totalAmount, 'status' => $pr->status->value]
            );

            $pr->load(['lines.item', 'costCenter', 'requester']);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Request submitted successfully with soft budget encumbrance.',
                'data' => $pr,
                'budget_status' => [
                    'allocated' => (float) $budget->allocated_budget,
                    'soft_encumbered' => (float) $budget->soft_encumbered,
                    'remaining_available' => $budget->availableBudget(),
                ],
                'approval_chain_id' => $chain->id,
            ], 201);
        });
    }
}
