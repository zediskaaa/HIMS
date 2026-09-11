<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ApprovalChainType;
use App\Enums\Permission;
use App\Enums\ProcurementMethod;
use App\Enums\RequisitionStatus;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Models\ApprovalChain;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementCategory;
use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Rules\ProcurementEligibleSupplier;
use App\Services\Procurement\ApprovalRoutingEngine;
use App\Services\Procurement\BudgetEncumbranceService;
use App\Services\Procurement\EvaluationEngine;
use App\Services\Procurement\POConversionService;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProcurementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewProcurement->value, only: ['index']),
            new Middleware('can:'.Permission::CreateRequisition->value, only: ['storeRequest', 'storeEnterpriseRequest']),
            new Middleware('can:'.Permission::ManageSourcing->value, only: ['storeQuote', 'createEnterpriseRfq']),
            new Middleware('can:'.Permission::EvaluateBids->value, only: ['evaluateRfqWeb']),
            new Middleware('can:'.Permission::AwardProcurement->value, only: ['awardRfqWeb']),
            new Middleware('can:'.Permission::ApprovePurchaseOrder->value, only: ['approveStepWeb', 'rejectStepWeb']),
            new Middleware('can:'.Permission::IssuePurchaseOrder->value, only: ['generatePoFromAwardWeb']),
            new Middleware('can:'.Permission::ApproveRequisition->value, only: ['approve']),
        ];
    }

    public function __construct(
        private readonly BudgetEncumbranceService $budgetService,
        private readonly EvaluationEngine $evaluationEngine,
        private readonly ApprovalRoutingEngine $approvalEngine,
        private readonly POConversionService $poConversionService,
        private readonly ProcurementAuditService $auditService
    ) {}

    public function index(): View
    {
        $items = InventoryItem::orderBy('name')->get();
        $suppliers = Supplier::procurementEligible()->orderBy('name')->get();

        // Legacy requests for backward compatibility
        $requests = ProcurementRequest::with(['item', 'supplier'])
            ->latest('id')
            ->get();

        // Enterprise Purchase Requests
        $enterpriseRequests = PurchaseRequest::with(['requester', 'costCenter', 'lines.item'])
            ->latest('id')
            ->get();

        // Auto-close bidding window for published RFQs whose submission deadline has elapsed
        SourcingRfq::where('status', RfqStatus::Published->value)
            ->where('submission_deadline', '<=', now())
            ->update(['status' => RfqStatus::BiddingClosed->value]);

        // Sourcing RFQs
        $rfqs = SourcingRfq::with(['lines.item', 'quotes.supplier', 'quotes.lines', 'invitations.supplier', 'evaluations'])
            ->latest('id')
            ->get();

        // Active Cost Centers
        $costCenters = CostCenter::with('budgets')->where('is_active', true)->get();

        // Procurement Categories
        $categories = ProcurementCategory::where('is_active', true)->orderBy('name')->get();

        // Pending & Active Approval Chains
        $approvalChains = ApprovalChain::with(['steps.approver'])->latest('id')->get();

        // Purchase Orders with line items and revisions
        $purchaseOrders = PurchaseOrder::with(['supplier', 'item', 'lines.item', 'revisions'])
            ->latest('requested_at')
            ->get();

        // Procurement Audit Logs
        $procurementAuditLogs = ProcurementAuditLog::with('user')
            ->latest('id')
            ->take(30)
            ->get();

        return view('inventory.purchases.index', compact(
            'items',
            'suppliers',
            'requests',
            'enterpriseRequests',
            'rfqs',
            'costCenters',
            'categories',
            'approvalChains',
            'purchaseOrders',
            'procurementAuditLogs'
        ));
    }

    // -------------------------------------------------- Legacy Endpoints (Preserved)

    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'supplier_id' => ['nullable', new ProcurementEligibleSupplier],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approval_notes' => ['nullable', 'string', 'max:255'],
            'evaluation_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'evaluation_status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        ProcurementRequest::create([
            ...$validated,
            'request_number' => 'REQ-'.now()->format('YmdHis'),
        ]);

        return redirect()->route('inventory.purchases')->with('success', 'Procurement request created successfully.');
    }

    public function storeQuote(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'procurement_request_id' => ['required', 'exists:procurement_requests,id'],
            'supplier_id' => ['required', new ProcurementEligibleSupplier],
            'quoted_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        SupplierQuote::create($validated);

        return redirect()->route('inventory.purchases')->with('success', 'Supplier quote submitted successfully.');
    }

    public function approve(Request $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $validated = $request->validate([
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approval_notes' => ['nullable', 'string', 'max:255'],
            'evaluation_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'evaluation_status' => ['nullable', 'in:pending,approved,rejected'],
            'supplier_id' => ['nullable', new ProcurementEligibleSupplier],
        ]);

        $procurementRequest->fill($validated);
        $procurementRequest->status = 'approved';
        $procurementRequest->save();

        return redirect()->route('inventory.purchases')->with('success', 'Procurement request approved.');
    }

    // ---------------------------------------------- Enterprise S2P / P2P Web Handlers

    /**
     * Create Multi-Line Enterprise Purchase Request with Budget Validation.
     */
    public function storeEnterpriseRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'cost_center_id' => ['required', 'exists:cost_centers,id'],
            'procurement_category_id' => ['nullable', 'exists:procurement_categories,id'],
            'procurement_method' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'description' => ['nullable', 'string'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'estimated_unit_price' => ['required', 'numeric', 'min:0.01'],
            'need_by_date' => ['nullable', 'date'],
        ]);

        $costCenter = CostCenter::findOrFail($validated['cost_center_id']);
        $totalAmount = round($validated['quantity'] * $validated['estimated_unit_price'], 2);

        if (! $this->budgetService->validateBudgetAvailability($costCenter, $totalAmount)) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['budget' => "Budget limit exceeded for Cost Center '{$costCenter->name}'. Available uncommitted budget is insufficient."]);
        }

        DB::transaction(function () use ($validated, $costCenter, $totalAmount, $request) {
            $pr = PurchaseRequest::create([
                'pr_number' => 'PR-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'requester_id' => auth()->id(),
                'cost_center_id' => $costCenter->id,
                'procurement_category_id' => $validated['procurement_category_id'] ?? null,
                'procurement_method' => $validated['procurement_method'] ?? ProcurementMethod::RequestForQuotation->value,
                'total_estimated_amount' => $totalAmount,
                'currency' => 'PHP',
                'priority' => $validated['priority'],
                'status' => RequisitionStatus::PendingApproval->value,
                'submitted_at' => now(),
            ]);

            $item = InventoryItem::find($validated['item_id']);

            $pr->lines()->create([
                'item_id' => $item->id,
                'line_number' => 1,
                'gl_account_code' => 'GL-MED-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT),
                'item_description' => $item->name,
                'quantity' => $validated['quantity'],
                'uom' => $item->unit ?: 'unit',
                'estimated_unit_price' => $validated['estimated_unit_price'],
                'estimated_total_price' => $totalAmount,
                'need_by_date' => $validated['need_by_date'] ?? now()->addDays(14)->toDateString(),
            ]);

            $this->budgetService->reserveSoftCommitment($pr, auth()->user());

            $this->approvalEngine->instantiateChain(
                ApprovalChainType::PurchaseRequest,
                $pr->id,
                $totalAmount,
                auth()->user()
            );

            $this->auditService->record(
                auth()->user(),
                'PurchaseRequest',
                $pr->id,
                'created_purchase_request',
                null,
                ['pr_number' => $pr->pr_number, 'amount' => $totalAmount]
            );
        });

        return redirect()->route('inventory.purchases')->with('success', 'Purchase Request created with soft budget commitment.');
    }

    /**
     * Create Sourcing RFQ Package from Web UI.
     */
    public function createEnterpriseRfq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'bidding_type' => ['required', 'in:sealed,open'],
            'submission_deadline' => ['required', 'date', 'after:now'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'target_quantity' => ['required', 'integer', 'min:1'],
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['required', 'exists:suppliers,id'],
            'terms_conditions' => ['nullable', 'string'],
        ]);

        $eligibleSuppliers = Supplier::procurementEligible()
            ->whereIn('id', $validated['supplier_ids'])
            ->get();

        if ($eligibleSuppliers->count() < count($validated['supplier_ids'])) {
            return redirect()->route('inventory.purchases')
                ->withErrors(['rfq' => 'One or more selected suppliers are not accredited or eligible for procurement.']);
        }

        DB::transaction(function () use ($validated, $eligibleSuppliers) {
            $rfq = SourcingRfq::create([
                'rfq_number' => 'RFQ-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'created_by_user_id' => auth()->id(),
                'procurement_method' => ProcurementMethod::RequestForQuotation->value,
                'bidding_type' => $validated['bidding_type'],
                'submission_deadline' => $validated['submission_deadline'],
                'status' => RfqStatus::Published->value,
                'terms_conditions' => $validated['terms_conditions'] ?? 'Standard hospital commercial terms and warranty apply.',
                'published_at' => now(),
            ]);

            $item = InventoryItem::find($validated['item_id']);

            $rfq->lines()->create([
                'item_id' => $item->id,
                'line_number' => 1,
                'target_quantity' => $validated['target_quantity'],
                'uom' => $item->unit ?: 'unit',
                'item_description' => $item->name,
                'technical_specifications' => "Standard specifications for {$item->name}.",
                'max_budget_unit_price' => $item->unit_cost,
            ]);

            foreach ($eligibleSuppliers as $supplier) {
                $rfq->invitations()->create([
                    'supplier_id' => $supplier->id,
                    'portal_token' => 'RFQ-TOK-'.Str::random(32),
                    'status' => 'invited',
                    'invited_at' => now(),
                ]);
            }

            $this->auditService->record(
                auth()->user(),
                'SourcingRfq',
                $rfq->id,
                'created_sourcing_rfq',
                null,
                ['rfq_number' => $rfq->rfq_number, 'deadline' => $rfq->submission_deadline->toIso8601String()]
            );
        });

        return redirect()->route('inventory.purchases')->with('success', 'Sourcing RFQ package published to accredited suppliers.');
    }

    /**
     * Execute RFQ Evaluation from Web UI.
     */
    public function evaluateRfqWeb(Request $request, SourcingRfq $rfq): RedirectResponse
    {
        try {
            $this->evaluationEngine->evaluateRfq($rfq, auth()->user());

            $this->auditService->record(
                auth()->user(),
                'SourcingRfq',
                $rfq->id,
                'evaluated_sourcing_rfq',
                null,
                ['status' => 'under_evaluation']
            );

            return redirect()->route('inventory.purchases')->with('success', "RFQ #{$rfq->rfq_number} unsealed and comparative scoring matrix evaluated.");
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['evaluate' => $e->getMessage()]);
        }
    }

    /**
     * Award RFQ to winning quotation from Web UI.
     */
    public function awardRfqWeb(Request $request, SourcingRfq $rfq): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_quote_id' => ['required', 'exists:supplier_quotes,id'],
            'justification_notes' => ['nullable', 'string'],
        ]);

        $quote = SupplierQuote::where('sourcing_rfq_id', $rfq->id)->findOrFail($validated['supplier_quote_id']);

        try {
            $chain = $this->approvalEngine->instantiateChain(
                ApprovalChainType::SourcingAward,
                $rfq->id,
                $quote->totalLandedCost(),
                auth()->user()
            );

            $this->auditService->record(
                auth()->user(),
                'SourcingRfq',
                $rfq->id,
                'awarded_sourcing_rfq',
                null,
                ['quote_id' => $quote->id, 'supplier_id' => $quote->supplier_id]
            );

            return redirect()->route('inventory.purchases')->with('success', "Award recommendation recorded for Supplier '{$quote->supplier->name}'. DOA approval initiated.");
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['award' => $e->getMessage()]);
        }
    }

    /**
     * Approve a step in an approval chain from Web UI.
     */
    public function approveStepWeb(Request $request, ApprovalChain $chain): RedirectResponse
    {
        $validated = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->approvalEngine->approveStep($chain, auth()->user(), $validated['decision_notes'] ?? null);

            return redirect()->route('inventory.purchases')->with('success', 'Approval step authorized successfully.');
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['approval' => $e->getMessage()]);
        }
    }

    /**
     * Reject a step in an approval chain from Web UI.
     */
    public function rejectStepWeb(Request $request, ApprovalChain $chain): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->approvalEngine->rejectStep($chain, auth()->user(), $validated['rejection_reason']);

            return redirect()->route('inventory.purchases')->with('info', 'Request has been rejected and funds released.');
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['approval' => $e->getMessage()]);
        }
    }

    /**
     * Convert awarded quote to PO from Web UI.
     */
    public function generatePoFromAwardWeb(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sourcing_rfq_id' => ['required', 'exists:sourcing_rfqs,id'],
            'supplier_quote_id' => ['required', 'exists:supplier_quotes,id'],
        ]);

        $rfq = SourcingRfq::findOrFail($validated['sourcing_rfq_id']);
        $quote = SupplierQuote::where('sourcing_rfq_id', $rfq->id)->findOrFail($validated['supplier_quote_id']);

        try {
            $po = $this->poConversionService->convertAwardToPO($rfq, $quote, auth()->user());

            $this->auditService->record(
                auth()->user(),
                'PurchaseOrder',
                $po->id,
                'issued_purchase_order',
                null,
                ['po_number' => $po->po_number, 'amount' => $po->total_amount]
            );

            return redirect()->route('inventory.purchases')->with('success', "Purchase Order {$po->po_number} generated with hard encumbered liability.");
        } catch (DomainException $e) {
            return redirect()->route('inventory.purchases')->withErrors(['po' => $e->getMessage()]);
        }
    }
}
