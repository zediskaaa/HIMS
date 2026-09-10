<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Enums\Permission;
use App\Enums\ProcurementMethod;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Services\Procurement\ProcurementAuditService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RfqController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:'.Permission::ManageSourcing->value];
    }

    public function __construct(
        private readonly ProcurementAuditService $auditService
    ) {}

    public function index(): JsonResponse
    {
        $rfqs = SourcingRfq::with(['creator', 'purchaseRequest', 'lines.item', 'invitations.supplier'])
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $rfqs,
        ]);
    }

    public function show(SourcingRfq $rfq): JsonResponse
    {
        $rfq->load([
            'creator',
            'purchaseRequest.costCenter',
            'lines.item',
            'invitations.supplier',
            'quotes.supplier',
            'quotes.lines',
            'evaluations',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $rfq,
        ]);
    }

    /**
     * Generate RFQ package from approved PR lines and publish to accredited vendors.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'procurement_method' => ['nullable', 'string'],
            'bidding_type' => ['nullable', 'in:sealed,open'],
            'submission_deadline' => ['required', 'date', 'after:now'],
            'terms_conditions' => ['nullable', 'string'],
            'weight_price' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weight_technical' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weight_quality' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weight_lead_time' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'invited_supplier_ids' => ['required', 'array', 'min:1'],
            'invited_supplier_ids.*' => ['required', 'exists:suppliers,id'],
            'lines' => ['nullable', 'array'],
            'lines.*.item_id' => ['required_with:lines', 'exists:inventory_items,id'],
            'lines.*.target_quantity' => ['required_with:lines', 'integer', 'min:1'],
            'lines.*.uom' => ['nullable', 'string'],
            'lines.*.technical_specifications' => ['nullable', 'string'],
            'lines.*.max_budget_unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $pr = null;
        if (! empty($validated['purchase_request_id'])) {
            $pr = PurchaseRequest::with('lines.item')->findOrFail($validated['purchase_request_id']);
            if ($pr->status->value === 'draft' || $pr->status->value === 'rejected') {
                throw new DomainException("Cannot initiate sourcing RFQ for PR #{$pr->pr_number}: Requisition must be approved first.");
            }
        }

        // Validate supplier accreditation status
        $eligibleSuppliers = Supplier::procurementEligible()
            ->whereIn('id', $validated['invited_supplier_ids'])
            ->get();

        if ($eligibleSuppliers->count() < count($validated['invited_supplier_ids'])) {
            $ineligibleIds = array_diff($validated['invited_supplier_ids'], $eligibleSuppliers->pluck('id')->all());
            return response()->json([
                'status' => 'error',
                'message' => 'One or more invited suppliers are not accredited or have active compliance blocks.',
                'ineligible_supplier_ids' => array_values($ineligibleIds),
            ], 422);
        }

        return DB::transaction(function () use ($validated, $user, $pr, $eligibleSuppliers) {
            $rfqNumber = 'RFQ-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $rfq = SourcingRfq::create([
                'rfq_number' => $rfqNumber,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'purchase_request_id' => $pr?->id,
                'created_by_user_id' => $user->id,
                'procurement_method' => $validated['procurement_method'] ?? ProcurementMethod::RequestForQuotation->value,
                'bidding_type' => $validated['bidding_type'] ?? RfqBiddingType::Sealed->value,
                'submission_deadline' => $validated['submission_deadline'],
                'status' => RfqStatus::Published->value,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'currency' => $pr?->currency ?? 'PHP',
                'weight_price' => $validated['weight_price'] ?? 0.40,
                'weight_technical' => $validated['weight_technical'] ?? 0.30,
                'weight_quality' => $validated['weight_quality'] ?? 0.15,
                'weight_lead_time' => $validated['weight_lead_time'] ?? 0.15,
                'published_at' => now(),
            ]);

            // Create line items from PR lines or custom lines
            $lineNum = 1;
            if ($pr && $pr->lines()->exists()) {
                foreach ($pr->lines as $prLine) {
                    $rfq->lines()->create([
                        'pr_line_id' => $prLine->id,
                        'item_id' => $prLine->item_id,
                        'line_number' => $lineNum++,
                        'target_quantity' => $prLine->quantity,
                        'uom' => $prLine->uom,
                        'item_description' => $prLine->item_description,
                        'technical_specifications' => "Standard clinical specification for {$prLine->item->name}.",
                        'max_budget_unit_price' => $prLine->estimated_unit_price,
                    ]);
                }
                $pr->status = 'sourcing';
                $pr->save();
            } elseif (! empty($validated['lines'])) {
                foreach ($validated['lines'] as $lineData) {
                    $rfq->lines()->create([
                        'item_id' => $lineData['item_id'],
                        'line_number' => $lineNum++,
                        'target_quantity' => $lineData['target_quantity'],
                        'uom' => $lineData['uom'] ?? 'unit',
                        'technical_specifications' => $lineData['technical_specifications'] ?? null,
                        'max_budget_unit_price' => $lineData['max_budget_unit_price'] ?? null,
                    ]);
                }
            }

            // Create vendor invitations with secure portal tokens
            foreach ($eligibleSuppliers as $supplier) {
                $rfq->invitations()->create([
                    'supplier_id' => $supplier->id,
                    'portal_token' => 'RFQ-TOK-'.Str::random(32),
                    'status' => 'invited',
                    'invited_at' => now(),
                ]);
            }

            $this->auditService->record(
                $user,
                'SourcingRfq',
                $rfq->id,
                'created_sourcing_rfq',
                null,
                ['rfq_number' => $rfq->rfq_number, 'deadline' => $rfq->submission_deadline->toIso8601String()]
            );

            $rfq->load(['lines.item', 'invitations.supplier']);

            return response()->json([
                'status' => 'success',
                'message' => 'Sourcing RFQ package created and published to accredited suppliers.',
                'data' => $rfq,
            ], 201);
        });
    }
}
